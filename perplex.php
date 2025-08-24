<?php
/* perplex.php — Perplexity-tyylinen haku (Google CSE → GPT-yhteenveto)
 * Ympäristömuuttujat (.htaccess tai palvelimen env):
 *   SetEnv OPENAI_API_KEY "..."
 *   SetEnv GOOGLE_CSE_KEY "..."
 *   SetEnv GOOGLE_CSE_CX "..."   ; Search engine ID
 */

function envget($k){
  // 1) getenv (Apache/PHP env)
  $v = getenv($k);
  if ($v !== false && $v !== '') return trim($v);

  // 2) $_SERVER (normaali + REDIRECT_*)
  if (!empty($_SERVER[$k])) return trim($_SERVER[$k]);
  if (!empty($_SERVER['REDIRECT_'.$k])) return trim($_SERVER['REDIRECT_'.$k]);

  // 3) $_ENV
  if (!empty($_ENV[$k])) return trim($_ENV[$k]);

  // 4) Fallback: paikallinen salaisuus (valinnainen)
  $secret = __DIR__.'/_secrets.php';
  if (is_readable($secret)) {
    require_once $secret;
    if (defined($k)) return constant($k);
  }
  return '';
}
function h($s){ return htmlspecialchars($s??'', ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function is_cli(){ return php_sapi_name()==='cli'; }

// Pikadebugi: /perplex.php?env=1 näyttää löytyvätkö avaimet
if (isset($_GET['env'])) {
  header('Content-Type: text/plain; charset=utf-8');
  foreach (['OPENAI_API_KEY','GOOGLE_CSE_KEY','GOOGLE_CSE_CX'] as $k) {
    $ok = envget($k) ? 'OK' : 'MISSING';
    echo "$k: $ok\n";
    if ($ok === 'MISSING') {
      echo "  SERVER? ".(isset($_SERVER[$k])?'YES':'no')."\n";
      echo "  REDIRECT? ".(isset($_SERVER['REDIRECT_'.$k])?'YES':'no')."\n";
    }
  }
  exit;
}

$OPENAI = envget('OPENAI_API_KEY');
$GKEY   = envget('GOOGLE_CSE_KEY');
$GCX    = envget('GOOGLE_CSE_CX');

$DB_FILE = __DIR__ . '/perplex_cache.sqlite';
$TTL_SEC = 24*3600; // 24 h välimuisti

/* --- tiny SQLite cache --- */
function db(){
  static $pdo=null; global $DB_FILE;
  if($pdo) return $pdo;
  $pdo = new PDO('sqlite:'.$DB_FILE);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->exec("CREATE TABLE IF NOT EXISTS cache(
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      qhash TEXT NOT NULL,
      payload_json TEXT NOT NULL,
      created_at INTEGER NOT NULL
  ); CREATE UNIQUE INDEX IF NOT EXISTS idx_qhash ON cache(qhash);");
  return $pdo;
}
function cache_get($qhash, $ttl){
  $stmt = db()->prepare("SELECT payload_json, created_at FROM cache WHERE qhash=?");
  $stmt->execute([$qhash]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if(!$row) return null;
  if(time() - intval($row['created_at']) > $ttl) return null;
  return json_decode($row['payload_json'], true);
}
function cache_put($qhash, $json){
  $stmt = db()->prepare("INSERT OR REPLACE INTO cache(qhash,payload_json,created_at) VALUES(?,?,?)");
  $stmt->execute([$qhash, json_encode($json, JSON_UNESCAPED_UNICODE), time()]);
}

/* --- Google Custom Search --- */
function google_cse($key, $cx, $q, $num=8){
  $url = 'https://www.googleapis.com/customsearch/v1?'.http_build_query([
    'key'=>$key,'cx'=>$cx,'q'=>$q,'num'=>$num,'safe'=>'active','hl'=>'fi'
  ]);
  $ch = curl_init($url);
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>20, CURLOPT_HTTPGET=>true]);
  $res = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 0;
  $err  = curl_error($ch);
  curl_close($ch);
  if($res===false) return [null, "google curl error: $err (HTTP $http)"];
  $j = json_decode($res, true);
  if(!$j) return [null, "google json error: $res"];
  $items = [];
  foreach(($j['items'] ?? []) as $i=>$it){
    $items[] = [
      'id'     => $i+1,
      'title'  => $it['title'] ?? '',
      'link'   => $it['link'] ?? '',
      'snippet'=> $it['snippet'] ?? '',
      'display'=> $it['displayLink'] ?? ''
    ];
  }
  return [$items, null];
}

/* --- OpenAI: selkokielinen yhteenveto + viitteet --- */
function call_openai_chat($apiKey, $messages, $model='gpt-4o-mini'){
  $ch = curl_init('https://api.openai.com/v1/chat/completions');
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true,
    CURLOPT_HTTPHEADER=>[
      'Content-Type: application/json',
      'Authorization: Bearer '.$apiKey
    ],
    CURLOPT_POSTFIELDS=>json_encode([
      'model'=>$model,'temperature'=>0.2,'messages'=>$messages
    ], JSON_UNESCAPED_UNICODE),
    CURLOPT_TIMEOUT=>45
  ]);
  $res = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 0;
  $err  = curl_error($ch);
  curl_close($ch);
  if($res===false) return [null, "openai curl error: $err (HTTP $http)"];
  $j = json_decode($res, true);
  $txt = $j['choices'][0]['message']['content'] ?? null;
  if(!$txt) return [null, "openai error: $res"];
  return [$txt, null];
}

