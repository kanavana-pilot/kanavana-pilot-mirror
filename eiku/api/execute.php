<?php
require_once __DIR__ . '/../lib/util.php';
require_once __DIR__ . '/../lib/gpt.php';

$sessionId = $_POST['sessionId'] ?? '';
$pdo = db();
$row = $pdo->query("SELECT * FROM sessions WHERE id=".$pdo->quote($sessionId))->fetch(PDO::FETCH_ASSOC);
if (!$row) jsonOut(['ok'=>false,'error'=>'Sessio puuttuu']);
$final = $row['final_prompt'] ?? '';
if (!$final) jsonOut(['ok'=>false,'error'=>'Lopullista promptia ei ole vielä luotu']);

$messages = [
  ['role'=>'system','content'=>'You are a helpful expert assistant. Provide accurate, concise answers in Finnish.'],
  ['role'=>'user','content'=>$final]
];
$resp = gpt_chat($messages, 'gpt-4o-mini', 0.2);
if(isset($resp['error'])) jsonOut(['ok'=>false,'error'=>$resp['error']]);
$answer = $resp['choices'][0]['message']['content'] ?? '';

$pdo->prepare("UPDATE sessions SET executed_answer=? WHERE id=?")->execute([$answer, $sessionId]);
jsonOut(['ok'=>true,'answer'=>$answer]);
