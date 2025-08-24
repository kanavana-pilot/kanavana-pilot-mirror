let swiper;
let sessionId = null;
let state = { turns:[], tone:'neutral', length:'short', audience:'general', formats:[] };

window.addEventListener('DOMContentLoaded', () => {
  swiper = new Swiper('.swiper', { allowTouchMove:false, pagination: { el: '.swiper-pagination', clickable:false } });
  document.getElementById('startBtn').onclick = startFlow;
  document.getElementById('next1').onclick = () => refine(1);
  document.getElementById('next2').onclick = () => refine(2);
  document.getElementById('makeSummary').onclick = makeSummary;
  document.getElementById('execute').onclick = executeFinal;
  document.getElementById('copyFinal').onclick = copyFinal;
  document.getElementById('share').onclick = sharePrompt;

  document.querySelectorAll('input[name="tone"]').forEach(r => r.onchange = e => state.tone = e.target.value);
  document.querySelectorAll('input[name="length"]').forEach(r => r.onchange = e => state.length = e.target.value);
  document.getElementById('audience').onchange = e => state.audience = e.target.value;
  ['format_list','format_markdown','format_sources'].forEach(id=>{
    document.getElementById(id).onchange = collectFormats;
  });
 // Prefill → täytä tekstikenttä ja käynnistä heti
const params = new URLSearchParams(location.search);
const prefill = params.get('prefill');
if (prefill && prefill.trim().length > 0) {
  const blob = document.getElementById('blob');
  blob.value = prefill.trim();
  // pieni viive että UI ehtii piirtyä ennen starttia
  setTimeout(() => {
    // estetään tuplaklikki jos käyttäjä painaa heti nappia
    document.getElementById('startBtn').disabled = true;
    startFlow().finally(() => {
      document.getElementById('startBtn').disabled = false;
      // tyhjennetään querystring (siisti URL)
      history.replaceState(null, '', location.pathname);
    });
  }, 150);
}
// Eiku-napit → ohjaa käyttäjän Eiku-sivulle
const e1 = document.getElementById('eiku1');
const e2 = document.getElementById('eiku2');
const e3 = document.getElementById('eiku3');
if (e1) e1.onclick = () => eiku(1);
if (e2) e2.onclick = () => eiku(1);
if (e3) e3.onclick = () => eiku(1);
 
  collectFormats();
});

function collectFormats(){
  state.formats = [];
  if (document.getElementById('format_list').checked) state.formats.push('list');
  if (document.getElementById('format_markdown').checked) state.formats.push('markdown');
  if (document.getElementById('format_sources').checked) state.formats.push('sources');
}

async function startFlow(){
  clearErr('startErr');
  const text = document.getElementById('blob').value.trim();
  if (!text) { setErr('startErr','Kirjoita ensin ajatuksesi.'); return; }
  const r = await post(API.start, { text });
  if (!r.ok){ setErr('startErr', r.error||'Virhe'); return; }
  sessionId = r.sessionId;
  document.getElementById('sum1').textContent = r.summary;
  document.getElementById('q1label').textContent = r.question;
  gsap.from('#sum1', { y:10, opacity:0, duration:.4 });
  swiper.slideNext();
}

async function refine(step){
  clearErr(`refErr${step}`);
  const ans = document.getElementById(`a${step}`).value.trim();
  if (!ans){ setErr(`refErr${step}`,'Vastaa lyhyesti.'); return; }
  const r = await post(API.refine, { sessionId, answer: ans });
  if (!r.ok){ setErr(`refErr${step}`, r.error||'Virhe'); return; }
  if (step === 1){
    document.getElementById('sum2').textContent = r.summary;
    document.getElementById('q2label').textContent = r.question;
    swiper.slideNext();
  } else {
    swiper.slideNext();
  }
}

async function makeSummary(){
  clearErr('sumErr');
  const r = await post(API.summary, { sessionId, tone:state.tone, length:state.length, audience:state.audience, formats: JSON.stringify(state.formats) });
  if (!r.ok){ setErr('sumErr', r.error||'Virhe'); return; }
  document.getElementById('orig').textContent = r.original;
  document.getElementById('final').textContent = r.finalPrompt;
  swiper.slideNext();
}

async function executeFinal(){
  clearErr('execErr');
  setOut('execOut','Lasketaan vastausta…');
  const r = await post(API.execute, { sessionId });
  if (!r.ok){ setErr('execErr', r.error||'Virhe'); setOut('execOut',''); return; }
  setOut('execOut', r.answer || '(tyhjä vastaus)');
}

async function sharePrompt(){
  const r = await post(API.share, { sessionId });
  if (r.ok && r.url) document.getElementById('shareOut').textContent = `Jako-osoite: ${r.url}`;
}

function setOut(id, val){ document.getElementById(id).textContent = val; }
function setErr(id, msg){ document.getElementById(id).textContent = msg; }
function clearErr(id){ setErr(id,''); }
async function post(url, data){
  const form = new URLSearchParams(data);
  const res = await fetch(url, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:form });
  const txt = await res.text();
  try { return JSON.parse(txt); }
  catch (e) { return { ok:false, error:`HTTP ${res.status} – non‑JSON`, raw: txt.slice(0,500) }; }
}


async function copyFinal(){
  const t = document.getElementById('final').textContent;
  await navigator.clipboard.writeText(t);
}
async function eiku(steps=1){
  if (!sessionId) return;
  const r = await post(API.rewind, { sessionId, steps });
  if (!r.ok){
    // näytä virhe siihen näkymään missä todennäköisesti ollaan
    setErr('refErr1', r.error || 'Eiku-virhe');
    return;
  }
  if (r.toStart){
    // takaisin alkuun: esitäytä blob ja palaa slideen 1
    document.getElementById('blob').value = r.original || '';
    swiper.slideTo(0); // ensimmäinen slide (indeksi 0)
    return;
  }
  // palaamme tiettyyn kysymykseen
  if (r.step === 1){
    document.getElementById('sum1').textContent = r.summary || '';
    document.getElementById('q1label').textContent = r.question || '';
    document.getElementById('a1').value = r.prefill || '';
    swiper.slideTo(1); // toinen slide
  } else { // step >= 2
    document.getElementById('sum2').textContent = r.summary || '';
    document.getElementById('q2label').textContent = r.question || '';
    document.getElementById('a2').value = r.prefill || '';
    swiper.slideTo(2); // kolmas slide
  }
}

