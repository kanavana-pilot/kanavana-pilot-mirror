<?php
// HUOM: Ei backend-muutoksia. Tämä on vain UI-sivu.
?><!doctype html>
<html lang="fi">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Common Sense Agent – UI</title>
  <!-- Perustyylit (uudelleenkäyttö teemaväreihin) -->
  <link rel="stylesheet" href="assets/css/common_sense.css">
  <!-- Tämän sivun omat tyylit -->
  <link rel="stylesheet" href="assets/css/common_sense_ui.css">
</head>
<body>
  <div class="wrap">
    <header class="ui-header">
      <div class="titles">
        <h1 data-i18n="ui.title">Common Sense Agent – UI</h1>
        <p class="subtitle" data-i18n="ui.subtitle">Luonnollinen näkymä agentin askeleisiin</p>
      </div>
      <div class="header-ctrls">
        <label class="lang-label" for="langSel" data-i18n="ui.lang_label">Kieli</label>
        <select id="langSel" aria-label="Language"></select>
        <span id="keyBadge" class="badge" aria-live="polite">...</span>
      </div>
    </header>

    <!-- Syötteet / painikkeet -->
    <section class="card controls" aria-labelledby="controlsTitle">
      <h2 id="controlsTitle" data-i18n="ui.controls">Ohjaimet</h2>
      <div class="grid-2">
        <div>
          <label for="goal" data-i18n="ui.goal_label">Tavoite</label>
          <textarea id="goal" rows="3" data-i18n-ph="ui.placeholders.goal" placeholder="Esim. Laita sekalainen astiapino kaappeihin"></textarea>
        </div>
        <div>
          <label for="context" data-i18n="ui.context_label">Konteksti (JSON)</label>
          <textarea id="context" rows="3" data-i18n-ph="ui.placeholders.context" placeholder='{"room":"kitchen","notes":["varovasti laseille"]}'></textarea>
          <small data-i18n="ui.context_hint">Vain tarvittaessa – jätä tyhjäksi jos ei ole lisäkontekstia.</small>
        </div>
      </div>

      <div class="btnrow">
        <button id="btnPlanUI" class="primary" data-i18n="ui.buttons.plan">Luo suunnitelma</button>
        <button id="btnStepUI" data-i18n="ui.buttons.step">Etene yksi askel</button>
        <button id="btnInterruptUI" class="warn" data-i18n="ui.buttons.interrupt">Keskeytä / pyydä välitavoite</button>
        <button id="btnResetUI" class="ghost" data-i18n="ui.buttons.reset">Tyhjennä näkymä</button>
      </div>
    </section>

    <!-- Korttilista agentin vastauksille -->
    <section class="cards-area">
      <h2 class="visually-hidden">Cards</h2>
      <div id="cards" class="cards">
        <div id="emptyState" class="empty" data-i18n="ui.empty_state">
          Ei vielä askeleita. Luo ensin suunnitelma tai etene askeleella.
        </div>
      </div>
    </section>

    <!-- Sessiomuistin tiivistelmä -->
    <aside class="card session-mem" aria-labelledby="sessionMemTitle">
      <h2 id="sessionMemTitle" data-i18n="ui.headings.session_memory">Session memory</h2>
      <ul id="sessionMemList" class="kv-list" aria-live="polite"></ul>
    </aside>

    <!-- Virheilmoitukset (validator yms.) menevät kortteina cards-alueelle -->
  </div>

  <script src="assets/js/common_sense_ui.js" defer></script>
</body>
</html>
