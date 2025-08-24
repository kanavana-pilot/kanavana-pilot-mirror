<?php
declare(strict_types=1);
require_once __DIR__ . '/util.php';

function translate_to_en(string $text): array {
  $tkey = env('GOOGLE_TRANSLATE_API_KEY'); // valinnainen
  if (!$tkey) {
    $isEn = preg_match('/\b(the|and|for|with|you|we|project|prompt)\b/i', $text) === 1;
    return ['text' => $text, 'lang' => $isEn ? 'en' : 'fi'];
  }

  $url  = "https://translation.googleapis.com/language/translate/v2";
  $data = ['q'=>$text, 'target'=>'en', 'format'=>'text', 'key'=>$tkey];
  $opts = ['http'=>[
    'method'=>'POST',
    'header'=>"Content-Type: application/x-www-form-urlencoded\r\n",
    'content'=>http_build_query($data)
  ]];
  $res = @file_get_contents($url, false, stream_context_create($opts));
  if ($res === false) return ['text'=>$text,'lang'=>'fi'];
  $json = json_decode($res, true);
  $t   = $json['data']['translations'][0]['translatedText'] ?? $text;
  $src = $json['data']['translations'][0]['detectedSourceLanguage'] ?? 'fi';
  return ['text'=>html_entity_decode($t), 'lang'=>$src];
}
