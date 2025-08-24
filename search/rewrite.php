<?php
/**
 * Kanavana – rewrite.php (search/)
 * GPT-4o-mini: tyylin iterointi ilman Tavilya
 * - Input JSON: { html, style, lang, length? ('Lyhyt'|'Keskitaso'|'Pitkä') }
 * - Palauttaa: { answer_html, plain_html, followups, citations: [] }
 */

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// --- Asetukset ---
$OPENAI_API_KEY = getenv('OPENAI_API_KEY') ?: ($_SERVER['OPENAI_API_KEY'] ?? $_SERVER['REDIRECT_OPENAI_API_KEY'] ?? '');
$smartQuotes = ["\u{201C}","\u{201D}","\u{2018}","\u{2019}"];
$OPENAI_API_KEY = trim(str_replace($smartQuotes, '', $OPENAI_API_KEY), " \t\n\r\0\x0B\"'");
if (!$OPENAI_API_KEY) { http_response_code(500); echo json_encode(['error'=>'config','detail'=>'OPENAI_API_KEY missing']); exit; }

// --- Lue syöte ---
$raw = file_get_contents('php://input');
$in  = json_decode($raw, true) ?: [];

$html   = trim($in['html']   ?? '');
$style  = trim($in['style']  ?? 'clear');
$langIn = trim($in['lang']   ?? 'fi');
$length = trim($in['length'] ?? 'Keskitaso');

if ($html === '') {
  echo json_encode(['error'=>'empty','detail'=>'html empty']);
  exit;
}

// --- Kielinormalisointi ---
// tuetut kielet (perusosa, pienillä kirjaimilla)
$ALLOWED = ['fi','en','sv','so','uk','ru','et','ar','fa', 'fr','de','es','it'];

// perusosa (esim. "ar-SA" -> "ar")
$baseOf = function(string $tag): string {
  $t = strtolower(trim($tag));
  $parts = explode('-', $t, 2);
  return $parts[0] ?: $t;
};

$lang = $baseOf($langIn);

// muutama alias (jos joskus selaimesta tulee yllättävä)
$ALIASES = [
  'ua' => 'uk',   // Ukrainian
  'fa-ir' => 'fa' // Persian
];

if (isset($ALIASES[$lang])) $lang = $ALIASES[$lang];

// fallback suomi jos ei sallittu
if (!in_array($lang, $ALLOWED, true)) { $lang = 'fi'; }

// --- Kevyt puhdistus/pituusraja (turvallisuus & kustannus) ---
$MAX_CHARS = 12000;
$src = mb_substr(strip_tags($html, '<p><br><ul><ol><li><strong><em><b><i><u><a>'), 0, $MAX_CHARS, 'UTF-8');

// --- Tyylikartta ---
$STYLE_MAP = [
  'clear'      => 'Selkeä yleiskieli: lyhyet lauseet, arjen sanat, vältä jargonia, aktiivimuoto, yksi ajatus per kappale.',
  'selko'      => 'Selkokieli: hyvin lyhyet lauseet (enintään ~10–12 sanaa), tuttu sanasto, konkreettiset verbit, vältä sivulauseita ja metaforia, toimi-listat. Kirjoita suoraan lukijalle.',
  'confident'  => 'Itsevarma ja tuloksiin nojaava: aktiivimuoto, mitat ja numerot esiin, “minä tein → vaikutus”. Ei ylimääräisiä sivulauseita.',
  'friendly'   => 'Ystävällinen ja empaattinen: lämmin sävy, minä-muoto, kohteliaat siirtymät, pehmeät vahvistukset.',
  'analytical' => 'Analyyttinen ja jäsennelty: otsikkolause + bulletit, loogiset kohdat, evidenssi ja mittarit, ei turhaa retoriikkaa.',
  'story'      => 'Tarinallinen: tilanne → toiminta → tulos, 2–3 kappaletta, lopussa yhteys yrityksen arvoihin.',
  'noNonsense' => 'Napakka, suora ja tekninen: lyhyet virkkeet, ei korulauseita, tärkein ensin, mahdollisimman konkreettinen.'
];
$styleDesc = $STYLE_MAP[$style] ?? $STYLE_MAP['clear'];

// --- System-prompt ---
$system = <<<SYS
You are Kanavana's application rewriter.

LANGUAGE: All output must be in "{$lang}".

RULES:
- Keep factual content faithful to the source. Do not invent projects, dates, employers or numbers.
- Preserve any URLs; convert bare URLs to <a> links if needed.
- Keep basic HTML (<p>, <ul>, <li>, <strong>, <em>, <a>) and return valid, clean HTML fragments.
- Avoid imitating any identifiable person's voice. Use the STYLE TRAITS abstractly.
- Respect the requested LENGTH: Lyhyt (≈ 120–160 words), Keskitaso (≈ 180–240), Pitkä (≈ 260–340).
- Return a JSON object with: "answer_html", "plain_html" (simpler rephrase), "followups" (1–2 short suggestions).
SYS;

// --- User-prompt ---
$user = <<<USR
Rewrite the following application text according to STYLE and LENGTH.

STYLE TRAITS: {$styleDesc}
LENGTH: {$length}

SOURCE HTML:
<<<HTML
{$src}
HTML

INSTRUCTIONS:
- Rewrite, don't summarize away key qualifications.
- Prefer active voice and short sentences. Avoid jargon unless necessary and define it briefly.
- Do not add a source list and do not output any JSON keys other than requested.
USR;

// --- OpenAI-kutsu ---
$resp = httpPostJson(
  "https://api.openai.com/v1/chat/completions",
  [
    "model" => "gpt-4o-mini",
    "messages" => [
      ["role"=>"system","content"=>$system],
      ["role"=>"user","content"=>$user]
    ],
    "temperature" => 0.4,
    "response_format" => ["type"=>"json_object"]
  ],
  ["Content-Type: application/json","Authorization: Bearer $OPENAI_API_KEY"]
);
if ($resp['error']) {
  http_response_code(502);
  echo json_encode(['error'=>'openai_error','detail'=>$resp['error']]); exit;
}

$openai = json_decode($resp['body'], true);
$content = $openai['choices'][0]['message']['content'] ?? '{}';
$parsed  = json_decode($content, true);
$out = [
  'answer_html' => $parsed['answer_html'] ?? '<p>(virhe: answer_html puuttuu)</p>',
  'plain_html'  => $parsed['plain_html']  ?? '<p>(virhe: plain_html puuttuu)</p>',
  'followups'   => $parsed['followups']   ?? ['Haluatko tiivistää lisää?'],
  'citations'   => []
];

echo json_encode($out, JSON_UNESCAPED_UNICODE);

// --- Helpers ---
function httpPostJson(string $url, array $payload, array $headers=[]): array {
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
  curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(["User-Agent: KanavanaPilot/1.0"], $headers));
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
