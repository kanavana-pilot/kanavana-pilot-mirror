<?php
/* perplex.php — Perplexity-tyylinen haku tekstille + kuvagallerialle
 * ENV (.htaccess tai _secrets.php fallback):
 *   SetEnv OPENAI_API_KEY "..."
 *   SetEnv GOOGLE_CSE_KEY "..."
 *   SetEnv GOOGLE_CSE_CX  "..."
 */

function envget($k){
  $v = getenv($k);                 if ($v !== false && $v !== '') return trim($v);
  if (!empty($_SERVER[$k]))        return trim($_SERVER[$k]);
  if (!empty($_SERVER['REDIRECT_'.$k])) return trim($_SERVER['REDIRECT_'.$k]);
  if (!empty($_ENV[$k]))           return trim($_ENV[$k]);
  $secret = __DIR__.'/_secrets.php';
  if (is_readable($secret)) { require_once $secret; if (defined($k)) return constant($k); }
  return '';
}
function h($s){ return htmlspecialchars($s??'', ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }

if (isset($_GET['env'])) {
  header('Content-Type: text/plain; charset=utf-8');
  foreach (['OPENAI_API_KEY','GOOGLE_CSE_KEY','GOOGLE_CSE_CX'] as $k) {
    echo $k.': '.(envget($k)?'OK':'MISSING')."\n";
  }
  exit;
}

$OPENAI = envget('OPENAI_API_KEY');
$GKEY   = envget('GOOGLE_CSE_KEY');
$GCX    = envget('GOOGLE_CSE_CX');

$DB_FILE = __DIR__.'/perplex_cache.sqlite';
$TTL_SEC = 24*3600;

/* --- SQLite cache --- */
function db(){ static $pdo=null; global $DB_FILE;
  if($pdo) return $pdo;
  $pdo = new PDO('sqlite:'.$DB_FILE);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->exec("CREATE TABLE IF NOT EXISTS cache(
      qhash TEXT PRIMARY KEY, payload_json TEXT NOT NULL, created_at INTEGER NOT NULL)");
  return $pdo;
}
function cache_get($qhash,$ttl){ $s=db()->prepare("SELECT payload_json,created_at FROM cache WHERE qhash=?"); $s->execute([$qhash]);
  if(!$r=$s->fetch(PDO::FETCH_ASSOC)) return null; if(time()-$r['created_at']>$ttl) return null;
  return json_decode($r['payload_json'],true);
}
function cache_put($qhash,$json){ $s=db()->prepare("INSERT OR REPLACE INTO cache(qhash,payload_json,created_at) VALUES(?,?,?)");
  $s->execute([$qhash,json_encode($json,JSON_UNESCAPED_UNICODE),time()]);
}

/* --- Google CSE (teksti) --- */
function google_cse($key,$cx,$q,$num=8){
  $url='https://www.googleapis.com/customsearch/v1?'.http_build_query([
    'key'=>$key,'cx'=>$cx,'q'=>$q,'num'=>$num,'safe'=>'active','hl'=>'fi'
  ]);
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20]);
  $res=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE)?:0; $err=curl_error($ch); curl_close($ch);
  if($res===false) return [null,"google curl error: $err (HTTP $http)"];
  $j=json_decode($res,true); if(!$j) return [null,"google json error: $res"];
  $items=[];
  foreach(($j['items']??[]) as $i=>$it){
    $items[]=[
      'id'=>$i+1,'title'=>$it['title']??'','link'=>$it['link']??'',
      'snippet'=>$it['snippet']??'','display'=>$it['displayLink']??''
    ];
  }
  return [$items,null];
}

/* --- Google CSE (kuvat) --- */
function google_cse_images($key,$cx,$q,$num=8){
  $url='https://www.googleapis.com/customsearch/v1?'.http_build_query([
    'key'=>$key,'cx'=>$cx,'q'=>$q,'num'=>$num,'safe'=>'active','hl'=>'fi','searchType'=>'image'
  ]);
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20]);
  $res=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE)?:0; $err=curl_error($ch); curl_close($ch);
  if($res===false) return [null,"google img curl error: $err (HTTP $http)"];
  $j=json_decode($res,true); if(!$j) return [null,"google img json error: $res"];
  $items=[];
  foreach(($j['items']??[]) as $i=>$it){
    $img=$it['image']??[];
    $items[]=[
      'id'=>$i+1,
      'thumb'=>$img['thumbnailLink']??($it['link']??''),
      'src'=>$it['link']??'',
      'context'=>$img['contextLink']??($it['image']['contextLink']??$it['link']??''),
      'title'=>$it['title']??'',
      'display'=>$it['displayLink']??''
    ];
  }
  return [$items,null];
}

