# TaskLamp – Everyday Tasks Assistant

**TaskLamp** is a lightweight AI-powered web service that helps migrants complete everyday bureaucratic tasks step by step.  
The name refers to a flashlight that always lights the next step forward.

---

## Background & Purpose

Migrants arriving in a new country face numerous practical challenges:
- finding and filling official forms  
- locating printing services without having a printer  
- understanding deadlines and required attachments  
- booking healthcare appointments  
- registering children for school  
- opening a bank account and secure authentication

Although information exists online, it is fragmented and often only in the local language. TaskLamp turns it into clear steps in the user’s own language.

---

## Key Features

- **Natural language input**: user asks e.g.  
  *“How do I register my child for school in Prague?”*
- **AI-generated step plan** including:  
  - direct links to official forms  
  - instructions on how to fill them  
  - printing and submission locations  
  - deadlines and attachments  
- **Focus mode**: one step at a time to avoid overwhelm  
- **Multilingual**: interface in Finnish, English, Swedish (easily extendable)  
- **Privacy first**: no permanent storage, with a “Panic Button” to wipe all data instantly  
- **Open technologies**: OpenStreetMap for map links, open i18n structure, native PHP/JS

---

## User Experience

- **Low threshold**: simple form (country, city, task)  
- **Accessible UI**: WCAG 2.1 compliant, large buttons, clear contrasts  
- **Healthcare phrases**: if the task relates to healthcare, useful ready-made phrases are shown

---

## Technical Implementation

- **Frontend**:  
  - `tasklamp.php` (UI, no inline code)  
  - `assets/js/tasklamp.js` (i18n, spinner, navigation, OSM integration, panic button)  
  - `assets/css/tasklamp.css` (WCAG styles)  
- **Backend**:  
  - `search/tasklamp.php` integrates GPT API for step planning  
  - Tavily API provides address/resource search  
- **I18n**: `/assets/i18n/fi.json`, `en.json`, `sv.json`  
- **Security**: `.htaccess` stores API keys and prevents directory listing  

---

## Benefits

- **Migrants**: reduced stress, faster integration, ability to handle tasks independently  
- **Authorities**: fewer errors and incomplete applications  
- **EU**: aligned with AMIF-2025 priorities (digital integration tools, accessibility, multilingual support)  
- **Privacy and equality**: all services operate fully anonymously – no login, no identification required.  
  This ensures equal access for everyone with an internet connection, regardless of their legal status,  
  documentation, or digital identity. TaskLamp empowers users to handle their affairs without barriers.


---

## Roadmap

1. Demo (step plans, multilingual UI, privacy)  
2. Integrate local databases (libraries, offices, NGOs)  
3. Add cultural customization and more languages  
4. Pilot in multiple EU countries (SE, PL, CZ, SK, HU)  

---

## For Developers

- API keys are configured in `.htaccess`  
- Install: copy files + open `tasklamp.php` in browser  
- Requirements: Apache + PHP 8.1+  

---

© Kanavana / 2025 – Prototype developed for EU AMIF funding proposal
