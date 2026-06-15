# Livento Kurskatalog — Handbuch & Einrichtung

Komplette Anleitung zum WordPress-Plugin **Livento Kurskatalog**: was es kann, wie man es installiert und wie man jede Funktion einstellt. Dieselben Schritt-für-Schritt-Hilfen findest du auch direkt im WordPress-Backend unter **Livento Katalog → Anleitung**.

> **Kurzfassung:** Plugin installieren → anon-Key eintragen → Permalinks speichern → Seiten mit den passenden Shortcodes anlegen. Fertig.

---

## 1. Was das Plugin macht

Das Plugin rendert Inhalte aus **Campus Connect** (und plugin-eigene Inhalte) **nativ in WordPress** — als echtes, indexierbares HTML auf `livento-bildung.de`:

| Bereich | Quelle | Shortcode |
|---|---|---|
| Kurskatalog + Kurs-Detailseiten | Campus Connect (live) | `[livento_kurse]` |
| Themen-Kacheln | Campus Connect (live) | `[livento_themen]` |
| Suchfeld | — | `[livento_kurse_suche]` |
| Kursberater (geführt) | Campus Connect + Plugin | `[livento_kurse_berater]` |
| **Förderprogramme + Detailseiten** | **Plugin (selbst gepflegt)** | `[livento_foerderungen]` |
| **Förderberater (geführt)** | **Plugin (selbst gepflegt)** | `[livento_foerder_berater]` |

Kurse/Themen kommen **automatisch live** aus Campus Connect (mit Cache). Förderprogramme pflegst du **selbst im Plugin** (sie ersetzen die früheren WP-Beiträge).

---

## 2. Installation & Updates

### Erstinstallation
1. WordPress → **Plugins → Installieren → Plugin hochladen** → `livento-kurskatalog.zip` wählen → **Installieren** → **Aktivieren**.

### Updates (automatisch)
Das Plugin meldet neue Versionen selbst (GitHub-Releases). Du aktualisierst es wie jedes andere Plugin:
- **Dashboard → Aktualisierungen → Erneut prüfen** → beim Livento Kurskatalog auf **Aktualisieren**.

> Nach jedem Update, das neue **URL-Muster** mitbringt (neue Detailseiten o. Ä.), einmal **Einstellungen → Permalinks → Speichern** klicken. Schadet nie, dauert 2 Sekunden.

---

## 3. Erste Einrichtung (Pflicht)

### a) anon-Key eintragen
Damit das Plugin Kurse aus Campus Connect lesen darf:
1. **Livento Katalog → Einstellungen**.
2. Im Feld **anon-Key** den Supabase-Anon-Key einfügen → **Speichern**.
3. Kontrolle: **Livento Katalog → Übersicht** zeigt „anon-Key konfiguriert ✅" und eine Kurszahl > 0.

### b) Permalinks einmal speichern
**Einstellungen → Permalinks → Änderungen speichern** (ohne etwas zu ändern). Das aktiviert die schönen Detail-URLs (`/kurse/<slug>/`, `/foerdermoeglichkeiten/<slug>/`).

---

## 4. Seitenstruktur — welche Seite braucht welchen Shortcode

Lege je eine WordPress-**Seite** an und setze den jeweiligen Shortcode in den Inhalt:

| Seite (empfohlener Slug) | Inhalt (Shortcode) | Zweck |
|---|---|---|
| `/kurse/` | `[livento_kurse]` | Kurskatalog mit Filter + Kurs-Detailseiten |
| `/foerdermoeglichkeiten/` | `[livento_foerderungen]` | Förderprogramme + Detailseiten |
| z. B. `/kursberatung/` | `[livento_kurse_berater]` | Kursberater |
| z. B. `/foerderberatung/` | `[livento_foerder_berater]` | Förderberater |
| Startseite o. Ä. | `[livento_themen]`, `[livento_kurse_suche]` | Einstiege |

> **Wichtig:** Der Seiten-Slug muss zur jeweiligen Basis passen. Katalog = `kurse`, Förderungen = `foerdermoeglichkeiten`. Die Detailseiten (`/kurse/<slug>/`, `/foerdermoeglichkeiten/<slug>/`) entstehen automatisch unter dieser Seite.

