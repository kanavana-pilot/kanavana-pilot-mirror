  // ----- Elementit -----
    const qEl = document.getElementById('q');
    const langEl = document.getElementById('lang');
    const askBtn = document.getElementById('askBtn');
    const undoBtn = document.getElementById('undoBtn');
    const clearBtn = document.getElementById('clearBtn');
    const answerEl = document.getElementById('answer');
    const sourcesEl = document.getElementById('sources');
    const historyEl = document.getElementById('history');
    const copyBtn = document.getElementById('copyBtn');
    const modeNormalBtn = document.getElementById('modeNormal');
    const modeSelkoBtn = document.getElementById('modeSelko');

    // ----- Tila -----
    const HISTORY_KEY = 'kanavana_history_v3';
    let history = loadHistory();
    let viewMode = 'normal'; // 'normal' | 'selko'

    // ----- Apu -----
    function loadHistory(){ try { return JSON.parse(localStorage.getItem(HISTORY_KEY) || '[]'); } catch { return []; } }
    function saveHistory(){ localStorage.setItem(HISTORY_KEY, JSON.stringify(history)); }
    function pushHistory(item){ history.unshift(item); if (history.length>25) history.pop(); saveHistory(); renderHistory(); }
    function popHistory(){ const it = history.shift(); saveHistory(); renderHistory(); return it; }
    function renderHistory(){
      historyEl.innerHTML = history.map((h,i)=>`
        <li style="margin-bottom:8px">
          <button class="pill" data-i="${i}" title="Avaa">${escapeHtml(h.q).slice(0,72)}${h.q.length>72?'…':''}</button>
        </li>`).join('') || '<div class="muted small">Ei vielä kysymyksiä.</div>';
    }
    function escapeHtml(s){ return s.replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
    function renderSources(citations){
      if (citations && citations.length){
        sourcesEl.innerHTML = `<div class="muted">Lähteet</div><ol>${
          citations.map(c=>`<li><a href="${c.url}" target="_blank" rel="noopener noreferrer">${escapeHtml(c.title||c.url)}</a></li>`).join('')
        }</ol>`;
      } else sourcesEl.innerHTML = `<div class="muted">Ei lähteitä.</div>`;
    }
    function setMode(mode){
      viewMode = mode;
      modeNormalBtn.setAttribute('aria-pressed', mode==='normal');
      modeSelkoBtn.setAttribute('aria-pressed', mode==='selko');
      answerEl.classList.toggle('selko', mode==='selko');
      const cur = history[0];
      if (cur){ answerEl.innerHTML = mode==='selko' ? cur.plain_html : cur.answer_html; renderSources(cur.citations); }
    }

    // ----- Haku -----
    async function ask(q, lang){
      if (!q) return;
      answerEl.classList.remove('muted');
      answerEl.innerHTML = `<span class="spinner"></span> Haetaan ja kootaan vastausta…`;
      sourcesEl.innerHTML = '';

      try{
        const res = await fetch('/search/answer.php', {
          method:'POST',
          headers:{'Content-Type':'application/x-www-form-urlencoded'},
          body: new URLSearchParams({ q, lang })
        });
        const j = await res.json();

        const item = { q, lang,
          answer_html: j.answer_html || '',
          plain_html:  j.plain_html  || '',
          citations:   j.citations   || [],
          followups:   j.followups   || []
        };
        pushHistory(item);

        answerEl.innerHTML = (viewMode==='selko' ? item.plain_html : item.answer_html) || 'Ei vastausta';
        renderSources(item.citations);

      }catch(e){
        answerEl.innerHTML = `<div class="card warn">Tapahtui virhe palvelukutsussa. Kokeile hetken päästä uudelleen.</div>`;
      }
    }

    // ----- Tapahtumat -----
    askBtn.onclick = ()=> ask(qEl.value.trim(), langEl.value);
    qEl.addEventListener('keydown', e=>{ if(e.key==='Enter'){ e.preventDefault(); ask(qEl.value.trim(), langEl.value); }});
    document.querySelectorAll('.examples button').forEach(b=>{
      b.onclick = ()=>{ qEl.value = b.dataset.q; ask(qEl.value.trim(), langEl.value); };
    });
    historyEl.addEventListener('click', e=>{
      const btn = e.target.closest('button[data-i]'); if(!btn) return;
      const i = +btn.dataset.i, h = history[i]; if(!h) return;
      qEl.value = h.q; answerEl.innerHTML = viewMode==='selko' ? h.plain_html : h.answer_html; renderSources(h.citations);
    });
    undoBtn.onclick = ()=>{
      if (!history.length) return;
      popHistory();
      const prev = history[0];
      if (prev){ qEl.value = prev.q; answerEl.innerHTML = viewMode==='selko' ? prev.plain_html : prev.answer_html; renderSources(prev.citations); }
      else { answerEl.classList.add('muted'); answerEl.textContent='Kysytään kun olet valmis…'; sourcesEl.innerHTML=''; }
    };
    clearBtn.onclick = ()=>{
      history = []; saveHistory(); renderHistory();
      answerEl.classList.add('muted'); answerEl.textContent='Kysytään kun olet valmis…'; sourcesEl.innerHTML='';
    };
    modeNormalBtn.onclick = ()=> setMode('normal');
    modeSelkoBtn.onclick  = ()=> setMode('selko');
    copyBtn.onclick = async ()=>{
      const temp = document.createElement('div'); temp.innerHTML = answerEl.innerHTML;
      const text = temp.textContent || temp.innerText || '';
      try{ await navigator.clipboard.writeText(text.trim()); copyBtn.textContent='Kopioitu!'; setTimeout(()=>copyBtn.textContent='Kopioi',1200);}catch{}
    };

    // Init
    renderHistory(); setMode('normal');