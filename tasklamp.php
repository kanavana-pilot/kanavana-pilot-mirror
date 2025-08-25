<?php
?><!DOCTYPE html>
<html lang="fi">
<head>
  <meta charset="utf-8" />
  <meta http-equiv="x-ua-compatible" content="ie=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>TaskLamp – Tehtävien taskulamppu</title>
  <meta name="description" content="TaskLamp auttaa maahanmuuttajia hoitamaan arjen viranomaisasioita vaihe vaiheelta." />
  <link rel="stylesheet" href="/assets/css/tasklamp.css" />
</head>
<body>
  <a class="skip-link" href="#main">Siirry sisältöön</a>

  <header role="banner" class="tl-header" aria-label="Sivun yläosa">
    <div class="container">
      <h1 id="app-title" class="tl-title" data-i18n="tasklamp.ui.title">TaskLamp – Tehtävien taskulamppu</h1>
      <nav aria-label="Asetukset">
        <ul class="tl-toolbar">
          <li>
            <label for="lang" class="visually-hidden" data-i18n="tasklamp.ui.language">Kieli</label>
            <select id="lang" name="lang" aria-label="Kieli">
              <option value="fi">Suomi</option>
              <option value="en">English</option>
              <option value="sv">Svenska</option>
            </select>
          </li>
          <li>
            <button id="panic" class="btn btn-danger" type="button" data-i18n="tasklamp.ui.panic">Tyhjennä kaikki</button>
          </li>
        </ul>
      </nav>
    </div>
  </header>

  <!-- Globaali latausspinneri -->
  <div id="spinner" class="tl-spinner" role="status" aria-live="polite" hidden>
    <div class="dots" aria-hidden="true">
      <span class="dot"></span><span class="dot"></span><span class="dot"></span>
    </div>
    <p class="label" data-i18n="app.loading.label">Ladataan</p>
  </div>

  <main id="main" role="main" class="container">
    <section aria-labelledby="intro-head">
      <h2 id="intro-head" data-i18n="tasklamp.ui.introHead">Aloita ilman kynnystä</h2>
      <p id="intro-copy" data-i18n="tasklamp.ui.introCopy">
        Kerro mitä haluat hoitaa. TaskLamp pilkkoo sen selkeiksi askeliksi, näyttää viralliset linkit ja kertoo missä voit tulostaa tarvittavat paperit.
      </p>
    </section>

    <section aria-labelledby="form-head" class="tl-card tl-card--form">
      <h3 id="form-head" data-i18n="tasklamp.ui.formHead">Kuvaile tehtävä</h3>

      <form id="task-form" novalidate>
        <div class="grid">
          <div class="field">
            <label for="country" data-i18n="tasklamp.form.country">Maa</label>
            <input id="country" name="country" type="text" inputmode="text" autocomplete="country-name" placeholder="Sweden / Polska / Česká republika / Slovensko / Magyarország" aria-describedby="country-hint" required />
            <small id="country-hint" class="hint" data-i18n="tasklamp.form.countryHint">Kirjoita maa paikallisella tai englanninkielisellä nimellä.</small>
          </div>
          <div class="field">
            <label for="city" data-i18n="tasklamp.form.city">Kaupunki</label>
            <input id="city" name="city" type="text" inputmode="text" autocomplete="address-level2" placeholder="Stockholm / Warszawa / Praha / Bratislava / Budapest" aria-describedby="city-hint" />
            <small id="city-hint" class="hint" data-i18n="tasklamp.form.cityHint">Jos et tiedä, jätä tyhjäksi – pyydämme tarvittaessa.</small>
          </div>
        </div>

        <div class="field">
          <label for="task" data-i18n="tasklamp.form.task">Mitä haluat hoitaa?</label>
          <textarea id="task" name="task" rows="3" placeholder="Esim. 'Miten ilmoitan lapseni kouluun Prahassa?'" aria-describedby="task-hint" required></textarea>
          <small id="task-hint" class="hint" data-i18n="tasklamp.form.taskHint">Kirjoita luonnollisella kielellä – yksi tavoite kerrallaan.</small>
        </div>

        <div class="actions">
          <button id="btn-plan" class="btn btn-primary" type="submit" data-i18n="tasklamp.ui.createPlan">Luo askelpolku</button>
          <button id="btn-clear" class="btn btn-ghost" type="button" data-i18n="tasklamp.ui.clear">Tyhjennä kentät</button>
        </div>
      </form>
    </section>

    <section aria-labelledby="result-head" class="tl-card tl-card--result" hidden>
      <div class="result-headline">
        <h3 id="result-head" data-i18n="tasklamp.ui.resultHead">Askel kerrallaan</h3>
        <div class="result-actions">
          <button id="btn-prev" class="btn" type="button" data-i18n="tasklamp.ui.prev" aria-controls="step-view" disabled>Edellinen</button>
          <button id="btn-next" class="btn btn-primary" type="button" data-i18n="tasklamp.ui.next" aria-controls="step-view" disabled>Seuraava</button>
          <button id="btn-all" class="btn" type="button" data-i18n="tasklamp.ui.showAll">Näytä kaikki</button>
          <button id="btn-citymap" class="btn" type="button" data-i18n="tasklamp.step.map">Katso kartalla</button>
          <button id="btn-print" class="btn" type="button" data-i18n="tasklamp.ui.print">Tulosta</button>
          <button id="btn-copy" class="btn" type="button" data-i18n="tasklamp.ui.copy">Kopioi lista</button>
        </div>
      </div>

      <div id="followup" class="tl-followup" role="alert" hidden>
        <p id="followup-text"></p>
        <form id="followup-form" class="inline">
          <label for="followup-input" class="visually-hidden" data-i18n="tasklamp.ui.followupLabel">Lisätieto</label>
          <input id="followup-input" name="followup" type="text" />
          <button id="followup-send" class="btn btn-primary" type="submit" data-i18n="tasklamp.ui.send">Lähetä</button>
        </form>
      </div>

      <div id="step-view" class="tl-step" role="region" aria-live="polite" aria-atomic="true"></div>
      <details id="all-steps" class="tl-all" hidden>
        <summary data-i18n="tasklamp.ui.allSteps">Kaikki vaiheet</summary>
        <ol id="step-list"></ol>
      </details>
    </section>

    <section aria-labelledby="phrases-head" class="tl-card tl-card--phrases">
      <h3 id="phrases-head" data-i18n="tasklamp.ui.phrasesHead">Hyödyllisiä fraaseja terveydenhoitoon</h3>
      <ul id="phrases-list" class="phrases"></ul>
    </section>
  </main>

  <footer role="contentinfo" class="tl-footer">
    <div class="container">
      <p class="muted" data-i18n="tasklamp.ui.privacy">
        Ei pysyvää tallennusta. Voit tyhjentää kaiken koska tahansa “Tyhjennä kaikki” -painikkeella.
      </p>
    </div>
  </footer>

  <noscript>
    <div class="noscript">JavaScript vaaditaan tämän palvelun käyttämiseen.</div>
  </noscript>

  <script src="/assets/js/tasklamp.js" defer></script>
</body>
</html>
