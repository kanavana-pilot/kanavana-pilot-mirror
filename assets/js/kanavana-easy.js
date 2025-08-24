 const answerEl = document.getElementById('answer');
  const sourcesEl = document.getElementById('sources');
  const historyEl = document.getElementById('history');
  const copyBtn = document.getElementById('copyBtn');
  const govOnlyEl = document.getElementById('govOnly');
  const modeNormal = document.getElementById('modeNormal');
  const modeSelko  = document.getElementById('modeSelko');

  let viewMode = 'normal';
  const HISTORY_KEY = 'kanavana_easy_hist_v1';
  let history = loadHistory();

  function loadHistory(){ try{ return JSON.parse(localStorage.getItem(HISTORY_KEY)||'[]'); }catch{ return []; } }
  function saveHistory(){ localStorage.setItem(HISTORY_KEY, JSON.stringify(history)); }
  function pushHistory(item){ history.unshift(item); if(history.length>25) history.pop(); saveHistory(); renderHistory(); }
  function popHistory(){ const it = history.shift(); saveHistory(); renderHistory(); return it; }
  function renderHistory(){
    historyEl.innerHTML = history.map((h,i)=>`
      <li style="margin-bottom:8px"><button class="tag btn" data-i="${i}">
        ${escapeHtml(h.q).slice(0,60)}${h.q.length>60?'…':''}
      </button></li>
    `).join('') || `<div class="mut">Ei vielä kysymyksiä.</div>`;
  }
  function escapeHtml(s){ return s.replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
  function renderSources(citations){
    if (citations?.length){
      sourcesEl.innerHTML = `<div class="mut">Lähteet</div><ol>${
        citations.map(c=>`<li><a href="${c.url}" target="_blank" rel="noopener noreferrer">${escapeHtml(c.title||c.url)}</a></li>`).join('')
      }</ol>`;
    } else sourcesEl.innerHTML = `<div class="mut">Ei lähteitä.</div>`;
  }
  function setMode(mode){
    viewMode = mode;
    modeNormal.setAttribute('aria-pressed', mode==='normal');
    modeSelko.setAttribute('aria-pressed', mode==='selko');
    answerEl.classList.toggle('selko', mode==='selko');
    const cur = history[0];
    if(cur){ answerEl.innerHTML = mode==='selko' ? cur.plain_html : cur.answer_html; renderSources(cur.citations); }
  }

  async function ask(q, lang='fi'){
    if(!q) return;
    answerEl.classList.remove('mut');
    answerEl.innerHTML = `<span class="spinner"></span> Haetaan ja kootaan vastausta…`;
    sourcesEl.innerHTML = '';
    try{
      const res = await fetch('/search/answer.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ q, lang, gov_only: !!govOnlyEl.checked })
      });
      const j = await res.json();
      const item = { q, lang, answer_html: j.answer_html||'', plain_html: j.plain_html||'', citations: j.citations||[], followups: j.followups||[] };
      pushHistory(item);
      answerEl.innerHTML = (viewMode==='selko'? item.plain_html : item.answer_html) || 'Ei vastausta';
      renderSources(item.citations);
    }catch(e){
      answerEl.innerHTML = `<div class="card warn">Tapahtui virhe palvelukutsussa. Kokeile hetken päästä uudelleen.</div>`;
    }
  }

  document.querySelectorAll('.chip').forEach(chip=>{
    chip.addEventListener('click', ()=> ask(chip.dataset.q, 'fi'));
  });
  document.getElementById('undoBtn').addEventListener('click', ()=>{
    if(!history.length) return;
    popHistory();
    const prev = history[0];
    if(prev){ answerEl.innerHTML = viewMode==='selko'? prev.plain_html : prev.answer_html; renderSources(prev.citations); }
    else { answerEl.classList.add('mut'); answerEl.textContent='Valitse aihe yllä.'; sourcesEl.innerHTML=''; }
  });
  document.getElementById('clearBtn').addEventListener('click', ()=>{
    history = []; saveHistory(); renderHistory();
    answerEl.classList.add('mut'); answerEl.textContent='Valitse aihe yllä.'; sourcesEl.innerHTML='';
  });
  modeNormal.addEventListener('click', ()=> setMode('normal'));
  modeSelko .addEventListener('click', ()=> setMode('selko'));
  copyBtn.addEventListener('click', async ()=>{
    const temp = document.createElement('div'); temp.innerHTML = answerEl.innerHTML;
    const text = temp.textContent || temp.innerText || '';
    try{ await navigator.clipboard.writeText(text.trim()); copyBtn.textContent='Kopioitu!'; setTimeout(()=>copyBtn.textContent='Kopioi vastaus',1200);}catch{}
  });

  renderHistory(); setMode('normal');