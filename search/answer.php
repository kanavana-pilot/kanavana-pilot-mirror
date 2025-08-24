<?php
/**
 * Kanavana – answer.php (search/)
 * Tavily + GPT-4o-mini
 * - Lukee inputin JSON tai form-data
 * - Kielen pakotus (fi/en/sv/so/uk/ru/et/ar/fa)
 * - KAKSI hakua Tavilyyn: job + company (jos saatavilla/voitettu)
 * - gov_only + include_domains-tuki
 * - Välimuisti (24h) + nocache/debug tuet
 * - Health-check: ?health=1
 */

header('Content-Type: application/json; charset=utf-8');

// --- Preflight ---
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ===================== ASETUKSET =====================
$TAVILY_API_KEY = getenv('TAVILY_API_KEY') ?: ($_SERVER['TAVILY_API_KEY'] ?? $_SERVER['REDIRECT_TAVILY_API_KEY'] ?? '');
$OPENAI_API_KEY = getenv('OPENAI_API_KEY') ?: ($_SERVER['OPENAI_API_KEY'] ?? $_SERVER['REDIRECT_OPENAI_API_KEY'] ?? '');

// Siivoa älylainaukset ja whitespace
$smartQuotes = ["\u{201C}","\u{201D}","\u{2018}","\u{2019}"]; // ” ” ‘ ’
$TAVILY_API_KEY = trim(str_replace($smartQuotes, '', $TAVILY_API_KEY), " \t\n\r\0\x0B\"'");
$OPENAI_API_KEY = trim(str_replace($smartQuotes, '', $OPENAI_API_KEY), " \t\n\r\0\x0B\"'");

$CACHE_DIR = __DIR__ . '/cache/';
if (!is_dir($CACHE_DIR)) { @mkdir($CACHE_DIR, 0700, true); }
$denyHt = $CACHE_DIR . '.htaccess';
if (!file_exists($denyHt)) { @file_put_contents($denyHt, "Require all denied\n"); }

