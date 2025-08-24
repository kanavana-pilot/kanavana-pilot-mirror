<?php
/* google-grid.php — Teksti + kuvahaku vierekkäin (kaksi CSE:tä)
 * ENV (.htaccess tai _secrets.php):
 *   SetEnv GOOGLE_CSE_KEY "..."
 *   SetEnv GOOGLE_CSE_CX "..."            ; tekstihaku
 *   SetEnv GOOGLE_CSE_CX_IMAGES "..."     ; kuvahaku
 */

function envget($k){
  $v = getenv($k); if ($v !== false && $v !== '') return trim($v);
  if (!empty($_SERVER[$k])) return trim($_SERVER[$k]);
  if (!empty($_SERVER['REDIRECT_'.$k])) return trim($_SERVER['REDIRECT_'.$k]);
  if (!empty($_ENV[$k])) return trim($_ENV[$k]);
  $secret = __DIR__.'/_secrets.php';
  if (is_readable($secret)) { require_once $secret; if (defined($k)) return constant($k); }
  return '';
}
function h($s){ return htmlspecialchars($s??'', ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }

if (isset($_GET['env'])) {
  header('Content-Type: text/plain; charset=utf-8');
  foreach (['GOOGLE_CSE_KEY','GOOGLE_CSE_CX','GOOGLE_CSE_CX_IMAGES'] as $k) {
    echo $k.': '.(envget($k)?'OK':'MISSING')."\n";
  }
  exit;
}

$GKEY  = envget('GOOGLE_CSE_KEY');
$CX_TXT= envget('GOOGLE_CSE_CX');
$CX_IMG= envget('GOOGLE_CSE_CX_IMAGES');

$DB_FILE = __DIR__.'/google_grid_cache.sqlite';
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

/* --- Google CSE --- */
function g_cse($key,$cx,$q,$num=10){
  $url='https://www.googleapis.com/customsearch/v1?'.http_build_query([
    'key'=>$key,'cx'=>$cx,'q'=>$q,'num'=>$num,'safe'=>'active','hl'=>'fi'
  ]);
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20]);
  $res=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE)?:0; $err=curl_error($ch); curl_close($ch);
  if($res===false) return [null,"curl error: $err (HTTP $http)"];
  $j=json_decode($res,true); if(!$j) return [null,"json error: $res"];
  $items=[];
  foreach(($j['items']??[]) as $i=>$it){
    $items[]=['title'=>$it['title']??'','link'=>$it['link']??'','snippet'=>$it['snippet']??'','display'=>$it['displayLink']??''];
  }
  return [$items,null];
}
function g_cse_images($key,$cx,$q,$num=10,$fallbackCx=null){
  // Google image search: num max 10
  $url='https://www.googleapis.com/customsearch/v1?'.http_build_query([
    'key'=>$key,'cx'=>$cx,'q'=>$q,'num'=>min(10,$num),
    'safe'=>'active','hl'=>'fi','searchType'=>'image'
  ]);
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20]);
  $res=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE)?:0; $err=curl_error($ch); curl_close($ch);
  if($res===false) return [null,"img curl error: $err (HTTP $http)"];
  $j=json_decode($res,true); if(!$j) return [null,"img json error: $res"];

  $items=[];
  foreach(($j['items']??[]) as $i=>$it){
    $img=$it['image']??[];
    $items[]=[
      'thumb'=>$img['thumbnailLink']??($it['link']??''),
      'src'=>$it['link']??'',
      'context'=>$img['contextLink']??($it['link']??''),
      'title'=>$it['title']??'',
      'display'=>$it['displayLink']??''
    ];
  }

  // Jos ei kuvia, kokeillaan varmistuksena toisella CX:llä (tekstikone),
  // koska monesti image-haku toimii myös siinä kun searchType=image on.
  if (empty($items) && $fallbackCx) {
    return g_cse_images($key,$fallbackCx,$q,$num,null);
  }
  return [$items,null];
}


/* --- Request --- */
$error=null; $data=null;
$q=trim($_GET['q'] ?? $_POST['q'] ?? '');

if(isset($_GET['clear'])) @unlink($DB_FILE);

