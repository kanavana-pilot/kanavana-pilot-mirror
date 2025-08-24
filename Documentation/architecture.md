architecture.md — Tekniset ratkaisut (frontti/back, integraatiot)
1) Yleiskuva

Sovellus on kevyt, monikielinen “3-vaiheinen” web-työkalu, joka auttaa käyttäjää kirjoittamaan työhakemuksen.
Frontti on toteutettu kevyenä, kehyksettömänä (vanilla) HTML/CSS/JS-ratkaisuna. Backissa on kaksi PHP-päätä:

/search/answer.php — rakentaa haun (Tavily), kokoaa lähteet ja tuottaa hakemustekstin (OpenAI).

/search/rewrite.php — muotoilee jo tuotetun HTML-vastauksen (tyyli, pituus, kielipeilaus).

Integraatioiden päälinja: Käyttäjän syöte → (frontti) → answer.php → Tavily haku → LLM-generointi → (frontti).
Lisämuokkaus ja FI-peilaus tehdään tarvittaessa rewrite.php:n kautta.

2) Frontend
2.1 Rakenne

Monivaiheinen lomake (3 askelta): Perustiedot → Tarkennukset → Luonnos.
Vaiheiden vaihto hallitaan history.pushState/popstate-ohjauksella, ja fokus siirretään aina ensimmäiseen loogiseen syötepisteeseen (näppäimistö- ja ruudunlukijaystävällisyys).

Tilanhallinta:

localStorage: käyttäjän luonnos (kentät) automaattisessa tallennuksessa.

sessionStorage: FI-peilin välimuisti (avaimella fi:<hash>).

I18n: langSel ohjaa i18n.js:ää. UI-tekstit päivittyvät lennossa.

Kaksikielinen esikatselu: paikallisen kielen paneeli + pakotettu LTR-suomen peili (#out-fi { direction:ltr; unicode-bidi:isolate; }).

Docx-vienti: luodaan yksinkertainen HTML-pohjainen .doc blobina ja ladataan.

2.2 Syöte → pyyntö

Frontti normalisoi käyttäjän “työpaikan linkki TAI yrityksen nimi” -syötteen:

Jos URL, talletetaan job_url ja rajoitetaan haku domainiin (include_domains=[host]).

Lisäksi erillinen company-kenttä (vapaaehtoinen) välitetään backille.

Lähetettävä payload (answer.php):

{
  "q": "SEARCH: …\nWRITE: …",   // rakennettu kysely (tiivistetty)
  "lang": "fi|…",
  "gov_only": false,
  "company": "<yrityksen nimi tai ''>",
  "job_url": "<url tai ''>",
  "include_domains": ["<host>"] // kun job_url on annettu
}

2.3 A11y & WCAG

Fokus näkyy selvästi kaikilla interaktiivisilla elementeillä (Focus Visible).

Kosketus-/osoitinkohteet ≥44px (Target Size Minimum).

2.4 XSS-kovennus & sisältöturva

Kaikki mallin tuottama HTML ajetaan DOMPurifyn läpi; käytössä hook, joka lisää ulkoisiin linkkeihin rel="noopener noreferrer" ja siivoaa epäkelvot URI:t. DOMPurify on laajasti käytetty XSS-sanitointikirjasto.

Suositus: ota käyttöön myös tiukka Content Security Policy (CSP) palvelimelta (esim. estä inline-skriptit, salli vain tarvittavat alkuperät). 
Strapi Community Forum
OWASP Cheat Sheet Series

3) Backend
3.1 End-pointit

/search/answer.php

Vastaanottaa frontin payloadin.

Orkestroi Tavily Search API -haun (mahd. kahdella strategiakyselyllä: yrityksen profiili + ilmoituskohtainen tieto).

Rakentaa LLM-kehotteen (WRITE) Tavilyn tulosten perusteella.

Palauttaa: answer_html, plain_html (selkokieli), fi_html (jos tehty), citations[], followups[].
Debug-tilassa (?debug=1) palauttaa myös haun metatietoja (kyselyt, host-rajaus, tuloslistat).

/search/rewrite.php

Vastaanottaa olemassa olevan HTML:n + halutun tyyliprofiilin/pituuden + kohdekielen.

Palauttaa uudelleenkirjoitetun answer_htmlin (ja tarvittaessa FI-peilin).

3.2 Integraatiot
Tavily Search API

Käytössä verkkohaku, joka tukee mm. domain-rajausta ja hakusyvyyden/palautemäärän säätöä. Integraatio syöttää include_domains-listan, kun työpaikan URL on annettu. Tavily on suunniteltu LLM-sovelluksille (haku + tiivistys/viittaukset).

OpenAI (LLM-generointi)

Vastaukset tuotetaan OpenAI:n API:lla.

Tietosuoja: OpenAI kertoo, että API-asiakkaiden dataa ei käytetä mallien kouluttamiseen ja että yritys/ChatGPT Enterprise -tuotteille on erillinen tietosuojakäytäntö (mm. lyhyet lokien säilytysajat; yksityiskohdat tuotteesta riippuen). Viittaamme virallisiin OpenAI-sivuihin.

