<?php
declare(strict_types=1);

/**
 * planner.php
 * - GET  -> renderöi UI:n (ei inline-skriptejä)
 * - POST -> ?api=1: välittää historian + nykyisen vastauksen OpenAI:lle ja palauttaa JSONin
 *
 * Kovennukset:
 *  - POST palauttaa aina JSONin (ei HTML-virhesivuja).
 *  - display_errors pois POST-haarassa.
 *  - API-avain useasta lähteestä (SERVER/ENV + REDIRECT_).
 *  - Ei mbstring-riippuvuutta (shorten()).
 *  - Kovat syöterajat: TOPIC <= 120, INPUT <= 2000 merkkiä, raakadata <= 200 kB.
 */

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// --- Syöterajat ---
const MAX_TOPIC_LEN   = 120;
const MAX_INPUT_LEN   = 2000;
const MAX_BODY_BYTES  = 200 * 1024; // 200 kB
const MAX_HISTORY_LEN = 7;          // varmistus, vaikka UI rajoittaa

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['api'])) {
    // --- API (POST) ---
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    ini_set('display_errors', '0');
    // ⬇️ Korjattu typoja: E_ALL, ei EALL
    error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

    $raw = file_get_contents('php://input') ?: '';
    if (strlen($raw) > MAX_BODY_BYTES) {
        echo json_encode(['ok'=>false,'error'=>'payload_too_large','limits'=>['max_body_bytes'=>MAX_BODY_BYTES]]);
        exit;
    }
    $req = json_decode($raw, true);
    if (!is_array($req)) { echo json_encode(['ok'=>false,'error'=>'bad_json_request']); exit; }

    $lang      = clean((string)($req['lang'] ?? 'fi'));
    $topic     = trim((string)($req['topic'] ?? ''));
    $historyIn = $req['history'] ?? [];
    $input     = trim((string)($req['input'] ?? ''));
    $stepIndex = (int)($req['stepIndex'] ?? 0);
    $forceDone = !empty($req['forceDone']);

    if (strlen_u($topic) > MAX_TOPIC_LEN) { echo json_encode(['ok'=>false,'error'=>'input_too_long','field'=>'topic','limits'=>['max_topic_len'=>MAX_TOPIC_LEN]]); exit; }
    if (strlen_u($input) > MAX_INPUT_LEN) { echo json_encode(['ok'=>false,'error'=>'input_too_long','field'=>'input','limits'=>['max_input_len'=>MAX_INPUT_LEN]]); exit; }
    if ($topic === '' && $input === '' && empty($historyIn)) { echo json_encode(['ok'=>false,'error'=>'empty_request']); exit; }

    $apiKey = getApiKey();
    if (!$apiKey) { echo json_encode(['ok'=>false,'error'=>'missing_api_key']); exit; }

    // Varmista että cURL on saatavilla (muuten fatal)
    if (!function_exists('curl_init')) {
        echo json_encode(['ok'=>false,'error'=>'curl_missing','detail'=>'PHP cURL extension is not available']);
        exit;
    }

    // Rajaa historia
    $historyIn = array_values($historyIn);
    if (count($historyIn) > MAX_HISTORY_LEN) $historyIn = array_slice($historyIn, -MAX_HISTORY_LEN);

    $compactHistory = [];
    foreach ($historyIn as $h) {
        $aiField = $h['ai'] ?? '';
        if (is_array($aiField)) $aiField = $aiField['message'] ?? json_encode($aiField, JSON_UNESCAPED_UNICODE);
        $compactHistory[] = [
            'n'    => (int)($h['n'] ?? 0),
            'user' => shorten((string)($h['user'] ?? ''), 600),
            'ai'   => shorten((string)$aiField, 600),
        ];
    }

    $system = buildSystemPrompt($lang);
    $userPayload =
        "LANG: {$lang}\n" .
        "ROOT_TOPIC: " . json_encode($topic, JSON_UNESCAPED_UNICODE) . "\n" .
        "STEP_INDEX: {$stepIndex}\n" .
        "FORCE_DONE: " . ($forceDone ? 'true' : 'false') . "\n" .
        "CURRENT_INPUT: " . json_encode($input, JSON_UNESCAPED_UNICODE) . "\n" .
        "HISTORY_JSON: " . json_encode($compactHistory, JSON_UNESCAPED_UNICODE) . "\n" .
        "Respond STRICTLY as a single JSON object.";

    $messages = [
        ['role' => 'system', 'content' => $system],
        ['role' => 'user',   'content' => $userPayload],
    ];

    $resp = callOpenAIChat($apiKey, $messages);
    if (!$resp['ok']) { echo json_encode($resp, JSON_UNESCAPED_UNICODE); exit; }

    $content = (string)($resp['content'] ?? '');
    $parsed  = json_decode($content, true);
    if (!is_array($parsed) || !isset($parsed['status'], $parsed['message'])) {
        $parsed = ['status'=>'instruct','title'=>'Seuraava askel','message'=>$content!==''?$content:'Jatketaan eteenpäin: kuvaile seuraava yksityiskohta.','checklist'=>[]];
    }

    echo json_encode(['ok'=>true,'ai'=>$parsed], JSON_UNESCAPED_UNICODE);
    exit;
}

