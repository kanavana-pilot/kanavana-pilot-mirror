// /assets/js/jobs.js
(() => {
  const getLang = () => (window.i18n?.getLocale?.() || "fi");
  const steps = ["step-1", "step-2", "step-3"];
  const $ = id => document.getElementById(id);

  // Vakiot elementti-ID:ille
  const OUT_LOCAL_ID = 'out-local';
  const OUT_FI_ID    = 'out-fi';
  const $local = () => document.getElementById(OUT_LOCAL_ID) || document.getElementById('outLocal');
  const $fi    = () => document.getElementById(OUT_FI_ID)    || document.getElementById('outFi');

  // Ekan #statusin otto (sivulla on kaksi)
  const statusEl = document.querySelector('#status');
  const errSum = $('errorSummary'), errList = $('errorList');
  let lastApiResponse = null;
  let showingPlain = false;

  const on = (id, evt, fn) => { const el = $(id); if (el) el.addEventListener(evt, fn); };

  function setRenderModeText(modeKey){
    const txt = i18n?.t("ui.showing", { mode: i18n?.t(modeKey) }) || '';
    document.querySelectorAll('#renderMode').forEach(el => { el.textContent = txt; });
  }

  // --- XSS-suoja ---
  const SANITIZE_CFG = {
    USE_PROFILES: { html: true },
    ALLOWED_URI_REGEXP: /^(?:(?:https?|mailto|tel):|[^a-z]|[a-z+.-]+(?:[^a-z+.-]|$))/i
  };
  function ensureDomPurifyHooks() {
    if (window.DOMPurify && !window.__dpHooked) {
      DOMPurify.addHook('afterSanitizeAttributes', (node) => {
        if (node && node.tagName && node.tagName.toLowerCase() === 'a') {
          const href = node.getAttribute('href') || '';
          if (/^(https?:|mailto|tel):/i.test(href)) {
            if (!node.getAttribute('rel')) node.setAttribute('rel', 'noopener noreferrer');
          } else {
            node.removeAttribute('href');
          }
        }
      });
      window.__dpHooked = true;
    }
  }
  function safeSetHtml(target, dirtyHtml) {
    if (!target) return;
    if (window.DOMPurify && typeof DOMPurify.sanitize === 'function') {
      ensureDomPurifyHooks();
      target.innerHTML = DOMPurify.sanitize(dirtyHtml || '', SANITIZE_CFG);
    } else {
      target.textContent = (dirtyHtml || '').replace(/<[^>]+>/g, '');
    }
  }

  // --- Step-vaihto ---
  function showStep(n, push = true) {
    steps.forEach((sid, i) => {
      const el = $(sid);
      if (!el) return;
      const hide = i !== n;
      el.hidden = hide;
      el.setAttribute('aria-hidden', String(hide));
      if (hide) el.setAttribute('inert', ''); else el.removeAttribute('inert');
    });

    if (push) history.pushState({ step: n }, "", `#step=${n + 1}`);

    steps.forEach((sid, i) => {
      const badge = $('badge-' + (i + 1));
      if (badge) badge.style.fontWeight = (i === n ? '700' : '400');
    });

    const target = $(steps[n]);
    if (target) {
      const firstFocusable = target.querySelector('input, select, textarea, button, [tabindex]:not([tabindex="-1"])');
      if (firstFocusable) firstFocusable.focus({ preventScroll: true });
      else { target.setAttribute('tabindex', '-1'); target.focus({ preventScroll: true }); target.removeAttribute('tabindex'); }
    }

    const nav = $('stepNav');
    if (nav) {
      [...nav.querySelectorAll('.badge, .step-badge')].forEach((btn, i) => {
        if (i === n) btn.setAttribute('aria-current','step'); else btn.removeAttribute('aria-current');
      });
    }

    hideErrorSummary();
  }

  function gather() {
    return {
      jobLink:   $('jobLink').value.trim(),
      company:   $('company')?.value.trim() || '',
      intro:     $('intro').value.trim(),
      strengths: $('strengths').value.trim(),
      motivation:$('motivation').value.trim(),
      example:   $('example').value.trim(),
      tone:      $('tone').value,
      length:    $('length').value
    };
  }

  // --- URL-utils ---
  function looksLikeUrl(s){ return /^(https?:\/\/|www\.)/i.test(s) || /^[^\s]+\.[^\s]{2,}$/i.test(s); }
  function normalizeJobInput(raw){
    const v = (raw || '').trim();
    if (!v) return { value: '', isUrl:false };
    if (looksLikeUrl(v)) {
      const fixed = /^https?:\/\//i.test(v) ? v : `https://${v}`;
      try { const u = new URL(fixed); return { value: u.toString(), isUrl:true }; }
      catch { return { value: v, isUrl:false }; }
    }
    return { value: v, isUrl:false };
  }
  const clip = (s, n) => (s || '').replace(/\s+/g,' ').trim().slice(0, n);

  function buildQuery(d){
    const norm = normalizeJobInput(d.jobLink);
    const input = norm.value, isUrl = norm.isUrl;

    const intro      = clip(d.intro, 180);
    const strengths  = clip(d.strengths, 120);
    const motivation = clip(d.motivation, 120);
    const example    = clip(d.example, 140);

    const writeCommon = [
      `Profiili: ${intro}`,
      strengths  && `Vahvuudet: ${strengths}`,
      motivation && `Motivaatio: ${motivation}`,
      example    && `Esimerkki: ${example}`
    ].filter(Boolean).join('. ');

    const companyName = (d.company || '').trim() || (!isUrl && input ? clip(input, 80) : '');

    let search, write;
    if (isUrl) {
      search = input;
      write  = `Laadi suomeksi työhakemus (sävy: ${d.tone}, pituus: ${d.length}). ${writeCommon}. Huomioi ilmoituksen vaatimukset.`;
    } else if (companyName) {
      search = `"${companyName}" (ura OR "avoimet työpaikat" OR rekry OR careers OR jobs)`;
      write  = `Laadi suomeksi avoin työhakemus yritykselle "${companyName}" (sävy: ${d.tone}, pituus: ${d.length}). ${writeCommon}. Hyödynnä yrityksestä löytyvää julkista tietoa (toimiala, arvot, kulttuuri) ilman spekulointia.`;
    } else {
      search = 'työpaikkailmoitus';
      write  = `Laadi suomeksi työhakemus (sävy: ${d.tone}, pituus: ${d.length}). ${writeCommon}.`;
    }

    const out = `SEARCH: ${search}\nWRITE: ${write}`;
    return out.length > 380 ? out.slice(0, 380) : out;
  }

  function saveLocal(){
    try { localStorage.setItem('jobs_aiapply', JSON.stringify(gather())); } catch {}
    const t = document.getElementById('savedToast');
    if (t && !document.body.classList.contains('is-loading')) {
      t.classList.add('show'); setTimeout(()=>t.classList.remove('show'), 1200);
    }
  }
  function loadLocal(){
    try {
      const d = JSON.parse(localStorage.getItem('jobs_aiapply')||'{}');
      Object.entries(d).forEach(([k,v])=>{ if($(k)) $(k).value = v; });
    } catch {}
  }

  function validateStep1(){
    const errors = [];
    const intro = $('intro')?.value.trim();
    if (!intro) errors.push({id:'intro', msg: i18n?.t("field.introReq") || 'Kirjoita 2–3 virkettä esittelystäsi.'});

    const rawEl = $('jobLink');
    const raw = rawEl ? rawEl.value : '';
    if (!raw) return errors;

    const norm = normalizeJobInput(raw);
    if (rawEl) rawEl.value = norm.value;

    if (looksLikeUrl(raw) && !norm.isUrl) {
      errors.push({ id:'jobLink', msg: i18n?.t("field.urlInvalid") || 'Linkin tulee olla kelvollinen (http/https).' });
    }
    return errors;
  }

  function renderFieldErrors(errors){
    ['jobLink','intro','strengths','motivation','example'].forEach(id=>{
      $('grp-'+id)?.classList.remove('invalid');
      $('err-'+id)?.replaceChildren?.();
    });
    errors.forEach(e=>{
      $('grp-'+e.id)?.classList.add('invalid');
      if ($('err-'+e.id)) $('err-'+e.id).textContent = e.msg;
    });
  }
  function showErrorSummary(errors){
    if (!errSum || !errList) return;
    errList.replaceChildren();
    errors.forEach(e=>{
      const li = document.createElement('li');
      const a = document.createElement('a');
      a.href = '#'+e.id; a.textContent = e.msg;
      a.addEventListener('click', ev=>{ ev.preventDefault(); $(e.id)?.focus(); });
      li.appendChild(a); errList.appendChild(li);
    });
    errSum.hidden = false; errSum.setAttribute('tabindex','-1'); errSum.focus();
    document.title = 'Virhe: ' + document.title;
  }
  function hideErrorSummary(){
    if (!errSum || !errList) return;
    errSum.hidden = true; errList.replaceChildren();
  }

  const hashStr = (s) => { let h=0; for (let i=0;i<(s||'').length;i++) h=(h*31 + s.charCodeAt(i))|0; return (h>>>0).toString(16); };

  async function ensureFinnishMirror(respHtml) {
    const fiBox = $fi(); if (!fiBox) return;
    const setFi = (html) => {
      safeSetHtml(fiBox, html || ''); fiBox.dataset.ready = '1';
      fiBox.style.direction = 'ltr'; fiBox.style.unicodeBidi = 'isolate';
    };
    if (getLang() === 'fi') { setFi(respHtml || ''); return; }
    const key = 'fi:' + hashStr(respHtml || '');
    const cached = sessionStorage.getItem(key);
    if (cached) { setFi(cached); return; }
    try {
      setLoading(true, i18n?.t('status.styling') || 'Muokataan tyyliä…');
      const res = await fetch('/search/rewrite.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ html: respHtml, style: 'clear', lang: 'fi', length: $('length')?.value || 'Keskitaso' })
      });
      const text = await res.text();
      if (!res.ok) throw new Error(`HTTP ${res.status}: ${text}`);
      const data = JSON.parse(text);
      const fiHtml = data.answer_html || '';
      setFi(fiHtml || '(ei suomenkielistä versiota)');
      sessionStorage.setItem(key, fiHtml);
    } catch {
      setFi(`(ei suomenkielistä versiota)`);
    } finally { setLoading(false); }
  }

  function renderServerError(d){
    const detail = d.detail ? `<pre>${(String(d.detail)).slice(0,2000).replace(/[&<>"']/g,m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m]))}</pre>` : '';
    const html =
      `<p><strong>Palvelimen virhe:</strong> ${((d.error||'tuntematon')).replace(/[&<>"']/g,m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m]))}</p>${detail}
       <p><a href="/search/answer.php?health=1" target="_blank" rel="noopener noreferrer">Avaa health-check</a></p>`;
    safeSetHtml($local(), html); safeSetHtml($fi(), '');
  }

  // --- Backend-kutsu ---
  async function callAnswer(refine=false){
    const d = gather();
    const norm = normalizeJobInput(d.jobLink);
    d.jobLink = norm.value;

    const q = buildQuery(d);

    const payload = {
      q, lang: getLang(), gov_only: false,
      company: (d.company || '').trim(),
      job_url: norm.isUrl ? norm.value : ''
    };

    if (norm.isUrl) {
      try { const host = new URL(norm.value).hostname; if (host) payload.include_domains = [host]; } catch {}
    }
    const qs = location.search.includes('debug=1') ? '?debug=1&nocache=1' : '';

    setLoading(true, refine ? i18n?.t("status.refining") : i18n?.t("status.creating"));
    try {
      const res = await fetch('/search/answer.php' + qs, {
        method:'POST', headers:{ 'Content-Type':'application/json' }, body: JSON.stringify(payload)
      });
      const text = await res.text();
      if (!res.ok) throw new Error(`HTTP ${res.status} ${res.statusText}: ${text}`);
      let data; try { data = JSON.parse(text); } catch { throw new Error('Palvelin ei palauttanut kelvollista JSONia.'); }
      if (data.error) { renderServerError(data); return; }

      lastApiResponse = data; showingPlain = false;
      setRenderModeText("mode.normal");
      safeSetHtml($local(), data.answer_html || '(ei sisältöä)');
      if (statusEl) statusEl.textContent = i18n?.t("status.done") || "Valmis.";
      saveLocal();
      await ensureFinnishMirror(data.answer_html || '');
    } catch(e){
      safeSetHtml($local(), `<p><strong>Virhe:</strong> ${(e.message||e).toString().replace(/[&<>"']/g,m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m]))}</p>`);
      safeSetHtml($fi(), '');
      if (statusEl) statusEl.textContent = i18n?.t("status.error") || "Virhe luonnoksessa.";
      console.error('[answer.php]', e);
    } finally { setLoading(false); }
  }

  // --- Iterointi: rewrite.php ---
  async function iterateStyle(){
    if (!lastApiResponse?.answer_html) { safeSetHtml($local(), '(ei vielä luonnosta muokattavaksi)'); return; }
    const style  = $('styleSel')?.value || 'clear';
    const length = $('lenSel')?.value || 'Keskitaso';
    setLoading(true, i18n?.t("status.styling") || "Muokataan tyyliä…");
    try{
      const res = await fetch('/search/rewrite.php', {
        method:'POST', headers:{ 'Content-Type':'application/json' },
        body: JSON.stringify({ html: lastApiResponse.answer_html, style, length, lang: getLang() })
      });
      const text = await res.text();
      if (!res.ok) throw new Error(`HTTP ${res.status}: ${text}`);
      const data = JSON.parse(text);
      if (data.error) { renderServerError(data); return; }
      lastApiResponse = data; showingPlain = false;
      setRenderModeText("mode.normal");
      safeSetHtml($local(), data.answer_html || '(ei sisältöä)');
      if (statusEl) statusEl.textContent = i18n?.t("status.done") || 'Valmis.';
      await ensureFinnishMirror(data.answer_html || '');
    } catch(e){
      safeSetHtml($local(), `<p><strong>Virhe:</strong> ${(e.message||e).toString().replace(/[&<>"']/g,m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m]))}</p>`);
      console.error('[rewrite.php]', e);
    } finally { setLoading(false); }
  }

  // --- Navigointi ---
  on('to-2','click', () => {
    const errors = validateStep1(); renderFieldErrors(errors);
    if (errors.length) return showErrorSummary(errors);
    saveLocal(); showStep(1);
  });
  on('back-1','click', () => showStep(0));
  on('to-3','click', async (ev) => {
    const btn = ev.currentTarget; btn.setAttribute('data-loading','true');
    try { saveLocal(); await callAnswer(false); showStep(2); }
    finally { btn.removeAttribute('data-loading'); }
  });

  on('refine','click', async (ev) => {
    ev.preventDefault(); ev.stopPropagation();
    const btn = ev.currentTarget; btn.setAttribute('data-loading','true');
    try { saveLocal(); await callAnswer(true); }
    finally { btn.removeAttribute('data-loading'); }
  });

  on('iterate','click', async (ev) => {
    ev.preventDefault(); ev.stopPropagation();
    const btn = ev.currentTarget; btn.setAttribute('data-loading','true');
    try { await iterateStyle(); }
    finally { btn.removeAttribute('data-loading'); }
  });

  // Selkokieli
  on('togglePlain','click', (ev) => {
    ev.preventDefault(); ev.stopPropagation();
    if (!lastApiResponse) { safeSetHtml($local(), '(ei vielä vastausta)'); return; }
    showingPlain = !showingPlain;
    const localHtml = showingPlain ? (lastApiResponse.plain_html || '(ei selkokieltä)') : (lastApiResponse.answer_html || '(ei normaaliversiota)');
    setRenderModeText(showingPlain ? "mode.plain" : "mode.normal");
    safeSetHtml($local(), localHtml);
  });

  // DOCX
  on('download','click', (ev) => {
    ev.preventDefault(); ev.stopPropagation();
    const preferFi = ($('out-fi') || $('outFi'))?.innerHTML?.trim();
    const html = (preferFi && preferFi !== '(ei suomenkielistä versiota)') ? preferFi : (lastApiResponse?.answer_html || '');
    const sanitized = (window.DOMPurify && typeof DOMPurify.sanitize==='function')
      ? DOMPurify.sanitize(html, SANITIZE_CFG)
      : String(html || '').replace(/<[^>]+>/g,'');
    const blob = new Blob([`<!doctype html><meta charset="utf-8">${sanitized}`], {type:'application/msword'});
    const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = 'tyohakemus.doc';
    document.body.appendChild(a); a.click(); setTimeout(()=>{ URL.revokeObjectURL(a.href); a.remove(); }, 0);
  });

  // --- Reset-toimintojen TOTEUTUKSET ---
  function clearDraftOnly(){
    try {
      localStorage.removeItem('jobs_aiapply');
      sessionStorage.clear(); // tyhjennä peili-cache
    } catch {}
    const form = $('form'); if (form) form.reset();
    $local()?.replaceChildren(); $fi()?.replaceChildren();
    history.replaceState({step:0}, "", "#step=1");
    showStep(0);
    if (statusEl) statusEl.textContent = i18n?.t("ui.reset") || "Tiedot poistettu.";
  }

  async function handleResetAllLang(){
    if (!confirm(i18n?.t("ui.resetAllConfirm") || "Tyhjennetään kaikki tiedot ja kieliasetus?")) return;
    try {
      localStorage.clear(); sessionStorage.clear();
      if ('caches' in window) {
        const keys = await caches.keys(); await Promise.all(keys.map(k => caches.delete(k)));
      }
    } catch {}
    try {
      const def = (window.i18n?.getManifest?.() || {}).default || 'fi';
      await window.i18n?.setLocale?.(def); const langSel = $('langSel'); if (langSel) langSel.value = def;
    } catch {}
    location.replace(location.pathname + "?nocache=" + Date.now());
  }

  function hardRefresh(){
    try {
      if ('caches' in window) { caches.keys().then(keys => keys.forEach(k => caches.delete(k))); }
    } catch {}
    location.replace(location.pathname + "?nocache=" + Date.now());
  }

  // --- Reset-valikko (dropdown) ---
  (function initResetMenu(){
    // Jos sivulla on vahingossa kaksi #resetAll -nappia, nollaa duplikaatti
    const allResetButtons = document.querySelectorAll('#resetAll');
    if (allResetButtons.length > 1) {
      allResetButtons.forEach((b,i)=>{ if (i>0) b.id = 'resetAllLegacy'; });
    }

    const btn  = document.getElementById('resetAll');   // yläpalkin pääpainike
    const menu = document.getElementById('resetMenu');  // dropdown
    if (!btn || !menu) return;

    const open  = () => { menu.removeAttribute('hidden'); btn.setAttribute('aria-expanded','true'); };
    const close = () => { menu.setAttribute('hidden','');   btn.setAttribute('aria-expanded','false'); };

    btn.addEventListener('click', (e) => {
      e.preventDefault(); e.stopPropagation();
      menu.hasAttribute('hidden') ? open() : close();
    });

    // delegointi
    menu.addEventListener('click', (e) => {
      const b = e.target.closest('button'); if (!b) return;
      const act = b.id || b.dataset.action;
      close();
      switch (act) {
        case 'resetDraft':
        case 'reset-draft':     clearDraftOnly(); break;
        case 'resetAllLang':
        case 'reset-all-lang':  handleResetAllLang(); break;
        case 'hardRefresh':
        case 'hard-refresh':    hardRefresh(); break;
      }
    });

    // Jos sivulla on legacy Vaihe-3 -nappi, avaa sama menu kun sitä klikataan
    const legacy = document.getElementById('resetAllLegacy');
    if (legacy) {
      legacy.addEventListener('click', (e) => {
        e.preventDefault(); open(); window.scrollTo({ top: 0, behavior: 'smooth' }); btn.focus();
      });
    }

    document.addEventListener('click', (e) => { if (!menu.contains(e.target) && e.target !== btn) close(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') { close(); btn.focus(); } });
  })();
  // Latausindikaattori + a11y

  function setLoading(on,msg){

    ['refine','download','togglePlain','back-2','iterate','to-3','to-2'].forEach(id=>{

      const el=$(id); if(el){ el.disabled=on; }

    });

    const fallback = on ? (i18n?.t("status.creating") || "Työstetään…") : "";

    if (statusEl) statusEl.textContent = msg || fallback;

    document.body.classList.toggle('is-loading', !!on);

    const overlay = $('busyIndicator'); if (overlay) overlay.setAttribute('aria-hidden', String(!on));

    const wrap = $('dualWrap'); if (wrap) wrap.setAttribute('aria-busy', on ? 'true' : 'false');

  }
  // --- Lomake ---
  const formEl = $('form');
  if (formEl) {
    formEl.addEventListener('submit', (e) => { e.preventDefault(); e.stopPropagation(); });
    formEl.noValidate = true; formEl.setAttribute('autocomplete','off');
    formEl.addEventListener('input', saveLocal);
  }
  ['jobLink','company','intro','strengths','motivation','example'].forEach(id=>{
    const el = $(id); if (el) el.setAttribute('autocomplete','off');
  });

  window.addEventListener('popstate', (ev) => {
    const step = ev.state?.step ?? Math.max(0, (parseInt(new URL(location).hash.split('=').pop())||1)-1);
    showStep(Math.min(Math.max(step,0),2), false);
  });

  // Init
  loadLocal();
  const hashStep = Math.max(1, parseInt(new URL(location).hash.split('=').pop())||1) - 1;
  showStep(Math.min(Math.max(hashStep,0),2), false);

  // kielivalitsin
  const langSel = $('langSel');
  if (langSel) {
    try { langSel.value = i18n.getLocale(); } catch {}
    langSel.addEventListener('change', async () => {
      await i18n.setLocale(langSel.value);
      i18n.applyTranslations(document);
      setRenderModeText(showingPlain ? "mode.plain" : "mode.normal");
      if (lastApiResponse?.answer_html) {
        safeSetHtml($local(), lastApiResponse.answer_html);
        await ensureFinnishMirror(lastApiResponse.answer_html);
      }
      if (statusEl) statusEl.textContent = '';
    });
  }

  // Step-nav: klikkaukset -> showStep
  const stepNav = $('stepNav');
  if (stepNav) {
    stepNav.addEventListener('click', (ev) => {
      const btn = ev.target.closest('.badge[data-step], .step-badge[data-step]');
      if (!btn) return;
      const step = parseInt(btn.getAttribute('data-step'), 10) || 0;
      showStep(step);
    });
  }

  // Asettelu (rinnakkain / allekain)
  const layoutSel = $('layoutSel');
  if (layoutSel) {
    layoutSel.addEventListener('change', () => {
      const wrap = $('dualWrap'); if (!wrap) return;
      wrap.classList.remove('cols','stack');
      wrap.classList.add(layoutSel.value === 'stack' ? 'stack' : 'cols');
    });
  }

  // UX: normalisoi jobLink blurissa
  const jobEl = $('jobLink');
  if (jobEl) {
    jobEl.addEventListener('blur', () => {
      const n = normalizeJobInput(jobEl.value);
      jobEl.value = n.value;
    });
  }
})();