/* --- OpenAI tiivistys --- */
function call_openai_chat($apiKey,$messages,$model='gpt-4o-mini'){
  $ch=curl_init('https://api.openai.com/v1/chat/completions');
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,
    CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.$apiKey],
    CURLOPT_POSTFIELDS=>json_encode(['model'=>$model,'temperature'=>0.2,'messages'=>$messages],JSON_UNESCAPED_UNICODE),
    CURLOPT_TIMEOUT=>45]);
  $res=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE)?:0; $err=curl_error($ch); curl_close($ch);
  if($res===false) return [null,"openai curl error: $err (HTTP $http)"];
  $j=json_decode($res,true); $txt=$j['choices'][0]['message']['content']??null;
  if(!$txt) return [null,"openai error: $res"]; return [$txt,null];
}
function prompt_for_summary($query,$mode,$sources){
  $src=""; foreach($sources as $s){ $src.="[{$s['id']}] {$s['title']} — {$s['snippet']} (URL: {$s['link']})\n"; }
  $style=$mode==='steps'
    ? "Produce a short, numbered step-by-step guide in Finnish easy language (selkokieli)."
    : "Produce a short overview in Finnish easy language (selkokieli), add bullet points where helpful.";
  return [
    ['role'=>'system','content'=>'Answer ONLY from the given sources. Add inline numeric citations [1]… matching the sources. Be concise.'],
    ['role'=>'user','content'=>"Query: {$query}\n\nSources:\n{$src}\n\nTask: {$style} End with a one-sentence takeaway."]
  ];
}

/* --- Request handling --- */
$error=null; $data=null;
$q=trim($_POST['q']??'');
$mode=$_POST['mode']??'overview';           // overview|steps
$want_images=isset($_POST['with_images']);   // checkbox

if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!$OPENAI || !$GKEY || !$GCX){ $error="Puuttuva avain: varmista OPENAI_API_KEY, GOOGLE_CSE_KEY ja GOOGLE_CSE_CX."; }
  elseif($q===''){ $error="Anna hakulause."; }
  else{
    $qhash=hash('sha256',"perplex|$mode|img:".($want_images?1:0)."|".mb_strtolower($q,'UTF-8'));
    if($cached=cache_get($qhash,$TTL_SEC)){ $data=$cached; }
    else{
      list($sources,$gerr)=google_cse($GKEY,$GCX,$q,8);
      if($gerr){ $error=$gerr; }
      elseif(!$sources){ $error="Ei hakutuloksia."; }
      else{
        list($summary,$oerr)=call_openai_chat($OPENAI,prompt_for_summary($q,$mode,$sources));
        if($oerr){ $error=$oerr; }
        else{
          $images=[]; $imgErr=null;
          if($want_images){
            list($images,$imgErr)=google_cse_images($GKEY,$GCX,$q,8);
            if($imgErr) $images=[]; // ei kaadeta sivua, vain tyhjä galleria
          }
          $data=['query'=>$q,'mode'=>$mode,'summary'=>$summary,'sources'=>$sources,'images'=>$images,'ts'=>time()];
          cache_put($qhash,$data);
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
.src a{color:#0b57d0;text-decoration:none}
pre{background:#0b1020;color:#e6edf3;border-radius:12px;padding:1rem;overflow:auto}
.bad{color:#b00020;font-weight:700}
.gallery{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px}
.gallery a{display:block;background:#fff;border:1px solid #e7e7e7;border-radius:10px;overflow:hidden}
.gallery img{width:100%;height:110px;object-fit:cover;display:block}
.switch{display:flex;align-items:center;gap:.5rem;margin-top:.5rem}
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
          <div class="switch">
            <input id="with_images" type="checkbox" name="with_images" <?= $want_images?'checked':''?>>
            <label for="with_images" style="margin:0;">Näytä myös kuvat</label>
          </div>
          <small class="muted">Selkokielinen tiivistelmä + numeroviitteet [1]… ja valinnainen kuvagalleria.</small>
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
    </div>

    <?php if(!empty($data['images'])): ?>
      <div class="card">
        <strong>Kuvat</strong>
        <div class="gallery">
          <?php foreach($data['images'] as $im): ?>
            <a href="<?=h($im['context'] ?: $im['src'])?>" target="_blank" rel="noopener" title="<?=h($im['title'])?>">
              <img src="<?=h($im['thumb'] ?: $im['src'])?>" alt="<?=h($im['title'])?>">
            </a>
          <?php endforeach; ?>
        </div>
        <small class="muted">Klikkaa kuvaa – avaa alkuperäisen sivun.</small>
      </div>
    <?php endif; ?>

    <div class="card">
      <div class="src">
        <strong>Lähteet</strong>
        <ol>
          <?php foreach(($data['sources']??[]) as $s): ?>
            <li>
              <a href="<?=h($s['link'])?>" target="_blank" rel="noopener"><?=h($s['title'])?></a>
              <div><small class="muted">(<?=h($s['display'])?>)</small></div>
              <div><small><?=h($s['snippet'])?></small></div>
            </li>
          <?php endforeach; ?>
        </ol>
        <small class="muted">Päivitetty: <?=date('Y-m-d H:i',$data['ts'])?> · Välimuisti 24 h</small>
      </div>
    </div>

    <div class="card">
      <details>
        <summary>Näytä/piilota raakadata</summary>
        <pre><?=h(json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE))?></pre>
      </details>
    </div>
  <?php endif; ?>

  <div class="card">
    <strong>/Tietoturva & kulut</strong>
    <ul>
      <li>Avaimet vain palvelimella: <code>OPENAI_API_KEY</code>, <code>GOOGLE_CSE_KEY</code>, <code>GOOGLE_CSE_CX</code>.</li>
      <li>Kuvahaku käyttää samaa Google‑API:a (lasketaan yhdeksi hauksi).</li>
      <li>Välimuisti (SQLite) vähentää kutsuja ja nopeuttaa vastauksia.</li>
    </ul>
  </div>
</div>
</body>
</html>
