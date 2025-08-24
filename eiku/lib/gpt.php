<?php
declare(strict_types=1);
require_once __DIR__ . '/util.php';

function gpt_chat(array $messages, string $model='gpt-4o-mini', float $temp=0.3): array {
  $key = env('OPENAI_API_KEY');
  if (!$key) return ['error'=>'OPENAI_API_KEY puuttuu'];
  $payload = [
    'model' => $model,
    'temperature' => $temp,
    'messages' => $messages
  ];
  $ch = curl_init('https://api.openai.com/v1/chat/completions');
  curl_setopt_array($ch, [
    CURLOPT_POST=>true,
    CURLOPT_HTTPHEADER=>[
      'Content-Type: application/json',
      "Authorization: Bearer $key"
    ],
    CURLOPT_POSTFIELDS=>json_encode($payload),
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_TIMEOUT=>30
  ]);
  $res = curl_exec($ch);
  if ($res === false) return ['error'=>curl_error($ch)];
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  $data = json_decode($res, true);
  if ($code>=400) return ['error'=>$data['error']['message'] ?? 'OpenAI error'];
  return $data;
}

function prompt_kirkastaja_system(): string {
  return "Olet 'Prompt-kirkastaja'. Tehtäväsi:
- Tiivistä käyttäjän ajatus yhdeksi selkeäksi tulkinnaksi (2–4 lausetta).
- Kysy KORKEINTAAN 1–2 ytimekästä jatkokysymystä per kierros.
- Ylläpidä 'statea': yhdistä uudet vastaukset tiivistelmään.
- Älä vielä vastaa tehtävään. Muodosta lopuksi vain yksi selkeä, täsmällinen prompti.
- Kun saat tone/length/audience/formats, sisällytä ne eksplisiittisinä ohjeina promptin alkuun.";
}
