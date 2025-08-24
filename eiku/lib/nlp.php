<?php
declare(strict_types=1);
require_once __DIR__ . '/util.php';

function sentiment_hint(string $text): ?string {
  $key = env('GOOGLE_NLP_API_KEY');
  if (!$key) return null;

  $payload = [
    'document' => ['type' => 'PLAIN_TEXT', 'content' => $text],
    'encodingType' => 'UTF8'
  ];
  $ctx = stream_context_create([
    'http' => [
      'method'  => 'POST',
      'header'  => "Content-Type: application/json\r\n",
      'content' => json_encode($payload)
    ]
  ]);
  $url = "https://language.googleapis.com/v2/documents:analyzeSentiment?key=" . urlencode($key);
  $res = @file_get_contents($url, false, $ctx);
  if (!$res) return null;

  $data = json_decode($res, true);
  $score = $data['documentSentiment']['score'] ?? null; // -1..1
  if ($score === null) return null;
  return $score < -0.4 ? 'harsh' : ($score > 0.4 ? 'positive' : 'neutral');
}