---

## 5. Shortcode-Referenz

### `[livento_kurse]` — Kurskatalog
| Attribut | Beschreibung |
|---|---|
| `limit` | Anzahl Karten (0 = alle). >0 = kuratierter Block ohne Filterleiste. |
| `sort` | `next_start` (Standard), `newest`, `popular`, `rating`, `most_booked`, `price_asc`, `price_desc`. |
| `filters` | Filterleiste erzwingen: `yes`/`no` (Standard: an, außer bei gesetztem `limit`). |
| `topics` | Auf Themen vorfiltern: Komma-Liste von Themen-Slugs (z. B. `leitung-management,demenz`). |
| `audience` | Auf Zielgruppen vorfiltern: Komma-Liste von Zielgruppen-Slugs (z. B. `fuehrungskraefte,praxisanleitende`). |

Beispiele:
```
[livento_kurse]                                   ← voller Katalog mit Filter
[livento_kurse limit="6"]                         ← 6 Kurse als Block
[livento_kurse limit="6" topics="leitung-management"]
[livento_kurse topics="demenz,palliative-care"]   ← nur diese Themen (ODER)
[livento_kurse audience="fuehrungskraefte"]       ← nur Kurse für Führungskräfte
[livento_kurse topics="demenz" audience="pflegehilfskraefte"]
```

> `topics` und `audience` lassen sich kombinieren. Die jeweiligen Slugs stehen live unter **Livento Katalog → Filter & Slugs**. Zielgruppen-Slugs sind u. a. `pflegefachkraefte`, `pflegehilfskraefte`, `fuehrungskraefte`, `praxisanleitende`, `betreuungskraefte_43b_53b`, `quereinsteigende`, `angehoerige`.

### `[livento_themen]` — Themen-Kacheln
`limit`, `sort` (`count`/`alpha`), `counts` (`yes`/`no`), `all` (Alle-Themen-Kachel), `min` (Themen mit < N Kursen ausblenden).

### `[livento_kurse_suche]` — Suchfeld
`placeholder`, `button`, `title`. Springt zur Katalogseite `/kurse/?q=<begriff>`.

### `[livento_foerderungen]` — Förderprogramme
`audience` (`privat`/`unternehmen`), `region` (Region-Slug), `filter` (`yes`/`no`).

### `[livento_kurse_berater]` — Kursberater
`title`, `intro`, `starttermin` (`yes`/`no`), `form` (`yes`/`no`), `result_limit`.

### `[livento_foerder_berater]` — Förderberater
`title`, `intro`, `form` (`yes`/`no`).

---

## 6. Kurskatalog & Filter

- **Filterleiste** (links): Typ, Format, Level, Zielgruppe, Förderung, Thema u. a. — voll datengetrieben aus den Kursen.
- **Deep-Links:** Filter lassen sich per URL vorbelegen, z. B. `…/kurse/?format=online_live`, `…/kurse/?funding=azav_bildungsgutschein`, `…/kurse/?topics=demenz`. Mehrere Werte mit Komma, mehrere Parameter mit `&`.
- Alle verfügbaren Parameter + Live-Werte stehen unter **Livento Katalog → Filter & Slugs**.

