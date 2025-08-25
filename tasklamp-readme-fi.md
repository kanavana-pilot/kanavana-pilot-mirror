# TaskLamp – Tehtävien taskulamppu

**TaskLamp** on kevyt tekoälyavusteinen verkkopalvelu, joka auttaa maahanmuuttajia selviytymään arjen viranomaisasioista vaihe vaiheelta.  
Nimi viittaa taskulamppuun, joka valaisee aina seuraavan askeleen.

---

## Tausta ja tarkoitus

Uuteen maahan muuttavat ihmiset kohtaavat usein käytännön haasteita:
- virallisten lomakkeiden etsiminen ja täyttäminen
- tulostuspalveluiden löytäminen ilman omaa tulostinta
- määräaikojen ja liitteiden ymmärtäminen
- ajanvaraus terveydenhuollossa
- lasten ilmoittaminen kouluun
- pankkitilin avaaminen ja tunnistuspalvelut

Tieto on olemassa, mutta sirpaleista ja vaikeasti löydettävää. TaskLamp kokoaa sen selkeiksi askeliksi käyttäjän omalla kielellä.

---

## Keskeiset ominaisuudet

- **Luonnollinen kieli**: käyttäjä kirjoittaa tehtävän esim.  
  *“Miten ilmoitan lapseni kouluun Prahassa?”*
- **Askelpolku**: tekoäly laatii vaiheittaisen suunnitelman, jossa on  
  - suorat linkit virallisiin lomakkeisiin  
  - ohjeet niiden täyttämiseen  
  - tulostus- ja jättöpaikat  
  - määräajat ja liitteet
- **Yksi askel kerrallaan**: käyttöliittymä näyttää vain yhden vaiheen kerrallaan.
- **Monikielisyys**: käyttöliittymä toimii suomeksi, englanniksi ja ruotsiksi.
- **Tietosuoja**: ei pysyvää tallennusta. Käyttäjällä on “Paniikkinappi”, joka tyhjentää kaiken selaimesta.
- **Avoimet teknologiat**: OpenStreetMap-karttalinkit, avoin i18n-rakenne, natiivi PHP/JS.

---

## Käyttäjäkokemus

- **Alhainen kynnys**: yksinkertainen lomake (maa, kaupunki, tehtävä).
- **Selkeä UI**: WCAG 2.1 mukainen, isot painikkeet ja hyvä kontrasti.
- **Fraasit terveydenhoitoon**: jos käyttäjän kysymys liittyy terveyteen, tarjotaan valmiita sanontoja.

---

## Tekninen toteutus

- **Frontend**:  
  - `tasklamp.php` (UI, ei inline-koodia)  
  - `assets/js/tasklamp.js` (i18n, spinner, navigointi, OSM-linkitys, paniikkinappi)  
  - `assets/css/tasklamp.css` (WCAG-tyylit)  
- **Backend**:  
  - `search/tasklamp.php` käyttää GPT API:a askelpolun luontiin  
  - Tavily API mahdollistaa osoite- ja paikkahaut  
- **I18n**: `/assets/i18n/fi.json`, `en.json`, `sv.json`  
- **Tietoturva**: `.htaccess` sisältää API-avaimet ja estää hakemistolistauksen

---

## Hyödyt

- **Käyttäjille**: vähemmän stressiä, nopeampi kotoutuminen, itsenäinen asiointi  
- **Viranomaisille**: vähemmän virheitä ja keskeneräisiä hakemuksia  
- **EU-tasolla**: tukee AMIF-2025 -tavoitteita (digitaaliset integraatiota tukevat työkalut, saavutettavuus, monikielisyys)  
- **Yksityisyys ja tasa-arvo**: kaikki palvelut toimivat täysin anonyymisti – kirjautumista tai tunnistautumista ei vaadita.  
  Tämä takaa tasavertaisen pääsyn kaikille verkkoon pääseville, riippumatta laillisesta asemasta,  
  dokumenteista tai digitaalisesta identiteetistä. TaskLamp mahdollistaa asioiden hoitamisen ilman esteitä.


---

## Laajennettavuus

1. Demo (askelpolku, monikielisyys, tietosuoja)  
2. Integraatiot paikallisiin tietokantoihin  
3. Kulttuurikohtaiset ohjeet ja uudet kielet  
4. Pilotointi useassa EU-maassa (SE, PL, CZ, SK, HU)  

---

## Kehittäjille

- API-avaimet määritellään `.htaccess`-tiedostossa  
- Käyttöönotto: kopioi tiedostot + avaa `tasklamp.php` selaimessa  
- Edellytys: Apache + PHP 8.1+  

---

© Kanavana / 2025 – Prototyyppi EU AMIF-hakemusta varten