if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!$GKEY || !$CX_TXT || !$CX_IMG){ $error="Puuttuva avain: GOOGLE_CSE_KEY / GOOGLE_CSE_CX / GOOGLE_CSE_CX_IMAGES."; }
  elseif($q===''){ $error="Anna hakulause."; }
  else{
    $qhash=hash('sha256',"grid|".mb_strtolower($q,'UTF-8'));
    if($cached=cache_get($qhash,$TTL_SEC)){ $data=$cached; }
    else{
      list($text,$e1)=g_cse($GKEY,$CX_TXT,$q,10);
      list($imgs,$e2)=g_cse_images($GKEY,$CX_IMG,$q,10,$CX_TXT);
      if($e1) $error=$e1; if(!$error && $e2) $error=$e2;
      if(!$error){
        $data=['q'=>$q,'text'=>$text,'images'=>$imgs,'ts'=>time()];
        cache_put($qhash,$data);
      }
    }
  }
}
?>
<!doctype html>
<html lang="fi">
<head>
<meta charset="utf-8">
<title>Google – teksti + kuvat</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
:root{--fg:#111;--muted:#666;--bg:#f7f7f9;--card:#fff;--accent:#0b57d0;}
*{box-sizing:border-box}
body{font:16px/1.55 system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,sans-serif;color:var(--fg);background:var(--bg);margin:0;padding:2rem}
.wrap{max-width:1100px;margin:0 auto}
h1{margin:0 0 1rem;font-size:1.6rem}
.card{background:var(--card);border:1px solid #e7e7e7;border-radius:12px;padding:1rem 1.25rem;margin-bottom:1rem;box-shadow:0 1px 2px rgba(0,0,0,.04)}
label{display:block;font-weight:600;margin:.25rem 0}
input[type=text]{width:100%;padding:.7rem;border:1px solid #ddd;border-radius:10px;font:inherit}
.btn{display:inline-block;background:var(--accent);color:#fff;border:none;padding:.7rem 1rem;border-radius:10px;font-weight:700;cursor:pointer}
.grid{display:grid;grid-template-columns:1.15fr .85fr;gap:16px}
@media (max-width:980px){ .grid{grid-template-columns:1fr} }
.result a{color:#0b57d0;text-decoration:none}
.result{padding:.5rem 0;border-bottom:1px dashed #eee}
.result:last-child{border-bottom:none}
.gallery{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px}
.gallery a{display:block;background:#fff;border:1px solid #e7e7e7;border-radius:10px;overflow:hidden}
.gallery img{width:100%;height:110px;object-fit:cover;display:block}
small.muted{color:var(--muted)}
.bad{color:#b00020;font-weight:700}
</style>
</head>
<body>
<div class="wrap">
  <h1>Google‑haku: teksti + kuvat (grid)</h1>

  <div class="card">
    <form method="post">
      <label for="q">Hakulause</label>
      <input id="q" name="q" type="text" placeholder="Esim. Kouvolan nähtävyydet" value="<?=h($q)?>">
      <div style="display:flex;gap:.5rem;margin-top:.5rem">
        <button class="btn" type="submit">Hae</button>
        <a class="btn" style="background:#666" href="?clear=1">Tyhjennä välimuisti</a>
      </div>
      <small class="muted">Tekstihaku: <code><?=h($CX_TXT?:'—')?></code> · Kuvahaku: <code><?=h($CX_IMG?:'—')?></code></small>
    </form>
  </div>

  <?php if($error): ?>
    <div class="card bad">Virhe: <?=h($error)?></div>
  <?php elseif($data): ?>
    <div class="grid">
      <div class="card">
        <strong>Tekstitulokset</strong>
        <?php foreach($data['text'] as $r): ?>
          <div class="result">
            <a href="<?=h($r['link'])?>" target="_blank" rel="noopener"><?=h($r['title'])?></a><br>
            <small class="muted"><?=h($r['display'])?></small>
            <div><?=h($r['snippet'])?></div>
          </div>
        <?php endforeach; ?>
        <?php if(empty($data['text'])): ?>
          <div class="result"><em>Ei tekstituloksia.</em></div>
        <?php endif; ?>
        <small class="muted">Päivitetty: <?=date('Y-m-d H:i',$data['ts'])?> · Välimuisti 24 h</small>
      </div>

      <div class="card">
        <strong>Kuvat</strong>
        <div class="gallery">
          <?php foreach($data['images'] as $im): ?>
            <a href="<?=h($im['src'] ?: $im['context'])?>" target="_blank" rel="noopener" title="<?=h($im['title'])?>">
              <img src="<?=h($im['thumb'] ?: $im['src'])?>" alt="<?=h($im['title'])?>">
            </a>
          <?php endforeach; ?>
        </div>
        <?php if(empty($data['images'])): ?>
          <div><em>Ei kuvia.</em></div>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>

  <div class="card">
    <strong>/Huom</strong>
    <ul>
      <li>Ensimmäiset ~100 pyyntöä/päivä ilmaisia. Sen jälkeen n. <em>5 USD / 1000 hakua</em> (Google JSON API).</li>
      <li>Välimuisti pienentää kuluja ja nopeuttaa sivua.</li>
      <li>Jos kuvagalleria ei näy, varmista että kuvahaku‑CSE:ssä on <em>Image search</em> päällä.</li>
    </ul>
  </div>
</div>
</body>
</html>