**Neue Kurse** erscheinen automatisch: Sobald ein Kurs in Campus Connect öffentlich ist, taucht er nach Ablauf des Caches (oder nach „Cache leeren" / Webhook) im Katalog auf.

---

## 7. Themen-Kacheln

`[livento_themen]` erzeugt anklickbare Kacheln je Kursthema (mit Kurszahl). Klick führt auf `/kurse/?topics=<slug>`. Die Themen kommen automatisch aus den Kursen — keine Pflege nötig.

---

## 8. Kursberater einrichten

Geführter Berater im SGD-Stil: **Ihre Interessen → Ihr Starttermin → Ihre Angaben → Ihr Ergebnis** (passende Kurse inline).

1. **Interessen-Aussagen pflegen:** **Livento Katalog → Berater**. Jede Zeile = eine „Ich möchte …"-Aussage + angekreuzte **Themen**, auf die sie zeigt. Hinzufügen/Entfernen, **Speichern**. „Auf Standard zurücksetzen" stellt die 10 Vorlagen wieder her.
2. **Kontaktformular (Schritt „Ihre Angaben"):** **Einstellungen → Kursberater: Kontaktformular-Embed** — GoHighLevel-Embed einfügen. Leer = Schritt entfällt.
3. **Seite anlegen** mit `[livento_kurse_berater]`.

Im Berater erscheinen automatisch nur Aussagen, deren Themen auch wirklich Kurse haben.

---

## 9. Förderprogramme pflegen

Die Förderprogramme liegen **im Plugin** (nicht mehr als WP-Beiträge). Pflege unter **Livento Katalog → Förderprogramme**.

Pro Programm:
- **Titel**, **Slug** (optional, sonst automatisch), **Icon**.
- **Für** (Zielgruppe): Privatpersonen / Unternehmen.
- **Region**: Bundesweit oder einzelne Bundesländer (Strg/Cmd-Klick = mehrere).
- **Kurzbeschreibung** (Kachel) + **ausführliche Beschreibung** (Detailseite, Markdown: `**fett**`, `- Liste`, `[Text](URL)`).
- **Kurse-Förder-Tag** (optional): verknüpft das Programm mit dem Kursfilter → auf der Detailseite erscheint „Passende Kurse ansehen →" (`/kurse/?funding=<key>`).
- **Offizieller Link** (optional).
- **Förderberater: passt zu …** (siehe Abschnitt 10).

**Hinzufügen/Entfernen** über die Buttons, dann **Speichern**. „Auf Standard zurücksetzen" stellt die 6 Vorlagen wieder her.

**Anzeige:** Seite `/foerdermoeglichkeiten/` mit `[livento_foerderungen]`. Detailseiten entstehen automatisch unter `/foerdermoeglichkeiten/<slug>/` (mit SEO + Sitemap). Nach dem Anlegen neuer Programme einmal **Permalinks speichern**.

---

## 10. Förderberater einrichten

Geführter Berater im SGD-Stil: **Ihr Status → Ihre Qualifikation → Ihre Angaben → Ihr Ergebnis** (passende Förderungen inline).

Drei Stellschrauben:

1. **Schema (Fragen):** **Livento Katalog → Förderprogramme → Förderberater-Schema** (unten im Tab).
   - Pro **Status** ein Block: Label (z. B. „berufstätig") + Frage (z. B. „Ich bin berufstätig und …?").
   - **Qualifikationen** je Status: eine pro Zeile im Format `schlüssel | Anzeigetext`. Der Schlüssel ist optional (wird sonst aus dem Text erzeugt). **Schlüssel stabil halten** — die Programm-Zuordnung (Punkt 2) verweist darauf.
   - Vorbefüllt SGD-nah; frei änderbar. „Auf Standard zurücksetzen" möglich.
2. **Zuordnung (welches Programm bei welcher Antwort):** In jeder Förderprogramm-Karte (Abschnitt 9) den Block **„Förderberater: passt zu …"** aufklappen und die Qualifikationen ankreuzen, bei denen das Programm im Ergebnis erscheinen soll.
3. **Kontaktformular:** **Einstellungen → Förderberater: Kontaktformular-Embed**. **Leer = es wird automatisch das Kursberater-Formular verwendet.** Nur ausfüllen, wenn die Förder-Leads getrennt erfasst werden sollen.

**Seite anlegen** mit `[livento_foerder_berater]`.

---

## 11. GoHighLevel anbinden (Lead-Erfassung)

Es gibt zwei Wege für den „Ihre Angaben"-Schritt. **Empfohlen ist der Webhook** — dann muss der Interessent nur **einen** Button klicken.

### A) GHL Inbound-Webhook (empfohlen, ein Button)
Das Plugin zeigt ein eigenes schlankes Formular (Vorname, Nachname, E-Mail). Beim Klick auf **„Weiter"** sendet das Plugin die Daten serverseitig an euren GoHighLevel-**Workflow** und zeigt dann das Ergebnis — alles mit einem Button.

1. In GoHighLevel: **Automation → Workflows → neuen Workflow → Trigger „Inbound Webhook"** → die angezeigte **Webhook-URL kopieren**.
2. In WordPress: **Einstellungen → Kursberater: Kontaktformular → „GHL Inbound-Webhook-URL"** einfügen → Speichern. (Für den Förderberater analog; leer = Kursberater-Webhook wird genutzt.)
3. Im Workflow die Felder verarbeiten (E-Mail mit Kursprogramm senden, Tag setzen usw.). Ankommende Felder: `first_name`, `last_name`, `email`, `consent`, `source` (`kursberater`/`foerderberater`), `selection` (die gewählten Interessen/Qualifikationen), `page`.

### B) Embed-Code (Alternative)
Rohen iframe-Embed in das jeweilige „Kontaktformular"-Feld einfügen. Wird nur genutzt, wenn **keine** Webhook-URL gesetzt ist. Nachteil: das eingebettete Formular hat einen **eigenen** Absende-Button (zwei Buttons), und ein Absenden lässt sich technisch nicht sicher erkennen.

> **Empfehlung:** Webhook verwenden (A). Das ist die Ein-Button-Lösung.

---

## 12. SEO: Sitemap & Canonical

- **Sitemap:** `/livento-kurse.xml` enthält Katalog, alle Kurs-Detailseiten **und** alle Förder-Detailseiten. Sie hängt automatisch im Rank-Math-Sitemap-Index (`/sitemap_index.xml`).
- **Kanonische Heimat:** WordPress (`livento-bildung.de`). Die Campus-Connect-Subdomain liefert für Suchmaschinen `noindex` + Verweis hierher — kein Duplicate Content.
- Neue Inhalte tauchen nach dem nächsten Crawl/Cache in der Sitemap auf.

---

## 13. Cache & Purge-Webhook

- Kurse/Themen werden **zwischengespeichert** (Standard-TTL siehe Übersicht), damit die Seite schnell bleibt.
- **Sofort aktualisieren:** **Übersicht → Cache jetzt leeren**.
- **Automatisch bei Kursänderungen:** **Einstellungen → Purge-Secret** setzen (langer Zufallsstring) und in Campus Connect als Webhook hinterlegen (`POST /wp-json/livento/v1/purge`, Header `X-Livento-Purge-Secret`). Dann leert Campus Connect den Cache bei jeder Kursänderung selbst. Leer = nur TTL.

---

## 14. Problembehebung

| Symptom | Ursache / Lösung |
|---|---|
| „anon-Key konfiguriert ❌" | Key fehlt → Einstellungen → anon-Key eintragen. |
| Keine Kurse / 0 geladen | Key falsch, oder keine öffentlichen Kurse, oder API nicht erreichbar. Übersicht prüfen, Cache leeren. |
| Detailseiten zeigen 404 | **Permalinks → Speichern** klicken. |
| Förderberater-Ergebnis leer | In den Programmen unter „passt zu …" Qualifikationen ankreuzen; im Schema dieselben Schlüssel verwenden. |
| Förderberater hat keinen Formular-Schritt | Kein Embed hinterlegt → entweder Förder- oder Kursberater-Formular in den Einstellungen eintragen. |
| Sitemap „konnte nicht gelesen werden" | Permalinks speichern; in der Search Console erneut einreichen (`/livento-kurse.xml`). |
| Neuer Kurs fehlt im Katalog | Cache leeren (oder Webhook einrichten). |

---

## 15. Admin-Tabs im Überblick (Livento Katalog → …)

| Tab | Wofür |
|---|---|
| **Übersicht** | Status (Key, Kurszahl, Sitemap, Cache), „Cache leeren". |
| **Anleitung** | Diese Schritt-für-Schritt-Hilfen direkt im Backend. |
| **Shortcodes** | Alle Shortcodes mit Beispielen + Attributen zum Kopieren. |
| **Filter & Slugs** | Deep-Link-Parameter, Live-Filterwerte, Kurs-Slugs. |
| **Berater** | Kursberater: Interessen-Aussagen + Themen-Zuordnung. |
| **Förderprogramme** | Förderprogramme-Editor **und** Förderberater-Schema. |
| **Einstellungen** | anon-Key, Purge-Secret, beide GHL-Formulare. |

---

© Livento – Privates Bildungsinstitut für Pflege und Gesundheit UG (haftungsbeschränkt)