// ===================== TERVEYSTESTI =====================
// Aja: /search/answer.php?health=1
if (isset($_GET['health'])) {
  $k = $TAVILY_API_KEY;
  $mask = strlen($k) ? substr($k,0,3) . '...' . substr($k,-3) : '';
  $hexStart = bin2hex(substr($k, 0, 3));
  $hexEnd   = bin2hex(substr($k, -3));

  $tavily_bearer = test_http_post(
    'https://api.tavily.com/search',
    ['query'=>'healthcheck','search_depth'=>'basic','max_results'=>1],
    ["Content-Type: application/json","Accept: application/json","Authorization: Bearer $TAVILY_API_KEY"]
  );
  $openai = test_http(
    'https://api.openai.com/v1/models',
    ["Authorization: Bearer $OPENAI_API_KEY"]
  );

  echo json_encode([
    'php' => PHP_VERSION,
    'curl_loaded' => extension_loaded('curl'),
    'env' => [
      'OPENAI_API_KEY' => (bool)$OPENAI_API_KEY,
      'TAVILY_API_KEY' => (bool)$TAVILY_API_KEY
    ],
    'tavily_bearer' => $tavily_bearer,
    'openai' => $openai,
    'tavily_key_debug' => ['len' => strlen($k), 'mask' => $mask, 'hex_start' => $hexStart, 'hex_end' => $hexEnd]
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

// ===================== SYÖTE (JSON + form + GET) =====================
$raw = file_get_contents('php://input');
$in  = json_decode($raw, true);
if (!is_array($in)) { $in = []; }

$q          = trim($in['q'] ?? ($_POST['q'] ?? ($_GET['q'] ?? '')));
$lang       = ($in['lang'] ?? ($_POST['lang'] ?? 'fi')) ?: 'fi';
$govOnly    = (bool)($in['gov_only'] ?? $_POST['gov_only'] ?? false);
$companyArg = trim($in['company'] ?? ($_POST['company'] ?? ''));              // <-- uusi vapaaehtoinen
$jobUrlArg  = trim($in['job_url'] ?? ($_POST['job_url'] ?? ''));              // <-- vapaaehtoinen

$debug      = isset($_GET['debug']) && $_GET['debug'] !== '0';
$nocache    = isset($_GET['nocache']) && $_GET['nocache'] !== '0';

// Kielinormalisointi ja sallittujen joukko
$lang = strtolower($lang);
$ALLOWED_LANGS = ['fi','en','sv','so','uk','ru','et','ar','fa', 'fr','de','es','it'];
if (!in_array($lang, $ALLOWED_LANGS, true)) { $lang = 'fi'; }

// Sallitut include_domains (valinnainen, voi tulla frontista)
$includeDomains = [];
if (!empty($in['include_domains']) && is_array($in['include_domains'])) {
  foreach ($in['include_domains'] as $d) {
    $d = strtolower(trim($d));
    if ($d && preg_match('/^[a-z0-9.-]+$/', $d)) { $includeDomains[] = $d; }
  }
}

// Oletuslista viranomais-/luotettaville domaineille (kun gov_only = true)
$GOV_DOMAINS_DEFAULT = [
  "suomi.fi","valtioneuvosto.fi","kela.fi","migri.fi","poliisi.fi","te-palvelut.fi","tyomarkkinatori.fi",
  "vero.fi","thl.fi","stm.fi","eduskunta.fi","oph.fi","om.fi","avi.fi","dvv.fi",
  "europa.eu","ec.europa.eu","euipo.europa.eu","ema.europa.eu"
];

// GET ilman q-paramia → tyhjä
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $q === '') { echo json_encode(['error' => 'empty']); exit; }
if ($q === '') { echo json_encode(['error' => 'empty']); exit; }

// ===================== CACHE =====================
$CACHE_VERSION = 'v9-company-mix'; // nosta kun prompt/algoritmi muuttuu
$CACHE_TTL     = 86400;            // 24h
$cacheKey  = hash('sha256', $CACHE_VERSION.'|'.$lang.'|'.($govOnly?'1':'0').'|'.$q.'|'.$companyArg.'|'.$jobUrlArg);
$cacheFile = $CACHE_DIR . $cacheKey . '.json';
if (!$nocache && file_exists($cacheFile) && (time() - filemtime($cacheFile) < $CACHE_TTL)) {
  readfile($cacheFile); exit;
}

// === Derivoi lyhyt hakulause Tavilylle (tukee "SEARCH: ...") ===
$searchQ = $q;
if (preg_match('/^SEARCH:\s*(.+)$/mi', $q, $m)) {
  $searchQ = trim($m[1]);
}
$searchQ = mb_substr($searchQ, 0, 390, 'UTF-8'); // alle 400
$isUrl   = filter_var($searchQ, FILTER_VALIDATE_URL) ? true : false;
$jobUrl  = $jobUrlArg ?: ($isUrl ? $searchQ : null);
$jobHost = $jobUrl ? (parse_url($jobUrl, PHP_URL_HOST) ?: null) : null;

// Yrityksen nimi – ensisijaisesti frontista, muuten heuristiikka hostista
$companyName = $companyArg;
if ($companyName === '' && $jobHost) {
  $companyName = guessCompanyNameFromHost($jobHost); // esim. autoklinikka.fi -> Autoklinikka
}

// Snippetin maksimipituus
$MAX_SNIPPET_CHARS = 700;

// ===================== 1) TAVILY – JOB PAYLOAD =====================
if (!$TAVILY_API_KEY) { http_response_code(500); echo json_encode(['error'=>'config','detail'=>'TAVILY_API_KEY missing']); exit; }

$jobPayload = [
  "query"        => $jobUrl ? $jobUrl : $searchQ,
  "search_depth" => $jobUrl ? "basic" : "basic",
  "max_results"  => 6
];
$domains = [];
if ($jobHost) $domains[] = $jobHost;
if (!empty($includeDomains)) $domains = array_values(array_unique(array_merge($domains, $includeDomains)));
if ($govOnly) {
  $jobPayload["include_domains"] = !empty($domains) ? $domains : $GOV_DOMAINS_DEFAULT;
} elseif (!empty($domains)) {
  $jobPayload["include_domains"] = $domains;
}

$jobResp = httpPostJson(
  "https://api.tavily.com/search",
  $jobPayload,
  ["Content-Type: application/json", "Accept: application/json", "Authorization: Bearer $TAVILY_API_KEY"]
);
if ($jobResp['error']) {
  http_response_code(502);
  echo json_encode(['error'=>'tavily_error','detail'=>$jobResp['error']]); exit;
}
$jobData    = json_decode($jobResp['body'], true);
$jobResults = $jobData['results'] ?? [];

// Fallback: jos Tavily ei löytänyt mitään ja meillä on URL, lue sivu itse
if (!$jobResults && $jobUrl) {
  $html = httpGet($jobUrl);
  if ($html) {
    $title   = extractTitle($html) ?: $jobUrl;
    $text    = stripToText($html);
    $snippet = trimTo($text, $MAX_SNIPPET_CHARS);
    if ($snippet) {
      $jobResults = [[ 'url'=>$jobUrl, 'title'=>$title, 'snippet'=>$snippet ]];
    }
  }
}

// ===================== 2) TAVILY – COMPANY PAYLOAD (jos yritys tiedossa/voitettu) =====================
$companyResults = [];
$companyPayload = null;
if ($companyName !== '') {
  $companyPayload = [
    "query"        => $companyName,
    "search_depth" => "advanced",
    "max_results"  => 6,
    "time_range"   => "year"
  ];

  // Jos työ-URL:n host on samaa yritystä, anna vihjeeksi include_domains
  if ($jobHost) {
    $companyPayload["include_domains"] = [$jobHost];
  }

  $companyResp = httpPostJson(
    "https://api.tavily.com/search",
    $companyPayload,
    ["Content-Type: application/json", "Accept: application/json", "Authorization: Bearer $TAVILY_API_KEY"]
  );
  if (!$companyResp['error']) {
    $companyData    = json_decode($companyResp['body'], true);
    $companyResults = $companyData['results'] ?? [];
  }
}

// Jos mikään ei löytynyt → palauta “ei tuloksia”
if (!$jobResults && !$companyResults) {
  $out = [
    'answer_html'=>"En löytänyt luotettavia hakutuloksia tähän kysymykseen.",
    'plain_html'=>"En löytänyt hyviä tuloksia. Kokeile täsmentää kysymystä.",
    'fi_html'=>"En löytänyt hyviä tuloksia. Kokeile täsmentää kysymystä.",
    'followups'=>['Mitä yritystä tämä koskee?','Onko sinulla linkkiä työpaikkailmoitukseen?'],
    'citations'=>[],
    'debug' => $debug ? [
      'query' => $searchQ,
      'company' => $companyName,
      'job_payload' => $jobPayload,
      'company_payload' => $companyPayload,
      'job_results' => [],
      'company_results' => []
    ] : null
  ];
  file_put_contents($cacheFile, json_encode($out, JSON_UNESCAPED_UNICODE));
  echo json_encode($out, JSON_UNESCAPED_UNICODE); exit;
}

// ===================== 3) PISTEYTYS & EVIDENCE =====================
$PREFERRED = [
  '/(^|\.)gov(\.|$)/i','/(^|\.)gov\.fi$/i','/(^|\.)valtioneuvosto\.fi$/i','/(^|\.)kela\.fi$/i',
  '/(^|\.)migri\.fi$/i','/(^|\.)te-palvelut\.fi$/i','/(^|\.)poliisi\.fi$/i',
  '/(^|\.)edu(\.|$)/i','/(^|\.)thl\.fi$/i','/(^|\.)stm\.fi$/i','/(^|\.)europa\.eu$/i'
];

$scored = [];
$addItems = function(array $rows, string $kind) use (&$scored, $PREFERRED, $MAX_SNIPPET_CHARS, $q, $jobHost) {
  foreach ($rows as $r) {
    $url = $r['url'] ?? ''; $title = $r['title'] ?? ''; $snippet = $r['snippet'] ?? '';
    if (!$url || (!$title && !$snippet)) continue;
    $host = hostFromUrl($url);
    $score = 0;

    // Peruspreferenssit
    foreach ($PREFERRED as $pat) { if (preg_match($pat, $host)) { $score += 80; break; } }

    // Jos sama host kuin jobHost → +40 (yrityksen oma sivu)
    if ($jobHost && $host === $jobHost) $score += 40;

    // Tekstisosuma
    $score += keywordScore(($title.' '.$snippet), $q);

    // Kind-painotus
    if ($kind === 'company') $score += 25;     // nostetaan yrityssisältöä hieman
    if ($kind === 'job')     $score += 15;

    $scored[] = [
      'kind'    => $kind,
      'url'     => $url,
      'title'   => $title ?: $host,
      'host'    => $host,
      'snippet' => trimTo($snippet, $MAX_SNIPPET_CHARS),
      'score'   => $score
    ];
  }
};

$addItems($jobResults, 'job');
$addItems($companyResults, 'company');

usort($scored, fn($a,$b)=>$b['score']<=>$a['score']);
$top = array_slice($scored, 0, 6); // otetaan 3–6 parasta

// EVIDENCE-teksti + cite-lista
$evidence = ""; $citations=[]; $i=1;
foreach ($top as $it) {
  $prefix = ($it['kind']==='company') ? '[COMPANY] ' : '';
  $evidence .= "[$i] ".$prefix.safeLine($it['title'])." — ".$it['host']." — ".safeLine($it['snippet'])."\n";
  $evidence .= "URL: ".$it['url']."\n\n";
  $citations[] = ['id'=>$i,'url'=>$it['url'],'title'=>$it['title']];
  $i++;
}
$onlyOneSource = (count($citations) < 2);

// ===================== 4) OPENAI (GPT-4o-mini) =====================
if (!$OPENAI_API_KEY) { http_response_code(500); echo json_encode(['error'=>'config','detail'=>'OPENAI_API_KEY missing']); exit; }

// Huomautus, jos vain yksi lähde
$scarcityNote = $onlyOneSource ? <<<TXT
SCARCITY:
- Only one source found. Do NOT block the answer.
- Start both outputs with a concise notice in "{$lang}" that only one source was found and the reader should verify.
TXT : '';

// Kielen pakotus + ohje “käytä yritys-evidenssiä”
$systemPrompt = <<<SYS
You are the Kanavana pilot answer engine.

LANGUAGE RULE:
- The target language is "{$lang}". Always write ALL output in "{$lang}".

OUTPUT:
Return a JSON object with:
- normal_html: a clear, well-structured answer in "{$lang}" using short paragraphs and basic HTML (<p>, <ul>, <li>, <strong>). Add inline refs like [1], [2] aligned to EVIDENCE items.
- plain_html: the same content rewritten in simplified "{$lang}" (shorter sentences and a short action list).
- fi_html: the same as normal_html but translated into Finnish ("fi"), preserving the HTML structure.
- followups: 1–3 short follow-up questions in "{$lang}".

WRITING GUIDELINES (cover letter):
- If EVIDENCE includes details about the company (services, values, culture), explicitly mention 2–3 specific points and connect them to the applicant's strengths.
- If an employer name is inferable, mention it naturally in the greeting or first paragraph.
- Keep it concise, mobile-friendly, and concrete. Avoid repeating the same point.

EVIDENCE USE:
- Base claims only on EVIDENCE. If sources conflict, say so. Do not include a source list inside HTML.
{$scarcityNote}
SYS;

$userPrompt = "QUESTION (in any language, answer in {$lang}): {$q}\n\nEVIDENCE:\n{$evidence}\nINSTRUCTIONS:\n- Answer concisely for mobile. - Do NOT include the source list in HTML.";

// OpenAI-kutsu
$openaiResp = httpPostJson(
  "https://api.openai.com/v1/chat/completions",
  [
    "model"=>"gpt-4o-mini",
    "messages"=>[
      ["role"=>"system","content"=>$systemPrompt],
      ["role"=>"user","content"=>$userPrompt]
    ],
    "temperature"=>0.2,
    "response_format"=>["type"=>"json_object"]
  ],
  ["Content-Type: application/json","Authorization: Bearer $OPENAI_API_KEY"]
);
if ($openaiResp['error']) {
  http_response_code(502);
  echo json_encode(['error'=>'openai_error','detail'=>$openaiResp['error']]); exit;
}
$openaiJson = json_decode($openaiResp['body'], true);
$content = $openaiJson['choices'][0]['message']['content'] ?? '{}';
$parsed  = json_decode($content, true);
$normal  = $parsed['normal_html'] ?? 'Virhe: normal_html puuttuu.';
$plain   = $parsed['plain_html']  ?? 'Virhe: plain_html puuttuu.';
$fi      = $parsed['fi_html']     ?? ''; // voi olla tyhjä jos malli ei palauta – ei kaaduta
$follow  = $parsed['followups']   ?? [];

// ===================== 5) VASTAUS + CACHE =====================
$out = [
  'answer_html'=>$normal,
  'plain_html' =>$plain,
  'fi_html'    =>$fi,
  'followups'  =>$follow,
  'citations'  =>$citations,
  'debug'      => $debug ? [
    'query'           => $searchQ,
    'job_url'         => $jobUrl,
    'company'         => $companyName,
    'gov_only'        => $govOnly,
    'include_domains' => $includeDomains,
    'job_payload'     => $jobPayload,
    'company_payload' => $companyPayload,
    'job_results'     => array_map('debugSlim', $jobResults),
    'company_results' => array_map('debugSlim', $companyResults),
  ] : null
];

file_put_contents($cacheFile, json_encode($out, JSON_UNESCAPED_UNICODE));
echo json_encode($out, JSON_UNESCAPED_UNICODE);

// ===================== HELPERS =====================
function httpPostJson(string $url, array $payload, array $headers=[]): array {
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
  curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(["User-Agent: KanavanaPilot/1.0"], $headers));
  curl_setopt($ch, CURLOPT_TIMEOUT, 25);
  if (defined('CURL_IPRESOLVE_V4') && defined('CURLOPT_IPRESOLVE')) {
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
function test_http(string $url, array $headers=[]): array {
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_NOBODY, true);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_TIMEOUT, 10);
  if (defined('CURL_IPRESOLVE_V4') && defined('CURLOPT_IPRESOLVE')) {
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
  }
  if ($headers) curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(["User-Agent: KanavanaPilot/1.0"], $headers));
  $ok = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err = curl_error($ch) ?: null;
  curl_close($ch);
  return ['http_code'=>$code, 'curl_error'=>$err];
}
function test_http_post(string $url, array $payload, array $headers=[]): array {
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
  curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(["User-Agent: KanavanaPilot/1.0","Accept: application/json"], $headers));
  curl_setopt($ch, CURLOPT_TIMEOUT, 15);
  if (defined('CURL_IPRESOLVE_V4') && defined('CURLOPT_IPRESOLVE')) {
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
  }
  $body = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err  = curl_error($ch) ?: null;
  curl_close($ch);
  return ['http_code'=>$code, 'curl_error'=>$err, 'body_snippet'=> $body ? mb_substr($body,0,240,'UTF-8') : null];
}
function hostFromUrl(string $url): string { return parse_url($url, PHP_URL_HOST) ?: ''; }
function trimTo(string $text, int $max): string {
  $text = preg_replace('/\s+/', ' ', strip_tags($text));
  if (mb_strlen($text,'UTF-8') <= $max) return $text;
  $cut = mb_substr($text, 0, $max-1, 'UTF-8');
  $sp  = mb_strrpos($cut, ' ', 0, 'UTF-8');
  if ($sp !== false) $cut = mb_substr($cut, 0, $sp, 'UTF-8');
  return rtrim($cut, " \t\n\r\0\x0B.,;:") . '…';
}
function keywordScore(string $haystack, string $needle): int {
  $hay = mb_strtolower($haystack, 'UTF-8');
  $need = preg_split('/\s+/', mb_strtolower($needle, 'UTF-8'));
  $score = 0;
  foreach ($need as $w) { $w = trim($w); if ($w==='' || mb_strlen($w,'UTF-8')<3) continue; $score += substr_count($hay,$w); }
  return min($score, 20);
}
function safeLine(string $s): string { return trim(preg_replace('/\s+/', ' ', $s)); }
function httpGet(string $url, array $headers=[]): string {
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($ch, CURLOPT_TIMEOUT, 15);
  if (defined('CURL_IPRESOLVE_V4') && defined('CURLOPT_IPRESOLVE')) {
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
  }
  if ($headers) curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(["User-Agent: KanavanaPilot/1.0"], $headers));
  $body = curl_exec($ch); curl_close($ch); return (string)$body;
}
function extractTitle(string $html): ?string {
  if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
    return safeLine(html_entity_decode($m[1] ?? '', ENT_QUOTES|ENT_HTML5, 'UTF-8'));
  }
  return null;
}
function stripToText(string $html): string {
  $html = preg_replace('#<script[^>]*>.*?</script>#is', ' ', $html);
  $html = preg_replace('#<style[^>]*>.*?</style>#is', ' ', $html);
  $text = strip_tags($html);
  return preg_replace('/\s+/', ' ', $text);
}
function guessCompanyNameFromHost(string $host): string {
  // yksinkertainen: ota pääosa ennen ensimmäistä pistettä ja siisti
  $base = preg_replace('/\.(fi|se|no|com|net|org|eu)$/i', '', $host);
  $base = explode('.', $base)[0] ?? $base;
  $base = preg_replace('/[^a-zåäöA-ZÅÄÖ0-9 -]/u', ' ', $base);
  $base = trim($base);
  if ($base === '') return '';
  // Kapitaalit nätiksi: "autoklinikka" -> "Autoklinikka"
  return mb_convert_case($base, MB_CASE_TITLE, "UTF-8");
}
function debugSlim($r) {
  if (!is_array($r)) return $r;
  return [
    'url'     => $r['url']     ?? null,
    'title'   => $r['title']   ?? null,
    'host'    => isset($r['url']) ? hostFromUrl($r['url']) : null,
    'snippet' => isset($r['snippet']) ? trimTo($r['snippet'], 220) : null,
    'score'   => null
  ];
}