function build_summary_prompt($query, $mode, $sources){
  // Muodosta kompakti lähdelista LLM:lle
  $srcText = "";
  foreach($sources as $s){
    $srcText .= "[{$s['id']}] {$s['title']} — {$s['snippet']} (URL: {$s['link']})\n";
  }
  $style = $mode==='steps'
    ? "Produce a short, numbered, step-by-step guide in Finnish easy language (selkokieli)."
    : "Produce a short overview in Finnish easy language (selkokieli) using bullet points where helpful.";
  $sys = "You answer ONLY based on the given sources. Never invent facts. Add inline numeric citations like [1], [2] mapping to the provided sources. Keep it concise and helpful.";
  $user = "Query: {$query}\n\nSources:\n{$srcText}\n\nTask: {$style} End with a one-sentence takeaway.";
  return [
    ['role'=>'system','content'=>$sys],
    ['role'=>'user','content'=>$user]
  ];
}

/* --- handle request --- */
$error = null; $data = null;
$q = trim($_POST['q'] ?? '');
$mode = $_POST['mode'] ?? 'overview'; // overview|steps

if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!$OPENAI || !$GKEY || !$GCX){
    $error = "Puuttuva avain: varmista OPENAI_API_KEY, GOOGLE_CSE_KEY ja GOOGLE_CSE_CX.";
  } elseif($q===''){
    $error = "Anna hakulause.";
  } else {
    $qhash = hash('sha256', "perplex|$mode|".mb_strtolower($q,'UTF-8'));
    $cached = cache_get($qhash, $TTL_SEC);
    if($cached){ $data=$cached; }
    else{
      list($items, $gerr) = google_cse($GKEY, $GCX, $q, 8);
      if($gerr){ $error = $gerr; }
      elseif(!$items){ $error = "Ei hakutuloksia."; }
      else{
        list($summary, $oerr) = call_openai_chat($OPENAI, build_summary_prompt($q, $mode, $items));
        if($oerr){ $error=$oerr; }
        else{
          $data = [
            'query'=>$q,
            'mode'=>$mode,
            'summary'=>$summary,
            'sources'=>$items,
            'ts'=>time()
          ];
          cache_put($qhash, $data);
        }
      }
    }
  }
}
?>
<!doctype html>
<html lang="fi">
<head>
<meta charset="utf-8">
<title>Perplexity-tyylinen haku</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
:root{--fg:#111;--muted:#666;--bg:#fafafa;--card:#fff;--accent:#0b57d0;}
body{font:16px/1.55 system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,sans-serif;color:var(--fg);background:var(--bg);margin:0;padding:2rem;}
.wrap{max-width:960px;margin:0 auto;}
h1{margin:0 0 1rem;font-size:1.6rem}
.card{background:var(--card);border:1px solid #e7e7e7;border-radius:12px;padding:1rem 1.25rem;margin-bottom:1rem;box-shadow:0 1px 2px rgba(0,0,0,.04);}
label{display:block;font-weight:600;margin-top:.4rem;}
input[type=text]{width:100%;padding:.7rem;border:1px solid #ddd;border-radius:10px;font:inherit;}
select{width:100%;padding:.6rem;border:1px solid #ddd;border-radius:10px;font:inherit;}
.btn{display:inline-block;background:var(--accent);color:#fff;border:none;padding:.7rem 1rem;border-radius:10px;font-weight:700;cursor:pointer}
.row{display:grid;grid-template-columns:1fr 200px;gap:12px}
.summary{white-space:pre-wrap}
small.muted{color:var(--muted)}
.src{display:flex;flex-direction:column;gap:.25rem}
.src a{color:#0b57d0;text-decoration:none}
pre{background:#0b1020;color:#e6edf3;border-radius:12px;padding:1rem;overflow:auto}
.toggle{cursor:pointer;color:#0b57d0}
.hidden{display:none}
.bad{color:#b00020;font-weight:700}
</style>
</head>
<body>
<div class="wrap">
  <h1>Perplexity‑tyylinen haku</h1>

  <div class="card">
    <form method="post">
      <label for="q">Hakulause</label>
      <input id="q" name="q" type="text" placeholder="Esim. Miten saan kirjastokortin Helsingissä" value="<?=h($q)?>">
      <div class="row" style="margin-top:.5rem">
        <div>
          <label for="mode">Vastaustyyppi</label>
          <select id="mode" name="mode">
            <option value="overview" <?=($mode==='overview'?'selected':'')?>>Yleiskuva</option>
            <option value="steps" <?=($mode==='steps'?'selected':'')?>>Toimintaohjeet (vaiheittain)</option>
          </select>
          <small class="muted">Lopputulos selkokielellä + numeroviitteet [1]…</small>
        </div>
        <div style="display:flex;align-items:end;justify-content:flex-end">
          <button class="btn" type="submit">Hae & tiivistä</button>
        </div>
      </div>
    </form>
  </div>

  <?php if($error): ?>
    <div class="card bad">Virhe: <?=h($error)?></div>
  <?php elseif($data): ?>
    <div class="card">
      <div class="summary"><?=nl2br(h($data['summary']))?></div>
      <hr>
      <div class="src">
        <strong>Lähteet</strong>
        <ol>
          <?php foreach(($data['sources']??[]) as $s): ?>
            <li>
              <a href="<?=h($s['link'])?>" target="_blank" rel="noopener">
                <?=h($s['title'])?>
              </a>
              <div><small class="muted">(<?=h($s['display'])?>)</small></div>
              <div><small><?=h($s['snippet'])?></small></div>
            </li>
          <?php endforeach; ?>
        </ol>
        <small class="muted">Päivitetty: <?=date('Y-m-d H:i', $data['ts'])?> · Välimuisti 24 h</small>
      </div>
    </div>

    <div class="card">
      <span class="toggle" onclick="document.getElementById('raw').classList.toggle('hidden')">Näytä/piilota raakadata</span>
      <pre id="raw" class="hidden"><?=h(json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE))?></pre>
    </div>
  <?php endif; ?>

  <div class="card">
    <strong>/Tietoturva & kustannukset</strong>
    <ul>
      <li>Kaikki avaimet vain palvelimella: <code>OPENAI_API_KEY</code>, <code>GOOGLE_CSE_KEY</code>, <code>GOOGLE_CSE_CX</code>.</li>
      <li>Välimuisti (SQLite) vähentää kutsuja ja nopeuttaa vastauksia.</li>
      <li>Haku tekee: 1× Google CSE ‑kutsu + 1× GPT‑kutsu.</li>
    </ul>
  </div>
</div>
</body>
</html>
