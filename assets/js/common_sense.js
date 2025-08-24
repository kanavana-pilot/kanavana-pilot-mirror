(() => {
  const outEl = document.getElementById('out');
  const memEl = document.getElementById('mem');
  const logEl = document.getElementById('log');

  const $ = (id) => document.getElementById(id);
  const safeJson = (txt) => { try { return JSON.parse(txt); } catch(e){ return {}; } };

  async function call(action, payload){
    const r = await fetch(`./common_sense.php?action=${encodeURIComponent(action)}`, {
      method:"POST",
      headers:{"Content-Type":"application/json"},
      body: JSON.stringify(payload)
    });
    let data = null;
    try { data = await r.json(); } catch(e) {}
    if(!data){
      outEl.textContent = "Error: invalid response";
      return;
    }
    if(!data.ok){
      outEl.textContent = "VALIDATOR BLOCKED:\n" + JSON.stringify(data, null, 2);
    }else{
      outEl.textContent = JSON.stringify(data.data, null, 2);
      memEl.textContent = JSON.stringify(data.memory || {}, null, 2);
      // Päivitä loki refrešaamalla pelkkä UI:n lokilohko
      fetch('./common_sense.php').then(r=>r.text()).then(html=>{
        const m = html.match(/<pre id="log">([\s\S]*?)<\/pre>/);
        if(m){
          const cleaned = m[1]
            .replace(/&quot;/g,'"')
            .replace(/&lt;/g,'<')
            .replace(/&gt;/g,'>')
            .replace(/&amp;/g,'&');
          logEl.textContent = cleaned;
        }
      });
    }
  }

  function payloadPlan(){
    return {
      goal: $('goal').value || "Put mixed stack of dishes into cabinets",
      context: safeJson($('context').value),
      guardrails: ["no_knife_with_others","no_personal_data_leak"]
    };
  }
  function payloadStep(){
    return {
      last_state: {"holding":"bowl","obstacles":["cabinet_full"],"inventory":["knife"]},
      observation: "Cabinet full of stacked bowls",
      feedback: null
    };
  }
  function payloadInterrupt(){
    return {
      reason: "Safety",
      details: "Holding knife and bowl simultaneously",
      proposed_fix_needed: true
    };
  }

  $('btnPlan').addEventListener('click', ()=> call('plan', payloadPlan()));
  $('btnStep').addEventListener('click', ()=> call('step', payloadStep()));
  $('btnInterrupt').addEventListener('click', ()=> call('interrupt', payloadInterrupt()));
  $('btnReload').addEventListener('click', ()=> location.reload());
})();
