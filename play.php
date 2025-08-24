<?php
// play.php — Tekstiteatteri-demo (i18n-kohdennuksilla)
?><!doctype html>
<html lang="fi">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Tekstiteatteri – Play</title>
  <link rel="icon" href="/assets/favicon.ico">
  <link rel="stylesheet" href="/assets/css/play.css">
</head>
<body
  data-answer-endpoint="/search/answer-play.php"
  data-rewrite-endpoint="/search/rewrite-play.php"
>
  <header class="site-header">
    <h1>
      <span class="app-title" data-i18n="app.title">Tekstiteatteri</span>
      <span class="beta">PLAY</span>
    </h1>
    <p class="lead" data-i18n="app.subtitle">Kirjoita, valitse tyyli ja katso kun näyttämö vaihtuu lennossa.</p>
  </header>

  <main class="container">
    <section class="stage">
      <div class="editor">
        <label for="source" class="label" data-i18n="editor.label">Teksti (≤ 1000 merkkiä suositus)</label>
        <textarea id="source" rows="7" placeholder="Liitä tai kirjoita teksti tähän…" data-i18n-placeholder="editor.placeholder"></textarea>
        <div class="row between">
          <div class="muted">
            <span id="counter">0</span>
            <span id="counterSuffix" data-i18n="editor.counterSuffix">merkkiä</span>
          </div>
          <div class="row gap">
            <button id="btnClear" class="ghost" data-i18n="actions.clear">Tyhjennä</button>
            <button id="btnPaste" class="ghost" data-i18n="actions.paste">Liitä leikepöydältä</button>
          </div>
        </div>
      </div>

      <div class="controls">
        <div class="grid">
          <div>
            <label class="label" for="lang" data-i18n="controls.language">Kieli</label>
            <select id="lang">
              <option value="fi">Suomi</option>
              <option value="en">English</option>
              <option value="sv">Svenska</option>
              <option value="de">Deutsch</option>
              <option value="fr">Français</option>
              <option value="es">Español</option>
              <option value="it">Italiano</option>
              <option value="et">Eesti</option>
              <option value="ru">Русский</option>
              <option value="uk">Українська</option>
              <option value="ar">العربية</option>
              <option value="fa">فارسی</option>
              <option value="so">Af-Soomaali</option>
            </select>
          </div>

          <div>
            <label class="label" for="style" data-i18n="controls.style">Tyyli</label>
            <select id="style" data-i18n-options="dropdowns.styles">
              <option>Humoristinen</option>
              <option>Tarinallinen</option>
              <option>Analyyttinen</option>
              <option>Ystävällinen</option>
              <option>Napakka</option>
              <option>Itsevarma</option>
              <option>Some-tyyli</option>
              <option>Lastenkirja</option>
              <option>Runollinen</option>
              <option>Selkokieli</option>
              <option>Radiourheilija</option>
              <option>Juridiikka</option>
            </select>
          </div>

          <div>
            <label class="label" for="tone" data-i18n="controls.tone">Mieliala</label>
            <select id="tone" data-i18n-options="dropdowns.tones">
              <option>(neutraali)</option>
              <option>pirteä</option>
              <option>asiallinen</option>
              <option>sarkastinen</option>
              <option>leikkisä</option>
              <option>empaattinen</option>
              <option>itsevarma</option>
            </select>
          </div>

          <div>
            <label class="label" for="audience" data-i18n="controls.audience">Kenelle</label>
            <select id="audience" data-i18n-options="dropdowns.audiences">
              <option>yleisö</option>
              <option>rekrytoija</option>
              <option>työnantaja</option>
              <option>ystävä</option>
              <option>lapsi</option>
              <option>tiedelehti</option>
              <option>asiakas</option>
              <option>hallitus</option>
              <option>some-yleisö</option>
            </select>
          </div>

          <div>
            <label class="label" for="length" data-i18n="controls.length">Pituus</label>
            <select id="length" data-i18n-options="dropdowns.lengths">
              <option>Keskitaso</option>
              <option>Lyhyt</option>
              <option>Pitkä</option>
            </select>
          </div>
        </div>

        <div class="row wrap gap actions">
          <button id="btnGenerate" class="primary" data-i18n="actions.generate">Kirjoita puolestani</button>
          <button id="btnRewrite"  class="secondary" data-i18n="actions.rewrite">Muokkaa tyylillä</button>
          <button id="btnShuffle"  class="ghost" data-i18n="actions.shuffle">Impro (Shuffle)</button>
          <button id="btnDuel"     class="ghost" data-i18n="actions.duel">Kaksintaistelu (Duel)</button>
        </div>

        <p class="hint">
          <span data-i18n="hint">Vinkki: Kirjoita pari lausetta ja kokeile eri tyylejä – näyttämö vaihtuu lennossa.</span>
        </p>
      </div>
    </section>

    <section class="results">
      <div class="tabs">
        <button class="tab active" data-tab="answer" data-i18n="tabs.result">Tulos</button>
        <button class="tab" data-tab="plain" data-i18n="tabs.plain">Yksinkertaistettu</button>
        <button class="tab" data-tab="ideas" data-i18n="tabs.ideas">Jatkoideat</button>
      </div>
      <div class="panel" id="answer"></div>
      <div class="panel hidden" id="plain"></div>
      <div class="panel hidden" id="ideas"></div>
    </section>

    <section class="recents">
      <h2 data-i18n="recents.title">Viimeisimmät otokset</h2>
      <div id="cards" class="cards"></div>
    </section>
  </main>

  <footer class="site-footer">
    <div class="row between">
      <span>© Kanavana – Tekstiteatteri (Play)</span>
      <span class="muted">Rakennettu prompteilla, ei rikota vanhaa koodia.</span>
    </div>
  </footer>

  <!-- Overlay-loader (teksti vaihdetaan i18n:llä) -->
  <div id="spinner" class="spinner" aria-live="polite" aria-busy="false" aria-hidden="true">
    <div class="ring" role="status" aria-label="Ladataan…"></div>
    <div class="label" data-i18n="spinner.loading">Ladataan…</div>
  </div>

  <script src="/assets/js/play.js" defer></script>
</body>
</html>
