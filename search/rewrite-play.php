<?php
/**
 * Kanavana – rewrite-play.php (tekstiteatteri-demo)
 * - Muokkaa annetun tekstin valittuun tyyliin (≤ ~1000 merkkiä), anti-geneerinen sanalista
 * - Täysin erillinen alkuperäisestä rewrite.php:stä
 * - Input JSON: { lang, style_name, style_description?, tone, audience, length_hint, source_html }
 * - Output JSON: { answer_html, plain_html, followups }
 */

header('Content-Type: application/json; charset=utf-8');

// Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}

// --- Asetukset / avaimet ---
$OPENAI_API_KEY = getenv('OPENAI_API_KEY') ?: ($_SERVER['OPENAI_API_KEY'] ?? $_SERVER['REDIRECT_OPENAI_API_KEY'] ?? '');
$smartQuotes = ["\u{201C}","\u{201D}","\u{2018}","\u{2019}"]; // ” ” ‘ ’
$OPENAI_API_KEY = trim(str_replace($smartQuotes, '', $OPENAI_API_KEY), " \t\n\r\0\x0B\"'");

if (!$OPENAI_API_KEY) {
  http_response_code(500);
  echo json_encode(['error'=>'config','detail'=>'OPENAI_API_KEY missing'], JSON_UNESCAPED_UNICODE);
  exit;
}

// --- Syöte ---
$raw = file_get_contents('php://input');
$in  = json_decode($raw, true) ?: [];

$langIn           = trim((string)($in['lang'] ?? 'fi'));
$style_name       = trim((string)($in['style_name'] ?? 'Humoristinen'));
$style_desc       = trim((string)($in['style_description'] ?? ''));
$tone             = trim((string)($in['tone'] ?? ''));
$audience         = trim((string)($in['audience'] ?? ''));
$length_hint      = trim((string)($in['length_hint'] ?? 'Keskitaso')); // Lyhyt | Keskitaso | Pitkä (vapaamuotoinenkin ok)
$source_html_raw  = trim((string)($in['source_html'] ?? ''));

if ($source_html_raw === '') {
  echo json_encode(['error'=>'empty','detail'=>'source_html is empty'], JSON_UNESCAPED_UNICODE);
  exit;
}

// --- Kielinormalisointi ---
$ALLOWED = ['fi','en','sv','so','uk','ru','et','ar','fa','fr','de','es','it'];
$baseOf = function(string $tag): string {
  $t = strtolower(trim($tag));
  $parts = explode('-', $t, 2);
  return $parts[0] ?: $t;
};
$lang = $baseOf($langIn);
$ALIASES = [
  'ua'   => 'uk',   // Ukrainian
  'fa-ir'=> 'fa'    // Persian
];
if (isset($ALIASES[$lang])) $lang = $ALIASES[$lang];
if (!in_array($lang, $ALLOWED, true)) { $lang = 'fi'; }

// --- Kevyt puhdistus & raja lähteelle ---
$MAX_IN_CHARS = 12000;
$allowedTags  = '<p><br><ul><ol><li><strong><em><b><i><u><a>';
$source_html  = mb_substr(strip_tags($source_html_raw, $allowedTags), 0, $MAX_IN_CHARS, 'UTF-8');

// --- Geneerisyyslista (FI/EN) ---
$avoid_fi = ['innovatiivinen','edelläkävijä','skaalautuva','data-ohjautuva','maailmanluokan','vaikuttava','optimoida','ketterä','robusti','arvolupaus'];
$avoid_en = ['cutting-edge','innovative','world-class','scalable','data-driven','impactful','optimize','agile','robust','value proposition'];
$avoid_list = ($lang === 'fi') ? implode(', ', $avoid_fi) : implode(', ', $avoid_en);

// --- System-prompt ---
$systemPrompt = <<<SYS
Olet Tekstiteatteri-ohjaaja Kanavanassa (Play-demo).

KIELI: {$lang}. TYYLI: "{$style_name}".
TEHTÄVÄ: Muokkaa annetusta tekstistä ehjä versio tyylillä "{$style_name}" ({$style_desc}). Kirjoita konkreettisesti, mobiililukijalle selkeästi, ja pidä kokonaispituus enintään noin 1000 merkkiä.