// ---- GET (UI) ----
?><!doctype html>
<html lang="fi">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <meta name="robots" content="noindex,nofollow" />
  <title>Planner – pilot.kanavana.fi</title>
  <link rel="stylesheet" href="assets/css/planner.css">
</head>
<body>
  <div class="page">
    <header class="topbar">
      <div class="container">
        <div class="brand">
          <strong id="i18n_title">Suunnittelija</strong>
          <span class="muted" id="i18n_privacy">Tietosi pysyvät vain selaimessasi.</span>
        </div>
        <div class="controls">
          <label for="lang" id="i18n_lang_label">Kieli</label>
          <select id="lang">
            <option value="fi">Suomi</option>
            <option value="en">English</option>
            <option value="sv">Svenska</option>
          </select>
          <button id="btnRestart" class="ghost"><span id="i18n_restart">Aloita alusta</span></button>
          <button id="btnExport" class="ghost"><span id="i18n_export">Lataa suunnitelma</span></button>
          <button id="btnHardWipe" class="danger"><span id="i18n_hard_wipe">Poista kaikki</span></button>
          <button id="btnQuickExit" class="exit"><span id="i18n_quick_exit">Hätäpoistuminen</span></button>
        </div>
      </div>
    </header>

    <div class="notice privacy">
      <div class="container">
        <p id="i18n_privacy_disclaimer">
          Tämä työkalu toimii vain selaimessasi. Emme tallenna palvelimelle emmekä käytä analytiikkaa.
          Vastauksesi lähetetään kuitenkin tekoälypalvelulle suunnitelman tuottamista varten. Vältä henkilötietoja
          tai käytä alla olevaa yksityisyysfiltteriä.
        </p>
        <label class="privacy-filter">
          <input type="checkbox" id="privacyFilter" checked />
          <span id="i18n_privacy_filter_label">
            Yksityisyysfiltteri on päällä (poistaa sähköpostit ja puhelinnumerot ennen lähettämistä).
          </span>
        </label>
        <p class="privacy-tips" id="i18n_quick_tips">
          Vinkki: jaetulla laitteella käytä yksityistä selausta. Tarvittaessa paina <kbd>Esc</kbd> kaksi kertaa
          nopeaan poistumiseen tai <kbd>Shift</kbd>+<kbd>Del</kbd> poistaaksesi kaiken.
        </p>
      </div>
    </div>

    <main class="container">
      <div class="layout layout-narrow">
        <aside class="timeline" id="timeline" aria-label="Askelpolku"></aside>

        <section class="workspace">
          <div class="card">
            <div class="topic">
              <label for="topicInput" id="i18n_topic_label">Suunnitelman aihe</label>
              <input id="topicInput" type="text" placeholder="Esim. 'Kirjoita rakkauskirje' tai 'Pyykinpesu'"/>
            </div>

            <div class="prompt">
              <label for="promptInput" id="i18n_prompt_label">Vastaus / tarkennus</label>
              <textarea id="promptInput" rows="6" placeholder="Kirjoita vastaus tai kuvaus tähän..."></textarea>

              <div class="prompt-actions sticky">
                <button id="btnNext" class="primary"><span id="i18n_next">Seuraava</span></button>
              </div>
            </div>
          </div>

          <div class="card ai-panel" id="aiPanel" aria-live="polite"></div>

          <footer class="footnote">
            <small id="i18n_footer_note">
              Yksityisyys: Suunnitelma tallentuu vain selaimesi muistiin. Vie talteen (.txt) tai kopioi/lähetä sähköpostilla.
            </small>
          </footer>
        </section>
      </div>
    </main>
  </div>

  <!-- Latausoverlay -->
  <div id="loadingOverlay" class="loading-overlay" hidden aria-hidden="true">
    <div class="spinner" role="status" aria-live="assertive" aria-label="Loading"></div>
    <div class="loading-text" id="i18n_loading">Ladataan…</div>
  </div>

  <!-- Ei inline-skriptejä -->
  <script src="assets/js/planner.config.js"></script>
  <script src="assets/js/planner.js"></script>
