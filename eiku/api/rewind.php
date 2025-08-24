<?php
require_once __DIR__ . '/../lib/util.php';

$sessionId = $_POST['sessionId'] ?? '';
$steps = max(1, (int)($_POST['steps'] ?? 1));

if (!$sessionId) jsonOut(['ok'=>false,'error'=>'Sessio puuttuu']);

$pdo = db();
$row = $pdo->query("SELECT * FROM sessions WHERE id=".$pdo->quote($sessionId))->fetch(PDO::FETCH_ASSOC);
if (!$row) jsonOut(['ok'=>false,'error'=>'Sessioa ei löytynyt']);

$turns = json_decode($row['turns_json'] ?? '[]', true);
if (!is_array($turns) || empty($turns)) jsonOut(['ok'=>false,'error'=>'Sessio on virheellinen']);

// Jos on vain 1 vaihe tallessa → palataan alkuun (aivopuuro)
if (count($turns) <= 1){
  jsonOut(['ok'=>true, 'toStart'=>true, 'original'=>$row['original_input']]);
}

// Poista niin monta "seuraava kysymys" -vaihetta kuin pyydetty (mutta jätä vähintään 1)
for ($i=0; $i<$steps; $i++){
  if (count($turns) <= 1) break; // ei voi mennä alemmas tässä
  array_pop($turns); // poista viimeisin vaihe (kysymys, johon ei ole vielä vastattu)
}

// Nyt nykyinen vaihe on se, johon käyttäjä vastasi edellisellä kierroksella
$idx = count($turns) - 1;
$prefill = $turns[$idx]['userAnswer'] ?? '';
$turns[$idx]['userAnswer'] = null; // tyhjennetään, jotta käyttäjä voi muokata vastausta

$pdo->prepare("UPDATE sessions SET turns_json=? WHERE id=?")
    ->execute([json_encode($turns, JSON_UNESCAPED_UNICODE), $sessionId]);

jsonOut([
  'ok'=>true,
  'toStart'=>false,
  'step'=> max(1, $idx+1),         // 1 → kysymys #1, 2 → kysymys #2, jne.
  'summary'=> $turns[$idx]['summary'] ?? '',
  'question'=> $turns[$idx]['question'] ?? '',
  'prefill'=> $prefill
]);
