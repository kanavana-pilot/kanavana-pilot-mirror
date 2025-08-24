<?php
require_once __DIR__ . '/../lib/util.php';
require_once __DIR__ . '/../lib/gpt.php';

$sessionId = $_POST['sessionId'] ?? '';
$answer = trim($_POST['answer'] ?? '');
if (!$sessionId || $answer==='') jsonOut(['ok'=>false,'error'=>'Virheelliset parametrit']);

$pdo = db();
$row = $pdo->query("SELECT * FROM sessions WHERE id=".$pdo->quote($sessionId))->fetch(PDO::FETCH_ASSOC);
if (!$row) jsonOut(['ok'=>false,'error'=>'Sessio puuttuu']);

$turns = json_decode($row['turns_json'] ?? '[]', true);
if (!is_array($turns) || empty($turns)) jsonOut(['ok'=>false,'error'=>'Sessio on virheellinen']);

$idx = count($turns) - 1; // viimeisin askel on se, johon vastaamme
$turns[$idx]['userAnswer'] = $answer;

// Rakenna konteksti vastatuista askelista
$context = "Original (".$row['language']."): ".$row['original_input']."\n";
for ($i=0; $i<count($turns); $i++){
  if (!empty($turns[$i]['userAnswer'])){
    $context .= "- A".($i+1).": ".$turns[$i]['userAnswer']."\n";
  }
}

$sys = prompt_kirkastaja_system();
$userMsg = $context."\nPlease update the short summary (Finnish) and ask ONE next follow-up question (Finnish).";
$resp = gpt_chat([
  ['role'=>'system','content'=>$sys],
  ['role'=>'user','content'=>$userMsg]
]);
if(isset($resp['error'])) jsonOut(['ok'=>false,'error'=>$resp['error']]);
$out = $resp['choices'][0]['message']['content'] ?? '';

$summary = $out;
$question = 'Vielä yksi tarkennus?';
if (preg_match('/(?s)^(.*?)(?:Kysymys|Kysymykset):\s*(.*)$/i', $out, $m)) {
  $summary = trim($m[1]);
  $question = trim($m[2]);
}

// Lisää uusi vaihe (uusi kysymys, johon käyttäjä vastaa seuraavaksi)
$turns[] = [ 'summary'=>$summary, 'question'=>strip_tags($question), 'userAnswer'=>null ];

$pdo->prepare("UPDATE sessions SET turns_json=? WHERE id=?")
    ->execute([json_encode($turns, JSON_UNESCAPED_UNICODE), $sessionId]);

jsonOut(['ok'=>true,'summary'=>$summary,'question'=>strip_tags($question)]);
