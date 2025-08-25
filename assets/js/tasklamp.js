/* TaskLamp Frontend (no inline JS). WCAG 2.1, i18n, Focus UI, OSM-linkitys, Paniikkinappi. */
(() => {
  "use strict";

  // ---------- Utilities ----------
  const $ = (sel, el = document) => el.querySelector(sel);
  const $$ = (sel, el = document) => [...el.querySelectorAll(sel)];
  const qs = new URLSearchParams(location.search);

  // ---------- Spinner ----------
  function showSpinner(on = true) {
    const sp = $("#spinner");
    const main = $("#main");
    if (sp) sp.hidden = !on;
    if (main) main.setAttribute("aria-busy", on ? "true" : "false");
    document.body.classList.toggle("is-loading", !!on);
    // Lukitaan interaktiot latauksen ajaksi (paniikkinappi jätetään toimimaan)
    $$("button, input, textarea, select").forEach(el => {
      if (el.id !== "panic") el.disabled = !!on;
    });
  }

  // Hiljennä kolmansien osapuolien "Uncaught (in promise)" -hälyt, loggaa siististi
  window.addEventListener("unhandledrejection", (e) => {
    const msg = String(e.reason?.message || e.reason || "");
    if (msg.includes("A listener indicated an asynchronous response")) {
      e.preventDefault();
      console.debug("[ext]", msg);
    }
  });

  // ---------- i18n ----------
  const I18N = {
    lang: "fi",
    dict: {},
    async load(lang) {
      const code = ["fi","en","sv"].includes(lang) ? lang : "fi";
      try {
        const res = await fetch(`/assets/i18n/${code}.json`, { cache: "no-store" });
        if (!res.ok) throw new Error(`i18n ${code}.json HTTP ${res.status}`);
        this.dict = await res.json();
        this.lang = code;
      } catch (e) {
        console.error("i18n load failed:", e);
        this.dict = {};
        this.lang = "fi";
      }
    },
    t(key, fallback = "") {
      const parts = key.split(".");
      let node = this.dict;
      for (const p of parts) {
        if (!node || typeof node !== "object") return fallback;
        node = node[p];
      }
      return (typeof node === "string") ? node : fallback;
    },
    apply() {
      document.documentElement.lang = this.lang;
      $$("[data-i18n]").forEach(el => {
        const k = el.getAttribute("data-i18n");
        const txt = this.t(k, el.textContent);
        if (typeof txt === "string" && txt.length) el.textContent = txt;
      });
      [
        ["#country","tasklamp.form.countryPh"],
        ["#city","tasklamp.form.cityPh"],
        ["#task","tasklamp.form.taskPh"]
      ].forEach(([sel, key]) => {
        const el = $(sel);
        if (el) el.setAttribute("placeholder", this.t(key, el.getAttribute("placeholder") || ""));
      });
      const langSel = $("#lang");
      if (langSel) langSel.setAttribute("aria-label", this.t("tasklamp.ui.language","Language"));
    }
  };

  // ---------- OSM ----------
  const osmSearchLink = (q) => `https://www.openstreetmap.org/search?query=${encodeURIComponent(q)}`;

  // ---------- Paniikkinappi ----------
  function panicWipe() {
    try {
      localStorage.clear();
      sessionStorage.clear();
      const url = new URL(location.href);
      url.search = "";
      history.replaceState({}, "", url.toString());
    } catch(e){}
    $("#task-form")?.reset();
    $("#step-view")?.replaceChildren();
    $("#step-list")?.replaceChildren();
    $("#all-steps")?.setAttribute("hidden","hidden");
    $(".tl-card--result")?.setAttribute("hidden","hidden");
    // Piilota fraasit kokonaan
    const phrCard = $(".tl-card--phrases");
    if (phrCard) phrCard.hidden = true;
  }

  // ---------- State ----------
  const State = {
    steps: [],
    idx: 0,
    planMeta: null, // esim. needs_clarification, followup_prompt
    lastTaskText: "",
    setSteps(steps) {
      this.steps = Array.isArray(steps) ? steps : [];
      this.idx = 0;
    },
    current() {
      if (!this.steps.length) return null;
      return this.steps[this.idx] || null;
    }
  };

  // ---------- Render ----------
  function renderCurrentStep() {
    const container = $("#step-view");
    if (!container) return;
    container.replaceChildren();

    const step = State.current();
    const prevBtn = $("#btn-prev");
    const nextBtn = $("#btn-next");

    prevBtn.disabled = State.idx <= 0;
    nextBtn.disabled = State.idx >= (State.steps.length - 1);

    if (!step) return;

    const wrap = document.createElement("article");
    wrap.className = "step-card";
    wrap.setAttribute("aria-label", `Step ${step.order || (State.idx+1)}`);

    const h = document.createElement("h4");
    h.textContent = step.title || I18N.t("tasklamp.step.untitled","Toimi seuraavasti");
    wrap.appendChild(h);

    const p = document.createElement("p");
    p.textContent = step.action || "";
    wrap.appendChild(p);

    const meta = document.createElement("ul");
    meta.className = "meta";

    if (step.url) {
      const li = document.createElement("li");
      const a = document.createElement("a");
      a.href = step.url;
      a.target = "_blank";
      a.rel = "noopener";
      a.textContent = I18N.t("tasklamp.step.openLink","Avaa linkki");
      li.appendChild(a);
      meta.appendChild(li);
    }

    if (step.deadline) {
      const li = document.createElement("li");
      li.textContent = I18N.t("tasklamp.step.deadline","Määräaika") + ": " + step.deadline;
      meta.appendChild(li);
    }

    if (step.attachments_hint) {
      const li = document.createElement("li");
      li.textContent = I18N.t("tasklamp.step.attach","Liitteet") + ": " + step.attachments_hint;
      meta.appendChild(li);
    }

    if (step.print_hint) {
      const li = document.createElement("li");
      li.textContent = I18N.t("tasklamp.step.print","Tulostus") + ": " + step.print_hint;
      meta.appendChild(li);
    }

    if (step.submit_place || step.submit_method) {
      const li = document.createElement("li");
      const parts = [];
      if (step.submit_place) parts.push(step.submit_place);
      if (step.submit_method) parts.push(step.submit_method);
      li.textContent = I18N.t("tasklamp.step.submit","Jättäminen") + ": " + parts.join(" · ");
      meta.appendChild(li);
    }

    if (step.verify_flag) {
      const li = document.createElement("li");
      li.textContent = I18N.t("tasklamp.step.verify","Varmista tiedot viranomaiselta.");
      li.setAttribute("aria-live","polite");
      meta.appendChild(li);
    }

    if (meta.children.length) wrap.appendChild(meta);
    container.appendChild(wrap);
  }

  function renderAllSteps() {
    const details = $("#all-steps");
    const list = $("#step-list");
    if (!details || !list) return;
    list.replaceChildren();
    State.steps.forEach((s) => {
      const li = document.createElement("li");
      const line = [s.title, s.action].filter(Boolean).join(" — ");
      li.textContent = line || I18N.t("tasklamp.step.untitled","Toimi seuraavasti");
      if (s.url) {
        const a = document.createElement("a");
        a.href = s.url; a.target="_blank"; a.rel="noopener";
        a.textContent = ` (${I18N.t("tasklamp.step.openLink","Avaa linkki")})`;
        li.appendChild(a);
      }
      list.appendChild(li);
    });
    details.hidden = false;
  }

  // ---------- Fraasit: näytä vain terveysaiheissa ----------
  const HEALTH_KEYWORDS = [
    "terveys","terveydenhuolto","sairaala","klinikka","ajanvaraus","resepti","lääkäri",
    "health","doctor","clinic","hospital","appointment","prescription","gp","emergency"
  ];
  function looksHealthRelated(text) {
    const t = (text || "").toLowerCase();
    return HEALTH_KEYWORDS.some(k => t.includes(k));
  }
  function populatePhrasesConditional() {
    const card = $(".tl-card--phrases");
    if (!card) return;
    const shouldShow = looksHealthRelated(State.lastTaskText);
    card.hidden = !shouldShow;
    if (!shouldShow) {
      $("#phrases-list")?.replaceChildren();
      return;
    }
    const list = $("#phrases-list");
    if (!list) return;
    list.replaceChildren();
    const phrases = I18N.t("tasklamp.phrases", []);
    if (Array.isArray(phrases)) {
      phrases.forEach(txt => {
        const li = document.createElement("li");
        li.textContent = txt;
        list.appendChild(li);
      });
    }
  }

  // ---------- Network ----------
  async function createPlan(payload) {
    const res = await fetch("/search/tasklamp.php", {
      method: "POST",
      headers: {"Content-Type":"application/json"},
      body: JSON.stringify(payload)
    });
    const isJson = res.headers.get("content-type")?.includes("application/json");
    if (!res.ok) {
      const msg = isJson ? ((await res.json()).error || `HTTP ${res.status}`) : `HTTP ${res.status}`;
      throw new Error(msg);
    }
    return isJson ? res.json() : { steps: [], needs_clarification: true, followup_prompt: "Server returned non-JSON." };
  }

  // ---------- Events ----------
  async function init() {
    // Kieli: URL ?lang => localStorage => navigator
    const urlLang = qs.get("lang");
    const stored = localStorage.getItem("tasklamp.lang");
    const browser = (navigator.language || "fi").slice(0,2).toLowerCase();
    const lang = urlLang || stored || (["fi","en","sv"].includes(browser) ? browser : "fi");

    showSpinner(true);
    await I18N.load(lang);
    I18N.apply();
    showSpinner(false);

    // Piilota fraasit aluksi aina
    const phrCard = $(".tl-card--phrases");
    if (phrCard) phrCard.hidden = true;

    // Aseta valintalistaan
    const langSel = $("#lang");
    if (langSel) langSel.value = I18N.lang;

    // Kielen vaihto
    langSel?.addEventListener("change", async (e) => {
      showSpinner(true);
      const v = e.target.value;
      await I18N.load(v);
      I18N.apply();
      localStorage.setItem("tasklamp.lang", I18N.lang);
      const url = new URL(location.href);
      url.searchParams.set("lang", I18N.lang);
      history.replaceState({}, "", url.toString());
      // Päivitä fraasit (jos näkyvissä)
      populatePhrasesConditional();
      showSpinner(false);
    });

    // Paniikkinappi
    $("#panic")?.addEventListener("click", () => {
      if (confirm(I18N.t("tasklamp.ui.panicConfirm","Tyhjennetäänkö kaikki paikallinen historia ja kentät?"))) {
        panicWipe();
        alert(I18N.t("tasklamp.ui.panicDone","Tyhjennetty."));
      }
    });

    // Tyhjennä kentät
    $("#btn-clear")?.addEventListener("click", () => $("#task-form").reset());

    // Navigointi
    $("#btn-prev")?.addEventListener("click", () => { if (State.idx>0){ State.idx--; renderCurrentStep(); } });
    $("#btn-next")?.addEventListener("click", () => { if (State.idx < State.steps.length-1){ State.idx++; renderCurrentStep(); } });
    $("#btn-all")?.addEventListener("click", renderAllSteps);
    $("#btn-print")?.addEventListener("click", () => window.print());
    $("#btn-copy")?.addEventListener("click", async () => {
      const lines = State.steps.map(s => `• ${s.title}\n  ${s.action}${s.url?`\n  ${s.url}`:""}`).join("\n\n");
      try { await navigator.clipboard.writeText(lines); alert(I18N.t("tasklamp.ui.copied","Kopioitu leikepöydälle.")); } catch(e){}
    });

    // Follow-up lisätieto
    $("#followup-form")?.addEventListener("submit", async (e) => {
      e.preventDefault();
      const follow = $("#followup-input").value.trim();
      if (!follow) return;
      const payload = JSON.parse(sessionStorage.getItem("tasklamp.lastPayload") || "{}");
      payload.followup = follow;
      showSpinner(true);
      try {
        await handlePlanRequest(payload);
        populatePhrasesConditional();
      } finally {
        showSpinner(false);
      }
    });

    // Lähetä pyyntö
    let submitting = false;
    $("#task-form")?.addEventListener("submit", async (e) => {
      e.preventDefault();
      if (submitting) return;
      const payload = {
        lang: $("#lang").value,
        country: $("#country").value.trim(),
        city: $("#city").value.trim(),
        task: $("#task").value.trim()
      };
      submitting = true;
      showSpinner(true);
      try {
        State.lastTaskText = payload.task || "";
        await handlePlanRequest(payload);
        populatePhrasesConditional(); // näytä fraasit vain jos terveysaihe
      } finally {
        submitting = false;
        showSpinner(false);
      }
    });
  }

  async function handlePlanRequest(payload) {
    // Validointi: maa ja tehtävä pakolliset
    if (!payload.country || !payload.task) {
      alert(I18N.t("tasklamp.ui.formError","Täytä vähintään maa ja tehtävä."));
      return;
    }
    sessionStorage.setItem("tasklamp.lastPayload", JSON.stringify(payload));
    $(".tl-card--result").hidden = false;
    $("#followup").hidden = true;

    try {
      const data = await createPlan(payload);
      const steps = Array.isArray(data?.steps) ? data.steps : [];
      State.setSteps(steps);
      renderCurrentStep();

      $("#all-steps").hidden = true;
      $("#step-list").replaceChildren();

      if (data?.needs_clarification) {
        $("#followup-text").textContent = data.followup_prompt || I18N.t("tasklamp.ui.followupAsk","Tarvitsisimme lisätiedon jatkaaksemme.");
        $("#followup").hidden = false;
        $("#followup-input").focus();
      }
    } catch (err) {
      console.error(err);
      alert(I18N.t("tasklamp.ui.fetchError","Suunnitelmaa ei voitu luoda juuri nyt. Yritä hetken päästä uudelleen.") + (err?.message ? `\n\n(${err.message})` : ""));
    }
  }

  // Init
  document.addEventListener("DOMContentLoaded", init);
})();
