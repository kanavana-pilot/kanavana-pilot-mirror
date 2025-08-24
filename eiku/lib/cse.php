<?php
declare(strict_types=1);
require_once __DIR__ . '/util.php';

function cse_search(string $q, int $num=5, bool $images=false): array {
  $key = env('GOOGLE_CSE_KEY');
  $cx  = $images ? env('GOOGLE_CSE_CX_IMAGES') : env('GOOGLE_CSE_CX');
  if (!$key || !$cx) return [];

  $url = "https://www.googleapis.com/customsearch/v1?key="
       . urlencode($key) . "&cx=" . urlencode($cx)
       . "&q=" . urlencode($q) . "&num=" . $num
       . ($images ? "&searchType=image" : "");

  $res = @file_get_contents($url);
  if (!$res) return [];
  $json = json_decode($res, true);

  $out = [];
  foreach (($json['items'] ?? []) as $it) {
    $out[] = [
      'title'     => $it['title']   ?? '',
      'snippet'   => $it['snippet'] ?? '',
      'link'      => $it['link']    ?? '',
      'thumbnail' => $it['pagemap']['cse_thumbnail'][0]['src'] ?? ($it['image']['thumbnailLink'] ?? null),
    ];
  }
  return $out;
}
