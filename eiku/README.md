# Eiku Prompt – Ajatusten kirkastamo

Suora PHP/HTML/JS-MVP pilot.kanavana.fi:lle. Ei WordPressiä.

## Asennus
1. Kopioi koko `eiku/`-hakemisto palvelimelle (esim. `pilot.kanavana.fi/eiku/`).
2. Varmista kirjoitusoikeus `data/`-kansioon (SQLite-tietokanta luodaan automaattisesti).
3. Lisää projektin juuren `.htaccess` (esimerkki): 
   ```apache
   <IfModule mod_env.c>
     SetEnv GOOGLE_NLP_API_KEY "AIzaxxx"
     SetEnv OPENAI_API_KEY "sk-xx"
     SetEnv GOOGLE_CSE_KEY "AIzaxxx"
     SetEnv GOOGLE_CSE_CX "xxx"
     SetEnv GOOGLE_CSE_CX_IMAGES "xxx"
     # (valinnainen) SetEnv GOOGLE_TRANSLATE_API_KEY "AIzayyy"
   </IfModule>
   ```
4. Avaa selaimessa `https://pilot.kanavana.fi/eiku/`.

## Endpointit
- `api/start.php` – aloita kirkastus
- `api/refine.php` – kierroskohtainen tarkennus
- `api/summary.php` – muodosta lopullinen prompti (sis. pehmennys, jos sävy on kova)
- `api/execute.php` – aja lopullinen prompti GPT:llä
- `api/share.php` – generoi jaettavan linkin
- `api/health.php` – tarkistaa avaimien olemassaolon (ei tulosta arvoja)

## Pehmennys (sentiment softening)
- Jos alkuperäisen syötteen sentimentti on **negatiivinen** (score < -0.4), lisätään ohje *Käytä neutraalia ja kohteliasta sävyä* ja pakotetaan tone=neutral lopulliseen promptiin.

## Vihjeet
- Lisää CORS/turvarajoituksia tarvittaessa.
- Lisää throttle/ratelimit (IP) kuormitusta vastaan.
- Kytke taustahaku (Google CSE) jatkokehityksessä UI:hin.

© Kanavana
