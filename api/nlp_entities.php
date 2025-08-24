<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://pilot.kanavana.fi');
header('Access-Control-Allow-Methods: POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

$text = trim($_POST['text'] ?? '');
if ($text === '') { http_response_code(400); echo json_encode(['error'=>'text required']); exit; }

$apiKey = getenv('GOOGLE_NLP_API_KEY') ?: ($_SERVER['GOOGLE_NLP_API_KEY'] ?? $_ENV['GOOGLE_NLP_API_KEY'] ?? null);
if (!$apiKey) { http_response_code(500); echo json_encode(['error'=>'API key missing']); exit; }

$payload = [
  'document' => ['type' => 'PLAIN_TEXT', 'content' => $text],
  'encodingType' => 'UTF8'
];

$ch = curl_init('https://language.googleapis.com/v1/documents:analyzeEntities?key='.rawurlencode($apiKey));
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
  CURLOPT_POST => true,
  CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
  CURLOPT_TIMEOUT => 20
]);
$res  = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 200;
if ($res === false) { http_response_code(500); echo json_encode(['error'=>'curl: '.curl_error($ch)]); }
else { http_response_code($code); echo $res; }
curl_close($ch);
