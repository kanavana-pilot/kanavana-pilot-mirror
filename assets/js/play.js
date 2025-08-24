/* assets/js/play.js – Tekstiteatteri frontti (vanilla JS) */

(function () {
  const $ = (sel) => document.querySelector(sel);
  const $$ = (sel) => Array.from(document.querySelectorAll(sel));

  // --- Endpointit: body[data-*] -> window.PLAY_ENDPOINTS -> oletus ---
  const body = document.body || document.getElementsByTagName('body')[0];
  const ep = {
    answer:
      (body && body.dataset && body.dataset.answerEndpoint) ? body.dataset.answerEndpoint :
      (window.PLAY_ENDPOINTS && window.PLAY_ENDPOINTS.answer) ? window.PLAY_ENDPOINTS.answer :
      '/answer-play.php',
    rewrite:
      (body && body.dataset && body.dataset.rewriteEndpoint) ? body.dataset.rewriteEndpoint :
      (window.PLAY_ENDPOINTS && window.PLAY_ENDPOINTS.rewrite) ? window.PLAY_ENDPOINTS.rewrite :
      '/rewrite-play.php',
  };

  const el = {
    source: $('#source'),
    counter: $('#counter'),
    lang: $('#lang'),
    style: $('#style'),
    tone: $('#tone'),
    audience: $('#audience'),
    length: $('#length'),
    btnGenerate: $('#btnGenerate'),
    btnRewrite: $('#btnRewrite'),
    btnShuffle: $('#btnShuffle'),
    btnDuel: $('#btnDuel'),
    btnClear: $('#btnClear'),
    btnPaste: $('#btnPaste'),
    tabs: $$('.tab'),
    panelAnswer: $('#answer'),
    panelPlain: $('#plain'),
    panelIdeas: $('#ideas'),
    cards: $('#cards'),
    spinner: $('#spinner') // overlay‑spinner
  };

  // ---- i18n: lataus, adapteri ja soveltaminen ----

  // apuri: hae objektin arvot listana
  const objVals = (o) => o ? Object.values(o) : [];

  // Muunna "toisen palvelun" skeema Playn sisäiseen muotoon
  function normalizeDict(src, langCode) {
    const F = (v, fb) => (v == null || v === '') ? fb : v;

    const styles  = objVals(src.style);
    const tones   = objVals(src.tone);
    const lengths = objVals(src.length);

    return {
      langName: langCode || 'fi',
      dir: src.dir === 'rtl' ? 'rtl' : 'ltr',
      app: {
        title: F(src.app?.title, 'Tekstiteatteri'),
        // käytetään intro-vihjettä subtitleen jos annettu, muuten fallback
        subtitle: F(src.app?.hints?.step1, 'Kirjoita, valitse tyyli ja katso kun näyttämö vaihtuu lennossa.')
      },
      editor: {
        label: 'Teksti (≤ 1000 merkkiä suositus)',
        placeholder: 'Liitä tai kirjoita teksti tähän…',
        counterSuffix: 'merkkiä'
      },
      controls: {
        language: F(src.app?.langLabel, 'Kieli'),
        style: F(src.ui?.style, 'Kirjoitustyyli'),
        tone: F(src.ui?.tone, 'Sävy'),
        audience: 'Kenelle',
        length: F(src.ui?.length, 'Pituus')
      },
      actions: {
        generate: F(src.app?.buttons?.createDraft, 'Kirjoita puolestani'),
        rewrite: F(src.ui?.iterate || src.ui?.refine, 'Muokkaa tyylillä'),
        shuffle: 'Impro (Shuffle)',
        duel: 'Kaksintaistelu (Duel)',
        clear: F(src.app?.reset?.draft, 'Tyhjennä'),
        paste: 'Liitä leikepöydältä'
      },
      tabs: {
        result: 'Tulos',
        plain: F(src.ui?.plain, 'Yksinkertaistettu'),
        ideas: 'Jatkoideat'
      },
      hint: F(src.app?.hints?.intro, 'Vinkki: Kirjoita pari lausetta ja kokeile eri tyylejä – näyttämö vaihtuu lennossa.'),
      recents: {
        title: 'Viimeisimmät otokset',
        restore: 'Palauta',
        none: '(ei ehdotuksia)'
      },
      spinner: { loading: F(src.app?.loading?.label, 'Ladataan…') },
      alerts: {
        empty: 'Kirjoita jotain ensin.',
        answerError: 'Virhe vastauksen luonnissa:',
        rewriteError: 'Virhe muokkauksessa:',
        duelError: 'Duel epäonnistui:',
        pasteError: 'Liittäminen epäonnistui. Salli leikepöydän käyttö selaimessa.'
      },
      dropdowns: {
        styles: styles.length ? styles : ["Humoristinen","Tarinallinen","Analyyttinen","Ystävällinen","Napakka","Itsevarma","Some-tyyli","Lastenkirja","Runollinen","Selkokieli","Radiourheilija","Juridiikka"],
        // lisätään neutraali ensimmäiseksi jos ei löydy
        tones:  (tones && tones.length) ? (tones.includes('(neutraali)') ? tones : ['(neutraali)', ...tones]) : ["(neutraali)","pirteä","asiallinen","sarkastinen","leikkisä","empaattinen","itsevarma"],
        audiences: ["yleisö","rekrytoija","työnantaja","ystävä","lapsi","tiedelehti","asiakas","hallitus","some-yleisö"],
        lengths: lengths.length ? lengths : ["Lyhyt","Keskitaso","Pitkä"]
      }
    };
  }

  function applyI18n(dict) {
    // data-i18n: sisällöt
    document.querySelectorAll('[data-i18n]').forEach(el => {
      const key = el.getAttribute('data-i18n');
      const val = key.split('.').reduce((o,k)=>o&&o[k], dict);
      if (typeof val === 'string') el.textContent = val;
    });
    // data-i18n-placeholder: placeholder-attribuutit
    document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
      const key = el.getAttribute('data-i18n-placeholder');
      const val = key.split('.').reduce((o,k)=>o&&o[k], dict);
      if (typeof val === 'string') el.setAttribute('placeholder', val);
    });
    // Dropdownit listasta
    const fill = (sel, list) => {
      const el = document.querySelector(sel);
      if (!el || !Array.isArray(list) || !list.length) return;
      const cur = el.value;
      el.innerHTML = list.map(v => `<option>${v}</option>`).join('');
      if (list.includes(cur)) el.value = cur;
    };
    fill('#style',    dict.dropdowns?.styles || []);
    fill('#tone',     dict.dropdowns?.tones || []);
    fill('#audience', dict.dropdowns?.audiences || []);
    fill('#length',   dict.dropdowns?.lengths || []);

    // RTL/LTR
    document.documentElement.dir = dict.dir || 'ltr';

    // Päivitä spinner‑label varmuudeksi
    const sp = $('#spinner');
    if (sp) {
      const l = sp.querySelector('.label');
      if (l) l.textContent = dict.spinner?.loading || 'Ladataan…';
    }

    // Talleta globaaliin alertteja varten
    window.PLAY_I18N = dict;
  }

  async function loadI18n(langCode) {
    const url = `/assets/i18n/${langCode}.json`;
    const res = await fetch(url);
    if (!res.ok) throw new Error(`i18n ${langCode} HTTP ${res.status}`);
    const raw = await res.json();
    const dict = normalizeDict(raw, langCode);
    applyI18n(dict);
  }

  // ---- Char counter + soft limit UI (1000 suositus) ----
  function updateCounter() {
    const n = (el.source.value || '').length;
    el.counter.textContent = n;
    const suff = $('#counterSuffix');
    // i18n counterSuffix on jo päivitetty applyI18n:ssä
    el.counter.parentElement.classList.toggle('warn', n > 1000);
  }
  el.source.addEventListener('input', updateCounter);
  updateCounter();

  // ---- Tabs ----
  el.tabs.forEach((t) =>
    t.addEventListener('click', () => {
      el.tabs.forEach((x) => x.classList.remove('active'));
      t.classList.add('active');
      const tab = t.dataset.tab;
      $$('.panel').forEach((p) => p.classList.add('hidden'));
      $('#'+tab).classList.remove('hidden');
    })
  );

  // ---- Helpers ----
  function getOptions(sel) {
    const node = $(sel);
    if (!node) return [];
    return Array.from(node.options).map(o => o.value || o.textContent || '');
  }
  function pick(arr) { return arr[Math.floor(Math.random()*arr.length)]; }

  // Shuffle käyttää aina TÄMÄNHETKISIÄ valikon arvoja (i18n jälkeen)
  function shuffleOne(listSel) {
    const items = getOptions(listSel).filter(Boolean);
    return items.length ? pick(items) : '';
  }

  // Näytä/piilota spinner + nappien "loading"
  function setBusy(b) {
    [el.btnGenerate, el.btnRewrite, el.btnShuffle, el.btnDuel].forEach(btn => {
      if (!btn) return;
      btn.disabled = b;
      btn.classList.toggle('loading', b);
      btn.setAttribute('aria-busy', String(b));
    });
    if (el.spinner) {
      el.spinner.classList.toggle('show', b);
      el.spinner.setAttribute('aria-busy', String(b));
      el.spinner.setAttribute('aria-hidden', String(!b));
    }
  }

  function escapeHtml(s='') {
    return s.replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
  }

  // i18n‑alert helper
  function t(path, fallback) {
    const dict = window.PLAY_I18N || {};
    const val = path.split('.').reduce((o,k)=>o&&o[k], dict);
    return (typeof val === 'string') ? val : fallback;
  }
  function i18nAlert(path, fallback) {
    alert(t(path, fallback));
  }

  // Yleinen pyyntöhelperi: selkeät virheilmoitukset (404/HTML/parse)
  async function postJson(url, payload) {
    const res = await fetch(url, {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify(payload)
    });
    const ct = res.headers.get('content-type') || '';
    const raw = await res.text(); // luetaan aina ensin tekstinä
    if (!res.ok) {
      throw new Error(`HTTP ${res.status} @ ${url}\n${raw.slice(0,300)}`);
    }
    if (!ct.toLowerCase().includes('application/json')) {
      throw new Error(`Ei JSON-vastaus @ ${url}\n${raw.slice(0,300)}`);
    }
    try {
      return JSON.parse(raw);
    } catch (e) {
      throw new Error(`JSON parse error @ ${url}\n${raw.slice(0,300)}`);
    }
  }

  function metaSnapshot() {
    return {
      lang: el.lang.value,
      style: el.style.value,
      tone: el.tone.value,
      audience: el.audience.value,
      length: el.length.value
    };
  }

  function showResult({answer_html, plain_html, followups}) {
    el.panelAnswer.innerHTML = answer_html || '<p>(tyhjä)</p>';
    el.panelPlain.innerHTML  = plain_html  || '<p>(tyhjä)</p>';
    const ideas = (followups || []).map(x=>`<button class="chip">${escapeHtml(x)}</button>`).join('');
    el.panelIdeas.innerHTML  = ideas || `<p class="muted">${escapeHtml(t('recents.none','(ei ehdotuksia)'))}</p>`;
    addRecentCard(answer_html || '', metaSnapshot());
  }

  // ---- Recent cards (3 viimeisintä, dynaamiset) ----
  const recent = [];
  function addRecentCard(html, meta) {
    if (!html) return;
    recent.unshift({ html, meta });
    if (recent.length > 3) recent.pop();
    renderCards();
  }
  function renderCards() {
    el.cards.innerHTML = recent.map((r, i) => {
      const text = (r.html || '').replace(/<[^>]+>/g,' ');
      const preview = text.length > 140 ? text.slice(0, 140).trim() + '…' : text;
      const tag = [r.meta.style, r.meta.tone, r.meta.audience].filter(Boolean).join(' · ');
      return `
        <article class="card">
          <div class="card-meta">${escapeHtml(tag)}</div>
          <div class="card-text">${escapeHtml(preview)}</div>
          <div class="card-actions">
            <button data-i="${i}" class="btn-restore">${escapeHtml(t('recents.restore','Palauta'))}</button>
          </div>
        </article>`;
    }).join('');
    $$('.btn-restore').forEach(btn => {
      btn.addEventListener('click', () => {
        const i = +btn.dataset.i;
        const html = recent[i]?.html || '';
        // palautetaan editoriin raakatekstinä poistamalla tagit
        el.source.value = (html || '').replace(/<[^>]+>/g,' ').replace(/\s+/g,' ').trim();
        updateCounter();
        window.scrollTo({top: 0, behavior: 'smooth'});
      });
    });
  }

  // ---- Actions ----
  async function callAnswer() {
    const src = el.source.value.trim();
    if (!src) return i18nAlert('alerts.empty', 'Kirjoita jotain ensin.');
    setBusy(true);
    try {
      const json = await postJson(ep.answer, {
        lang: el.lang.value,
        style_name: el.style.value,
        tone: el.tone.value,
        audience: el.audience.value,
        source: src
      });
      showResult(json);
    } catch (e) {
      console.error(e);
      i18nAlert('alerts.answerError', 'Virhe vastauksen luonnissa:'); // prefix
    } finally {
      setBusy(false);
    }
  }

  async function callRewrite() {
    const src = el.source.value.trim();
    if (!src) return i18nAlert('alerts.empty', 'Kirjoita jotain ensin.');
    setBusy(true);
    try {
      const json = await postJson(ep.rewrite, {
        lang: el.lang.value,
        style_name: el.style.value,
        style_description: '', // voi halutessa syöttää tarkenteen
        tone: el.tone.value,
        audience: el.audience.value,
        length_hint: el.length.value,
        source_html: `<p>${escapeHtml(src)}</p>`
      });
      showResult(json);
    } catch (e) {
      console.error(e);
      i18nAlert('alerts.rewriteError', 'Virhe muokkauksessa:');
    } finally {
      setBusy(false);
    }
  }

  async function doShuffle() {
    // Käytä nykyisiä valikkovaihtoehtoja i18n:n jälkeen
    const newStyle = shuffleOne('#style');   if (newStyle) el.style.value = newStyle;
    const newTone  = shuffleOne('#tone');    if (newTone)  el.tone.value  = newTone;
    const newAud   = shuffleOne('#audience');if (newAud)   el.audience.value = newAud;
    await callRewrite();
  }

  async function doDuel() {
    const src = el.source.value.trim();
    if (!src) return i18nAlert('alerts.empty', 'Kirjoita jotain ensin.');
    setBusy(true);

    // Valitse kaksi erilaista tyyliä nykyisestä listasta
    const styles = getOptions('#style').filter(Boolean);
    let styleA = styles.length ? pick(styles) : 'Humoristinen';
    let styleB = styles.length ? pick(styles) : 'Analyyttinen';
    if (styleB === styleA && styles.length > 1) {
      while (styleB === styleA) styleB = pick(styles);
    }

    try {
      const basePayload = {
        lang: el.lang.value,
        style_description: '',
        tone: el.tone.value,
        audience: el.audience.value,
        length_hint: el.length.value,
        source_html: `<p>${escapeHtml(src)}</p>`
      };

      const [rA, rB] = await Promise.all([
        postJson(ep.rewrite, {...basePayload, style_name: styleA}),
        postJson(ep.rewrite, {...basePayload, style_name: styleB})
      ]);

      // Näytä rinnakkain kevyt valinta
      const modal = document.createElement('div');
      modal.className = 'duel';
      modal.innerHTML = `
        <div class="duel-wrap">
          <div class="duel-col">
            <div class="duel-head">${escapeHtml(styleA)}</div>
            <div class="duel-body">${rA.answer_html || ''}</div>
            <button class="primary pickA">${escapeHtml(t('actions.rewrite','Valitse'))} ${escapeHtml(styleA)}</button>
          </div>
          <div class="duel-col">
            <div class="duel-head">${escapeHtml(styleB)}</div>
            <div class="duel-body">${rB.answer_html || ''}</div>
            <button class="primary outline pickB">${escapeHtml(t('actions.rewrite','Valitse'))} ${escapeHtml(styleB)}</button>
          </div>
          <button class="ghost close">Sulje</button>
        </div>
      `;
      document.body.appendChild(modal);

      modal.querySelector('.pickA').onclick = () => { showResult(rA); modal.remove(); };
      modal.querySelector('.pickB').onclick = () => { showResult(rB); modal.remove(); };
      modal.querySelector('.close').onclick  = () =>  modal.remove();
    } catch (e) {
      console.error(e);
      i18nAlert('alerts.duelError', 'Duel epäonnistui:');
    } finally {
      setBusy(false);
    }
  }

  // ---- Buttons ----
  el.btnGenerate.addEventListener('click', callAnswer);
  el.btnRewrite.addEventListener('click', callRewrite);
  el.btnShuffle.addEventListener('click', doShuffle);
  el.btnDuel.addEventListener('click', doDuel);

  el.btnClear.addEventListener('click', () => {
    el.source.value = '';
    updateCounter();
    el.panelAnswer.innerHTML = '';
    el.panelPlain.innerHTML = '';
    el.panelIdeas.innerHTML = '';
  });

  el.btnPaste.addEventListener('click', async () => {
    try {
      const text = await navigator.clipboard.readText();
      if (text) {
        el.source.value = text;
        updateCounter();
      }
    } catch (e) {
      i18nAlert('alerts.pasteError', 'Liittäminen epäonnistui. Salli leikepöydän käyttö selaimessa.');
    }
  });

  // ---- i18n käyntiin: vaihda kieltä lennossa ----
  if (el.lang) {
    el.lang.addEventListener('change', (e) => {
      loadI18n(e.target.value).catch(console.error);
    });
    // Alku: lataa valittu kieli
    loadI18n(el.lang.value).catch(console.error);
  }
})();
