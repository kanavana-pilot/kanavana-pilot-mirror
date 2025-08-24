<?php
/* google.php — NLP-testi käännösputkella (GPT → Google NLP → GPT)
 * Ympäristömuuttujat (esim. .htaccess):
 *   SetEnv GOOGLE_NLP_API_KEY "..."
 *   SetEnv OPENAI_API_KEY "..."
 */

function envget($k){ return trim(getenv($k) ?: ($_SERVER[$k] ?? $_ENV[$k] ?? '')); }
function h($s){ return htmlspecialchars($s??'', ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }

$GOOGLE = envget('GOOGLE_NLP_API_KEY');
$OPENAI = envget('OPENAI_API_KEY');

function call_openai_chat($apiKey, $messages, $model='gpt-4o-mini'){
  $ch = curl_init('https://api.openai.com/v1/chat/completions');
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_POST=>true,
    CURLOPT_HTTPHEADER=>[
      'Content-Type: application/json',
      'Authorization: Bearer '.$apiKey
    ],
    CURLOPT_POSTFIELDS=>json_encode([
      'model'=>$model,
      'temperature'=>0,
      'messages'=>$messages
    ], JSON_UNESCAPED_UNICODE),
    CURLOPT_TIMEOUT=>30
  ]);
  $res = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 0;
  $err  = curl_error($ch);
  curl_close($ch);
  if($res===false) return [null,"openai curl error: $err (HTTP $http)"];
  $j = json_decode($res, true);
  $txt = $j['choices'][0]['message']['content'] ?? null;
  if(!$txt) return [null,"openai error: $res"];
  return [$txt, null];
}

function translate_via_gpt($apiKey, $text, $targetLang, $sourceLang=null){
  $sys = "You are a professional translator. Preserve meaning and names. Return only the translated text, no extra notes.";
  $user = "Translate the following text ".($sourceLang? "from $sourceLang ":"")."to {$targetLang}:\n\n".$text;
  return call_openai_chat($apiKey, [
    ['role'=>'system','content'=>$sys],
    ['role'=>'user','content'=>$user]
  ]);
}

function detect_lang_via_gpt($apiKey, $text){
  $sys = "You detect language codes. Reply with only a lowercase ISO 639-1 code (e.g., fi, en, ar, ru). If unsure, guess.";
  $user = "Detect the language of this text:\n\n".$text;
  return call_openai_chat($apiKey, [
    ['role'=>'system','content'=>$sys],
    ['role'=>'user','content'=>$user]
  ]);
}

function call_google_nlp($apiKey, $endpoint, array $payload){
  $url = "https://language.googleapis.com/v1/$endpoint?key=".rawurlencode($apiKey);
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_POST=>true,
    CURLOPT_HTTPHEADER=>['Content-Type: application/json; charset=utf-8'],
    CURLOPT_POSTFIELDS=>json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_TIMEOUT=>20
  ]);
  $res = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 0;
  $err  = curl_error($ch);
  curl_close($ch);
  if($res===false) return [null,"google curl error: $err (HTTP $http)", $http];
  $j = json_decode($res, true);
  if(!$j) return [null,"google json error: $res", $http];
  $j['_http'] = $http;
  return [$j, null, $http];
}

// ---------- käsittele lomake ----------
$result = null; $error = null; $backTranslation = null;

