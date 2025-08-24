data-protection.md — Tietosuoja & henkilötietojen käsittely

Versio: 1.0
Viimeksi päivitetty: 2025-08-18

Tämä asiakirja kuvaa, miten palvelumme (AI-avusteinen työhakemustyökalu) käsittelee henkilötietoja EU:n yleisen tietosuoja-asetuksen (GDPR) mukaisesti. Dokumentti on tarkoitettu sekä käyttäjille että sopimusteknisten kumppanuuksien (esim. käsittelijäsopimukset) pohjaksi.

Tiivistettynä: palvelu luo työhakemusluonnoksia käyttäjän syöttämistä tiedoista. Luonnos tallennetaan ensisijaisesti vain käyttäjän omaan selaimeen (localStorage). Kun käyttäjä pyytää luonnoksen luontia, syötteet välitetään palvelimelle, joka kutsuu mallipalvelua (LLM) ja hakupalvelua tuottaakseen tekstin. Emme käytä tietoja markkinointiprofilointiin.

1. Rekisterinpitäjä ja yhteystiedot

Rekisterinpitäjä: [Yrityksen / organisaation nimi]

Y-tunnus: [täydennettävä]

Postiosoite: [täydennettävä]

Sähköposti: [tietosuoja@… tai yleinen yhteysosoite]

Mahdollinen tietosuojavastaava (DPO): [nimi / rooli / yhteystiedot, jos nimetty]

2. Käsittelyn tarkoitukset

Palvelun tuottaminen: työhakemusluonnoksen generointi käyttäjän antamien tietojen ja valinnaisen työpaikkalinkin perusteella.

Tietoturva ja väärinkäytösten ehkäisy: kuormitus- ja virhelokit, rajoitukset roskapyyntöihin, palvelun väärinkäytön seuranta.

Kehitys ja laadunvarmistus: anonyymit diagnostiset tiedot ja virheilmoitukset (ei sisällön tarpeetonta tallettamista).

Asiakastuki: käsittelemme yhteydenottoon liittyvät tiedot, jos käyttäjä ottaa meihin yhteyttä.

3. Käsiteltävät henkilötietoryhmät

Käyttäjän itse antamat tiedot: vapaa teksti esittelystäsi, vahvuuksista, motivaatiosta ja esimerkeistä; mahdollinen työnantajan nimi; vapaaehtoinen työpaikkailmoituksen URL.

Tekniset tiedot: IP-osoite ja aikaleima palvelinpyynnöissä; laite/selain-tiedot, istuntotunnisteet lokituksessa.

Selainsäilöt: luonnos- ja asetusdata localStorage/sessionStorage-tilassa (vain käyttäjän laitteella).

Hakukonteksti: kun annat URL-osoitteen tai työnantajan nimen, palvelu voi hakea julkista tietoa ja välittää mallille lyhyen tehtäväkuvauksen (”search + write”-ohje).

Emme pyydä tai tarvitse arkaluonteisia tietoja (esim. terveystiedot). Pyydämme, ettet kirjoita niistä vapaamuotoisiin kenttiin.

4. Käsittelyn oikeusperusteet (GDPR 6 artikla)

Sopimus / palvelun toteuttaminen (6(1)(b)): luonnoksen tuottaminen käyttäjän pyynnöstä.

Oikeutettu etu (6(1)(f)): tietoturva, väärinkäytösten esto sekä välttämätön kehitysanalytiikka rajatusti.

Suostumus (6(1)(a)) vain, jos erikseen pyydämme sellaiseen toimintoon (ei oletuksena).

GDPR:n yleiset oikeusperusteet ja rekisteröidyn oikeudet on kuvattu EU- ja kansallisilla viranomaissivuilla. Katso mm. Tietosuojavaltuutetun koonti rekisteröidyn oikeuksista.

5. Tallennusajat

Selaimen localStorage / sessionStorage: säilyy käyttäjän laitteella kunnes käyttäjä poistaa (”Poista kaikki”/”Tyhjennä luonnos”) tai tyhjentää selaindatan.

Palvelimen sovellus- ja virhelokit: vain tarpeellinen minimi, tyypillisesti [täydennä, esim. 30–90 päivää].

Mallipalvelun (LLM) ja haku-API:n säilytys: riippuu käsittelijästä (ks. §7). Esim. OpenAI API kertoo säilyttävänsä API-sisältöä enintään 30 päivän ajan väärinkäytösten seurantaan, ja tarjoaa Zero-Data-Retention-vaihtoehdon tietyille käyttöyhteyksille; API-dataa ei käytetä koulutukseen oletuksena. Tarkemmat ehdot: OpenAI Enterprise Privacy.

6. Evästeet ja vastaavat tekniikat

Emme käytä seurantaan tarkoitettuja kolmannen osapuolen evästeitä.

Käytämme localStoragea luonnosten ja asetusten tallentamiseen vain selaimeesi; tietoja ei lähetetä HTTP-pyynnöissä automaattisesti palvelimelle, toisin kuin evästeet.

Sivusto saattaa ladata kolmannen osapuolen resursseja (esim. kirjasimet/CDN), jolloin kyseinen palveluntarjoaja näkee IP-osoitteesi resurssipyynnön käsittelemiseksi. [Lisää tähän lista, esim. Google Fonts / CDN-toimittaja, jos käytössä.]

