<?php
require_once __DIR__ . '/../lib/util.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$writable = is_writable(__DIR__ . '/../data');

$out = [
  'ok' => true,
  'env_present' => [
    'OPENAI_API_KEY' => (bool)env('OPENAI_API_KEY'),
    'GOOGLE_CSE_KEY' => (bool)env('GOOGLE_CSE_KEY'),
    'GOOGLE_CSE_CX' => (bool)env('GOOGLE_CSE_CX'),
    'GOOGLE_CSE_CX_IMAGES' => (bool)env('GOOGLE_CSE_CX_IMAGES'),
    'GOOGLE_NLP_API_KEY' => (bool)env('GOOGLE_NLP_API_KEY'),
    'GOOGLE_TRANSLATE_API_KEY' => (bool)env('GOOGLE_TRANSLATE_API_KEY'),
  ],
  'extensions' => [
    'curl' => extension_loaded('curl'),
    'pdo_sqlite' => extension_loaded('pdo_sqlite'),
  ],
  'paths' => [
    'data_writable' => $writable,
  ],
];
echo json_encode($out, JSON_UNESCAPED_UNICODE);
exit;
