<?php
require_once __DIR__ . '/lib/util.php';

$slug = $_GET['i'] ?? '';
$view = $_GET['view'] ?? 'prompt'; // 'prompt' | 'answer'
if ($slug===''){ http_response_code(400); echo 'Bad request'; exit; }

$pdo = db();
$stmt = $pdo->prepare("SELECT final_prompt, executed_answer FROM sessions WHERE share_slug=?");
$stmt->execute([$slug]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row){ http_response_code(404); echo 'Not found'; exit; }

$final = $row['final_prompt'] ?? '';
$answer = $row['executed_answer'] ?? '';
?>
<!doctype html>
<html lang="fi"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Jaettu – Eiku Prompt</title>
<link rel="stylesheet" href="public/app.css">
<style>
  body{background:#0f1115;color:#eaeaea}
  .wrap{max-width:980px;margin:40px auto;padding:20px}
  pre{white-space:pre-wrap}
  a{color:#8fb1ff}
  .tabs{display:flex;gap:8px;margin:10px 0 16px}
  .tab{padding:8px 12px;border-radius:10px;background:#1a1f2a;cursor:pointer;text-decoration:none;color:#eaeaea}
  .tab.active{background:#5b8cff}
  .copy{margin:8px 0 16px}
  button.copybtn{padding:8px 12px;border:0;border-radius:8px;background:#2a3350;color:#fff;cursor:pointer}
  .hint{opacity:.85;margin:6px 0 16px}
  .empty{opacity:.8}
</style>
</head><body>
  <div class="wrap">
    <h1>Jaettu sisältö</h1>
    <p class="hint">Voit kopioida lopullisen promptin tai valmiin vastauksen.</p>

    <div class="tabs">
      <a class="tab <?= $view==='prompt'?'active':'' ?>" href="?i=<?= urlencode($slug) ?>&view=prompt">Prompti</a>
      <a class="tab <?= $view==='answer'?'active':'' ?>" href="?i=<?= urlencode($slug) ?>&view=answer">Vastaus</a>
    </div>

    <?php if ($view==='answer'): ?>
      <?php if (trim($answer)!==''): ?>
        <div class="copy"><button class="copybtn" data-target="ans">Kopioi vastaus</button></div>
        <pre id="ans"><?= htmlspecialchars($answer, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8') ?></pre>
      <?php else: ?>
        <p class="empty">Tähän sessioon ei ole vielä ajettu vastausta.</p>
        <p><a href="./">← Palaa Eiku Prompt -alkuun ajamaan vastaus</a></p>
      <?php endif; ?>
    <?php else: ?>
      <div class="copy"><button class="copybtn" data-target="prm">Kopioi prompti</button></div>
      <pre id="prm"><?= htmlspecialchars($final, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8') ?></pre>
    <?php endif; ?>

    <p style="margin-top:20px"><a href="./">← Takaisin Eiku Prompt -alkuun</a></p>
  </div>
  <script>
    document.querySelectorAll('.copybtn').forEach(btn=>{
      btn.addEventListener('click', async ()=>{
        const id = btn.getAttribute('data-target');
        const txt = document.getElementById(id).textContent;
        try { await navigator.clipboard.writeText(txt); btn.textContent='Kopioitu!'; setTimeout(()=>btn.textContent='Kopioi',1200); } catch(e){}
      });
    });
  </script>
</body></html>
