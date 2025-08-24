(() => {
  const cfg = window.__PLANNER__ || {};
  const apiUrl = cfg.apiUrl;
  const maxSteps = cfg.maxSteps || 7;

  // Syöterajat (front): pidä synkassa planner.php:n kanssa
  const LIMITS = {
    topic: 120,
    input: 2000,
    maxBodyKB: 200
  };

  // Elements
  const $ = (sel) => document.querySelector(sel);
  const timelineEl = $('#timeline');
  const aiPanel = $('#aiPanel');
  const topicInput = $('#topicInput');
  const promptInput = $('#promptInput');
  const btnNext = $('#btnNext');
  const btnRestart = $('#btnRestart');
  const btnExport = $('#btnExport');
  const langSel = $('#lang');
  const btnHardWipe = $('#btnHardWipe');
  const btnQuickExit = $('#btnQuickExit');
  const privacyFilter = $('#privacyFilter');
  const loadingOverlay = $('#loadingOverlay');

  // Char counters (luodaan dynaamisesti)
  let topicCounter, inputCounter;

  // ---------------- i18n (sisäänrakennettu) ----------------
  const I18N = {
    fi: {
      title: "Suunnittelija",
      privacy: "Tietosi pysyvät vain selaimessasi.",
      lang_label: "Kieli",
      restart: "Aloita alusta",
      export: "Lataa suunnitelma",
      topic_label: "Suunnitelman aihe",
      prompt_label: "Vastaus / tarkennus",
      next: "Seuraava",
      footer_note: "Yksityisyys: Suunnitelma tallentuu vain selaimeesi.",
      topic_ph: "Esim. 'Kirjoita rakkauskirje' tai 'Pyykinpesu'",
      prompt_ph: "Kirjoita vastaus tai kuvaus tähän...",
      timeline_empty: "Ei askeleita vielä — aloita antamalla aihe ja ensimmäinen vastaus.",
      timeline_click_to_edit: "Palaa tähän askeleeseen",
      confirm_trim: "Palataanko kohtaan {N}? Kohdat sen jälkeen poistetaan.",
      alert_step_loaded: "Valittu askel tuotu muokattavaksi. Voit jatkaa tästä.",
      need_topic: "Anna ensin suunnitelman aihe.",
      server_error: "Palvelinvirhe. Yritä uudelleen.",
      server_error_parse: "Palvelin palautti odottamatonta dataa. Avaa konsoli (F12) nähdäksesi vastauksen.",
      network_error: "Verkkovirhe. Tarkista yhteys.",
      export_title: "Suunnitelma",
      step: "Askel",
      you_said: "Sinä",
      ai_said: "Ohje",
      checklist: "Tarkistuslista",
      latest: "Viimeisin palaute",
      confirm_restart: "Aloitetaanko alusta? Nykyinen suunnitelma poistetaan.",
      hard_wipe: "Poista kaikki",
      quick_exit: "Hätäpoistuminen",
      privacy_disclaimer: "Tämä työkalu toimii vain selaimessasi. Emme tallenna palvelimelle emmekä käytä analytiikkaa. Vastauksesi lähetetään kuitenkin tekoälypalvelulle suunnitelman tuottamista varten. Vältä henkilötietoja tai käytä yksityisyysfiltteriä.",
      privacy_filter_label: "Yksityisyysfiltteri on päällä (poistaa sähköpostit ja puhelinnumerot ennen lähettämistä).",
      quick_tips: "Vinkki: jaetulla laitteella käytä yksityistä selausta. Esc Esc = hätäpoistuminen, Shift+Del = poista kaikki.",
      confirm_hard_wipe: "Poistetaanko KAIKKI tämän sivun tiedot ja välimuistit tältä laitteelta?",
      wipe_done: "Kaikki tämän sivuston paikalliset tiedot ja välimuistit poistettu tältä selaimelta.",
      wipe_error: "Poistossa tapahtui virhe. Sulje selain varmuuden vuoksi.",
      quick_exit_url: "https://www.wikipedia.org",
      loading: "Ladataan…",
      topic_too_long: "Aihe on liian pitkä (max {N} merkkiä). Lyhennä.",
      input_too_long: "Vastaus on liian pitkä (max {N} merkkiä). Lyhennä.",
      payload_too_large: "Lähetys on liian suuri. Lyhennä tekstiä (max {KB} kB).",
      counter_of: "{USED}/{MAX}"
    },
    en: {
      title: "Planner",
      privacy: "Your data stays in your browser only.",
      lang_label: "Language",
      restart: "Start over",
      export: "Download plan",
      topic_label: "Plan topic",
      prompt_label: "Answer / detail",
      next: "Next",
      footer_note: "Privacy: the plan is stored only in your browser.",
      topic_ph: "e.g. 'Write a love letter' or 'Do the laundry'",
      prompt_ph: "Type your answer or description here...",
      timeline_empty: "No steps yet — start by giving a topic and your first answer.",
      timeline_click_to_edit: "Return to this step",
      confirm_trim: "Return to step {N}? Steps after it will be removed.",
      alert_step_loaded: "Selected step loaded for editing. You can continue from here.",
      need_topic: "Please provide the plan topic first.",
      server_error: "Server error. Please try again.",
      server_error_parse: "Server returned unexpected data. Open DevTools (F12) to see it.",
      network_error: "Network error. Check your connection.",
      export_title: "Plan",
      step: "Step",
      you_said: "You",
      ai_said: "Instruction",
      checklist: "Checklist",
      latest: "Latest feedback",
      confirm_restart: "Start over? The current plan will be deleted.",
      hard_wipe: "Clear all",
      quick_exit: "Quick exit",
      privacy_disclaimer: "This tool runs in your browser only. We do not store data on our server or use analytics. Your messages are sent to the AI service to generate guidance. Avoid personal data or keep the privacy filter on.",
      privacy_filter_label: "Privacy filter ON (removes emails and phone numbers before sending).",
      quick_tips: "Tip: on shared devices use private browsing. Esc Esc = quick exit, Shift+Del = clear all.",
      confirm_hard_wipe: "Clear ALL data and caches for this site on this device?",
      wipe_done: "All local data and caches for this site have been removed from this browser.",
      wipe_error: "An error occurred while clearing. Close the browser just in case.",
      quick_exit_url: "https://www.wikipedia.org",
      loading: "Loading…",
      topic_too_long: "Topic is too long (max {N} characters). Please shorten.",
      input_too_long: "Answer is too long (max {N} characters). Please shorten.",
      payload_too_large: "Request is too large. Please shorten your text (max {KB} kB).",
      counter_of: "{USED}/{MAX}"
    },
    sv: {
      title: "Planerare",
      privacy: "Dina uppgifter stannar endast i din webbläsare.",
      lang_label: "Språk",
      restart: "Börja om",
      export: "Ladda ner planen",
      topic_label: "Planens ämne",
      prompt_label: "Svar / förtydligande",
      next: "Nästa",
      footer_note: "Integritet: planen lagras endast i din webbläsare.",
      topic_ph: "t.ex. 'Skriv ett kärleksbrev' eller 'Tvätta kläder'",
      prompt_ph: "Skriv ditt svar eller beskrivning här...",
      timeline_empty: "Inga steg ännu — börja med ämnet och ditt första svar.",
      timeline_click_to_edit: "Gå tillbaka till detta steg",
      confirm_trim: "Gå tillbaka till steg {N}? Stegen efter tas bort.",
      alert_step_loaded: "Valt steg har laddats för redigering. Du kan fortsätta härifrån.",
      need_topic: "Ange planens ämne först.",
      server_error: "Serverfel. Försök igen.",
      server_error_parse: "Servern returnerade oväntad data. Öppna DevTools (F12) för att se den.",
      network_error: "Nätverksfel. Kontrollera anslutningen.",
      export_title: "Plan",
      step: "Steg",
      you_said: "Du",
      ai_said: "Instruktion",
      checklist: "Checklista",
      latest: "Senaste feedback",
      confirm_restart: "Börja om? Den aktuella planen raderas.",
      hard_wipe: "Radera allt",
      quick_exit: "Snabbutgång",
      privacy_disclaimer: "Detta verktyg körs endast i din webbläsare. Vi lagrar inget på servern och använder ingen analys. Dina meddelanden skickas dock till AI-tjänsten för att skapa vägledning. Undvik personuppgifter eller håll integritetsfiltret på.",
      privacy_filter_label: "Integritetsfilter PÅ (tar bort e-post och telefonnummer innan sändning).",
      quick_tips: "Tips: använd privat läge på delade enheter. Esc Esc = snabbutgång, Shift+Del = radera allt.",
      confirm_hard_wipe: "Radera ALL data och cache för denna sida på denna enhet?",
      wipe_done: "All lokal data och cache för denna webbplats har raderats från denna webbläsare.",
      wipe_error: "Ett fel uppstod vid radering. Stäng webbläsaren för säkerhets skull.",
      quick_exit_url: "https://www.wikipedia.org",
      loading: "Laddar…",
      topic_too_long: "Ämnet är för långt (max {N} tecken). Förkorta.",
      input_too_long: "Svaret är för långt (max {N} tecken). Förkorta.",
      payload_too_large: "Begäran är för stor. Förkorta din text (max {KB} kB).",
      counter_of: "{USED}/{MAX}"
    }
  };

  // i18n state
  let i18n = {};
  let lang = localStorage.getItem('planner_lang') || cfg.defaultLang || 'fi';

  // State (localStorage only)
  const LS_KEY = 'planner_state_v1';
  let state = loadState() || {
    lang,
    topic: '',
    steps: [],
    createdAt: Date.now()
  };

  // --- Init ---
  langSel && (langSel.value = state.lang || lang);
  topicInput && (topicInput.value = state.topic || '');

  // Aseta maxlengthit heti
  if (topicInput)  topicInput.setAttribute('maxlength', String(LIMITS.topic));
  if (promptInput) promptInput.setAttribute('maxlength', String(LIMITS.input));

  // Luo laskurit
  if (topicInput)  topicCounter  = attachCounter(topicInput,  LIMITS.topic);
  if (promptInput) inputCounter  = attachCounter(promptInput, LIMITS.input);

  loadI18n(state.lang).then(applyI18n);
  render();

  // --- Events ---
  langSel?.addEventListener('change', async () => {
    state.lang = langSel.value;
    lang = state.lang;
    localStorage.setItem('planner_lang', state.lang);
    saveState();
    await loadI18n(state.lang);
    applyI18n();
    render();
  });

  btnRestart?.addEventListener('click', () => {
    if (confirm(txt('confirm_restart', 'Aloitetaanko alusta? Nykyinen suunnitelma poistetaan.'))) {
      state = { lang: state.lang, topic: '', steps: [], createdAt: Date.now() };
      topicInput && (topicInput.value = '');
      promptInput && (promptInput.value = '');
      aiPanel && (aiPanel.innerHTML = '');
      if (topicCounter) updateCounter(topicInput, LIMITS.topic, topicCounter);
      if (inputCounter) updateCounter(promptInput, LIMITS.input, inputCounter);
      saveState();
      render();
    }
  });

  btnExport?.addEventListener('click', () => {
    const text = exportText();
    const blob = new Blob([text], { type: 'text/plain;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    const topicSlug = (state.topic || 'suunnitelma').toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9\-]/gi, '');
    a.href = url;
    a.download = `${topicSlug || 'suunnitelma'}.txt`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
  });

  btnNext?.addEventListener('click', () => next(false));
  promptInput?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
      e.preventDefault();
      next(false);
    }
  });

  // Päivitä laskurit syötön aikana ja leikkaa yli-maksit
  topicInput?.addEventListener('input', () => {
    if (topicCounter) updateCounter(topicInput, LIMITS.topic, topicCounter);
  });
  promptInput?.addEventListener('input', () => {
    if (inputCounter) updateCounter(promptInput, LIMITS.input, inputCounter);
  });

  timelineEl?.addEventListener('click', (e) => {
    const stepBtn = e.target.closest?.('[data-step-index]');
    if (!stepBtn) return;
    const idx = parseInt(stepBtn.getAttribute('data-step-index'), 10);
    const msg = txt('confirm_trim', 'Palataanko kohtaan {N}? Kohdat sen jälkeen poistetaan.').replace('{N}', (idx + 1).toString());
    if (confirm(msg)) {
      const step = state.steps[idx];
      const aiText = (step && step.ai && step.ai.message) ? step.ai.message : '';
      promptInput && (promptInput.value = aiText || step.user || '');
      if (inputCounter) updateCounter(promptInput, LIMITS.input, inputCounter);
      state.steps = state.steps.slice(0, idx + 1);
      saveState();
      render();
      alert(txt('alert_step_loaded', 'Valittu askel tuotu muokattavaksi. Voit jatkaa tästä.'));
    }
  });

  btnHardWipe?.addEventListener('click', hardWipe);
  btnQuickExit?.addEventListener('click', quickExit);

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      if (window.__escOnce) { quickExit(); }
      else { window.__escOnce = true; setTimeout(()=> window.__escOnce = false, 700); }
    }
    if (e.key === 'Delete' && (e.shiftKey || e.metaKey || e.ctrlKey)) {
      e.preventDefault();
      hardWipe();
    }
  });

  // --- Functions ---
  function txt(key, fallback) { return (i18n[key] ?? fallback); }
  function loadI18n(nextLang) { return new Promise((r)=>{ i18n = I18N[nextLang] || I18N.fi; r(); }); }

  function applyI18n() {
    $('#i18n_title') && ($('#i18n_title').textContent = txt('title', 'Suunnittelija'));
    $('#i18n_privacy') && ($('#i18n_privacy').textContent = txt('privacy', 'Tietosi pysyvät vain selaimessasi.'));
    $('#i18n_lang_label') && ($('#i18n_lang_label').textContent = txt('lang_label', 'Kieli'));
    $('#i18n_restart') && ($('#i18n_restart').textContent = txt('restart', 'Aloita alusta'));
    $('#i18n_export') && ($('#i18n_export').textContent = txt('export', 'Lataa suunnitelma'));
    $('#i18n_topic_label') && ($('#i18n_topic_label').textContent = txt('topic_label', 'Suunnitelman aihe'));
    $('#i18n_prompt_label') && ($('#i18n_prompt_label').textContent = txt('prompt_label', 'Vastaus / tarkennus'));
    $('#i18n_next') && ($('#i18n_next').textContent = txt('next', 'Seuraava'));
    $('#i18n_footer_note') && ($('#i18n_footer_note').textContent = txt('footer_note', 'Yksityisyys: Suunnitelma tallentuu vain selaimeesi.'));
    $('#i18n_hard_wipe') && ($('#i18n_hard_wipe').textContent = txt('hard_wipe', 'Poista kaikki'));
    $('#i18n_quick_exit') && ($('#i18n_quick_exit').textContent = txt('quick_exit', 'Hätäpoistuminen'));
    $('#i18n_privacy_disclaimer') && ($('#i18n_privacy_disclaimer').textContent = txt('privacy_disclaimer', 'Tämä työkalu toimii vain selaimessasi...'));
    $('#i18n_privacy_filter_label') && ($('#i18n_privacy_filter_label').textContent = txt('privacy_filter_label', 'Yksityisyysfiltteri on päällä...'));
    $('#i18n_quick_tips') && ($('#i18n_quick_tips').textContent = txt('quick_tips', 'Vinkki: jaetulla laitteella...'));
    $('#i18n_loading') && ($('#i18n_loading').textContent = txt('loading', 'Ladataan…'));

    // Päivitä laskurien teksti heti oikealla kielellä
    if (topicCounter) updateCounter(topicInput, LIMITS.topic, topicCounter);
    if (inputCounter) updateCounter(promptInput, LIMITS.input, inputCounter);

    topicInput && (topicInput.placeholder = txt('topic_ph', "Esim. 'Kirjoita rakkauskirje' tai 'Pyykinpesu'"));
    promptInput && (promptInput.placeholder = txt('prompt_ph', 'Kirjoita vastaus tai kuvaus tähän...'));
  }

  function loadState() {
    try { const raw = localStorage.getItem(LS_KEY); return raw ? JSON.parse(raw) : null; }
    catch { return null; }
  }
  function saveState() { try { localStorage.setItem(LS_KEY, JSON.stringify(state)); } catch {} }

  function render() {
    if (!timelineEl) return;
    timelineEl.innerHTML = '';
    const ul = document.createElement('ul');
    ul.className = 'timeline-list';

    if (!state.steps.length) {
      const li = document.createElement('li');
      li.className = 'timeline-empty';
      li.textContent = txt('timeline_empty', 'Ei askeleita vielä — aloita antamalla aihe ja ensimmäinen vastaus.');
      ul.appendChild(li);
    } else {
      state.steps.forEach((s, i) => {
        const li = document.createElement('li');
        li.className = 'timeline-item';

        const btn = document.createElement('button');
        btn.className = 'timeline-step';
        btn.setAttribute('data-step-index', String(i));
        btn.title = txt('timeline_click_to_edit', 'Palaa tähän askeleeseen');

        const num = document.createElement('span');
        num.className = 'step-num';
        num.textContent = String(s.n);

        const title = document.createElement('span');
        title.className = 'step-title';
        title.textContent = (s.ai && s.ai.title) ? s.ai.title : `${txt('step', 'Askel')} ${s.n}`;

        btn.appendChild(num);
        btn.appendChild(title);

        const body = document.createElement('div');
        body.className = 'step-body';

        const userQ = document.createElement('div');
        userQ.className = 'step-user';
        userQ.textContent = s.user || '';

        const aiAns = document.createElement('div');
        aiAns.className = 'step-ai';
        const aiMsg = (s.ai && s.ai.message) ? s.ai.message : '';
        aiAns.textContent = aiMsg;

        body.appendChild(userQ);
        body.appendChild(aiAns);

        if (s.ai && Array.isArray(s.ai.checklist) && s.ai.checklist.length) {
          const chk = document.createElement('ul');
          chk.className = 'step-checklist';
          s.ai.checklist.forEach(item => {
            const li2 = document.createElement('li');
            li2.textContent = item;
            chk.appendChild(li2);
          });
          body.appendChild(chk);
        }

        li.appendChild(btn);
        li.appendChild(body);
        ul.appendChild(li);
      });
    }

    timelineEl.appendChild(ul);

    if (aiPanel) {
      aiPanel.innerHTML = '';
      const latest = state.steps[state.steps.length - 1];
      if (latest && latest.ai) {
        const h = document.createElement('h3');
        h.textContent = latest.ai.title || (txt('latest', 'Viimeisin palaute'));
        const p = document.createElement('p');
        p.textContent = latest.ai.message || '';
        aiPanel.appendChild(h);
        aiPanel.appendChild(p);
      }
    }
  }

  // ---------- LOADING ----------
  function setLoading(isOn) {
    const body = document.body;
    if (!body || !loadingOverlay) return;
    if (isOn) {
      body.classList.add('is-loading');
      loadingOverlay.hidden = false;
      loadingOverlay.setAttribute('aria-hidden', 'false');
    } else {
      body.classList.remove('is-loading');
      loadingOverlay.hidden = true;
      loadingOverlay.setAttribute('aria-hidden', 'true');
    }
  }

  // ---------- NEXT / FETCH ----------
  async function next(forceDone) {
    const topic = (topicInput?.value || '').trim();
    const rawInput = (promptInput?.value || '').trim();

    // Eturajojen tarkistus (ennen pyyntöä)
    if (topic && topic.length > LIMITS.topic) {
      alert(txt('topic_too_long', 'Aihe on liian pitkä (max {N} merkkiä). Lyhennä.').replace('{N}', String(LIMITS.topic)));
      return;
    }
    if (rawInput && rawInput.length > LIMITS.input) {
      alert(txt('input_too_long', 'Vastaus on liian pitkä (max {N} merkkiä). Lyhennä.').replace('{N}', String(LIMITS.input)));
      return;
    }

    const input = sanitizeInput(rawInput);

    if (!state.steps.length && !topic) {
      alert(txt('need_topic', 'Anna ensin suunnitelman aihe.'));
      topicInput?.focus();
      return;
    }

    if (state.steps.length >= maxSteps && !forceDone) {
      forceDone = true;
    }

    btnNext && (btnNext.disabled = true);
    setLoading(true);

    const payload = {
      lang: state.lang,
      topic: topic || state.topic || '',
      history: state.steps.map(s => ({ n: s.n, user: s.user, ai: s.ai })),
      input,
      stepIndex: state.steps.length,
      forceDone: !!forceDone
    };

    try {
      const res = await fetch(apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
        cache: 'no-store',
      });

      const raw = await res.text();
      let data;
      try { data = JSON.parse(raw); }
      catch {
        console.error('[planner] Non-JSON response:', { status: res.status, raw });
        alert(txt('server_error_parse', 'Palvelin palautti odottamatonta dataa. Avaa konsoli (F12) nähdäksesi vastauksen.'));
        return;
      }

      if (!data.ok) {
        if (data.error === 'input_too_long') {
          if (data.field === 'topic') {
            alert(txt('topic_too_long', 'Aihe on liian pitkä (max {N} merkkiä). Lyhennä.').replace('{N}', String(data.limits?.max_topic_len || LIMITS.topic)));
            return;
          }
          if (data.field === 'input') {
            alert(txt('input_too_long', 'Vastaus on liian pitkä (max {N} merkkiä). Lyhennä.').replace('{N}', String(data.limits?.max_input_len || LIMITS.input)));
            return;
          }
        }
        if (data.error === 'payload_too_large') {
          const kb = Math.round((data.limits?.max_body_bytes || LIMITS.maxBodyKB*1024)/1024);
          alert(txt('payload_too_large', 'Lähetys on liian suuri. Lyhennä tekstiä (max {KB} kB).').replace('{KB}', String(kb)));
          return;
        }
        console.error('[planner] API error payload:', data);
        alert(txt('server_error', 'Palvelinvirhe. Yritä uudelleen.'));
        return;
      }

      if (!state.topic && topic) state.topic = topic;

      const n = state.steps.length + 1;
      const ai = data.ai || {};
      const step = { n, user: rawInput, ai };
      state.steps.push(step);

      promptInput && (promptInput.value = '');
      if (inputCounter) updateCounter(promptInput, LIMITS.input, inputCounter);

      saveState();
      render();

    } catch (e) {
      console.error(e);
      alert(txt('network_error', 'Verkkovirhe. Tarkista yhteys.'));
    } finally {
      btnNext && (btnNext.disabled = false);
      setLoading(false);
    }
  }

  function exportText() {
    const lines = [];
    lines.push(`${txt('export_title', 'Suunnitelma')}: ${state.topic || '-'}`);
    lines.push('');
    state.steps.forEach(s => {
      lines.push(`${txt('step', 'Askel')} ${s.n}`);
      if (s.ai && s.ai.title) lines.push(`- ${s.ai.title}`);
      if (s.user) lines.push(`${txt('you_said', 'Sinä')}: ${s.user}`);
      if (s.ai && s.ai.message) lines.push(`${txt('ai_said', 'Ohje')}: ${s.ai.message}`);
      if (s.ai && Array.isArray(s.ai.checklist) && s.ai.checklist.length) {
        lines.push(txt('checklist', 'Tarkistuslista') + ':');
        s.ai.checklist.forEach(it => lines.push(`  • ${it}`));
      }
      lines.push('');
    });
    return lines.join('\n');
  }

  function sanitizeInput(text) {
    if (!privacyFilter || !privacyFilter.checked) return text;
    let t = text;
    t = t.replace(/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/gi, '[email]');
    t = t.replace(/(\+?\d[\d\s().-]{6,}\d)/g, '[phone]');
    return t;
  }

  // ---- Laskurit ----
  function attachCounter(inputEl, max) {
    const wrap = document.createElement('div');
    wrap.className = 'char-counter';
    inputEl.insertAdjacentElement('afterend', wrap);
    updateCounter(inputEl, max, wrap);
    return wrap;
  }
  function updateCounter(inputEl, max, counterEl) {
    if (!inputEl || !counterEl) return;
    const used = (inputEl.value || '').length;
    counterEl.textContent = (txt('counter_of', '{USED}/{MAX}')).replace('{USED}', String(used)).replace('{MAX}', String(max));
    counterEl.classList.toggle('near-limit', used >= Math.floor(max*0.9));
    counterEl.classList.toggle('over-limit', used > max);
  }

  async function hardWipe() {
    const ok = confirm(txt('confirm_hard_wipe', 'Poistetaanko KAIKKI tämän sivun tiedot ja välimuistit tältä laitteelta?'));
    if (!ok) return;
    try {
      localStorage.clear();
      sessionStorage.clear();

      if (window.caches && caches.keys) {
        const keys = await caches.keys();
        await Promise.all(keys.map(k => caches.delete(k)));
      }
      if (window.indexedDB) {
        if (indexedDB.databases) {
          const dbs = await indexedDB.databases();
          await Promise.all((dbs || []).map(db => db && db.name
            ? new Promise((res)=>{ const del = indexedDB.deleteDatabase(db.name); del.onsuccess=del.onerror=del.onblocked=()=>res(); })
            : Promise.resolve()
          ));
        } else {
          await new Promise((res)=>{ const del = indexedDB.deleteDatabase('planner'); del.onsuccess=del.onerror=del.onblocked=()=>res(); });
        }
      }
      if (navigator.serviceWorker && navigator.serviceWorker.getRegistrations) {
        const regs = await navigator.serviceWorker.getRegistrations();
        await Promise.all(regs.map(r => r.unregister()));
      }

      // Keksit
      document.cookie.split(';').forEach(c => {
        const eqPos = c.indexOf('=');
        const name = (eqPos > -1 ? c.substr(0, eqPos) : c).trim();
        if (!name) return;
        const domains = location.hostname.split('.').map((_,i,arr)=>arr.slice(i).join('.'));
        domains.forEach(d => {
          document.cookie = `${name}=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/;domain=${d}`;
        });
        document.cookie = `${name}=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/`;
      });

      state = { lang: cfg.defaultLang || 'fi', topic: '', steps: [], createdAt: Date.now() };
      saveState();

      alert(txt('wipe_done', 'Kaikki tämän sivuston paikalliset tiedot ja välimuistit poistettu tältä selaimelta.'));
      window.location.replace('about:blank');
    } catch(e) {
      console.error(e);
      alert(txt('wipe_error', 'Poistossa tapahtui virhe. Sulje selain varmuuden vuoksi.'));
    }
  }

  function quickExit() {
    try {
      localStorage.clear(); sessionStorage.clear();
      if (window.caches && caches.keys) caches.keys().then(keys => keys.forEach(k => caches.delete(k)));
      if (navigator.serviceWorker && navigator.serviceWorker.getRegistrations) navigator.serviceWorker.getRegistrations().then(regs => regs.forEach(r => r.unregister()));
    } catch(e) {}
    window.location.href = txt('quick_exit_url', 'https://www.wikipedia.org');
  }
})();
