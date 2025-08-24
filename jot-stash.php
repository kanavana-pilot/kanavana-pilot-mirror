<?php
// ---- Perusasetukset ----
error_reporting(E_ALL);
ini_set('display_errors','0');

session_start();
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

// Turva-avain URL:ssa (voit myös käyttää .htaccess SetEnv JOT_STASH_SECRET …)
$SECRET = getenv('JOT_STASH_SECRET');
if (!$SECRET) { $SECRET = 'vaihda_tama_salasana'; }

$key = isset($_GET['key']) ? (string)$_GET['key'] : '';
if (!hash_equals((string)$SECRET, $key)) {
  http_response_code(403);
  echo 'Forbidden';
  exit;
}

// Hakemistot
$BASE_DIR = __DIR__;
if (!$BASE_DIR) { $BASE_DIR = __DIR__; }
$DATA_DIR    = __DIR__ . '/_jot_stash';
$STAGING_DIR = $DATA_DIR . '/staging';
$BACKUP_DIR  = $DATA_DIR . '/backups';
$LOG_FILE    = $DATA_DIR . '/actions.log';
$ALLOWED_EXT = array('php','phtml','js','css','json','html','htm','md','txt','yml','yaml');
$MAX_BYTES   = 2 * 1024 * 1024;

@mkdir($DATA_DIR, 0755, true);
@mkdir($STAGING_DIR, 0755, true);
@mkdir($BACKUP_DIR, 0755, true);

if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