if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!$GOOGLE) $error = "GOOGLE_NLP_API_KEY puuttuu.";
  if(!$OPENAI) $error = $error ? $error." & OPENAI_API_KEY puuttuu." : "OPENAI_API_KEY puuttuu.";

  $text = trim($_POST['text'] ?? '');
  $op   = $_POST['op'] ?? 'entities';
  $lang = strtolower(trim($_POST['lang'] ?? '')); // käyttäjän kirjoituskieli (valinnainen)

  if(!$error){
    if($text===''){ $error="Syötä analysoitava teksti."; }
    else{
      // 1) Tunnista kieli tarvittaessa
      if($lang===''){
        list($det, $derr) = detect_lang_via_gpt($OPENAI, $text);
        if($derr){ $error=$derr; }
        else { $lang = preg_replace('~[^a-z]~','', strtolower(trim($det))); }
      }

      if(!$error){
        // 2) Käännä englanniksi GPT:llä (jos ei ennestään en)
        $srcLang = $lang ?: 'auto';
        if($srcLang!=='en'){
          list($enText, $terr) = translate_via_gpt($OPENAI, $text, 'en', $srcLang);
          if($terr){ $error=$terr; }
        } else {
          $enText = $text;
        }
      }

      if(!$error){
        // 3) Google NLP (englanniksi)
        $document = ['type'=>'PLAIN_TEXT', 'content'=>$enText, 'language'=>'en'];
        if($op==='sentiment'){
          $endpoint='documents:analyzeSentiment';
          $payload=['document'=>$document,'encodingType'=>'UTF8'];
        } elseif($op==='classify'){
          $endpoint='documents:classifyText';
          $payload=['document'=>$document];
        } else {
          $endpoint='documents:analyzeEntities';
          $payload=['document'=>$document,'encodingType'=>'UTF8'];
        }
        list($nlp, $nerr, $http) = call_google_nlp($GOOGLE, $endpoint, $payload);
        if($nerr){ $error=$nerr." (HTTP $http)"; }
        else {
          $result = $nlp;
          // 4) Käännä tiivis yhteenveto takaisin alkuperäiselle kielelle (vain jos src != en)
          if($srcLang!=='en'){
            $summary = "Summarize these Google NLP results in plain English for a non-technical reader. ".
                       "Mention key entities/sentiment/categories and short explanations.\n\n".
                       json_encode($nlp, JSON_UNESCAPED_UNICODE);
            list($back, $berr) = call_openai_chat($OPENAI, [
              ['role'=>'system','content'=>'You turn JSON NLP results into a short readable summary.'],
              ['role'=>'user','content'=>$summary]
            ]);
            if(!$berr){
              list($final, $xerr) = translate_via_gpt($OPENAI, $back, $srcLang, 'en');
              if(!$xerr) $backTranslation = $final;
            }
          }
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
<title>Google NLP + GPT-käännösputki – testi</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
:root{--fg:#111;--muted:#666;--bg:#fafafa;--card:#fff;--accent:#0b57d0;}
body{font:16px/1.5 system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,sans-serif;color:var(--fg);background:var(--bg);margin:0;padding:2rem;}
.wrap{max-width:980px;margin:0 auto;}
h1{margin:0 0 1rem 0;font-size:1.6rem;}
.card{background:var(--card);border:1px solid #e7e7e7;border-radius:12px;padding:1rem 1.25rem;margin-bottom:1rem;box-shadow:0 1px 2px rgba(0,0,0,.04);}
label{display:block;font-weight:600;margin-top:.5rem;}
textarea{width:100%;min-height:150px;padding:.75rem;border:1px solid #ddd;border-radius:10px;font:inherit;resize:vertical;}
select,input[type=text]{width:100%;padding:.6rem;border:1px solid #ddd;border-radius:10px;font:inherit;}
.row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.btn{display:inline-block;background:var(--accent);color:#fff;border:none;padding:.7rem 1rem;border-radius:10px;font-weight:700;cursor:pointer}
.muted{color:var(--muted);font-size:.9rem}
pre{background:#0b1020;color:#e6edf3;border-radius:12px;padding:1rem;overflow:auto;max-height:520px;}
.err{color:#b00020;font-weight:700;}
.ok{color:#0b7d1a;font-weight:700;}
</style>
</head>
<body>
<div class="wrap">
  <h1>Google NLP + GPT-käännösputki – testi</h1>

  <div class="card">
    <form method="post">
      <label for="text">Teksti</label>
      <textarea id="text" name="text" placeholder="Kirjoita tai liitä teksti..."><?=h($_POST['text'] ?? '')?></textarea>

      <div class="row">
        <div>
          <label for="op">Toiminto</label>
          <select id="op" name="op">
            <option value="entities" <?=(@$_POST['op']==='entities'?'selected':'')?>>Entiteetit</option>
            <option value="sentiment" <?=(@$_POST['op']==='sentiment'?'selected':'')?>>Sentimentti</option>
            <option value="classify" <?=(@$_POST['op']==='classify'?'selected':'')?>>Sisältöluokitus</option>
          </select>
          <div class="muted">Analyysi tehdään englanniksi, mutta tulos tiivistetään ja käännetään takaisin.</div>
        </div>
        <div>
          <label for="lang">Kirjoituskieli (valinnainen)</label>
          <input id="lang" name="lang" type="text" placeholder="esim. fi, en (tyhjä = automaattinen)" value="<?=h($_POST['lang'] ?? '')?>">
        </div>
      </div>

      <div style="display:flex;justify-content:flex-end;margin-top:.5rem">
        <button class="btn" type="submit">Analysoi</button>
      </div>
    </form>
  </div>

  <?php if ($error): ?>
    <div class="card"><span class="err">Virhe:</span> <?=h($error)?></div>
  <?php elseif ($result!==null): ?>
    <div class="card">
      <div><span class="ok">Google NLP raw (HTTP <?=$result['_http'] ?? '?'?>)</span></div>
      <pre><?=h(json_encode($result, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE))?></pre>
    </div>
    <?php if ($backTranslation): ?>
      <div class="card">
        <div><span class="ok">Tiivistelmä omalla kielelläsi</span></div>
        <pre><?=h($backTranslation)?></pre>
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <div class="card">
    <strong>Huomioita</strong>
    <ul>
      <li>Avainmuuttujat: <code>GOOGLE_NLP_API_KEY</code> ja <code>OPENAI_API_KEY</code> (.htaccess → SetEnv).</li>
      <li>Pidä kaikki kutsut palvelinpuolella — älä koskaan paljasta avaimia selaimeen.</li>
      <li>Kustannukset: yksi tai kaksi pientä GPT‑kutsua + yksi Google NLP ‑kutsu / analyysi.</li>
    </ul>
  </div>
</div>
</body>
</html>
