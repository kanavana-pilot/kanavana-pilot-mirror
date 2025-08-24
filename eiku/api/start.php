<?php
require_once __DIR__ . '/../lib/util.php';
require_once __DIR__ . '/../lib/gpt.php';
require_once __DIR__ . '/../lib/translate.php';

header('Content-Type: application/json; charset=utf-8');

try {
  $text = trim($_POST['text'] ?? '');
  if ($text==='') jsonOut(['ok'=>false,'error'=>'Tyhjä syöte']);

  $tr = translate_to_en($text);
  $en = $tr['text']; $lang = $tr['lang'] ?? 'fi';

  $sys = prompt_kirkastaja_system();
  $userMsg = "ORIGINAL (".$lang."): ".$text."\n\nEN_VERSION: ".$en."\n\nPlease: 1) Summarize intent (2-4 sentences). 2) Ask 1-2 follow-up questions in Finnish if original language was Finnish, otherwise in the original language.";
  $resp = gpt_chat([
    ['role'=>'system','content'=>$sys],
    ['role'=>'user','content'=>$userMsg]
  ]);

  if(isset($resp['error'])) jsonOut(['ok'=>false,'error'=>$resp['error']]);

  $answer = $resp['choices'][0]['message']['content'] ?? '';
  if (!$answer) jsonOut(['ok'=>false,'error'=>'OpenAI tyhjä vastaus']);

  $summary = $answer;
  $question = 'Mikä on tärkein tavoitteesi?';
  if (preg_match('/(?s)^(.*?)(?:Kysymys|Kysymykset|Question)\\s*:\\s*(.*)$/i', $answer, $m)) {
    $summary = trim($m[1]);
    $question = trim($m[2]);
  }

// ...gpt-vastaus -> $summary, $question jo olemassa

$sid = uuid();
$turns = [
  [ 'summary' => $summary, 'question' => strip_tags($question), 'userAnswer' => null ]
];
$stmt = db()->prepare("INSERT INTO sessions(id, created_at, original_input, language, turns_json) VALUES(?,?,?,?,?)");
$stmt->execute([$sid, now(), $text, $lang, json_encode($turns, JSON_UNESCAPED_UNICODE)]);

jsonOut(['ok'=>true,'sessionId'=>$sid,'summary'=>$summary,'question'=>strip_tags($question)]);


} catch (Throwable $e) {
  echo json_encode(['ok'=>false,'error'=>'start.php fatal','details'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
  exit;
}