| Vastaanottaja / prosessori              | Rooli                                                | Sijainti                      | Siirtomekanismi                                                                                                                                    | Keskeinen tietosuojakuvaus                                                                                                        |
| --------------------------------------- | ---------------------------------------------------- | ----------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------- |
| **OpenAI (API/LLM)**                    | Luonnostekstin tuottaminen syötteiden perusteella    | USA / globaalit datakeskukset | DPA + **SCC**-lausekkeet; **API-data ei koulutukseen oletuksena**; **≤30 pv** säilytys väärinkäytösten torjuntaan; **ZDR** valikoivasti saatavilla | OpenAI Enterprise Privacy.                                                                                                        |
| **Tavily (web-haku API)**               | Julkisen taustatiedon haku (esim. työnantajan sivut) | \[globaalit]                  | \[SCC / muu, palveluntarjoajan mukaan]                                                                                                             | Katso Tavilyn dokumentaatio/ehdot. (Lisää linkki omaan DPIAasi; suositus: älä lähetä tarpeettomia henkilötietoja hakukyselyihin.) |
| **\[Hosting-kumppani]**                 | Sivuston ja backendin isännöinti                     | \[EU/ETA tai muu]             | \[SCC tms.]                                                                                                                                        | \[täydennettävä]                                                                                                                  |
| **\[Virheenhallinta / lokituspalvelu]** | Diagnostiikka                                        | \[EU/ETA tai muu]             | \[SCC tms.]                                                                                                                                        | \[täydennettävä]                                                                                                                  |


8. Henkilötietojen siirrot EU/ETA-alueen ulkopuolelle

Jos prosessori sijaitsee EU/ETA-alueen ulkopuolella, varmistamme lainmukaisen siirtoperusteen (kuten Euroopan komission vakiosopimuslausekkeet, SCC). Esimerkiksi OpenAI tarjoaa DPA:n ja SCC:t GDPR-vaatimusten täyttämiseksi; katso heidän dokumentaationsa.

9. Turvatoimet

Salaus siirrossa (TLS).

Minimointi: lähetämme mallille vain luonnoksen tuottamiseen välttämättömät tekstikatkelmat (”search + write” -ohje).

Selaimen puolella: luonnos säilytetään localStoragessa; tarjolla toiminnot ”Tyhjennä luonnos” ja ”Poista kaikki”.

Sisällön puhdistus: palvelu siivoaa HTML-sisällön XSS-riskien pienentämiseksi (DOMPurify/ vastaava).

Pääsynhallinta ja lokitus: vain rajatulla ylläpitohenkilöstöllä on pääsy palvelinlokitietoihin.

DPIA / riskiarvio: teemme vaikutustenarvioinnin, jos käsittely muuttuu riskialttiiksi (esim. laajennettu profilointi tai arkaluonteiset tiedot).

10. Automatisoidut päätökset ja profilointi

Palvelu ei tee oikeusvaikutuksia aiheuttavia automatisoituja päätöksiä. Luonnos on käyttäjän itse muokattavissa, eikä sitä käytetä rekrytointipäätöksiin ilman käyttäjän omaa toimintaa.

11. Rekisteröidyn oikeudet

GDPR antaa sinulle mm. oikeuden:

saada pääsy tietoihin, oikaista virheelliset tiedot ja poistaa tiedot (”oikeus tulla unohdetuksi”),

rajoittaa käsittelyä, vastustaa käsittelyä sekä siirtää tiedot järjestelmästä toiseen.

Katso kattava koonti Tietosuojavaltuutetun toimiston sivulta Rekisteröidyn oikeudet.

Miten käytät oikeuksiasi?
Ota yhteyttä meihin (ks. §1). Saat vastauksen ilman aiheetonta viivytystä ja viimeistään kuukauden kuluessa (erityistapauksissa enintään 3 kk).

Valitusoikeus valvontaviranomaiselle:
Sinulla on oikeus tehdä kantelu Tietosuojavaltuutetun toimistolle (FI). Toiminta-ohjeet ja yhteystiedot viranomaisen sivulla. 
Tietosuojavaltuutetun toimisto

12. Lasten tiedot

Palvelu on suunnattu aikuisille työnhakijoille. Emme tietoisesti kerää lasten henkilötietoja.

13. Muutokset tähän asiakirjaan

Kehitämme palvelua jatkuvasti. Päivitämme tämän asiakirjan tarvittaessa; merkittävistä muutoksista ilmoitetaan palvelussa.

14. Käytännön ohjeita käyttäjälle

Älä sisällytä arkaluonteisia henkilötietoja vapaatekstikenttiin.

Käytä Tyhjennä luonnos tai Poista kaikki -toimintoa, kun olet valmis.

Jos liität työpaikkalinkin, varmista että se ei sisällä yksityisiä tunnisteita (esim. seuranta-parametrit).

15. Liitteet / täydennettävät kentät

Rekisteröityjen kategoriat: palvelun käyttäjät (työnhakijat), asiakastuen yhteydenottajat.

Tarkemmat säilytysajat: [täydennä ympäristökohtaisesti: tuotanto / kehitys / lokit].

Tekniset lokit: mitä kirjataan (IP, aikaleima, resurssipyyntö, virhekoodi), miksi ja millä poistopolitiikalla.

Prosessorikohtaiset DPA-viitteet: OpenAI DPA/SCC (ks. enterprise-sivu), Tavilyn ehdot, hosting-kumppanin DPA.

Lähteitä

OpenAI — Enterprise privacy & API-datan käsittely (retentio, koulutus, DPA/SCC): ”Enterprise privacy at OpenAI”.

Tietosuojavaltuutetun toimisto — Rekisteröidyn oikeudet: rekisteröidyn oikeuksien koontisivu.

Tietosuojavaltuutetun toimisto — Valituksen tekeminen: ohjeet kantelun tekemiseen. 
Tietosuojavaltuutetun toimisto