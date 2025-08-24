(() => {
  // --------- i18n ----------
  const I18N = {
    lang: "fi",
    dict: {},
    resolveLang() {
      const urlLang = new URLSearchParams(location.search).get("lang");
      const ssLang = sessionStorage.getItem("lang");
      const browser = (navigator.language || "en").slice(0,2).toLowerCase();
      return (urlLang || ssLang || browser || "en").replace(/[^a-z]/g,"");
    },
    async load(lang) {
      const tryLangs = [lang, lang.startsWith("fi")?"fi":"en", "en"];
      for (const l of tryLangs) {
        try {
          const r = await fetch(`assets/i18n/${l}.json`, {cache:"no-store"});
          if (r.ok) {
            this.dict = await r.json();
            this.lang = l;
            return;
          }
        } catch(e){}
      }
      this.dict = {}; this.lang = "en";
    },
    t(key, params) {
      const path = key.split(".");
      let cur = this.dict;
      for (const p of path) cur = (cur && cur[p] !== undefined) ? cur[p] : undefined;
      let s = (typeof cur === "string") ? cur : key;
      if (params && typeof params === "object") {
        for (const [k,v] of Object.entries(params)) {
          s = s.replace(new RegExp(`\\{${k}\\}`,"g"), String(v));
        }
      }
      return s;
    },
    apply() {
      // tämä ylikirjoitetaan alempana, kun tx() on määritelty
      document.querySelectorAll("[data-i18n]").forEach(el => {
        el.textContent = I18N.t(el.getAttribute("data-i18n"));
      });
      document.querySelectorAll("[data-i18n-ph]").forEach(el => {
        el.setAttribute("placeholder", I18N.t(el.getAttribute("data-i18n-ph")));
      });
      const sel = document.getElementById("langSel");
      if (sel) {
        sel.innerHTML = "";
        [["fi","Suomi"],["en","English"],["sv","Svenska"]].forEach(([code,label])=>{
          const opt = document.createElement("option");
          opt.value = code; opt.textContent = label;
          if (I18N.lang.startsWith(code)) opt.selected = true;
          sel.appendChild(opt);
        });
      }
    }
  };

  // --------- apu ---------
  const $ = (id) => document.getElementById(id);
  const esc = (s) => {
    const t = document.createElement("span");
    t.textContent = String(s);
    return t.textContent;
  };
  const cut = (v, n=120) => {
    const s = typeof v === "string" ? v : JSON.stringify(v);
    return s.length > n ? s.slice(0,n) + "…" : s;
  };
  const knownFieldsByType = {
    place_item:["item","target"],
    move:["target"],
    open:["target"],
    classify:["target"],
    plan_subgoal:["goal"],
    ask_user:["question"],
    replan:[],
    wait:["seconds"],
    noop:[]
  };

  // --------- i18n namespace-fallback ----------
  function tx(key, params) {
    // 1) yritä suoraan (esim. "ui.title")
    let v = I18N.t(key, params);
    if (v !== key) return v;
    // 2) fallback namespacen alle (esim. "common_sense_ui.ui.title")
    const nsKey = `common_sense_ui.${key}`;
    v = I18N.t(nsKey, params);
    return (v !== nsKey) ? v : key;
  }

  // Korvataan apply käyttämään tx():ää ja päivitetään kielivalitsin
  I18N.apply = function() {
    document.querySelectorAll("[data-i18n]").forEach(el => {
      const k = el.getAttribute("data-i18n");
      el.textContent = tx(k);
    });
    document.querySelectorAll("[data-i18n-ph]").forEach(el => {
      const k = el.getAttribute("data-i18n-ph");
      el.setAttribute("placeholder", tx(k));
    });
    const sel = document.getElementById("langSel");
    if (sel) {
      sel.innerHTML = "";
      [["fi","Suomi"],["en","English"],["sv","Svenska"]].forEach(([code,label])=>{
        const opt = document.createElement("option");
        opt.value = code; opt.textContent = label;
        if (I18N.lang.startsWith(code)) opt.selected = true;
        sel.appendChild(opt);
      });
    }
  };

  // --------- humanize next_action ----------
  function humanizeAction(next_action) {
    if (!next_action || typeof next_action !== "object") {
      return tx("actions.unknown");
    }
    const {type, args = {}} = next_action;
    const params = {};
    (knownFieldsByType[type] || []).forEach(k => params[k] = (args[k] != null ? args[k] : "—"));
    const line = tx(`actions.${type || "unknown"}`, params);
    return (line === `actions.${type || "unknown"}`) ? tx("actions.unknown") : line;
  }

  // --------- API kutsut ----------
  async function callApi(action, payload) {
    const r = await fetch(`./common_sense.php?action=${encodeURIComponent(action)}`, {
      method:"POST",
      headers:{"Content-Type":"application/json"},
      body: JSON.stringify(payload || {})
    });
    let data = null;
    try { data = await r.json(); } catch(e){}
    return data;
  }

  function payloadPlan() {
    // Vastaa nyky-MVP:n oletuksia: goal + context + guardrails :contentReference[oaicite:2]{index=2}
    let context = {};
    const raw = $("context").value.trim();
    if (raw) {
      try { context = JSON.parse(raw); } catch(e){ /* jos ei kelpaa, lähetetään tyhjä */ }
    }
    return {
      goal: $("goal").value || "Put mixed stack of dishes into cabinets",
      context,
      guardrails: ["no_knife_with_others","no_personal_data_leak"]
    };
  }
  function payloadStep() {
    // Käytetään samaa esimerkkilast_statea kuin MVP:ssä :contentReference[oaicite:3]{index=3}
    return {
      last_state: {"holding":"bowl","obstacles":["cabinet_full"],"inventory":["knife"]},
      observation: "Cabinet full of stacked bowls",
      feedback: null
    };
  }
  function payloadInterrupt() {
    return {
      reason: "Safety",
      details: "Holding knife and bowl simultaneously",
      proposed_fix_needed: true
    };
  }

  // --------- status badge (OPENAI key) ----------
  async function checkKeyStatus() {
    // Heuristiikka: luetaan dev-näkymän HTML ja etsitään avain-status.
    // Jos "OPENAI_API_KEY OK" löytyy -> OK; muuten DEMO.
    try {
      const html = await fetch("./common_sense.php", {method:"GET"}).then(r=>r.text());
      const ok = /OPENAI_API_KEY\s*OK/i.test(html) || /badge\s+ok/i.test(html);
      setBadge(ok ? "ok" : "demo");
    } catch(e) {
      setBadge("demo");
    }
  }
  function setBadge(mode){
    const el = $("keyBadge");
    if (!el) return;
    el.classList.remove("ok","err");
    if (mode === "ok") {
      el.classList.add("ok");
      el.textContent = tx("ui.key_ok_badge");
    } else {
      el.classList.add("err");
      el.textContent = tx("ui.demo_badge");
    }
  }

  // --------- renderöinti ----------
  const cardsEl = $("cards");
  const emptyEl = $("emptyState");
  function ensureNonEmpty() {
    const hasAny = cardsEl && cardsEl.querySelector(".card.step");
    if (emptyEl) emptyEl.style.display = hasAny ? "none" : "block";
  }

  function renderValidatorError(resp) {
    const card = document.createElement("article");
    card.className = "card step error";
    const h = document.createElement("h3");
    h.textContent = tx("ui.validator_blocked");
    const pre = document.createElement("pre");
    pre.textContent = JSON.stringify(resp, null, 2);
    card.append(h, pre);
    cardsEl.prepend(card);
    ensureNonEmpty();
  }

  function renderGenericError() {
    const card = document.createElement("article");
    card.className = "card step error";
    const h = document.createElement("h3");
    h.textContent = tx("ui.error");
    cardsEl.prepend(card);
    ensureNonEmpty();
  }

  function makeList(items) {
    const ul = document.createElement("ul");
    if (!Array.isArray(items) || !items.length) {
      const li = document.createElement("li"); li.textContent = "—"; ul.appendChild(li);
      return ul;
    }
    items.forEach(it => {
      const li = document.createElement("li");
      li.textContent = typeof it === "string" ? it : JSON.stringify(it);
      ul.appendChild(li);
    });
    return ul;
  }

  function makeMemWritesTable(list) {
    const table = document.createElement("table");
    table.className = "memwrites";
    const thead = document.createElement("thead");
    thead.innerHTML = `<tr>
      <th>${esc(tx("ui.table.key"))}</th>
      <th>${esc(tx("ui.table.value"))}</th>
      <th>${esc(tx("ui.table.ttl"))}</th>
    </tr>`;
    const tbody = document.createElement("tbody");
    (list || []).forEach(row => {
      const tr = document.createElement("tr");
      tr.innerHTML = `<td>${esc(row.key ?? "—")}</td>
                      <td>${esc(cut(row.value ?? "—", 160))}</td>
                      <td>${esc(row.ttl_days ?? "—")}</td>`;
      tbody.appendChild(tr);
    });
    table.append(thead, tbody);
    return table;
  }

  function extraArgsDetails(type, args) {
    const known = new Set(knownFieldsByType[type] || []);
    const unknown = Object.keys(args||{}).filter(k => !known.has(k));
    if (!unknown.length) return null;
    const det = document.createElement("details");
    const sum = document.createElement("summary");
    sum.textContent = tx("ui.details");
    const pre = document.createElement("pre");
    const pruned = {};
    unknown.forEach(k => pruned[k] = args[k]);
    pre.textContent = JSON.stringify(pruned, null, 2);
    det.append(sum, pre);
    return det;
  }

  function renderStepCard(payload) {
    const { data, memory } = payload;

    const card = document.createElement("article");
    card.className = "card step";

    // Yläpalkki: plan_version
    const meta = document.createElement("div");
    meta.className = "meta";
    const ver = document.createElement("span");
    ver.className = "meta-pill";
    ver.textContent = (data && data.plan_version) ? data.plan_version : "—";
    meta.appendChild(ver);

    // Seuraava toimenpide
    const hAction = document.createElement("h3");
    hAction.textContent = tx("ui.headings.next_action");
    const pAction = document.createElement("p");
    pAction.className = "big";
    const na = data && data.next_action || null;
    pAction.textContent = humanizeAction(na);

    // Miksi näin?
    const hWhy = document.createElement("h4");
    hWhy.textContent = tx("ui.headings.why");
    const pWhy = document.createElement("p");
    pWhy.textContent = (data && data.reasoning_summary) ? data.reasoning_summary : "—";

    // Riskit
    const hRisks = document.createElement("h4");
    hRisks.textContent = tx("ui.headings.risks");
    const risks = makeList((data && data.risks) || []);

    // Valmis, kun…
    const hExit = document.createElement("h4");
    hExit.textContent = tx("ui.headings.exit");
    const exit = makeList((data && data.exit_criteria) || []);

    // Muistipäivitykset
    const hMw = document.createElement("h4");
    hMw.textContent = tx("ui.headings.memory_writes");
    const mw = makeMemWritesTable((data && data.memory_writes) || []);

    card.append(meta, hAction, pAction, hWhy, pWhy, hRisks, risks, hExit, exit, hMw, mw);

    // args extra (details)
    const det = extraArgsDetails(na && na.type, na && na.args);
    if (det) card.append(det);

    cardsEl.prepend(card);
    ensureNonEmpty();

    // Päivitä sessiomuisti tiivistelmä
    renderSessionMemory(memory);
  }

  function renderSessionMemory(mem) {
    const ul = $("sessionMemList");
    ul.innerHTML = "";
    if (!mem || typeof mem !== "object") {
      const li = document.createElement("li");
      li.textContent = "—";
      ul.appendChild(li);
      return;
    }
    const rows = Object.entries(mem).slice(0,10);
    if (!rows.length) {
      const li = document.createElement("li"); li.textContent = "—"; ul.appendChild(li); return;
    }
    rows.forEach(([k,v]) => {
      const li = document.createElement("li");
      li.innerHTML = `<strong>${esc(k)}</strong>: ${esc(cut(v, 140))}`;
      ul.appendChild(li);
    });
  }

  // --------- tapahtumat ----------
  function bindEvents() {
    $("btnPlanUI").addEventListener("click", async () => {
      const resp = await callApi("plan", payloadPlan());
      if (!resp) return renderGenericError();
      if (!resp.ok) return renderValidatorError(resp);
      renderStepCard(resp);
    });

    $("btnStepUI").addEventListener("click", async () => {
      const resp = await callApi("step", payloadStep());
      if (!resp) return renderGenericError();
      if (!resp.ok) return renderValidatorError(resp);
      renderStepCard(resp);
    });

    $("btnInterruptUI").addEventListener("click", async () => {
      const resp = await callApi("interrupt", payloadInterrupt());
      if (!resp) return renderGenericError();
      if (!resp.ok) return renderValidatorError(resp);
      renderStepCard(resp);
    });

    $("btnResetUI").addEventListener("click", () => {
      document.querySelectorAll(".card.step").forEach(c => c.remove());
      ensureNonEmpty();
    });

    // kielenvaihto
    $("langSel").addEventListener("change", async (e) => {
      const lang = e.target.value;
      sessionStorage.setItem("lang", lang);
      await I18N.load(lang);
      I18N.apply();
      // Huom: jo renderöidyt kortit eivät käänny takautuvasti (voin lisätä tämän, jos haluat).
      checkKeyStatus(); // päivitä badge-teksti valitulla kielellä
    });
  }

  // --------- init ----------
  (async function init(){
    const lang = I18N.resolveLang();
    await I18N.load(lang);
    I18N.apply();
    bindEvents();
    ensureNonEmpty();
    checkKeyStatus();
  })();
})();
