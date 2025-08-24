const langSel = document.getElementById('langSel');
const govOnlyEl = document.getElementById('govOnly');
const resultBox = document.getElementById('resultBox');

async function quickSearch(q) {
    resultBox.className = 'alert alert-info';
    resultBox.textContent = 'Haetaan...';
    resultBox.classList.remove('d-none');

    try {
        const API = '/search/answer.php';
        const res = await fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                q,
                lang: langSel.value,
                gov_only: !!govOnlyEl.checked
            })
        });

        if (!res.ok) {
            throw new Error(`HTTP ${res.status}`);
        }

        const data = await res.json();
        resultBox.className = 'alert alert-success';
        resultBox.textContent = data.answer || 'Ei tulosta';
    } catch (err) {
        resultBox.className = 'alert alert-danger';
        resultBox.textContent = `Virhe haussa: ${err.message}`;
    }
}
// JS (ulkoinen tiedosto)
document.querySelectorAll('button[data-q]').forEach(btn => {
  btn.addEventListener('click', () => quickSearch(btn.dataset.q));
});
