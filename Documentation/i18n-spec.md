Kielten nimien esitystapa (i18n-spec)

Versio: 1.0
Päiväys: 2025-08-18

Tämä dokumentti määrittelee, miten kielten nimet esitetään i18n-manifestissa ja käyttöliittymässä.

Periaatteet

name on kielen nimi englanniksi (esim. Finnish, Estonian).

nativeName on kielen oma nimi sellaisena kuin kieliyhteisö sitä käyttää.

Kirjainkoko noudattaa natiivikäytäntöä (esim. suomi, svenska, eesti, français, español; Deutsch isolla, koska saksan substantiivit kirjoitetaan isolla; English isolla).

Maan nimeä ei käytetä kielen nimenä. Esim. Eesti = maa, eesti = kieli.

dir on ltr tai rtl kielen kirjoitussuunnan mukaan.

Ratkaisu tukee WCAG 2 -saavutettavuutta ja linjautuu ISO 639 -käytäntöihin sekä EU:n kielellisen yhdenvertaisuuden periaatteisiin.

Language Naming Specification (i18n-spec)

Version: 1.0
Date: 2025-08-18

This document defines how language names are represented in the i18n manifest and UI.

Principles

name is the language name in English (e.g., Finnish, Estonian).

nativeName is the endonym (the language’s self-name).

Capitalization follows native conventions (e.g., suomi, svenska, eesti, français, español; Deutsch capitalized as a German noun; English capitalized).

Do not use country names as language names. E.g., Eesti = country, eesti = language.

dir is ltr or rtl according to the script’s writing direction.

The approach supports WCAG 2 accessibility and aligns with ISO 639 practices and EU linguistic equality principles.

| code | name (EN) | nativeName (endonym) | dir |
| ---- | --------- | -------------------- | --- |
| fi   | Finnish   | **suomi**            | ltr |
| sv   | Swedish   | **svenska**          | ltr |
| en   | English   | **English**          | ltr |
| et   | Estonian  | **eesti**            | ltr |
| ru   | Russian   | **русский**          | ltr |
| uk   | Ukrainian | **українська**       | ltr |
| so   | Somali    | **Soomaali**         | ltr |
| fr   | French    | **français**         | ltr |
| de   | German    | **Deutsch**          | ltr |
| es   | Spanish   | **español**          | ltr |
| it   | Italian   | **italiano**         | ltr |
| ar   | Arabic    | **العربية**          | rtl |
| fa   | Persian   | **فارسی**            | rtl |

{
  "default": "fi",
  "languages": [
    { "code": "fi", "name": "Finnish",   "nativeName": "suomi",       "dir": "ltr" },
    { "code": "sv", "name": "Swedish",   "nativeName": "svenska",     "dir": "ltr" },
    { "code": "en", "name": "English",   "nativeName": "English",     "dir": "ltr" },

    { "code": "et", "name": "Estonian",  "nativeName": "eesti",       "dir": "ltr" },
    { "code": "ru", "name": "Russian",   "nativeName": "русский",     "dir": "ltr" },
    { "code": "uk", "name": "Ukrainian", "nativeName": "українська",  "dir": "ltr" },
    { "code": "so", "name": "Somali",    "nativeName": "Soomaali",    "dir": "ltr" },

    { "code": "fr", "name": "French",    "nativeName": "français",    "dir": "ltr" },
    { "code": "de", "name": "German",    "nativeName": "Deutsch",     "dir": "ltr" },
    { "code": "es", "name": "Spanish",   "nativeName": "español",     "dir": "ltr" },
    { "code": "it", "name": "Italian",   "nativeName": "italiano",    "dir": "ltr" },

    { "code": "ar", "name": "Arabic",    "nativeName": "العربية",     "dir": "rtl" },
    { "code": "fa", "name": "Persian",   "nativeName": "فارسی",       "dir": "rtl" }
  ]
}

Perustelulause (FI)

Kielten nimet on esitetty endonyymeinä (natiivimuodossa) ISO 639 -standardin mukaisesti, mikä tukee EU:n kielellistä yhdenvertaisuutta ja WCAG 2 -saavutettavuutta.

Justification Sentence (EN)

Language names are presented as endonyms (native forms) in accordance with ISO 639 standards, supporting EU linguistic equality and WCAG 2 accessibility compliance.