function h($s){ return htmlspecialchars($s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function now(){ return date('Y-m-d H:i:s'); }
function fail($msg){ http_response_code(400); echo '<pre>'.h($msg).'</pre>'; exit; }
function ext_ok($path, $allowed){
  $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
  return in_array($ext, $allowed, true);
}
function normalize_rel_path($base, $rel){
  $rel = str_replace('\\','/',$rel);
  $rel = ltrim($rel,'/');
  $full = realpath($base . '/' . $rel);
  if (!$full) return false;
  $base = realpath($base);
  if (strpos($full, $base) !== 0) return false;
  return array($rel, $full);
}
function list_registered($DATA_DIR){
  $file = $DATA_DIR.'/registered.json';
  if (!is_file($file)) return array();
  $j = file_get_contents($file);
  $a = json_decode($j, true);
  return is_array($a) ? $a : array();
}
function save_registered($DATA_DIR, $arr){
  $file = $DATA_DIR.'/registered.json';
  file_put_contents($file, json_encode(array_values($arr), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES), LOCK_EX);
}
function new_id(){ return date('Ymd_His').'_' . bin2hex(random_bytes(3)); }
function simple_diff($old, $new){
  $a = explode("\n", $old);
  $b = explode("\n", $new);
  $i=0; $j=0; $out=array();
  while ($i < count($a) || $j < count($b)) {
    if ($i < count($a) && $j < count($b) && $a[$i] === $b[$j]) { $out[] = array(' ',' '.$a[$i]); $i++; $j++; continue; }
    $found=false;
    for ($k=1; $k<=5 && !$found; $k++) {
      if ($i+$k < count($a) && $j < count($b) && $a[$i+$k] === $b[$j]) { for ($t=0;$t<$k;$t++) $out[] = array('-','-'.$a[$i+$t]); $i+=$k; $found=true; break; }
      if ($j+$k < count($b) && $i < count($a) && $a[$i] === $b[$j+$k]) { for ($t=0;$t<$k;$t++) $out[] = array('+','+'.$b[$j+$t]); $j+=$k; $found=true; break; }
    }
    if (!$found) {
      if ($i < count($a)) { $out[] = array('-','-'.$a[$i]); $i++; }
      if ($j < count($b)) { $out[] = array('+','+'.$b[$j]); $j++; }
    }
  }
  return $out;
}
function audit($LOG_FILE, $action, $meta=array()){
  $line = now()."\t".$action."\t".json_encode($meta)."\n";
  @file_put_contents($LOG_FILE, $line, FILE_APPEND|LOCK_EX);
}
function opcache_nudge($path){
  if (function_exists('opcache_invalidate')) @opcache_invalidate($path, true);
}

// ---- Actions ----
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'ui';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!isset($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) fail('CSRF');
}

if ($action === 'register' && $_SERVER['REQUEST_METHOD']==='POST') {
  $label = trim(isset($_POST['label'])?$_POST['label']:'');
  $rel   = trim(isset($_POST['rel_path'])?$_POST['rel_path']:'');
  if ($label === '' || $rel === '') fail('Puuttuva label tai polku');
  $norm = normalize_rel_path($BASE_DIR, $rel);
  if (!$norm) fail('Polku virheellinen');
  list($relClean, $abs) = $norm;
  if (!is_file($abs)) fail('Tiedostoa ei löydy');
  if (!ext_ok($abs, $ALLOWED_EXT)) fail('Pääte ei sallittu');
  $list = list_registered($DATA_DIR);
  $list[] = array('id'=>new_id(), 'label'=>$label, 'rel'=>$relClean, 'abs'=>$abs);
  save_registered($DATA_DIR, $list);
  audit($LOG_FILE,'register',array('rel'=>$relClean));
  header('Location: ?key='.rawurlencode($key)); exit;
}

if ($action === 'propose' && $_SERVER['REQUEST_METHOD']==='POST') {
  $rel = trim(isset($_POST['rel'])?$_POST['rel']:'');
  $norm = normalize_rel_path($BASE_DIR, $rel);
  if (!$norm) fail('Polku virheellinen');
  list($relClean, $abs) = $norm;
  if (!ext_ok($abs, $ALLOWED_EXT)) fail('Pääte ei sallittu');
  $content = '';
  if (!empty($_FILES['file']['tmp_name'])) {
    $content = file_get_contents($_FILES['file']['tmp_name']);
  } else {
    $content = (string)(isset($_POST['content'])?$_POST['content']:'');
  }
  if ($content === '') fail('Ei sisältöä');
  if (strlen($content) > $MAX_BYTES) fail('Tiedosto liian suuri');

  $note = trim(isset($_POST['note'])?$_POST['note']:'');
  $id = new_id();
  $dir = $STAGING_DIR.'/'.md5($relClean);
  if (!is_dir($dir)) @mkdir($dir,0755,true);
  $meta = array('id'=>$id, 'rel'=>$relClean, 'abs'=>$abs, 'note'=>$note, 'time'=>now(), 'len'=>strlen($content));
  file_put_contents($dir.'/'.$id.'.meta.json', json_encode($meta, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES), LOCK_EX);
  file_put_contents($dir.'/'.$id.'.payload', $content, LOCK_EX);
  audit($LOG_FILE,'propose',array('rel'=>$relClean,'id'=>$id));
  header('Location: ?key='.rawurlencode($key).'&rel='.rawurlencode($relClean).'#staging'); exit;
}

if ($action === 'promote' && $_SERVER['REQUEST_METHOD']==='POST') {
  $rel = trim(isset($_POST['rel'])?$_POST['rel']:'');
  $id  = trim(isset($_POST['id'])?$_POST['id']:'');
  $norm = normalize_rel_path($BASE_DIR, $rel);
  if (!$norm) fail('Polku virheellinen');
  list($relClean, $abs) = $norm;
  $dir = $STAGING_DIR.'/'.md5($relClean);
  $payload = $dir.'/'.$id.'.payload';
  $metaF   = $dir.'/'.$id.'.meta.json';
  if (!is_file($payload) || !is_file($metaF)) fail('Staging-item puuttuu');
  $new = file_get_contents($payload);
  $cur = is_file($abs) ? file_get_contents($abs) : '';
  $bdir = $BACKUP_DIR.'/'.md5($relClean);
  if (!is_dir($bdir)) @mkdir($bdir,0755,true);
  $bname = basename($relClean).'.'.date('Ymd_His').'.bak';
  file_put_contents($bdir.'/'.$bname, $cur, LOCK_EX);
  $ok = file_put_contents($abs, $new, LOCK_EX);
  if ($ok === false) fail('Kirjoitus epäonnistui');
  @touch($abs, time());
  opcache_nudge($abs);
  audit($LOG_FILE,'promote',array('rel'=>$relClean,'id'=>$id,'backup'=>$bname));
  header('Location: ?key='.rawurlencode($key).'&rel='.rawurlencode($relClean).'&promoted=1'); exit;
}

if ($action === 'rollback' && $_SERVER['REQUEST_METHOD']==='POST') {
  $rel = trim(isset($_POST['rel'])?$_POST['rel']:'');
  $bak = trim(isset($_POST['backup'])?$_POST['backup']:'');
  $norm = normalize_rel_path($BASE_DIR, $rel);
  if (!$norm) fail('Polku virheellinen');
  list($relClean, $abs) = $norm;
  $bpath = $BACKUP_DIR.'/'.md5($relClean).'/'.$bak;
  if (!is_file($bpath)) fail('Backupia ei löydy');
  $data = file_get_contents($bpath);
  $ok = file_put_contents($abs, $data, LOCK_EX);
  if ($ok === false) fail('Rollback epäonnistui');
  opcache_nudge($abs);
  audit($LOG_FILE,'rollback',array('rel'=>$relClean,'backup'=>$bak));
  header('Location: ?key='.rawurlencode($key).'&rel='.rawurlencode($relClean).'&rolled=1'); exit;
}
// Lisää Actions-osioon muiden if-lohkojen sekaan:
if ($action === 'raw' && $_SERVER['REQUEST_METHOD']==='GET') {
  $rel = isset($_GET['rel']) ? trim($_GET['rel']) : '';
  $norm = normalize_rel_path($BASE_DIR, $rel);
  if (!$norm) { http_response_code(404); exit('not found'); }
  list($relClean, $abs) = $norm;

  // hae uusin staging-payload tälle tiedostolle
  $dir = $STAGING_DIR.'/'.md5($relClean);
  if (!is_dir($dir)) { http_response_code(404); exit('no staging'); }
  $candidates = glob($dir.'/*.meta.json');
  rsort($candidates); // uusin ekaksi, koska id alkaa yyyyMMdd_HHmmss
  $id = basename($candidates[0], '.meta.json');
  $payload = $dir.'/'.$id.'.payload';
  if (!is_file($payload)) { http_response_code(404); exit('no payload'); }

  header('Content-Type: text/plain; charset=UTF-8');
  header('X-Robots-Tag: noindex, nofollow');
  header('Content-Disposition: inline; filename="'.basename($relClean).'"');
  readfile($payload);
  exit;
}
// ---- UI-data ----
$registered = list_registered($DATA_DIR);
$relFocus = isset($_GET['rel']) ? $_GET['rel'] : '';
$focusAbs = '';
$focusContent = '';
if ($relFocus) {
  $norm = normalize_rel_path($BASE_DIR, $relFocus);
  if ($norm) { list($relFocus, $focusAbs) = $norm; $focusContent = @file_get_contents($focusAbs); if ($focusContent===false) $focusContent=''; }
}

function list_backups_compat($BACKUP_DIR, $rel){
  $d = $BACKUP_DIR.'/'.md5($rel);
  if (!is_dir($d)) return array();
  $sc = scandir($d);
  $out = array();
  foreach ($sc as $f){ if ($f!=='.' && $f!=='..') $out[]=$f; }
  rsort($out);
  return $out;
}
function list_staging_compat($STAGING_DIR, $rel){
  $dir = $STAGING_DIR.'/'.md5($rel);
  if (!is_dir($dir)) return array();
  $items = array();
  foreach (glob($dir.'/*.meta.json') as $m){
    $meta = json_decode(file_get_contents($m), true);
    if (!is_array($meta)) $meta = array();
    $id = isset($meta['id']) ? $meta['id'] : basename($m, '.meta.json');
    $payload = $dir.'/'.$id.'.payload';
    $len = is_file($payload) ? filesize($payload) : 0;
    $meta['len'] = $len; $meta['payload'] = basename($payload);
    $items[] = $meta;
  }
  usort($items, function($a,$b){
    $aa = isset($a['id'])?$a['id']:''; $bb = isset($b['id'])?$b['id']:'';
    return strcmp($bb,$aa);
  });
  return $items;
}
$backups = $relFocus ? list_backups_compat($BACKUP_DIR, $relFocus) : array();
$items   = $relFocus ? list_staging_compat($STAGING_DIR, $relFocus) : array();
?>
<!doctype html>
<html lang="fi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>jot-stash</title>
  <link rel="stylesheet" href="/assets/css/jot-stash.css">
  <script src="/assets/js/jot-stash.js" defer></script>
</head>
<body>
<header>
  <h1>jot-stash <span class="muted">(kevyt staging / julkaisu)</span></h1>
  <div class="muted">BASE: <?=h($BASE_DIR)?></div>
</header>

<div class="wrap">
  <aside class="card">
    <h3>Rekisteröidyt tiedostot</h3>
    <ul class="list">
      <?php foreach ($registered as $r): ?>
        <li>
          <a href="?key=<?=rawurlencode($key)?>&rel=<?=rawurlencode($r['rel'])?>"><?=h($r['label'])?></a>
          <div class="muted"><?=h($r['rel'])?></div>
        </li>
      <?php endforeach; ?>
    </ul>

    <hr>
    <form method="post" action="?action=register&key=<?=rawurlencode($key)?>">
      <input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>">
      <h4>Lisää uusi</h4>
      <label>Selite</label>
      <input class="input" type="text" name="label" placeholder="Esim. planner.js">
      <label>Polku (suhteessa BASEen)</label>
      <input class="input" type="text" name="rel_path" placeholder="assets/js/planner.js">
      <div class="row"><button class="btn" type="submit">Rekisteröi</button></div>
      <p class="muted">Sallitut päätteet: <?=h(implode(', ',$ALLOWED_EXT))?></p>
    </form>
  </aside>

  <main>
    <?php if ($relFocus && $focusAbs): ?>
      <div class="card">
        <h3>Erittely: <code><?=h($relFocus)?></code></h3>
        <p class="muted">Absoluuttinen: <?=h($focusAbs)?></p>
        <?php if (isset($_GET['promoted'])): ?><p class="ok">✅ Julkaisu onnistui.</p><?php endif; ?>
        <?php if (isset($_GET['rolled'])): ?><p class="ok">✅ Rollback tehty.</p><?php endif; ?>
      </div>

      <div class="card" id="staging">
        <h3>1) Lataa/pastea ehdotettu versio stagingiin</h3>
        <form method="post" enctype="multipart/form-data" action="?action=propose&key=<?=rawurlencode($key)?>">
          <input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>">
          <input type="hidden" name="rel" value="<?=h($relFocus)?>">
          <div class="grid-2">
            <div>
              <label>Tiedostolataus (vaihtoehto tekstille)</label>
              <input class="input" type="file" name="file" accept="*/*">
            </div>
            <div>
              <label>Tai liitä sisältö</label>
              <textarea class="area" name="content" placeholder="Liitä tänne uusi tiedostosisältö…"></textarea>
            </div>
          </div>
          <label>Muistiinpano</label>
          <input class="input" type="text" name="note" placeholder="Mitä muutit?">
          <div class="row"><button class="btn" type="submit">Tallenna stagingiin</button></div>
        </form>
      </div>

      <div class="card">
        <h3>2) Esikatsele diff ja julkaise</h3>
        <?php if (!$items): ?>
          <p class="muted">Ei ehdotuksia stagingissa vielä.</p>
        <?php else: ?>
          <?php foreach ($items as $it):
            $p = $STAGING_DIR.'/'.md5($relFocus).'/'.$it['id'].'.payload';
            $proposed = @file_get_contents($p); if ($proposed===false) $proposed='';
            $diff = simple_diff($focusContent, $proposed);
          ?>
            <details class="card">
              <summary><strong><?=h($it['id'])?></strong> — <?=h(isset($it['note'])?$it['note']:'')?> <span class="muted">(<?=number_format($it['len'])?> bytes)</span></summary>
              <div class="diff"><pre class="code"><?php foreach ($diff as $d){ echo h($d[1])."\n"; } ?></pre></div>
              <form method="post" action="?action=promote&key=<?=rawurlencode($key)?>" data-confirm="Julkaistaanko tämä versio? Backup otetaan automaattisesti.">
                <input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>">
                <input type="hidden" name="rel" value="<?=h($relFocus)?>">
                <input type="hidden" name="id" value="<?=h($it['id'])?>">
                <button class="btn" type="submit">Julkaise → korvaa kohdetiedosto</button>
              </form>
            </details>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <div class="card">
        <h3>3) Tarvittaessa palauta backup</h3>
        <?php if (!$backups): ?>
          <p class="muted">Ei backuppeja vielä.</p>
        <?php else: ?>
          <ul class="list">
            <?php foreach ($backups as $b): ?>
              <li>
                <code><?=h($b)?></code>
                <form class="row" method="post" action="?action=rollback&key=<?=rawurlencode($key)?>" data-confirm="Palautetaanko tämä backup?">
                  <input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>">
                  <input type="hidden" name="rel" value="<?=h($relFocus)?>">
                  <input type="hidden" name="backup" value="<?=h($b)?>">
                  <button class="btn secondary" type="submit">Palauta</button>
                </form>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>

      <div class="card">
        <h3>Nykyinen kohdetiedosto</h3>
        <pre class="code"><?=h($focusContent)?></pre>
      </div>
    <?php else: ?>
      <div class="card">
        <h3>Ohje</h3>
        <ol>
          <li>Lisää muokattavat tiedostot vasemmalta listaan.</li>
          <li>Valitse tiedosto → lataa/pastea uusi versio stagingiin.</li>
          <li>Tarkista diff ja julkaise.</li>
        </ol>
      </div>
    <?php endif; ?>
  </main>
</div>

<footer class="muted"><small>© <?=date('Y')?> jot-stash · loki: <?=h($LOG_FILE)?></small></footer>
</body>
</html>