</body>
</html>

<?php
// ---------- Helpers ----------
function clean(string $s): string { $s = trim($s); $out = preg_replace('/[^\P{C}\n\r\t]/u', '', $s); return $out !== null ? $out : ''; }
function strlen_u(string $s): int { return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s); }
function shorten(string $s, int $len = 600): string {
    if (function_exists('mb_strlen')) return mb_strlen($s,'UTF-8')>$len ? mb_substr($s,0,$len,'UTF-8').'…' : $s;
    return strlen($s)>$len ? substr($s,0,$len).'…' : $s;
}
function buildSystemPrompt(string $lang): string {
    $locale = $lang ?: 'fi';
    return <<<SYS
You are PlannerGPT, a strict step-by-step planner used in a UI with a maximum of 7 steps.
Respond in {$locale}.

Rules:
- Output MUST be a single valid JSON object with keys:
  - "status": one of "ask" | "instruct" | "done"
  - "title": short string (<= 60 chars)
  - "message": plain text (no code blocks, no links)
  - "checklist": array of short strings (optional; [] if none)
- Stay STRICTLY on the user's ROOT_TOPIC. If user drifts off-topic, tell them to start a new plan and set "status":"done".
- Aim to finish within 7 steps. If STEP_INDEX >= 6 (0-based) or FORCE_DONE=true, return "status":"done" with a concise final summary and a checklist of next concrete actions.
- When information is missing, prefer "status":"ask" and ask ONE focused question.
- Otherwise use "status":"instruct" and provide a concrete, actionable next step.
- No code, no scripts, no links. Plain language only.
- Friendly but concise. Keep messages compact.

Return ONLY JSON, nothing else.
SYS;
}
function getApiKey(): string {
    $candidates = [
        $_SERVER['OPENAI_API_KEY']          ?? null,
        $_SERVER['REDIRECT_OPENAI_API_KEY'] ?? null,
        getenv('OPENAI_API_KEY')            ?: null,
        getenv('REDIRECT_OPENAI_API_KEY')   ?: null,
    ];
    foreach ($candidates as $c) if (is_string($c) && trim($c) !== '') return trim($c);
    return '';
}
function callOpenAIChat(string $apiKey, array $messages): array {
    $url = 'https://api.openai.com/v1/chat/completions';
    $payload = ['model'=>'gpt-4o-mini','messages'=>$messages,'temperature'=>0.2,'max_tokens'=>500,'n'=>1];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json','Authorization: Bearer '.$apiKey],
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 25,
    ]);
    $res = curl_exec($ch);
    if ($res === false) { $err = curl_error($ch); curl_close($ch); return ['ok'=>false,'error'=>'curl_error','detail'=>$err]; }
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $json = json_decode($res, true);
    if ($status >= 400 || !isset($json['choices'][0]['message']['content'])) return ['ok'=>false,'error'=>'api_error','status'=>$status,'detail'=>$res];
    return ['ok'=>true,'content'=>(string)$json['choices'][0]['message']['content']];
}
