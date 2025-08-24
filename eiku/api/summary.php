<?php
require_once __DIR__ . '/../lib/util.php';
require_once __DIR__ . '/../lib/gpt.php';
require_once __DIR__ . '/../lib/nlp.php';

$sessionId = $_POST['sessionId'] ?? '';
$tone = $_POST['tone'] ?? 'neutral';
$length = $_POST['length'] ?? 'short';
$audience = $_POST['audience'] ?? 'general';
$formats = json_decode($_POST['formats'] ?? '[]', true);

$pdo = db();
$row = $pdo->query("SELECT * FROM sessions WHERE id=".$pdo->quote($sessionId))->fetch(PDO::FETCH_ASSOC);
if (!$row) jsonOut(['ok'=>false,'error'=>'Sessio puuttuu']);

$turns = json_decode($row['turns_json'] ?? '[]', true);

// Sentiment softening: if harsh, force neutral polite note
$sent = sentiment_hint($row['original_input'] ?? '');
$softenNote = ($sent === 'harsh') ? "Käytä neutraalia ja kohteliasta sävyä. Vältä kärkevää ilmaisuja." : null;
if ($sent === 'harsh') { $tone = 'neutral'; }

$context = "Original (".$row['language']."): ".$row['original_input']."\n";
foreach($turns as $i=>$t){ $context .= "- A".($i+1).": ".$t['userAnswer']."\n"; }

$styleHeader = "STYLE: Tone=".$tone."; Length=".$length."; Audience=".$audience."; Formats=".implode(',', $formats);

$sys = prompt_kirkastaja_system();
$userMsg = $context."\nNow produce the FINAL PROMPT in Finnish, with explicit style header.\n".
  "Header format: '".$styleHeader."'\n".
  ($softenNote ? "SOFTENING: ".$softenNote."\n" : "").
  "Then a blank line and the exact final prompt text only.";

$resp = gpt_chat([
  ['role'=>'system','content'=>$sys],
  ['role'=>'user','content'=>$userMsg]
]);
if(isset($resp['error'])) jsonOut(['ok'=>false,'error'=>$resp['error']]);
$final = trim($resp['choices'][0]['message']['content'] ?? '');

$pdo->prepare("UPDATE sessions SET final_prompt=? WHERE id=?")->execute([$final, $sessionId]);

jsonOut(['ok'=>true,'original'=>$row['original_input'],'finalPrompt'=>$final, 'softeningApplied'=> (bool)$softenNote]);
