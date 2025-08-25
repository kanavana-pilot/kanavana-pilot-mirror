<?php
/**
 * TaskLamp backend (same-origin, no CORS needs).
 * - Reads JSON: { lang, country, city?, task, followup? }
 * - Uses Tavily (optional) and OpenAI to build step-by-step plan
 * - Returns JSON: { steps: [...], needs_clarification: bool, followup_prompt?: string }
 *
 * ENV (SetEnv in .htaccess):
 *  - OPENAI_API_KEY
 *  - TAVILY_API_KEY
 *
 * CSP: connect-src 'self' — this file must be on same origin.
 */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'Method not allowed']); exit;
}

$raw = file_get_contents('php://input');
$in  = json_decode($raw ?? '', true);

$lang    = trim((string)($in['lang'] ?? 'fi'));
$country = trim((string)($in['country'] ?? ''));
$city    = trim((string)($in['city'] ?? ''));
$task    = trim((string)($in['task'] ?? ''));
$follow  = trim((string)($in['followup'] ?? ''));

if ($country === '' || $task === '') {
  http_response_code(400);
  echo json_encode(['error' => 'country and task are required']); exit;
}

$OPENAI = getenv('OPENAI_API_KEY') ?: '';
$TAVILY = getenv('TAVILY_API_KEY') ?: '';
if (!$OPENAI) {
  http_response_code(500);
  echo json_encode(['error' => 'OPENAI_API_KEY missing']); exit;
}

// --------------- Helper: HTTP ---------------
function http_json(string $url, array $opts = []): array {
  $headers = $opts['headers'] ?? ['Content-Type: application/json'];
  $payload = $opts['body'] ?? null;
  $timeout = $opts['timeout'] ?? 20;

  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT        => $timeout,
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_POST           => $payload !== null,
    CURLOPT_POSTFIELDS     => $payload,
  ]);
  $resp = curl_exec($ch);
  $err  = curl_error($ch);
  $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($resp === false) return ['__http_error' => $err, '__status' => $code];
  $data = json_decode($resp, true);
  if (!is_array($data)) return ['__parse_error' => true, '__status' => $code, '__raw' => $resp];
  $data['__status'] = $code;
  return $data;
}

// --------------- Optional: Tavily search ---------------
$tavilyResults = [];
if ($TAVILY) {
  $q = trim($task . ' ' . $city . ' ' . $country);
  $tavilyPayload = json_encode([
    'query' => $q,
    'search_depth' => 'advanced',
    'include_answer' => false,
    'include_images' => false,
    'max_results' => 6
  ]);
  $tavily = http_json(
    'https://api.tavily.com/search',
    [
      'headers' => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $TAVILY
      ],
      'body' => $tavilyPayload,
      'timeout' => 25
    ]
  );
  if (!isset($tavily['__http_error']) && isset($tavily['results']) && is_array($tavily['results'])) {
    // keep only fields we need
    foreach ($tavily['results'] as $r) {
      $tavilyResults[] = [
        'title' => $r['title'] ?? '',
        'url' => $r['url'] ?? '',
        'content' => $r['content'] ?? ($r['snippet'] ?? ''),
        'source' => $r['source'] ?? '',
      ];
    }
  }
}

// --------------- OpenAI prompt ---------------
$SYSTEM = <<<SYS
You are TaskLamp, a cautious assistant that returns a **strict JSON** plan with clear, actionable, short steps for migrants.
- Output ONLY valid JSON (UTF-8). No prose, no code fences.
- 5–8 steps. Keep each step compact but complete. Use one official link per step if necessary.
- Required JSON schema:
{
  "steps":[
    {"order":1,"title":"string","action":"string","url":"string|null","deadline":"string|null","attachments_hint":"string|null","print_hint":"string|null","submit_place":"string|null","submit_method":"string|null","verify_flag":boolean}
  ],
  "needs_clarification": boolean,
  "followup_prompt": "string|null"
}
Rules:
- Prefer official domains (.gov, municipality, .eu) when visible in the provided context, but do not invent links.
- If address or city is missing but necessary for printing/submission, set "needs_clarification": true and craft a short, friendly "followup_prompt" asking for the missing detail.
- If some info may change (deadlines required papers), set verify_flag=true on that step.
- If no suitable official link is found in context, set url=null and advise which section to find on the official site.
SYS;

$USER = [
  'lang' => $lang,
  'country' => $country,
  'city' => $city,
  'task' => $task,
  'followup' => $follow,
  'tavily' => $tavilyResults, // context for links/addresses
];

$payload = json_encode([
  'model' => 'gpt-4o-mini',
  'messages' => [
    ['role' => 'system', 'content' => $SYSTEM],
    ['role' => 'user',   'content' => json_encode($USER, JSON_UNESCAPED_UNICODE)]
  ],
  'temperature' => 0.2,
  'response_format' => ['type' => 'json_object']
], JSON_UNESCAPED_UNICODE);

$openai = http_json(
  'https://api.openai.com/v1/chat/completions',
  [
    'headers' => [
      'Content-Type: application/json',
      'Authorization: ' . ('Bearer ' . $OPENAI)
    ],
    'body' => $payload,
    'timeout' => 45
  ]
);

if (isset($openai['__http_error']) || !isset($openai['choices'][0]['message']['content'])) {
  http_response_code(502);
  echo json_encode(['error' => 'AI_unavailable']); exit;
}

$outJson = $openai['choices'][0]['message']['content'];
$out = json_decode($outJson, true);
if (!is_array($out) || !isset($out['steps']) || !is_array($out['steps'])) {
  // fallback minimal structure
  $out = [
    'steps' => [],
    'needs_clarification' => true,
    'followup_prompt' => 'Could you specify the city or the exact office?'
  ];
}

// Basic sanitization: ensure fields exist
$norm = [];
$order = 1;
foreach ($out['steps'] as $s) {
  $norm[] = [
    'order' => (int)($s['order'] ?? $order++),
    'title' => trim((string)($s['title'] ?? '')),
    'action' => trim((string)($s['action'] ?? '')),
    'url' => (isset($s['url']) && $s['url'] !== null) ? filter_var($s['url'], FILTER_SANITIZE_URL) : null,
    'deadline' => $s['deadline'] ?? null,
    'attachments_hint' => $s['attachments_hint'] ?? null,
    'print_hint' => $s['print_hint'] ?? null,
    'submit_place' => $s['submit_place'] ?? null,
    'submit_method' => $s['submit_method'] ?? null,
    'verify_flag' => (bool)($s['verify_flag'] ?? false),
  ];
}
$resp = [
  'steps' => $norm,
  'needs_clarification' => (bool)($out['needs_clarification'] ?? false),
  'followup_prompt' => $out['followup_prompt'] ?? null
];

// No persistence; return directly
echo json_encode($resp, JSON_UNESCAPED_UNICODE);