OHJEET:
- Pidä teksti ≤ ~1000 merkkiä. Jos lähde on pidempi, priorisoi tärkein sisältö.
- Vältä geneerisiä AI-fraaseja: {$avoid_list}. Jos jokin niistä houkuttaa, keksi konkreettisempi korvaus ja KÄYTÄ korvausta.
- Salli perus-HTML: <p>, <ul>, <ol>, <li>, <strong>, <em>, <a>. Palauta validia, siistiä HTML:ää.
- Palauta vain kelvollinen JSON:
  - "answer_html": tyylin mukainen valmis katkelma
  - "plain_html": sama sisältö lyhyempänä ja yksinkertaisempana
  - "followups": 1–3 lyhyttä jatkoideaa "{$lang}"-kielellä
SYS;

// --- User-prompt ---
$userPrompt = <<<USR
Kieli: {$lang}
Tyyli: {$style_name}
Mieliala: {$tone}
Yleisö: {$audience}
Pituus: {$length_hint}

Lähdeteksti (muokkaa yllä olevien sääntöjen mukaan):
{$source_html}
USR;

// --- OpenAI-kutsu ---
$payload = [
  "model" => "gpt-4o-mini",
  "messages" => [
    ["role"=>"system","content"=>$systemPrompt],
    ["role"=>"user","content"=>$userPrompt]
  ],
  "temperature" => 0.5,
  "response_format" => ["type"=>"json_object"]
];

$resp = httpPostJson(
  "https://api.openai.com/v1/chat/completions",
  $payload,
  ["Content-Type: application/json","Authorization: Bearer $OPENAI_API_KEY"]
);

if ($resp['error']) {
  http_response_code(502);
  echo json_encode(['error'=>'openai_error','detail'=>$resp['error']], JSON_UNESCAPED_UNICODE);
  exit;
}

$data = json_decode($resp['body'], true);
$content = $data['choices'][0]['message']['content'] ?? '{}';
$parsed  = json_decode($content, true);

// Kenttien varmistus
$answer_html = (string)($parsed['answer_html'] ?? '');
$plain_html  = (string)($parsed['plain_html']  ?? '');
$followups   = is_array($parsed['followups'] ?? null) ? $parsed['followups'] : [];

// (Valinnainen) Kevyt server-side pituusklipsi plain_html:iin
$plain_text_len = mb_strlen(preg_replace('/\s+/', ' ', strip_tags($plain_html)), 'UTF-8');
if ($plain_text_len > 1200) {
  $plain_html = '<p>' . safeClip(strip_tags($plain_html), 1000) . '</p>';
}

// Palauta
echo json_encode([
  'answer_html' => $answer_html,
  'plain_html'  => $plain_html,
  'followups'   => $followups
], JSON_UNESCAPED_UNICODE);

// --- Helperit ---
function httpPostJson(string $url, array $payload, array $headers=[]): array {
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
  curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(["User-Agent: KanavanaPlay/1.0"], $headers));
  curl_setopt($ch, CURLOPT_TIMEOUT, 25);
  if (defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')) {
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
  }
  $body = curl_exec($ch);
  $err  = null;
  if ($body === false) {
    $err = curl_error($ch) ?: 'curl_error';
  } else {
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($code >= 400) { $err = "HTTP $code: $body"; }
  }
  curl_close($ch);
  return ['body'=>(string)$body, 'error'=>$err];
}

function safeClip(string $text, int $maxChars): string {
  $text = preg_replace('/\s+/', ' ', $text);
  if (mb_strlen($text, 'UTF-8') <= $maxChars) return $text;
  $cut = mb_substr($text, 0, $maxChars - 1, 'UTF-8');
  $sp  = mb_strrpos($cut, ' ', 0, 'UTF-8');
  if ($sp !== false) $cut = mb_substr($cut, 0, $sp, 'UTF-8');
  return rtrim($cut, " \t\n\r\0\x0B.,;:") . '…';
}