Huom. Vältä henkilötietojen tarpeetonta välittämistä API:lle. Pseudonymisoi ja supista kehotteet mahdollisimman pieniksi.

4) Tietoturva & tietosuoja
4.1 Frontti

Sanitointi: DOMPurify kaikelle mallin tuottamalle HTML:lle.

CSP: lisää vahva CSP (esim. default-src 'self'; salli CDN:t eksplisiittisesti; estä unsafe-inline). 
Strapi Community Forum
OWASP Cheat Sheet Series

HTTPS everywhere.

Tallennus vain paikallisesti: luonnokset localStoragessa (käyttäjän laitteella). Ei säilötä palvelimella ellei erikseen oteta käyttöön.

4.2 Backki

Syötteiden validointi: URL-normalisointi, sallittujen domainien lista vain kun URL lähetetään.

Aikaleimat & nopeusrajoitus: throttlaus IP/asiakasavaimen mukaan.

Virheiden käsittely: yhtenäinen JSON-virhemuoto ({ error, detail }), 4xx/5xx.

4.3 Henkilötiedot

Pidä payload minimissä (ei arkaluontoista PII:tä).

Jos lokitat, poista kentistä nimet/yhteystiedot tai käytä tietuekohtaista maskingia.

OpenAI API:n osalta: noudata OpenAI:n virallisia data-/yksityisyyskäytäntöjä ja yritysasiakkaiden sopimusehtoja.

5) Suorituskyky & skaala

Välimuisti:

Frontissa FI-peilin sessionStorage-cache.

Backissa Tavily-hakujen tuloscache (lyhyt TTL) vähentää kustannuksia ja latenssia.

Asynkronia: Tavily → LLM pipeline aikakatkaisuilla ja uudelleenyrityksillä (backoff).

Staattiset assetit CDN:ltä, HTTP/2; preconnect Google Fontsille (jo käytössä).

Skaala: PHP-päiden skaalautuminen (FPM/worker-pool). LLM-kutsuissa kiintiövarmistus ja jono.

6) Lokitus, diagnostiikka, valvonta

Health-check: /search/answer.php?health=1 palauttaa nopean OK-vastauksen.

Sovellusloki: pyyntöaika, integraatioviiveet (Tavily/LLM), virhekoodit, ei henkilötietoja.

CSP-raportointi: mahdollinen report-to/report-uri (selaintuki vaihtelee). 
MDN Web Docs

7) Dev & CI/CD
7.1 Ympäristömuuttujat

OPENAI_API_KEY — LLM-kutsut

TAVILY_API_KEY — haku

APP_BASE_URL, CSP_REPORT_ENDPOINT (valinnainen)

Älä koskaan upota avaimia fronttiin. Kehitystilassa salli ?debug=1 vain VPN/IP-rajoitettuna.

7.2 Julkaisuketju

Lint / format / unit (frontin apufunktiot) → integraatiotestit mockatuilla Tavily/LLM-vastauksilla → paketointi → deploy.

Release-muutokset: i18n-avaimet, CSS-regressiot, A11y-tarkistus (Focus Visible, kontrastit, tab-järjestys).

| Endpoint              | Metodi | Pyyntö (ydinkentät)                                     | Vaste                                                                      |
| --------------------- | ------ | ------------------------------------------------------- | -------------------------------------------------------------------------- |
| `/search/answer.php`  | POST   | `q`, `lang`, `company?`, `job_url?`, `include_domains?` | `{ answer_html, plain_html?, fi_html?, citations[], followups[], debug? }` |
| `/search/rewrite.php` | POST   | `html`, `style`, `length`, `lang`                       | `{ answer_html }`                                                          |
| `?health=1`           | GET    | —                                                       | `200 OK /json`                                                             |


9) Sekvenssi (tiivis)
User → Frontti: täyttää kentät
Frontti → answer.php: { q, lang, company?, job_url?, include_domains? }
answer.php → Tavily API: haku(t)
answer.php → OpenAI API: "WRITE..." prompt + tavily tiiviste
OpenAI API → answer.php: HTML + rakenne
answer.php → Frontti: { answer_html, citations, ... }
Frontti → rewrite.php (valinnainen): tyyli/kieli
rewrite.php → Frontti: { answer_html }

10) Tunnetut reunaehdot & jatkokehitys

Työpaikan URL puuttuu / sisältö ohut → käytä company-kenttää profiilihaun siementämiseen (paremmat snippetit).

WCAG: tarkista kontrastit & näppäimistöpolku jokaisessa kielessä (RTL/LTR).

CSP kiristäminen (estetään inline-skriptit, rajoitetaan fontti-/cdn-alkuperät). 
Strapi Community Forum
OWASP Cheat Sheet Series

Lokien minimointi & PII-masking. Viittaa OpenAI:n data-/privacy-ohjeisiin organisaatiokohtaisesti.

Lähteet

DOMPurify README (sanitointi, käyttö & hookit).

Tavily — Search API & tuotekuvaus.

OpenAI — API/Enterprise data & privacy (ei koulutusta API-datalla).

WCAG 2.2 — Focus Visible; Target Size (Minimum).

CSP — MDN & OWASP Cheat Sheet. 
Strapi Community Forum
OWASP Cheat Sheet Series