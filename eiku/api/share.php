<?php
$mode = $_POST['mode'] ?? 'prompt'; // 'prompt' | 'answer'
require_once __DIR__ . '/../lib/util.php';

$sessionId = $_POST['sessionId'] ?? '';
$pdo = db();
$row = $pdo->query("SELECT * FROM sessions WHERE id=".$pdo->quote($sessionId))->fetch(PDO::FETCH_ASSOC);
if (!$row) jsonOut(['ok'=>false,'error'=>'Sessio puuttuu']);
if (empty($row['final_prompt'])) jsonOut(['ok'=>false,'error'=>'Ei jaettavaa']);

$slug = $row['share_slug'] ?: substr(hash('sha256',$sessionId),0,10);
$pdo->prepare("UPDATE sessions SET share_slug=? WHERE id=?")->execute([$slug,$sessionId]);

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/api');
$view = ($mode === 'answer') ? '&view=answer' : '&view=prompt';
$url = $scheme . '://' . $host . $basePath . '/share.php?i=' . $slug . $view;
jsonOut(['ok'=>true,'url'=>$url]);
