<?php
/* common_sense.php — Common-sense agent (MVP)
 * - UI + API (?action=plan|step|interrupt)
 * - Kaikki viittaukset common_sense-prefiksillä
 */
session_start();
header('X-Frame-Options: SAMEORIGIN');

function json_out($arr, $code=200){
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
  exit;
}
function now_iso(){ return gmdate('c'); }

function env_api_key(){
  $k = getenv('OPENAI_API_KEY');
  if (!$k && isset($_SERVER['OPENAI_API_KEY'])) $k = $_SERVER['OPENAI_API_KEY'];
  return $k ?: '';
}

function openai_call($system, $userPayload){
  $apiKey = env_api_key();
  if(!$apiKey){ // Dry-run demo ilman avainta
    return [
      "plan_version"=> now_iso(),
      "next_action"=> ["type"=>"place_item","args"=>["item"=>"knife","target"=>"nearest_knife_storage"]],
      "reasoning_summary"=>"Turvasääntö: veitsi ensin talteen, sitten jatketaan varsinaista tehtävää.",
      "risks"=>["Veitsitelinettä ei löydy → käytä väliaikaista suojapaikkaa"],
      "exit_criteria"=>["Veitsi sijoitettu hyväksyttyyn paikkaan"],
      "memory_writes"=>[["key"=>"last_safe_spot","value"=>"knife_rack_kitchen_northwall","ttl_days"=>30]]
    ];
  }

  $url = "https://api.openai.com/v1/chat/completions";
  $body = [
    "model" => "gpt-4o-mini",
    "response_format" => ["type"=>"json_object"],
    "messages" => [
      ["role"=>"system","content"=>$system],
      ["role"=>"user","content"=> json_encode($userPayload, JSON_UNESCAPED_UNICODE)]
    ],
    "temperature" => 0.2
  ];

  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_HTTPHEADER=>[
      "Authorization: Bearer {$apiKey}",
      "Content-Type: application/json"
    ],
    CURLOPT_POST=>true,
    CURLOPT_POSTFIELDS=>json_encode($body)
  ]);
  $res = curl_exec($ch);
  if($res===false){ return ["error"=>"curl_error","detail"=>curl_error($ch)]; }
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  $json = json_decode($res,true);
  if($code>=400 || !$json){ return ["error"=>"api_error","detail"=>"Upstream error"]; }

  $content = $json['choices'][0]['message']['content'] ?? '{}';
  $obj = json_decode($content,true);
  if(!$obj){ return ["error"=>"format_error","detail"=>"Model returned non-JSON"]; }
  return $obj;
}

/* Guardrail + skeema-validaattori (kevyt) */
function validate_agent_reply($out){
  $errors = [];
  foreach (["plan_version","next_action","reasoning_summary","risks","exit_criteria"] as $k){
      if(!isset($out[$k])) $errors[]="Missing field: $k";
  }
  if(!isset($out["next_action"]["type"])) $errors[]="next_action.type missing";
  if(!isset($out["next_action"]["args"]) || !is_array($out["next_action"]["args"])) $errors[]="next_action.args missing or invalid";

  $allowed = ["place_item","move","open","classify","plan_subgoal","ask_user","replan","wait","noop"];
  if(isset($out["next_action"]["type"]) && !in_array($out["next_action"]["type"], $allowed)){
      $errors[]="Disallowed next_action.type: ".$out["next_action"]["type"];
  }

  if(isset($out["next_action"]["args"]["items"]) && is_array($out["next_action"]["args"]["items"])){
      $items = array_map('strval', $out["next_action"]["args"]["items"]);
      if(in_array("knife",$items) && count($items)>1){
          $errors[]="Safety violation: knife cannot be grasped with other items.";
      }
  }
  return $errors;
}

/* Session memory */
if(!isset($_SESSION['common_sense_log'])) $_SESSION['common_sense_log']=[];
if(!isset($_SESSION['common_sense_memory'])) $_SESSION['common_sense_memory']=[];

$action = $_GET['action'] ?? null;
if($action){
  $system = <<<SYS
Role: Common-sense Task Planner.
Principles:
1) Safety & rules > efficiency.
2) Hierarchical, interruptible plan. If obstacle, produce subgoal.
3) Do not invent missing data; ask clarification or propose safe default.
4) Always return JSON:
{ "plan_version": ISO8601,
  "next_action": { "type": "...", "args": {...}},
  "reasoning_summary": "1-2 sentences",
  "risks": ["..."],
  "exit_criteria": ["..."],
  "memory_writes": [{"key":"...","value":"...","ttl_days":30}] }
5) Forbidden: unsafe combos (knife + others in same grasp), unnecessary personal data handling.
SYS;

  $raw = file_get_contents('php://input');
  $payload = $raw ? json_decode($raw,true) : [];
  $payload['session_memory'] = $_SESSION['common_sense_memory'];

  $out = openai_call($system, $payload);
  $valid = validate_agent_reply($out);

  if($valid){ json_out(["ok"=>false,"errors"=>$valid], 400); }

  if(isset($out["memory_writes"]) && is_array($out["memory_writes"])){
    foreach($out["memory_writes"] as $mw){
      $k = $mw["key"] ?? null; $v = $mw["value"] ?? null;
      if($k){ $_SESSION['common_sense_memory'][$k] = ["value"=>$v, "ts"=>time(), "ttl_days"=>($mw["ttl_days"]??30)]; }
    }
  }

  $_SESSION['common_sense_log'][] = ["t"=>now_iso(), "in"=>$payload, "out"=>$out];
  json_out(["ok"=>true,"data"=>$out,"memory"=>$_SESSION['common_sense_memory']]);
}

/* --- UI --- */
$hasKey = env_api_key() ? true : false;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Common Sense Agent (MVP)</title>
  <link rel="stylesheet" href="/assets/css/common_sense.css">
</head>
<body>
<div class="wrap">
  <h1>Common Sense Agent (MVP)
    <span id="apiState" class="badge <?= $hasKey?'ok':'err' ?>">
      <?= $hasKey ? 'OPENAI_API_KEY OK' : 'DEMO (no key)' ?>
    </span>
  </h1>

  <div class="card">
    <p>This UI calls <code>?action=plan|step|interrupt</code>.  
       If no API key, you get a demo dry-run response.</p>
  </div>

  <div class="card">
    <label>Goal</label>
    <input id="goal" placeholder="e.g. Put mixed stack of dishes into cabinets">

    <label class="mt">Context (JSON)</label>
    <textarea id="context" rows="7">{
  "env": "home-kitchen",
  "capabilities": ["open","move","grasp","place","classify"],
  "constraints": {"safety":["no_knife_with_others"], "budget": null},
  "knowns": [],
  "unknowns": ["cabinet contents"]
}</textarea>

    <div class="btnrow">
      <button class="primary" id="btnPlan">/plan</button>
      <button id="btnStep">/step</button>
      <button class="warn" id="btnInterrupt">/interrupt</button>
      <button id="btnReload">Reload log</button>
    </div>
  </div>

  <div class="card">
    <label>Latest Response</label>
    <pre id="out">{ }</pre>
  </div>

  <div class="card">
    <label>Session Memory</label>
    <pre id="mem">{ }</pre>
  </div>

  <div class="card">
    <label>Log</label>
    <pre id="log"><?= htmlspecialchars(json_encode($_SESSION['common_sense_log'], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)); ?></pre>
  </div>
</div>

<script src="/assets/js/common_sense.js"></script>
</body>
</html>
