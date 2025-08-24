const basicView = document.getElementById('basic-view');
const proView   = document.getElementById('pro-view');
const btnBasic  = document.getElementById('btn-basic');
const btnPro    = document.getElementById('btn-pro');
const resultsDiv= document.getElementById('results');
const langSel   = document.getElementById('lang');
const govOnlyEl = document.getElementById('govOnly');
// Näkymän vaihto
btnBasic.addEventListener('click', () => {
  basicView.style.display = 'block';
  proView.style.display   = 'none';
  btnBasic.classList.replace('btn-outline-primary','btn-primary');
  btnPro.classList.replace('btn-primary','btn-outline-primary');
});
btnPro.addEventListener('click', () => {
  basicView.style.display = 'none';
  proView.style.display   = 'block';
  btnPro.classList.replace('btn-outline-primary','btn-primary');
  btnBasic.classList.replace('btn-primary','btn-outline-primary');
});

// Perus-näkymän painikkeet
document.querySelectorAll('.basic-btn').forEach(btn => {
  btn.addEventListener('click', () => doSearch(btn.textContent.trim()));
});

// Pro-näkymä
document.getElementById('search-btn').addEventListener('click', () => {
  const q = document.getElementById('query').value.trim();
  if (q) doSearch(q);
});
document.getElementById('query').addEventListener('keydown', e => {
  if (e.key === 'Enter') {
    e.preventDefault();
    const q = e.currentTarget.value.trim();
    if (q) doSearch(q);
  }
});

// Turvallinen fetch + robusti parseri
async function doSearch(q){
  resultsDiv.innerHTML = '<div class="alert alert-secondary text-center">Haetaan…</div>';
  try{
const API = '/search/answer.php';
const res = await fetch(API, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ 
        q, 
        lang: langSel?.value || 'fi', 
        gov_only: !!govOnlyEl?.checked 
    })
});


    const ct = res.headers.get('content-type') || '';
    if (!res.ok) {
      const txt = await res.text();
      throw new Error(`HTTP ${res.status} – ${txt.slice(0,200)}`);
    }

    let data;
    if (ct.includes('application/json')) {
      data = await res.json();
    } else {
      const txt = await res.text();
      throw new Error(`Palasi ei-JSON (Content-Type: ${ct}): ${txt.slice(0,200)}`);
    }

    renderAnswer(data);
  } catch (err){
    resultsDiv.innerHTML = `<div class="alert alert-danger">Virhe haussa: ${escapeHtml(String(err))}</div>`;
  }
}

function renderAnswer(d){
  // d: { answer_html, plain_html, citations, followups }
  const answerHtml = d?.answer_html || d?.plain_html || '';
  const cites = Array.isArray(d?.citations) ? d.citations : [];
  const follow = Array.isArray(d?.followups) ? d.followups : [];

  resultsDiv.innerHTML = `
    <div class="card result-card">
      <div class="card-body">
        <div class="mb-3">${answerHtml || '<span class="text-muted">Ei vastausta</span>'}</div>
        ${cites.length ? renderCitations(cites) : '<div class="text-muted">Ei lähteitä.</div>'}
        ${follow.length ? renderFollowups(follow) : ''}
      </div>
    </div>
  `;
}

function renderCitations(cites){
  const items = cites.map(c => `
    <li class="mb-1">
      <a href="${c.url}" target="_blank" rel="noopener">${escapeHtml(c.title || c.url)}</a>
    </li>`).join('');
  return `<div><strong>Lähteet</strong><ol class="mt-2">${items}</ol></div>`;
}
function renderFollowups(f){
  const items = f.map(x => `<li>${escapeHtml(x)}</li>`).join('');
  return `<div class="mt-3"><strong>Jatkokysymykset</strong><ul class="mt-2">${items}</ul></div>`;
}
function escapeHtml(s){ return s.replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[m] )); }