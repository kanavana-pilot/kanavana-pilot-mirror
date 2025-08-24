<?php
// spark.php
?>
<!doctype html>
<html lang="fi">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Heräteareena – Eiku Prompt</title>
<link rel="stylesheet" href="public/app.css">
<style>
body { background:#0f1115; color:#fff; display:flex; align-items:center; justify-content:center; height:100vh; flex-direction:column; text-align:center; padding:1rem; }
h1 { font-size:2rem; margin-bottom:1rem; }
small { font-size:1.1rem; margin-bottom:1rem; display:block;}
#trigger { font-size:1.5rem; margin-bottom:2rem; min-height:3rem; max-width:800px; }
button { font-size:1.2rem; padding:10px 20px; border-radius:8px; background:#5b8cff; color:#fff; border:none; cursor:pointer; }
#ideas { margin-top:2rem; opacity:.9; font-size:0.95rem; max-width:800px; }
#ideas span { display:inline-block; margin:0.2rem 0.4rem; padding:0.4rem 0.6rem; background:#1a1f2a; border-radius:6px; cursor:pointer; }
</style>
</head>
<body>
  <h1>Mikä polttelee mielessä? <small>Mitä tarkemmin kysyt, sitä vähemmän hämmennystä – sinulle ja meille.</small></h1>
  <div id="trigger"></div>
  <button id="useIt">Kirkasta tämä</button>
  <div id="ideas">
    <p>Valitse valmis kysymys tai aihe:</p>
    <!-- Arjen ja työn ideat -->
    <span>Mistä voisin etsiä uutta työtä?</span>
    <span>Miten kerron taidoistani selkeästi työnhaussa?</span>
    <span>Miten voisin aloittaa pienen sivutoimisen työn?</span>
    <!-- Seniorit / arjen digitaidot -->
    <span>Kuinka käytän videopuhelua lapsenlapsen kanssa?</span>
    <span>Miten voin tallentaa ja järjestää vanhat valokuvat?</span>
    <span>Miten löydän luotettavaa tietoa internetistä?</span>
    <!-- Maahanmuuttajat / kotoutuminen -->
    <span>Miten kirjoitan hyvän vuokrahakemuksen suomeksi?</span>
    <span>Kuinka haen opiskelu- tai kielikurssipaikkaa?</span>
    <span>Miten voin tutustua uusiin ihmisiin Suomessa?</span>
    <!-- Yleinen elämänlaatu -->
    <span>Miten voin säästää rahaa arjen menoissa?</span>
    <span>Kuinka voin oppia uuden harrastuksen?</span>
    <span>Miten pysyn hyvässä kunnossa ilman kuntosalia?</span>
  </div>
<script src="https://cdn.jsdelivr.net/npm/gsap@3/dist/gsap.min.js"></script>
<script>
const prompts = [
  // Yleistajuiset, avoimet kysymykset
  "Mitä haluaisit oppia tänään?",
  "Mikä on ollut mielessäsi viime päivinä?",
  "Jos voisit kysyä keneltä tahansa mitä tahansa, mitä kysyisit?",
  // Senioriystävälliset
  "Mitä haluaisit kertoa lapsenlapsellesi tai ystävällesi?",
  "Onko jokin laite tai ohjelma, jonka käyttö mietityttää?",
  "Minkä taidon haluaisit muistaa tai oppia uudelleen?",
  // Maahanmuuttajille sopivat
  "Mitä haluaisit tietää Suomesta?",
  "Mikä asia kotoutumisessa tuntuu vaikealta?",
  "Miten voisimme selittää sinulle viranomaiskirjeen selkeästi?"
];
let idx = 0;
const triggerEl = document.getElementById('trigger');
function showNext(){
  gsap.to(triggerEl, {opacity:0, duration:0.5, onComplete:()=>{
    triggerEl.textContent = prompts[idx];
    gsap.to(triggerEl, {opacity:1, duration:0.5});
    idx = (idx+1) % prompts.length;
  }});
}
showNext();
setInterval(showNext, 5000);

document.getElementById('useIt').onclick = () => {
  const q = encodeURIComponent(triggerEl.textContent);
  window.location.href = 'index.html?prefill=' + q;
};
document.querySelectorAll('#ideas span').forEach(s => {
  s.onclick = () => {
    const q = encodeURIComponent(s.textContent);
    window.location.href = 'index.html?prefill=' + q;
  };
});
</script>
</body>
</html>
