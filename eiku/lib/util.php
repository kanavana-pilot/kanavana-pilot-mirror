<?php
declare(strict_types=1);

function env(string $key, ?string $default=null): ?string {
  if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
    return $_SERVER[$key];
  }
  $val = getenv($key);
  if ($val !== false && $val !== '') return $val;

  static $loaded=false;
  if(!$loaded && file_exists(__DIR__ . '/../.env')){
    foreach (file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES) as $line){
      $line = trim($line);
      if ($line==='' || $line[0]==='#') continue;
      $parts = explode('=', $line, 2);
      if (count($parts)===2){
        [$k,$v] = array_map('trim', $parts);
        $_ENV[$k] = $v;
        $_SERVER[$k] = $v;
      }
    }
    $loaded = true;
  }
  if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') return $_SERVER[$key];
  return $default;
}

function db(): PDO {
  static $pdo=null;
  if ($pdo) return $pdo;

  $dir = __DIR__ . '/../data';
  if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
  if (!is_writable($dir)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>false,'error'=>'Data directory not writable: /data'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $path = $dir . '/eiku.sqlite';
  try {
    $pdo = new PDO('sqlite:' . $path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE TABLE IF NOT EXISTS sessions (
      id TEXT PRIMARY KEY,
      created_at INTEGER,
      original_input TEXT,
      language TEXT,
      turns_json TEXT,
      final_prompt TEXT,
      executed_answer TEXT,
      share_slug TEXT
    )");
  } catch (Throwable $e) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>false,'error'=>'SQLite init failed','details'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
  }
  return $pdo;
}


function jsonOut($arr){ header('Content-Type: application/json; charset=utf-8'); echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }
function uuid(): string { return bin2hex(random_bytes(16)); }
function now(): int { return time(); }
