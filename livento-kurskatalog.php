<?php
/**
 * Plugin Name:       Livento Kurskatalog (nativ)
 * Plugin URI:        https://campus-connect.livento-bildung.de
 * Description:        Rendert den oeffentlichen Kurskatalog aus Campus Connect serverseitig nativ in WordPress (statt iframe) — damit der Katalog auf der WordPress-Domain indexierbar wird. Holt die Daten aus der Supabase-View `public_offerings` via PostgREST, cached sie als Transient und erzeugt Karten, Detailseiten, Filter, Schema.org-JSON-LD und kanonische URLs.
 * Version:           1.71.0
 * Author:            Livento – Privates Bildungsinstitut für Pflege und Gesundheit UG (haftungsbeschränkt)
 * Update URI:        https://github.com/ChristianKarlConsulting/livento-kurskatalog
 * License:           proprietär
 * Text Domain:       livento-kurskatalog
 *
 * NUTZUNG: siehe README.md im selben Verzeichnis.
 *
 * Schnellstart:
 *   1. Datei nach wp-content/plugins/livento-kurskatalog/livento-kurskatalog.php kopieren.
 *   2. Unten LIVENTO_CC_ANON_KEY eintragen (Supabase → Project Settings → API → "anon public").
 *   3. Die bestehende WordPress-Seite „Kurse" (Slug aus LIVENTO_CC_BASE, Default: "kurse")
 *      verwenden, iframe entfernen, dort den Shortcode [livento_kurse] einfuegen.
 *   4. Plugin aktivieren (flusht die Rewrite-Regeln) ODER Einstellungen → Permalinks → Speichern.
 *
 * v1.1.0: Facet-Filterleiste (Format/Thema/Zielgruppe/Foerderung/Anerkennung/Ort + Suche),
 *         clientseitig auf den server-gerenderten Karten (SEO-neutral). Button-Textfarbe
 *         gegen Theme-Override abgesichert (!important).
 * v1.2.0: Volle Filter-Paritaet zum iframe-Widget — 12 Facet-Dimensionen (zusaetzlich Typ,
 *         CarePath-Level, Methodik, Dauer, Start-Monat, Verfuegbarkeit), Sortierung (7 Optionen),
 *         Max-Preis-Auswahl und Schalter (AZAV / USt-frei / kostenfrei).
 * v1.3.0: Zweiter Shortcode [livento_kurse_suche] — eigenstaendiges Suchfeld (z. B. Startseite),
 *         das zur Katalogseite /<base>/?q=… springt. Der Katalog uebernimmt ?q= + beliebige
 *         Facet-/Toggle-Parameter aus der URL (Deep-Linking).
 * v1.4.0: Eigene Kurs-Sitemap unter /kurse-sitemap.xml (die virtuellen Detail-URLs, die Rank
 *         Math nicht kennt) + automatische Einhaengung in den Rank-Math-Sitemap-Index.
 * v1.5.0: Admin-Dashboard im WP-Backend (Menue „Livento Katalog") mit Tabs Uebersicht/Status,
 *         Shortcodes (Liste + Beispiele) und Filter & Slugs (Deep-Link-Parameter, Live-Facet-
 *         Werte, Kurs-Slugs). Registry-basiert (livento_cc_shortcodes/_filter_groups) fuer
 *         einfache Erweiterung um kuenftige Campus-Connect-Inhalte.
 * v1.5.1: Fix Admin-Statuspruefung „anon-Key konfiguriert" — erkennt den Key jetzt am JWT-Format
 *         (der Zip-Build hatte zuvor auch das Platzhalter-Literal in der Pruefzeile ersetzt).
 * v1.6.0: Kurs-Sitemap umbenannt /kurse-sitemap.xml → /livento-kurse.xml, weil Rank Math das
 *         Muster *-sitemap.xml beansprucht und unsere URL mit 404 abfing.
 * v1.7.0: (1) Filter-UI als linke Sidebar mit aufklappbaren Gruppen (Treffer-Badge je Gruppe);
 *         Suche/Sortierung/Preis als Toolbar ueber der Liste. (2) anon-Key + Purge-Secret als
 *         Admin-Einstellung (DB-Option) statt Code-Konstante → ueberlebt Plugin-Updates; saubere
 *         Zip ohne eingebackenen Key moeglich. (3) Tab „Einstellungen" mit Webhook-URL.
 * v1.7.1: Fix Sitemap /livento-kurse.xml lieferte 301 (Trailing-Slash) → Google „konnte nicht
 *         gelesen werden". Handler laeuft jetzt mit Prioritaet 0 vor redirect_canonical + Canonical-
 *         Redirect fuer die Sitemap unterdrueckt → direktes 200.
 * v1.7.2: Auto-Updates aus dem GitHub-Repo (Plugin Update Checker) — neue Releases erscheinen als
 *         Ein-Klick-Update im WP-Dashboard. Diese Version einmal manuell installieren, danach automatisch.
 * v1.7.3: Fix Sitemap — X-Robots-Tag: noindex entfernt (Google lehnte die Sitemap sonst ab:
 *         „konnte nicht gelesen werden / 0 Seiten"). Sitemap-Dateien werden ohnehin nicht indexiert.
 * v1.7.4: Fix Fatal Error auf Detailseiten — kein fremdes Parsedown mehr (inkompatible Forks ohne
 *         setSafeMode() per Elementor/Plugins). Eigener Markdown-Renderer wird immer genutzt.
 * v1.7.5: [livento_kurse] unterstuetzt jetzt Attribute: limit (N Karten, kuratierter Block ohne
 *         Filter), sort (Default-Sortierung, server-seitig) und filters (yes/no).
 * v1.8.0: Zwei neue Shortcodes: [livento_kurse_berater] (mehrstufiger Kursberater → Deep-Link in den
 *         Katalog) und [livento_themen] (dynamische Themen-Kacheln aus der Themen-Aggregation).
 * v1.9.0: [livento_kurse_berater] auf SGD-Stil umgebaut: Stepper (Interessen → Starttermin → Angaben
 *         → Ergebnis), formulierte Interessen-Aussagen (Themen-Mapping), GoHighLevel-Formular-Schritt
 *         (Embed in den Einstellungen) und passende Kurse inline im Ergebnis.
 * v1.10.0: Admin-Tab „Berater" — Interessen-Aussagen + Themen-Mapping direkt im Backend editieren
 *          (hinzufügen/entfernen, Themen ankreuzen, „Auf Standard zurücksetzen"). Speicherung in DB.
 * v1.11.0: Förderprogramme als eigener Inhaltstyp — Admin-Tab „Förderprogramme" (Editor), Shortcode
 *          [livento_foerderungen] (Grid + Region-/Zielgruppen-Filter), eigene Detailseiten
 *          /foerdermoeglichkeiten/<slug> inkl. SEO + Sitemap, optionale Kurs-Verknüpfung (funding_key).
 * v1.12.0: Förderberater [livento_foerder_berater] (SGD-Stil: Status → bedingte Qualifikation →
 *          GHL-Formular → passende Förderungen), editierbares Schema + Programm-Zuordnung im Admin,
 *          eigenes Förder-Formular (Fallback auf Kursberater). Außerdem: [livento_kurse topics="…"]
 *          serverseitiger Themen-Vorfilter (Komma-Liste von Slugs).
 * v1.13.0: Admin-Tab „Anleitung" (Schritt-für-Schritt-Hilfe im Backend) + ANLEITUNG.md-Handbuch.
 *          Förder-Filter im Kurs-Look: linke Sidebar mit Checkboxen (alle Bundesländer), kein
 *          blaues Hover, Card-Farben explizit.
 * v1.14.0: [livento_kurse audience="…"] serverseitiger Zielgruppen-Vorfilter (kombinierbar mit
 *          topics); generischer Helper livento_cc_filter_by_field().
 * v1.15.0: Beide Berater: natives Ein-Button-Lead-Formular → GHL Inbound-Webhook (statt iframe mit
 *          eigenem Button). Der „Weiter"-Button sendet den Lead serverseitig (REST-Proxy) und geht
 *          weiter. Neue Einstellungen: GHL-Webhook-URL für Kurs- und Förderberater.
 * v1.16.0: Lead-Formular: zusätzliches Telefonfeld (optional, → GHL „E-Mail ODER Telefon") und
 *          Einwilligung als Pflichtfeld (ohne Häkchen kein „Weiter").
 * v1.17.0: FIX Lead-Versand: REST-Endpoint warf 403 (Session-nonce scheitert im REST-Kontext) →
 *          Lead kam nie bei GHL an. nonce entfernt, stattdessen Honeypot (Bot-Schutz). Neuer Admin-
 *          „Webhook testen"-Button (serverseitiger Test-POST). Durchgängige Du-Ansprache im
 *          öffentlichen Bereich. Kein blaues Fokus/Active mehr auf den Auswahl-Buttons (Livento-Grün).
 * v1.18.0: SEO — Meta-Description + FAQ. Die Detailseite nutzt jetzt die in Campus Connect gepflegte
 *          redaktionelle meta_description (Fallback: Beschreibung) für <meta description>/og:description,
 *          rendert einen aufklappbaren FAQ-Block und gibt zusätzlich schema.org/FAQPage-JSON-LD aus
 *          (Rich Results / KI-Suchmaschinen). Beide Felder kommen über die public_offerings-View.
 * v1.19.0: Kein eigener Plugin-Footer mehr auf den Detailseiten (Kurs + Fördermöglichkeit). Die
 *          „© … / Impressum / Datenschutz"-Zeile war eine Dublette zum seitenweiten WordPress-Footer
 *          und wird jetzt weggelassen — das Theme liefert Footer + Rechtslinks bereits selbst.
 * v1.20.0: CRO — Kursdetailseite. (1) „Auf einen Blick"-Cluster direkt unter den Fakten: Primaer-CTA
 *          „Jetzt Platz sichern" above the fold + optionaler Sekundaer-Button „Rueckruf vereinbaren".
 *          (2) Foerder-Hinweis am Preis (nur bei AZAV/Foerderung, Link zur Foerderseite). (3) Kompakte
 *          Trust-Zeile (AZAV / USt-frei / Zertifikat / Rechnung). (4) Plaetze-/Knappheitsanzeige aus
 *          show_availability_indicator + max_participants/enrolled_count. (5) Wiederholter gruener
 *          CTA-Block am Seitenende. (6) Mobiler Sticky-CTA (< 768px). Neue Einstellung
 *          „Beratung/Rueckruf-URL" (leer = Sekundaer-Button aus). FAQ + JSON-LD unveraendert (keine Dubletten).
 * v1.21.0: SEO-Dedup — Kurs-Detailseiten waren als Dublette von /kurse/ gewertet, weil Rank Math
 *          PARALLEL seine /kurse/-Canonical/Description/OG ausgab (Route = pagename=kurse). Das Plugin
 *          fuettert jetzt Rank Math ueber dessen Filter (canonical/description/title/robots/opengraph/
 *          json_ld) mit den Kurswerten und gibt seine eigenen Tags nur noch aus, wenn Rank Math FEHLT
 *          (Fallback). Ergebnis: genau EIN Canonical/og:url/description = eigene Kurs-URL. Zusaetzlich
 *          BreadcrumbList (Start › Kurse › Kursname), Course.offers.category=USt-frei und
 *          CourseInstance.courseWorkload. Behebt „kanonische URL = /kurse/" in der Search Console.
 * v1.22.0: Detail-Fixes. (1) <title> = nur „{Kursname} | Livento" (Format/Datum + 65-Zeichen-Kuerzung
 *          raus — die kappte das Startdatum mittendrin: „· 3."). (2) og:image-Metadaten ans Kursbild
 *          angeglichen: Rank Math gab Breite/Hoehe/Alt/Twitter weiter vom Default-Logo aus, weil nur die
 *          Bild-URL ueberschrieben war — jetzt og:image:alt/twitter:image/secure_url = Kursbild,
 *          og:image:width/height/type weggelassen (echte Größe des Remote-Bilds unbekannt). (3) Body-
 *          Klasse „lvk-course-detail" auf der Kurs-Detailroute (Theme-/CSS-Hook).
 *
 * v1.23.0: „Umfang" in der Faktenliste (Kurs-Einzelseite) kommt jetzt aus total_hours +
 *          hours_unit (UE bei „unterrichtsstunden", sonst „Std."); duration_minutes nur
 *          noch als Fallback (ohne „ca."-Praefix).
 * v1.24.0: CRO-Faktenbox „Auf einen Blick" auf der Kurs-Einzelseite — sticky rechte Spalte
 *          (Desktop) / Block direkt unter dem Intro (Mobile). Buendelt Format, Dauer/Umfang,
 *          Abschluss (neues Feld certificate_title), naechster Start, Kosten + Foerder-Pruef-
 *          Link, „Jetzt anmelden" und „Kostenlose Beratung" above the fold. Ersetzt die alte
 *          Faktenliste + den oberen CTA-Cluster (keine Dublette). Modul-Fix: „Aufbau & Module"
 *          nur noch bei echtem Modulinhalt (sonst Sektion aus), erstes Modul offen.
 * v1.25.0: Lead-Tracking — Kurs- und Foerderberater pushen bei erfolgreichem Lead ein
 *          GTM/GA4-Event in window.dataLayer: {event:'generate_lead', lead_type:'anfrage',
 *          lead_source:'kursberater'|'foerderberater'} (Quelle aus data-source). Greift nur
 *          beim nativen Lead-Formular (Webhook konfiguriert), nicht beim rohen GHL-Embed.
 * v1.26.0: Kurslisten — benannte, kriterienbasierte Kurs-Widgets fuer Landingpages/Ad-Kampagnen.
 *          Neuer Admin-Tab „Kurslisten": je Kampagne eine Liste anlegen (Kriterien Zielgruppe/
 *          Thema/Format/Anerkennung + Titel-Stichwort, plus Ueberschrift/Sortierung/Spalten/CTA),
 *          fertigen Shortcode kopieren. Neuer Shortcode [livento_kursliste id="…"] rendert eine
 *          eigenstaendige Sektion (Ueberschrift + Karten-Grid + optionaler „Alle ansehen"-CTA mit
 *          Deep-Link in den gefilterten Katalog); auch ad-hoc per Attributen nutzbar. Die Liste
 *          fuellt sich automatisch aus dem Katalog. „Pflichtfortbildungen" laesst sich so ueber das
 *          Titel-Stichwort abbilden (kein eigenes Facet), „Betreuungskraefte" ueber die Zielgruppe.
 * v1.26.1: FIX Kursliste — gruener Vollbreite-Balken ueber dem Widget. Ursache: manche Themes
 *          geben generischen <section>/<header>-Tags einen markenfarbenen Vollbreite-Hintergrund.
 *          Wrapper auf neutrale <div> umgestellt (wie der restliche Katalog) + defensiver
 *          Reset (background/border/padding 0) auf .lvk-kursliste.
 * v1.27.0: Kurse-Förder-Tags selbst verwaltbar. Neuer Abschnitt im Tab „Förderprogramme":
 *          eigene Förder-Tags fuers „Kurse-Förder-Tag"-Dropdown anlegen/umbenennen/entfernen
 *          (DB-Option livento_cc_funding_tags, gemerged mit den 9 CC-Standard-Werten in
 *          livento_cc_funding_labels()). Out-of-the-box vorbelegt mit „Anpassungsqualifizierung".
 *          HINWEIS: plugin-only — ein eigener Tag filtert nur Kurse, wenn Campus Connect denselben
 *          funding-Wert kennt; sonst reines Label/Verlinkungsziel.
 *
 * v1.71.0: Zweites H1 auf allen Detailseiten weg, und der Foerder-Titel wird nicht mehr
 *          mitten im Wort abgeschnitten.
 *          (a) /kurse/ und /foerdermoeglichkeiten/ tragen je ein Elementor-HTML-Widget mit
 *          dem Seitenintro, darin ein <h1>. Weil die Detailseiten ueber dieselbe WP-Seite
 *          ausgeliefert werden, stand dieses H1 auf jeder Detailseite ein zweites Mal —
 *          gemessen am 24.11.2026: 56 Kurs- und 25 Foerder-Detailseiten mit zwei H1, bei
 *          83 Seiten in der Sitemap. Dazu Text, der nur zur Uebersicht passt („Hier finden
 *          Sie unsere Weiterbildungen …", auf 57 Seiten) und den Kurs nach unten drueckte.
 *          Auf der Uebersichtsseite bleibt alles: dort ist das Intro-H1 richtig.
 *          (b) Der Foerder-Titel entstand aus mb_substr($titel, 0, 62) + Zusatz. Live stand
 *          damit „Weiterbildungsfoerderung Brandenburg (Weiterbildungsrichtlinie – Foerderung
 *          | Livento" — mitten im Wort, mit offener Klammer, 83 Zeichen. Jetzt kuerzt
 *          livento_cc_titel_kuerzen() an der Wortgrenze und nimmt eine Klammer, die erst
 *          nach dem Schnitt geschlossen wuerde, mit ihrem Inhalt weg. Gekuerzt wird nur,
 *          wenn der kurze Titel eindeutig bleibt: „Sachsen-Anhalt WEITERBILDUNG –
 *          individueller Zugang" und „… – betrieblicher Zugang" fielen sonst beide auf
 *          „Sachsen-Anhalt WEITERBILDUNG" zusammen — zwei Seiten mit demselben Titel sind
 *          schlimmer als ein langer. Ein Titel ohne Leerzeichen bleibt ebenfalls ganz.
 *          NICHT angefasst: die 29 Kurstitel ueber 60 Zeichen. Dort steht die
 *          unterscheidende Angabe oft hinten („… – Abendkurs", „… – Tageskurs"); ein
 *          automatischer Schnitt wuerde genau sie wegnehmen und zwei Seiten gleich
 *          benennen. Das ist Redaktionsarbeit oder ein eigenes Titelfeld.
 * v1.70.0: Block "Das wirst du koennen" auf der Kursdetailseite (braucht Campus Connect
 *          v4.63.0, Feld learning_outcomes). Steht zwischen Zielgruppe und "Dein Nutzen" —
 *          die erste Antwort auf "was kann ich danach", bevor argumentiert wird.
 *          Anlass war der Aufbauvergleich mit kenbi-akademie.com am 23.11.2026: dort tragen
 *          60 von 62 Seiten an dieser Stelle genau vier knappe Punkte (zusammen rund 42
 *          Woerter), die Leserin weiss nach etwa 70 Woertern, was sie danach kann. Unsere
 *          Seiten hatten dort nur "Dein Nutzen" mit im Schnitt 6 argumentierenden Punkten —
 *          Verkauf, keine Lernzielliste; das Koennen stand erst nach 400+ Woertern.
 *          Haken statt Punkt, zweispaltig ab 640px. Hoechstens 6 Punkte werden ausgegeben
 *          (wie die CHECK-Bedingung in Campus Connect).
 *          Leeres Feld = kein Block. Bewusst kein Rueckfall auf benefit, course_contents
 *          oder die Modul-Lernziele: ein automatisch gefuellter Block waere laenger und
 *          unpraeziser als keiner. Kennt die Datenbank die Spalte noch nicht, entfaellt der
 *          Block stillschweigend (Detailseite liest select=*).
 *
 * v1.69.0: Bewertungen je Kurs statt je Durchgang (braucht Campus Connect v4.62.0).
 *          Sterne und aggregateRating gibt es seit v1.59.0, sie erschienen aber nirgends: die
 *          Schwelle liegt bei 5 freigegebenen Bewertungen (LIVENTO_CC_RATING_MIN), und weil
 *          jeder Durchgang ein eigenes Programm ist, zaehlt jeder fuer sich. Am 23.11.2026
 *          lagen 19 Bewertungen auf 17 Kursen, hoechstens 2 je Kurs — und 15 davon auf
 *          Durchgaengen, die nicht mehr oeffentlich sind, also dauerhaft unsichtbar. Ein Kurs
 *          konnte ueber Jahre sammeln und zeigte nie Sterne; das war kein Mengen-, sondern ein
 *          Strukturproblem.
 *          Jetzt holt livento_cc_get_group_ratings() die Gruppenwerte aus Campus Connect
 *          (fn_public_offering_group_ratings, gruppiert wie die Katalogkarten) und
 *          livento_cc_rating() nimmt sie, sobald es zum Slug welche gibt. Das wirkt an einer
 *          Stelle fuer Karte, Detailseite, aggregateRating und die Sortierung „Beste
 *          Bewertung" — die Funktion ist seit v1.59.0 die einzige Quelle.
 *          Schwelle und UWG-Hinweis bleiben unveraendert: was im Schema steht, steht auch
 *          sichtbar auf der Seite.
 *          Faellt der Abruf aus, gelten weiter die Einzelwerte aus public_offerings.
 *          Heute noch ohne sichtbare Folge (hoechstens 2 von 5) — es ist der Unterschied
 *          zwischen „irgendwann erscheinen Sterne" und „es erscheinen nie welche".
 *
 * v1.68.0: Canonical je Kursgruppe statt je Termin. Jeder Durchgang ist in Campus Connect ein
 *          eigenes Programm und hat darum eine eigene Detailseite — am 23.11.2026 standen 95
 *          Termin-Adressen fuer 56 Kurse, 39 davon waren Zweitfassungen in 23 Gruppen mit
 *          mehreren Terminen (gleicher Titel, gleicher Text, nur anderes Startdatum), jede mit
 *          Canonical auf sich selbst und auf index,follow. Dazu stand jede davon in
 *          /livento-kurse.xml. Google musste sich eine aussuchen, Links und Autoritaet
 *          verteilten sich auf bis zu fuenf Adressen desselben Kurses.
 *          Jetzt: livento_cc_canonical_slug() bildet jeden Termin-Slug auf den Gruppenfuehrer ab
 *          (dieselbe Gruppierung wie die Katalogkarten, v1.58.0). livento_cc_seo_values() setzt
 *          damit Canonical, og:url, die Course-URL im JSON-LD und die letzte Brotkrume; die
 *          Sitemap nimmt nur noch Gruppenfuehrer auf.
 *          Kein noindex auf den Zweitfassungen — noindex plus Canonical auf eine andere Seite
 *          sind widerspruechliche Signale. Die Termin-Seiten bleiben erreichbar und buchbar.
 *          Offen: dauerhaft stabiler waere eine eigene Kurs-URL mit den Terminen nur zur
 *          Auswahl. So wandert das Canonical, wenn ein Durchgang auslaeuft.
 *
 * v1.67.0: Abschnitt „Für dein Team“ auf der Kursdetailseite: Inhouse-Anfrage direkt am Kurs
 *          (Termin-Kurse und Weiterbildungen, auch mit Bildungsgutschein). REST-Proxy
 *          /livento/v1/inhouse-anfrage → submit-inhouse-inquiry, Kurs als „Bezug zu Kurs“ (Titel
 *          serverseitig ueber den Slug). Selbstlernkurse: Hinweis auf E-Learning fuer Einrichtungen.
 *          Anfragekurse ohne Abschnitt (ihre Kursanfrage hat „Für meine Einrichtung“).
 *
 * v1.66.0: Formvergleich „Welche Form passt zu dir?“ auf der Kursdetailseite — Gruppen „gleiches
 *          Thema, andere Form“ als Kurstitel (Vorgabe: 5 Gruppen, vom Nutzer bestaetigt; Pflege unter
 *          Einstellungen → Formvarianten). Karten mit Angebotsart/Form/Zeitmodell, Umfang, Start,
 *          Preis, Abschluss; aktuelle Seite markiert. Bewusst nicht ueber Kursfamilien (= Durchgaenge).
 *          Dazu: Dokumente namens „Teilnahmebescheinigung …“ heissen ueberall Teilnahmebescheinigung
 *          (vorher „Zertifikat mit Abschlusstest“, z. B. Pflichtfortbildung Betreuungskraefte).
 *
 * v1.65.0: „Aufbau & Module“ zeigt je Modul das Lernziel (Groblernziel aus Campus Connect v4.58.0,
 *          learning_goal in public_offering_modules / public_offering_entry_modules), hervorgehoben
 *          ueber Beschreibung und Lektionen. Formulierung bewusst unveraendert (Lehrplan-Stil). Module
 *          nur mit Lernziel erscheinen jetzt auch. Ohne v4.58.0 bleibt alles wie bisher.
 *
 * v1.64.0: Fachbeiträge ↔ Kurse. Je Blogbeitrag Post-Meta _livento_cc_ziel (k:<Kurstitel> bzw.
 *          t:<Themenbereich des CampusTickets>), gepflegt in der Box „Passende Kurse (Livento)“ im
 *          Beitrags-Editor. Kursdetailseite: „Fachbeiträge zum Thema“ (bis zu 3, neueste zuerst).
 *          Unter dem Beitrag: „Passende Weiterbildungen“ (Kurse mit Start/Preis bzw. Bereich auf der
 *          Ticketseite; der Link oeffnet den Bereich). Erstzuordnung (48 Beitraege, vom Nutzer
 *          bestaetigt) wird einmal uebernommen, nur wo noch nichts gesetzt ist. Zuordnung am Titel,
 *          damit sie neue Durchgaenge ueberlebt; Kurse, die gerade nicht im Katalog sind, fallen weg.
 *
 * v1.63.0: Selbstlernkurse auf Anfrage ohne „Termin“ (7 von 21 Anfragekursen): Kartenabzeichen und
 *          Sticky-Leiste „Auf Anfrage“, Button und Formular „Kurs anfragen“, Feld „Gewünschter Start“,
 *          Danke-Meldung ohne „passenden Termin“.
 *          Erkennung zentral in livento_cc_is_self_learning (Angebotsart, Kurstyp oder Format).
 *
 * v1.62.0: Kursdetailseite — drei Bausteine aus der Wettbewerber-Analyse (19.11.2026):
 *          „So läuft es ab“ in 3–4 Schritten je Angebotsart (Selbstlernkurs, Termin je Format,
 *          Anfragekurs; nicht bei Bildungsgutschein, das erklaert der eigene Abschnitt), „Dein
 *          Abschluss“ (Zertifikat, Pruefung, RbP-Punkte, Echtheitspruefung per Pruefnummer — nur aus
 *          gepflegten Feldern) und die Karte „Fragen zum Kurs?“ mit Ansprechpartner, Antwortzeit,
 *          Telefon und E-Mail (Einstellungen → Ansprechpartner; Name/Foto nicht im Code). Dazu:
 *          Nach einem Plugin-Update leert das Plugin seinen Cache einmal selbst; Ticketseite ohne
 *          Themenbereiche heisst die Liste „Alle Kurse“ statt „Weitere Kurse“.
 *
 * v1.61.0: Ticketseite — Kurse nach Themenbereichen als Kacheln (Campus Connect v4.57.0, #295). Vorher
 *          gruppierte die Liste nach Kursthemen (Website-Filter), die an Selbstlernkurse fast nie
 *          vergeben sind: 268 von 273 Kursen standen unter „Weitere Themen“. Jetzt je Themenbereich
 *          (wie „Meine Kurse“, topic_area aus public_bundle_courses) eine Kachel mit Kursanzahl im
 *          Kartenstil der E-Learning-Seite (elt-paket), Klick oeffnet alle Kurse des Bereichs, Suchfeld
 *          ueber alle Bereiche, Rechtsgrundlagen nur als Kuerzel (volle Fassung als Tooltip). Ohne
 *          JavaScript stehen alle Bereiche mit allen Kursen untereinander (indexierbar). Ohne Bereich:
 *          „Weitere Kurse“. Liefert Campus Connect noch keinen Bereich, gibt es nur die Liste.
 *
 * v1.60.0: Sterne auf der Ticketseite ([livento_tarif]): Schnitt aller veroeffentlichten Bewertungen
 *          der im Ticket enthaltenen Kurse, gewichtet nach Anzahl, gleiche Schwelle (5) und gleicher
 *          Hinweis wie beim Kurs, beschriftet „… Bewertungen der Kurse in diesem Ticket“. Quelle:
 *          View program_rating_summary (anon, nur Schnitt und Anzahl je Lehrgang, gecached).
 *          Bewusst KEIN aggregateRating im Product-Schema — die Bewertungen gelten den Kursen,
 *          nicht dem Ticket. CSS der Sterne jetzt in livento_cc_rating_css() (Katalog + Ticket).
 *
 * v1.59.0: Sterne sichtbar und erst ab 5 Bewertungen (LIVENTO_CC_RATING_MIN). Vorher stand die
 *          Bewertung nur im Schema (AggregateRating, z. B. „5,0 aus 1 Bewertung“), auf der Seite
 *          selbst nirgends — Google verlangt, dass markierte Bewertungen sichtbar sind. Jetzt eine
 *          Quelle (livento_cc_rating) fuer Schema, Detailseite, Karte und Sortierung „Beste
 *          Bewertung“; darunter keine Sterne, kein Schema, Sortierung behandelt den Kurs als
 *          unbewertet. Auf der Detailseite der Hinweis nach § 5b Abs. 3 UWG, wie Bewertungen
 *          zustande kommen (Campus Connect prueft seit v4.51.1 den Platz im Kurs).
 *
 * v1.58.1: Umschalter ohne Scrollleiste: kurze Beschriftungen (Fortbildungen, Workshops) passen auf
 *          dem Desktop in eine Zeile, schmal bricht er um statt seitlich zu scrollen (unter Windows stand
 *          sonst eine Scrollleiste darunter und der erste Knopf war angeschnitten). Abschnitte unveraendert.
 *
 * v1.58.0: Katalog nach Angebotsart gegliedert (Campus Connect v4.47.0, #282–#285). Anlass: Feedback,
 *          dass Live-Online-Kurse und weitere Programme ohne Trennung nach Abschluss oder Dauer in
 *          einer Uebersicht standen.
 *          - Umschalter ueber dem Katalog: Alle · Weiterbildungen mit Abschluss · Fort- und
 *            Pflichtfortbildungen · Workshops und Kompaktkurse · Selbstlernkurse (?art=…).
 *            „Alle“ zeigt eigene Abschnitte mit Ueberschrift (auch ohne JS — gut fuers SEO).
 *          - Eine Karte je Kurs: gleiche Titel/Art = eine Karte mit „Weitere Termine“ (jeder
 *            Termin behaelt seine Detailseite). Filter greifen je Termin, die Karte zeigt die Union.
 *          - Karte: Angebotsart, Umfang + Laufzeit, Abschluss, Zeitmodell, Abzeichen Pflichtfortbildung.
 *          - Filter: Umfang nach Gesamtstunden (statt Sitzungsdauer), Zeitmodell, Schalter
 *            Pflichtfortbildung. Typ, CarePath-Level, Methodik und Verfuegbarkeit nicht mehr in
 *            der Seitenleiste (URL-Parameter wirken weiter).
 *          - Kurslisten: Kriterien „Angebotsart“ und „Nur Pflichtfortbildungen“ (statt Titel-Stichwort).
 *          Kennt die Datenbank die neuen Spalten noch nicht (vor v4.47.0), faellt der Abruf auf die
 *          alte Spaltenliste zurueck und die Angebotsart wird aus dem Umfang vorgeschlagen.
 *
 * v1.57.0: Neue Supabase-Schluessel (Campus Connect v4.36.0). Das Feld „Supabase anon-Key“ nimmt
 *          jetzt auch den Publishable Key (sb_publishable_…) an; bisher verlangte die Pruefung einen
 *          Punkt im Schluessel (JWT-Format) und verwarf ihn. Der Legacy-Anon-JWT (eyJ…) gilt weiter,
 *          bis Supabase ihn abschaltet. Secret Keys (sb_secret_…) werden ausdruecklich abgewiesen —
 *          sie gehoeren nie in WordPress.
 *
 * v1.56.0: Bildungsgutschein fuer AZAV-zugelassene Kurse sichtbar — auch ohne Massnahmenummer.
 *          Einzige Quelle ist is_azav_relevant; die Nummer folgt je Durchgang mit dem ersten Gutschein.
 *          Karte/Detail: Badge „Bildungsgutschein moeglich“ (statt „AZAV“), Filter-Schalter heisst
 *          „Bildungsgutschein“ (?azav=1 bleibt). Detailseite: Abschnitt „Foerderung mit
 *          Bildungsgutschein“ (#bildungsgutschein), Faktenbox mit klarer Aussage und Massnahme-Nr.
 *          bzw. „folgt mit dem ersten Bildungsgutschein“. Hub-Seite /foerdermoeglichkeiten/<bildungs-
 *          gutschein>/ listet alle AZAV-Kurse serverseitig. Neues Feld „Text fuer Kursseiten“ am
 *          Foerderprogramm. Kurslisten: Kriterium bzw. Attribut bildungsgutschein="1". AZAV-Kurse
 *          tragen im Filter immer den Foerder-Tag azav_bildungsgutschein.
 *
 * v1.55.0: Massnahmenummer zusaetzlich in der Faktenbox „Auf einen Blick“ (unter
 *          „Kosten“, mit Hinweis auf den Bildungsgutschein).
 *
 * v1.54.0: Massnahmenummer auf der Detailseite (Campus Connect v4.22.0):
 *          public_offerings.azav_massnahmenummer, nur bei AZAV-Durchgaengen mit
 *          gepflegter Nummer. Zeile unter der Kurs-Nr. mit Hinweis auf den
 *          Bildungsgutschein (Agentur fuer Arbeit / Jobcenter).
 *
 * v1.53.0: Hinweis unter dem Terminplan (Campus Connect v4.17.0, modulare Einstiege):
 *          public_offerings.schedule_note, z. B. „Zwischen den Modulen koennen
 *          unterrichtsfreie Wochen liegen.“ Wird nur gezeigt, wenn gesetzt.
 *
 * v1.52.1: Anfrageformular in CI-Gruen — der Absende-Button ist ein <button>, die
 *          CI-Regeln galten nur fuer a.lvk-cta, das Theme faerbte ihn blau. Haekchen
 *          und Auswahlpunkte ebenfalls gruen (accent-color).
 *
 * v1.52.0: Anfragekurse (Campus Connect v4.12.0). Kurse ohne Termin kommen als
 *          offering_type 'on_request' aus public_offerings: Karte mit Badge „Termin auf
 *          Anfrage" und „Jetzt anfragen", ganz am Ende der Sortierung „Naechster Start".
 *          Detailseite mit Anfrageformular (REST-Proxy /wp-json/livento/v1/kursanfrage →
 *          submit-course-inquiry). Tritt ein Anfragekurs hinter einem verknuepften Termin
 *          zurueck, liest die Detailseite public_inquiry_courses und zeigt „Naechster Termin".
 *
 * v1.51.0: Der Purge leert jetzt auch den Seitencache von WP-Optimize. Bis hier entwertete
 *          er nur die eigenen Transients — WP-Optimize lieferte die fertige Kursseite aber
 *          weiter aus dem Seitencache, z. B. ohne einen gerade freigegebenen Dozenten.
 *
 * v1.50.0: 301-Weiterleitungen der alten Ticket-Seiten (Pflicht-/Komplett-/RollenTicket und
 *          die alten Namen PflichtStart/PflegeKomplett/RollenPlus) auf /e-learning/campus-ticket/.
 *
 * v1.49.1: Fix — der Lernpfade-Abschnitt erschien nicht: Auf Prod heisst die Familie
 *          'campusticket', auf Dev 'ticket'. Beide Schluessel werden jetzt erkannt.
 *
 * v1.49.0: CampusTicket-Seite: Abschnitt „Lernpfade" (Anker #lernpfade) zwischen Preis und
 *          „So läuft's ab". Nur fuer die Familie mit key 'ticket' — die alten Ticket-Seiten
 *          bleiben unveraendert. Reiner Text, keine Daten aus Campus Connect: Die Pfade selbst
 *          sind nur in der App sichtbar (Entscheidung v4.0.0).
 *
 * v1.48.0: CampusTicket-Nachkauf von Lizenzen (Campus Connect v4.0.0).
 *          Campus Connect berechnet den Preis anteilig bis Vertragsende und schickt
 *          einen signierten Beleg (?cc_topup=...) mit. Das Plugin prueft die
 *          Signatur (HMAC-SHA256, Secret unter „Einstellungen"), setzt daraus den
 *          Warenkorbpreis und legt den Beleg als Positions-Meta _cc_topup ab. Der
 *          Webhook prueft den Beleg ein zweites Mal gegen den bezahlten Betrag und
 *          stockt erst dann das Lizenzpaket auf.
 *          Das Nachkauf-Produkt (Produkt-ID unter „Einstellungen") laesst sich NUR
 *          mit gueltigem Beleg in den Warenkorb legen — es steht auf 0 EUR, ohne
 *          diese Sperre gaebe es eine Gratisbestellung.
 *
 * v1.47.0: Faktenbox zeigt die Art der Pruefung (Campus Connect v3.228.0).
 *          Neue Zeile "Pruefung" aus dem Feld exam_title, direkt ueber "Abschluss".
 *          Ist das Feld nicht gepflegt, erscheint die Zeile gar nicht — es wird nie
 *          automatisch "keine Pruefung" behauptet. Die Angabe ist rein redaktionell
 *          und hat in Campus Connect bewusst NICHTS mit has_exam/exam_types zu tun;
 *          an denen haengen dort Notenerfassung und Zeugnisausstellung.
 *          Zwei weitere Aenderungen derselben Version:
 *          - Der feste Vorspann „Qualifiziertes Zertifikat ‚…'" vor der Abschluss-
 *            bezeichnung ist ENTFALLEN. Er war eine Qualitaetsaussage des Plugins
 *            selbst und bei einem pruefungsfreien Workshop zu viel versprochen.
 *            Angezeigt wird jetzt der in Campus Connect gepflegte Wortlaut.
 *          - Einzeltermine (scheduled_courses) koennen erstmals eine Abschluss-
 *            bezeichnung fuehren; die View lieferte dort bis v3.228.0 fest NULL.
 *          Am Datenabruf war nichts zu tun: die Detailseite liest select=*.
 *
 * v1.46.0: KI-Kennzeichnung der Kursbilder (Campus Connect v3.220.0).
 *          Kursbilder liegen nicht in der WordPress-Mediathek, sondern im Supabase-
 *          Bucket public-course-images. Das Plugin "Livento KI-Kennzeichnung" kann
 *          sie deshalb nicht erfassen — zu ihnen gibt es keinen WordPress-Anhang,
 *          an dem ein Haekchen haengen koennte. Die Kennzeichnung kommt darum aus
 *          Campus Connect mit: neues Feld public_image_ai_generated am Angebot,
 *          gepflegt unter "Oeffentliche Kurse", durchgereicht in public_offerings.
 *          Ist es gesetzt, sitzt auf Karte und Detailbild eine Plakette
 *          "KI-generiert" in der unteren rechten Ecke (Petrol #004D33, heller
 *          Ring). Text ueber den Filter livento_cc_ki_text aenderbar.
 *          Eigene Klassen im lvk-Namensraum statt der Klassen des anderen Plugins:
 *          sonst haenge der Katalog an einem Stylesheet, das nur geladen wird,
 *          solange in der Mediathek mindestens ein Bild angehakt ist.
 *          ACHTUNG: Die Feldliste in livento_cc_get_offerings() musste erweitert
 *          werden — die Detailseite holt select=*, die Kursliste nicht.
 *
 * v1.45.1: Der Dozenten-Teil bekommt im Backend einen eigenen Platz. Bis hier war er
 *          nur als Einstellungsfeld sichtbar — wer wissen wollte, WARUM jemand nicht
 *          auf der Seite steht, fand im Plugin keinen Anhaltspunkt.
 *          Neuer Reiter "Dozenten": Anzahl freigegebener Profile, Tabelle mit Name,
 *          Funktionsbezeichnung, Foto-Status und Stichworten, der Shortcode zum
 *          Kopieren, die Einrichtungsschritte und — der eigentliche Punkt — die
 *          vollstaendige Liste der Bedingungen, unter denen ein Profil ueberhaupt
 *          erscheint, samt Hinweis, dass die Ursache IMMER in Campus Connect liegt
 *          und nie im Plugin. Warnung bei null Profilen, Hinweis bei weniger als drei
 *          (eine Personenseite mit einem Eintrag wird als duenn bewertet).
 *          Ausserdem: [livento_dozenten] steht jetzt in der Shortcode-Registry (Reiter
 *          "Shortcodes") und die Anleitung hat einen eigenen Abschnitt 8; die
 *          bisherigen Abschnitte 8 und 9 ruecken auf 9 und 10.
 *          Reine Backend-Ergaenzung — an der Ausgabe auf der Website aendert sich
 *          nichts.
 *
 * v1.45.0: Dozentinnen und Dozenten werden sichtbar (Campus Connect Feature 181).
 *          Neuer Shortcode [livento_dozenten] rendert alle freigegebenen Profile als
 *          Raster: rundes Portrait, Name, Funktionsbezeichnung, Qualifikations-Stichworte
 *          und eine Kurzvorstellung, deren Rest sich aufklappt. Dazu ein Abschnitt
 *          "Wer dich unterrichtet" auf jeder Kursdetailseite.
 *          Der Shortcode ist bewusst URL-frei — er funktioniert auf jeder Seite, auf die
 *          er gesetzt wird. Es gibt KEINE Slug-Konstante, keine Rewrite-Regel und keine
 *          Detailseiten je Dozent: sechs duenne Personenseiten waeren eine SEO-Last,
 *          kein Gewinn. Die Verlinkung vom Kurs zur Dozentenseite haengt an der Option
 *          "URL der Dozentenseite" (Tab Einstellungen); ist sie leer, wird ohne Link
 *          gerendert — nie ein toter Link.
 *          Profile OHNE Foto erscheinen trotzdem, in einer eigenen Textdarstellung mit
 *          farbiger Kante statt als Karte mit leerem Bildbereich. Wer freigegeben ist,
 *          entscheidet allein Campus Connect: dort muss der Dozent eingewilligt UND die
 *          Institutsleitung freigegeben haben. Das Plugin filtert nichts nach — die View
 *          public_instructors liefert ausschliesslich freigegebene Profile und nur sieben
 *          Spalten (nie E-Mail, Telefon, Adresse, Bankdaten).
 *          Neu im JSON-LD: Person-Eintraege als ItemList auf der Dozentenseite und als
 *          instructor am hasCourseInstance der Kursseiten (loest instructor_name ab, das
 *          nur fuer Einzeltermine gefuellt war).
 *          livento_cc_rest_get() nimmt jetzt einen Pfad entgegen; die Tabelle war bis
 *          hier fest verdrahtet.
 *
 *          SCHEMA: Bis v1.44.0 hatte KEINE Entitaet im Plugin eine @id — jede Nennung
 *          war fuer eine Suchmaschine ein eigenes, unverbundenes Objekt. Ein Dozent
 *          taucht aber auf der Dozentenseite UND auf jeder seiner Kursseiten auf; ohne
 *          @id waeren das ebenso viele verschiedene Personen, und genau der kumulierte
 *          Autoritaetsbezug ginge verloren. Neu:
 *          - Person bekommt eine stabile @id aus der Campus-Connect-UUID, verankert auf
 *            der Dozentenseite (<dozentenseite>#dozent-<uuid>). Bewusst die UUID und
 *            nicht der Name: die Kennung muss eine Namensaenderung ueberleben.
 *          - Das id-Attribut der Karte traegt dasselbe Fragment, die @id zeigt also auf
 *            ein real vorhandenes Element statt ins Leere.
 *          - Auf Kursseiten steht nur eine schlanke Referenz (ohne description und
 *            knowsAbout) — denselben Langtext auf fuenf Kursseiten zu wiederholen bringt
 *            keinen zusaetzlichen Aussagewert; die @id stellt die Verbindung her.
 *          - Neuer Organisationsknoten mit fester @id
 *            (https://livento-bildung.de/#organization); Kurs-provider und Dozenten-
 *            worksFor zeigen jetzt auf denselben Knoten.
 *          - Die Karten-Ueberschrift ist h2 auf der Dozentenseite (dort steht nur die
 *            H1 darueber) und h3 im Kursseiten-Abschnitt unter dessen h2 — keine
 *            uebersprungene Ebene.
 *          Titel, Meta-Description und Canonical der Dozentenseite kommen wie bei jeder
 *          normalen WordPress-Seite aus dem Theme bzw. Rank Math — das Plugin greift
 *          dort bewusst nicht ein.
 *
 * v1.43.0: Testzugang ueber die Ticketseite anfragen (Campus Connect Feature 158).
 *          Neuer Abschnitt "Erst testen, dann entscheiden" auf den Ticket-Detailseiten
 *          (Anker #testzugang) mit eigenem Formular; unter jedem Kaufknopf steht dezent
 *          "oder erst 14 Tage testen". Der Kaufknopf bleibt bewusst die Hauptaktion —
 *          ein gleich grosser Testknopf daneben holt zuverlaessig auch die ab, die
 *          ohnehin gekauft haetten.
 *          Das Formular geht an den WP-REST-Proxy /wp-json/livento/v1/trial, der die
 *          Anfrage serverseitig an die Campus-Connect-Function submit-trial-request
 *          weiterreicht (kein CORS, anon-Schluessel bleibt aus der Seite draussen).
 *          Nach bestaetigtem Eingang Redirect auf /danke-anfrage/ — damit ist die
 *          Conversion als URL-Ziel messbar, nicht nur als dataLayer-Event.
 *          ZWEI Haekchen: Datenschutzhinweis (Pflicht) und Werbeeinwilligung
 *          (freiwillig). Den Testzugang von der Werbeeinwilligung abhaengig zu machen
 *          waere eine unzulaessige Kopplung — die Datenverarbeitung selbst ist
 *          Vertragsanbahnung und braucht keine Einwilligung.
 *          Die bundle_id der angesehenen Setting-Variante reist mit, damit in Campus
 *          Connect genau das angefragte Ticket freigegeben wird. Sperre je IP und
 *          E-Mail per Transient, Honeypot wie beim Lead-Formular. Neue Diagnose im
 *          Admin-Tab "Einstellungen".
 *
 * v1.40.0: Der Preis sagt endlich, WORAUF er sich bezieht. Bis hier stand auf den
 *          Ticket-Seiten "348,00 € / Jahr" — direkt neben einem Rechner, der auf 10
 *          Beschaeftigte voreingestellt ist, aber ohne einen Halbsatz, der die beiden
 *          Zahlen zueinander ins Verhaeltnis setzt. Ein Erstbesucher las daraus
 *          "348 € pro Kopf" und rechnete sich das PflichtTicket bis zu 15-fach zu
 *          teuer (tatsaechlich: 348 € fuer bis zu 15 Personen = 1,93 € je Person und
 *          Monat). Preis-Ambiguitaet loest sich beim Leser pessimistisch auf — die
 *          Seite versteckte damit ausgerechnet ihr staerkstes Argument.
 *          Aufgeloest wurde es bislang NUR in einer zugeklappten FAQ am Seitenende,
 *          und die nannte nicht einmal Zahlen ("bis zu einer bestimmten Teamgroesse").
 *          Neu: (1) Bezugsgroesse direkt am Preis — Faktenbox "fuer die ganze
 *          Einrichtung, bis 15 Beschaeftigte", Variantenzeile "Gesamtpreis fuer alle
 *          10 Beschaeftigten · 2,90 € pro Person und Monat"; (2) die Staffel steht
 *          offen als Tabelle unter dem Rechner (sie stand vorher NIRGENDS auf der
 *          Seite); (3) der Rechner-Hinweis sagt, dass die Zahl immer der Gesamtpreis
 *          ist und nicht mit der Kopfzahl multipliziert wird.
 *          ALLES AUS DEN STAFFELN ABGELEITET, nichts hartkodiert — und das ist keine
 *          Kosmetik: Nur das PflichtTicket hat ueberhaupt eine Pauschale. Komplett-
 *          Ticket (99 €/Person/Jahr) und RollenTicket (49 €/Person/Jahr) rechnen ab
 *          der ERSTEN Person pro Kopf; ein hartkodiertes "fuer die ganze Einrichtung"
 *          waere dort falsch und haette das gespiegelte Missverstaendnis erzeugt
 *          (99 € fuer den ganzen Betrieb). Daher liefert livento_cc_scope_note() den
 *          Bezug aus dem pricing_mode, und die Staffeltabelle rendert nur, wo es
 *          wirklich eine Staffel gibt (Familie mit genau einem Plan und >1 Staffel).
 *          Die neuen Textbausteine kommen wie Netto und Steuerhinweis fertig
 *          formatiert vom Server — im Browser wird weiterhin KEIN Preis gerechnet,
 *          sonst gaebe es eine zweite Mathematik, die auseinanderlaufen kann.
 *
 * v1.39.0: Das Hero-Band laeuft jetzt randlos ueber die ganze Seitenbreite, statt im
 *          1200px-Container zu stecken. Zur Einordnung, warum das hier Arbeit ist und
 *          auf /foerdermoeglichkeiten/ nicht: Die Vorbildseite steht auf Astras
 *          Full-Width-Template (Body-Klasse ast-page-builder-template), ihr Container
 *          deckelt gar nicht erst — .lv-ahero ist dort gratis randlos. Die Ticket-Seiten
 *          stehen auf ast-plain-container (1200px) und muessen ausbrechen:
 *          margin-inline:calc(50% - 50vw) zieht das Band an die Raender,
 *          padding-inline:max(1.25rem, calc(50vw - 37.5rem)) holt den INHALT wieder auf
 *          die 1200px-Spur, damit er buendig zum Rest der Seite bleibt.
 *          DIE FALLE: 100vw zaehlt die Scrollbar mit, 100% nicht — auf Windows mit
 *          klassischer Scrollbar ragt das Band ~15px ueber den Viewport und erzeugt
 *          Querscroll auf jeder Ticket-Seite. Dagegen html{overflow-x:clip}. BEWUSST clip
 *          und nicht hidden: hidden macht das Element zum Scroll-Container und bricht
 *          damit position:sticky (.lv-fb) und Anker-Sprunge (#rechner) — clip kappt nur
 *          den Ueberstand. Untergrenze dafuer ist Safari 16; darunter bleibt der
 *          Querscroll, das ist bewusst in Kauf genommen.
 *          Der Alternativweg waere gewesen, die drei Seiten auf das Full-Width-Template
 *          zu stellen (identischer Mechanismus wie die Vorbildseite, kein vw noetig) —
 *          verworfen, weil er einen manuellen Handgriff je Seite braucht.
 *
 * v1.38.0: Der Kopf der Ticket-Detailseiten traegt jetzt dieselbe Bildsprache wie die
 *          Orientierungsseiten /kurse/ und /foerdermoeglichkeiten/. Inhaltlich fehlte
 *          dort nichts (H1, Claim, Beschreibung, Highlights und Faktenbox stehen seit
 *          v1.36.0) — es fehlte die Fassung: alles lag als nackter Text auf Weiss.
 *          Neu: .lv-tarif-band legt den warmen Verlauf von .lv-ahero unter Kopf UND
 *          Raster, dazu Eyebrow „E-Learning-Ticket", Qurova-Headline in CI-Gruen und
 *          der Lead in der Groesse von .lv-ahero__sub.
 *          BEWUSST ANDERS als .lv-ahero: kein zentrierter 80px-Hero und keine CTA-
 *          Buttons im Kopf. /kurse/ und /foerdermoeglichkeiten/ sind Orientierungs-
 *          seiten — dort stoebert der Besucher und braucht Einordnung. Die Ticket-
 *          seiten sind Entscheidungsseiten: wer hier landet, kam ueber /e-learning/
 *          und will wissen, was drin ist und was es kostet. Ein hoher, luftiger Hero
 *          schoebe Preis und CTA unter die Falz, und die Faktenbox haelt beides schon
 *          oben — ein zweiter CTA im Kopf waere nur eine Dublette 300px daneben.
 *          Kein Full-Bleed via 100vw-Trick: bricht in Containern mit overflow und
 *          erzeugt bei sichtbarer Scrollbar Querscroll. Das Band fuellt den Theme-
 *          Container und ist mit border-radius abgesetzt.
 *
 * v1.37.0: Die Danke-Seite sagte die Unwahrheit: „Sie erhalten gleich zwei E-Mails" —
 *          die zweite gab es nie, weil das Passwort mangels Platzhalter in der Vorlage
 *          verworfen wurde. Seit Campus Connect v3.169.0 kommt EINE Mail mit Zugangs-
 *          daten und Team-Link zusammen; der Text sagt das jetzt auch. Dazu der Hinweis,
 *          dass die Kurse E-Learnings sind — ohne Termine, ohne Wartezeit.
 *          (Nachgetragen mit v1.38.0: v1.37.0 wurde nie als Release ausgeliefert, der
 *          Eintrag fehlte hier. Beide Aenderungen gehen mit v1.38.0 zusammen raus.)
 *
 * v1.36.0: Die Ticket-Detailseiten bekommen die Struktur der Kursdetailseiten (Stufe 3).
 *          Bis hier war die Seite eine Textwueste: H1, Claim, Beschreibung, Rechner,
 *          Produktkarten, Kursliste — ohne Hierarchie, ohne Faktenbox, ohne Abschluss.
 *          Neu:
 *          - Zweispaltiger Hero mit Faktenbox „Auf einen Blick" (Muster: lvk-factbox der
 *            Kursseite, eigene lv-fb-Klassen, weil das Tarif-CSS getrennt ausgeliefert
 *            wird). Zeigt Zuschnitte, Kurse, Umfang, Laufzeit und den „ab"-Preis. Die
 *            Kennzahlen sind SPANNEN (11–24 Kurse), weil das PflichtTicket je Zuschnitt
 *            unterschiedlich gross ist — eine einzelne Zahl waere fuer die meisten falsch.
 *          - Der „ab"-Preis ist aus dem Fliesstext in die Faktenbox gewandert; er bleibt
 *            sichtbar, weil er als lowPrice im Product-Schema steht.
 *          - Primaerer CTA „Preis fuer dein Team berechnen" (Anker auf den Rechner) und
 *            sekundaerer Beratungs-CTA — vorher fuehrte die Seite nur ueber die
 *            Produktkarten zum Kauf.
 *          - „So laeuft's ab" in vier Schritten. Diese Information stand bisher nur in
 *            product_plans.description (wird nicht gerendert) und in der Willkommensmail,
 *            also erst NACH dem Kauf — waehrend genau das die Frage davor ist.
 *          - Schluss-CTA nach der FAQ.
 *          - Auf schmalen Schirmen rutscht die Faktenbox VOR den Fliesstext, damit Preis
 *            und CTA nicht unter der Beschreibung begraben liegen.
 *
 * v1.35.0: Sichtbares FAQ-Accordion auf den Ticket-Detailseiten. Das FAQPage-Schema
 *          kam schon mit v1.34.0, die Fragen standen aber nirgends auf der Seite —
 *          Google verlangt ausdruecklich, dass ausgezeichnete FAQ-Inhalte sichtbar
 *          sind, Schema allein waere ein Richtlinienverstoss gewesen. Praktisch war
 *          das folgenlos, weil product_families.faq bis hier leer war; jetzt, wo die
 *          Fragen gepflegt werden, muss beides zusammen raus. Sichtbare Ausgabe und
 *          Schema speisen sich aus derselben livento_cc_faq_items() und koennen
 *          daher nicht auseinanderlaufen. <details>/<summary> statt JS: laeuft ohne
 *          Skript und ist auch zugeklappt fuer Crawler lesbar.
 *
 *          KORREKTUR zu v1.34.0: Dessen Changelog behauptet, Rank Math habe diese
 *          Seiten als Article deklariert und das Plugin raeume den Widerspruch weg.
 *          Das trifft nicht zu — der Befund stammte aus einer veralteten Seiten-
 *          Cache-Kopie (WP-Optimize). Ein frisches Rendering zeigt, dass Rank Math
 *          hier nur eine BreadcrumbList ausgibt. Das Entfernen von Article/
 *          BlogPosting bleibt als Absicherung im Code, ist aber wirkungslos, solange
 *          die Rank-Math-Schema-Einstellung fuer Seiten nicht wieder greift. Der
 *          echte Schema-Fehler (fixer Preis im Product) war real und ist behoben.
 *
 * v1.34.0: SEO fuer die Ticket-Detailseiten (/e-learning/<slug>/). Diese Seiten hatten
 *          bis hier WEDER Title (der Browser zeigte den rohen Slug "pflicht-ticket")
 *          NOCH Meta-Description, Canonical, og-Tags oder ein H1 — die komplette
 *          SEO-Maschinerie haengt am ?kurs=-Gate und hat sie nie erreicht. Neu:
 *          - Eigener wp-Hook, der die Seite am Slug erkennt (Seiten-Slug == Familien-Slug
 *            ist ohnehin Pflicht, siehe Admin-Hilfe). Bewusst NICHT ueber das
 *            Shortcode-Attribut: der Shortcode steckt in einem Elementor-Widget und
 *            steht damit nicht in post_content.
 *          - Title/Meta/Canonical/og aus public_tariffs (neue Spalte meta_title,
 *            vorhandene meta_description — die wurde bislang NIE gelesen).
 *          - H2 -> H1; highlights werden endlich gerendert (existierten, waren aber
 *            nur auf der Landing sichtbar — das war die eigentliche "Leblosigkeit").
 *          - Schema: Product mit AggregateOffer (lowPrice aus price_from) statt Offer
 *            mit Festpreis, dazu FAQPage + Breadcrumb, alles im RM-@graph. Der
 *            Article-Knoten faellt auf diesen Seiten weg — eine Produktseite ist kein
 *            Artikel, und die Seite behauptete bis hier beides gleichzeitig.
 *          - "ab X € / Jahr" sichtbar im Kopf, identisch mit lowPrice im Schema
 *            (sonst Preis-Mismatch: die Seite zeigte den Preis fuer N Beschaeftigte,
 *            das Schema einen fixen Betrag).
 *          - Nettobetrag zusaetzlich zum Bruttopreis (B2B rechnet netto, die Kasse
 *            bucht brutto). Serverseitig im REST-Format, nicht im JS.
 *          - Durchgaengig Du-Form: der Rechner siezte ("haben Sie?"), direkt daneben
 *            duzte der Text.
 *          BENOETIGT Migration v3.164.0 (meta_title + public_tariffs neu).
 *
 * v1.33.0: Tarif-CTA in CI-Gruen (#004D33) statt Petrol; stoerender Hover-Farbwechsel
 *          entfernt (Hover behaelt dieselbe Farbe). Standard-Basis der Tarif-Detailseiten
 *          von "selbstlernkurse" auf "e-learning" geaendert — die "Kurse & Details
 *          ansehen"-Links zeigen jetzt auf /e-learning/<slug>/ (per Shortcode-Attribut
 *          base="…" weiterhin ueberschreibbar).
 *
 * v1.32.0: Warenkorb-Zaehler im Header. Der Header lauscht auf `lv:cart` (detail.count);
 *          weil die Tickets per Link (?add-to-cart) und Reload in den Warenkorb kommen —
 *          nicht per AJAX — feuerte das Event nie. Jetzt wird es beim Seitenaufbau mit dem
 *          echten Stand gesendet (und bei WC-AJAX-Add/Remove). count = Positionen im
 *          Warenkorb. Rein additiv, keine Aenderung am Kauffluss.
 *
 * v1.31.0: Tarifpreise werden als Bruttopreise inkl. MwSt ausgewiesen (einheitlich mit
 *          WooCommerce, wo der eingegebene Preis der Kundenpreis ist). Die Betraege bleiben
 *          unveraendert — nur der Steuerhinweis wechselt von "netto zzgl. USt" auf
 *          "inkl. MwSt" (Angebotsrechner, Karten, Detailseiten, Fusszeilen). USt-freie
 *          Tarife (is_vat_exempt) zeigen weiterhin "USt-frei". Der Warenkorbpreis (yearly_net)
 *          bleibt unveraendert.
 *
 * v1.30.0: Tarife heissen Tickets. Wegen einer Namenskollision mit einem Wettbewerber
 *          wurden die Tariffamilien umbenannt: PflichtStart -> PflichtTicket,
 *          PflegeKomplett -> KomplettTicket, RollenPlus -> RollenTicket
 *          (FunktionsbereichPlus entfaellt, laeuft als RollenTicket weiter). Betrifft nur
 *          Texte und Beispiele — Namen, Schluessel und Slugs kommen live aus Campus Connect.
 *          NACH DEM UPDATE: Die WordPress-Unterseiten der Tarife auf die neuen Slugs
 *          (pflicht-ticket, komplett-ticket, rollen-ticket) umstellen und im Shortcode
 *          family="pflichtticket|komplettticket|rollenticket" setzen. Alte Seiten-Slugs
 *          auf die neuen weiterleiten (301), sonst laufen bestehende Links ins Leere.
 *
 * Optional: Cache-Purge-Webhook — LIVENTO_CC_PURGE_SECRET setzen, dann kann Campus
 * Connect bei Kursaenderungen POST /wp-json/livento/v1/purge (Header
 * X-Livento-Purge-Secret) pingen, um den Transient-Cache sofort zu leeren.
 */

if (!defined('ABSPATH')) {
    exit; // kein Direktaufruf
}

/* ============================================================
 * 1. Konfiguration
 * ============================================================ */

// Supabase-Projekt (Produktion). Bei Bedarf auf Dev umstellen.
define('LIVENTO_CC_SUPABASE_URL', 'https://ighppnxvttxmwexhhfnn.supabase.co');

// v1.34.0: USt-Satz fuer die Netto-Nebenangabe (Regelsteuersatz). Nur fuer die
// Anzeige — abgerechnet wird der Bruttobetrag, der aus fn_calc_tariff_price kommt.
define('LIVENTO_CC_VAT_RATE', 0.19);

// Oeffentlicher anon-Key (KEIN service_role!). BEVORZUGT im WP-Backend unter
// „Livento Katalog → Einstellungen" hinterlegen — das ueberlebt Plugin-Updates.
// Alternativ als Konstante in wp-config.php: define('LIVENTO_CC_ANON_KEY', '...');
// Der Fallback hier greift nur, wenn weder Option noch wp-config gesetzt sind.
if (!defined('LIVENTO_CC_ANON_KEY')) {
    define('LIVENTO_CC_ANON_KEY', 'PASTE_ANON_KEY_HERE');
}

// URL-Basis = Slug der WordPress-Seite, die den Shortcode enthaelt.
// Muss zur Campus-Connect-Env CC_PUBLIC_CATALOG_PATH passen (beide Default "kurse").
// Liste:  https://livento-bildung.de/kurse/
// Detail: https://livento-bildung.de/kurse/<kurs-slug>/
define('LIVENTO_CC_BASE', 'kurse');

// URL-Basis = Slug der WordPress-Seite mit [livento_foerderungen]. Detailseiten unter
// /<base>/<slug>/. Muss zum Slug der „Fördermöglichkeiten"-Seite passen.
define('LIVENTO_CC_FOERDER_BASE', 'foerdermoeglichkeiten');

// Cache-Dauer in Sekunden (Liste + Einzelkurse). 3 Stunden (Spec-Default).
define('LIVENTO_CC_TTL', 3 * HOUR_IN_SECONDS);

// Optionales Shared-Secret fuer den Cache-Purge-Webhook (POST /wp-json/livento/v1/purge
// mit Header X-Livento-Purge-Secret). BEVORZUGT im Admin („Einstellungen") setzen.
// Leer = Webhook deaktiviert (nur TTL/Cron).
if (!defined('LIVENTO_CC_PURGE_SECRET')) {
    define('LIVENTO_CC_PURGE_SECRET', '');
}

// Anbietername (Schema.org provider + Footer).
define('LIVENTO_CC_PROVIDER', 'Livento – Privates Bildungsinstitut für Pflege und Gesundheit UG (haftungsbeschränkt)');

// GitHub-Repo fuer Auto-Updates (Plugin meldet Updates im WP-Dashboard wie jedes andere).
// Format: https://github.com/USER/REPO/  · Leer = Auto-Update aus.
if (!defined('LIVENTO_CC_UPDATE_REPO')) {
    define('LIVENTO_CC_UPDATE_REPO', 'https://github.com/ChristianKarlConsulting/livento-kurskatalog/');
}
// Optionaler GitHub-Token NUR fuer PRIVATE Repos (Public braucht keinen).
if (!defined('LIVENTO_CC_UPDATE_TOKEN')) {
    define('LIVENTO_CC_UPDATE_TOKEN', '');
}

// 1 Unterrichtseinheit (UE) in Minuten — fuer die Dauer-Anzeige.
define('LIVENTO_CC_UE_MINUTES', 45);

// v1.59.0: Sterne (sichtbar und im Schema) erst ab dieser Zahl freigegebener Bewertungen.
define('LIVENTO_CC_RATING_MIN', 5);

/* ============================================================
 * 2. Label-Maps (gespiegelt aus src/lib/courseFilterLabels.ts)
 * ============================================================ */

function livento_cc_format_labels() {
    return array(
        'praesenz'        => 'Präsenz',
        'online_live'     => 'Online-Live',
        'selbstlern'      => 'Selbstlern',
        'blended'         => 'Blended Learning',
        'flexibel_modular'=> 'Flexibel / Modular',
        'kompakt'         => 'Kompakt',
    );
}

function livento_cc_audience_labels() {
    return array(
        'pflegefachkraefte'        => 'Pflegefachkräfte',
        'pflegehilfskraefte'       => 'Pflegehilfskräfte',
        'fuehrungskraefte'         => 'Führungskräfte',
        'praxisanleitende'         => 'Praxisanleitende',
        'betreuungskraefte_43b_53b'=> 'Betreuungskräfte (§43b / §53b)',
        'quereinsteigende'         => 'Quereinsteigende',
        'angehoerige'              => 'Angehörige',
    );
}

function livento_cc_funding_labels() {
    // 9 CC-gespiegelte Standard-Werte (aus src/lib/courseFilterLabels.ts) — diese tragen
    // echte Kurse und werden im Katalog-Filter „Förderung" gelabelt.
    $defaults = array(
        'azav_bildungsgutschein' => 'AZAV-Bildungsgutschein',
        'bildungsurlaub'         => 'Bildungsurlaub',
        'bildungsscheck'         => 'Bildungsscheck',
        'praevention_par20a_sgb_v' => '§20a SGB V',
        'qcg'                    => 'QCG',
        'aufstiegs_bafoeg'       => 'Aufstiegs-BAföG',
        'arbeitgeber'            => 'Arbeitgeber',
        'ratenzahlung'           => 'Ratenzahlung',
        'selbstzahler'           => 'Selbstzahler',
    );
    // Im Backend selbst verwaltete Zusatz-Tags (Tab „Förderprogramme → Kurse-Förder-Tags").
    // Ergänzen neue Slugs bzw. dürfen ein Default-Label überschreiben (Umbenennen).
    return array_merge($defaults, livento_cc_funding_tags_custom());
}

/**
 * Selbst verwaltete Zusatz-Förder-Tags (slug => Label). Option unset = Seed mit
 * „Anpassungsqualifizierung" (out-of-the-box verfügbar); gesetzt (auch leer) = wie gespeichert.
 * HINWEIS: Ein Custom-Tag filtert nur Kurse, wenn Campus Connect denselben funding-Wert kennt —
 * sonst dient er als reines Label/Verlinkungsziel im Förderprogramm-Editor.
 */
function livento_cc_funding_tags_custom() {
    $opt = get_option('livento_cc_funding_tags', null);
    if (!is_array($opt)) {
        return array('anpassungsqualifizierung' => 'Anpassungsqualifizierung');
    }
    return $opt;
}

function livento_cc_recognition_labels() {
    return array(
        'tn_bescheinigung'    => 'TN-Bescheinigung',
        'fp_zertifikat'       => 'FP / Zertifikat',
        'gesetzlich_anerkannt'=> 'Gesetzlich anerkannt',
        'md_relevant'         => 'MD-relevant',
        'rbp_punkte'          => 'RbP-Punkte',
        'azav_zertifikat'     => 'AZAV-Zertifikat',
    );
}

function livento_cc_type_labels() {
    return array(
        // v1.58.0: nicht mehr „Weiterbildung“ — sonst Verwechslung mit der Angebotsart
        'program'          => 'Kurs mit festen Terminen',
        'scheduled_course' => 'Einzeltermin',
        'self_learning'    => 'Selbstlernkurs',
        'on_request'       => 'Termin auf Anfrage',
    );
}

function livento_cc_level_labels() {
    return array(
        'L0' => 'Pflicht-Onboarding (0)', 'L1' => 'Basis I (1)', 'L2' => 'Basis II (2)',
        'L3' => 'Skill-Booster (3)', 'L4' => 'Bonus / Spezial (4)', 'L5' => 'Fachweiterbildung (5)',
        'L6' => 'Fachweiterbildung (6)', 'L7' => 'Leitung & Führung (7)', 'L8' => 'Advanced (8)',
    );
}

function livento_cc_methodology_labels() {
    return array(
        'praxis_uebung'       => 'Praxis & Übung',
        'theorie_schwerpunkt' => 'Theorie-Schwerpunkt',
        'kompakt_input'       => 'Kompakter Input',
        'reflexion_austausch' => 'Reflexion & Austausch',
        'selbstlern'          => 'Selbstlern-Format',
    );
}

/**
 * v1.58.0: Umfang-Stufen nach GESAMTumfang in UE (gespiegelt aus src/lib/offeringKind.ts).
 * Vorher Sitzungsdauer (Unter 2 Std. … Mehrtaegig) — die letzte Stufe warf eine
 * 16-UE-Pflichtfortbildung mit einem 460-Stunden-Lehrgang zusammen.
 */
function livento_cc_duration_labels() {
    return array(
        'bis_8_ue'     => 'Bis 8 UE',
        'bis_40_ue'    => '9 bis 40 UE',
        'bis_160_ue'   => '41 bis 160 UE',
        'ueber_160_ue' => 'Über 160 UE',
    );
}

/** v1.58.0: Angebotsart (Campus Connect v4.47.0, feste Liste). Plural fuer Umschalter/Abschnitte. */
function livento_cc_kind_labels() {
    return array(
        'weiterbildung_abschluss' => 'Weiterbildungen mit Abschluss',
        'fortbildung'             => 'Fort- und Pflichtfortbildungen',
        'workshop'                => 'Workshops und Kompaktkurse',
        'selbstlernkurs'          => 'Selbstlernkurse',
    );
}

/** v1.58.1: Kurzform fuer den Umschalter — passt auf dem Desktop in eine Zeile.
 *  Die Abschnittsueberschriften behalten die Langform aus livento_cc_kind_labels(). */
function livento_cc_kind_short() {
    return array(
        'weiterbildung_abschluss' => 'Weiterbildungen mit Abschluss',
        'fortbildung'             => 'Fortbildungen',
        'workshop'                => 'Workshops',
        'selbstlernkurs'          => 'Selbstlernkurse',
    );
}

/** Singular fuer den Kartenkopf. */
function livento_cc_kind_singular() {
    return array(
        'weiterbildung_abschluss' => 'Weiterbildung mit Abschluss',
        'fortbildung'             => 'Fortbildung',
        'workshop'                => 'Workshop / Kompaktkurs',
        'selbstlernkurs'          => 'Selbstlernkurs',
    );
}

/** Untertitel der Abschnitte — bewusst ohne Stundenangabe (die Art darf von Hand abweichen). */
function livento_cc_kind_hints() {
    return array(
        'weiterbildung_abschluss' => 'Umfangreiche Qualifizierungen mit Abschluss',
        'fortbildung'             => 'Fortbildungen und jährliche Pflichtfortbildungen',
        'workshop'                => 'Kompakte Formate von wenigen Stunden',
        'selbstlernkurs'          => 'Online lernen im eigenen Tempo',
    );
}

/** v1.58.0: Zeitmodell (Campus Connect leitet es aus den Unterrichtseinheiten ab). */
function livento_cc_time_model_labels() {
    return array(
        'tagsueber'  => 'Tagsüber',
        'abends'     => 'Abends',
        'wochenende' => 'Am Wochenende',
    );
}

/** Wie fn_normalize_hours_unit in Campus Connect: 'unterrichtsstunden' (45 min) oder 'stunden' (60 min). */
function livento_cc_normalize_hours_unit($u) {
    $u = strtolower(trim((string) $u));
    if ($u === '') {
        return '';
    }
    if (in_array($u, array('unterrichtsstunden', 'unterrichtsstunde', 'unterrichtseinheiten', 'unterrichtseinheit', 'ue'), true)) {
        return 'unterrichtsstunden';
    }
    if (in_array($u, array('stunden', 'stunde', 'zeitstunden', 'zeitstunde', 'std', 'std.', 'h'), true)) {
        return 'stunden';
    }
    return '';
}

/** Gesamtumfang in UE; sonst Dauer aus Beginn/Ende (Einzeltermine). null = unbekannt. */
function livento_cc_teaching_units($o) {
    $h = isset($o['total_hours']) ? (float) $o['total_hours'] : 0.0;
    if ($h > 0) {
        $ue = livento_cc_normalize_hours_unit($o['hours_unit'] ?? '') === 'stunden' ? $h * 4 / 3 : $h;
        return round($ue, 2);
    }
    $m = isset($o['duration_minutes']) ? (int) $o['duration_minutes'] : 0;
    return $m > 0 ? round($m / 45, 2) : null;
}

/** Vorschlag wie fn_suggest_offering_kind: bis 8 UE Workshop, 9–79 Fortbildung, ab 80 Weiterbildung. */
function livento_cc_suggest_kind($ue, $self_learning) {
    if ($self_learning) {
        return 'selbstlernkurs';
    }
    if ($ue === null || $ue <= 0) {
        return '';
    }
    if ($ue >= 80) {
        return 'weiterbildung_abschluss';
    }
    return $ue > 8 ? 'fortbildung' : 'workshop';
}

/**
 * Angebotsart fuer die Anzeige. Liefert Campus Connect die Spalte (auch leer), gilt sie;
 * fehlt sie ganz (Datenbank vor v4.47.0), wird aus dem Umfang vorgeschlagen.
 */
function livento_cc_effective_kind($o) {
    if (array_key_exists('offering_kind', $o)) {
        $k = (string) $o['offering_kind'];
        return isset(livento_cc_kind_labels()[$k]) ? $k : '';
    }
    $self = (($o['offering_type'] ?? '') === 'self_learning') || (($o['format'] ?? '') === 'selbstlern');
    return livento_cc_suggest_kind(livento_cc_teaching_units($o), $self);
}

function livento_cc_fmt_number($n) {
    $n = round((float) $n, 1);
    return (floor($n) == $n) ? number_format($n, 0, ',', '.') : number_format($n, 1, ',', '.');
}

/** „460 Std.“ / „24 UE“; Einzeltermine aus der Dauer in Stunden. '' = unbekannt. */
function livento_cc_umfang_label($o) {
    $h = isset($o['total_hours']) ? (float) $o['total_hours'] : 0.0;
    if ($h > 0) {
        return livento_cc_fmt_number($h) . (livento_cc_normalize_hours_unit($o['hours_unit'] ?? '') === 'stunden' ? ' Std.' : ' UE');
    }
    $m = isset($o['duration_minutes']) ? (int) $o['duration_minutes'] : 0;
    return $m > 0 ? livento_cc_fmt_number($m / 60) . ' Std.' : '';
}

/** Laufzeit langer Angebote: ab 14 Tagen Wochen, ab 60 Tagen Monate. '' sonst. */
function livento_cc_laufzeit_label($o) {
    if (($o['offering_type'] ?? '') === 'scheduled_course' || empty($o['start_datetime']) || empty($o['end_datetime'])) {
        return '';
    }
    $s = strtotime(substr((string) $o['start_datetime'], 0, 10));
    $e = strtotime(substr((string) $o['end_datetime'], 0, 10));
    if (!$s || !$e || $e <= $s) {
        return '';
    }
    $days = (int) round(($e - $s) / 86400) + 1;
    if ($days < 14) {
        return '';
    }
    if ($days < 60) {
        return 'ca. ' . (int) round($days / 7) . ' Wochen';
    }
    return 'ca. ' . (int) round($days / 30.44) . ' Monate';
}

/** Abschluss in einem Satz aus Pruefung, Zertifikatstitel und Anerkennung. '' = keine Aussage. */
function livento_cc_abschluss_label($o) {
    $exam = trim((string) ($o['exam_title'] ?? ''));
    $rec  = (isset($o['recognition']) && is_array($o['recognition'])) ? $o['recognition'] : array();
    // v1.66.0: Heisst das Dokument selbst „Teilnahmebescheinigung …“, ist es kein Zertifikat
    // (z. B. Jaehrliche Pflichtfortbildung fuer Betreuungskraefte: Abschlusstest + Teilnahmebescheinigung).
    $tn = stripos(ltrim((string) ($o['certificate_title'] ?? '')), 'Teilnahmebescheinigung') === 0;
    if ($exam !== '') {
        return ($tn ? 'Teilnahmebescheinigung' : 'Zertifikat')
             . (stripos($exam, 'test') !== false ? ' mit Abschlusstest' : ' mit Abschlussprüfung');
    }
    if ($tn) {
        return 'Teilnahmebescheinigung';
    }
    if (trim((string) ($o['certificate_title'] ?? '')) !== '' || in_array('fp_zertifikat', $rec, true) || in_array('azav_zertifikat', $rec, true)) {
        return 'Zertifikat';
    }
    return in_array('tn_bescheinigung', $rec, true) ? 'Teilnahmebescheinigung' : '';
}

/** Umfang-Stufe aus UE (gespiegelt aus bucketUmfang). */
function livento_cc_bucket_umfang($ue) {
    if ($ue === null || $ue <= 0) {
        return '';
    }
    if ($ue <= 8) {
        return 'bis_8_ue';
    }
    if ($ue <= 40) {
        return 'bis_40_ue';
    }
    return $ue <= 160 ? 'bis_160_ue' : 'ueber_160_ue';
}

/**
 * v1.58.0: Eine Karte je Kurs. Gleicher Titel, gleiche Art (Termin / Selbstlernkurs /
 * Anfrage) und gleiche Angebotsart = eine Gruppe. Reihenfolge folgt dem ersten Termin
 * jeder Gruppe in der (bereits sortierten) Liste; die Karte traegt der frueheste Termin.
 * Jeder Termin behaelt seine eigene Detailseite (SEO), die Karte verlinkt sie.
 */
function livento_cc_group_key($o) {
    $t = $o['offering_type'] ?? '';
    $art = $t === 'self_learning' ? 'sl' : ($t === 'on_request' ? 'rq' : 'tm');
    $title = trim((string) ($o['title'] ?? ''));
    $title = function_exists('mb_strtolower') ? mb_strtolower($title, 'UTF-8') : strtolower($title);
    $title = preg_replace('/\s+/u', ' ', $title);
    return ($o['_kind'] ?? '') . '|' . $art . '|' . $title;
}

function livento_cc_group_offerings($offerings) {
    $groups = array();
    foreach ($offerings as $o) {
        $k = livento_cc_group_key($o);
        if (!isset($groups[$k])) {
            $groups[$k] = array();
        }
        $groups[$k][] = $o;
    }
    $out = array();
    foreach ($groups as $members) {
        usort($members, function ($a, $b) {
            $sa = !empty($a['start_datetime']) ? (string) $a['start_datetime'] : '9999';
            $sb = !empty($b['start_datetime']) ? (string) $b['start_datetime'] : '9999';
            return strcmp($sa, $sb);
        });
        $out[] = array('lead' => $members[0], 'members' => $members);
    }
    return $out;
}

/**
 * v1.68.0: Kanonischer Slug einer Kursgruppe.
 *
 * In Campus Connect ist jeder Durchgang ein eigenes Programm, also bekommt jeder Termin
 * eine eigene Detailseite. Fuer Suchmaschinen sind das Dubletten: gleicher Titel,
 * gleicher Text, nur ein anderes Startdatum. Stand 23.11.2026: 95 Termin-Adressen fuer
 * 56 Kurse, 39 davon Zweitfassungen — jede mit Canonical auf sich selbst und auf
 * index,follow. Google musste sich eine aussuchen, Links und Autoritaet verteilten sich
 * auf bis zu fuenf Adressen desselben Kurses.
 *
 * Deshalb zeigen jetzt alle Termine einer Gruppe auf denselben Gruppenfuehrer — genau
 * die Seite, die auch die Karte im Katalog verlinkt. Interne Verlinkung und Canonical
 * sagen damit dasselbe.
 *
 * Bewusste Abwaegung: Gruppenfuehrer ist der naechste Termin (livento_cc_group_offerings
 * sortiert nach Startdatum). Laeuft ein Durchgang aus, wandert das Canonical auf den
 * folgenden Termin. Das kostet etwas Ranking-Stabilitaet, dafuer zeigt es nie auf eine
 * Seite, die aus dem Katalog verschwindet. Dauerhaft stabiler waere eine eigene
 * Kurs-URL, auf der die Termine nur zur Auswahl stehen — das ist ein groesserer Umbau
 * (Routing, Cache-Schluessel, Sitemap) und hier absichtlich nicht enthalten.
 *
 * Kein noindex auf den Zweitfassungen: noindex und ein Canonical auf eine andere Seite
 * sind widerspruechliche Signale. Die Termin-Seiten bleiben erreichbar und buchbar
 * (Anzeigen, Direktlinks, Bildungsgutschein-Beratung), nur eben nicht eigenstaendig
 * indexiert.
 */
function livento_cc_canonical_slug($o) {
    static $map = null;
    if ($map === null) {
        $map = array();
        foreach (livento_cc_group_offerings(livento_cc_augment(livento_cc_get_offerings())) as $g) {
            $lead = (string) ($g['lead']['slug'] ?? '');
            if ($lead === '') {
                continue;
            }
            foreach ($g['members'] as $m) {
                $s = (string) ($m['slug'] ?? '');
                if ($s !== '') {
                    $map[$s] = $lead;
                }
            }
        }
    }
    $slug = (string) ($o['slug'] ?? '');
    return ($slug !== '' && isset($map[$slug])) ? $map[$slug] : $slug;
}

/** v1.68.0: true, wenn dieser Slug der Gruppenfuehrer ist (also selbst kanonisch). */
function livento_cc_is_canonical_slug($o) {
    $slug = (string) ($o['slug'] ?? '');
    return $slug !== '' && livento_cc_canonical_slug($o) === $slug;
}

/** v1.58.0: Zahl der Kurse (Gruppen) je Angebotsart — fuer den Admin-Tab „Kurslisten“. */
function livento_cc_art_counts() {
    static $counts = null;
    if ($counts !== null) {
        return $counts;
    }
    $counts = array();
    foreach (livento_cc_group_offerings(livento_cc_augment(livento_cc_get_offerings())) as $g) {
        $k = (string) ($g['lead']['_kind'] ?? '');
        if ($k !== '') {
            $counts[$k] = isset($counts[$k]) ? $counts[$k] + 1 : 1;
        }
    }
    return $counts;
}

function livento_cc_availability_labels() {
    return array(
        'verfuegbar'     => 'Sofort verfügbar',
        'wenige_plaetze' => 'Wenige Plätze',
        'ausgebucht'     => 'Ausgebucht',
        'warteliste'     => 'Warteliste',
    );
}

/** Verfügbarkeit aus enrolled/max (gespiegelt aus courseFilterLabels.ts). */
function livento_cc_derive_availability($enrolled, $max) {
    if ($max === null || $max === '') {
        return 'verfuegbar';
    }
    $enrolled = (int) $enrolled;
    $max = (int) $max;
    if ($enrolled >= $max) {
        return 'ausgebucht';
    }
    if ($max - $enrolled <= max(2, (int) ceil($max * 0.2))) {
        return 'wenige_plaetze';
    }
    return 'verfuegbar';
}

/** Reichert jedes Angebot um abgeleitete Filter-Werte an (_ue/_kind/_duration/_zeitmodell/_startmonth/_availability). */
function livento_cc_augment($offerings) {
    foreach ($offerings as &$o) {
        // v1.58.0: Umfang in UE, Angebotsart, Umfang-Stufe, Zeitmodell
        $ue                 = livento_cc_teaching_units($o);
        $o['_ue']           = $ue;
        $o['_kind']         = livento_cc_effective_kind($o);
        $o['_duration']     = livento_cc_bucket_umfang($ue);
        $o['_zeitmodell']   = (isset($o['time_models']) && is_array($o['time_models']))
            ? array_values(array_filter(array_map('strval', $o['time_models'])))
            : array();
        $o['_startmonth']   = !empty($o['start_datetime']) ? substr($o['start_datetime'], 0, 7) : '';
        $o['_availability'] = livento_cc_derive_availability($o['enrolled_count'] ?? 0, $o['max_participants'] ?? null);
    }
    unset($o);
    return $offerings;
}

/* ============================================================
 * 3. Datenabruf (PostgREST + Transient-Cache)
 * ============================================================ */

/**
 * Gueltigkeitscheck des oeffentlichen Schluessels.
 * v1.57.0: Publishable Key (sb_publishable_…) oder Legacy-Anon-JWT (lang + enthaelt Punkte).
 * Secret Keys (sb_secret_…) gelten nie als gueltig — sie gehoeren nicht in WordPress.
 */
function livento_cc_key_is_valid($k) {
    if (!is_string($k) || strpos($k, 'sb_secret_') === 0) {
        return false;
    }
    if (strpos($k, 'sb_publishable_') === 0) {
        return strlen($k) > strlen('sb_publishable_') + 10;
    }
    return strlen($k) > 40 && strpos($k, '.') !== false;
}

/** Anon-Key: Admin-Einstellung (ueberlebt Updates) → Konstante (wp-config/Fallback). */
function livento_cc_anon_key() {
    $opt = (string) get_option('livento_cc_anon_key', '');
    if (livento_cc_key_is_valid($opt)) {
        return $opt;
    }
    return livento_cc_key_is_valid(LIVENTO_CC_ANON_KEY) ? LIVENTO_CC_ANON_KEY : '';
}

/** Purge-Webhook-Secret: Admin-Einstellung → Konstante (Fallback). */
function livento_cc_purge_secret() {
    $opt = (string) get_option('livento_cc_purge_secret', '');
    return $opt !== '' ? $opt : (string) LIVENTO_CC_PURGE_SECRET;
}

/** v1.48.0: Secret fuer Nachkauf-Belege — identisch mit TICKET_TOPUP_SECRET in Supabase. */
function livento_cc_topup_secret() {
    $opt = (string) get_option('livento_cc_topup_secret', '');
    if ($opt !== '') return $opt;
    return defined('LIVENTO_CC_TOPUP_SECRET') ? (string) LIVENTO_CC_TOPUP_SECRET : '';
}

/** v1.48.0: WooCommerce-Produkt „Lizenzen nachkaufen" (0 = nicht eingerichtet). */
function livento_cc_topup_product_id() {
    return absint(get_option('livento_cc_topup_product_id', 0));
}

/**
 * Roher GET gegen die Supabase REST-API. Gibt ein dekodiertes Array oder
 * WP_Error zurueck.
 */
function livento_cc_rest_get($query, $path = 'public_offerings') {
    $key = livento_cc_anon_key();
    // v1.45.0: Pfad war bis hier fest auf public_offerings verdrahtet. Der Default
    // haelt alle Bestandsaufrufe unveraendert; Feature 181 liest public_instructors.
    $url = LIVENTO_CC_SUPABASE_URL . '/rest/v1/' . $path . '?' . $query;

    $res = wp_remote_get($url, array(
        'timeout' => 8,
        'headers' => array(
            'apikey'        => $key,
            'Authorization' => 'Bearer ' . $key,
            'Accept'        => 'application/json',
        ),
    ));

    if (is_wp_error($res)) {
        return $res;
    }
    $code = wp_remote_retrieve_response_code($res);
    if ($code < 200 || $code >= 300) {
        return new WP_Error('livento_cc_http', 'PostgREST HTTP ' . $code, wp_remote_retrieve_body($res));
    }
    $data = json_decode(wp_remote_retrieve_body($res), true);
    return is_array($data) ? $data : array();
}

/** Cache-Version — wird beim Purge hochgezaehlt und entwertet alle Schluessel auf einmal. */
function livento_cc_ver() {
    return (int) get_option('livento_cc_cachever', 1);
}

/** Alle oeffentlichen Angebote (gecached). */
function livento_cc_get_offerings() {
    $list_key = 'livento_cc_list_v' . livento_cc_ver();
    $cache = get_transient($list_key);
    if ($cache !== false) {
        return livento_cc_with_bg_funding($cache);
    }

    $base = array(
        'id', 'offering_type', 'title', 'slug', 'short_description', 'public_description',
        'public_image_url', 'public_image_ai_generated', 'public_price', 'is_vat_exempt', 'start_datetime', 'end_datetime',
        'site_name', 'site_city', 'format', 'level', 'is_azav_relevant', 'rbp_points',
        'duration_minutes', 'course_number', 'published_at', 'max_participants', 'enrolled_count',
        'show_availability_indicator', 'wc_checkout_url', 'is_free',
        // Filter-Dimensionen:
        'audience', 'funding', 'recognition', 'methodology', 'topics',
        // Sortierung:
        'booking_count', 'rating_avg', 'rating_count', 'is_featured', 'featured_order',
        // v1.58.0: Karte (Umfang, Abschluss) — gibt es seit Campus Connect v3.228.0
        'total_hours', 'hours_unit', 'certificate_title', 'exam_title',
    );
    // v1.58.0: Gliederung (Campus Connect v4.47.0). Kennt die Datenbank die Spalten noch
    // nicht, antwortet PostgREST mit 400 → einmal ohne sie abfragen (Angebotsart dann
    // aus dem Umfang vorgeschlagen). So laufen Plugin- und App-Release unabhaengig.
    $neu   = array('offering_kind', 'is_mandatory_training', 'time_models');
    $order = '&order=start_datetime.asc.nullslast&limit=500';

    $data = livento_cc_rest_get('select=' . implode(',', array_merge($base, $neu)) . $order);
    if (is_wp_error($data) && $data->get_error_message() === 'PostgREST HTTP 400') {
        $data = livento_cc_rest_get('select=' . implode(',', $base) . $order);
    }
    if (is_wp_error($data)) {
        // Fehler NICHT cachen — letzten guten Stand (Stale-While-Error) liefern.
        $stale = get_transient('livento_cc_list_stale');
        return $stale !== false ? livento_cc_with_bg_funding($stale) : array();
    }

    set_transient($list_key, $data, LIVENTO_CC_TTL);
    set_transient('livento_cc_list_stale', $data, DAY_IN_SECONDS); // Notfall-Fallback
    return livento_cc_with_bg_funding($data);
}

/** Einen Kurs per Slug (gecached). Gibt array|null zurueck. */
function livento_cc_get_offering($slug) {
    $slug = sanitize_title($slug);
    if ($slug === '') {
        return null;
    }
    $key = 'livento_cc_one_' . livento_cc_ver() . '_' . md5($slug);
    $cache = get_transient($key);
    if ($cache !== false) {
        return $cache === 'NULL' ? null : livento_cc_with_bg_funding(array($cache))[0];
    }

    $query = 'select=*&slug=eq.' . rawurlencode($slug) . '&limit=1';
    $data = livento_cc_rest_get($query);
    if (is_wp_error($data)) {
        return null; // nicht cachen
    }
    $row = !empty($data) ? $data[0] : null;

    // v1.52.0: Ein Anfragekurs, der gerade hinter einem verknuepften Termin
    // zuruecktritt, fehlt in public_offerings. Seine Seite bleibt erreichbar
    // (Ads, Google) und zeigt „Naechster Termin" — Quelle ist dann
    // public_inquiry_courses (gleiche Spalten plus next_program).
    if ($row === null) {
        $alt = livento_cc_rest_get($query, 'public_inquiry_courses');
        if (is_wp_error($alt)) {
            return null; // nicht cachen
        }
        $row = !empty($alt) ? $alt[0] : null;
    }

    set_transient($key, $row === null ? 'NULL' : $row, LIVENTO_CC_TTL);
    return $row === null ? null : livento_cc_with_bg_funding(array($row))[0];
}

/** v1.52.0: Anfragekurs (Kurs ohne Termin, Anfrage statt Buchung)? */
function livento_cc_is_on_request($o) {
    return isset($o['offering_type']) && $o['offering_type'] === 'on_request';
}

/**
 * v1.63.0: Selbstlernkurs? Nicht nur ueber die Angebotsart — ist sie nicht gepflegt, liefert
 * livento_cc_effective_kind ''. Anfragekurse koennen Selbstlernkurse sein (20.11.2026: 7 von 21);
 * dort gibt es keinen Termin, also auch kein „Termin anfragen“.
 */
function livento_cc_is_self_learning($o) {
    return livento_cc_effective_kind($o) === 'selbstlernkurs'
        || ($o['offering_type'] ?? '') === 'self_learning'
        || ($o['format'] ?? '') === 'selbstlern';
}

/**
 * v1.56.0: Ist der Kurs mit Bildungsgutschein foerderfaehig?
 * Einzige Quelle ist das AZAV-Flag (= durch die fachkundige Stelle zugelassene
 * Massnahme). Die Massnahmenummer ist optional: Sie wird je Durchgang erst mit
 * dem ersten Bildungsgutschein vergeben. Ein Foerder-Tag „azav_bildungsgutschein"
 * OHNE AZAV-Flag loest bewusst keine Bildungsgutschein-Aussage aus.
 */
function livento_cc_is_bildungsgutschein($o) {
    return !empty($o['is_azav_relevant']);
}

/**
 * v1.56.0: AZAV-Kurse tragen im Feld funding immer den Tag azav_bildungsgutschein,
 * damit der Filter ?funding=azav_bildungsgutschein sie nie verliert.
 */
function livento_cc_with_bg_funding($rows) {
    if (!is_array($rows)) {
        return $rows;
    }
    foreach ($rows as &$o) {
        if (is_array($o) && livento_cc_is_bildungsgutschein($o)) {
            $f = (isset($o['funding']) && is_array($o['funding'])) ? $o['funding'] : array();
            if (!in_array('azav_bildungsgutschein', $f, true)) {
                $f[] = 'azav_bildungsgutschein';
            }
            $o['funding'] = $f;
        }
    }
    unset($o);
    return $rows;
}

/**
 * Alle oeffentlich freigegebenen Dozentinnen und Dozenten (gecached).
 *
 * Es wird NICHT nachgefiltert: Die View public_instructors gibt ausschliesslich
 * Profile heraus, bei denen in Campus Connect beide Tore offen sind (Einwilligung
 * des Dozenten UND redaktionelle Freigabe) und Funktionsbezeichnung sowie
 * Kurzvorstellung vorliegen. Ein Foto ist bewusst KEINE Bedingung.
 */
function livento_cc_get_instructors() {
    $key = 'livento_cc_doz_v' . livento_cc_ver();
    $cache = get_transient($key);
    if ($cache !== false) {
        return $cache;
    }

    $select = implode(',', array(
        'id', 'first_name', 'last_name', 'photo_url',
        'public_role', 'public_bio', 'qualifications',
    ));
    $data = livento_cc_rest_get('select=' . $select . '&limit=200', 'public_instructors');
    if (is_wp_error($data)) {
        // Fehler nicht cachen — letzten guten Stand liefern (wie bei der Kursliste).
        $stale = get_transient('livento_cc_doz_stale');
        return $stale !== false ? $stale : array();
    }

    set_transient($key, $data, LIVENTO_CC_TTL);
    set_transient('livento_cc_doz_stale', $data, DAY_IN_SECONDS);
    return $data;
}

/**
 * Cache komplett leeren (Liste + alle Einzelkurse) durch Hochzaehlen der
 * Cache-Version — alte Transient-Schluessel werden damit auf einen Schlag
 * entwertet (und laufen per TTL aus). Aufruf via Purge-Webhook oder manuell.
 */
/**
 * v1.62.0: Nach einem Plugin-Update einmal den Cache leeren. Der Cache-Schluessel haengt nur an
 * der Cache-Version, nicht an der Plugin-Version — bei v1.61.0 zeigte die Ticketseite deshalb
 * bis zum manuellen Leeren die Kursliste von vor der Migration (ohne Themenbereiche).
 */
add_action('init', function () {
    $kopf = get_file_data(__FILE__, array('v' => 'Version'));
    $v    = (string) ($kopf['v'] ?? '');
    if ($v !== '' && get_option('livento_cc_plugin_version') !== $v) {
        update_option('livento_cc_plugin_version', $v);
        livento_cc_flush_cache();
    }
});

function livento_cc_flush_cache() {
    $old = livento_cc_ver();
    update_option('livento_cc_cachever', $old + 1);
    delete_transient('livento_cc_list_v' . $old); // alten Listen-Key sofort freigeben

    // v1.51.0: WP-Optimize haelt die fertig gerenderten Kursseiten im Seitencache —
    // ohne diesen Aufruf kaeme die Aenderung erst nach dessen Ablauf an.
    if (function_exists('wpo_cache_flush')) {
        wpo_cache_flush();
    }
}

/* ============================================================
 * 4. Formatierungs-Helfer
 * ============================================================ */

function livento_cc_detail_url($slug) {
    return home_url(user_trailingslashit(LIVENTO_CC_BASE . '/' . sanitize_title($slug)));
}

function livento_cc_list_url() {
    return home_url(user_trailingslashit(LIVENTO_CC_BASE));
}

function livento_cc_fmt_date($iso) {
    if (empty($iso)) {
        return '';
    }
    $ts = strtotime($iso);
    return $ts ? wp_date('j. F Y', $ts) : '';
}

/** "YYYY-MM-DD" → "dd.mm.yyyy". */
function livento_cc_fmt_date_short($iso) {
    if (empty($iso)) {
        return '';
    }
    $ts = strtotime($iso);
    return $ts ? wp_date('d.m.Y', $ts) : '';
}

/** ISO-Wochentag (1=Mo … 7=So) → deutsche Kurzform. */
function livento_cc_weekday_short($iso) {
    $map = array(1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So');
    $i = (int) $iso;
    return isset($map[$i]) ? $map[$i] : '';
}

/**
 * v3.192.0: Unterrichts-Zeitplan robust als Array holen (json-Spalte kann — wie
 * `modules` — als String ankommen). Gibt null zurueck, wenn keine Termine da sind.
 */
function livento_cc_schedule_arr($o) {
    if (!isset($o['schedule']) || empty($o['schedule'])) {
        return null;
    }
    $sched = $o['schedule'];
    if (is_string($sched)) {
        $sched = json_decode($sched, true);
    }
    if (!is_array($sched) || empty($sched['sessions']) || !is_array($sched['sessions'])) {
        return null;
    }
    return $sched;
}

/** Wochentage als Text: "Mo", "Mo & Mi", "Mo, Mi & Fr". */
function livento_cc_weekdays_text($weekdays) {
    if (empty($weekdays) || !is_array($weekdays)) {
        return '';
    }
    $labels = array();
    foreach ($weekdays as $w) {
        $l = livento_cc_weekday_short($w);
        if ($l !== '') {
            $labels[] = $l;
        }
    }
    $n = count($labels);
    if ($n === 0) {
        return '';
    }
    if ($n === 1) {
        return $labels[0];
    }
    return implode(', ', array_slice($labels, 0, $n - 1)) . ' & ' . $labels[$n - 1];
}

/** Zeitspannen als Text: "17:00–20:15 Uhr · 08:30–11:45 Uhr". */
function livento_cc_timeranges_text($ranges) {
    if (empty($ranges) || !is_array($ranges)) {
        return '';
    }
    $parts = array();
    foreach ($ranges as $r) {
        if (!is_array($r)) {
            continue;
        }
        $start = isset($r['start']) ? $r['start'] : '';
        $end   = isset($r['end']) ? $r['end'] : '';
        if ($start === '' || $start === null) {
            continue;
        }
        $parts[] = ($end !== '' && $end !== null) ? ($start . '–' . $end . ' Uhr') : ('ab ' . $start . ' Uhr');
    }
    return implode(' · ', $parts);
}

/** Kurz-Zusammenfassung (Wochentage · Uhrzeiten) fuer die Faktenbox. */
function livento_cc_schedule_summary($o) {
    $sched = livento_cc_schedule_arr($o);
    if (!$sched) {
        return '';
    }
    $wd = livento_cc_weekdays_text(isset($sched['weekdays']) ? $sched['weekdays'] : array());
    $tr = livento_cc_timeranges_text(isset($sched['time_ranges']) ? $sched['time_ranges'] : array());
    return trim($wd . (($wd !== '' && $tr !== '') ? ' · ' : '') . $tr);
}

/**
 * v3.192.0: Abschnitt „Unterrichtszeiten & Termine" — abgeleitete Zusammenfassung
 * (Wochentage + Uhrzeiten) plus aufklappbare Liste aller Praesenztermine.
 * Native <details> ohne JS. Alle Werte escaped.
 */
function livento_cc_render_schedule($o) {
    $sched = livento_cc_schedule_arr($o);
    if (!$sched) {
        return '';
    }
    $sessions = $sched['sessions'];
    $wd = livento_cc_weekdays_text(isset($sched['weekdays']) ? $sched['weekdays'] : array());
    $tr = livento_cc_timeranges_text(isset($sched['time_ranges']) ? $sched['time_ranges'] : array());
    $n  = count($sessions);

    $out = '<div class="lvk-section lvk-schedule"><h2>Unterrichtszeiten &amp; Termine</h2>';
    if ($wd !== '') {
        $out .= '<p class="lvk-sch-sum"><strong>Unterrichtstage:</strong> ' . esc_html($wd) . '</p>';
    }
    if ($tr !== '') {
        $out .= '<p class="lvk-sch-sum"><strong>Uhrzeiten:</strong> ' . esc_html($tr) . '</p>';
    }
    // v1.53.0: Hinweis auf unterrichtsfreie Wochen zwischen Modulen (modulare Einstiege)
    if (!empty($o['schedule_note']) && is_string($o['schedule_note'])) {
        $out .= '<p class="lvk-sch-sum">' . esc_html($o['schedule_note']) . '</p>';
    }
    $out .= '<details class="lvk-sch-details"><summary>' . esc_html('Alle ' . $n . ' Termine anzeigen') . '</summary>';
    $out .= '<ul class="lvk-sch-list">';
    foreach ($sessions as $s) {
        if (!is_array($s)) {
            continue;
        }
        $day   = livento_cc_weekday_short(isset($s['weekday']) ? $s['weekday'] : 0);
        $date  = livento_cc_fmt_date_short(isset($s['date']) ? $s['date'] : '');
        $start = isset($s['start']) ? $s['start'] : '';
        $end   = isset($s['end']) ? $s['end'] : '';
        $time  = ($end !== '' && $end !== null) ? ($start . '–' . $end) : ('ab ' . $start);
        $out  .= '<li><span class="lvk-sch-day">' . esc_html(trim($day . ' ' . $date)) . '</span>'
               . '<span class="lvk-sch-time">' . esc_html($time) . '</span></li>';
    }
    $out .= '</ul></details></div>';
    return $out;
}

/** schema.org Schedule[] aus dem Zeitplan (ein Schedule je Uhrzeit-Spanne, byDay aus Terminen). */
function livento_cc_jsonld_schedule($o) {
    $sched = livento_cc_schedule_arr($o);
    if (!$sched) {
        return array();
    }
    $day_map = array(1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday');
    $by_range = array();
    foreach ($sched['sessions'] as $s) {
        if (!is_array($s)) {
            continue;
        }
        $start = isset($s['start']) ? $s['start'] : '';
        $end   = isset($s['end']) ? $s['end'] : '';
        if ($start === '' || $start === null) {
            continue;
        }
        $key = $start . '-' . $end;
        if (!isset($by_range[$key])) {
            $by_range[$key] = array('start' => $start, 'end' => $end, 'days' => array());
        }
        $wd = (int) (isset($s['weekday']) ? $s['weekday'] : 0);
        if (isset($day_map[$wd])) {
            $by_range[$key]['days'][$wd] = true;
        }
    }
    $out = array();
    foreach ($by_range as $r) {
        $days = array_keys($r['days']);
        sort($days);
        $by_day = array();
        foreach ($days as $d) {
            $by_day[] = 'https://schema.org/' . $day_map[$d];
        }
        $sch = array(
            '@type'           => 'Schedule',
            'byDay'           => $by_day,
            'startTime'       => $r['start'],
            'repeatFrequency' => 'P1W',
        );
        if ($r['end'] !== '' && $r['end'] !== null) {
            $sch['endTime'] = $r['end'];
        }
        $out[] = $sch;
    }
    return $out;
}

function livento_cc_fmt_price($price, $vat_exempt) {
    if ($price === null || $price === '') {
        return '';
    }
    $s = number_format((float) $price, 2, ',', '.') . ' €';
    return $vat_exempt ? $s . ' (USt-frei)' : $s;
}

function livento_cc_format_label($format) {
    $map = livento_cc_format_labels();
    return isset($map[$format]) ? $map[$format] : $format;
}

/** Slug zu lesbarem Label (fuer Themen ohne feste Label-Map). */
function livento_cc_humanize($slug) {
    $s = trim(str_replace(array('-', '_'), ' ', (string) $slug));
    if ($s === '') {
        return '';
    }
    return function_exists('mb_convert_case') ? mb_convert_case($s, MB_CASE_TITLE, 'UTF-8') : ucwords($s);
}

/**
 * Leichtgewichtiges Markdown → HTML fuer public_description / benefit.
 * Escapt zuerst, dann werden Fett/Kursiv/Links/Listen/Absaetze aufgeloest.
 * Falls Parsedown im WP installiert ist, wird es bevorzugt.
 */
function livento_cc_richtext($text) {
    if (empty($text)) {
        return '';
    }
    // Bewusst KEIN globales Parsedown nutzen: Andere Plugins (z. B. via Elementor) laden
    // teils inkompatible Parsedown-Forks ohne setSafeMode() → Fatal Error. Der eigene,
    // escapende Markdown-Light-Renderer ist self-contained und XSS-sicher.
    $text = str_replace("\r\n", "\n", (string) $text);
    $text = esc_html($text);

    // Links [Text](https://…)
    $text = preg_replace_callback(
        '/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/',
        function ($m) {
            return '<a href="' . esc_url($m[2]) . '" rel="nofollow">' . $m[1] . '</a>';
        },
        $text
    );
    // Fett **x**, dann Kursiv *x*
    $text = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/(?<!\*)\*([^*\n]+)\*(?!\*)/', '<em>$1</em>', $text);

    // Zeilenweise: "- " → Liste, Leerzeile → Absatz
    $lines = explode("\n", $text);
    $html = '';
    $in_list = false;
    $para = array();

    $flush_para = function () use (&$para, &$html) {
        if (!empty($para)) {
            $html .= '<p>' . implode('<br>', $para) . '</p>';
            $para = array();
        }
    };

    foreach ($lines as $line) {
        $trim = trim($line);
        if (preg_match('/^[-*]\s+(.*)$/', $trim, $m)) {
            $flush_para();
            if (!$in_list) {
                $html .= '<ul>';
                $in_list = true;
            }
            $html .= '<li>' . $m[1] . '</li>';
        } elseif ($trim === '') {
            if ($in_list) {
                $html .= '</ul>';
                $in_list = false;
            }
            $flush_para();
        } else {
            if ($in_list) {
                $html .= '</ul>';
                $in_list = false;
            }
            $para[] = $trim;
        }
    }
    if ($in_list) {
        $html .= '</ul>';
    }
    $flush_para();
    return $html;
}

/* ============================================================
 * 5. Rewrite-Regel fuer /<base>/<slug>/
 * ============================================================ */

add_action('init', function () {
    add_rewrite_rule(
        '^' . LIVENTO_CC_BASE . '/([^/]+)/?$',
        'index.php?pagename=' . LIVENTO_CC_BASE . '&kurs=$matches[1]',
        'top'
    );
    // Eigene Kurs-Sitemap (virtuelle Detail-URLs, die Rank Math nicht kennt)
    add_rewrite_rule('^livento-kurse\.xml$', 'index.php?livento_sitemap=1', 'top');
});

add_filter('query_vars', function ($vars) {
    $vars[] = 'kurs';
    $vars[] = 'livento_sitemap';
    return $vars;
});

register_activation_hook(__FILE__, function () {
    add_rewrite_rule(
        '^' . LIVENTO_CC_BASE . '/([^/]+)/?$',
        'index.php?pagename=' . LIVENTO_CC_BASE . '&kurs=$matches[1]',
        'top'
    );
    add_rewrite_rule('^livento-kurse\.xml$', 'index.php?livento_sitemap=1', 'top');
    flush_rewrite_rules();
});
register_deactivation_hook(__FILE__, 'flush_rewrite_rules');

// Cache-Purge-Webhook: POST /wp-json/livento/v1/purge mit Header
// X-Livento-Purge-Secret. Nur aktiv, wenn LIVENTO_CC_PURGE_SECRET gesetzt ist.
add_action('rest_api_init', function () {
    register_rest_route('livento/v1', '/purge', array(
        'methods'             => 'POST',
        'permission_callback' => '__return_true', // Auth via Shared-Secret im Callback
        'callback'            => function (WP_REST_Request $req) {
            $secret = livento_cc_purge_secret();
            if ($secret === '') {
                return new WP_REST_Response(array('ok' => false, 'error' => 'disabled'), 403);
            }
            $given = (string) $req->get_header('x-livento-purge-secret');
            if (!hash_equals($secret, $given)) {
                return new WP_REST_Response(array('ok' => false, 'error' => 'forbidden'), 403);
            }
            livento_cc_flush_cache();
            return new WP_REST_Response(array('ok' => true, 'ver' => livento_cc_ver()), 200);
        },
    ));
});

/** Aktueller Kurs-Slug aus der URL (Rewrite-Var oder ?kurs=) oder ''. */
function livento_cc_current_slug() {
    $slug = get_query_var('kurs');
    if (!$slug && isset($_GET['kurs'])) {
        $slug = sanitize_title(wp_unslash($_GET['kurs']));
    }
    return $slug ? sanitize_title($slug) : '';
}

/**
 * v1.71.0: Auf den Detailseiten das Intro der Uebersichtsseite ausblenden.
 *
 * /kurse/ und /foerdermoeglichkeiten/ tragen je ein Elementor-HTML-Widget mit dem
 * Seitenintro, und darin ein <h1>. Die Detailseiten werden ueber dieselbe WP-Seite
 * ausgeliefert, also erschien dieses H1 dort ein zweites Mal, neben dem Kurstitel —
 * am 24.11.2026 auf 56 Kurs- und 25 Foerder-Detailseiten. Zwei H1 sind nicht nur ein
 * SEO-Fehler: der Intro-Text passt inhaltlich nur zur Uebersicht und schob den
 * eigentlichen Kurs nach unten.
 *
 * Die Bedingung greift nur, wenn ein Detail-Slug anliegt. Auf der Uebersichtsseite
 * selbst bleibt das Intro unveraendert — dort ist sein H1 das richtige.
 *
 * Erkannt wird das Widget an seinem Inhalt (Klasse lv-intro), nicht an seiner
 * Elementor-ID: IDs wechseln, sobald das Widget neu angelegt wird. Passt das Muster
 * eines Tages nicht mehr, erscheint das Intro wieder — sichtbar, aber harmlos. Ohne
 * Elementor laeuft der Filter nie, das Plugin bleibt davon unberuehrt.
 */
add_filter('elementor/widget/render_content', function ($content, $widget) {
    if (!is_object($widget) || !method_exists($widget, 'get_name') || $widget->get_name() !== 'html') {
        return $content;
    }
    if (strpos($content, 'lv-intro') === false) {
        return $content;
    }
    if (!livento_cc_current_slug() && !livento_cc_foerder_current_slug()) {
        return $content;
    }
    return '';
}, 10, 2);

/**
 * v1.71.0: Den Titelteil auf hoechstens $platz Zeichen bringen, an der Wortgrenze.
 *
 * Gibt '' zurueck, wenn das nicht geht, ohne ein Wort zu zerschneiden — etwa bei einem
 * Titel ohne Leerzeichen. Der Aufrufer nimmt dann den ganzen Titel.
 */
function livento_cc_titel_stamm($titel, $platz) {
    if (mb_strlen($titel) <= $platz) {
        return $titel;
    }
    $kurz = mb_substr($titel, 0, $platz);
    $lz   = mb_strrpos($kurz, ' ');
    if ($lz === false || $lz < 20) {
        return '';
    }
    $kurz = mb_substr($kurz, 0, $lz);
    // Eine Klammer, die erst nach dem Schnitt geschlossen wuerde, mit ihrem Inhalt weg.
    $auf = mb_strrpos($kurz, '(');
    $zu  = mb_strrpos($kurz, ')');
    if ($auf !== false && ($zu === false || $zu < $auf)) {
        $kurz = mb_substr($kurz, 0, $auf);
    }
    // Trenn- und Satzzeichen am Ende weg. Mit /u, damit der Gedankenstrich ein Zeichen
    // bleibt — rtrim() arbeitet byteweise und wuerde ihn in Bruchstuecke zerlegen.
    $kurz = preg_replace('/[\s\x{00A0}\-\x{2013}\x{2014},;:\x{00B7}|\/]+$/u', '', $kurz);
    return mb_strlen($kurz) >= 20 ? $kurz : '';
}

/**
 * v1.71.0: Dokumenttitel bauen — Titel + Zusatz, moeglichst unter $max Zeichen.
 *
 * Vorher schnitt die Foerderseite mit mb_substr($titel, 0, 62) hart ab und hing den
 * Zusatz an. Live stand damit am 24.11.2026 „Weiterbildungsfoerderung Brandenburg
 * (Weiterbildungsrichtlinie – Foerderung | Livento": mitten im Wort, mit offener
 * Klammer, zusammen 84 Zeichen.
 *
 * $andere nimmt die Titel der uebrigen Seiten derselben Familie. Wuerde die Kuerzung
 * zwei Seiten gleich benennen, bleibt der Titel ganz: ein langer eindeutiger Titel ist
 * besser als zwei identische. Bei den Foerderprogrammen trifft das zu —
 * „Sachsen-Anhalt WEITERBILDUNG – individueller Zugang" und „… – betrieblicher
 * Zugang" fielen sonst beide auf „Sachsen-Anhalt WEITERBILDUNG" zusammen (gemessen
 * 24.11.2026). Ohne $andere wird ohne diese Pruefung gekuerzt.
 *
 * Gekuerzt wird nur der Titel, der Zusatz bleibt: er sagt, worum es auf der Seite geht.
 */
function livento_cc_titel_kuerzen($titel, $max, $zusatz = '', $andere = array()) {
    $titel = trim(preg_replace('/\s+/u', ' ', (string) $titel));
    if ($titel === '') {
        return trim(preg_replace('/^[\s\x{2013}\x{2014}\-|]+/u', '', $zusatz));
    }
    if (mb_strlen($titel . $zusatz) <= $max) {
        return $titel . $zusatz;
    }
    $platz = $max - mb_strlen($zusatz);
    if ($platz < 25) {
        // Der Zusatz frisst den Titel auf. Dann bleibt der Titel, wie er ist.
        return $titel . $zusatz;
    }
    $kurz = livento_cc_titel_stamm($titel, $platz);
    if ($kurz === '' || $kurz === $titel) {
        return $titel . $zusatz;
    }
    foreach ((array) $andere as $a) {
        $a = trim(preg_replace('/\s+/u', ' ', (string) $a));
        if ($a === '' || $a === $titel) {
            continue;
        }
        if (livento_cc_titel_stamm($a, $platz) === $kurz) {
            return $titel . $zusatz;
        }
    }
    return $kurz . $zusatz;
}

/* ============================================================
 * 5b. Eigene Kurs-Sitemap (https://…/livento-kurse.xml)
 *
 * Rank Math listet nur echte WP-Inhalte — die virtuellen /kurse/<slug>-Detail-
 * URLs kennt es nicht. Daher liefern wir sie als eigene XML-Sitemap.
 *
 * WICHTIG: Der Dateiname endet bewusst NICHT auf "-sitemap.xml" — dieses Muster
 * beansprucht Rank Math fuer seine eigenen Sitemaps (page-sitemap.xml etc.) und
 * wuerde unsere URL mit 404 abfangen. Daher "livento-kurse.xml".
 *
 * Direkt in der Google Search Console einreichbar: /livento-kurse.xml
 * (Wir versuchen zusaetzlich, sie per Filter in den Rank-Math-Index zu haengen.)
 * ============================================================ */

add_action('template_redirect', function () {
    if (!get_query_var('livento_sitemap')) {
        return;
    }
    $offerings = livento_cc_get_offerings();
    if (!headers_sent()) {
        header('Content-Type: application/xml; charset=UTF-8');
        // KEIN X-Robots-Tag: noindex — Google verweigert sonst die Verarbeitung der Sitemap
        // („Sitemap konnte nicht gelesen werden"). Sitemap-Dateien werden ohnehin nicht indexiert.
        header('Cache-Control: public, max-age=3600');
    }
    echo livento_cc_build_sitemap_xml($offerings); // phpcs:ignore — fertiges XML
    exit;
}, 0); // Prioritaet 0: noch vor redirect_canonical → kein Trailing-Slash-301

function livento_cc_build_sitemap_xml($offerings) {
    $urls  = '  <url><loc>' . esc_url(livento_cc_list_url()) . '</loc><changefreq>daily</changefreq><priority>0.9</priority></url>' . "\n";
    foreach ($offerings as $o) {
        if (empty($o['slug'])) {
            continue;
        }
        // v1.68.0: Nur den Gruppenfuehrer eintragen. Vorher standen alle Termine einer
        // Kursgruppe in der Sitemap — wir haben Google also aktiv aufgefordert, die
        // Dubletten zu indexieren, waehrend das Canonical auf eine andere Seite zeigt.
        if (!livento_cc_is_canonical_slug($o)) {
            continue;
        }
        $lastmod = '';
        if (!empty($o['published_at'])) {
            $lastmod = substr($o['published_at'], 0, 10);
        } elseif (!empty($o['start_datetime'])) {
            $lastmod = substr($o['start_datetime'], 0, 10);
        }
        $urls .= '  <url><loc>' . esc_url(livento_cc_detail_url($o['slug'])) . '</loc>'
               . ($lastmod ? '<lastmod>' . esc_html($lastmod) . '</lastmod>' : '')
               . '<changefreq>weekly</changefreq><priority>0.8</priority></url>' . "\n";
    }
    // Förderprogramme (Übersicht + Detailseiten)
    if (function_exists('livento_cc_foerderungen')) {
        $urls .= '  <url><loc>' . esc_url(livento_cc_foerder_list_url()) . '</loc><changefreq>monthly</changefreq><priority>0.7</priority></url>' . "\n";
        foreach (livento_cc_foerderungen() as $f) {
            if (empty($f['slug'])) {
                continue;
            }
            $urls .= '  <url><loc>' . esc_url(livento_cc_foerder_url($f['slug'])) . '</loc><changefreq>monthly</changefreq><priority>0.6</priority></url>' . "\n";
        }
    }
    return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
         . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n"
         . $urls
         . '</urlset>';
}

// Kurs-Sitemap in den Rank-Math-Sitemap-Index einhaengen (erscheint in /sitemap_index.xml).
add_filter('rank_math/sitemap/index', function ($xml) {
    $loc = esc_url(home_url('/livento-kurse.xml'));
    return $xml . '<sitemap><loc>' . $loc . '</loc><lastmod>' . esc_html(gmdate('c')) . '</lastmod></sitemap>';
});

/* ============================================================
 * 6. SEO: Canonical, Title, Meta, JSON-LD (nur Detailansicht)
 * ============================================================ */

// Auf Detailansichten kein WordPress-Canonical-Redirect (sonst Sprung zur Liste).
add_filter('redirect_canonical', function ($redirect) {
    if (livento_cc_current_slug() || get_query_var('livento_sitemap')) {
        return false; // weder Kurs-Detail noch Sitemap sollen kanonisch umgeleitet werden
    }
    return $redirect;
});

// Aktuellen Kurs einmal pro Request laden und globale Head-Hooks registrieren.
add_action('wp', function () {
    $slug = livento_cc_current_slug();
    if (!$slug) {
        return;
    }
    $offering = livento_cc_get_offering($slug);
    $GLOBALS['livento_cc_current'] = $offering; // array|null

    if (!$offering) {
        // Unbekannter Slug → echtes 404 + noindex.
        add_action('wp_head', function () {
            echo '<meta name="robots" content="noindex,follow">' . "\n";
        });
        status_header(404);
        return;
    }

    // SEO: Werte einmal bauen, dann Rank Math fuettern (genau EINE saubere Ausgabe) bzw.
    // — falls Rank Math fehlt — selbst im wp_head ausgeben. Title immer setzen, sonst
    // ueberschreibt RM ihn mit dem /kurse/-Seitentitel.
    $seo = livento_cc_seo_values($offering);
    add_filter('pre_get_document_title', function () use ($seo) {
        return $seo['title'];
    }, 20);

    // v1.22.0: Body-Klasse fuer Theme-/CSS-Hooks. Nur hier registriert (Offering existiert),
    // daher nie auf dem Katalog- oder 404-Pfad.
    add_filter('body_class', function ($classes) {
        $classes[] = 'lvk-course-detail';
        return $classes;
    });

    if (livento_cc_rankmath_active()) {
        livento_cc_seo_apply_rankmath($offering, $seo);
    } else {
        livento_cc_seo_apply_manual($offering, $seo);
    }
});

/**
 * v1.34.0: SEO fuer die Ticket-Detailseiten (/e-learning/<slug>/).
 *
 * Bewusst ein ZWEITER wp-Hook: der Kurs-Hook oben steigt aus, wenn kein ?kurs=
 * gesetzt ist — und Tarifseiten setzen das nie, weil sie keine virtuellen
 * Rewrite-Seiten sind, sondern echte WordPress-Seiten mit [livento_tarif] in
 * einem Elementor-Widget. Genau deshalb hatten sie bis v1.33.0 keinerlei SEO.
 */
add_action('wp', function () {
    if (livento_cc_current_slug()) {
        return; // Kursdetailseite — die hat ihren eigenen Hook oben
    }
    $family = livento_cc_current_family();
    if (!$family) {
        return;
    }

    $seo = livento_cc_tariff_seo_values($family);
    add_filter('pre_get_document_title', function () use ($seo) {
        return $seo['title'];
    }, 20);
    add_filter('body_class', function ($classes) {
        $classes[] = 'lvk-tarif-detail';
        return $classes;
    });

    if (livento_cc_rankmath_active()) {
        livento_cc_tariff_seo_apply_rankmath($family, $seo);
    } else {
        livento_cc_tariff_seo_apply_manual($family, $seo);
    }
});

/**
 * v1.34.0: Tariffamilie der aktuell aufgerufenen Seite — oder null.
 *
 * Erkennung ueber den Seiten-Slug, nicht ueber das family-Attribut des Shortcodes:
 * Der Shortcode steckt in einem Elementor-Widget, seine Attribute stehen also in
 * _elementor_data und nicht in post_content — has_shortcode() liefe ins Leere.
 * Dass der Seiten-Slug dem Familien-Slug entspricht, ist ohnehin dokumentierte
 * Pflicht (Admin-Hilfe, Abschnitt Tarife), also ein belastbarer Anker.
 */
function livento_cc_current_family() {
    static $cache = false; // false = noch nicht ermittelt, null = keine Tarifseite
    if ($cache !== false) {
        return $cache;
    }
    $cache = null;
    if (!is_page()) {
        return null;
    }
    $post = get_post();
    if ($post && !empty($post->post_name)) {
        // find_family matcht Key ODER Slug; die Landing ("e-learning") und
        // /e-learning/individuell/ treffen bewusst keine Familie -> null.
        $cache = livento_cc_find_family($post->post_name);
    }
    return $cache;
}

/**
 * v1.34.0: SEO-Werte einer Ticket-Detailseite.
 *
 * meta_title, weil "PflichtTicket | Livento" ein reiner Markenbegriff waere, nach
 * dem niemand sucht. Fallback bleibt trotzdem besser als der Status quo (roher Slug).
 * Whitespace wird normalisiert: meta_description ist ein Freitextfeld, in dem schon
 * einmal eine mehrzeilige Liste gelandet ist — mb_substr() haette daraus eine
 * Description mit Zeilenumbruechen gemacht.
 */
function livento_cc_tariff_seo_values($family) {
    $descr_src = !empty($family['meta_description'])
        ? $family['meta_description']
        : (!empty($family['claim']) ? $family['claim'] : $family['public_description']);
    $descr = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags((string) $descr_src)));

    return array(
        'url'   => get_permalink(),
        'title' => !empty($family['meta_title']) ? $family['meta_title'] : $family['name'] . ' | Livento',
        'descr' => mb_substr($descr, 0, 160),
        'img'   => !empty($family['public_image_url']) ? $family['public_image_url'] : '',
    );
}

/** Variante A: Rank Math fuettern (genau EINE Ausgabe) und den @graph umbauen. */
function livento_cc_tariff_seo_apply_rankmath($family, $seo) {
    add_filter('rank_math/frontend/canonical',   function () use ($seo) { return $seo['url']; });
    add_filter('rank_math/frontend/description', function () use ($seo) { return $seo['descr']; });
    add_filter('rank_math/frontend/title',       function () use ($seo) { return $seo['title']; });
    add_filter('rank_math/frontend/robots',      function () {
        return array('index' => 'index', 'follow' => 'follow');
    });
    add_filter('rank_math/opengraph/url',                     function () use ($seo) { return $seo['url']; });
    add_filter('rank_math/opengraph/facebook/og_title',       function () use ($seo) { return $seo['title']; });
    add_filter('rank_math/opengraph/facebook/og_description', function () use ($seo) { return $seo['descr']; });
    add_filter('rank_math/opengraph/type',                    function () { return 'website'; });
    if (!empty($seo['img'])) {
        add_filter('rank_math/opengraph/facebook/og_image',            function () use ($seo) { return $seo['img']; });
        add_filter('rank_math/opengraph/facebook/og_image_secure_url', function () use ($seo) { return $seo['img']; });
        add_filter('rank_math/opengraph/facebook/og_image_width',  '__return_empty_string');
        add_filter('rank_math/opengraph/facebook/og_image_height', '__return_empty_string');
        add_filter('rank_math/opengraph/facebook/og_image_type',   '__return_empty_string');
        add_filter('rank_math/opengraph/facebook/og_image_alt',        function () use ($family) { return $family['name']; });
        add_filter('rank_math/opengraph/twitter/twitter_image',        function () use ($seo) { return $seo['img']; });
        add_filter('rank_math/opengraph/twitter/twitter_image_alt',    function () use ($family) { return $family['name']; });
    }

    add_filter('rank_math/json_ld', function ($data, $jsonld) use ($family, $seo) {
        if (!is_array($data)) {
            $data = array();
        }

        // Article raus: Rank Math haengt an Seiten per Default einen Article-Knoten
        // (samt Autor-Person). Zusammen mit unserem Product behauptete die Seite
        // gleichzeitig "Artikel" und "Produkt" — ein widerspruechliches Signal.
        foreach ($data as $k => $piece) {
            if (!isset($piece['@type'])) {
                continue;
            }
            if (array_intersect((array) $piece['@type'], array('Article', 'BlogPosting', 'NewsArticle'))) {
                unset($data[$k]);
            }
        }

        $product = livento_cc_jsonld_tariff_product($family, $seo['url']);
        if ($product) {
            $data['livento_tariff'] = $product;
        }

        $faq = livento_cc_jsonld_faq($family);
        if ($faq) {
            unset($faq['@context']); // im @graph redundant
            $data['livento_faq'] = $faq;
        }

        // Letzte Brotkrume traegt sonst den WordPress-Seitentitel — und der ist bei
        // diesen Seiten der rohe Slug ("pflicht-ticket").
        foreach ($data as $k => $piece) {
            if (!isset($piece['@type']) || $piece['@type'] !== 'BreadcrumbList') {
                continue;
            }
            if (empty($piece['itemListElement']) || !is_array($piece['itemListElement'])) {
                continue;
            }
            $items = $piece['itemListElement'];
            $last  = count($items) - 1;
            if ($last >= 0) {
                $items[$last]['name']            = $family['name'];
                $data[$k]['itemListElement']     = $items;
            }
            break;
        }
        return $data;
    }, 99, 2);

    // Sichtbare Brotkrumenleiste: ebenfalls den Slug durch den Familiennamen ersetzen.
    add_filter('rank_math/frontend/breadcrumb/items', function ($crumbs) use ($family, $seo) {
        if (is_array($crumbs) && !empty($crumbs)) {
            $crumbs[count($crumbs) - 1] = array($family['name'], $seo['url']);
        }
        return $crumbs;
    });
}

/** Fallback (Rank Math nicht aktiv): Tags selbst ausgeben. */
function livento_cc_tariff_seo_apply_manual($family, $seo) {
    add_action('wp_head', function () use ($family, $seo) {
        echo "\n<!-- Livento Tarif -->\n";
        echo '<link rel="canonical" href="' . esc_url($seo['url']) . '">' . "\n";
        echo '<meta name="description" content="' . esc_attr($seo['descr']) . '">' . "\n";
        echo '<meta name="robots" content="index,follow">' . "\n";
        echo '<meta property="og:type" content="website">' . "\n";
        echo '<meta property="og:title" content="' . esc_attr($seo['title']) . '">' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($seo['descr']) . '">' . "\n";
        echo '<meta property="og:url" content="' . esc_url($seo['url']) . '">' . "\n";
        if ($seo['img']) {
            echo '<meta property="og:image" content="' . esc_url($seo['img']) . '">' . "\n";
            echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
            echo '<meta name="twitter:image" content="' . esc_url($seo['img']) . '">' . "\n";
        }
        $product = livento_cc_jsonld_tariff_product($family, $seo['url']);
        if ($product) {
            $product['@context'] = 'https://schema.org';
            echo '<script type="application/ld+json">' . wp_json_encode($product) . '</script>' . "\n";
        }
        $faq = livento_cc_jsonld_faq($family);
        if ($faq) {
            echo '<script type="application/ld+json">' . wp_json_encode($faq) . '</script>' . "\n";
        }
    }, 1);
}

/**
 * v1.34.0: schema.org/Product der Familie mit AggregateOffer.
 *
 * AggregateOffer statt eines Offer je Variante mit Festbetrag: Der Preis haengt an
 * der Teamgroesse. Ein fixer Betrag war schlicht falsch — die Seite zeigte den Preis
 * fuer N Beschaeftigte, das Schema behauptete ihn als DEN Preis. Preis-Mismatch
 * zwischen Schema und sichtbarer Seite ist genau das, was Google an Product prueft.
 * lowPrice kommt aus price_from der View, also aus derselben fn_calc_tariff_price
 * wie der sichtbare "ab"-Preis im Seitenkopf — eine Quelle, keine zweite Rechnung.
 * Kein highPrice: nach oben ist die Staffel offen (ab 151 Beschaeftigten individuell).
 */
function livento_cc_jsonld_tariff_product($family, $url) {
    $from = isset($family['price_from']) && is_array($family['price_from']) ? $family['price_from'] : null;
    if (!$from || !isset($from['yearly_net']) || $from['yearly_net'] === null) {
        return null; // reiner Angebotsfall: lieber gar kein Preis-Schema als ein geratenes
    }

    $offer_count = 0;
    foreach ((array) $family['plans'] as $plan) {
        $offer_count += count((array) $plan['bundles']);
    }

    $descr_src = !empty($family['meta_description'])
        ? $family['meta_description']
        : (!empty($family['claim']) ? $family['claim'] : $family['public_description']);

    $product = array(
        '@type'       => 'Product',
        'name'        => $family['name'],
        'description' => mb_substr(trim(preg_replace('/\s+/', ' ', wp_strip_all_tags((string) $descr_src))), 0, 300),
        'brand'       => array('@type' => 'Brand', 'name' => 'Livento'),
        'offers'      => array(
            '@type'         => 'AggregateOffer',
            'lowPrice'      => number_format((float) $from['yearly_net'], 2, '.', ''),
            'priceCurrency' => 'EUR',
            'availability'  => 'https://schema.org/InStock',
            'url'           => $url,
        ),
    );
    if ($offer_count > 0) {
        $product['offers']['offerCount'] = $offer_count;
    }
    if (!empty($family['public_image_url'])) {
        $product['image'] = $family['public_image_url'];
    }
    return $product;
}

/**
 * v1.70.0: Block „Das wirst du koennen" (Campus Connect v4.63.0, learning_outcomes).
 *
 * Steht auf der Detailseite zwischen Zielgruppe und „Dein Nutzen" — die erste Antwort auf
 * „was kann ich danach", bevor argumentiert wird. Vorlage ist der Aufbau von Kenbi: dort
 * tragen 60 von 62 Seiten an dieser Stelle genau vier knappe Punkte, zusammen rund 42
 * Woerter, und die Leserin weiss nach etwa 70 Woertern, was sie danach kann.
 *
 * Leeres oder fehlendes Feld = kein Block. Kein Rueckfall auf benefit, course_contents oder
 * die Modul-Lernziele: ein automatisch gefuellter Block waere laenger und unpraeziser als
 * keiner, und „Dein Nutzen" steht ohnehin direkt darunter.
 *
 * Kennt die Datenbank die Spalte noch nicht, fehlt sie im Datensatz und der Block entfaellt
 * stillschweigend — Plugin- und App-Release bleiben unabhaengig (Muster aus v1.58.0).
 */
function livento_cc_outcomes_html($o) {
    $werte = isset($o['learning_outcomes']) ? $o['learning_outcomes'] : null;
    // PostgREST liefert text[] als JSON-Array; bei aelteren Staenden kann auch ein
    // JSON-String ankommen. Beides annehmen, alles andere verwerfen.
    if (is_string($werte)) {
        $dekodiert = json_decode($werte, true);
        $werte = is_array($dekodiert) ? $dekodiert : null;
    }
    if (!is_array($werte)) {
        return '';
    }

    $punkte = array();
    foreach ($werte as $w) {
        if (!is_string($w)) {
            continue;
        }
        $w = trim(wp_strip_all_tags($w));
        if ($w !== '') {
            $punkte[] = $w;
        }
    }
    // Obergrenze wie die CHECK-Bedingung in Campus Connect: mehr waere ein Pflegefehler,
    // und die Seite soll ihn nicht ausspielen.
    $punkte = array_slice($punkte, 0, 6);
    if (empty($punkte)) {
        return '';
    }

    $out = '<div class="lvk-section lvk-outcomes"><h2>Das wirst du können</h2><ul class="lvk-outcomes__list">';
    foreach ($punkte as $p) {
        $out .= '<li>' . esc_html($p) . '</li>';
    }
    return $out . '</ul></div>';
}

/** Ist Rank Math aktiv? Dann fuettern wir es, statt selbst Tags auszugeben (Dublettenschutz). */
function livento_cc_rankmath_active() {
    return defined('RANK_MATH_VERSION') || class_exists('RankMath\\Helper');
}

/** Gemeinsame SEO-Werte einer Kurs-Detailseite: url, title, descr (≤160), img. */
function livento_cc_seo_values($o) {
    // v1.22.0: Title = nur „{Kursname} | Livento". Format/Datum (+ 65-Zeichen-Kuerzung)
    // entfernt — die Kuerzung kappte das Startdatum mittendrin („· 3.").
    // v1.18.0: redaktionelle meta_description bevorzugen, sonst Fallback-Kette.
    $descr_src = !empty($o['meta_description'])
        ? $o['meta_description']
        : ($o['public_description'] ?: ($o['short_description'] ?: $o['title']));
    return array(
        // v1.68.0: Nicht die eigene Adresse, sondern die der Kursgruppe. Jeder Durchgang
        // hat eine eigene Detailseite; ohne das zeigten bis zu fuenf gleiche Seiten je
        // Kurs mit Canonical auf sich selbst. Wirkt auf Canonical, og:url, die Course-URL
        // im JSON-LD und die letzte Brotkrume — alle sollen dieselbe Seite benennen.
        'url'   => livento_cc_detail_url(livento_cc_canonical_slug($o)),
        'title' => $o['title'] . ' | Livento',
        'descr' => mb_substr(trim(wp_strip_all_tags($descr_src)), 0, 160),
        'img'   => $o['public_image_url'] ?? '',
    );
}

/** BreadcrumbList: Startseite › Kurse › {Kursname}. */
function livento_cc_jsonld_breadcrumb($o, $url) {
    return array(
        '@context'        => 'https://schema.org',
        '@type'           => 'BreadcrumbList',
        'itemListElement' => array(
            array('@type' => 'ListItem', 'position' => 1, 'name' => 'Startseite', 'item' => home_url('/')),
            array('@type' => 'ListItem', 'position' => 2, 'name' => 'Kurse',      'item' => livento_cc_list_url()),
            array('@type' => 'ListItem', 'position' => 3, 'name' => $o['title'],  'item' => $url),
        ),
    );
}

/**
 * Variante A: Rank Math mit den Kurswerten fuettern. So entsteht genau EINE Ausgabe
 * (kein zweites Canonical/og:url/description) und Course faedelt sich in den RM-@graph ein.
 */
function livento_cc_seo_apply_rankmath($o, $seo) {
    add_filter('rank_math/frontend/canonical',   function () use ($seo) { return $seo['url']; });
    add_filter('rank_math/frontend/description', function () use ($seo) { return $seo['descr']; });
    add_filter('rank_math/frontend/title',       function () use ($seo) { return $seo['title']; });
    add_filter('rank_math/frontend/robots',      function () {
        return array('index' => 'index', 'follow' => 'follow');
    });
    add_filter('rank_math/opengraph/url',                     function () use ($seo) { return $seo['url']; });
    add_filter('rank_math/opengraph/facebook/og_title',       function () use ($o)   { return $o['title']; });
    add_filter('rank_math/opengraph/facebook/og_description', function () use ($seo) { return $seo['descr']; });
    add_filter('rank_math/opengraph/type',                    function () { return 'website'; });
    if (!empty($seo['img'])) {
        // v1.22.0: nicht nur die Bild-URL ueberschreiben, sonst beschreiben Breite/Hoehe/Alt/
        // Twitter-Bild weiter Rank Maths Default (Logo, 500x500) -> falsches Social-Cropping.
        // Maße/Typ weglassen (remote Bild, echte Größe unbekannt; Plattformen messen selbst).
        add_filter('rank_math/opengraph/facebook/og_image',            function () use ($seo) { return $seo['img']; });
        add_filter('rank_math/opengraph/facebook/og_image_secure_url', function () use ($seo) { return $seo['img']; });
        add_filter('rank_math/opengraph/facebook/og_image_width',  '__return_empty_string');
        add_filter('rank_math/opengraph/facebook/og_image_height', '__return_empty_string');
        add_filter('rank_math/opengraph/facebook/og_image_type',   '__return_empty_string');
        add_filter('rank_math/opengraph/facebook/og_image_alt',        function () use ($o) { return $o['title']; });
        add_filter('rank_math/opengraph/twitter/twitter_image',        function () use ($seo) { return $seo['img']; });
        add_filter('rank_math/opengraph/twitter/twitter_image_alt',    function () use ($o) { return $o['title']; });
    }

    // Course (+ Offer/CourseInstance), FAQPage und BreadcrumbList in den RM-@graph haengen.
    add_filter('rank_math/json_ld', function ($data, $jsonld) use ($o, $seo) {
        if (!is_array($data)) {
            $data = array();
        }
        $course = livento_cc_jsonld_course($o, $seo['url']);
        unset($course['@context']); // im @graph redundant
        $data['livento_course'] = $course;

        $faq = livento_cc_jsonld_faq($o);
        if ($faq) {
            unset($faq['@context']);
            $data['livento_faq'] = $faq;
        }

        // Breadcrumb: vorhandene RM-BreadcrumbList um den Kursnamen ergaenzen, sonst eigene.
        $bc_key = null;
        foreach ($data as $k => $piece) {
            if (isset($piece['@type']) && $piece['@type'] === 'BreadcrumbList') {
                $bc_key = $k;
                break;
            }
        }
        if ($bc_key !== null && !empty($data[$bc_key]['itemListElement']) && is_array($data[$bc_key]['itemListElement'])) {
            $items   = $data[$bc_key]['itemListElement'];
            $items[] = array(
                '@type'    => 'ListItem',
                'position' => count($items) + 1,
                'name'     => $o['title'],
                'item'     => $seo['url'],
            );
            $data[$bc_key]['itemListElement'] = $items;
        } else {
            $bc = livento_cc_jsonld_breadcrumb($o, $seo['url']);
            unset($bc['@context']);
            $data['livento_breadcrumb'] = $bc;
        }
        return $data;
    }, 99, 2);

    // Sichtbare Brotkrumen-Leiste (falls RM-Breadcrumbs aktiv): Kursname als letzte Krume.
    add_filter('rank_math/frontend/breadcrumb/items', function ($crumbs) use ($o, $seo) {
        if (is_array($crumbs)) {
            $crumbs[] = array($o['title'], $seo['url']);
        }
        return $crumbs;
    });
}

/** Fallback (Rank Math nicht aktiv): Tags selbst im wp_head ausgeben (inkl. Breadcrumb). */
function livento_cc_seo_apply_manual($o, $seo) {
    add_action('wp_head', function () use ($o, $seo) {
        $url   = $seo['url'];
        $descr = $seo['descr'];
        $img   = $seo['img'];

        echo "\n<!-- Livento Kurskatalog -->\n";
        echo '<link rel="canonical" href="' . esc_url($url) . '">' . "\n";
        echo '<meta name="description" content="' . esc_attr($descr) . '">' . "\n";
        echo '<meta name="robots" content="index,follow">' . "\n";
        echo '<meta property="og:type" content="website">' . "\n";
        echo '<meta property="og:title" content="' . esc_attr($o['title']) . '">' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($descr) . '">' . "\n";
        echo '<meta property="og:url" content="' . esc_url($url) . '">' . "\n";
        if ($img) {
            echo '<meta property="og:image" content="' . esc_url($img) . '">' . "\n";
            echo '<meta property="og:image:alt" content="' . esc_attr($o['title']) . '">' . "\n";
            echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
            echo '<meta name="twitter:image" content="' . esc_url($img) . '">' . "\n";
            echo '<meta name="twitter:image:alt" content="' . esc_attr($o['title']) . '">' . "\n";
        }
        echo '<script type="application/ld+json">' . wp_json_encode(livento_cc_jsonld_course($o, $url)) . '</script>' . "\n";
        $faq_schema = livento_cc_jsonld_faq($o);
        if ($faq_schema) {
            echo '<script type="application/ld+json">' . wp_json_encode($faq_schema) . '</script>' . "\n";
        }
        echo '<script type="application/ld+json">' . wp_json_encode(livento_cc_jsonld_breadcrumb($o, $url)) . '</script>' . "\n";
    }, 1);
}

/** Zeitstunden fuer schema courseWorkload: UE×0,75 (45-Min-Einheiten) bzw. aus duration_minutes. */
function livento_cc_course_workload_hours($o) {
    if (isset($o['total_hours']) && $o['total_hours'] !== null && $o['total_hours'] !== '') {
        $h = (float) $o['total_hours'];
        if (($o['hours_unit'] ?? '') === 'unterrichtsstunden') {
            $h = $h * 0.75;
        }
        return (int) round($h);
    }
    if (!empty($o['duration_minutes'])) {
        return (int) round(((int) $o['duration_minutes']) / 60);
    }
    return 0;
}

function livento_cc_jsonld_course($o, $url) {
    $format_to_mode = array(
        'praesenz' => 'onsite', 'online_live' => 'online', 'selbstlern' => 'online',
        'blended' => 'blended', 'flexibel_modular' => 'blended', 'kompakt' => 'onsite',
    );
    $mode = isset($format_to_mode[$o['format']]) ? $format_to_mode[$o['format']] : 'onsite';
    $sold_out = isset($o['max_participants'], $o['enrolled_count'])
        && $o['max_participants'] !== null
        && (int) $o['enrolled_count'] >= (int) $o['max_participants'];

    $offer = array(
        '@type'         => 'Offer',
        'url'           => $o['wc_checkout_url'] ?: $url,
        // v1.52.0: Anfragekurs hat keinen buchbaren Termin — vormerken statt „auf Lager".
        'availability'  => livento_cc_is_on_request($o) ? 'https://schema.org/PreOrder'
            : ($sold_out ? 'https://schema.org/SoldOut' : 'https://schema.org/InStock'),
        'priceCurrency' => 'EUR',
    );
    if ($o['public_price'] !== null && $o['public_price'] !== '') {
        $offer['price'] = number_format((float) $o['public_price'], 2, '.', '');
    }
    if (!empty($o['is_vat_exempt'])) {
        $offer['category'] = 'USt-frei';
    }

    $instance = array('@type' => 'CourseInstance', 'courseMode' => $mode, 'offers' => $offer);
    $workload = livento_cc_course_workload_hours($o);
    if ($workload > 0) {
        $instance['courseWorkload'] = 'PT' . $workload . 'H';
    }
    if (!empty($o['start_datetime'])) {
        $instance['startDate'] = $o['start_datetime'];
    }
    if (!empty($o['end_datetime'])) {
        $instance['endDate'] = $o['end_datetime'];
    }
    $course_schedule = livento_cc_jsonld_schedule($o);
    if (!empty($course_schedule)) {
        $instance['courseSchedule'] = $course_schedule;
    }
    if (!empty($o['site_name']) || !empty($o['site_city'])) {
        $place = array('@type' => 'Place', 'name' => $o['site_name'] ?: 'Livento');
        if (!empty($o['site_city'])) {
            $place['address'] = array(
                '@type' => 'PostalAddress',
                'addressLocality' => $o['site_city'],
                'addressCountry' => 'DE',
            );
        }
        $instance['location'] = $place;
    }
    // v1.45.0: Dozenten kommen aus public_offering_instructors() (Programme UND
    // Einzeltermine). instructor_name bleibt als Rueckfallebene stehen — es war nur
    // fuer Einzeltermine je gefuellt.
    if (!empty($o['instructors']) && is_array($o['instructors'])) {
        $persons = array();
        foreach ($o['instructors'] as $i) {
            // false = schlanke Referenz. Der vollstaendige Personenknoten steht auf
            // der Dozentenseite; hier verbindet die @id beide Nennungen.
            $persons[] = livento_cc_instructor_schema($i, false);
        }
        $instance['instructor'] = $persons;
    } elseif (!empty($o['instructor_name'])) {
        $instance['instructor'] = array('@type' => 'Person', 'name' => $o['instructor_name']);
    }

    $schema = array(
        '@context'    => 'https://schema.org',
        '@type'       => 'Course',
        'name'        => $o['title'],
        'description' => $o['public_description'] ?: ($o['short_description'] ?: $o['title']),
        // v1.45.0: mit stabiler @id, damit provider hier und worksFor bei den
        // Dozenten auf denselben Organisationsknoten zeigen.
        'provider'    => livento_cc_org_node(true),
        'url'               => $url,
        'hasCourseInstance' => array($instance),
    );
    if (!empty($o['public_image_url'])) {
        $schema['image'] = $o['public_image_url'];
    }
    // v1.59.0: nur, wenn die Sterne auch sichtbar auf der Seite stehen (gleiche Quelle).
    $rating = livento_cc_rating($o);
    if ($rating !== null) {
        $schema['aggregateRating'] = array(
            '@type'       => 'AggregateRating',
            'ratingValue' => number_format($rating['avg'], 2, '.', ''),
            'reviewCount' => $rating['count'],
            'bestRating'  => 5,
            'worstRating' => 1,
        );
    }
    return $schema;
}

/**
 * v1.69.0: Bewertungen je Kursgruppe (Campus Connect v4.62.0).
 *
 * Jeder Durchgang ist in Campus Connect ein eigenes Programm, Bewertungen haengen also am
 * Durchgang. Pro Durchgang wird LIVENTO_CC_RATING_MIN praktisch nie erreicht, und sobald ein
 * Durchgang ausgelaufen ist, sind seine Bewertungen dauerhaft unsichtbar. Am 23.11.2026 lagen
 * 15 von 19 freigegebenen Bewertungen auf Durchgaengen, die nicht mehr oeffentlich sind.
 *
 * fn_public_offering_group_ratings() fasst sie je Kurs zusammen und liefert sie je
 * oeffentlichem Slug. Die Funktion laeuft als SECURITY DEFINER, weil anon die ausgelaufenen
 * Durchgaenge nicht sieht; sie gibt nur Slug und Kennzahlen heraus.
 *
 * Rueckgabe: Slug => array('avg' => float, 'count' => int). Leeres Array, wenn die Funktion
 * nicht erreichbar ist — dann gelten weiter die Einzelwerte aus public_offerings.
 */
function livento_cc_get_group_ratings() {
    $key = 'livento_cc_group_ratings_v' . livento_cc_ver();
    $cached = get_transient($key);
    if (is_array($cached)) {
        return $cached;
    }
    // Bewusst ohne select=: die Funktion liefert genau vier Spalten, und ein Tippfehler im
    // Spaltennamen wuerde den ganzen Abruf mit HTTP 400 scheitern lassen.
    $data = livento_cc_rest_get_path('rpc/fn_public_offering_group_ratings');
    if (is_wp_error($data)) {
        error_log('[livento] fn_public_offering_group_ratings nicht erreichbar: ' . $data->get_error_message());
        return array();
    }
    $map = array();
    foreach ((array) $data as $r) {
        $s = isset($r['slug']) ? (string) $r['slug'] : '';
        $n = isset($r['group_rating_count']) ? (int) $r['group_rating_count'] : 0;
        if ($s !== '' && $n > 0 && isset($r['group_rating_avg']) && $r['group_rating_avg'] !== null) {
            $map[$s] = array('avg' => (float) $r['group_rating_avg'], 'count' => $n);
        }
    }
    set_transient($key, $map, LIVENTO_CC_TTL);
    return $map;
}

/**
 * v1.59.0: Freigegebene Bewertung eines Angebots — oder null unter LIVENTO_CC_RATING_MIN.
 * Einzige Quelle fuer Schema, Detailseite, Karte und Sortierung: Was im Schema steht,
 * steht auch sichtbar auf der Seite (Google-Richtlinie fuer Bewertungs-Markup).
 *
 * v1.69.0: Hat das Angebot einen Slug und kennt Campus Connect dazu Gruppenwerte, gelten
 * diese — eine Bewertung gilt dem Kurs, nicht dem einzelnen Durchgang. Die Gruppenzahl
 * enthaelt die eigene immer mit (Selbsttest der Migration v4.62.0 prueft das). Aufrufe ohne
 * Slug (livento_cc_family_rating fuer die Ticketseite) bleiben unberuehrt.
 */
function livento_cc_rating($o) {
    $slug = isset($o['slug']) ? (string) $o['slug'] : '';
    if ($slug !== '') {
        $gruppe = livento_cc_get_group_ratings();
        if (isset($gruppe[$slug])) {
            $o['rating_avg']   = $gruppe[$slug]['avg'];
            $o['rating_count'] = $gruppe[$slug]['count'];
        }
    }
    $count = (int) ($o['rating_count'] ?? 0);
    if (!isset($o['rating_avg']) || $o['rating_avg'] === null || $o['rating_avg'] === '' || $count < LIVENTO_CC_RATING_MIN) {
        return null;
    }
    return array('avg' => (float) $o['rating_avg'], 'count' => $count);
}

/**
 * v1.59.0: Sterne mit Wert und Anzahl. $hinweis = true ergaenzt auf der Detailseite den
 * Hinweis nach § 5b Abs. 3 UWG, wie Bewertungen zustande kommen.
 * v1.60.0: $bezug benennt, worauf sich der Schnitt bezieht, wenn er mehrere Kurse
 * zusammenfasst (Ticketseite: „der Kurse in diesem Ticket“).
 */
function livento_cc_rating_html($rating, $hinweis = false, $bezug = '') {
    if ($rating === null) {
        return '';
    }
    $wert   = number_format($rating['avg'], 1, ',', '');
    $anzahl = $rating['count'] . ' ' . ($rating['count'] === 1 ? 'Bewertung' : 'Bewertungen')
            . ($bezug !== '' ? ' ' . $bezug : '');
    $breite = max(0, min(100, round($rating['avg'] / 5 * 100)));
    $out  = '<div class="lvk-rating">';
    $out .= '<span class="lvk-stars" role="img" aria-label="' . esc_attr($wert . ' von 5 Sternen') . '">'
          . '<span class="lvk-stars-fill" style="width:' . (int) $breite . '%"></span></span>';
    $out .= '<span class="lvk-rating-val">' . esc_html($wert) . '</span>';
    $out .= '<span class="lvk-rating-count">' . esc_html($anzahl) . '</span>';
    if ($hinweis) {
        $out .= '<details class="lvk-rating-info"><summary>Wie entstehen die Bewertungen?</summary>'
              . '<p>Bewerten können nur Personen, die im Kurs eingeschrieben sind oder ihn abgeschlossen haben, '
              . 'je Kurs einmal. Das prüft unsere Lernplattform Campus Connect. Bewertungen werden vor der '
              . 'Veröffentlichung geprüft. Der Wert ist der Durchschnitt aller veröffentlichten Bewertungen'
              . esc_html($bezug !== '' ? ' ' . $bezug : '') . '.</p></details>';
    }
    return $out . '</div>';
}

/**
 * v1.60.0: CSS der Sterne — eine Quelle fuer Katalog (livento_cc_styles) und Ticketseite
 * (livento_cc_tariff_styles). Steht in einem einfach quotierten PHP-String: in den
 * SVG-Daten-URIs daher %27 statt '. Farben mit Rueckfall, weil die Ticketseite die
 * .lvk-Variablen nicht kennt.
 */
function livento_cc_rating_css() {
    $stern = 'M12 2l2.9 6.9 7.1.6-5.4 4.7 1.7 7-6.3-3.9-6.3 3.9 1.7-7L2 9.5l7.1-.6z';
    $svg = function ($farbe) use ($stern) {
        return 'url("data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 24 24%27%3E'
             . '%3Cpath fill=%27%23' . $farbe . '%27 d=%27' . $stern . '%27/%3E%3C/svg%3E") repeat-x left center/1.1em 1.1em';
    };
    return '.lvk-rating{display:flex;flex-wrap:wrap;align-items:center;gap:4px 8px;margin:4px 0 10px;font-size:.95rem;color:#444}'
         . '.lvk-stars{position:relative;display:inline-block;width:5.5em;height:1.1em;background:' . $svg('d6d6d6') . '}'
         . '.lvk-stars-fill{position:absolute;inset:0 auto 0 0;background:' . $svg('e3a008') . '}'
         . '.lvk-rating-val{font-weight:700;color:#222}'
         . '.lvk-rating-count{color:#666}'
         . '.lvk-rating-info{flex-basis:100%;font-size:.85rem;color:#555}'
         . '.lvk-rating-info summary{cursor:pointer;color:var(--lvk-green,#004D33)}'
         . '.lvk-rating-info p{margin:6px 0 0}'
         . '.lvk-card .lvk-rating{font-size:.85rem;margin:2px 0 6px}'
         . '.lv-tarif-detail__head .lvk-rating{margin:.75rem 0 0;font-size:1rem}';
}

/** v1.60.0: Veroeffentlichte Bewertungsschnitte je Lehrgang (View program_rating_summary, anon, nur Zahlen). */
function livento_cc_get_rating_summary() {
    $key = 'livento_cc_ratings_v' . livento_cc_ver();
    $cached = get_transient($key);
    if (is_array($cached)) {
        return $cached;
    }
    $data = livento_cc_rest_get_path('program_rating_summary',
        'select=training_program_id,avg_value,count&scheduled_course_id=is.null');
    if (is_wp_error($data)) {
        error_log('[livento] program_rating_summary nicht erreichbar: ' . $data->get_error_message());
        return array();
    }
    set_transient($key, $data, LIVENTO_CC_TTL);
    return $data;
}

/**
 * v1.60.0: Bewertung ueber alle Kurse der Varianten eines Tickets (gewichtet nach Anzahl),
 * gleiche Schwelle wie beim einzelnen Kurs. Nur sichtbar, bewusst KEIN aggregateRating im
 * Product-Schema: Die Bewertungen gelten den enthaltenen Kursen, nicht dem Ticket selbst.
 */
function livento_cc_family_rating($variants) {
    $ids = array();
    foreach ($variants as $v) {
        foreach (livento_cc_get_bundle_courses($v['bundle']['id']) as $c) {
            if (!empty($c['id'])) {
                $ids[(string) $c['id']] = true;
            }
        }
    }
    $summe  = 0.0;
    $anzahl = 0;
    foreach (livento_cc_get_rating_summary() as $r) {
        $n = (int) ($r['count'] ?? 0);
        if ($n > 0 && isset($r['avg_value']) && $r['avg_value'] !== null
            && isset($ids[(string) ($r['training_program_id'] ?? '')])) {
            $summe  += (float) $r['avg_value'] * $n;
            $anzahl += $n;
        }
    }
    return $anzahl > 0 ? livento_cc_rating(array('rating_avg' => $summe / $anzahl, 'rating_count' => $anzahl)) : null;
}

function livento_cc_jsonld_list($offerings) {
    $items = array();
    $pos = 1;
    foreach ($offerings as $o) {
        if (empty($o['slug'])) {
            continue;
        }
        $items[] = array(
            '@type'    => 'ListItem',
            'position' => $pos++,
            'url'      => livento_cc_detail_url($o['slug']),
            'name'     => $o['title'],
        );
    }
    return array(
        '@context'        => 'https://schema.org',
        '@type'           => 'ItemList',
        'name'            => 'Livento Kurskalender',
        'itemListElement' => $items,
    );
}

/**
 * v1.18.0: Gueltige FAQ-Eintraege ({q,a}) eines Angebots. Die View liefert `faq`
 * als JSON-Array; PostgREST gibt es i. d. R. bereits dekodiert zurueck (String-
 * Fallback fuer Sicherheit). Halb gefuellte/leere Eintraege werden verworfen.
 */
function livento_cc_faq_items($o) {
    $faq = isset($o['faq']) ? $o['faq'] : array();
    if (is_string($faq)) {
        $faq = json_decode($faq, true);
    }
    if (!is_array($faq)) {
        return array();
    }
    $items = array();
    foreach ($faq as $f) {
        if (!is_array($f)) {
            continue;
        }
        $q = isset($f['q']) ? trim((string) $f['q']) : '';
        $a = isset($f['a']) ? trim((string) $f['a']) : '';
        if ($q !== '' && $a !== '') {
            $items[] = array('q' => $q, 'a' => $a);
        }
    }
    return $items;
}

/** v1.18.0: schema.org/FAQPage-JSON-LD oder null (wenn keine FAQ gepflegt). */
function livento_cc_jsonld_faq($o) {
    $items = livento_cc_faq_items($o);
    if (empty($items)) {
        return null;
    }
    $main = array();
    foreach ($items as $f) {
        $main[] = array(
            '@type'          => 'Question',
            'name'           => $f['q'],
            'acceptedAnswer' => array('@type' => 'Answer', 'text' => $f['a']),
        );
    }
    return array(
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => $main,
    );
}

/* ============================================================
 * 7. Shortcode + Rendering
 * ============================================================ */

add_shortcode('livento_kurse', function ($atts) {
    $a = shortcode_atts(array(
        'limit'   => 0,                       // 0 = alle; >0 = nur die ersten N (kuratierter Block)
        'sort'    => 'next_start',            // next_start|newest|popular|rating|most_booked|price_asc|price_desc
        'filters'  => '',                     // '' = auto (Filter an, wenn kein limit) | 'yes' | 'no'
        'topics'   => '',                     // '' = alle | Komma-Liste von Themen-Slugs (z. B. leitung-management,demenz)
        'audience' => '',                     // '' = alle | Komma-Liste von Zielgruppen-Slugs (z. B. fuehrungskraefte,praxisanleitende)
    ), $atts, 'livento_kurse');

    $slug = livento_cc_current_slug();
    if ($slug) {
        $offering = isset($GLOBALS['livento_cc_current'])
            ? $GLOBALS['livento_cc_current']
            : livento_cc_get_offering($slug);
        $body = $offering
            ? livento_cc_render_detail($offering)
            : livento_cc_render_notfound();
    } else {
        $limit   = max(0, (int) $a['limit']);
        $filters = strtolower(trim((string) $a['filters']));
        // Bei gesetztem Limit standardmaessig ohne Filterleiste (kuratierter Block),
        // sonst mit. Per filters="yes"/"no" explizit erzwingbar.
        $show_filters = ($filters === 'yes') || ($filters !== 'no' && $limit === 0);
        $offerings = livento_cc_get_offerings();
        $offerings = livento_cc_filter_by_field($offerings, 'topics', $a['topics']);
        $offerings = livento_cc_filter_by_field($offerings, 'audience', $a['audience']);
        $body = livento_cc_render_list($offerings, $a['sort'], $limit, $show_filters);
    }
    // Styles dem zurueckgegebenen Markup voranstellen (nicht echoen — sonst
    // Leak-Risiko, wenn the_content-Filter ausserhalb der Seitenausgabe laeuft).
    return livento_cc_styles() . $body;
});

/**
 * Eigenstaendiges Suchfeld (z. B. fuer die Startseite). Springt beim Absenden zur
 * Katalogseite `/<base>/?q=<begriff>` — dort uebernimmt der Filter den Begriff.
 * Shortcode: [livento_kurse_suche placeholder="…" button="…" title="…"]
 */
add_shortcode('livento_kurse_suche', function ($atts) {
    $a = shortcode_atts(array(
        'placeholder' => 'Kurs oder Thema suchen…',
        'button'      => 'Kurse finden',
        'title'       => '',
    ), $atts, 'livento_kurse_suche');

    $out  = livento_cc_styles();
    $out .= '<div class="lvk lvk-herosearch-wrap">';
    if ($a['title'] !== '') {
        $out .= '<p class="lvk-herosearch-title">' . esc_html($a['title']) . '</p>';
    }
    $out .= '<form class="lvk-herosearch" role="search" method="get" action="' . esc_url(livento_cc_list_url()) . '">';
    $out .= '<input type="search" name="q" class="lvk-herosearch-input" placeholder="' . esc_attr($a['placeholder']) . '" aria-label="Kurssuche">';
    $out .= '<button type="submit" class="lvk-herosearch-btn">' . esc_html($a['button']) . '</button>';
    $out .= '</form></div>';
    return $out;
});

/** Interessen-Aussagen → Themen-Slugs: Admin-Einstellung (Tab „Berater") → Code-Default. */
function livento_cc_berater_interests() {
    $opt = get_option('livento_cc_berater_interests', null);
    if (is_array($opt)) {
        $out = array();
        foreach ($opt as $row) {
            $label  = isset($row['label']) ? (string) $row['label'] : '';
            $topics = (isset($row['topics']) && is_array($row['topics']))
                ? array_values(array_filter(array_map('strval', $row['topics'])))
                : array();
            if ($label !== '' && !empty($topics)) {
                $out[] = array('label' => $label, 'topics' => $topics);
            }
        }
        if (!empty($out)) {
            return $out;
        }
    }
    return livento_cc_berater_interests_default();
}

/** Standard-Interessen (Fallback, wenn im Admin nichts gepflegt ist). */
function livento_cc_berater_interests_default() {
    return array(
        array('label' => 'Ich möchte Menschen mit Demenz besser begleiten',                 'topics' => array('demenz')),
        array('label' => 'Ich möchte in der Betreuung & Alltagsbegleitung arbeiten',         'topics' => array('soziale-betreuung', 'pflegeassistenz')),
        array('label' => 'Ich interessiere mich für Palliativversorgung & Sterbebegleitung', 'topics' => array('palliative-care')),
        array('label' => 'Ich möchte eine Leitungs- oder Führungsrolle übernehmen',          'topics' => array('leitung-management')),
        array('label' => 'Ich möchte andere anleiten & ausbilden (Praxisanleitung)',         'topics' => array('praxisanleitung')),
        array('label' => 'Ich möchte Pflegebedürftige & Angehörige beraten',                 'topics' => array('pflegeberatung-case-management')),
        array('label' => 'Ich möchte rechtssicher arbeiten und Pflegerecht vertiefen',       'topics' => array('pflegerecht')),
        array('label' => 'Ich interessiere mich für Schmerzmanagement',                      'topics' => array('schmerz')),
        array('label' => 'Ich möchte digitaler arbeiten (Digitalisierung in der Pflege)',    'topics' => array('digitalisierung')),
        array('label' => 'Ich kümmere mich um Verwaltung & Abrechnung',                      'topics' => array('verwaltung-abrechnung')),
    );
}

/** Starttermin-Optionen (reiner Lead-Qualifier, filtert die Ergebnisse nicht). */
function livento_cc_berater_starttermine() {
    return array(
        'asap'    => 'so schnell wie möglich',
        '4wochen' => 'innerhalb der nächsten 4 Wochen',
        '3monate' => 'innerhalb der nächsten 3 Monate',
        '6monate' => 'innerhalb der nächsten 6 Monate',
        'jahr'    => 'innerhalb des nächsten Jahres',
        'spaeter' => 'später',
    );
}

/**
 * Kursberater im SGD-Stil: Ihre Interessen → Ihr Starttermin → Ihre Angaben
 * (GoHighLevel-Formular aus den Einstellungen) → Ihr Ergebnis (passende Kurse inline).
 * Shortcode: [livento_kurse_berater starttermin="yes" form="yes" result_limit="12"]
 */
add_shortcode('livento_kurse_berater', function ($atts) {
    $a = shortcode_atts(array(
        'title'        => 'Kursberatung für persönliche Weiterbildung',
        'intro'        => '',
        'starttermin'  => 'yes',
        'form'         => 'yes',
        'result_limit' => 12,
    ), $atts, 'livento_kurse_berater');

    $offerings = livento_cc_augment(livento_cc_get_offerings());
    if (empty($offerings)) {
        return '<div class="lvk"><p>Aktuell sind keine Kurse verfügbar.</p></div>';
    }

    // Nur Interessen, deren Themen tatsaechlich im Katalog vorkommen.
    $topic_counts = livento_cc_collect_facet($offerings, 'topics', true);
    $interests = array();
    foreach (livento_cc_berater_interests() as $it) {
        $present = array_values(array_filter($it['topics'], function ($t) use ($topic_counts) {
            return isset($topic_counts[$t]);
        }));
        if (!empty($present)) {
            $interests[] = array('label' => $it['label'], 'topics' => $present);
        }
    }
    if (empty($interests)) {
        return '<div class="lvk"><p><a href="' . esc_url(livento_cc_list_url()) . '">Zum Kurskatalog</a></p></div>';
    }

    $ghl       = (string) get_option('livento_cc_berater_form', '');
    $use_start = (strtolower((string) $a['starttermin']) !== 'no');
    $use_form  = (strtolower((string) $a['form']) !== 'no') && livento_cc_has_angaben('kurs', $ghl);
    $rlimit    = max(1, (int) $a['result_limit']);

    // Schritt-Reihenfolge fuer den Stepper.
    $stepdefs = array(array('Deine Interessen'));
    if ($use_start) { $stepdefs[] = array('Dein Starttermin'); }
    if ($use_form)  { $stepdefs[] = array('Deine Angaben'); }
    $stepdefs[] = array('Dein Ergebnis');
    $total = count($stepdefs);

    $out  = livento_cc_styles();
    $out .= '<div class="lvk lvk-berater" id="lvk-berater" data-total="' . (int) $total . '" data-limit="' . (int) $rlimit . '" data-base="' . esc_attr(livento_cc_list_url()) . '">';
    if ($a['title'] !== '') {
        $out .= '<h2 class="lvk-bx-title">' . esc_html($a['title']) . '</h2>';
    }
    if ($a['intro'] !== '') {
        $out .= '<p class="lvk-bx-intro">' . esc_html($a['intro']) . '</p>';
    }

    // Stepper
    $out .= '<ol class="lvk-bx-stepper">';
    foreach ($stepdefs as $i => $s) {
        $out .= '<li class="lvk-bx-stp' . ($i === 0 ? ' is-active' : '') . '"><span class="lvk-bx-dot"></span><span class="lvk-bx-lbl">' . esc_html($s[0]) . '</span></li>';
    }
    $out .= '</ol>';

    // Schritt 1 — Interessen (Mehrfachauswahl)
    $out .= '<div class="lvk-bx-step" data-key="interests">';
    $out .= '<h3 class="lvk-bx-q">Was sind deine Interessen?</h3>';
    $out .= '<p class="lvk-bx-hint">Wähle aus, was am ehesten auf dich zutrifft – Mehrfachauswahl möglich.</p>';
    $out .= '<div class="lvk-bx-list">';
    foreach ($interests as $it) {
        $out .= '<button type="button" class="lvk-bx-row" data-topics="' . esc_attr(implode(',', $it['topics'])) . '">' . esc_html($it['label']) . '</button>';
    }
    $out .= '</div></div>';

    // Schritt 2 — Starttermin (Einfachauswahl)
    if ($use_start) {
        $out .= '<div class="lvk-bx-step" data-key="starttermin" hidden>';
        $out .= '<h3 class="lvk-bx-q">Wann möchtest du mit deiner Weiterbildung beginnen?</h3>';
        $out .= '<p class="lvk-bx-hint">Wähle den Zeitraum, der am ehesten zutrifft.</p>';
        $out .= '<div class="lvk-bx-list">';
        foreach (livento_cc_berater_starttermine() as $val => $label) {
            $out .= '<button type="button" class="lvk-bx-row lvk-bx-single" data-field="starttermin" data-value="' . esc_attr($val) . '">' . esc_html($label) . '</button>';
        }
        $out .= '</div></div>';
    }

    // Schritt 3 — Angaben (natives Lead-Formular → GHL-Webhook, sonst Embed)
    if ($use_form) {
        $out .= '<div class="lvk-bx-step" data-key="angaben" hidden>';
        $out .= '<h3 class="lvk-bx-q">Deine Angaben</h3>';
        $out .= '<p class="lvk-bx-hint">Damit wir dir deine persönliche Empfehlung zusenden können.</p>';
        $out .= livento_cc_angaben_inner('kurs', $ghl);
        $out .= '</div>';
    }

    // Schritt 4 — Ergebnis (alle Karten gerendert, JS zeigt die passenden)
    $out .= '<div class="lvk-bx-step" data-key="ergebnis" hidden>';
    $out .= '<h3 class="lvk-bx-q">Dein Ergebnis: Diese Kurse könnten dir gefallen</h3>';
    $out .= '<p class="lvk-bx-count" aria-live="polite"></p>';
    $out .= '<div class="lvk-grid lvk-bx-results">';
    foreach ($offerings as $o) {
        $out .= livento_cc_render_card($o);
    }
    $out .= '</div>';
    $out .= '<p class="lvk-bx-allcta"><a href="' . esc_url(livento_cc_list_url()) . '">Alle Kurse im Katalog ansehen →</a></p>';
    $out .= '</div>';

    // Navigation
    $out .= '<div class="lvk-bx-nav">';
    $out .= '<button type="button" class="lvk-bx-back" hidden>← Zurück</button>';
    $out .= '<span class="lvk-bx-spacer"></span>';
    $out .= '<button type="button" class="lvk-bx-next">Weiter</button>';
    $out .= '</div>';
    $out .= '<noscript><p style="margin-top:12px"><a href="' . esc_url(livento_cc_list_url()) . '">Zum vollständigen Kurskatalog →</a></p></noscript>';
    $out .= '</div>';
    $out .= livento_cc_berater_js();
    $out .= livento_cc_lead_js();
    return $out;
});

/** Slug → Inline-SVG-Icon fuer die Themen-Kacheln (Fallback _default). */
function livento_cc_topic_icons() {
    return array(
        'soziale-betreuung'              => '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.8 7.6a5 5 0 00-8.8-2.6A5 5 0 003.2 7.6c0 4 4.3 6.8 8.8 11 4.5-4.2 8.8-7 8.8-11z"/></svg>',
        'verwaltung-abrechnung'          => '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3h7l4 4v13a1 1 0 01-1 1H7a1 1 0 01-1-1V4a1 1 0 011-1z"/><path d="M14 3v4h4"/><path d="M9 13h6M9 17h6"/></svg>',
        'pflegeassistenz'                => '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/></svg>',
        'praxisanleitung'                => '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10L12 5 2 10l10 5 10-5z"/><path d="M6 12v5c0 1 3 2 6 2s6-1 6-2v-5"/></svg>',
        'leitung-management'             => '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 20v-6M10 20V8M15 20v-9M20 20H3"/></svg>',
        'digitalisierung'                => '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="7" y="7" width="10" height="10" rx="1"/><path d="M10 7V4M14 7V4M10 20v-3M14 20v-3M7 10H4M7 14H4M20 10h-3M20 14h-3"/></svg>',
        'palliative-care'                => '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-4.5-7-10a4 4 0 017-2.6A4 4 0 0119 11c0 5.5-7 10-7 10z"/><path d="M12 7v4M10 9h4"/></svg>',
        'pflegeberatung-case-management' => '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 01-2 2H8l-4 4V5a2 2 0 012-2h13a2 2 0 012 2z"/></svg>',
        'pflegerecht'                    => '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v18M5 7h14M7 7l-3 6a3 3 0 006 0zM17 7l-3 6a3 3 0 006 0z"/></svg>',
        'schmerz'                        => '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h3l2-5 4 10 2-5h7"/></svg>',
        'demenz'                         => '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21a5 5 0 01-2-9.5A4 4 0 0114 6a4 4 0 014 5 3.5 3.5 0 01-1 6.8"/><path d="M9 21v-4M14 17v4"/></svg>',
        '_default'                       => '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>',
    );
}

/**
 * Themen-Kacheln aus der Themen-Aggregation (gleiche Quelle wie Filter „Thema").
 * Shortcode: [livento_themen limit="6" sort="count" counts="yes" all="yes" min="1"]
 */
add_shortcode('livento_themen', function ($atts) {
    $a = shortcode_atts(array(
        'limit'  => 0,
        'sort'   => 'count',  // count | alpha
        'counts' => 'yes',
        'all'    => 'yes',
        'min'    => 1,
    ), $atts, 'livento_themen');

    $offerings = livento_cc_get_offerings();
    if (empty($offerings)) {
        return '';
    }
    $counts = livento_cc_collect_facet($offerings, 'topics', true); // slug => count
    $min = max(1, (int) $a['min']);
    $items = array();
    foreach ($counts as $slug => $cnt) {
        if ((int) $cnt < $min) {
            continue;
        }
        $items[] = array('slug' => (string) $slug, 'label' => livento_cc_humanize($slug), 'count' => (int) $cnt);
    }
    if (empty($items)) {
        return '';
    }
    if ($a['sort'] === 'alpha') {
        usort($items, function ($x, $y) { return strcasecmp($x['label'], $y['label']); });
    } else {
        usort($items, function ($x, $y) { return $y['count'] - $x['count']; }); // Kursanzahl absteigend
    }
    $limit = max(0, (int) $a['limit']);
    if ($limit > 0) {
        $items = array_slice($items, 0, $limit);
    }

    $show_counts = (strtolower((string) $a['counts']) !== 'no');
    $show_all    = (strtolower((string) $a['all']) !== 'no');
    $icons = livento_cc_topic_icons();
    $base  = livento_cc_list_url();

    $out  = livento_cc_themen_styles();
    $out .= '<div class="lv-topics__grid">';
    foreach ($items as $it) {
        $icon = isset($icons[$it['slug']]) ? $icons[$it['slug']] : $icons['_default'];
        $url  = $base . '?topics=' . rawurlencode($it['slug']);
        $out .= '<a class="lv-topic" href="' . esc_url($url) . '">';
        $out .= '<span class="lv-topic__ic" aria-hidden="true">' . $icon . '</span>';
        if ($show_counts) {
            $out .= '<span class="lv-topic__badge">' . (int) $it['count'] . ' Kurse</span>';
        }
        $out .= '<h3 class="lv-topic__t">' . esc_html($it['label']) . '</h3>';
        $out .= '<span class="lv-topic__cta">Kurse ansehen →</span>';
        $out .= '</a>';
    }
    if ($show_all) {
        $out .= '<a class="lv-topic lv-topic--all" href="' . esc_url($base) . '">';
        $out .= '<span class="lv-topic__ic" aria-hidden="true">' . $icons['_default'] . '</span>';
        $out .= '<h3 class="lv-topic__t">Alle Themen</h3>';
        $out .= '<span class="lv-topic__cta">Zum gesamten Kurskatalog →</span>';
        $out .= '</a>';
    }
    $out .= '</div>';
    return $out;
});

/** CSS fuer die Themen-Kacheln (lv-topic*). Einmal pro Request. */
function livento_cc_themen_styles() {
    static $done = false;
    if ($done) {
        return '';
    }
    $done = true;
    $css = '
.lv-topics,.lv-topics__grid{--lv-green:#004D33;--lv-accent:#AAC42B;--lv-body:#334155}
.lv-topics{font-family:"Inter",sans-serif;background:#fdf4ef;padding:72px 20px}
.lv-topics__inner{max-width:1180px;margin:0 auto}
.lv-topics__head{text-align:center;max-width:760px;margin:0 auto 44px}
.lv-topics__eyebrow{display:inline-block;margin-bottom:12px;font-weight:700;font-size:16px;color:var(--lv-green)}
.lv-topics__title{margin:0;font-family:"Qurova","Figtree",sans-serif;font-weight:600;font-size:clamp(26px,3.4vw,40px);line-height:1.12;color:var(--lv-green)}
.lv-topics__grid{display:grid;grid-template-columns:repeat(3,1fr);gap:24px}
.lv-topic{position:relative;display:flex;flex-direction:column;background:#fff;border:1px solid #f0e7e0;border-radius:18px;padding:28px 26px;text-decoration:none;min-height:150px;transition:transform .2s,box-shadow .2s}
.lv-topic:hover{transform:translateY(-4px);box-shadow:0 14px 30px rgba(0,77,51,.10)}
.lv-topic__ic{display:inline-flex;align-items:center;justify-content:center;width:52px;height:52px;border-radius:14px;margin-bottom:18px;background:rgba(170,196,43,.18);color:var(--lv-green)}
.lv-topic__badge{position:absolute;top:22px;right:22px;background:rgba(0,77,51,.06);color:var(--lv-green);font-weight:700;font-size:12px;padding:4px 10px;border-radius:999px}
.lv-topic__t{margin:0 0 14px;font-family:"Qurova","Figtree",sans-serif;font-weight:600;font-size:19px;line-height:1.25;color:var(--lv-green)}
.lv-topic__cta{margin-top:auto;font-weight:700;font-size:15px;color:var(--lv-green)}
.lv-topic:hover .lv-topic__cta{text-decoration:underline}
.lv-topic--all{background:var(--lv-green);border-color:var(--lv-green)}
.lv-topic--all .lv-topic__ic{background:rgba(255,255,255,.14);color:#fff}
.lv-topic--all .lv-topic__t,.lv-topic--all .lv-topic__cta{color:#fff}
@media (max-width:900px){.lv-topics__grid{grid-template-columns:1fr 1fr}}
@media (max-width:860px){.lv-topics{padding:48px 16px}.lv-topics__head{margin-bottom:30px}}
@media (max-width:560px){.lv-topics__grid{grid-template-columns:1fr;gap:16px}}
';
    return '<style id="lv-topics-styles">' . $css . '</style>';
}

/* ============================================================
 * 4c. Kurslisten — benannte, kriterienbasierte Kurs-Widgets fuer Landingpages
 *
 * Im Admin-Tab „Kurslisten" pflegt der Nutzer benannte Listen (Kriterien +
 * Ueberschrift/CTA) und bindet sie per [livento_kursliste id="…"] auf einer
 * Landingpage ein (z. B. je Google-/Meta-Ads-Kampagne eine Kategorie wie
 * „Pflichtfortbildungen" oder „Betreuungskraefte"). Dynamisch: die Liste fuellt
 * sich automatisch aus dem Katalog. „Pflichtfortbildungen" laesst sich nicht ueber
 * ein einzelnes Facet abbilden → zusaetzlich Titel-Stichwort (q) als Kriterium.
 * ============================================================ */

/** Gespeicherte Kurslisten (Admin-Option). */
function livento_cc_kurslisten() {
    $opt = get_option('livento_cc_kurslisten', null);
    return is_array($opt) ? $opt : array();
}

/** Eine Kursliste per id. Gibt array|null zurueck. */
function livento_cc_kursliste_get($id) {
    $id = sanitize_title($id);
    if ($id === '') {
        return null;
    }
    foreach (livento_cc_kurslisten() as $l) {
        if (isset($l['id']) && $l['id'] === $id) {
            return $l;
        }
    }
    return null;
}

/** Filtert Angebote nach den Kriterien einer Kursliste (alle gesetzten Kriterien = UND). */
function livento_cc_kursliste_filter($offerings, $cfg) {
    // Mehrfachwert-Felder (ODER innerhalb des Feldes, vorhandener Helper).
    $offerings = livento_cc_filter_by_field($offerings, 'audience',    $cfg['audience']    ?? '');
    $offerings = livento_cc_filter_by_field($offerings, 'topics',      $cfg['topics']      ?? '');
    $offerings = livento_cc_filter_by_field($offerings, 'recognition', $cfg['recognition'] ?? '');

    // v1.56.0: Nur Kurse, die mit Bildungsgutschein foerderfaehig sind.
    if (!empty($cfg['bildungsgutschein']) && (string) $cfg['bildungsgutschein'] !== 'no') {
        $offerings = array_values(array_filter($offerings, 'livento_cc_is_bildungsgutschein'));
    }

    // Format = Skalarfeld (eigener Vergleich).
    $fmt = trim((string) ($cfg['format'] ?? ''));
    if ($fmt !== '') {
        $want = array_values(array_filter(array_map(function ($v) { return strtolower(trim($v)); }, explode(',', $fmt))));
        if (!empty($want)) {
            $offerings = array_values(array_filter($offerings, function ($o) use ($want) {
                return in_array(strtolower((string) ($o['format'] ?? '')), $want, true);
            }));
        }
    }

    // v1.58.0: Angebotsart + Pflichtfortbildung (Campus Connect v4.47.0)
    $art = sanitize_key((string) ($cfg['art'] ?? ''));
    if ($art !== '') {
        $offerings = array_values(array_filter($offerings, function ($o) use ($art) {
            return livento_cc_effective_kind($o) === $art;
        }));
    }
    if (!empty($cfg['pflicht']) && (string) $cfg['pflicht'] !== 'no') {
        $offerings = array_values(array_filter($offerings, function ($o) {
            return !empty($o['is_mandatory_training']);
        }));
    }

    // Titel-Stichwort (Teilstring, case-insensitive) — fuer Kategorien ohne eigenes
    // Facet. Seit v1.58.0 gibt es fuer Pflichtfortbildungen das Kriterium „pflicht“.
    $q = trim((string) ($cfg['q'] ?? ''));
    if ($q !== '') {
        $needle = function_exists('mb_strtolower') ? mb_strtolower($q, 'UTF-8') : strtolower($q);
        $offerings = array_values(array_filter($offerings, function ($o) use ($needle) {
            $t   = (string) ($o['title'] ?? '');
            $hay = function_exists('mb_strtolower') ? mb_strtolower($t, 'UTF-8') : strtolower($t);
            return strpos($hay, $needle) !== false;
        }));
    }
    return $offerings;
}

/** Deep-Link in den vollen Katalog, vorgefiltert auf die Listen-Kriterien (fuer den CTA-Button). */
function livento_cc_kursliste_deeplink($cfg) {
    $params = array();
    foreach (array('audience', 'topics', 'recognition', 'format') as $k) {
        $v = trim((string) ($cfg[$k] ?? ''));
        if ($v !== '') {
            $params[$k] = implode(',', array_values(array_filter(array_map('trim', explode(',', $v)))));
        }
    }
    $q = trim((string) ($cfg['q'] ?? ''));
    if ($q !== '') {
        $params['q'] = $q;
    }
    if (!empty($cfg['bildungsgutschein']) && (string) $cfg['bildungsgutschein'] !== 'no') {
        $params['azav'] = '1';
    }
    $art = sanitize_key((string) ($cfg['art'] ?? ''));
    if ($art !== '') {
        $params['art'] = $art;
    }
    if (!empty($cfg['pflicht']) && (string) $cfg['pflicht'] !== 'no') {
        $params['pflicht'] = '1';
    }
    $base = livento_cc_list_url();
    return empty($params) ? $base : $base . '?' . http_build_query($params);
}

/** CSS fuer die Kurslisten-Sektion (Kopf + CTA + optionale Spaltenzahl). Einmal pro Request. */
function livento_cc_kursliste_styles() {
    static $done = false;
    if ($done) {
        return '';
    }
    $done = true;
    $css = '
.lvk-kursliste{margin:8px 0;background:none;border:0;padding:0}
.lvk-kl-head{margin:0 0 18px}
.lvk-kl-title{margin:0;font-family:"Qurova","Figtree",sans-serif;font-weight:600;font-size:clamp(22px,2.6vw,30px);line-height:1.15;color:#004D33}
.lvk-kl-sub{margin:6px 0 0;color:#475569;font-size:15px;line-height:1.5}
.lvk-kl-grid[style*="--lvk-cols"]{grid-template-columns:repeat(var(--lvk-cols),minmax(0,1fr))}
@media(max-width:900px){.lvk-kl-grid[style*="--lvk-cols"]{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:560px){.lvk-kl-grid[style*="--lvk-cols"]{grid-template-columns:1fr}}
.lvk-kl-cta{margin:24px 0 4px;text-align:center}
.lvk-kl-btn{display:inline-block;background:#004D33;color:#fff!important;font-weight:700;font-size:15px;text-decoration:none;padding:13px 28px;border-radius:999px;transition:background .15s,transform .15s}
.lvk-kl-btn:hover{background:#013a26;transform:translateY(-1px)}
';
    return '<style id="lvk-kursliste-styles">' . $css . '</style>';
}

/** Rendert eine Kursliste als eigenstaendige Sektion (Ueberschrift + Karten-Grid + optional CTA). */
function livento_cc_render_kursliste($cfg) {
    $offerings = livento_cc_get_offerings();
    $offerings = livento_cc_kursliste_filter($offerings, $cfg);
    $offerings = livento_cc_augment($offerings);
    $offerings = livento_cc_sort_offerings($offerings, $cfg['sort'] ?? 'next_start');

    // v1.58.0: mehrere Termine desselben Kurses = eine Karte; Limit zaehlt Kurse
    $groups = livento_cc_group_offerings($offerings);
    $limit = max(0, (int) ($cfg['limit'] ?? 0));
    if ($limit > 0) {
        $groups = array_slice($groups, 0, $limit);
    }

    if (empty($groups)) {
        // Im Frontend keine leere Sektion ausgeben; im Backend Hinweis fuer den Redakteur.
        return current_user_can('manage_options')
            ? '<div class="lvk"><p>Kursliste „' . esc_html((string) ($cfg['name'] ?? ($cfg['id'] ?? ''))) . '": aktuell keine passenden Kurse (Kriterien zu eng?).</p></div>'
            : '';
    }

    $heading  = trim((string) ($cfg['heading'] ?? ''));
    $sub      = trim((string) ($cfg['subheading'] ?? ''));
    $cols     = (int) ($cfg['columns'] ?? 0);
    $show_cta = (strtolower((string) ($cfg['cta'] ?? 'no')) === 'yes');
    $cta_lbl  = trim((string) ($cfg['cta_label'] ?? ''));
    if ($cta_lbl === '') {
        $cta_lbl = 'Alle Kurse ansehen';
    }
    $grid_style = ($cols >= 1 && $cols <= 4) ? ' style="--lvk-cols:' . $cols . '"' : '';

    // Bewusst <div> statt <section>/<header>: manche Themes geben generischen
    // semantischen Tags einen vollbreiten markenfarbenen Hintergrund (gruener Balken).
    $out  = livento_cc_styles() . livento_cc_kursliste_styles();
    $out .= '<div class="lvk lvk-kursliste">';
    if ($heading !== '' || $sub !== '') {
        $out .= '<div class="lvk-kl-head">';
        if ($heading !== '') {
            $out .= '<h2 class="lvk-kl-title">' . esc_html($heading) . '</h2>';
        }
        if ($sub !== '') {
            $out .= '<p class="lvk-kl-sub">' . esc_html($sub) . '</p>';
        }
        $out .= '</div>';
    }
    $out .= '<div class="lvk-grid lvk-kl-grid"' . $grid_style . '>';
    foreach ($groups as $g) {
        $out .= livento_cc_render_card($g['lead'], $g['members']);
    }
    $out .= '</div>';
    if ($show_cta) {
        $out .= '<div class="lvk-kl-cta"><a class="lvk-kl-btn" href="' . esc_url(livento_cc_kursliste_deeplink($cfg)) . '">' . esc_html($cta_lbl) . ' →</a></div>';
    }
    $out .= '</div>';
    return $out;
}

/**
 * Kursliste als eigenstaendiges Widget fuer Landingpages.
 * Shortcode: [livento_kursliste id="pflichtfortbildungen"]
 * Ad-hoc auch ohne gespeicherte Liste:
 *   [livento_kursliste audience="betreuungskraefte_43b_53b" heading="Für Betreuungskräfte" limit="6"]
 */
add_shortcode('livento_kursliste', function ($atts) {
    $a = shortcode_atts(array(
        'id'          => '',
        'audience'    => '',
        'topics'      => '',
        'format'      => '',
        'recognition' => '',
        'q'           => '',
        'bildungsgutschein' => '',
        'art'         => '',
        'pflicht'     => '',
        'limit'       => '',
        'sort'        => '',
        'heading'     => '',
        'subheading'  => '',
        'cta'         => '',
        'cta_label'   => '',
        'columns'     => '',
    ), $atts, 'livento_kursliste');

    // Basis: gespeicherte Liste (per id) ODER Ad-hoc-Vorlage (nur Attribute).
    $id = sanitize_title((string) $a['id']);
    if ($id !== '') {
        $cfg = livento_cc_kursliste_get($id);
        if (!$cfg) {
            return current_user_can('manage_options')
                ? '<div class="lvk"><p>⚠️ Kursliste „' . esc_html($id) . '" nicht gefunden – im Backend unter „Livento Katalog → Kurslisten" anlegen.</p></div>'
                : '';
        }
    } else {
        $cfg = array('limit' => 6, 'sort' => 'next_start', 'cta' => 'no');
    }

    // Shortcode-Attribute ueberschreiben gespeicherte Werte (nur wenn explizit gesetzt).
    foreach (array('audience', 'topics', 'format', 'recognition', 'q', 'heading', 'subheading', 'cta_label') as $k) {
        if ($a[$k] !== '') {
            $cfg[$k] = $a[$k];
        }
    }
    if ($a['bildungsgutschein'] !== '') {
        $cfg['bildungsgutschein'] = in_array(strtolower(trim($a['bildungsgutschein'])), array('1', 'yes', 'ja', 'true'), true) ? 'yes' : 'no';
    }
    if ($a['art'] !== '')     { $cfg['art']     = sanitize_key($a['art']); }
    if ($a['pflicht'] !== '') {
        $cfg['pflicht'] = in_array(strtolower(trim($a['pflicht'])), array('1', 'yes', 'ja', 'true'), true) ? 'yes' : 'no';
    }
    if ($a['limit'] !== '')   { $cfg['limit']   = (int) $a['limit']; }
    if ($a['sort'] !== '')    { $cfg['sort']    = sanitize_key($a['sort']); }
    if ($a['cta'] !== '')     { $cfg['cta']     = strtolower($a['cta']); }
    if ($a['columns'] !== '') { $cfg['columns'] = (int) $a['columns']; }

    return livento_cc_render_kursliste($cfg);
});

function livento_cc_render_notfound() {
    return '<div class="lvk"><h1>Kurs nicht gefunden</h1>'
        . '<p><a href="' . esc_url(livento_cc_list_url()) . '">Zur Kursübersicht</a></p></div>';
}

/** Serverseitiger Vorfilter für [livento_kurse] über ein Mehrfachwert-Feld (z. B. topics, audience).
 *  $csv = Komma-Liste von Slugs; ein Angebot bleibt, wenn mind. ein Slug passt (ODER-Verknüpfung). */
function livento_cc_filter_by_field($offerings, $field, $csv) {
    $csv = trim((string) $csv);
    if ($csv === '') {
        return $offerings;
    }
    $want = array_values(array_filter(array_map(function ($t) { return sanitize_title(trim($t)); }, explode(',', $csv))));
    if (empty($want)) {
        return $offerings;
    }
    return array_values(array_filter($offerings, function ($o) use ($field, $want) {
        $have = (isset($o[$field]) && is_array($o[$field])) ? array_map('sanitize_title', $o[$field]) : array();
        return (bool) array_intersect($want, $have);
    }));
}

function livento_cc_render_list($offerings, $sort = 'next_start', $limit = 0, $show_filters = true) {
    if (empty($offerings)) {
        return '<div class="lvk"><p>Aktuell sind keine öffentlichen Angebote verfügbar.</p></div>';
    }
    $offerings = livento_cc_augment($offerings);           // _ue/_kind/_duration/_zeitmodell/_startmonth/_availability
    $offerings = livento_cc_sort_offerings($offerings, $sort);

    // JSON-LD weiter je Termin: jeder hat seine eigene Detailseite.
    $jsonld = '<script type="application/ld+json">' . wp_json_encode(livento_cc_jsonld_list($offerings)) . '</script>';

    // v1.58.0: mehrere Termine desselben Kurses = eine Karte
    $groups = livento_cc_group_offerings($offerings);

    // Kuratierter Block ohne Filter-UI (z. B. [livento_kurse limit="6"]): nur Karten, kein JS.
    if (!$show_filters) {
        if ($limit > 0) {
            $groups = array_slice($groups, 0, $limit);
        }
        $out = '<div class="lvk lvk-list">' . $jsonld . '<div class="lvk-grid">';
        foreach ($groups as $g) {
            $out .= livento_cc_render_card($g['lead'], $g['members']);
        }
        return $out . '</div></div>';
    }
    if ($limit > 0) {
        $groups = array_slice($groups, 0, $limit);
    }

    // v1.58.0: Abschnitte je Angebotsart; ohne Art zuletzt („Weitere Angebote“).
    $labels   = livento_cc_kind_labels();
    $hints    = livento_cc_kind_hints();
    $sections = array();
    foreach (array_keys($labels) as $k) {
        $sections[$k] = array();
    }
    $sections[''] = array();
    foreach ($groups as $g) {
        $k = (string) ($g['lead']['_kind'] ?? '');
        $sections[isset($sections[$k]) ? $k : ''][] = $g;
    }

    // Umschalter (Zahlen = Kurse; JS aktualisiert sie bei Filteraenderungen)
    $sw  = '<div class="lvk-art" role="group" aria-label="Angebotsart">';
    $sw .= '<button type="button" class="lvk-art-btn is-active" data-art="" aria-pressed="true">Alle <span class="lvk-art-count">' . count($groups) . '</span></button>';
    foreach (livento_cc_kind_short() as $k => $lbl) {
        $sw .= '<button type="button" class="lvk-art-btn" data-art="' . esc_attr($k) . '" aria-pressed="false" title="' . esc_attr($labels[$k]) . '">' . esc_html($lbl)
            . ' <span class="lvk-art-count">' . count($sections[$k]) . '</span></button>';
    }
    $sw .= '</div>';

    // Voller Katalog mit Filter-Sidebar + Toolbar + JS.
    $out  = '<div class="lvk lvk-list" id="lvk-catalog">' . $jsonld;
    $out .= '<div class="lvk-layout">';
    $out .= '<aside class="lvk-sidebar">' . livento_cc_render_sidebar($offerings) . '</aside>';
    $out .= '<div class="lvk-main">';
    $out .= $sw;
    $out .= livento_cc_render_toolbar($sort);
    // Bewusst <div> statt <section>: manche Themes faerben generische semantische Tags ein.
    $out .= '<div class="lvk-kat" id="lvk-grid">';
    foreach ($sections as $k => $list) {
        if (empty($list)) {
            continue;
        }
        $out .= '<div class="lvk-kat-section" data-section="' . esc_attr($k) . '">';
        $out .= '<div class="lvk-kat-head"><h2 class="lvk-kat-title">' . esc_html($k !== '' ? $labels[$k] : 'Weitere Angebote')
            . ' <span class="lvk-kat-count">' . count($list) . '</span></h2>';
        if ($k !== '') {
            $out .= '<p class="lvk-kat-hint">' . esc_html($hints[$k]) . '</p>';
        }
        $out .= '</div><div class="lvk-grid">';
        foreach ($list as $g) {
            $out .= livento_cc_render_card($g['lead'], $g['members']);
        }
        $out .= '</div></div>';
    }
    $out .= '</div>'; // .lvk-kat
    $out .= '</div>'; // .lvk-main
    $out .= '</div>'; // .lvk-layout
    $out .= livento_cc_filter_js();
    $out .= '</div>';
    return $out;
}

/** Server-seitige Sortierung (spiegelt die JS-Sortierung im Filter). */
function livento_cc_sort_offerings($offerings, $sort) {
    switch ($sort) {
        case 'newest':
            usort($offerings, function ($a, $b) { return strcmp((string) ($b['published_at'] ?? ''), (string) ($a['published_at'] ?? '')); });
            break;
        case 'popular':
            usort($offerings, function ($a, $b) {
                $fa = !empty($a['is_featured']) ? (int) ($a['featured_order'] ?? 0) : 99999;
                $fb = !empty($b['is_featured']) ? (int) ($b['featured_order'] ?? 0) : 99999;
                if ($fa !== $fb) { return $fa - $fb; }
                return (int) ($b['booking_count'] ?? 0) - (int) ($a['booking_count'] ?? 0);
            });
            break;
        case 'rating':
            // v1.59.0: unter der Mindestzahl gilt ein Kurs als unbewertet (wie die Anzeige).
            usort($offerings, function ($a, $b) {
                $ra = livento_cc_rating($a);
                $rb = livento_cc_rating($b);
                return ($rb !== null ? $rb['avg'] : -1) <=> ($ra !== null ? $ra['avg'] : -1);
            });
            break;
        case 'most_booked':
            usort($offerings, function ($a, $b) { return (int) ($b['booking_count'] ?? 0) - (int) ($a['booking_count'] ?? 0); });
            break;
        case 'price_asc':
            usort($offerings, function ($a, $b) {
                $pa = ($a['public_price'] === null || $a['public_price'] === '') ? INF : (float) $a['public_price'];
                $pb = ($b['public_price'] === null || $b['public_price'] === '') ? INF : (float) $b['public_price'];
                return $pa <=> $pb;
            });
            break;
        case 'price_desc':
            usort($offerings, function ($a, $b) {
                $pa = ($a['public_price'] === null || $a['public_price'] === '') ? -INF : (float) $a['public_price'];
                $pb = ($b['public_price'] === null || $b['public_price'] === '') ? -INF : (float) $b['public_price'];
                return $pb <=> $pa;
            });
            break;
        case 'next_start':
        default:
            // v1.52.0: Anfragekurse ('zzzz') stehen hinter allem ohne Datum ('9999').
            $key = function ($o) { return (string) ($o['start_datetime'] ?? (livento_cc_is_on_request($o) ? 'zzzz' : '9999')); };
            usort($offerings, function ($a, $b) use ($key) { return strcmp($key($a), $key($b)); });
            break;
    }
    return $offerings;
}

/** Sammelt distinct Facet-Werte (value => count) einer Dimension ueber alle Angebote. */
function livento_cc_collect_facet($offerings, $field, $is_array) {
    $counts = array();
    foreach ($offerings as $o) {
        if ($is_array) {
            $vals = (isset($o[$field]) && is_array($o[$field])) ? $o[$field] : array();
        } else {
            $v = isset($o[$field]) ? $o[$field] : null;
            $vals = ($v === null || $v === '') ? array() : array($v);
        }
        foreach ($vals as $v) {
            if ($v === null || $v === '') {
                continue;
            }
            $counts[$v] = isset($counts[$v]) ? $counts[$v] + 1 : 1;
        }
    }
    return $counts;
}

/** Zentrale Definition der Filter-Dimensionen — von Filterleiste UND Admin-Dashboard genutzt.
 *  Neue Dimension hier ergaenzen → erscheint automatisch im Frontend-Filter und in der
 *  Admin-Slug-Uebersicht. (`dim` = data-Attribut/URL-Parameter, `field` = Quelle im Angebot,
 *  `arr` = Mehrfachwert-Feld, `lab` = Label-Map, 'month' oder null fuer humanisierte Slugs.) */
function livento_cc_filter_groups() {
    // v1.58.0: Reihenfolge = Wichtigkeit; die ersten drei Gruppen sind aufgeklappt.
    // 'hidden' = nicht mehr in der Seitenleiste, per URL-Parameter aber weiter wirksam
    // (alte Kampagnen-Links). Typ/Angebotsart uebernimmt der Umschalter ueber der Liste.
    return array(
        array('dim' => 'format',      'field' => 'format',        'arr' => false, 'title' => 'Format',         'lab' => livento_cc_format_labels()),
        array('dim' => 'audience',    'field' => 'audience',      'arr' => true,  'title' => 'Zielgruppe',     'lab' => livento_cc_audience_labels()),
        array('dim' => 'topics',      'field' => 'topics',        'arr' => true,  'title' => 'Thema',          'lab' => null),
        array('dim' => 'duration',    'field' => '_duration',     'arr' => false, 'title' => 'Umfang',         'lab' => livento_cc_duration_labels()),
        array('dim' => 'zeitmodell',  'field' => '_zeitmodell',   'arr' => true,  'title' => 'Zeitmodell',     'lab' => livento_cc_time_model_labels()),
        array('dim' => 'startmonth',  'field' => '_startmonth',   'arr' => false, 'title' => 'Start-Monat',    'lab' => 'month'),
        array('dim' => 'funding',     'field' => 'funding',       'arr' => true,  'title' => 'Förderung',      'lab' => livento_cc_funding_labels()),
        array('dim' => 'recognition', 'field' => 'recognition',   'arr' => true,  'title' => 'Anerkennung',    'lab' => livento_cc_recognition_labels()),
        array('dim' => 'city',        'field' => 'site_city',     'arr' => false, 'title' => 'Ort',            'lab' => null),
        array('dim' => 'type',        'field' => 'offering_type', 'arr' => false, 'title' => 'Typ',            'lab' => livento_cc_type_labels(),         'hidden' => true),
        array('dim' => 'level',       'field' => 'level',         'arr' => false, 'title' => 'CarePath-Level', 'lab' => livento_cc_level_labels(),        'hidden' => true),
        array('dim' => 'methodology', 'field' => 'methodology',   'arr' => true,  'title' => 'Methodik',       'lab' => livento_cc_methodology_labels(),  'hidden' => true),
        array('dim' => 'availability','field' => '_availability', 'arr' => false, 'title' => 'Verfügbarkeit',  'lab' => livento_cc_availability_labels(), 'hidden' => true),
    );
}

/** Label fuer einen Facet-Wert (Monat / Label-Map / humanisierter Slug). */
function livento_cc_facet_label($lab, $val) {
    if ($lab === 'month') {
        $ts = strtotime($val . '-01');
        return $ts ? wp_date('F Y', $ts) : $val;
    }
    if (is_array($lab)) {
        return isset($lab[$val]) ? $lab[$val] : $val;
    }
    return livento_cc_humanize($val);
}

/** Toolbar ueber der Kursliste: Suche + Sortierung + Preis + Treffer-Zaehler. */
function livento_cc_render_toolbar($sort = 'next_start') {
    $sort_opts = array(
        'next_start'  => 'Sortierung: Nächster Start',
        'newest'      => 'Neueste zuerst',
        'popular'     => 'Beliebt',
        'rating'      => 'Beste Bewertung',
        'most_booked' => 'Meiste Buchungen',
        'price_asc'   => 'Preis aufsteigend',
        'price_desc'  => 'Preis absteigend',
    );
    $sort_html = '';
    foreach ($sort_opts as $val => $label) {
        $sort_html .= '<option value="' . esc_attr($val) . '"' . ($val === $sort ? ' selected' : '') . '>' . esc_html($label) . '</option>';
    }
    $h  = '<div class="lvk-main-bar">';
    $h .= '<input type="search" class="lvk-search" placeholder="Kurse durchsuchen…" aria-label="Kurse durchsuchen">';
    $h .= '<select class="lvk-sort" aria-label="Sortierung">' . $sort_html . '</select>';
    $h .= '<select class="lvk-pricemax" aria-label="Maximalpreis">'
        . '<option value="">Preis: alle</option>'
        . '<option value="0">Nur kostenfrei</option>'
        . '<option value="50">bis 50 €</option>'
        . '<option value="100">bis 100 €</option>'
        . '<option value="250">bis 250 €</option>'
        . '<option value="500">bis 500 €</option>'
        . '<option value="1000">bis 1.000 €</option>'
        . '</select>';
    $h .= '</div>';
    $h .= '<p class="lvk-count" aria-live="polite"></p>';
    return $h;
}

/** Filter-Sidebar: Schalter + 12 aufklappbare Facet-Gruppen (data-getrieben). */
function livento_cc_render_sidebar($offerings) {
    $h  = '<div class="lvk-sidebar-head"><span class="lvk-sidebar-title">Filter</span>';
    $h .= '<button type="button" class="lvk-reset" hidden>Zurücksetzen</button></div>';
    $h .= '<div class="lvk-toggles">'
        . '<button type="button" class="lvk-pill lvk-toggle" data-toggle="pflicht">Pflichtfortbildung</button>'
        . '<button type="button" class="lvk-pill lvk-toggle" data-toggle="azav">Bildungsgutschein</button>'
        . '<button type="button" class="lvk-pill lvk-toggle" data-toggle="vatexempt">USt-frei</button>'
        . '<button type="button" class="lvk-pill lvk-toggle" data-toggle="free">Kostenfrei</button>'
        . '</div>';

    $idx = 0;
    foreach (livento_cc_filter_groups() as $g) {
        if (!empty($g['hidden'])) {
            continue; // v1.58.0: nur noch per URL-Parameter
        }
        $counts = livento_cc_collect_facet($offerings, $g['field'], $g['arr']);
        if (empty($counts)) {
            continue; // Dimension nur zeigen, wenn Werte vorhanden
        }
        $items = array();
        foreach ($counts as $val => $cnt) {
            $items[] = array('val' => (string) $val, 'label' => livento_cc_facet_label($g['lab'], $val), 'cnt' => $cnt);
        }
        if ($g['dim'] === 'startmonth') {
            usort($items, function ($a, $b) { return strcmp($a['val'], $b['val']); }); // chronologisch
        } elseif (is_array($g['lab']) && in_array($g['dim'], array('duration', 'zeitmodell'), true)) {
            // v1.58.0: feste Reihenfolge der Label-Map (Umfang aufsteigend, Tageszeit)
            $pos = array_flip(array_keys($g['lab']));
            usort($items, function ($a, $b) use ($pos) {
                return ($pos[$a['val']] ?? 99) - ($pos[$b['val']] ?? 99);
            });
        } else {
            usort($items, function ($a, $b) { return strcasecmp($a['label'], $b['label']); });
        }

        $open = ($idx < 3) ? ' open' : ''; // die ersten 3 Gruppen offen, Rest eingeklappt
        $h .= '<details class="lvk-fgroup" data-group="' . esc_attr($g['dim']) . '"' . $open . '>';
        $h .= '<summary><span class="lvk-fgroup-title">' . esc_html($g['title']) . '</span><span class="lvk-group-count"></span></summary>';
        $h .= '<div class="lvk-pills">';
        foreach ($items as $it) {
            $h .= '<button type="button" class="lvk-pill" data-dim="' . esc_attr($g['dim']) . '" data-value="' . esc_attr($it['val']) . '">'
                . esc_html($it['label']) . ' <span class="lvk-pill-count">' . (int) $it['cnt'] . '</span></button>';
        }
        $h .= '</div></details>';
        $idx++;
    }
    return $h;
}

/** Pipe-umrahmte Werteliste eines Array-Feldes fuer data-Attribute (|a|b|). */
function livento_cc_data_list($o, $field) {
    $vals = (isset($o[$field]) && is_array($o[$field])) ? $o[$field] : array();
    $vals = array_filter(array_map('strval', $vals), function ($v) {
        return $v !== '';
    });
    return empty($vals) ? '' : '|' . implode('|', $vals) . '|';
}

/**
 * Die KI-Plakette zu einem Angebot, oder ein leerer String.
 *
 * Das Kursbild liegt nicht in der WordPress-Mediathek, sondern im Supabase-
 * Bucket public-course-images. Das Plugin "Livento KI-Kennzeichnung" kann es
 * deshalb nicht erfassen — es gibt dazu keinen WordPress-Anhang. Die
 * Kennzeichnung kommt darum aus Campus Connect mit, als Feld am Angebot
 * (public_image_ai_generated, seit v3.220.0).
 *
 * Bewusst eigene Klassen im lvk-Namensraum statt der Klassen des anderen
 * Plugins: Sonst haenge dieser Katalog an einem Stylesheet, das nur geladen
 * wird, solange in der Mediathek mindestens ein Bild angehakt ist.
 */
function livento_cc_ki_plakette($o) {
    if (empty($o['public_image_ai_generated'])) {
        return '';
    }
    return '<span class="lvk-ki">' . esc_html(livento_cc_ki_text()) . '</span>';
}

/** Beschriftung der Plakette. Filterbar, damit sie sich zentral umstellen laesst. */
function livento_cc_ki_text() {
    return (string) apply_filters('livento_cc_ki_text', 'KI-generiert');
}

/**
 * Eine Kurskarte. v1.58.0: $members = alle Termine derselben Gruppe (inkl. $o, nach Start
 * sortiert). Die data-Attribute fuer den Filter tragen dann die Werte ALLER Termine
 * (|a|b|), damit z. B. der Start-Monat-Filter auch den zweiten Termin findet.
 */
function livento_cc_render_card($o, $members = array()) {
    if (empty($members)) {
        $members = array($o);
    }
    $has_slug = !empty($o['slug']);
    $url = $has_slug ? livento_cc_detail_url($o['slug']) : ($o['wc_checkout_url'] ?? '');
    $desc = $o['short_description'] ?: $o['public_description'] ?: '';
    $desc = mb_substr(wp_strip_all_tags($desc), 0, 180);

    $meta = array();
    if (!empty($o['start_datetime'])) {
        $meta[] = 'Beginn: ' . livento_cc_fmt_date($o['start_datetime']);
    }
    if (!empty($o['site_city'])) {
        $meta[] = $o['site_city'];
    }
    if ($o['public_price'] !== null && $o['public_price'] !== '') {
        $meta[] = livento_cc_fmt_price($o['public_price'], !empty($o['is_vat_exempt']));
    }

    $title_html = esc_html($o['title']);
    if ($url) {
        $title_html = '<a href="' . esc_url($url) . '">' . $title_html . '</a>';
    }

    $img = '';
    if (!empty($o['public_image_url'])) {
        $img = '<a href="' . esc_url($url ?: '#') . '" class="lvk-card-img">'
            . '<img src="' . esc_url($o['public_image_url']) . '" alt="' . esc_attr($o['title']) . '" loading="lazy">'
            . livento_cc_ki_plakette($o) . '</a>';
    }

    // data-Attribute fuer den clientseitigen Filter + Sortierung
    $data_text = strtolower(wp_strip_all_tags($o['title'] . ' ' . ($o['short_description'] ?? '') . ' ' . ($o['public_description'] ?? '')));
    $featured = !empty($o['is_featured']) ? (isset($o['featured_order']) ? (int) $o['featured_order'] : 0) : 99999;
    $price = ($o['public_price'] !== null && $o['public_price'] !== '') ? (string) (float) $o['public_price'] : '';
    // v1.58.0: Werte ueber alle Termine der Gruppe (Union) bzw. Minimum/Summe.
    $union = function ($field) use ($members) {
        $vals = array();
        foreach ($members as $m) {
            $v = isset($m[$field]) ? $m[$field] : null;
            foreach ((is_array($v) ? $v : array($v)) as $x) {
                $x = (string) $x;
                if ($x !== '') {
                    $vals[$x] = true;
                }
            }
        }
        return empty($vals) ? '' : '|' . implode('|', array_keys($vals)) . '|';
    };
    $min_start = '';
    $min_price = $price;
    $booked    = 0;
    foreach ($members as $m) {
        $st = (string) ($m['start_datetime'] ?? '');
        if ($st !== '' && ($min_start === '' || strcmp($st, $min_start) < 0)) {
            $min_start = $st;
        }
        if (isset($m['public_price']) && $m['public_price'] !== null && $m['public_price'] !== ''
            && ($min_price === '' || (float) $m['public_price'] < (float) $min_price)) {
            $min_price = (string) (float) $m['public_price'];
        }
        $booked += (int) ($m['booking_count'] ?? 0);
    }
    $data = ' data-type="' . esc_attr((string) ($o['offering_type'] ?? '')) . '"'
          . ' data-kind="' . esc_attr((string) ($o['_kind'] ?? '')) . '"'
          . ' data-termine="' . (int) count($members) . '"'
          . ' data-pflicht="' . (!empty($o['is_mandatory_training']) ? '1' : '') . '"'
          . ' data-zeitmodell="' . esc_attr($union('_zeitmodell')) . '"'
          . ' data-format="' . esc_attr($union('format')) . '"'
          . ' data-level="' . esc_attr((string) ($o['level'] ?? '')) . '"'
          . ' data-city="' . esc_attr($union('site_city')) . '"'
          . ' data-audience="' . esc_attr(livento_cc_data_list($o, 'audience')) . '"'
          . ' data-funding="' . esc_attr(livento_cc_data_list($o, 'funding')) . '"'
          . ' data-recognition="' . esc_attr(livento_cc_data_list($o, 'recognition')) . '"'
          . ' data-methodology="' . esc_attr(livento_cc_data_list($o, 'methodology')) . '"'
          . ' data-topics="' . esc_attr(livento_cc_data_list($o, 'topics')) . '"'
          . ' data-duration="' . esc_attr((string) ($o['_duration'] ?? '')) . '"'
          . ' data-startmonth="' . esc_attr($union('_startmonth')) . '"'
          . ' data-availability="' . esc_attr($union('_availability')) . '"'
          . ' data-azav="' . (!empty($o['is_azav_relevant']) ? '1' : '') . '"'
          . ' data-vatexempt="' . (!empty($o['is_vat_exempt']) ? '1' : '') . '"'
          . ' data-free="' . (!empty($o['is_free']) ? '1' : '') . '"'
          . ' data-price="' . esc_attr($min_price) . '"'
          . ' data-start="' . esc_attr($min_start) . '"'
          . ' data-published="' . esc_attr((string) ($o['published_at'] ?? '')) . '"'
          . ' data-booked="' . (int) $booked . '"'
          . ' data-rating="' . esc_attr(($r = livento_cc_rating($o)) !== null ? (string) $r['avg'] : '') . '"'
          . ' data-featured="' . (int) $featured . '"'
          . ' data-text="' . esc_attr($data_text) . '"';

    $out  = '<article class="lvk-card"' . $data . '>';
    $out .= $img;
    $out .= '<div class="lvk-card-body">';
    $kinds = livento_cc_kind_singular();
    $kind  = (string) ($o['_kind'] ?? '');
    if ($kind !== '' && isset($kinds[$kind])) {
        $out .= '<p class="lvk-card-kind">' . esc_html($kinds[$kind]) . '</p>';
    }
    if (!empty($o['format']) || !empty($o['is_azav_relevant']) || livento_cc_is_on_request($o) || !empty($o['is_mandatory_training'])) {
        $out .= '<div class="lvk-badges">';
        if (!empty($o['is_mandatory_training'])) {
            $out .= '<span class="lvk-badge lvk-badge-pflicht">Pflichtfortbildung</span>';
        }
        if (livento_cc_is_on_request($o)) {
            $out .= '<span class="lvk-badge lvk-badge-anfrage">' . (livento_cc_is_self_learning($o) ? 'Auf Anfrage' : 'Termin auf Anfrage') . '</span>';
        }
        if (!empty($o['format'])) {
            $out .= '<span class="lvk-badge">' . esc_html(livento_cc_format_label($o['format'])) . '</span>';
        }
        if (livento_cc_is_bildungsgutschein($o)) {
            $out .= '<span class="lvk-badge lvk-badge-bg">' . livento_cc_bg_icon() . 'Bildungsgutschein möglich</span>';
        }
        $out .= '</div>';
    }
    // v1.58.0: h3 — die Abschnitte des Katalogs tragen jetzt h2.
    $out .= '<h3 class="lvk-card-title">' . $title_html . '</h3>';
    $out .= livento_cc_rating_html(livento_cc_rating($o));
    if ($desc) {
        $out .= '<p class="lvk-card-desc">' . esc_html($desc) . '</p>';
    }
    if (!empty($meta)) {
        $out .= '<p class="lvk-card-meta">' . esc_html(implode(' · ', $meta)) . '</p>';
    }
    // v1.58.0: Umfang · Laufzeit · Zeitmodell, Abschluss
    $facts = array();
    $umfang = livento_cc_umfang_label($o);
    if ($umfang !== '') {
        $facts[] = 'Umfang: ' . $umfang;
    }
    $laufzeit = livento_cc_laufzeit_label($o);
    if ($laufzeit !== '') {
        $facts[] = $laufzeit;
    }
    $zm_labels = livento_cc_time_model_labels();
    $zm = array();
    foreach ((array) ($o['_zeitmodell'] ?? array()) as $t) {
        if (isset($zm_labels[$t])) {
            $zm[] = $zm_labels[$t];
        }
    }
    if (!empty($zm)) {
        $facts[] = implode(', ', $zm);
    }
    if (!empty($facts)) {
        $out .= '<p class="lvk-card-facts">' . esc_html(implode(' · ', $facts)) . '</p>';
    }
    $abschluss = livento_cc_abschluss_label($o);
    if ($abschluss !== '') {
        $out .= '<p class="lvk-card-facts">Abschluss: ' . esc_html($abschluss) . '</p>';
    }
    // v1.58.0: weitere Termine derselben Gruppe, je mit eigener Detailseite
    $more = array();
    foreach (array_slice($members, 1) as $m) {
        if (!empty($m['start_datetime']) && !empty($m['slug'])) {
            $more[] = $m;
        }
    }
    if (!empty($more)) {
        $links = array();
        foreach (array_slice($more, 0, 4) as $m) {
            $links[] = '<a href="' . esc_url(livento_cc_detail_url($m['slug'])) . '">'
                . esc_html(wp_date('d.m.Y', strtotime((string) $m['start_datetime']))) . '</a>';
        }
        $rest = count($more) - 4;
        $out .= '<p class="lvk-card-dates">Weitere Termine: ' . implode(', ', $links)
            . ($rest > 0 ? ' <span>+' . (int) $rest . '</span>' : '') . '</p>';
    }
    if ($url) {
        $label = $has_slug ? (livento_cc_is_on_request($o) ? 'Jetzt anfragen' : 'Details ansehen') : 'Jetzt buchen';
        $out .= '<a class="lvk-card-cta" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
    }
    $out .= '</div></article>';
    return $out;
}

/* ---- CRO-Bausteine (Detailseite) — alle datengetrieben aus public_offerings ---- */

/** Beratungs-/Rueckruf-Ziel fuer den Sekundaer-CTA. Leer = Button wird weggelassen. */
function livento_cc_beratung_url() {
    return trim((string) get_option('livento_cc_beratung_url', ''));
}

/** Ist der Kurs foerderrelevant? AZAV-Flag ODER hinterlegte Foerderungen. */
function livento_cc_is_foerderbar($o) {
    if (!empty($o['is_azav_relevant'])) {
        return true;
    }
    return !empty($o['funding']) && is_array($o['funding']);
}

/**
 * CTA-Buttons: Primaer „Jetzt Platz sichern" (wc_checkout_url) + optional Sekundaer.
 * $variant 'top' = heller Cluster (Sekundaer: Rueckruf, falls URL gesetzt);
 *          'block' = gruener Block (Sekundaer: „Foerderung pruefen").
 * Eine Quelle fuer Cluster oben, Block unten und Sticky-Bar.
 */
function livento_cc_cta_buttons($o, $variant = 'top') {
    if (empty($o['wc_checkout_url'])) {
        return '';
    }
    $on_dark = ($variant === 'block');
    $out  = '<div class="lvk-cta-row">';
    $out .= '<a class="lvk-cta' . ($on_dark ? ' lvk-cta-on-dark' : '') . '" href="'
          . esc_url($o['wc_checkout_url']) . '" rel="nofollow">Jetzt Platz sichern</a>';
    if ($on_dark) {
        $out .= '<a class="lvk-cta-secondary lvk-on-dark" href="'
              . esc_url(livento_cc_foerder_list_url()) . '">Förderung prüfen</a>';
    } else {
        $beratung = livento_cc_beratung_url();
        if ($beratung !== '') {
            $out .= '<a class="lvk-cta-secondary" href="' . esc_url($beratung) . '">Rückruf vereinbaren</a>';
        }
    }
    $out .= '</div>';
    return $out;
}

/** v1.56.0: Kleines Gutschein-Icon fuer Badge und Abschnitt (erbt die Textfarbe). */
function livento_cc_bg_icon() {
    return '<svg class="lvk-bg-ic" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 8a2 2 0 012-2h14a2 2 0 012 2v2a2 2 0 000 4v2a2 2 0 01-2 2H5a2 2 0 01-2-2v-2a2 2 0 000-4z"/><path d="M14 6v12" stroke-dasharray="2 2"/></svg>';
}

/** v1.56.0: Das Foerderprogramm mit dem Tag azav_bildungsgutschein (= Hub-Seite), oder null. */
function livento_cc_bg_program() {
    foreach (livento_cc_foerderungen() as $f) {
        if (isset($f['funding_key']) && $f['funding_key'] === 'azav_bildungsgutschein') {
            return $f;
        }
    }
    return null;
}

/** v1.56.0: URL der Hub-Seite zum Bildungsgutschein (Fallback: Foerder-Uebersicht). */
function livento_cc_bg_hub_url() {
    $f = livento_cc_bg_program();
    return ($f && !empty($f['slug'])) ? livento_cc_foerder_url($f['slug']) : livento_cc_foerder_list_url();
}

/** v1.56.0: Standardtext fuer den Abschnitt auf Kursseiten (Markdown-Light). */
function livento_cc_bg_kurs_text_default() {
    return "Diese Weiterbildung ist eine **AZAV-zugelassene Maßnahme**. Mit einem Bildungsgutschein der Agentur für Arbeit oder des Jobcenters können bis zu 100 % der Lehrgangskosten übernommen werden.\n\n"
         . "**So kommst du zu deinem Bildungsgutschein:**\n\n"
         . "- **1. Beratung:** Vereinbare einen Termin bei deiner Agentur für Arbeit oder deinem Jobcenter.\n"
         . "- **2. Gutschein beantragen:** Sprich die Weiterbildung bei deiner Vermittlungsfachkraft an und beantrage den Bildungsgutschein.\n"
         . "- **3. Anmelden:** Melde dich mit deinem Gutschein bei uns an – wir begleiten dich bei allen weiteren Schritten.";
}

/** v1.56.0: Text fuer Kursseiten — gepflegt am Foerderprogramm, sonst Standard. */
function livento_cc_bg_kurs_text() {
    $f = livento_cc_bg_program();
    $t = ($f && isset($f['kurs_text'])) ? trim((string) $f['kurs_text']) : '';
    return $t !== '' ? $t : livento_cc_bg_kurs_text_default();
}

/** v1.56.0: Massnahmenummer oder Hinweis, dass sie mit dem ersten Gutschein vergeben wird. */
function livento_cc_bg_nr_text($o) {
    return !empty($o['azav_massnahmenummer'])
        ? (string) $o['azav_massnahmenummer']
        : 'folgt mit dem ersten Bildungsgutschein';
}

/** v1.56.0: Abschnitt „Foerderung mit Bildungsgutschein" auf der Kursdetailseite. */
function livento_cc_bg_section_html($o) {
    if (!livento_cc_is_bildungsgutschein($o)) {
        return '';
    }
    $out  = '<div class="lvk-section lvk-bg" id="bildungsgutschein">';
    $out .= '<h2>' . livento_cc_bg_icon() . 'Förderung mit Bildungsgutschein</h2>';
    $out .= '<div class="lvk-bg-text">' . livento_cc_richtext(livento_cc_bg_kurs_text()) . '</div>';
    $out .= '<div class="lvk-bg-nr">';
    if (!empty($o['azav_massnahmenummer'])) {
        $out .= '<strong>Maßnahme-Nr.</strong> <span class="lvk-bg-nr-val">' . esc_html($o['azav_massnahmenummer']) . '</span>'
              . '<span class="lvk-bg-nr-sub">Gib sie bei deiner Vermittlungsfachkraft an.</span>';
    } else {
        $out .= '<strong>Maßnahme-Nr.</strong> '
              . '<span class="lvk-bg-nr-sub">Die Maßnahmenummer für diesen Durchgang wird mit dem ersten Bildungsgutschein vergeben – sprich uns an, wir begleiten dich dabei.</span>';
    }
    $out .= '</div>';
    $beratung = livento_cc_beratung_url();
    if ($beratung === '') {
        $beratung = home_url('/kontakt/');
    }
    $out .= '<p class="lvk-bg-links">'
          . '<a class="lvk-bg-link" href="' . esc_url(livento_cc_bg_hub_url()) . '">Alles zum Bildungsgutschein →</a>'
          . '<a class="lvk-cta-secondary" href="' . esc_url($beratung) . '">Kostenlose Beratung</a>'
          . '</p>';
    return $out . '</div>';
}

/** Foerder-Chip am Preis. Leerer String, wenn nicht foerderrelevant. */
function livento_cc_foerder_hint_html($o) {
    if (!livento_cc_is_foerderbar($o)) {
        return '';
    }
    return '<div class="lvk-foerder-hint">'
         . '<strong>Ggf. förderfähig.</strong> '
         . '<a href="' . esc_url(livento_cc_foerder_list_url()) . '">In 2 Minuten prüfen →</a>'
         . '</div>';
}

/** Kompakte Trust-Zeile am Entscheidungspunkt — konditional aus vorhandenen Flags. */
function livento_cc_trust_row_html($o) {
    $items = array();
    if (!empty($o['is_azav_relevant'])) {
        $items[] = 'AZAV-zertifiziert';
    }
    if (!empty($o['is_vat_exempt'])) {
        $items[] = 'USt-frei';
    }
    if (!empty($o['recognition']) && is_array($o['recognition'])) {
        $items[] = 'Zertifikat';
    }
    $items[] = 'Rechnung möglich';
    $out = '<div class="lvk-trust">';
    foreach ($items as $i) {
        $out .= '<span class="lvk-trust-item">✓ ' . esc_html($i) . '</span>';
    }
    return $out . '</div>';
}

/**
 * Plaetze-/Knappheitsanzeige. Nur wenn der Admin-Indikator aktiv ist und
 * max_participants gesetzt ist. verbleibend = max − belegt.
 */
function livento_cc_scarcity_html($o) {
    if (empty($o['show_availability_indicator']) || empty($o['max_participants'])) {
        return '';
    }
    $max   = (int) $o['max_participants'];
    $taken = isset($o['enrolled_count']) ? (int) $o['enrolled_count'] : 0;
    $left  = $max - $taken;
    if ($left <= 0) {
        return '<div class="lvk-scarcity lvk-scarcity-full">Dieser Termin ist ausgebucht – frag nach dem nächsten Start.</div>';
    }
    $urgent = ($left <= 5);
    $label  = $urgent
        ? 'Nur noch ' . $left . ' ' . ($left === 1 ? 'Platz' : 'Plätze') . ' frei'
        : 'Begrenzte Teilnehmerzahl (max. ' . $max . ')';
    return '<div class="lvk-scarcity' . ($urgent ? ' lvk-scarcity-urgent' : '') . '">' . esc_html($label) . '</div>';
}

/** Inline-SVG-Icon (24x24, stroke=currentColor) fuer die Faktenbox-Zeilen. */
function livento_cc_fb_icon($key) {
    $paths = array(
        'format'   => '<rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/>',
        'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'hours'    => '<path d="M6 2h12M6 22h12M8 2v3c0 2 4 3.5 4 7s-4 5-4 7v1M16 2v3c0 2-4 3.5-4 7"/>',
        'cert'     => '<circle cx="12" cy="8" r="6"/><path d="M8.5 13.5 7 22l5-3 5 3-1.5-8.5"/>',
        'exam'     => '<rect x="6" y="3" width="12" height="18" rx="2"/><path d="M9 3h6v3H9zM9 13l2 2 4-4"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M3 10h18M8 2v4M16 2v4"/>',
        'voucher'  => '<path d="M3 8a2 2 0 012-2h14a2 2 0 012 2v2a2 2 0 000 4v2a2 2 0 01-2 2H5a2 2 0 01-2-2v-2a2 2 0 000-4z"/><path d="M12 6v12" stroke-dasharray="2 2"/>',
        'price'    => '<path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0l-7.2-7.2a2 2 0 0 1-.6-1.4V5a2 2 0 0 1 2-2h6.8a2 2 0 0 1 1.4.6l7.6 7.6a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
    );
    $p = isset($paths[$key]) ? $paths[$key] : $paths['clock'];
    return '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $p . '</svg>';
}

/** Format-Text fuer die Faktenbox — etwas ausfuehrlicher als das Badge-Label. */
function livento_cc_factbox_format_text($o) {
    $map = array(
        'online_live'      => '100 % online – live im virtuellen Klassenzimmer',
        'selbstlern'       => 'Selbstlernkurs – 100 % online, zeitlich flexibel',
        'blended'          => 'Blended Learning – online & Präsenz',
        'praesenz'         => 'Präsenz – vor Ort',
        'flexibel_modular' => 'Flexibel / modular',
        'kompakt'          => 'Kompaktformat',
    );
    $f = isset($o['format']) ? $o['format'] : '';
    return isset($map[$f]) ? $map[$f] : livento_cc_format_label($f);
}

/**
 * Zeitmodell „Variante B" (Dauer) — nur fuer Einzeltermine/Workshops sinnvoll.
 * Selbstlernkurse laufen „im eigenen Tempo"; Lehrgaenge nutzen die Umfang-Zeile.
 */
function livento_cc_factbox_dauer($o) {
    $type = isset($o['offering_type']) ? $o['offering_type'] : '';
    if ($type === 'self_learning') {
        return 'im eigenen Tempo';
    }
    if ($type === 'scheduled_course' && !empty($o['duration_minutes'])) {
        $min = (int) $o['duration_minutes'];
        $ue  = (int) round($min / LIVENTO_CC_UE_MINUTES);
        $txt = $min . ' Min';
        if ($ue >= 1) {
            $txt .= ' (' . $ue . ' UE)';
        }
        $f = isset($o['format']) ? $o['format'] : '';
        if ($f === 'online_live') {
            $txt .= ', live-online';
        } elseif ($f === 'praesenz') {
            $txt .= ', vor Ort';
        }
        return $txt;
    }
    return '';
}

/** Umfang (Unterrichtsstunden gesamt) — fuer Lehrgaenge/Selbstlernkurse. */
function livento_cc_factbox_umfang($o) {
    if (!empty($o['total_hours'])) {
        $unit = (isset($o['hours_unit']) && $o['hours_unit'] === 'unterrichtsstunden') ? 'UE' : 'Std.';
        return (int) $o['total_hours'] . ' ' . $unit;
    }
    // Lehrgang ohne total_hours: aus Minuten ableiten (Einzeltermine zeigen die Dauer-Zeile).
    $type = isset($o['offering_type']) ? $o['offering_type'] : '';
    if ($type !== 'scheduled_course' && !empty($o['duration_minutes'])) {
        return (int) round($o['duration_minutes'] / LIVENTO_CC_UE_MINUTES) . ' UE';
    }
    return '';
}

/** Naechster Start — Selbstlernkurse jederzeit, Lehrgaenge ohne Datum „auf Anfrage". */
function livento_cc_factbox_start($o) {
    $type = isset($o['offering_type']) ? $o['offering_type'] : '';
    if ($type === 'self_learning') {
        return 'jederzeit starten';
    }
    if (!empty($o['start_datetime'])) {
        return livento_cc_fmt_date($o['start_datetime']);
    }
    if ($type === 'program' || $type === 'on_request') {
        return 'auf Anfrage';
    }
    return '';
}

/**
 * Faktenbox „Auf einen Blick" — sticky rechte Spalte (Desktop) / Block unter dem Intro (Mobile).
 * Buendelt Eckdaten + Buchungs-CTA above the fold. Ersetzt die fruehere Faktenliste + den oberen
 * CTA-Cluster (eine Quelle, keine Dublette). Alle Zeilen datengetrieben und konditional.
 */
function livento_cc_factbox_html($o) {
    // Zeilen: [icon-key, label, bereits-escapter Wert]
    $rows = array();

    if (!empty($o['format'])) {
        $rows[] = array('format', 'Format', esc_html(livento_cc_factbox_format_text($o)));
    }
    $dauer = livento_cc_factbox_dauer($o);
    if ($dauer !== '') {
        $rows[] = array('clock', 'Dauer', esc_html($dauer));
    }
    $umfang = livento_cc_factbox_umfang($o);
    if ($umfang !== '') {
        $rows[] = array('hours', 'Umfang', esc_html($umfang));
    }
    // v1.47.0: Redaktionelle Pruefungsangabe aus Campus Connect (exam_title).
    // Leer = die Zeile erscheint gar nicht. Es wird NIE automatisch "keine
    // Pruefung" behauptet: ungepflegt heisst keine Aussage, nicht Abwesenheit.
    if (!empty($o['exam_title'])) {
        $rows[] = array('exam', 'Prüfung', esc_html($o['exam_title']));
    }
    if (!empty($o['certificate_title'])) {
        // v1.47.0: Der feste Vorspann "Qualifiziertes Zertifikat ‚…'" ist
        // entfallen. Er war eine Qualitaetsaussage, die das Plugin selbst
        // traf — bei einem pruefungsfreien Workshop zu viel versprochen, und
        // im Pflegefeld steht ausdruecklich, keine Anerkennungs-Aussagen zu
        // machen. Angezeigt wird jetzt der gepflegte Wortlaut.
        $abschluss = $o['certificate_title'];
        if (!empty($o['rbp_points'])) {
            $abschluss .= ' · ' . (int) $o['rbp_points'] . ' RbP-Punkte';
        }
        $rows[] = array('cert', 'Abschluss', esc_html($abschluss));
    }
    $start = livento_cc_factbox_start($o);
    if ($start !== '') {
        $rows[] = array('calendar', 'Nächster Start', esc_html($start));
    }
    $sched_sum = livento_cc_schedule_summary($o);
    if ($sched_sum !== '') {
        $rows[] = array('clock', 'Unterrichtszeiten', esc_html($sched_sum));
    }
    if (isset($o['public_price']) && $o['public_price'] !== null && $o['public_price'] !== '') {
        $rows[] = array('price', 'Kosten', esc_html(livento_cc_fmt_price($o['public_price'], !empty($o['is_vat_exempt']))));
    }
    // v1.55.0/v1.56.0: Massnahmenummer bei jedem AZAV-Kurs — ohne gepflegte Nummer
    // mit dem Hinweis, dass sie mit dem ersten Bildungsgutschein vergeben wird.
    if (livento_cc_is_bildungsgutschein($o)) {
        $rows[] = array('voucher', 'Maßnahme-Nr.', esc_html(livento_cc_bg_nr_text($o))
            . '<span class="lvk-fb-sub">für deinen Bildungsgutschein (Agentur für Arbeit / Jobcenter)</span>');
    }

    $out  = '<aside class="lvk-factbox" aria-label="Auf einen Blick">';
    $out .= '<p class="lvk-fb-eyebrow">Auf einen Blick</p>';
    $out .= '<p class="lvk-fb-title">' . esc_html($o['title']) . '</p>';

    // Knappheitsanzeige (konditional) oben in der Box.
    $out .= livento_cc_scarcity_html($o);

    if (!empty($rows)) {
        $out .= '<dl class="lvk-fb-list">';
        foreach ($rows as $r) {
            $out .= '<div class="lvk-fb-row">'
                  . '<span class="lvk-fb-ic" aria-hidden="true">' . livento_cc_fb_icon($r[0]) . '</span>'
                  . '<span class="lvk-fb-rc"><dt class="lvk-fb-label">' . esc_html($r[1]) . '</dt>'
                  . '<dd class="lvk-fb-val">' . $r[2] . '</dd></span>'
                  . '</div>';
        }
        $out .= '</dl>';
    }

    // Aktion 1: Foerder-Hinweis + Pruef-Link (nur wenn foerderbar).
    // v1.56.0: AZAV-Kurse bekommen die klare Aussage statt „ggf. foerderfaehig".
    if (livento_cc_is_bildungsgutschein($o)) {
        $out .= '<div class="lvk-fb-foerder">'
              . '<p class="lvk-fb-foerder-txt"><strong>Förderfähig mit Bildungsgutschein</strong> (Agentur für Arbeit / Jobcenter).</p>'
              . '<a class="lvk-fb-foerderlink" href="#bildungsgutschein">So geht’s →</a>'
              . '</div>';
    } elseif (livento_cc_is_foerderbar($o)) {
        $out .= '<div class="lvk-fb-foerder">'
              . '<p class="lvk-fb-foerder-txt">Dieser Kurs ist ggf. förderfähig – z. B. über Bildungsgutschein oder Bildungsurlaub.</p>'
              . '<a class="lvk-fb-foerderlink" href="' . esc_url(livento_cc_foerder_list_url()) . '">Förderung in 2 Minuten prüfen →</a>'
              . '</div>';
    }

    // Aktion 2 + 3: Primaer „Jetzt anmelden", Sekundaer „Unsicher? Kostenlose Beratung".
    $beratung = livento_cc_beratung_url();
    if ($beratung === '') {
        $beratung = home_url('/kontakt/');
    }
    $out .= '<div class="lvk-fb-actions">';
    if (!empty($o['wc_checkout_url'])) {
        $out .= '<a class="lvk-cta lvk-fb-cta" href="' . esc_url($o['wc_checkout_url']) . '" rel="nofollow">Jetzt anmelden</a>';
    } elseif (livento_cc_is_on_request($o)) {
        // v1.52.0: Anfragekurs — kein Checkout, das Formular steht weiter unten.
        $out .= '<a class="lvk-cta lvk-fb-cta" href="#kursanfrage">' . (livento_cc_is_self_learning($o) ? 'Kurs anfragen' : 'Termin anfragen') . '</a>';
    }
    $out .= '<a class="lvk-cta-secondary lvk-fb-cta2" href="' . esc_url($beratung) . '">Unsicher? Kostenlose Beratung</a>';
    $out .= '</div>';

    // Trust-Zeile (konditional).
    $out .= livento_cc_trust_row_html($o);

    $out .= '</aside>';
    return $out;
}

function livento_cc_render_detail($o) {
    $format_label = !empty($o['format']) ? livento_cc_format_label($o['format']) : '';
    $aud_labels = livento_cc_audience_labels();

    $out = '<div class="lvk lvk-detail">';
    $out .= '<p class="lvk-back"><a href="' . esc_url(livento_cc_list_url()) . '">← Alle Kurse</a></p>';
    $out .= '<h1 class="lvk-detail-title">' . esc_html($o['title']) . '</h1>';
    $out .= livento_cc_rating_html(livento_cc_rating($o), true);

    if (!empty($o['course_number'])) {
        $out .= '<p class="lvk-coursenr"><strong>Kurs-Nr.</strong> ' . esc_html($o['course_number']) . '</p>';
    }
    if (!empty($o['azav_massnahmenummer'])) {
        $out .= '<p class="lvk-coursenr"><strong>Maßnahme-Nr.</strong> ' . esc_html($o['azav_massnahmenummer'])
              . ' – für deinen Bildungsgutschein bei der Agentur für Arbeit oder dem Jobcenter</p>';
    }

    // Badges
    $badges = array();
    if ($format_label) {
        $badges[] = esc_html($format_label);
    }
    if (livento_cc_is_bildungsgutschein($o)) {
        $badges[] = 'Bildungsgutschein möglich';
    }
    if (!empty($o['is_vat_exempt'])) {
        $badges[] = 'USt-frei';
    }
    if (!empty($o['rbp_points'])) {
        $badges[] = (int) $o['rbp_points'] . ' RbP-Punkte';
    }
    if (!empty($badges)) {
        $out .= '<div class="lvk-badges">';
        foreach ($badges as $b) {
            $out .= '<span class="lvk-badge">' . $b . '</span>';
        }
        $out .= '</div>';
    }

    if (!empty($o['public_image_url'])) {
        // Huelle, damit die Plakette eine Bezugsflaeche hat. inline-block statt
        // block: so ist die Huelle genau so breit wie das Bild, auch wenn das
        // Bild schmaler ist als die Spalte — sonst schwebte die Plakette neben
        // dem Bild statt in seiner Ecke.
        $out .= '<span class="lvk-ki-wrap">'
            . '<img class="lvk-hero" src="' . esc_url($o['public_image_url']) . '" alt="' . esc_attr($o['title']) . '" loading="lazy">'
            . livento_cc_ki_plakette($o)
            . '</span>';
    }

    if (!empty($o['short_description'])) {
        $out .= '<p class="lvk-lead">' . esc_html($o['short_description']) . '</p>';
    }

    // Zielgruppen-Kurzzeile (Array) — bleibt im vollbreiten Kopfbereich ueber dem Grid.
    if (!empty($o['audience']) && is_array($o['audience'])) {
        $labels = array_map(function ($a) use ($aud_labels) {
            return isset($aud_labels[$a]) ? $aud_labels[$a] : $a;
        }, $o['audience']);
        $out .= '<p class="lvk-audience"><strong>Für:</strong> ' . esc_html(implode(' · ', $labels)) . '</p>';
    }

    // Zwei-Spalten-Layout: rechts die sticky Faktenbox „Auf einen Blick", links der Inhalt.
    // Die Faktenbox steht ZUERST im DOM → Mobile erscheint sie direkt unter dem Intro, Desktop
    // platziert sie das CSS-Grid rechts (sticky). Sie ersetzt die fruehere Faktenliste
    // (.lvk-facts) UND den oberen CTA-Cluster (.lvk-cta-cluster) — eine Quelle, keine Dublette.
    $out .= '<div class="lvk-detail-grid">';
    $out .= livento_cc_factbox_html($o);
    $out .= '<div class="lvk-detail-main">';

    // v1.52.0: Anfragekurs mit verknuepftem kuenftigem Termin — auf den Termin verweisen.
    $out .= livento_cc_next_program_html($o);

    if (!empty($o['target_audience'])) {
        $out .= '<div class="lvk-section"><h2>Zielgruppe</h2>' . livento_cc_richtext($o['target_audience']) . '</div>';
    }

    // v1.70.0: „Das wirst du koennen" — vier knappe Punkte, bevor argumentiert wird.
    $out .= livento_cc_outcomes_html($o);

    // Ihr Nutzen
    if (!empty($o['benefit'])) {
        $out .= '<div class="lvk-section lvk-benefit"><h2>Dein Nutzen</h2>' . livento_cc_richtext($o['benefit']) . '</div>';
    }

    // Beschreibung
    if (!empty($o['public_description'])) {
        $out .= '<div class="lvk-section"><h2>Beschreibung</h2>' . livento_cc_richtext($o['public_description']) . '</div>';
    }

    // Kursinhalte
    if (!empty($o['course_contents'])) {
        $out .= '<div class="lvk-section"><h2>Inhalte</h2>' . livento_cc_richtext($o['course_contents']) . '</div>';
    }

    // Unterrichtszeiten & Termine (abgeleiteter Zeitplan; nur mehrtaegige Weiterbildungen)
    $out .= livento_cc_render_schedule($o);

    // Modulübersicht
    $out .= livento_cc_render_modules($o['modules'] ?? null);

    // Wer unterrichtet (Feature 181) — nach dem Inhalt, vor den Voraussetzungen:
    // erst "was wird gelehrt", dann "von wem".
    $out .= livento_cc_render_offering_instructors($o);

    // Zugangsvoraussetzungen
    if (!empty($o['admission_requirements'])) {
        $out .= '<div class="lvk-section"><h2>Zugangsvoraussetzungen</h2>' . livento_cc_richtext($o['admission_requirements']) . '</div>';
    }

    // v1.62.0: So laeuft es ab (je Angebotsart) und was der Abschluss belegt.
    $out .= livento_cc_ablauf_html($o);
    $out .= livento_cc_abschluss_html($o);
    // v1.66.0: dasselbe Thema in anderer Form (Einstellungen → Formvarianten)
    $out .= livento_cc_formvergleich_html($o);

    // v1.56.0: Foerderung mit Bildungsgutschein (nur AZAV-Kurse).
    $out .= livento_cc_bg_section_html($o);

    // v1.52.0: Anfrageformular fuer Kurse ohne Termin.
    if (livento_cc_is_on_request($o)) {
        $out .= livento_cc_inquiry_section($o);
    }

    // v1.67.0: Inhouse-Anfrage fuer Einrichtungen (Anfragekurse ausgenommen, siehe dort).
    $out .= livento_cc_inhouse_html($o);

    // Häufige Fragen (FAQ) — sichtbarer Block; FAQPage-JSON-LD kommt aus wp_head.
    $faq_items = livento_cc_faq_items($o);
    if (!empty($faq_items)) {
        $out .= '<div class="lvk-section lvk-faq"><h2>Häufige Fragen</h2>';
        foreach ($faq_items as $f) {
            $out .= '<details class="lvk-faq-item"><summary>' . esc_html($f['q']) . '</summary>'
                  . '<div class="lvk-faq-a">' . livento_cc_richtext($f['a']) . '</div></details>';
        }
        $out .= '</div>';
    }

    // v1.64.0: Blogbeitraege, die diesem Kurs zugeordnet sind.
    $out .= livento_cc_fachbeitraege_html($o);

    // v1.62.0: Ansprechpartner (Einstellungen → Ansprechpartner).
    $out .= livento_cc_kontakt_html();

    // CRO: wiederholter CTA-Block am Seitenende (zweite Conversion-Chance nach dem Inhalt).
    if (!empty($o['wc_checkout_url'])) {
        $out .= '<div class="lvk-cta-block">'
              . '<div class="lvk-cta-block-head">Bereit für den nächsten Karriereschritt?</div>'
              . '<div class="lvk-cta-block-sub">Sichere dir deinen Platz – oder lass uns vorab deine Förderung prüfen.</div>'
              . livento_cc_cta_buttons($o, 'block')
              . '</div>';
    }

    $out .= '</div>'; // .lvk-detail-main
    $out .= '</div>'; // .lvk-detail-grid

    // Kein eigener Footer (© / Impressum / Datenschutz) — das liefert das WordPress-Theme
    // bereits seitenweit; ein Plugin-Footer waere eine Dublette (v1.19.0).

    // CRO: mobiler Sticky-CTA (nur < 768px sichtbar, siehe CSS). Native Seite, kein iframe → fixed ok.
    if (!empty($o['wc_checkout_url'])) {
        $price = ($o['public_price'] !== null && $o['public_price'] !== '')
            ? livento_cc_fmt_price($o['public_price'], !empty($o['is_vat_exempt'])) : '';
        $out .= '<div class="lvk-sticky">';
        if ($price !== '') {
            $out .= '<div class="lvk-sticky-price">' . esc_html($price) . '</div>';
        }
        $out .= '<a class="lvk-cta" href="' . esc_url($o['wc_checkout_url']) . '" rel="nofollow">Platz sichern</a>';
        $out .= '</div>';
    } elseif (livento_cc_is_on_request($o)) {
        $out .= '<div class="lvk-sticky">'
              . '<div class="lvk-sticky-price">' . (livento_cc_is_self_learning($o) ? 'Auf Anfrage' : 'Termin auf Anfrage') . '</div>'
              . '<a class="lvk-cta" href="#kursanfrage">Anfragen</a>'
              . '</div>';
    }

    $out .= '</div>';
    return $out;
}

/**
 * v1.62.0: Ansprechpartner der Kursdetailseiten (Livento Katalog → Einstellungen → Ansprechpartner).
 * Name, Rolle und Foto stehen bewusst NICHT im Code (oeffentliches Repo) — ohne Name heisst die
 * Karte „Deine Kursberatung“. Vorgaben sind nur die Firmenkontakte aus dem Website-Kopf.
 */
function livento_cc_kontakt() {
    $vorgabe = array(
        'aktiv'   => 1,
        'name'    => '',
        'rolle'   => '',
        'foto'    => '',
        'telefon' => '+49 3494 3864790',
        'email'   => 'info@livento-bildung.de',
        'antwort' => 'innerhalb weniger Stunden an Werktagen',
    );
    $gespeichert = get_option('livento_cc_kontakt', null);
    return is_array($gespeichert) ? array_merge($vorgabe, $gespeichert) : $vorgabe;
}

/** v1.62.0: Karte „Fragen zum Kurs?“ mit Foto, Antwortzeit, Telefon und E-Mail. */
function livento_cc_kontakt_html() {
    $k = livento_cc_kontakt();
    if (empty($k['aktiv']) || (trim($k['telefon']) === '' && trim($k['email']) === '')) {
        return '';
    }
    $person = trim($k['name']) !== '';
    $name   = $person ? $k['name'] : 'Deine Kursberatung';
    $satz   = 'Du hast Fragen zu Inhalten, Förderung oder Ablauf? '
            . ($person ? 'Ich melde mich' : 'Wir melden uns')
            . (trim($k['antwort']) !== '' ? ' ' . trim($k['antwort']) : '') . '.';

    $out  = '<div class="lvk-section lvk-kontakt"><h2>Fragen zum Kurs?</h2><div class="lvk-kontakt-card">';
    if (trim($k['foto']) !== '') {
        $out .= '<img class="lvk-kontakt-foto" src="' . esc_url($k['foto']) . '" alt="' . esc_attr($name) . '" width="96" height="96" loading="lazy">';
    }
    $out .= '<div class="lvk-kontakt-body">';
    $out .= '<p class="lvk-kontakt-name">' . esc_html($name) . '</p>';
    if (trim($k['rolle']) !== '') {
        $out .= '<p class="lvk-kontakt-rolle">' . esc_html($k['rolle']) . '</p>';
    }
    $out .= '<p class="lvk-kontakt-text">' . esc_html($satz) . '</p><p class="lvk-kontakt-links">';
    if (trim($k['telefon']) !== '') {
        $tel = preg_replace('/[^0-9+]/', '', $k['telefon']);
        $out .= '<a href="tel:' . esc_attr($tel) . '">' . esc_html($k['telefon']) . '</a>';
    }
    if (trim($k['email']) !== '') {
        $out .= '<a href="mailto:' . esc_attr($k['email']) . '">' . esc_html($k['email']) . '</a>';
    }
    return $out . '</p></div></div></div>';
}

/**
 * v1.62.0: Letzter Schritt „Abschluss“ aus den gepflegten Feldern (livento_cc_abschluss_label).
 * Nichts gepflegt = kein Schritt; es wird nie ein Abschluss behauptet, den der Kurs nicht hat.
 */
function livento_cc_ablauf_abschluss_step($o, $selbstlern) {
    switch (livento_cc_abschluss_label($o)) {
        case 'Zertifikat mit Abschlusstest':
            return array('Zertifikat', $selbstlern
                ? 'Nach bestandenem Abschlusstest steht dein Zertifikat in Campus Connect zum Download bereit.'
                : 'Nach bestandenem Abschlusstest erhältst du dein Zertifikat.');
        case 'Zertifikat mit Abschlussprüfung':
            return array('Zertifikat', 'Nach bestandener Abschlussprüfung erhältst du dein Zertifikat.');
        case 'Zertifikat':
            return array('Zertifikat', $selbstlern
                ? 'Am Ende steht dein Zertifikat in Campus Connect zum Download bereit.'
                : 'Am Ende erhältst du dein Zertifikat.');
        case 'Teilnahmebescheinigung mit Abschlusstest':
            return array('Teilnahmebescheinigung', $selbstlern
                ? 'Nach bestandenem Abschlusstest steht deine Teilnahmebescheinigung in Campus Connect zum Download bereit.'
                : 'Nach bestandenem Abschlusstest erhältst du deine Teilnahmebescheinigung.');
        case 'Teilnahmebescheinigung mit Abschlussprüfung':
            return array('Teilnahmebescheinigung', 'Nach bestandener Abschlussprüfung erhältst du deine Teilnahmebescheinigung.');
        case 'Teilnahmebescheinigung':
            return array('Teilnahmebescheinigung', 'Am Ende erhältst du deine Teilnahmebescheinigung.');
    }
    return null;
}

/**
 * v1.62.0: „So läuft es ab“ in 3–4 Schritten je Angebotsart. Grundlage ist der echte Ablauf:
 * Nach jeder Buchung ueber WooCommerce schickt Campus Connect eine E-Mail mit dem Zugang bzw.
 * der Buchungsbestaetigung (send-enrollment-welcome, Lehrgaenge und Einzeltermine).
 * Kurse mit Bildungsgutschein: kein Ablauf hier, das erklaert der Abschnitt zum Bildungsgutschein.
 * Ohne Buchungs- oder Anfrageweg: keine Schritte.
 */
function livento_cc_ablauf_steps($o) {
    if (livento_cc_is_bildungsgutschein($o)) {
        return array();
    }
    $selbst = livento_cc_is_self_learning($o);
    $steps  = array();

    if (livento_cc_is_on_request($o)) {
        $antwort = trim(livento_cc_kontakt()['antwort']);
        $bald    = $antwort !== '' ? ' ' . $antwort : '';
        if ($selbst) {
            // Selbstlernkurse auf Anfrage haben keinen Termin (20.11.2026: 7 von 21 Anfragekursen).
            $steps[] = array('Kurs anfragen', 'Schreib uns über das Formular auf dieser Seite.');
            $steps[] = array('Wir melden uns', 'Wir melden uns' . $bald . ' und klären alles Weitere mit dir.');
        } else {
            $steps[] = array('Termin anfragen', 'Sag uns über das Formular auf dieser Seite, wann es dir passt.');
            $steps[] = array('Wir melden uns', 'Wir melden uns' . $bald . ' und stimmen den Termin mit dir ab.');
        }
    } elseif (!empty($o['wc_checkout_url'])) {
        if ($selbst) {
            $steps[] = array('Online buchen', 'Du buchst den Kurs direkt hier auf der Seite.');
            $steps[] = array('Zugang per E‑Mail', 'Nach der Buchung bekommst du deinen Zugang zu Campus Connect, unserer Lernplattform.');
            $steps[] = array('Lernen im eigenen Tempo', 'Am PC, Tablet oder Handy – unterbrechen und weitermachen, wann es dir passt.');
        } else {
            $steps[] = array('Platz sichern', 'Du meldest dich direkt hier auf der Seite an.');
            $steps[] = array('Bestätigung per E‑Mail', 'Du bekommst deine Buchungsbestätigung und deinen Zugang zu Campus Connect, unserer Lernplattform.');
            switch ((string) ($o['format'] ?? '')) {
                case 'praesenz':
                    $ort = trim((string) ($o['site_city'] ?? ''));
                    $steps[] = array('Vor Ort lernen', 'Du lernst in der Gruppe ' . ($ort !== '' ? 'in ' . $ort : 'vor Ort') . '.');
                    break;
                case 'online_live':
                    $steps[] = array('Live online lernen', 'Du bist live dabei – vom Arbeitsplatz oder von zu Hause aus.');
                    break;
                case 'blended':
                    $steps[] = array('Vor Ort und online', 'Du lernst abwechselnd in der Gruppe vor Ort und online.');
                    break;
                default:
                    $steps[] = array('Teilnehmen', 'Du nimmst an den Unterrichtsterminen teil.');
            }
        }
    } else {
        return array();
    }

    $ende = livento_cc_ablauf_abschluss_step($o, $selbst);
    if ($ende !== null) {
        $steps[] = $ende;
    }
    return $steps;
}

function livento_cc_ablauf_html($o) {
    $steps = livento_cc_ablauf_steps($o);
    if (empty($steps)) {
        return '';
    }
    $out = '<div class="lvk-section lvk-ablauf"><h2>So läuft es ab</h2><ol class="lvk-steps">';
    foreach ($steps as $i => $s) {
        $out .= '<li><span class="lvk-step-nr" aria-hidden="true">' . ($i + 1) . '</span>'
              . '<span class="lvk-step-t">' . esc_html($s[0]) . '</span>'
              . '<span class="lvk-step-x">' . esc_html($s[1]) . '</span></li>';
    }
    return $out . '</ol></div>';
}

/**
 * v1.62.0: „Dein Abschluss“ — nur bei Kursen mit Zertifikat, nur aus gepflegten Feldern:
 * Zertifikatstitel, Pruefung, RbP-Punkte, dazu die Echtheitspruefung ueber die Pruefnummer
 * (Campus Connect /zertifikat-prüfen). Keine Aussagen ueber Anerkennungen, die nicht gepflegt sind.
 */
function livento_cc_abschluss_html($o) {
    if (strpos(livento_cc_abschluss_label($o), 'Zertifikat') !== 0) {
        return '';
    }
    $punkte = array();
    $titel  = trim((string) ($o['certificate_title'] ?? ''));
    $punkte[] = $titel !== '' ? 'Du erhältst das Zertifikat „' . esc_html($titel) . '“.' : 'Du erhältst ein Zertifikat.';
    $exam = trim((string) ($o['exam_title'] ?? ''));
    if ($exam !== '') {
        $punkte[] = '<strong>Prüfung:</strong> ' . esc_html($exam);
    }
    if (!empty($o['rbp_points'])) {
        $punkte[] = '<strong>' . (int) $o['rbp_points'] . ' Fortbildungspunkte</strong> bei der Registrierung beruflich Pflegender (RbP)';
    }
    $punkte[] = 'Jedes Zertifikat trägt eine Prüfnummer. Arbeitgeber und Behörden können es online auf Echtheit prüfen: '
              . '<a href="https://campus-connect.livento-bildung.de/zertifikat-pr%C3%BCfen" rel="noopener">Zertifikat prüfen →</a>';
    return '<div class="lvk-section lvk-abschluss"><h2>Dein Abschluss</h2><ul class="lvk-abschluss-list"><li>'
         . implode('</li><li>', $punkte) . '</li></ul></div>';
}

/* ============================================================
 * v1.64.0: Fachbeiträge ↔ Kurse
 * Zuordnung je Blogbeitrag als Post-Meta _livento_cc_ziel (je Ziel eine Zeile):
 *   k:<Kurstitel, klein>  = Kurs (alle Durchgaenge mit diesem Titel)
 *   t:<Themenbereich>     = Bereich auf der CampusTicket-Seite (Anker lv-kb-<n>-<bereich>)
 * Am Titel, nicht am Slug: Ein neuer Durchgang bekommt einen neuen Slug, die Zuordnung bleibt.
 * Gepflegt im Beitrag (Box „Passende Kurse (Livento)“). Ausgabe: „Fachbeiträge zum Thema“ auf
 * der Kursdetailseite und „Passende Weiterbildungen“ unter dem Beitrag.
 * ============================================================ */

function livento_cc_title_key($title) {
    $t = trim((string) $title);
    $t = function_exists('mb_strtolower') ? mb_strtolower($t, 'UTF-8') : strtolower($t);
    return preg_replace('/\s+/u', ' ', $t);
}

function livento_cc_ticket_url() {
    return home_url('/e-learning/campus-ticket/');
}

/**
 * Erstzuordnung, vom Nutzer am 20.11.2026 bestaetigt (48 Beitraege). Wird einmal in die Beitraege
 * uebernommen — nur dort, wo noch nichts gesetzt ist —, danach gilt allein die Pflege im Beitrag.
 */
function livento_cc_fb_erstzuordnung() {
    return array(
        6015 => array('k:pain nursing (schmerzmanagement)', 'k:pain nursing (schmerzmanagement) abendkurs', 'k:professionelles schmerzmanagement in der pflege'), // Expertenstandard Schmerzmanagement in der Pflege 2026
        6011 => array('k:ki-führerschein basic – künstliche intelligenz für pflege und betreuung', 'k:ki-kompetenzen für pflegekräfte – praxisworkshop', 'k:eu ai act essentials – datenschutz im umgang mit ki in der pflege'), // KI in der Pflege: Use Cases, Prompts und Recht 2026
        5411 => array('k:ki-führerschein basic – künstliche intelligenz für pflege und betreuung', 'k:ki-kompetenzen für pflegekräfte – praxisworkshop', 'k:wie uns ki in der maßnahmenplanung und dokumentation wirklich helfen kann – praxisworkshop'), // KI in der Pflege: Was Künstliche Intelligenz heute wirklich 
        6008 => array('k:qualitätsmanagementbeauftragte:r in sozial-, gesundheits- und pflegeeinrichtungen', 'k:interner auditor in der stationären und ambulanten pflege (iso 19011)'), // QMB Pflege: Qualitätsbeauftragte Weiterbildung 2026
        2604 => array('k:qualitätsmanagementbeauftragte:r in sozial-, gesundheits- und pflegeeinrichtungen', 'k:interner auditor in der stationären und ambulanten pflege (iso 19011)'), // Was ändert sich bei der Prüfung von ambulanten Einrichtungen
        6005 => array('k:medizinproduktebeauftragte:r', 'k:medizinproduktebeauftragte:r (abendkurs)'), // Medizinproduktebeauftragter Pflege: Pflicht &038 Aufgaben
        5973 => array('k:hygienebeauftragte:r in stationärer und ambulanter pflege', 'k:medizinproduktebeauftragte:r', 'k:qualitätsmanagementbeauftragte:r in sozial-, gesundheits- und pflegeeinrichtungen'), // Beauftragte in der Pflege: Wer ist Pflicht (2026)
        5970 => array('k:hygienebeauftragte:r in stationärer und ambulanter pflege'), // Hygienebeauftragte vs. Hygienefachkraft: Unterschied
        6002 => array('k:palliative care – fachweiterbildung für pflegekräfte'), // Palliative Care Weiterbildung: 160 Std., Pflicht &038 Kosten
        2547 => array('k:palliative care – fachweiterbildung für pflegekräfte', 'k:trauerbegleitung – basis- und aufbaukurs'), // Palliative Care Ausbildung für Pflegekräfte: Der vollständig
        5998 => array('k:pain nursing (schmerzmanagement) abendkurs', 'k:medizinproduktebeauftragte:r (abendkurs)', 'k:praxisanleiter – jährliche pflichtfortbildung (abendkurs)'), // Weiterbildung neben dem Schichtdienst in der Pflege
        5985 => array('k:verantwortliche pflegefachkraft (§ 71 sgb xi)', 'k:qualifizierung zur betreuungskraft nach §53b sgb xi - für stationäre und ambulante betreuung', 'k:praxisanleiter:in (§ 4 pflaprv)', 'k:palliative care – fachweiterbildung für pflegekräfte'), // Bildungsgutschein Pflege 2026: § 81 vs. § 82 SGB III
        5982 => array('k:verantwortliche pflegefachkraft (§ 71 sgb xi)'), // Heimleitung und Wohnbereichsleitung: Voraussetzungen, Landes
        5967 => array('k:verantwortliche pflegefachkraft (§ 71 sgb xi)'), // Voraussetzungen nach § 71 SGB XI
        4918 => array('k:verantwortliche pflegefachkraft (§ 71 sgb xi)'), // Aufgaben einer Pflegedienstleitung (PDL)  LIVENTO BILDUNG
        4917 => array('k:verantwortliche pflegefachkraft (§ 71 sgb xi)'), // Gehalt als Pflegedienstleitung (PDL) 2026  LIVENTO BILDUNG
        5979 => array('k:pflegeberater:in nach § 7a und § 45 sgb xi', 'k:pflegeberatungseinsätze nach § 37.3 & § 45 sgb xi', 'k:pflegeberater – pflegekurs nach §45 sgb xi'), // Pflegeberater werden: § 7a, § 37.3 und § 45 SGB XI
        3946 => array('k:pflegeberater:in nach § 7a und § 45 sgb xi', 'k:pflegeberatungseinsätze nach § 37.3 & § 45 sgb xi', 'k:pflegeberater – pflegekurs nach §45 sgb xi'), // Pflegeberatung nach § 7a SGB XI: Kostenlos, neutral und oft 
        3931 => array('k:beratungseinsatz nach § 37 abs. 3 sgb xi – vom ersten anruf bis zum nachweis', 'k:pflegeberatungseinsätze nach § 37.3 & § 45 sgb xi'), // Beratungseinsatz nach § 37 Abs. 3 SGB XI 2026: Was jetzt gil
        5964 => array('k:jährliche pflichtfortbildung „praxisanleitung aktuell“', 'k:praxisanleiter – jährliche pflichtfortbildung (abendkurs)'), // Praxisanleiter Pflichtfortbildung: 24 Stunden erklärt
        4899 => array('k:praxisanleiter:in (§ 4 pflaprv)', 'k:berufspädagogische zusatzqualifikation zur praxisanleitung in der pflege', 'k:jährliche pflichtfortbildung „praxisanleitung aktuell“'), // Praxisanleitung in der Pflege
        5961 => array('k:jährliche pflichtfortbildung für betreuungskräfte', 'k:auffrischungs-pflichtfortbildung betreuungskräfte', 'k:auffrischungs-pflichtfortbildung betreuungskräfte (ambulant & stationär) – abendkurs', 'k:auffrischungs-pflichtfortbildung betreuungskräfte (ambulant & stationär) – tageskurs'), // Fortbildung Betreuungskräfte: 16 Stunden Pflicht
        5958 => array('k:qualifizierung zur betreuungskraft nach §53b sgb xi - für stationäre und ambulante betreuung'), // Betreuungskraft 43b 53b: Voraussetzungen &038 Kosten
        1904 => array('k:qualifizierung zur betreuungskraft nach §53b sgb xi - für stationäre und ambulante betreuung'), // Betreuungskraft Weiterbildung
        6078 => array('k:fachkraft für demenzversorgung'), // Demenzprävention in der Pflege: Risikofaktoren, Frühdiagnost
        5732 => array('k:fachkraft für demenzversorgung'), // Selbsterhaltungstherapie (SET) bei Demenz  einfach erklärt
        4150 => array('k:fachkraft für demenzversorgung', 't:betreuung-aktivierung'), // Biografiearbeit in der Pflege: Methoden, Nutzen und praktisc
        5951 => array('k:basiskurs: behandlungspflege lg1'), // Behandlungspflege LG1 und LG2: Regeln je Bundesland
        5097 => array('k:pflegehelfer qn 1 – grundqualifikation pflege für pflegehilfskräfte', 'k:basiskurs für pflegehilfs- und assistenzkräfte qn 2 nach § 113c sgb xi'), // Pflegeassistenz-Ausbildung ab 2027: Was die neue bundeseinhe
        1662 => array('k:pflegehelfer qn 1 – grundqualifikation pflege für pflegehilfskräfte', 'k:basiskurs für pflegehilfs- und assistenzkräfte qn 2 nach § 113c sgb xi', 'k:qualifizierung zur betreuungskraft nach §53b sgb xi - für stationäre und ambulante betreuung'), // Quereinsteiger Pflege
        5781 => array('k:rechtsupdate pflege- und sozialrecht'), // Pflegereform 2026: Das PNOG im Überblick
        3945 => array('k:rechtsupdate pflege- und sozialrecht'), // Das BEEP-Gesetz 2026: Was die Befugniserweiterung und Entbür
        2574 => array('k:verwaltung in pflegeeinrichtungen (ambulant und stationär)', 'k:excel-grundkurs für verwaltungsmitarbeitende'), // Verwaltung Pflegeeinrichtungen Kurs 2026: Was Sie für ambula
        2556 => array('k:verwaltung in pflegeeinrichtungen (ambulant und stationär)', 'k:excel-grundkurs für verwaltungsmitarbeitende'), // Grundlagen der Verwaltung in Pflegeeinrichtungen (ambulant &
        1899 => array('k:verantwortliche pflegefachkraft (§ 71 sgb xi)', 'k:praxisanleiter:in (§ 4 pflaprv)', 'k:palliative care – fachweiterbildung für pflegekräfte'), // Pflegefachkraft Weiterbildung
        1641 => array('k:verantwortliche pflegefachkraft (§ 71 sgb xi)', 'k:praxisanleiter:in (§ 4 pflaprv)', 'k:palliative care – fachweiterbildung für pflegekräfte'), // Weiterbildung für Pflegekräfte
        5690 => array('t:behandlungspflege-prophylaxen'), // Soor- und Parotitisprophylaxe: Maßnahmen
        5689 => array('t:behandlungspflege-prophylaxen'), // Aspirationsprophylaxe: Maßnahmen in der Pflege
        5688 => array('t:behandlungspflege-prophylaxen'), // Zystitisprophylaxe: Maßnahmen in der Pflege
        5681 => array('t:behandlungspflege-prophylaxen'), // Obstipationsprophylaxe: Maßnahmen in der Pflege
        5678 => array('t:behandlungspflege-prophylaxen'), // Intertrigoprophylaxe: Maßnahmen &038 Hautpflege
        5674 => array('t:behandlungspflege-prophylaxen'), // Norton-Skala: Kriterien &038 Punktwerte
        5671 => array('t:behandlungspflege-prophylaxen'), // Braden-Skala: Punktwerte &038 Anwendung
        4257 => array('t:behandlungspflege-prophylaxen'), // Kontrakturenprophylaxe: Maßnahmen &038 Übungen
        4254 => array('t:behandlungspflege-prophylaxen'), // Pneumonieprophylaxe: Maßnahmen &038 Übungen
        4242 => array('t:behandlungspflege-prophylaxen'), // Dekubitus: Grade, Entstehung &038 Prophylaxe
        4237 => array('t:behandlungspflege-prophylaxen'), // Sturzprophylaxe: Maßnahmen, Übungen &038 Standard
        4195 => array('t:behandlungspflege-prophylaxen'), // Expertenstandards Pflege
    );
}

add_action('init', function () {
    if (get_option('livento_cc_fb_import') === '1') {
        return;
    }
    foreach (livento_cc_fb_erstzuordnung() as $post_id => $ziele) {
        if (get_post_type($post_id) !== 'post' || metadata_exists('post', $post_id, '_livento_cc_ziel')) {
            continue;
        }
        foreach ($ziele as $z) {
            add_post_meta($post_id, '_livento_cc_ziel', $z);
        }
    }
    update_option('livento_cc_fb_import', '1');
}, 20);

/** Themenbereiche der CampusTicket-Seite: Schluessel => Name, Anzahl, Link mit Anker. */
function livento_cc_ticket_bereiche() {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache  = array();
    $family = livento_cc_find_family('campusticket');
    if (!$family) {
        return $cache;
    }
    $index = 0; // gleiche Zaehlung wie die Varianten in [livento_tarif]
    foreach ((array) $family['plans'] as $plan) {
        foreach ((array) $plan['bundles'] as $bundle) {
            list($groups) = livento_cc_ticket_areas(livento_cc_get_bundle_courses($bundle['id']));
            foreach ($groups as $g) {
                if ($g['key'] !== '_weitere' && !isset($cache[$g['key']])) {
                    $cache[$g['key']] = array(
                        'label' => $g['label'],
                        'count' => count($g['courses']),
                        'url'   => livento_cc_ticket_url() . '#lv-kb-' . $index . '-' . $g['key'],
                    );
                }
            }
            $index++;
        }
    }
    return $cache;
}

/** Fruehester Durchgang eines Kurstitels im Katalog (oder null, wenn gerade keiner da ist). */
function livento_cc_offering_by_title_key($key) {
    $best = null;
    foreach ((array) livento_cc_get_offerings() as $o) {
        if (empty($o['slug']) || livento_cc_title_key($o['title'] ?? '') !== $key) {
            continue;
        }
        $s = !empty($o['start_datetime']) ? (string) $o['start_datetime'] : '9999';
        if ($best === null || strcmp($s, $best[0]) < 0) {
            $best = array($s, $o);
        }
    }
    return $best ? $best[1] : null;
}

/** Text aus WordPress (Titel, Inhalt) ohne Tags und Entitaeten — fuer esc_html. */
function livento_cc_klartext($html) {
    return trim(html_entity_decode(wp_strip_all_tags((string) $html), ENT_QUOTES, 'UTF-8'));
}

/** Kursdetailseite: bis zu drei Beitraege, die diesem Kurs zugeordnet sind (neueste zuerst). */
function livento_cc_fachbeitraege_html($o) {
    $posts = get_posts(array(
        'post_type'     => 'post',
        'post_status'   => 'publish',
        'numberposts'   => 3,
        'meta_key'      => '_livento_cc_ziel',
        'meta_value'    => 'k:' . livento_cc_title_key($o['title'] ?? ''),
        'orderby'       => 'date',
        'order'         => 'DESC',
        'no_found_rows' => true,
    ));
    if (empty($posts)) {
        return '';
    }
    $out = '<div class="lvk-section lvk-beitraege-sec"><h2>Fachbeiträge zum Thema</h2><div class="lvk-beitraege">';
    foreach ($posts as $p) {
        $text   = $p->post_excerpt !== '' ? $p->post_excerpt : strip_shortcodes($p->post_content);
        // Viele Beitraege beginnen mit „📌 Kurz gesagt“ — in der Karte ueberfluessig.
        $auszug = preg_replace('/^[^\p{L}\p{N}]*Kurz gesagt[\s:]*/u', '', livento_cc_klartext($text));
        $auszug = wp_trim_words($auszug, 22, ' …');
        $out .= '<a class="lvk-beitrag" href="' . esc_url(get_permalink($p)) . '">'
              . '<span class="lvk-beitrag-d">' . esc_html(get_the_date('d.m.Y', $p)) . '</span>'
              . '<span class="lvk-beitrag-t">' . esc_html(livento_cc_klartext(get_the_title($p))) . '</span>'
              . '<span class="lvk-beitrag-x">' . esc_html($auszug) . '</span>'
              . '<span class="lvk-beitrag-more">Weiterlesen →</span></a>';
    }
    return $out . '</div></div>';
}

/** Kasten „Passende Weiterbildungen“ unter einem Beitrag. Kurse, die gerade nicht im Katalog sind, fallen weg. */
function livento_cc_beitrag_kurse_html($post_id) {
    $ziele = array_unique((array) get_post_meta($post_id, '_livento_cc_ziel', false));
    if (empty($ziele)) {
        return '';
    }
    $kinds    = livento_cc_kind_singular();
    $bereiche = null;
    $karten   = array();
    foreach ($ziele as $z) {
        $z = (string) $z;
        if (strpos($z, 'k:') === 0) {
            $o = livento_cc_offering_by_title_key(substr($z, 2));
            if (!$o) {
                continue;
            }
            $selbst = livento_cc_is_self_learning($o);
            $kopf   = array_filter(array(
                $kinds[livento_cc_effective_kind($o)] ?? '',
                (!empty($o['format']) && !$selbst) ? livento_cc_format_label($o['format']) : '',
            ));
            $meta = array();
            if (livento_cc_is_on_request($o)) {
                $meta[] = $selbst ? 'Auf Anfrage' : 'Termin auf Anfrage';
            } elseif (!$selbst && !empty($o['start_datetime'])) {
                $meta[] = 'Start ' . livento_cc_fmt_date($o['start_datetime']);
            } elseif ($selbst) {
                $meta[] = 'Online im eigenen Tempo';
            }
            if (isset($o['public_price']) && $o['public_price'] !== null && $o['public_price'] !== '') {
                $meta[] = livento_cc_fmt_price($o['public_price'], !empty($o['is_vat_exempt']));
            }
            $karten[] = array(implode(' · ', $kopf), (string) $o['title'], implode(' · ', $meta),
                              livento_cc_detail_url($o['slug']), 'Zum Kurs →');
        } elseif (strpos($z, 't:') === 0) {
            if ($bereiche === null) {
                $bereiche = livento_cc_ticket_bereiche();
            }
            $b = $bereiche[substr($z, 2)] ?? null;
            if (!$b) {
                continue;
            }
            $karten[] = array('CampusTicket · Online-Kurse', $b['label'],
                              $b['count'] . ' Online-Kurse mit Zertifikat für dein ganzes Team', $b['url'], 'Kurse ansehen →');
        }
    }
    if (empty($karten)) {
        return '';
    }
    static $css = false;
    $out = '';
    if (!$css) {
        $css = true;
        $out .= '<style>'
              . '.lvk-bk{--g:#004D33;margin:40px 0 0;padding:28px 26px;background:#fdf4ef;border-radius:20px}'
              . '.lvk-bk-eyebrow{margin:0 0 4px!important;font-weight:700;font-size:13px;letter-spacing:.06em;text-transform:uppercase;color:#5C8A30}'
              . '.lvk-bk-h{margin:0 0 16px!important;font-family:"Qurova","Figtree",sans-serif;font-weight:600;font-size:26px;line-height:1.2;color:var(--g)!important}'
              . '.lvk-bk-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px}'
              . '.lvk-bk a.lvk-bk-card{display:flex;flex-direction:column;background:#fff;border:1px solid #eef0ea;border-radius:16px;padding:18px;text-decoration:none!important;color:inherit!important;transition:transform .2s,box-shadow .2s}'
              . '.lvk-bk a.lvk-bk-card:hover{transform:translateY(-3px);box-shadow:0 12px 26px rgba(0,77,51,.10)}'
              . '.lvk-bk-kopf{font-size:12px;font-weight:700;color:#6b7a72;text-transform:uppercase;letter-spacing:.04em}'
              . '.lvk-bk-t{margin:6px 0;font-family:"Qurova","Figtree",sans-serif;font-weight:600;font-size:17px;line-height:1.3;color:var(--g)}'
              . '.lvk-bk-m{font-size:14px;color:#475569;margin-bottom:14px}'
              . '.lvk-bk-btn{margin-top:auto;align-self:flex-start;background:var(--g);color:#fff!important;font-family:"Qurova","Figtree",sans-serif;font-weight:600;font-size:14px;padding:9px 16px;border-radius:10px}'
              . '</style>';
    }
    $out .= '<aside class="lvk-bk" aria-label="Passende Weiterbildungen"><p class="lvk-bk-eyebrow">Passend zum Beitrag</p>'
          . '<h2 class="lvk-bk-h">Passende Weiterbildungen</h2><div class="lvk-bk-grid">';
    foreach ($karten as $k) {
        $out .= '<a class="lvk-bk-card" href="' . esc_url($k[3]) . '">'
              . ($k[0] !== '' ? '<span class="lvk-bk-kopf">' . esc_html($k[0]) . '</span>' : '')
              . '<span class="lvk-bk-t">' . esc_html($k[1]) . '</span>'
              . ($k[2] !== '' ? '<span class="lvk-bk-m">' . esc_html($k[2]) . '</span>' : '')
              . '<span class="lvk-bk-btn">' . esc_html($k[4]) . '</span></a>';
    }
    return $out . '</div></aside>';
}

/**
 * Kasten unter jedem zugeordneten Beitrag. Nur im Hauptinhalt der Einzelansicht — nicht in Feeds,
 * REST, Auszuegen (wp_trim_excerpt laeuft ueber the_content und landete sonst in Meta-Beschreibungen)
 * oder anderen Beitraegen auf derselben Seite.
 */
add_filter('the_content', function ($content) {
    if (is_admin() || is_feed() || !is_singular('post') || doing_filter('get_the_excerpt')
        || (defined('REST_REQUEST') && REST_REQUEST)) {
        return $content;
    }
    $id = get_the_ID();
    if (!$id || (int) $id !== (int) get_queried_object_id() || strpos($content, 'class="lvk-bk"') !== false) {
        return $content;
    }
    return $content . livento_cc_beitrag_kurse_html($id);
}, 20);

/** Box „Passende Kurse (Livento)“ im Beitrags-Editor. */
add_action('add_meta_boxes', function () {
    add_meta_box('livento_cc_ziele', 'Passende Kurse (Livento)', 'livento_cc_fb_metabox', 'post', 'side', 'default');
});

function livento_cc_fb_metabox($post) {
    wp_nonce_field('livento_cc_ziele_speichern', 'livento_cc_ziele_nonce');
    $gesetzt  = array_unique((array) get_post_meta($post->ID, '_livento_cc_ziel', false));
    $optionen = array();
    foreach ((array) livento_cc_get_offerings() as $o) {
        $k = 'k:' . livento_cc_title_key($o['title'] ?? '');
        if ($k !== 'k:' && !isset($optionen[$k])) {
            $optionen[$k] = trim((string) $o['title']);
        }
    }
    uasort($optionen, function ($a, $b) {
        return strcasecmp(remove_accents($a), remove_accents($b));
    });
    foreach (livento_cc_ticket_bereiche() as $key => $b) {
        $optionen['t:' . $key] = 'CampusTicket: ' . $b['label'] . ' (' . $b['count'] . ')';
    }
    foreach ($gesetzt as $z) {
        if (!isset($optionen[$z])) {
            $optionen[$z] = substr((string) $z, 2) . ' (derzeit nicht im Katalog)';
        }
    }
    // Gesetzte zuerst
    $sortiert = array();
    foreach ($optionen as $v => $l) {
        if (in_array($v, $gesetzt, true)) {
            $sortiert[$v] = $l;
        }
    }
    $sortiert += $optionen;

    echo '<p style="margin:0 0 8px">Erscheinen unter dem Beitrag als „Passende Weiterbildungen“. Auf der Kursseite steht der Beitrag dann unter „Fachbeiträge zum Thema“.</p>';
    echo '<input type="search" placeholder="Kurs filtern …" style="width:100%;margin-bottom:6px"'
       . ' oninput="var q=this.value.toLowerCase();this.nextElementSibling.querySelectorAll(\'label\').forEach(function(l){l.style.display=l.textContent.toLowerCase().indexOf(q)>-1?\'\':\'none\'})">';
    echo '<div style="max-height:280px;overflow:auto;border:1px solid #dcdcde;padding:6px 8px;background:#fff">';
    foreach ($sortiert as $v => $l) {
        echo '<label style="display:block;margin:3px 0"><input type="checkbox" name="livento_cc_ziele[]" value="' . esc_attr($v) . '"'
           . checked(in_array($v, $gesetzt, true), true, false) . '> ' . esc_html($l) . '</label>';
    }
    echo '</div>';
}

add_action('save_post_post', function ($post_id) {
    $nonce = isset($_POST['livento_cc_ziele_nonce']) ? sanitize_text_field(wp_unslash($_POST['livento_cc_ziele_nonce'])) : '';
    if ($nonce === '' || !wp_verify_nonce($nonce, 'livento_cc_ziele_speichern')) {
        return;
    }
    if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id) || !current_user_can('edit_post', $post_id)) {
        return;
    }
    $neu = array();
    foreach ((array) ($_POST['livento_cc_ziele'] ?? array()) as $z) {
        $z = sanitize_text_field(wp_unslash((string) $z));
        if (preg_match('/^(k|t):.+/u', $z)) {
            $neu[$z] = true;
        }
    }
    delete_post_meta($post_id, '_livento_cc_ziel');
    foreach (array_keys($neu) as $z) {
        add_post_meta($post_id, '_livento_cc_ziel', $z);
    }
});

/* ============================================================
 * v1.66.0: Formvergleich „Welche Form passt zu dir?“
 * Gruppen „gleiches Thema, andere Form“ (Live tags/abends, Selbstlernkurs, Präsenz …) als Listen
 * von Kurstiteln. Bewusst NICHT ueber die Kursfamilien in Campus Connect: Die fassen Durchgaenge
 * desselben Lehrgangs zusammen (gleiche Module), nicht verschiedene Formen eines Themas.
 * Pflege: Livento Katalog → Einstellungen → Formvarianten (eine Gruppe je Zeile, Titel mit „|“).
 * ============================================================ */

/** Vorgabe, vom Nutzer am 20.11.2026 bestaetigt. */
function livento_cc_formvarianten_vorgabe() {
    return array(
        array('Auffrischungs-Pflichtfortbildung Betreuungskräfte (ambulant & stationär) – Tageskurs',
              'Auffrischungs-Pflichtfortbildung Betreuungskräfte (ambulant & stationär) – Abendkurs',
              'Auffrischungs-Pflichtfortbildung Betreuungskräfte',
              'Jährliche Pflichtfortbildung für Betreuungskräfte'),
        array('Praxisanleiter – jährliche Pflichtfortbildung (Abendkurs)',
              'Jährliche Pflichtfortbildung „Praxisanleitung aktuell“'),
        array('Medizinproduktebeauftragte:r',
              'Medizinproduktebeauftragte:r (Abendkurs)'),
        array('Pain Nursing (Schmerzmanagement)',
              'Pain Nursing (Schmerzmanagement) Abendkurs',
              'Professionelles Schmerzmanagement in der Pflege'),
        array('Deeskalation & Gewaltprävention in der Pflege',
              'Gewaltprävention, Eigenschutz & Führungsverantwortung im ambulanten Dienst',
              'Sicher handeln bei aggressiven Klient:innen – Gewaltprävention im ambulanten Setting'),
    );
}

/** Gespeicherte Gruppen (auch eine leere Liste) gehen vor; ungespeichert gilt die Vorgabe. */
function livento_cc_formvarianten() {
    $g = get_option('livento_cc_formvarianten', null);
    return is_array($g) ? $g : livento_cc_formvarianten_vorgabe();
}

/** Textarea-Inhalt → Gruppen (je Zeile eine Gruppe, Titel mit „|“ getrennt, mindestens zwei). */
function livento_cc_formvarianten_parsen($text) {
    $gruppen = array();
    foreach (preg_split('/\r\n|\r|\n/', (string) $text) as $zeile) {
        $titel = array_values(array_filter(array_map('trim', explode('|', $zeile)), 'strlen'));
        if (count($titel) >= 2) {
            $gruppen[] = $titel;
        }
    }
    return $gruppen;
}

function livento_cc_formvergleich_html($o) {
    $key    = livento_cc_title_key($o['title'] ?? '');
    $gruppe = null;
    foreach (livento_cc_formvarianten() as $g) {
        foreach ((array) $g as $t) {
            if (livento_cc_title_key($t) === $key) {
                $gruppe = $g;
                break 2;
            }
        }
    }
    if ($gruppe === null) {
        return '';
    }
    $varianten = array();
    foreach ($gruppe as $t) {
        $k = livento_cc_title_key($t);
        if (isset($varianten[$k])) {
            continue;
        }
        $v = $k === $key ? $o : livento_cc_offering_by_title_key($k);
        if ($v) {
            $varianten[$k] = $v;
        }
    }
    // Gleiche Form unter verschiedenen Titeln (z. B. ein Durchgang ohne „– Tageskurs“ im Titel)
    // nur einmal: die aktuelle Seite hat Vorrang, sonst der naechste Start.
    $je_form = array();
    foreach ($varianten as $k => $v) {
        $tm = (array) ($v['time_models'] ?? array());
        sort($tm);
        $form = livento_cc_effective_kind($v) . '|' . ($v['format'] ?? '') . '|' . implode(',', $tm) . '|'
              . (livento_cc_is_self_learning($v) ? 's' : '') . (livento_cc_is_on_request($v) ? 'a' : '');
        $start = !empty($v['start_datetime']) ? (string) $v['start_datetime'] : '9999';
        if (!isset($je_form[$form])
            || ($k === $key)
            || ($je_form[$form][0] !== $key && strcmp($start, $je_form[$form][2]) < 0)) {
            $je_form[$form] = array($k, $v, $start);
        }
    }
    $varianten = array();
    foreach ($je_form as $e) {
        $varianten[$e[0]] = $e[1];
    }
    if (count($varianten) < 2) {
        return ''; // die anderen Formen sind gerade nicht im Katalog
    }

    $kinds = livento_cc_kind_singular();
    $zm    = livento_cc_time_model_labels();
    $out   = '<div class="lvk-section lvk-formen"><h2>Welche Form passt zu dir?</h2>'
           . '<p class="lvk-formen-lead">Dieses Thema gibt es bei uns in ' . count($varianten) . ' Formen.</p>'
           . '<div class="lvk-formen-grid">';
    foreach ($varianten as $k => $v) {
        $hier   = $k === $key;
        $selbst = livento_cc_is_self_learning($v);
        $kopf   = array_filter(array(
            $kinds[livento_cc_effective_kind($v)] ?? '',
            $selbst ? 'Online im eigenen Tempo' : (!empty($v['format']) ? livento_cc_format_label($v['format']) : ''),
        ));
        $zeit = array();
        foreach ((array) ($v['time_models'] ?? array()) as $t) {
            if (isset($zm[$t])) {
                $zeit[] = $zm[$t];
            }
        }
        if (!empty($zeit)) {
            $kopf[] = implode(' & ', $zeit);
        }

        $zeilen = array();
        $umfang = livento_cc_umfang_label($v);
        if ($umfang !== '') {
            $zeilen['Umfang'] = $umfang;
        }
        if (livento_cc_is_on_request($v)) {
            $zeilen['Start'] = 'auf Anfrage';
        } elseif ($selbst) {
            $zeilen['Start'] = 'jederzeit';
        } elseif (!empty($v['start_datetime'])) {
            $zeilen['Start'] = livento_cc_fmt_date($v['start_datetime']);
        }
        $zeilen['Preis'] = (isset($v['public_price']) && $v['public_price'] !== null && $v['public_price'] !== '')
            ? livento_cc_fmt_price($v['public_price'], !empty($v['is_vat_exempt'])) : 'auf Anfrage';
        $abschluss = livento_cc_abschluss_label($v);
        if ($abschluss !== '') {
            $zeilen['Abschluss'] = $abschluss;
        }

        $innen = '<span class="lvk-form-kopf">' . esc_html(implode(' · ', $kopf)) . '</span>'
               . '<span class="lvk-form-t">' . esc_html(trim((string) $v['title'])) . '</span><dl>';
        foreach ($zeilen as $l => $w) {
            $innen .= '<dt>' . esc_html($l) . '</dt><dd>' . esc_html($w) . '</dd>';
        }
        $innen .= '</dl>';
        if ($hier) {
            $out .= '<div class="lvk-form lvk-form--hier">' . $innen . '<span class="lvk-form-hier">Du bist hier</span></div>';
        } else {
            $out .= '<a class="lvk-form" href="' . esc_url(livento_cc_detail_url($v['slug'])) . '">' . $innen
                  . '<span class="lvk-form-link">Zu dieser Form →</span></a>';
        }
    }
    return $out . '</div></div>';
}

/**
 * v1.52.0: Box „Naechster Termin" auf der Seite eines Anfragekurses. Nur wenn
 * public_inquiry_courses einen verknuepften kuenftigen Lehrgang meldet — dann
 * steht der Anfragekurs gerade nicht im Katalog, seine URL bleibt aber gueltig.
 */
function livento_cc_next_program_html($o) {
    if (!livento_cc_is_on_request($o) || empty($o['next_program']) || !is_array($o['next_program'])) {
        return '';
    }
    $np = $o['next_program'];
    $link = '';
    if (!empty($np['slug'])) {
        $link = livento_cc_detail_url($np['slug']);
    } elseif (!empty($np['checkout_url'])) {
        $link = $np['checkout_url'];
    }
    $out  = '<div class="lvk-next-program">';
    $out .= '<p class="lvk-next-program-head">Nächster Termin: ' . esc_html(livento_cc_fmt_date($np['start'] ?? '')) . '</p>';
    if ($link !== '') {
        $out .= '<a class="lvk-cta" href="' . esc_url($link) . '">Zum Termin – jetzt Platz sichern</a>';
    }
    $out .= '<p class="lvk-next-program-sub">Der Termin passt nicht? Frag unten einfach einen anderen an.</p>';
    return $out . '</div>';
}

/**
 * v1.52.0: Anfrageformular eines Anfragekurses. Geht an den WP-REST-Proxy
 * /wp-json/livento/v1/kursanfrage, der serverseitig an die Campus-Connect-Function
 * submit-course-inquiry weiterreicht (Muster Testanfrage, v1.43.0).
 */
function livento_cc_inquiry_section($o) {
    $datenschutz = livento_cc_privacy_url();
    $selbst      = livento_cc_is_self_learning($o); // v1.63.0: Selbstlernkurse haben keinen Termin
    ob_start(); ?>
    <section class="lvk-section lv-inq" id="kursanfrage">
        <?php if ($selbst) : ?>
        <h2>Kurs anfragen</h2>
        <p class="lv-inq__lead">Diesen Kurs gibt es auf Anfrage. Sag uns, für wen du ihn anfragst – wir melden uns bei dir.</p>
        <?php else : ?>
        <h2>Termin anfragen</h2>
        <p class="lv-inq__lead">Für diesen Kurs steht gerade kein fester Termin. Sag uns, wann es dir passt – wir melden uns bei dir.</p>
        <?php endif; ?>
        <form class="lv-inq-form" novalidate
              data-endpoint="<?php echo esc_url(rest_url('livento/v1/kursanfrage')); ?>"
              data-slug="<?php echo esc_attr((string) $o['slug']); ?>"
              data-title="<?php echo esc_attr((string) $o['title']); ?>">
            <div class="lv-inq-switch" role="radiogroup" aria-label="Anfrage für">
                <label><input type="radio" name="requester_type" value="person" checked> Für mich</label>
                <label><input type="radio" name="requester_type" value="organization"> Für meine Einrichtung</label>
            </div>
            <div class="lv-inq-grid">
                <label class="lv-inq-field lv-inq-org" hidden>
                    <span>Einrichtung *</span>
                    <input type="text" name="organization" autocomplete="organization">
                </label>
                <label class="lv-inq-field lv-inq-org" hidden>
                    <span>Teilnehmende (ca.)</span>
                    <input type="number" name="participant_count" min="1" step="1">
                </label>
                <label class="lv-inq-field">
                    <span>Vorname *</span>
                    <input type="text" name="first_name" autocomplete="given-name" required>
                </label>
                <label class="lv-inq-field">
                    <span>Nachname *</span>
                    <input type="text" name="last_name" autocomplete="family-name" required>
                </label>
                <label class="lv-inq-field">
                    <span>E-Mail *</span>
                    <input type="email" name="email" autocomplete="email" required>
                </label>
                <label class="lv-inq-field">
                    <span>Telefon (optional)</span>
                    <input type="tel" name="phone" autocomplete="tel">
                </label>
                <label class="lv-inq-field lv-inq-field--wide">
                    <span><?php echo $selbst ? 'Gewünschter Start (optional)' : 'Wunschzeitraum (optional)'; ?></span>
                    <input type="text" name="desired_period" placeholder="z. B. Frühjahr 2027">
                </label>
                <label class="lv-inq-field lv-inq-field--wide">
                    <span>Nachricht (optional)</span>
                    <textarea name="message" rows="3"></textarea>
                </label>
            </div>

            <?php // Honeypot — fuer Menschen unsichtbar. ?>
            <div class="lvk-hp" aria-hidden="true">
                <input type="text" name="hp_field" tabindex="-1" autocomplete="off">
            </div>

            <label class="lv-inq-consent">
                <input type="checkbox" name="funding_wanted" value="1">
                <span>Ich interessiere mich für eine Förderung.</span>
            </label>
            <label class="lv-inq-consent">
                <input type="checkbox" name="privacy_ack" value="1" required>
                <span>Ich habe die <a href="<?php echo esc_url($datenschutz); ?>" target="_blank" rel="noopener">Datenschutzhinweise</a> zur Kenntnis genommen. *</span>
            </label>

            <p class="lv-inq-err" hidden></p>
            <p class="lv-inq-ok" hidden><?php echo $selbst
                ? 'Danke, deine Anfrage ist da. Wir melden uns bei dir.'
                : 'Danke, deine Anfrage ist da. Wir melden uns, sobald es einen passenden Termin gibt – oder vorher, wenn wir Fragen haben.'; ?></p>
            <button type="submit" class="lvk-cta">Jetzt anfragen</button>
            <p class="lv-inq__small">* Pflichtfelder. Die Anfrage ist unverbindlich.</p>
        </form>
    </section>
    <?php
    return ob_get_clean() . livento_cc_inquiry_js();
}

/**
 * v1.52.0: Absenden des Anfrageformulars. Erfolg erst nach bestaetigtem Eingang —
 * sonst zaehlte die Conversion auch fuer Anfragen, die nie angekommen sind.
 */
function livento_cc_inquiry_js() {
    static $done = false;
    if ($done) { return ''; }
    $done = true;
    return <<<'JS'
<script>
(function(){
  var form=document.querySelector('.lv-inq-form:not(.lvk-ih-form)');
  if(!form) return;
  var err=form.querySelector('.lv-inq-err');
  var ok=form.querySelector('.lv-inq-ok');
  var btn=form.querySelector('button[type=submit]');
  var busy=false;
  var orgFields=form.querySelectorAll('.lv-inq-org');

  function type(){ var r=form.querySelector('[name=requester_type]:checked'); return r?r.value:'person'; }
  Array.prototype.forEach.call(form.querySelectorAll('[name=requester_type]'), function(r){
    r.addEventListener('change', function(){
      Array.prototype.forEach.call(orgFields, function(f){ f.hidden = type()!=='organization'; });
    });
  });

  function fail(msg){ if(err){ err.textContent=msg; err.hidden=false; } }

  form.addEventListener('submit', function(e){
    e.preventDefault();
    if(busy) return;
    if(err) err.hidden=true;

    var g=function(n){ var el=form.querySelector('[name="'+n+'"]'); return el?String(el.value||'').trim():''; };
    var checked=function(n){ var el=form.querySelector('[name="'+n+'"]'); return !!(el&&el.checked); };
    var isOrg=type()==='organization';

    if(!g('first_name')||!g('last_name')) return fail('Bitte Vor- und Nachnamen angeben.');
    if(!/.+@.+\..+/.test(g('email'))) return fail('Bitte eine gültige E-Mail-Adresse angeben.');
    if(isOrg && !g('organization')) return fail('Bitte den Namen der Einrichtung angeben.');
    if(!checked('privacy_ack')) return fail('Bitte die Kenntnisnahme der Datenschutzhinweise bestätigen.');

    var payload={
      slug:form.getAttribute('data-slug'), requester_type:type(),
      first_name:g('first_name'), last_name:g('last_name'), email:g('email'), phone:g('phone'),
      organization:isOrg?g('organization'):null,
      participant_count:isOrg&&g('participant_count')?Number(g('participant_count')):null,
      desired_period:g('desired_period'), message:g('message'),
      funding_wanted:checked('funding_wanted'),
      source_url:location.href, hp_field:g('hp_field'),
      privacy_ack:true,
      privacy_ack_text:'Ich habe die Datenschutzhinweise zur Kenntnis genommen.'
    };

    busy=true; if(btn){ btn.disabled=true; btn.textContent='Wird gesendet …'; }
    fetch(form.getAttribute('data-endpoint'),{
      method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)
    }).then(function(r){
      return r.json().catch(function(){ return {ok:r.ok}; });
    }).then(function(j){
      if(!j||j.ok!==true){ throw new Error((j&&j.message)||'Die Anfrage konnte nicht gesendet werden.'); }
      try{ window.dataLayer=window.dataLayer||[];
        window.dataLayer.push({event:'generate_lead',lead_type:'kursanfrage',lead_source:'kursseite',course_title:form.getAttribute('data-title')}); }catch(_e){}
      Array.prototype.forEach.call(form.querySelectorAll('input,textarea,button,.lv-inq-switch,.lv-inq-grid,.lv-inq-consent,.lv-inq__small'), function(el){ el.hidden=true; });
      if(ok) ok.hidden=false;
    }).catch(function(ex){
      busy=false; if(btn){ btn.disabled=false; btn.textContent='Jetzt anfragen'; }
      fail(ex && ex.message ? ex.message : 'Die Anfrage konnte nicht gesendet werden. Bitte später erneut versuchen.');
    });
  });
})();
</script>
JS;
}

/**
 * v1.52.0: REST-Proxy fuer die Kursanfrage — wie /trial: kein CORS, anon-Schluessel
 * bleibt serverseitig, Sperren hier, weil Campus Connect hinter dem Proxy nur die
 * IP des Hosters sieht.
 */
add_action('rest_api_init', function () {
    register_rest_route('livento/v1', '/kursanfrage', array(
        'methods'             => 'POST',
        'callback'            => 'livento_cc_rest_course_inquiry',
        'permission_callback' => '__return_true',
    ));
});
function livento_cc_rest_course_inquiry($req) {
    $p = $req->get_json_params();
    if (!is_array($p)) { $p = array(); }

    if (!empty($p['hp_field'])) {
        return new WP_REST_Response(array('ok' => true), 200);
    }

    $email = isset($p['email']) ? sanitize_email($p['email']) : '';
    if (!is_email($email)) {
        return new WP_REST_Response(array('ok' => false, 'message' => 'Bitte eine gültige E-Mail-Adresse angeben.'), 200);
    }
    if (empty($p['privacy_ack'])) {
        return new WP_REST_Response(array('ok' => false, 'message' => 'Bitte die Kenntnisnahme der Datenschutzhinweise bestätigen.'), 200);
    }
    $slug = isset($p['slug']) ? sanitize_title($p['slug']) : '';
    if ($slug === '') {
        return new WP_REST_Response(array('ok' => false, 'message' => 'Die Anfrage konnte nicht zugeordnet werden.'), 200);
    }

    // Sperre: 5 Anfragen je IP und Stunde, eine je E-Mail und Kurs und Tag — als
    // stiller Erfolg, eine Fehlermeldung verriete einem Bot nur, dass er erkannt wurde.
    $ip       = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
    $ip_key   = 'lvk_inq_ip_' . md5($ip);
    $mail_key = 'lvk_inq_mail_' . md5(strtolower($email) . '|' . $slug);
    if (get_transient($mail_key)) {
        return new WP_REST_Response(array('ok' => true), 200);
    }
    $hits = (int) get_transient($ip_key);
    if ($hits >= 5) {
        return new WP_REST_Response(array('ok' => true), 200);
    }
    set_transient($ip_key, $hits + 1, HOUR_IN_SECONDS);
    set_transient($mail_key, 1, DAY_IN_SECONDS);

    $key = livento_cc_anon_key();
    if ($key === '') {
        error_log('[livento] Kursanfrage: kein Supabase-Schluessel hinterlegt.');
        return new WP_REST_Response(array('ok' => false, 'message' => 'Die Anfrage konnte nicht gesendet werden. Bitte melde dich telefonisch.'), 200);
    }

    $is_org  = isset($p['requester_type']) && $p['requester_type'] === 'organization';
    $payload = array(
        'slug'              => $slug,
        'requester_type'    => $is_org ? 'organization' : 'person',
        'first_name'        => isset($p['first_name']) ? sanitize_text_field($p['first_name']) : '',
        'last_name'         => isset($p['last_name']) ? sanitize_text_field($p['last_name']) : '',
        'email'             => $email,
        'phone'             => isset($p['phone']) ? sanitize_text_field($p['phone']) : '',
        'organization'      => $is_org && isset($p['organization']) ? sanitize_text_field($p['organization']) : null,
        'participant_count' => $is_org && !empty($p['participant_count']) ? (int) $p['participant_count'] : null,
        'desired_period'    => isset($p['desired_period']) ? sanitize_text_field($p['desired_period']) : '',
        'funding_wanted'    => !empty($p['funding_wanted']),
        'message'           => isset($p['message']) ? sanitize_textarea_field($p['message']) : '',
        'source_url'        => isset($p['source_url']) ? esc_url_raw($p['source_url']) : '',
        'privacy_ack'       => true,
        'privacy_ack_text'  => isset($p['privacy_ack_text']) ? sanitize_text_field($p['privacy_ack_text']) : '',
    );

    $res = wp_remote_post(LIVENTO_CC_SUPABASE_URL . '/functions/v1/submit-course-inquiry', array(
        'timeout' => 15,
        'headers' => array(
            'Content-Type'        => 'application/json',
            'apikey'              => $key,
            'Authorization'       => 'Bearer ' . $key,
            'x-livento-client-ip' => $ip,
        ),
        'body'    => wp_json_encode($payload),
    ));

    if (is_wp_error($res)) {
        error_log('[livento] Kursanfrage fehlgeschlagen: ' . $res->get_error_message());
        delete_transient($mail_key);
        return new WP_REST_Response(array('ok' => false, 'message' => 'Die Anfrage konnte nicht gesendet werden. Bitte später erneut versuchen.'), 200);
    }
    $code = (int) wp_remote_retrieve_response_code($res);
    if ($code < 200 || $code >= 300) {
        error_log('[livento] Kursanfrage: Campus Connect antwortete HTTP ' . $code . ' — ' . substr((string) wp_remote_retrieve_body($res), 0, 300));
        delete_transient($mail_key);
        return new WP_REST_Response(array('ok' => false, 'message' => 'Die Anfrage konnte nicht gesendet werden. Bitte später erneut versuchen.'), 200);
    }

    return new WP_REST_Response(array('ok' => true), 200);
}

/* ============================================================
 * v1.67.0: Abschnitt „Für dein Team“ — Inhouse-Anfrage direkt auf der Kursdetailseite.
 * Termin-Kurse und Weiterbildungen (auch mit Bildungsgutschein): kurzes Formular, geht ueber
 * den WP-REST-Proxy /livento/v1/inhouse-anfrage an submit-inhouse-inquiry und landet in Campus
 * Connect unter Akquise → Inhouse-Anfragen, mit dem Kurs als „Bezug zu Kurs“.
 * Selbstlernkurse: Hinweis auf E-Learning fuer Einrichtungen, kein Formular.
 * Anfragekurse: kein Abschnitt — ihre Kursanfrage kennt schon „Für meine Einrichtung“.
 * ============================================================ */

function livento_cc_inhouse_html($o) {
    if (livento_cc_is_on_request($o)) {
        return '';
    }
    if (livento_cc_is_self_learning($o)) {
        return '<section class="lvk-section lvk-ih lvk-ih--online" id="inhouse"><h2>Für dein Team</h2>'
             . '<p class="lv-inq__lead">Du möchtest dein ganzes Team schulen? Für Einrichtungen gibt es E-Learning mit Zugängen für alle Mitarbeitenden.</p>'
             . '<a class="lvk-cta" href="' . esc_url(home_url('/e-learning/')) . '">E-Learning für Einrichtungen</a></section>';
    }
    $azav        = livento_cc_is_bildungsgutschein($o);
    $datenschutz = livento_cc_privacy_url();
    ob_start(); ?>
    <section class="lvk-section lvk-ih" id="inhouse">
        <h2>Für dein Team</h2>
        <p class="lv-inq__lead">Du möchtest mehrere Kolleginnen und Kollegen schulen? Diesen Kurs bringen wir als Inhouse-Schulung zu euch: vor Ort in eurer Einrichtung oder live online.</p>
        <ul class="lvk-ih-punkte">
            <li>Termin nach eurer Planung</li>
            <li>Inhalte abgestimmt auf eure Abläufe</li>
            <li>ein Angebot für das ganze Team</li>
            <?php if ($azav) : ?><li>Ob eine Förderung für eure Beschäftigten infrage kommt, etwa nach § 82 SGB III, prüfen wir mit dir.</li><?php endif; ?>
        </ul>
        <button type="button" class="lvk-cta lvk-ih-open" aria-expanded="false" aria-controls="lvk-ih-form">Inhouse-Angebot anfragen</button>
        <form id="lvk-ih-form" class="lv-inq-form lvk-ih-form" hidden novalidate
              data-endpoint="<?php echo esc_url(rest_url('livento/v1/inhouse-anfrage')); ?>"
              data-slug="<?php echo esc_attr((string) $o['slug']); ?>"
              data-title="<?php echo esc_attr((string) $o['title']); ?>">
            <div class="lv-inq-grid">
                <label class="lv-inq-field">
                    <span>Einrichtung *</span>
                    <input type="text" name="organization" autocomplete="organization" required>
                </label>
                <label class="lv-inq-field">
                    <span>Teilnehmende (ca.)</span>
                    <input type="number" name="participant_count" min="1" step="1">
                </label>
                <label class="lv-inq-field">
                    <span>Vorname *</span>
                    <input type="text" name="first_name" autocomplete="given-name" required>
                </label>
                <label class="lv-inq-field">
                    <span>Nachname *</span>
                    <input type="text" name="last_name" autocomplete="family-name" required>
                </label>
                <label class="lv-inq-field">
                    <span>E-Mail *</span>
                    <input type="email" name="email" autocomplete="email" required>
                </label>
                <label class="lv-inq-field">
                    <span>Telefon (optional)</span>
                    <input type="tel" name="phone" autocomplete="tel">
                </label>
                <label class="lv-inq-field">
                    <span>Durchführung</span>
                    <select name="durchfuehrung">
                        <option value="">noch offen</option>
                        <option value="vor_ort">vor Ort bei uns</option>
                        <option value="online">live online</option>
                    </select>
                </label>
                <label class="lv-inq-field">
                    <span>Wunschzeitraum (optional)</span>
                    <input type="text" name="desired_period" placeholder="z. B. Frühjahr 2027">
                </label>
                <label class="lv-inq-field lv-inq-field--wide">
                    <span>Nachricht (optional)</span>
                    <textarea name="message" rows="3"></textarea>
                </label>
            </div>

            <?php // Honeypot — fuer Menschen unsichtbar. ?>
            <div class="lvk-hp" aria-hidden="true">
                <input type="text" name="hp_field" tabindex="-1" autocomplete="off">
            </div>

            <?php if ($azav) : ?>
            <label class="lv-inq-consent">
                <input type="checkbox" name="funding_wanted" value="1">
                <span>Bitte prüft, ob eine Förderung infrage kommt.</span>
            </label>
            <?php endif; ?>
            <label class="lv-inq-consent">
                <input type="checkbox" name="privacy_ack" value="1" required>
                <span>Ich habe die <a href="<?php echo esc_url($datenschutz); ?>" target="_blank" rel="noopener">Datenschutzhinweise</a> zur Kenntnis genommen. *</span>
            </label>

            <p class="lv-inq-err" hidden></p>
            <p class="lv-inq-ok" hidden>Danke, eure Anfrage ist da. Wir melden uns mit einem unverbindlichen Angebot, eine Bestätigung kommt per E-Mail.</p>
            <button type="submit" class="lvk-cta">Anfrage senden</button>
            <p class="lv-inq__small">* Pflichtfelder. Die Anfrage ist unverbindlich.</p>
        </form>
    </section>
    <?php
    return ob_get_clean() . livento_cc_inhouse_js();
}

/** v1.67.0: Aufklappen und Absenden. Erfolg erst nach bestaetigtem Eingang (wie die Kursanfrage). */
function livento_cc_inhouse_js() {
    static $done = false;
    if ($done) { return ''; }
    $done = true;
    return <<<'JS'
<script>
(function(){
  var box=document.querySelector('.lvk-ih');
  var form=box&&box.querySelector('.lvk-ih-form');
  if(!form) return;
  var openBtn=box.querySelector('.lvk-ih-open');
  var err=form.querySelector('.lv-inq-err');
  var ok=form.querySelector('.lv-inq-ok');
  var btn=form.querySelector('button[type=submit]');
  var busy=false;

  function zeigen(fokus){
    form.hidden=false;
    if(openBtn){ openBtn.hidden=true; openBtn.setAttribute('aria-expanded','true'); }
    if(fokus){ var f=form.querySelector('[name=organization]'); if(f) f.focus(); }
  }
  if(openBtn) openBtn.addEventListener('click', function(){ zeigen(true); });
  if(location.hash==='#inhouse') zeigen(false);

  function fail(msg){ if(err){ err.textContent=msg; err.hidden=false; } }

  form.addEventListener('submit', function(e){
    e.preventDefault();
    if(busy) return;
    if(err) err.hidden=true;

    var g=function(n){ var el=form.querySelector('[name="'+n+'"]'); return el?String(el.value||'').trim():''; };
    var checked=function(n){ var el=form.querySelector('[name="'+n+'"]'); return !!(el&&el.checked); };

    if(!g('organization')) return fail('Bitte den Namen der Einrichtung angeben.');
    if(!g('first_name')||!g('last_name')) return fail('Bitte Vor- und Nachnamen angeben.');
    if(!/.+@.+\..+/.test(g('email'))) return fail('Bitte eine gültige E-Mail-Adresse angeben.');
    if(!checked('privacy_ack')) return fail('Bitte die Kenntnisnahme der Datenschutzhinweise bestätigen.');

    var payload={
      slug:form.getAttribute('data-slug'),
      organization:g('organization'),
      participant_count:g('participant_count')?Number(g('participant_count')):null,
      first_name:g('first_name'), last_name:g('last_name'), email:g('email'), phone:g('phone'),
      durchfuehrung:g('durchfuehrung'), desired_period:g('desired_period'), message:g('message'),
      funding_wanted:checked('funding_wanted'),
      hp_field:g('hp_field'), privacy_ack:true
    };

    busy=true; if(btn){ btn.disabled=true; btn.textContent='Wird gesendet …'; }
    fetch(form.getAttribute('data-endpoint'),{
      method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)
    }).then(function(r){
      return r.json().catch(function(){ return {ok:r.ok}; });
    }).then(function(j){
      if(!j||j.ok!==true){ throw new Error((j&&j.message)||'Die Anfrage konnte nicht gesendet werden.'); }
      try{ window.dataLayer=window.dataLayer||[];
        window.dataLayer.push({event:'generate_lead',lead_type:'inhouse',lead_source:'kursseite',course_title:form.getAttribute('data-title')}); }catch(_e){}
      Array.prototype.forEach.call(form.querySelectorAll('input,select,textarea,button,.lv-inq-grid,.lv-inq-consent,.lv-inq__small'), function(el){ el.hidden=true; });
      if(ok) ok.hidden=false;
    }).catch(function(ex){
      busy=false; if(btn){ btn.disabled=false; btn.textContent='Anfrage senden'; }
      fail(ex && ex.message ? ex.message : 'Die Anfrage konnte nicht gesendet werden. Bitte später erneut versuchen.');
    });
  });
})();
</script>
JS;
}

/**
 * v1.67.0: REST-Proxy fuer die Inhouse-Anfrage — wie /kursanfrage: Schluessel bleibt serverseitig,
 * Sperren hier. Den Kurs loest der Server ueber den Slug auf, der Titel aus dem Browser zaehlt nicht.
 */
add_action('rest_api_init', function () {
    register_rest_route('livento/v1', '/inhouse-anfrage', array(
        'methods'             => 'POST',
        'callback'            => 'livento_cc_rest_inhouse_inquiry',
        'permission_callback' => '__return_true',
    ));
});
function livento_cc_rest_inhouse_inquiry($req) {
    $p = $req->get_json_params();
    if (!is_array($p)) { $p = array(); }

    if (!empty($p['hp_field'])) {
        return new WP_REST_Response(array('ok' => true), 200);
    }

    $email   = isset($p['email']) ? sanitize_email($p['email']) : '';
    $company = isset($p['organization']) ? sanitize_text_field($p['organization']) : '';
    $first   = isset($p['first_name']) ? sanitize_text_field($p['first_name']) : '';
    $last    = isset($p['last_name']) ? sanitize_text_field($p['last_name']) : '';
    if ($company === '') {
        return new WP_REST_Response(array('ok' => false, 'message' => 'Bitte den Namen der Einrichtung angeben.'), 200);
    }
    if ($first === '' || $last === '') {
        return new WP_REST_Response(array('ok' => false, 'message' => 'Bitte Vor- und Nachnamen angeben.'), 200);
    }
    if (!is_email($email)) {
        return new WP_REST_Response(array('ok' => false, 'message' => 'Bitte eine gültige E-Mail-Adresse angeben.'), 200);
    }
    if (empty($p['privacy_ack'])) {
        return new WP_REST_Response(array('ok' => false, 'message' => 'Bitte die Kenntnisnahme der Datenschutzhinweise bestätigen.'), 200);
    }
    $o = livento_cc_get_offering(isset($p['slug']) ? (string) $p['slug'] : '');
    if (!$o || empty($o['title'])) {
        return new WP_REST_Response(array('ok' => false, 'message' => 'Die Anfrage konnte nicht zugeordnet werden.'), 200);
    }

    // Sperre wie bei der Kursanfrage: 5 je IP und Stunde, eine je E-Mail und Kurs und Tag, still.
    $ip       = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
    $ip_key   = 'lvk_ih_ip_' . md5($ip);
    $mail_key = 'lvk_ih_mail_' . md5(strtolower($email) . '|' . $o['slug']);
    if (get_transient($mail_key)) {
        return new WP_REST_Response(array('ok' => true), 200);
    }
    $hits = (int) get_transient($ip_key);
    if ($hits >= 5) {
        return new WP_REST_Response(array('ok' => true), 200);
    }
    set_transient($ip_key, $hits + 1, HOUR_IN_SECONDS);
    set_transient($mail_key, 1, DAY_IN_SECONDS);

    $key = livento_cc_anon_key();
    if ($key === '') {
        error_log('[livento] Inhouse-Anfrage: kein Supabase-Schluessel hinterlegt.');
        delete_transient($mail_key);
        return new WP_REST_Response(array('ok' => false, 'message' => 'Die Anfrage konnte nicht gesendet werden. Bitte melde dich telefonisch.'), 200);
    }

    // inhouse_inquiries kennt kein Feld fuer Durchfuehrung, Wunschzeitraum (als Text) und
    // Foerderwunsch — sie stehen deshalb vorn in der Nachricht, die Kursseite am Ende.
    $wege   = array('vor_ort' => 'vor Ort in unserer Einrichtung', 'online' => 'live online');
    $weg    = isset($p['durchfuehrung']) ? (string) $p['durchfuehrung'] : '';
    $zeit   = isset($p['desired_period']) ? mb_substr(sanitize_text_field($p['desired_period']), 0, 200) : '';
    $text   = isset($p['message']) ? mb_substr(sanitize_textarea_field($p['message']), 0, 3000) : '';
    $kopf   = array();
    if (!empty($o['course_number'])) {
        $kopf[] = 'Kurs-Nr.: ' . $o['course_number'];
    }
    if (isset($wege[$weg])) {
        $kopf[] = 'Durchführung: ' . $wege[$weg];
    }
    if ($zeit !== '') {
        $kopf[] = 'Wunschzeitraum: ' . $zeit;
    }
    if (!empty($p['funding_wanted']) && livento_cc_is_bildungsgutschein($o)) {
        $kopf[] = 'Förderung: bitte prüfen';
    }
    $teile = array();
    if (!empty($kopf)) {
        $teile[] = implode("\n", $kopf);
    }
    if ($text !== '') {
        $teile[] = $text;
    }
    $teile[] = 'Kursseite: ' . livento_cc_detail_url($o['slug']);

    $anzahl = isset($p['participant_count']) ? (int) $p['participant_count'] : 0;
    $payload = array(
        'company'                => $company,
        'first_name'             => $first,
        'last_name'              => $last,
        'email'                  => $email,
        'phone'                  => isset($p['phone']) && trim((string) $p['phone']) !== '' ? sanitize_text_field($p['phone']) : null,
        'participant_count'      => ($anzahl >= 1 && $anzahl <= 10000) ? $anzahl : null,
        'reference_course_title' => trim((string) $o['title']),
        'message'                => implode("\n\n", $teile),
    );

    $res = wp_remote_post(LIVENTO_CC_SUPABASE_URL . '/functions/v1/submit-inhouse-inquiry', array(
        'timeout' => 20, // die Function verschickt zwei Mails und gleicht mit GoHighLevel ab, bevor sie antwortet
        'headers' => array(
            'Content-Type'  => 'application/json',
            'apikey'        => $key,
            'Authorization' => 'Bearer ' . $key,
        ),
        'body'    => wp_json_encode($payload),
    ));

    if (is_wp_error($res)) {
        error_log('[livento] Inhouse-Anfrage fehlgeschlagen: ' . $res->get_error_message());
        delete_transient($mail_key);
        return new WP_REST_Response(array('ok' => false, 'message' => 'Die Anfrage konnte nicht gesendet werden. Bitte später erneut versuchen.'), 200);
    }
    $code = (int) wp_remote_retrieve_response_code($res);
    if ($code < 200 || $code >= 300) {
        error_log('[livento] Inhouse-Anfrage: Campus Connect antwortete HTTP ' . $code . ' — ' . substr((string) wp_remote_retrieve_body($res), 0, 300));
        delete_transient($mail_key);
        return new WP_REST_Response(array('ok' => false, 'message' => 'Die Anfrage konnte nicht gesendet werden. Bitte später erneut versuchen.'), 200);
    }

    return new WP_REST_Response(array('ok' => true), 200);
}

function livento_cc_render_modules($modules) {
    if (empty($modules)) {
        return '';
    }
    // Die View liefert `modules` als JSON-Array; PostgREST gibt es bereits dekodiert.
    if (is_string($modules)) {
        $modules = json_decode($modules, true);
    }
    if (!is_array($modules) || empty($modules)) {
        return '';
    }

    // Nur Module mit echtem Inhalt behalten (Beschreibung mit Text ODER nicht-leere
    // Lektionsliste). Verhindert eine leere "Aufbau & Module"-Sektion, die sonst nur
    // den (Kurs-)Titel wiederholt, wenn ein Modul ohne Inhalt gepflegt ist.
    $modules = array_filter($modules, function ($m) {
        if (!is_array($m)) {
            return false;
        }
        $has_desc = !empty($m['description']) && trim(wp_strip_all_tags($m['description'])) !== '';
        $has_lessons = !empty($m['lessons']) && is_array($m['lessons']) && count($m['lessons']) > 0;
        $has_goal = !empty($m['learning_goal']) && trim((string) $m['learning_goal']) !== ''; // v1.65.0
        return $has_desc || $has_lessons || $has_goal;
    });
    if (empty($modules)) {
        return '';
    }

    $out = '<div class="lvk-section"><h2>Aufbau & Module</h2>';
    $first = true;
    foreach ($modules as $m) {
        $title = isset($m['title']) ? $m['title'] : 'Modul';
        $umfang = '';
        if (!empty($m['total_hours'])) {
            $unit = (isset($m['hours_unit']) && $m['hours_unit'] === 'unterrichtsstunden') ? 'UE' : 'Std.';
            $umfang = ' <span class="lvk-mod-hours">(' . esc_html($m['total_hours'] . ' ' . $unit) . ')</span>';
        }
        // Erstes inhaltliches Modul standardmaessig geoeffnet.
        $open = $first ? ' open' : '';
        $first = false;
        $out .= '<details class="lvk-module"' . $open . '><summary>' . esc_html($title) . $umfang . '</summary>';
        // v1.65.0: Groblernziel aus Campus Connect (Lehrplan-Formulierung, bewusst unveraendert)
        $ziel = trim((string) ($m['learning_goal'] ?? ''));
        if ($ziel !== '') {
            $out .= '<p class="lvk-mod-ziel"><strong>Lernziel:</strong> ' . nl2br(esc_html($ziel)) . '</p>';
        }
        if (!empty($m['description'])) {
            $out .= livento_cc_richtext($m['description']);
        }
        if (!empty($m['lessons']) && is_array($m['lessons'])) {
            $out .= '<ul class="lvk-lessons">';
            foreach ($m['lessons'] as $lesson) {
                $out .= '<li>' . esc_html($lesson) . '</li>';
            }
            $out .= '</ul>';
        }
        $out .= '</details>';
    }
    $out .= '</div>';
    return $out;
}

/* ------------------------------------------------------------
 * 7c. Dozentinnen und Dozenten (Feature 181)
 * ------------------------------------------------------------ */

/** Ziel der Verlinkung „Alle Dozenten" — leer = es wird gar nicht verlinkt. */
function livento_cc_dozenten_url() {
    return trim((string) get_option('livento_cc_dozenten_url', ''));
}

/**
 * Teilt die Kurzvorstellung in einen immer sichtbaren Anfang und einen Rest.
 *
 * Bewusst OHNE Textdopplung: Der Anfang steht als Absatz, der Rest im <details>.
 * Ein „Teaser plus vollstaendiger Text" haette denselben Inhalt zweimal im HTML —
 * schlecht fuer die Bewertung der Seite und unnoetig lang.
 * Geschnitten wird an einem Satzende, sonst an einer Wortgrenze.
 */
function livento_cc_split_bio($text, $limit = 190) {
    $text = trim((string) $text);
    if ($text === '' || mb_strlen($text) <= $limit) {
        return array($text, '');
    }

    // Satzende im Zielbereich suchen (ab 40 % der Grenze, damit der Anfang traegt).
    $window = mb_substr($text, 0, $limit);
    $cut = 0;
    foreach (array('. ', '! ', '? ') as $mark) {
        $pos = mb_strrpos($window, $mark);
        if ($pos !== false && $pos > $limit * 0.4 && $pos + 1 > $cut) {
            $cut = $pos + 1;
        }
    }
    if ($cut === 0) {
        $pos = mb_strrpos($window, ' ');
        $cut = ($pos !== false && $pos > $limit * 0.4) ? $pos : $limit;
    }

    return array(trim(mb_substr($text, 0, $cut)), trim(mb_substr($text, $cut)));
}

/**
 * Eine Dozentenkarte.
 *
 * Zwei bewusst verschiedene Layouts: Mit Foto steht das runde Portrait oben und die
 * Karte ist mittig gesetzt. Ohne Foto wird daraus KEINE Karte mit leerem Bildbereich,
 * sondern ein linksbuendiges Textlayout mit farbiger Kante — in einem Portraitraster
 * ist eine Luecke sonst der offensichtliche Bruch.
 */
function livento_cc_render_instructor_card($i, $heading = 'h3') {
    $name  = trim(($i['first_name'] ?? '') . ' ' . ($i['last_name'] ?? ''));
    $role  = trim((string) ($i['public_role'] ?? ''));
    $photo = trim((string) ($i['photo_url'] ?? ''));
    $hx    = in_array($heading, array('h2', 'h3'), true) ? $heading : 'h3';
    list($lead, $rest) = livento_cc_split_bio($i['public_bio'] ?? '');

    // Das id-Attribut ist die sichtbare Entsprechung der Schema-@id: Das Fragment
    // in der @id zeigt damit auf ein real vorhandenes Element und nicht ins Leere.
    $anchor = 'dozent-' . sanitize_html_class((string) ($i['id'] ?? ''));

    $out = '<article id="' . esc_attr($anchor) . '" class="lvk-doz'
         . ($photo === '' ? ' lvk-doz--plain' : '') . '">';

    if ($photo !== '') {
        $out .= '<img class="lvk-doz-photo" src="' . esc_url($photo) . '" alt="' . esc_attr($name)
              . '" width="112" height="112" loading="lazy" decoding="async">';
    }

    $out .= '<' . $hx . ' class="lvk-doz-name">' . esc_html($name) . '</' . $hx . '>';
    if ($role !== '') {
        $out .= '<p class="lvk-doz-role">' . esc_html($role) . '</p>';
    }
    if ($lead !== '') {
        $out .= '<p class="lvk-doz-bio">' . esc_html($lead) . '</p>';
    }
    if ($rest !== '') {
        $out .= '<details class="lvk-doz-more"><summary>Mehr lesen</summary>'
              . '<p>' . esc_html($rest) . '</p></details>';
    }

    $quals = is_array($i['qualifications'] ?? null) ? $i['qualifications'] : array();
    if (!empty($quals)) {
        $out .= '<ul class="lvk-doz-tags">';
        foreach (array_slice($quals, 0, 6) as $q) {
            $out .= '<li>' . esc_html($q) . '</li>';
        }
        $out .= '</ul>';
    }

    $out .= '</article>';
    return $out;
}

/**
 * Der Anbieter als Schema-Knoten — mit stabiler @id.
 *
 * Bis v1.44.0 stand die Organisation an jeder Stelle als anonymes Objekt. Ohne @id
 * ist jede Nennung fuer eine Suchmaschine eine eigene, unverbundene Organisation.
 * Mit fester @id verschmelzen Kurs-provider und Dozenten-worksFor zu EINEM Knoten.
 */
function livento_cc_org_node($full = false) {
    $node = array('@id' => 'https://livento-bildung.de/#organization');
    if ($full) {
        $node['@type'] = 'EducationalOrganization';
        $node['name']  = LIVENTO_CC_PROVIDER;
        $node['url']   = 'https://livento-bildung.de';
    }
    return $node;
}

/**
 * Stabile Entitaets-Kennung einer Person.
 *
 * Bewusst die UUID aus Campus Connect und nicht der Name: Die Kennung muss eine
 * Namensaenderung ueberleben und darf bei Namensgleichheit nicht kollidieren.
 * Verankert wird sie auf der Dozentenseite, sofern eine hinterlegt ist — dort steht
 * das Profil sichtbar, und das Fragment zeigt auf die tatsaechliche Karte
 * (siehe id-Attribut in livento_cc_render_instructor_card).
 */
function livento_cc_person_id($i) {
    $base = livento_cc_dozenten_url();
    if ($base === '') {
        $base = home_url('/');
    }
    return rtrim($base, '#') . '#dozent-' . sanitize_html_class((string) ($i['id'] ?? ''));
}

/**
 * Person-Objekt fuer Schema.org aus einer Dozentenzeile.
 *
 * $full = false liefert eine schlanke Referenz fuer Kursseiten: Dort ist die Person
 * nicht das Hauptthema, und die @id genuegt, damit die Nennung dem vollstaendigen
 * Knoten auf der Dozentenseite zugeordnet wird. Name und Bild bleiben trotzdem
 * drin, damit die Auszeichnung auch fuer sich allein aussagefaehig ist.
 */
function livento_cc_instructor_schema($i, $full = true) {
    $person = array(
        '@type' => 'Person',
        '@id'   => livento_cc_person_id($i),
        'name'  => trim(($i['first_name'] ?? '') . ' ' . ($i['last_name'] ?? '')),
    );
    if (!empty($i['public_role'])) {
        $person['jobTitle'] = $i['public_role'];
    }
    if (!empty($i['photo_url'])) {
        $person['image'] = $i['photo_url'];
    }

    $target = livento_cc_dozenten_url();
    if ($target !== '') {
        $person['url'] = $target;
    }

    if ($full) {
        // Beschreibung und Fachgebiete nur dort, wo das Profil auch das Thema der
        // Seite ist. Auf fuenf Kursseiten denselben Langtext zu wiederholen bringt
        // keinen zusaetzlichen Aussagewert.
        if (!empty($i['public_bio'])) {
            $person['description'] = $i['public_bio'];
        }
        if (!empty($i['qualifications']) && is_array($i['qualifications'])) {
            $person['knowsAbout'] = array_values($i['qualifications']);
        }
        $person['worksFor'] = livento_cc_org_node(true);
    } else {
        $person['worksFor'] = livento_cc_org_node();
    }

    return $person;
}

/**
 * [livento_dozenten] — Raster aller freigegebenen Profile.
 *
 * Bewusst ohne eigene Ueberschrift und ohne feste URL: Der Shortcode laeuft auf
 * jeder Seite, auf die er gesetzt wird; Titel und Einleitung liefert die Seite.
 */
add_shortcode('livento_dozenten', function ($atts) {
    $list = livento_cc_get_instructors();

    if (empty($list)) {
        // Kein Platzhalter und keine Fehlermeldung: Solange niemand freigegeben ist,
        // soll die Seite lieber leer bleiben als halbfertig wirken.
        return '';
    }

    $out  = livento_cc_styles();
    $out .= '<div class="lvk lvk-doz-wrap"><div class="lvk-doz-grid">';
    foreach ($list as $i) {
        // h2, weil auf dieser Seite nur die H1 der WordPress-Seite darueber steht —
        // ein h3 wuerde eine Ebene ueberspringen.
        $out .= livento_cc_render_instructor_card($i, 'h2');
    }
    $out .= '</div></div>';

    $items = array();
    $pos = 1;
    foreach ($list as $i) {
        $items[] = array(
            '@type'    => 'ListItem',
            'position' => $pos++,
            'item'     => livento_cc_instructor_schema($i),
        );
    }
    $out .= '<script type="application/ld+json">'
          . wp_json_encode(array(
                '@context'        => 'https://schema.org',
                '@type'           => 'ItemList',
                'itemListOrder'   => 'https://schema.org/ItemListUnordered',
                'numberOfItems'   => count($items),
                'itemListElement' => $items,
            ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
          . '</script>';

    return $out;
});

/**
 * Abschnitt „Wer dich unterrichtet" auf der Kursdetailseite.
 *
 * Ueberschrift bewusst geschlechtsneutral und zahlunabhaengig — „Deine Dozentin"
 * waere geraten, „Dein Dozent / Deine Dozentin" umstaendlich.
 */
function livento_cc_render_offering_instructors($o) {
    $list = isset($o['instructors']) && is_array($o['instructors']) ? $o['instructors'] : array();
    if (empty($list)) {
        return '';
    }

    $out  = '<div class="lvk-section lvk-doz-section"><h2>Wer dich unterrichtet</h2>';
    $out .= '<div class="lvk-doz-grid lvk-doz-grid--inline">';
    foreach ($list as $i) {
        $out .= livento_cc_render_instructor_card($i);
    }
    $out .= '</div>';

    $target = livento_cc_dozenten_url();
    if ($target !== '') {
        $out .= '<p class="lvk-doz-all"><a href="' . esc_url($target) . '">Alle Dozentinnen und Dozenten</a></p>';
    }

    $out .= '</div>';
    return $out;
}

/* ============================================================
 * 8. Client-Filter (Progressive Enhancement)
 * ============================================================ */

function livento_cc_filter_js() {
    static $done = false;
    if ($done) {
        return '';
    }
    $done = true;
    // Vanilla JS, keine Abhaengigkeiten. Filtert + sortiert die server-gerenderten Karten.
    return <<<'JS'
<script>
(function(){
  var root = document.getElementById('lvk-catalog');
  if(!root) return;
  root.classList.add('lvk-js');
  var cards  = Array.prototype.slice.call(root.querySelectorAll('.lvk-card'));
  var sections = Array.prototype.slice.call(root.querySelectorAll('.lvk-kat-section'));
  var artBtns  = Array.prototype.slice.call(root.querySelectorAll('.lvk-art-btn'));
  var art = '';
  var search = root.querySelector('.lvk-search');
  var sortSel= root.querySelector('.lvk-sort');
  var priceSel= root.querySelector('.lvk-pricemax');
  var reset  = root.querySelector('.lvk-reset');
  var countEl= root.querySelector('.lvk-count');

  // v1.58.0: Karten fassen mehrere Termine zusammen → je-Termin-Werte als |a|b|-Listen.
  var DIMS  = ['type','format','level','audience','funding','recognition','methodology','topics','duration','zeitmodell','startmonth','availability','city'];
  var MULTI = {audience:1,funding:1,recognition:1,methodology:1,topics:1,zeitmodell:1,format:1,city:1,startmonth:1,availability:1};
  var TOGGLES = ['azav','vatexempt','free','pflicht'];
  var active = {}; DIMS.forEach(function(d){ active[d]=[]; });
  var toggles = {}; TOGGLES.forEach(function(t){ toggles[t]=false; });
  var term = '', priceMax = null;

  function vals(card,d){
    var raw = card.getAttribute('data-'+d) || '';
    if(MULTI[d]) return raw.split('|').filter(function(x){return x;});
    return raw ? [raw] : [];
  }
  function matches(card){
    for(var i=0;i<DIMS.length;i++){
      var d=DIMS[i], act=active[d];
      if(act.length){
        var cv=vals(card,d), hit=false;
        for(var j=0;j<act.length;j++){ if(cv.indexOf(act[j])>-1){hit=true;break;} }
        if(!hit) return false;
      }
    }
    for(var k=0;k<TOGGLES.length;k++){ var t=TOGGLES[k]; if(toggles[t] && card.getAttribute('data-'+t)!=='1') return false; }
    if(priceMax!==null){ var p=card.getAttribute('data-price'); if(p===''||p===null||parseFloat(p)>priceMax) return false; }
    if(term){ if((card.getAttribute('data-text')||'').indexOf(term)===-1) return false; }
    return true;
  }
  function num(card,a){ var v=card.getAttribute('data-'+a); return (v===''||v===null)?NaN:parseFloat(v); }
  function str(card,a){ return card.getAttribute('data-'+a)||''; }
  function sortCards(key){
    cards.slice().sort(function(a,b){
      switch(key){
        case 'newest':      return str(b,'published').localeCompare(str(a,'published'));
        case 'popular':     { var fa=num(a,'featured'),fb=num(b,'featured'); if(fa!==fb)return fa-fb; return num(b,'booked')-num(a,'booked'); }
        case 'rating':      { var ra=num(a,'rating'),rb=num(b,'rating'); ra=isNaN(ra)?-1:ra; rb=isNaN(rb)?-1:rb; return rb-ra; }
        case 'most_booked': return num(b,'booked')-num(a,'booked');
        case 'price_asc':   { var pa=num(a,'price'),pb=num(b,'price'); pa=isNaN(pa)?Infinity:pa; pb=isNaN(pb)?Infinity:pb; return pa-pb; }
        case 'price_desc':  { var qa=num(a,'price'),qb=num(b,'price'); qa=isNaN(qa)?-Infinity:qa; qb=isNaN(qb)?-Infinity:qb; return qb-qa; }
        default:            { var ka=function(c){ return str(c,'start')||(str(c,'type')==='on_request'?'zzzz':'9999'); }; return ka(a).localeCompare(ka(b)); }
      }
    }).forEach(function(c){ if(c.parentNode) c.parentNode.appendChild(c); }); // je Abschnitt sortieren
  }
  function apply(){
    var shown=0, termine=0, perArt={'':0};
    cards.forEach(function(c){
      var base=matches(c), k=c.getAttribute('data-kind')||'';
      if(base){ perArt['']++; if(k) perArt[k]=(perArt[k]||0)+1; }
      var ok = base && (art==='' || k===art);
      c.style.display=ok?'':'none';
      if(ok){ shown++; termine += parseInt(c.getAttribute('data-termine')||'1',10)||1; }
    });
    // v1.58.0: Abschnitte ohne Treffer ausblenden, Ueberschriften nur in „Alle“
    sections.forEach(function(sec){
      var n=0;
      Array.prototype.forEach.call(sec.querySelectorAll('.lvk-card'), function(c){ if(c.style.display!=='none') n++; });
      sec.style.display = n ? '' : 'none';
      var cnt=sec.querySelector('.lvk-kat-count'); if(cnt) cnt.textContent=n;
      var head=sec.querySelector('.lvk-kat-head'); if(head) head.style.display = art ? 'none' : '';
    });
    artBtns.forEach(function(b){
      var k=b.getAttribute('data-art')||'', on=(k===art);
      b.classList.toggle('is-active', on); b.setAttribute('aria-pressed', on?'true':'false');
      var cn=b.querySelector('.lvk-art-count'); if(cn) cn.textContent = perArt[k]||0;
    });
    var anyActive = term!=='' || priceMax!==null
      || DIMS.some(function(d){return active[d].length;})
      || TOGGLES.some(function(t){return toggles[t];});
    if(countEl) countEl.textContent = shown+(shown===1?' Kurs':' Kurse')+(termine>shown?' · '+termine+' Termine':'');
    if(reset) reset.hidden = !anyActive;
    // Treffer-Badge je Filtergruppe (zeigt aktive Auswahl auch bei eingeklappter Gruppe)
    DIMS.forEach(function(d){
      var det=root.querySelector('details.lvk-fgroup[data-group="'+d+'"]'); if(!det) return;
      var b=det.querySelector('.lvk-group-count'); if(!b) return;
      var n=active[d].length; b.textContent=n?n:''; b.classList.toggle('on', n>0);
    });
  }

  Array.prototype.forEach.call(root.querySelectorAll('.lvk-pill[data-dim]'), function(p){
    p.addEventListener('click', function(){
      var d=p.getAttribute('data-dim'), v=p.getAttribute('data-value'), arr=active[d]; if(!arr) return;
      var i=arr.indexOf(v);
      if(i>-1){ arr.splice(i,1); p.classList.remove('is-active'); } else { arr.push(v); p.classList.add('is-active'); }
      apply();
    });
  });
  Array.prototype.forEach.call(root.querySelectorAll('.lvk-toggle'), function(p){
    p.addEventListener('click', function(){
      var t=p.getAttribute('data-toggle'); toggles[t]=!toggles[t]; p.classList.toggle('is-active', toggles[t]); apply();
    });
  });
  artBtns.forEach(function(b){ b.addEventListener('click', function(){ art=b.getAttribute('data-art')||''; apply(); }); });
  if(search)  search.addEventListener('input',  function(){ term=search.value.trim().toLowerCase(); apply(); });
  if(priceSel)priceSel.addEventListener('change',function(){ var v=priceSel.value; priceMax=(v===''?null:parseFloat(v)); apply(); });
  if(sortSel) sortSel.addEventListener('change', function(){ sortCards(sortSel.value); });
  if(reset)   reset.addEventListener('click', function(){
    DIMS.forEach(function(d){ active[d]=[]; }); TOGGLES.forEach(function(t){ toggles[t]=false; });
    term=''; priceMax=null;
    if(search) search.value=''; if(priceSel) priceSel.value='';
    Array.prototype.forEach.call(root.querySelectorAll('.lvk-pill.is-active'), function(p){ p.classList.remove('is-active'); });
    apply();
  });

  // Deep-Link / Startseiten-Suche: ?q= sowie Facet-/Toggle-Parameter aus der URL uebernehmen.
  // Beispiele: /kurse/?q=demenz   /kurse/?format=online_live&funding=azav_bildungsgutschein
  try {
    var params = new URLSearchParams(window.location.search);
    var q = params.get('q');
    if(q){ term = q.trim().toLowerCase(); if(search) search.value = q; }
    DIMS.forEach(function(d){
      var raw = params.get(d); if(!raw) return;
      raw.split(',').forEach(function(v){ v=v.trim(); if(v && active[d].indexOf(v)===-1) active[d].push(v); });
    });
    TOGGLES.forEach(function(t){ if(params.get(t)==='1') toggles[t]=true; });
    var a = params.get('art');
    if(a && /^[a-z_]+$/.test(a) && root.querySelector('.lvk-art-btn[data-art="'+a+'"]')) art = a;
    Array.prototype.forEach.call(root.querySelectorAll('.lvk-pill[data-dim]'), function(p){
      var d=p.getAttribute('data-dim'); if(active[d] && active[d].indexOf(p.getAttribute('data-value'))>-1) p.classList.add('is-active');
    });
    Array.prototype.forEach.call(root.querySelectorAll('.lvk-toggle'), function(p){
      if(toggles[p.getAttribute('data-toggle')]) p.classList.add('is-active');
    });
    DIMS.forEach(function(d){ if(active[d].length){ var det=root.querySelector('details.lvk-fgroup[data-group="'+d+'"]'); if(det) det.open=true; } });
  } catch(e){}

  apply();
})();
</script>
JS;
}

/** JS fuer den Kursberater (SGD-Stil: Stepper, Interessen/Starttermin, Inline-Ergebnis). */
function livento_cc_berater_js() {
    static $done = false;
    if ($done) {
        return '';
    }
    $done = true;
    return <<<'JS'
<script>
(function(){
  var root = document.getElementById('lvk-berater');
  if(!root) return;
  var total = parseInt(root.getAttribute('data-total'),10) || 0;
  var limit = parseInt(root.getAttribute('data-limit'),10) || 12;
  var steps = root.querySelectorAll('.lvk-bx-step');
  var stps  = root.querySelectorAll('.lvk-bx-stp');
  var back  = root.querySelector('.lvk-bx-back'),
      next  = root.querySelector('.lvk-bx-next');
  var cards = Array.prototype.slice.call(root.querySelectorAll('.lvk-bx-results .lvk-card'));
  var countEl = root.querySelector('.lvk-bx-count');
  var cur = 0;

  // Interessen: Mehrfachauswahl
  Array.prototype.forEach.call(root.querySelectorAll('.lvk-bx-step[data-key="interests"] .lvk-bx-row'), function(b){
    b.addEventListener('click', function(){ b.classList.toggle('on'); });
  });
  // Einfachauswahl-Felder (z. B. Starttermin)
  Array.prototype.forEach.call(root.querySelectorAll('.lvk-bx-single'), function(b){
    b.addEventListener('click', function(){
      var f = b.getAttribute('data-field');
      Array.prototype.forEach.call(root.querySelectorAll('.lvk-bx-single[data-field="'+f+'"]'), function(x){ x.classList.remove('on'); });
      b.classList.add('on');
    });
  });

  function chosenTopics(){
    var set = {};
    Array.prototype.forEach.call(root.querySelectorAll('.lvk-bx-step[data-key="interests"] .lvk-bx-row.on'), function(b){
      (b.getAttribute('data-topics')||'').split(',').forEach(function(t){ t=t.trim(); if(t) set[t]=1; });
    });
    return Object.keys(set);
  }
  function applyResults(){
    var topics = chosenTopics(), shown = 0;
    cards.forEach(function(c){
      var ct = (c.getAttribute('data-topics')||'').split('|').filter(function(x){return x;});
      var match = (topics.length===0);
      if(!match){ for(var i=0;i<topics.length;i++){ if(ct.indexOf(topics[i])>-1){ match=true; break; } } }
      if(match && shown<limit){ c.style.display=''; shown++; } else { c.style.display='none'; }
    });
    if(countEl) countEl.textContent = shown + (shown===1 ? ' passender Kurs' : ' passende Kurse');
  }
  function show(i){
    cur = i;
    Array.prototype.forEach.call(steps, function(s,idx){ s.hidden = (idx!==i); });
    Array.prototype.forEach.call(stps, function(s,idx){
      s.classList.toggle('is-active', idx===i);
      s.classList.toggle('is-done', idx<i);
    });
    if(back) back.hidden = (i===0);
    if(next) next.hidden = (i===total-1);
    if(i===total-1) applyResults();
    try { root.scrollIntoView({behavior:'smooth', block:'start'}); } catch(e){}
  }
  if(next) next.addEventListener('click', function(){ if(cur<total-1) show(cur+1); });
  if(back) back.addEventListener('click', function(){ if(cur>0) show(cur-1); });
  show(0);
})();
</script>
JS;
}

/* ============================================================
 * 9. Styles (Livento-CI, namespaced .lvk-)
 * ============================================================ */

function livento_cc_styles() {
    static $done = false;
    if ($done) {
        return ''; // pro Request nur einmal ausgeben
    }
    $done = true;
    $css = '
.lvk{--lvk-green:#004D33;--lvk-lime:#5C8A30;--lvk-accent:#a4d07b;color:#222;line-height:1.6}
.lvk a{color:var(--lvk-green)}
/* Layout: Filter-Sidebar links + Kursliste rechts. Filter nur mit JS sichtbar. */
.lvk-layout{display:flex;gap:24px;align-items:flex-start}
.lvk-main{flex:1 1 auto;min-width:0}
.lvk-sidebar{display:none;flex:0 0 250px;background:#f7faf4;border:1px solid #e1ecd6;border-radius:10px;padding:12px 14px;position:sticky;top:16px}
.lvk-js .lvk-sidebar{display:block}
.lvk-sidebar-head{display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:10px}
.lvk-sidebar-title{font-weight:700;color:var(--lvk-green);font-size:1.05rem}
.lvk-main-bar{display:none;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:10px}
.lvk-js .lvk-main-bar{display:flex}
.lvk-main-bar .lvk-search{flex:1 1 220px;min-width:160px;padding:10px 12px;border:1px solid #cdd9c2;border-radius:8px;font-size:.95rem;box-sizing:border-box}
.lvk-sort,.lvk-pricemax{padding:9px 10px;border:1px solid #cdd9c2;border-radius:8px;font-size:.88rem;background:#fff;color:#2b3a2b;cursor:pointer}
.lvk-toggles{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:4px}
.lvk-toggle{font-weight:600}
.lvk-fgroup{border-top:1px solid #e1ecd6;padding:9px 0}
.lvk-fgroup>summary{display:flex;align-items:center;gap:6px;cursor:pointer;font-weight:600;color:var(--lvk-green);font-size:.9rem;list-style:none}
.lvk-fgroup>summary::-webkit-details-marker{display:none}
.lvk-fgroup-title{flex:1}
.lvk-fgroup>summary::after{content:"\25BE";opacity:.5;font-size:.8rem;transition:transform .15s}
.lvk-fgroup[open]>summary::after{transform:rotate(180deg)}
.lvk-group-count{display:none;background:var(--lvk-green);color:#fff;border-radius:99px;font-size:.7rem;min-width:18px;height:18px;line-height:18px;text-align:center;padding:0 5px}
.lvk-group-count.on{display:inline-block}
.lvk-fgroup .lvk-pills{margin-top:8px}
.lvk-pills{display:flex;flex-wrap:wrap;gap:6px}
.lvk-pill{background:#fff;border:1px solid #cdd9c2;color:#2b3a2b;border-radius:99px;padding:5px 11px;font-size:.8rem;cursor:pointer;line-height:1.25}
.lvk-pill:hover{border-color:var(--lvk-lime)}
.lvk-pill.is-active{background:var(--lvk-green);border-color:var(--lvk-green);color:#fff}
.lvk-pill-count{opacity:.5;font-size:.72rem}
.lvk-pill.is-active .lvk-pill-count{opacity:.85}
.lvk-reset{background:none;border:none;color:var(--lvk-lime);text-decoration:underline;cursor:pointer;font-size:.82rem;padding:0;white-space:nowrap}
.lvk-count{display:none;color:var(--lvk-lime);font-size:.85rem;margin:0 0 12px}
.lvk-js .lvk-count{display:block}
/* v1.58.0: Umschalter nach Angebotsart + Abschnitte */
.lvk-art{display:none;flex-wrap:wrap;gap:6px;margin:0 0 12px}
.lvk-js .lvk-art{display:flex}
.lvk-art-btn{flex:0 0 auto;white-space:nowrap;background:#fff;border:1px solid #cdd9c2;color:#2b3a2b;border-radius:99px;padding:7px 14px;font-size:.88rem;cursor:pointer;line-height:1.25}
.lvk-art-btn:hover{border-color:var(--lvk-lime)}
.lvk-art-btn.is-active{background:var(--lvk-green);border-color:var(--lvk-green);color:#fff}
.lvk-art-count{opacity:.65;font-size:.75rem;margin-left:3px}
.lvk-kat-section{margin:0 0 30px}
.lvk-kat-head{margin:0 0 4px}
.lvk-kat-title{margin:0;font-size:1.35rem;line-height:1.25;color:var(--lvk-green)}
.lvk-kat-count{font-size:.85rem;font-weight:400;color:var(--lvk-lime);margin-left:4px}
.lvk-kat-hint{margin:2px 0 0;color:#475569;font-size:.9rem}
.lvk-card-kind{margin:0 0 6px;font-size:.72rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:var(--lvk-green)}
.lvk-badge-pflicht{background:var(--lvk-accent);color:#123}
.lvk-card-facts{font-size:.85rem;color:#2b3a2b;margin:0}
.lvk-card-dates{font-size:.82rem;color:#475569;margin:0}
@media(max-width:860px){
  .lvk-layout{flex-direction:column}
  .lvk-sidebar{position:static;flex:1 1 auto;width:auto;box-sizing:border-box}
}
/* Karten */
.lvk-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px;margin:8px 0}
.lvk-card{border:1px solid #e6e6e6;border-left:4px solid var(--lvk-accent);border-radius:0 8px 8px 0;background:#fff;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 2px 4px rgba(0,0,0,.05)}
.lvk-card-img img{width:100%;height:160px;object-fit:cover;display:block}
/* KI-Kennzeichnung (v1.46.0). Plakette unten rechts im Bild, Petrol mit
   hellem Ring — ohne den Ring verschwindet Petrol auf gruenstichigen Motiven,
   und Pflegebilder sind das oft. */
.lvk-card-img{position:relative;display:block}
.lvk-ki-wrap{position:relative;display:inline-block;max-width:100%;line-height:0;margin:16px 0}
.lvk-ki-wrap .lvk-hero{margin:0}
.lvk-ki{position:absolute;right:.85em;bottom:.85em;z-index:2;box-sizing:border-box;display:inline-block;padding:.4em .9em;border-radius:999px;background:rgba(0,77,51,.95);border:1px solid rgba(255,255,255,.22);color:#fff;font-size:12px;font-weight:600;line-height:1.4;letter-spacing:.01em;white-space:nowrap;box-shadow:0 1px 5px rgba(0,0,0,.28);pointer-events:none;text-decoration:none}
.lvk-card-img .lvk-ki{font-size:11px;right:.7em;bottom:.7em}
@media print{.lvk-ki{background:transparent;border:1px solid #004D33;color:#004D33;box-shadow:none}}
.lvk-card-body{padding:16px;display:flex;flex-direction:column;gap:8px;flex:1}
.lvk-card-title{font-size:1.1rem;margin:0;color:var(--lvk-green)}
.lvk-card-title a{text-decoration:none}
.lvk-card-title a:hover{text-decoration:underline}
.lvk-card-desc{font-size:.9rem;margin:0;color:#444}
.lvk-card-meta{font-size:.85rem;color:var(--lvk-lime);margin:0}
.lvk-badges{display:flex;flex-wrap:wrap;gap:6px}
.lvk-badge{background:#e6f0ec;color:var(--lvk-green);padding:3px 10px;border-radius:99px;font-size:.75rem;font-weight:600}
/* Buttons — Textfarbe gegen Theme-Link-Override absichern (!important + hohe Spezifitaet) */
.lvk a.lvk-card-cta,.lvk a.lvk-cta{margin-top:auto;display:inline-block;text-align:center;background:var(--lvk-green)!important;color:#fff!important;text-decoration:none!important;font-weight:600;border-radius:6px}
.lvk a.lvk-card-cta{padding:10px 16px}
.lvk a.lvk-cta{padding:14px 32px;font-size:1.05rem}
.lvk a.lvk-card-cta:hover,.lvk a.lvk-cta:hover{background:#006644!important;color:#fff!important;text-decoration:none!important}
/* Eigenstaendiges Suchfeld (Startseite) */
.lvk-herosearch-wrap{max-width:620px;margin:0 auto}
.lvk-herosearch-title{font-size:1.1rem;color:var(--lvk-green);margin:0 0 10px;text-align:center}
.lvk-herosearch{display:flex;gap:8px}
.lvk-herosearch-input{flex:1;min-width:0;padding:13px 16px;border:1px solid #cdd9c2;border-radius:8px;font-size:1rem;box-sizing:border-box}
.lvk button.lvk-herosearch-btn{background:var(--lvk-green)!important;color:#fff!important;border:none;border-radius:8px;padding:13px 24px;font-weight:600;font-size:1rem;cursor:pointer;white-space:nowrap}
.lvk button.lvk-herosearch-btn:hover{background:#006644!important}
@media(max-width:480px){.lvk-herosearch{flex-direction:column}}
/* Detailseite */
.lvk-detail{max-width:760px;margin:0 auto}
.lvk-detail-title{color:var(--lvk-green);font-size:2rem;line-height:1.2;margin:.2em 0}
.lvk-coursenr{color:var(--lvk-lime);font-size:.9rem;margin:0 0 12px}
' . livento_cc_rating_css() . '
.lvk-hero{max-width:100%;height:auto;border-radius:8px;margin:16px 0}
.lvk-lead{font-size:1.1rem;color:#444}
.lvk-facts{background:#f5f5f5;border-radius:8px;padding:16px;margin:24px 0}
.lvk-facts div{margin:6px 0}
.lvk-facts dt{font-weight:600;display:inline}
.lvk-facts dd{display:inline;margin:0 12px 0 4px}
.lvk-audience{color:var(--lvk-lime)}
.lvk-section{margin:28px 0}
.lvk-section h2{color:var(--lvk-green);font-size:1.3rem;border-bottom:2px solid var(--lvk-accent);padding-bottom:6px}
.lvk-steps{list-style:none;margin:16px 0 0;padding:0;display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px}
.lvk-steps li{background:#fff;border:1px solid #eef0ea;border-radius:14px;padding:18px 16px 16px;margin:0}
.lvk-step-nr{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:50%;background:var(--lvk-green);color:#fff;font-weight:700;font-size:.95rem;margin-bottom:10px}
.lvk-step-t{display:block;font-weight:700;color:var(--lvk-green);margin-bottom:4px}
.lvk-step-x{display:block;font-size:.92rem;color:#444;line-height:1.5}
.lvk-abschluss-list{list-style:none;margin:12px 0 0;padding:0}
.lvk-abschluss-list li{position:relative;padding:6px 0 6px 28px;line-height:1.5;margin:0}
.lvk-abschluss-list li:before{content:"\2713";position:absolute;left:4px;top:6px;color:var(--lvk-lime);font-weight:700}
.lvk-abschluss-list a{color:var(--lvk-green)!important;font-weight:600}
.lvk-kontakt-card{display:flex;gap:18px;align-items:center;background:#fff;border:2px solid var(--lvk-accent);border-radius:18px;padding:20px 22px;margin-top:14px}
.lvk-kontakt-foto{width:96px;height:96px;border-radius:50%;object-fit:cover;flex:0 0 96px}
.lvk-kontakt-name{margin:0;font-weight:700;font-size:1.1rem;color:var(--lvk-green)}
.lvk-kontakt-rolle{margin:2px 0 0;color:#666;font-size:.9rem}
.lvk-kontakt-text{margin:8px 0 10px;color:#444}
.lvk-kontakt-links{display:flex;flex-wrap:wrap;gap:8px 18px;margin:0}
.lvk-kontakt-links a{color:var(--lvk-green)!important;font-weight:600;text-decoration:none}
.lvk-kontakt-links a:hover{text-decoration:underline}
@media(max-width:560px){.lvk-kontakt-card{flex-direction:column;align-items:flex-start}}
.lvk-beitraege{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;margin-top:14px}
.lvk a.lvk-beitrag{display:flex;flex-direction:column;background:#fff;border:1px solid #eef0ea;border-radius:14px;padding:16px;text-decoration:none!important;color:inherit!important;transition:transform .2s,box-shadow .2s}
.lvk a.lvk-beitrag:hover{transform:translateY(-3px);box-shadow:0 12px 26px rgba(0,77,51,.10)}
.lvk-beitrag-d{font-size:.8rem;color:#777}
.lvk-beitrag-t{font-weight:700;color:var(--lvk-green);margin:4px 0 6px;line-height:1.35}
.lvk-beitrag-x{font-size:.9rem;color:#555;line-height:1.5}
.lvk-beitrag-more{margin-top:auto;padding-top:8px;font-weight:700;color:var(--lvk-green);font-size:.9rem}
.lvk-formen-lead{margin:8px 0 0;color:#555}
.lvk-formen-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;margin-top:14px}
.lvk .lvk-form{display:flex;flex-direction:column;background:#fff;border:1px solid #eef0ea;border-radius:14px;padding:16px;text-decoration:none!important;color:inherit!important;transition:transform .2s,box-shadow .2s}
.lvk a.lvk-form:hover{transform:translateY(-3px);box-shadow:0 12px 26px rgba(0,77,51,.10)}
.lvk .lvk-form--hier{border:2px solid var(--lvk-accent);background:#f9fcf5;padding:15px}
.lvk-form-kopf{font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#6b7a72}
.lvk-form-t{font-weight:700;color:var(--lvk-green);margin:4px 0 10px;line-height:1.35}
.lvk-form dl{margin:0 0 12px;display:grid;grid-template-columns:auto 1fr;gap:4px 10px;font-size:.9rem}
.lvk-form dt{color:#777;margin:0}
.lvk-form dd{margin:0;color:#222}
.lvk-form-hier,.lvk-form-link{margin-top:auto;font-weight:700;font-size:.9rem;color:var(--lvk-green)}
.lvk-form-hier{color:var(--lvk-lime)}
.lvk-benefit{background:#f3f8ee;border-radius:8px;padding:4px 20px}
/* v1.70.0: „Das wirst du koennen" — Haken statt Punkt, damit der Block als Koennensliste
   gelesen wird und nicht als weitere Aufzaehlung im Text. Haken als Zeichen, nicht als
   eingebettetes SVG: diese CSS steht in einem einfach quotierten PHP-String, ein SVG mit
   Attributen in Hochkommas beendet ihn (am 23.11.2026 genau so passiert). */
.lvk-outcomes__list{list-style:none;margin:0;padding:0;display:grid;gap:8px}
.lvk-outcomes__list li{position:relative;padding-left:26px;line-height:1.45}
.lvk-outcomes__list li::before{content:"\2713";position:absolute;left:0;top:0;
  color:var(--lvk-green);font-weight:700;font-size:1.05em;line-height:1.45}
@media (min-width:640px){.lvk-outcomes__list{grid-template-columns:1fr 1fr;gap:8px 24px}}
.lvk-module{border:1px solid #e6e6e6;border-radius:8px;margin:10px 0;padding:0 14px}
.lvk-module summary{font-weight:600;color:var(--lvk-green);cursor:pointer;padding:12px 0}
.lvk-mod-hours{font-weight:400;color:var(--lvk-lime);font-size:.9rem}
.lvk-mod-ziel{margin:0 0 12px;padding:10px 14px;background:#f3f8ec;border-left:3px solid var(--lvk-accent);border-radius:0 8px 8px 0;font-size:.95rem;line-height:1.5}
.lvk-mod-ziel strong{color:var(--lvk-green)}
.lvk-faq-item{border:1px solid #e6e6e6;border-radius:8px;margin:10px 0;padding:0 14px}
.lvk-faq-item summary{font-weight:600;color:var(--lvk-green);cursor:pointer;padding:12px 0}
.lvk-faq-a{padding:0 0 12px}
/* Unterrichtszeiten & Termine (v3.192.0) */
.lvk-sch-sum{margin:4px 0;color:#444}
.lvk-sch-sum strong{color:var(--lvk-green)}
.lvk-sch-details{margin-top:12px}
.lvk-sch-details>summary{cursor:pointer;font-weight:600;color:var(--lvk-green)}
.lvk-sch-list{list-style:none;margin:12px 0 0;padding:0;max-height:340px;overflow:auto}
.lvk-sch-list li{display:flex;justify-content:space-between;gap:16px;padding:6px 0;border-bottom:1px solid #eef2f2;font-variant-numeric:tabular-nums}
.lvk-sch-time{white-space:nowrap;color:#46604f}
/* CRO-Bausteine Detailseite */
.lvk-cta-cluster{background:#f3f8ee;border:1px solid #e1ecd6;border-radius:12px;padding:16px 18px;margin:20px 0}
.lvk-cta-row{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin:4px 0}
.lvk a.lvk-cta-secondary{display:inline-block;padding:14px 24px;border-radius:6px;font-weight:600;text-decoration:none!important;border:1.5px solid var(--lvk-green);color:var(--lvk-green)!important;background:transparent!important}
.lvk a.lvk-cta-secondary:hover{background:#e6f0ec!important;color:var(--lvk-green)!important}
.lvk a.lvk-cta-secondary.lvk-on-dark{border-color:#fff;color:#fff!important}
.lvk a.lvk-cta-secondary.lvk-on-dark:hover{background:rgba(255,255,255,.12)!important;color:#fff!important}
.lvk-foerder-hint{background:#fff;border:1px dashed var(--lvk-accent);border-radius:8px;padding:9px 13px;font-size:.92rem;margin:0 0 12px}
.lvk-foerder-hint strong{color:var(--lvk-green)}
.lvk-foerder-hint a{font-weight:600;white-space:nowrap}
.lvk-trust{display:flex;flex-wrap:wrap;gap:6px 16px;margin-top:14px;font-size:.85rem;color:#46604f;font-weight:600}
.lvk-scarcity{font-size:.92rem;font-weight:600;color:var(--lvk-lime);margin:0 0 12px}
.lvk-scarcity-urgent,.lvk-scarcity-full{color:#b3261e}
.lvk-cta-block{background:var(--lvk-green);border-radius:14px;padding:24px;margin:28px 0;text-align:center}
.lvk-cta-block-head{color:#fff;font-weight:700;font-size:1.2rem;margin-bottom:6px}
.lvk-cta-block-sub{color:#e7efe6;font-size:.95rem;margin-bottom:16px}
.lvk-cta-block .lvk-cta-row{justify-content:center}
.lvk a.lvk-cta.lvk-cta-on-dark{background:var(--lvk-accent)!important;color:var(--lvk-green)!important}
.lvk a.lvk-cta.lvk-cta-on-dark:hover{background:#b6dd92!important;color:var(--lvk-green)!important}
/* Faktenbox „Auf einen Blick" + Zwei-Spalten-Detaillayout (v1.24.0) */
.lvk-detail{max-width:1080px}
.lvk-detail-grid{display:block}
.lvk-detail-main{min-width:0}
.lvk-factbox{--fb-green:#004D33;--fb-accent:#AAC42B;--fb-tint:rgba(170,196,43,.18);--fb-ink:#334155;--fb-soft:#f6f8f0;background:#fff;border-radius:16px;padding:22px 22px 24px;margin:18px 0 26px;font-family:"Inter",system-ui,-apple-system,"Segoe UI",sans-serif;color:var(--fb-ink)}
.lvk-fb-eyebrow{color:var(--fb-green);font-weight:700;font-size:16px;margin:0 0 4px}
.lvk-fb-title{font-family:"Qurova","Figtree",sans-serif;font-weight:600;color:var(--fb-green);font-size:1.35rem;line-height:1.25;margin:0 0 14px}
.lvk-fb-list{margin:0 0 18px;padding:0}
.lvk-fb-row{display:flex;align-items:flex-start;gap:12px;padding:9px 0;border-top:1px solid var(--fb-soft)}
.lvk-fb-row:first-child{border-top:0}
.lvk-fb-ic{flex:0 0 auto;display:inline-flex;align-items:center;justify-content:center;width:44px;height:44px;border-radius:13px;background:var(--fb-tint);color:var(--fb-green)}
.lvk-fb-rc{display:flex;flex-direction:column;min-width:0;padding-top:2px}
.lvk-fb-label{font-size:.78rem;font-weight:600;letter-spacing:.02em;color:#6b7b73;margin:0}
.lvk-fb-val{margin:1px 0 0;font-size:1rem;font-weight:600;color:var(--fb-green);line-height:1.35}
.lvk-fb-sub{display:block;margin-top:2px;font-size:.8rem;font-weight:500;color:#6b7b73;line-height:1.35}
.lvk-factbox .lvk-scarcity{margin:0 0 14px}
.lvk-fb-foerder{background:var(--fb-soft);border-radius:12px;padding:12px 14px;margin:0 0 14px}
.lvk-fb-foerder-txt{margin:0 0 6px;font-size:.88rem;color:var(--fb-ink);line-height:1.4}
.lvk-factbox a.lvk-fb-foerderlink{font-weight:700;color:var(--fb-green)!important;text-decoration:none!important;font-size:.9rem}
.lvk-factbox a.lvk-fb-foerderlink:hover{text-decoration:underline!important}
.lvk-fb-actions{display:flex;flex-direction:column;gap:10px;margin:2px 0 0}
.lvk-factbox a.lvk-cta.lvk-fb-cta{display:block;text-align:center;background:var(--fb-green)!important;color:#fff!important;border-radius:10px;padding:14px 20px;font-size:1.02rem}
.lvk-factbox a.lvk-cta.lvk-fb-cta:hover{background:#006644!important}
.lvk-factbox a.lvk-cta-secondary.lvk-fb-cta2{display:block;text-align:center;border-radius:10px;border:1.5px solid var(--fb-green);color:var(--fb-green)!important;padding:12px 20px;font-size:.96rem}
.lvk-factbox a.lvk-cta-secondary.lvk-fb-cta2:hover{background:var(--fb-soft)!important}
.lvk-factbox .lvk-trust{margin-top:16px;border-top:1px solid var(--fb-soft);padding-top:12px}
@media(min-width:900px){
  .lvk-detail-grid{display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:32px;align-items:start}
  .lvk-detail-main{grid-column:1;grid-row:1}
  .lvk-factbox{grid-column:2;grid-row:1;position:sticky;top:20px;margin:0}
}
/* Mobile Sticky-CTA — nur schmale Screens, native Seite (kein iframe) */
.lvk-sticky{display:none}
@media(max-width:768px){
  .lvk-sticky{display:flex;align-items:center;justify-content:space-between;gap:12px;position:fixed;left:0;right:0;bottom:0;z-index:9000;background:#fff;border-top:1px solid #e1ecd6;box-shadow:0 -4px 14px rgba(0,0,0,.08);padding:10px 14px}
  .lvk-sticky-price{font-weight:700;color:var(--lvk-green);font-size:.95rem;line-height:1.15}
  .lvk-sticky a.lvk-cta{flex:0 0 auto;padding:11px 18px;font-size:.95rem;white-space:nowrap}
  .lvk-detail{padding-bottom:76px}
}
/* Kursberater (SGD-Stil mit Stepper) */
.lvk-berater{max-width:860px;margin:0 auto;background:#fff;border:1px solid #e1ecd6;border-radius:12px;padding:26px 28px;box-shadow:0 2px 10px rgba(0,0,0,.04)}
.lvk-bx-title{color:var(--lvk-green);margin:0 0 4px;font-size:1.5rem}
.lvk-bx-intro{color:#555;margin:0 0 16px}
.lvk-bx-stepper{display:flex;list-style:none;padding:0;margin:8px 0 28px}
.lvk-bx-stp{flex:1;text-align:center;position:relative}
.lvk-bx-stp .lvk-bx-dot{display:block;width:18px;height:18px;border-radius:50%;border:2px solid #cdd9c2;background:#fff;margin:0 auto 8px;position:relative;z-index:1}
.lvk-bx-stp .lvk-bx-lbl{font-size:.82rem;color:#9aa6a0}
.lvk-bx-stp::before{content:"";position:absolute;top:9px;left:-50%;width:100%;height:2px;background:#e1ecd6;z-index:0}
.lvk-bx-stp:first-child::before{display:none}
.lvk-bx-stp.is-active .lvk-bx-dot,.lvk-bx-stp.is-done .lvk-bx-dot{border-color:var(--lvk-green);background:var(--lvk-green)}
.lvk-bx-stp.is-active .lvk-bx-lbl,.lvk-bx-stp.is-done .lvk-bx-lbl{color:var(--lvk-green);font-weight:600}
.lvk-bx-stp.is-done::before{background:var(--lvk-green)}
.lvk-bx-q{color:var(--lvk-green);font-size:1.3rem;margin:0 0 4px}
.lvk-bx-hint{color:#777;font-size:.88rem;margin:0 0 16px}
.lvk-bx-list{display:flex;flex-direction:column;gap:10px}
.lvk-bx-row{text-align:left;background:#fff;border:1px solid #dbe5d3;border-radius:8px;padding:14px 18px;font-size:1rem;color:#2b3a2b;cursor:pointer;line-height:1.35}
.lvk button.lvk-bx-row,.lvk button.lvk-bx-row:hover,.lvk button.lvk-bx-row:focus,.lvk button.lvk-bx-row:active,.lvk button.lvk-bx-row:focus-visible{background:#fff!important;color:#2b3a2b!important;outline:none!important}
.lvk button.lvk-bx-row:hover,.lvk button.lvk-bx-row:focus-visible{border-color:var(--lvk-lime)!important;box-shadow:0 0 0 2px rgba(0,77,51,.12)!important}
.lvk button.lvk-bx-row:focus:not(:focus-visible){box-shadow:none!important}
.lvk button.lvk-bx-row.on,.lvk button.lvk-bx-row.on:hover,.lvk button.lvk-bx-row.on:focus,.lvk button.lvk-bx-row.on:active{background:#f3f8ee!important;color:#1b3a2b!important;border-color:var(--lvk-green)!important;box-shadow:inset 0 0 0 1px var(--lvk-green)!important}
.lvk-hp{position:absolute!important;left:-9999px!important;width:1px;height:1px;overflow:hidden}
/* v1.52.0: Anfragekurse — Badge, Box „Naechster Termin", Anfrageformular */
.lvk .lvk-badge-anfrage{background:#fff7e6;color:#8a5a00;border-color:#f0d9a8}
/* v1.56.0: Bildungsgutschein — Badge, Abschnitt auf der Kursseite, Kursliste der Hub-Seite */
.lvk .lvk-badge-bg{background:var(--lvk-green);color:#fff;display:inline-flex;align-items:center;gap:5px}
.lvk-bg-ic{flex:none;vertical-align:-2px;margin-right:6px}
.lvk-badge-bg .lvk-bg-ic{margin-right:0}
.lvk-bg{background:#f2f7ee;border:1px solid #d9e8cc;border-radius:12px;padding:4px 20px 18px}
.lvk-bg h2{display:flex;align-items:center}
.lvk-bg-text ul{list-style:none;padding-left:0}
.lvk-bg-text li{margin:0 0 6px}
.lvk-bg-nr{background:#fff;border:1px dashed var(--lvk-accent);border-radius:8px;padding:10px 14px;margin:14px 0}
.lvk-bg-nr-val{font-family:ui-monospace,Consolas,monospace;font-weight:700;color:var(--lvk-green)}
.lvk-bg-nr-sub{display:block;font-size:.9rem;color:#475569;margin-top:2px}
.lvk-bg-links{display:flex;flex-wrap:wrap;gap:10px 18px;align-items:center;margin:4px 0 0}
.lvk a.lvk-bg-link{font-weight:700;color:var(--lvk-green)!important}
.lvf-bg-courses{margin:32px 0 8px}
.lvf-bg-courses h2{margin:0 0 6px}
.lvf-bg-courses .lvk-kl-sub{margin:0 0 16px}
.lvk-next-program{margin:0 0 1.5rem;padding:1rem 1.25rem;border-radius:12px;background:#f2f7ee;border:1px solid #d9e8cc}
.lvk-next-program-head{margin:0 0 .6rem;font-weight:700;color:#004D33}
.lvk-next-program-sub{margin:.6rem 0 0;font-size:.9rem;color:#5c6a70}
.lv-inq{scroll-margin-top:90px}
.lv-inq__lead{margin:0 0 1rem;color:#5c6a70}
.lv-inq-switch{display:flex;flex-wrap:wrap;gap:.5rem 1.25rem;margin:0 0 1rem;font-size:.95rem}
.lv-inq-switch label{display:flex;align-items:center;gap:.4rem;cursor:pointer}
.lv-inq-grid{display:grid;gap:.85rem;grid-template-columns:repeat(2,minmax(0,1fr))}
@media(max-width:640px){.lv-inq-grid{grid-template-columns:1fr}}
.lv-inq-field{display:flex;flex-direction:column;gap:.3rem;font-size:.88rem;color:#5c6a70}
.lv-inq-form [hidden]{display:none!important}
.lv-inq-field--wide{grid-column:1/-1}
.lv-inq-field input,.lv-inq-field textarea{padding:.6rem .7rem;border:1px solid #d5dcdf;border-radius:9px;font-size:.97rem;color:#20343c;background:#fff;font-family:inherit}
.lv-inq-field input:focus,.lv-inq-field textarea:focus{outline:2px solid #004D33;outline-offset:1px}
.lv-inq-consent{display:flex;gap:.6rem;align-items:flex-start;margin-top:.9rem;font-size:.9rem;color:#5c6a70}
.lv-inq-consent input{margin-top:.25rem}
.lv-inq-err{margin:.8rem 0 0;color:#b3261e;font-size:.9rem}
.lv-inq-ok{margin:.8rem 0 0;padding:.9rem 1rem;border-radius:10px;background:#f2f7ee;color:#004D33;font-weight:600}
/* v1.52.1: <button> statt <a> — die CI-Regeln oben greifen nur fuer a.lvk-cta, sonst
   faerbt das Theme den Absende-Button blau. Gleiche Werte wie .lvk a.lvk-cta. */
.lvk .lv-inq-form button.lvk-cta{display:inline-block;margin-top:1.1rem;padding:14px 32px;font-size:1.05rem;font-weight:600;font-family:inherit;line-height:1.3;text-align:center;background:var(--lvk-green)!important;color:#fff!important;border:0!important;border-radius:6px;box-shadow:none!important;cursor:pointer}
.lvk .lv-inq-form button.lvk-cta:hover,.lvk .lv-inq-form button.lvk-cta:focus{background:#006644!important;color:#fff!important}
.lvk .lv-inq-form button.lvk-cta:disabled{opacity:.7;cursor:wait}
.lv-inq-form input[type=checkbox],.lv-inq-form input[type=radio]{accent-color:#004D33}
.lv-inq__small{margin:.6rem 0 0;font-size:.82rem;color:#7a868b}
/* v1.67.0: Fuer dein Team (Inhouse) */
.lvk-ih{scroll-margin-top:90px;padding:1.25rem 1.4rem;border-radius:14px;background:#f2f7ee;border:1px solid #d9e8cc}
.lvk-ih h2{margin-top:0}
.lvk-ih [hidden]{display:none!important}
.lvk-ih-punkte{margin:0 0 1.1rem;padding:0;list-style:none;display:grid;gap:.45rem}
.lvk-ih-punkte li{position:relative;margin:0;padding-left:1.6rem}
.lvk-ih-punkte li:before{content:"\2713";position:absolute;left:0;top:0;color:#004D33;font-weight:700}
.lvk .lvk-ih button.lvk-ih-open{display:inline-block;padding:14px 32px;font-size:1.05rem;font-weight:600;font-family:inherit;line-height:1.3;text-align:center;background:var(--lvk-green)!important;color:#fff!important;border:0!important;border-radius:6px;box-shadow:none!important;cursor:pointer}
.lvk .lvk-ih button.lvk-ih-open:hover,.lvk .lvk-ih button.lvk-ih-open:focus{background:#006644!important;color:#fff!important}
.lvk-ih .lv-inq-field select{padding:.6rem .7rem;border:1px solid #d5dcdf;border-radius:9px;font-size:.97rem;color:#20343c;background:#fff;font-family:inherit}
.lvk-ih .lv-inq-field select:focus{outline:2px solid #004D33;outline-offset:1px}
.lvk-bx-form{margin:6px 0}
.lvk-lead-form{display:flex;flex-direction:column;gap:12px;max-width:520px;margin:6px 0}
.lvk-lead-row input{width:100%;padding:13px 16px;border:1px solid #cdd9c2;border-radius:10px;font-size:1rem;background:#fff;box-sizing:border-box;color:#2b3a2b}
.lvk-lead-row input:focus{outline:none;border-color:var(--lvk-green);box-shadow:0 0 0 2px rgba(0,77,51,.12)}
.lvk-lead-consent{display:flex;gap:8px;align-items:flex-start;font-size:.85rem;color:#555;line-height:1.4}
.lvk-lead-consent input{accent-color:var(--lvk-green);margin-top:3px;flex:0 0 auto}
.lvk-lead-err{color:#b3261e;font-size:.85rem;margin:0}
.lvk-lead-note{color:#8a8a8a;font-size:.78rem;line-height:1.4;margin:0}
.lvk-bx-results{margin-top:16px}
.lvk-bx-count{color:var(--lvk-lime);font-size:.9rem;margin:0 0 12px}
.lvk-bx-allcta{margin-top:20px;text-align:center}
.lvk-bx-nav{display:flex;align-items:center;gap:10px;margin-top:24px;padding-top:16px;border-top:1px solid #eee}
.lvk-bx-spacer{flex:1}
.lvk-bx-back{background:none;border:none;color:var(--lvk-lime);cursor:pointer;font-size:.92rem;padding:6px}
.lvk button.lvk-bx-next{background:var(--lvk-green)!important;color:#fff!important;border:none;border-radius:99px;padding:12px 28px;font-weight:600;font-size:.98rem;cursor:pointer}
.lvk button.lvk-bx-next:hover{background:#006644!important}
@media(max-width:560px){.lvk-bx-stp .lvk-bx-lbl{font-size:.7rem}}

/* --- Dozentinnen und Dozenten (v1.45.0, Feature 181) --- */
.lvk-doz-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:20px}
.lvk-doz{background:#fff;border:1px solid #e1ecd6;border-radius:12px;padding:22px 20px;text-align:center}
.lvk-doz-photo{width:112px;height:112px;border-radius:50%;object-fit:cover;object-position:50% 30%;display:block;margin:0 auto 14px;background:#f7faf4}
.lvk-doz-name{color:var(--lvk-green);font-size:1.12rem;line-height:1.3;margin:0 0 2px}
.lvk-doz-role{color:var(--lvk-lime);font-size:.88rem;line-height:1.4;margin:0 0 10px}
.lvk-doz-bio{font-size:.92rem;line-height:1.6;margin:0;color:#3a453a}
.lvk-doz-more{margin-top:6px}
.lvk-doz-more>summary{cursor:pointer;color:var(--lvk-green);font-size:.88rem;font-weight:600;list-style:none;display:inline-block}
.lvk-doz-more>summary::-webkit-details-marker{display:none}
.lvk-doz-more>summary::after{content:" \25BE"}
.lvk-doz-more[open]>summary::after{content:" \25B4"}
.lvk-doz-more>p{font-size:.92rem;line-height:1.6;margin:.5em 0 0;color:#3a453a;text-align:left}
.lvk-doz-tags{list-style:none;display:flex;flex-wrap:wrap;gap:6px;justify-content:center;margin:14px 0 0;padding:0}
.lvk-doz-tags li{background:#f2f7ec;color:#3f5b2c;border-radius:99px;padding:3px 11px;font-size:.76rem;line-height:1.5}
/* Ohne Foto bewusst ein eigenes Layout statt einer Karte mit leerem Bildbereich. */
.lvk-doz--plain{text-align:left;border-left:4px solid var(--lvk-lime)}
.lvk-doz--plain .lvk-doz-tags{justify-content:flex-start}
.lvk-doz--plain .lvk-doz-more>summary{display:block}
/* Auf der Kursdetailseite schmaler und ohne doppelten Rahmen im Abschnitt. */
.lvk-doz-grid--inline{grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px}
.lvk-doz-grid--inline .lvk-doz{padding:18px 16px}
.lvk-doz-all{margin:14px 0 0;font-size:.9rem}
@media(max-width:480px){.lvk-doz-grid,.lvk-doz-grid--inline{grid-template-columns:1fr}}
';
    return '<style id="lvk-styles">' . $css . '</style>';
}

/* ============================================================
 * 10. Admin-Dashboard (WordPress-Backend)
 *
 * Top-Level-Menue „Livento Katalog" mit Tabs. Bewusst registry-basiert
 * (livento_cc_shortcodes() + livento_cc_filter_groups()), damit kuenftige
 * Campus-Connect-Inhaltstypen einfach als weitere Shortcodes/Tabs andocken:
 *   - Neuen Shortcode in livento_cc_shortcodes() eintragen → erscheint im Tab.
 *   - Neuen Tab: Key in $tabs ergaenzen + eine livento_cc_admin_tab_<key>()-Funktion.
 * ============================================================ */

/** Registry der bereitgestellten Shortcodes (fuer die Admin-Anzeige). */
function livento_cc_shortcodes() {
    return array(
        array(
            'tag'     => 'livento_tarife',
            'title'   => 'Tarife (Landingpage)',
            'desc'    => 'Die drei Tariffamilien als Preis-Karten (PflichtTicket, KomplettTicket, RollenTicket) mit Angebotsrechner: Der Besucher gibt seine Beschäftigtenzahl ein, alle Preise aktualisieren sich sofort. Verlinkt auf die Detailseiten.',
            'example' => '[livento_tarife]',
            'atts'    => array(
                'heading'    => 'Überschrift über den Karten.',
                'subheading' => 'Zeile darunter.',
                'users'      => 'Startwert des Rechners (Default 20).',
                'base'       => 'Slug der Seite, unter der die Detailseiten liegen (Default: e-learning). Muss zum Eltern-Slug der Unterseiten passen.',
            ),
        ),
        array(
            'tag'     => 'livento_tarif',
            'title'   => 'Tarif-Detailseite',
            'desc'    => 'Eine Tariffamilie im Detail: alle Setting-Varianten (ambulant, stationär …) mit Preisblock, Kaufbutton und der vollständigen Kursliste — je Kurs Titel, Kursnummer, Umfang, Module, Lektionen und Zertifikat. Preise und Kursliste kommen live aus Campus Connect.',
            'example' => '[livento_tarif family="pflichtticket"]',
            'atts'    => array(
                'family' => 'Pflicht. Schlüssel der Tariffamilie: pflichtticket, komplettticket oder rollenticket (der Slug pflicht-ticket, komplett-ticket, rollen-ticket funktioniert ebenfalls).',
                'users'  => 'Startwert des Rechners (Default 20).',
            ),
        ),
        array(
            'tag'     => 'livento_kurse',
            'title'   => 'Kurskatalog',
            'desc'    => 'Vollständiger Katalog: Karten-Liste mit Filterleiste + Einzelkurs-Detailseiten unter /' . LIVENTO_CC_BASE . '/<slug>. Ohne Attribute = voller Katalog mit Filtern.',
            'example' => '[livento_kurse limit="6" sort="popular"]',
            'atts'    => array(
                'limit'   => 'Anzahl Karten (0 = alle). >0 erzeugt einen kuratierten Block ohne Filterleiste.',
                'sort'    => 'Sortierung: next_start (Default), newest, popular, rating, most_booked, price_asc, price_desc.',
                'filters' => 'Filterleiste erzwingen: „yes" oder „no" (Default: an, außer wenn limit gesetzt ist).',
                'topics'   => 'Auf Themen vorfiltern: Komma-Liste von Themen-Slugs (z. B. leitung-management,demenz). Leer = alle.',
                'audience' => 'Auf Zielgruppen vorfiltern: Komma-Liste von Zielgruppen-Slugs (z. B. fuehrungskraefte,praxisanleitende). Slugs siehe „Filter & Slugs". Leer = alle.',
            ),
        ),
        array(
            'tag'     => 'livento_kursliste',
            'title'   => 'Kursliste (Landingpage-Widget)',
            'desc'    => 'Benannte, kriterienbasierte Kursliste für Landingpages / Werbekampagnen. Anlegen & verwalten im Tab „Kurslisten"; dort den fertigen Shortcode kopieren. Füllt sich automatisch aus dem Katalog.',
            'example' => '[livento_kursliste id="pflichtfortbildungen"]',
            'atts'    => array(
                'id'          => 'ID einer im Tab „Kurslisten" gespeicherten Liste. Ohne id lässt sich die Liste auch direkt per Attribut definieren.',
                'audience'    => 'Zielgruppen-Slug(s), Komma-getrennt (z. B. betreuungskraefte_43b_53b).',
                'topics'      => 'Themen-Slug(s), Komma-getrennt.',
                'format'      => 'Format-Slug(s), Komma-getrennt (z. B. online_live,selbstlern).',
                'bildungsgutschein' => '1 = nur Kurse, die mit Bildungsgutschein förderfähig sind (AZAV-zugelassen).',
                'recognition' => 'Anerkennungs-Slug(s), Komma-getrennt (z. B. gesetzlich_anerkannt).',
                'art'         => 'Angebotsart: weiterbildung_abschluss, fortbildung, workshop oder selbstlernkurs.',
                'pflicht'     => '1 = nur Pflichtfortbildungen.',
                'q'           => 'Titel-Stichwort – nur Kurse mit diesem Text im Titel.',
                'limit'       => 'Max. Anzahl Karten (Default 6).',
                'sort'        => 'Sortierung: next_start, popular, newest, rating, most_booked, price_asc, price_desc.',
                'heading'     => 'Öffentliche Überschrift über dem Grid (optional).',
                'cta'         => '„Alle ansehen"-Button anzeigen: yes/no (Deep-Link in den gefilterten Katalog).',
                'columns'     => 'Feste Spaltenzahl 1–4 (0/leer = automatisch responsiv).',
            ),
        ),
        array(
            'tag'     => 'livento_kurse_suche',
            'title'   => 'Suchfeld',
            'desc'    => 'Eigenständiges Suchfeld (z. B. Startseite). Springt zur Katalogseite und filtert dort vor.',
            'example' => '[livento_kurse_suche title="Finden Sie Ihre Weiterbildung" placeholder="z. B. Demenz…" button="Suchen"]',
            'atts'    => array(
                'title'       => 'Optionale Überschrift über dem Feld',
                'placeholder' => 'Platzhaltertext (Default: „Kurs oder Thema suchen…")',
                'button'      => 'Button-Beschriftung (Default: „Kurse finden")',
            ),
        ),
        array(
            'tag'     => 'livento_kurse_berater',
            'title'   => 'Kursberater (SGD-Stil)',
            'desc'    => 'Mehrstufiger Assistent mit Stepper: Ihre Interessen → Ihr Starttermin → Ihre Angaben (GoHighLevel-Formular aus den Einstellungen) → Ihr Ergebnis (passende Kurse inline). Interessen-Aussagen → Themen-Mapping in der .php (livento_cc_berater_interests()).',
            'example' => '[livento_kurse_berater]',
            'atts'    => array(
                'starttermin'  => 'Starttermin-Schritt anzeigen: yes/no (Default yes).',
                'form'         => 'GoHighLevel-Formular-Schritt anzeigen: yes/no (Default yes; erscheint nur, wenn unter Einstellungen ein Embed hinterlegt ist).',
                'result_limit' => 'Max. Kurse im Ergebnis (Default 12).',
                'title'        => 'Überschrift (Default „Kursberatung für persönliche Weiterbildung")',
                'intro'        => 'Optionaler Einleitungstext',
            ),
        ),
        array(
            'tag'     => 'livento_themen',
            'title'   => 'Themen-Kacheln',
            'desc'    => 'Dynamische Themenfelder als Grid (gleiche Quelle wie der Filter „Thema"), Kurszahl + Link auf /' . LIVENTO_CC_BASE . '/?topics=<slug>.',
            'example' => '[livento_themen limit="6"]',
            'atts'    => array(
                'limit'  => 'Anzahl Themen (0 = alle).',
                'sort'   => '„count" (nach Kursanzahl, Default) oder „alpha" (alphabetisch).',
                'counts' => 'Kurszahl-Badge anzeigen: yes/no (Default yes).',
                'all'    => '„Alle Themen"-Kachel anhängen: yes/no (Default yes).',
                'min'    => 'Themen mit weniger als N Kursen ausblenden (Default 1).',
            ),
        ),
        array(
            'tag'     => 'livento_foerderungen',
            'title'   => 'Förderprogramme',
            'desc'    => 'Förderprogramme als Kachel-Grid mit Region-/Zielgruppen-Filter + eigene Detailseiten unter /' . LIVENTO_CC_FOERDER_BASE . '/<slug>. Pflege im Tab „Förderprogramme".',
            'example' => '[livento_foerderungen]',
            'atts'    => array(
                'audience' => 'Vorfiltern auf Zielgruppe: privat | unternehmen (leer = alle).',
                'region'   => 'Vorfiltern auf Region-Slug, z. B. bundesweit, sachsen (leer = alle).',
                'filter'   => 'Filterleiste anzeigen: yes/no (Default yes).',
            ),
        ),
        array(
            'tag'     => 'livento_dozenten',
            'title'   => 'Dozentinnen und Dozenten',
            'desc'    => 'Alle in Campus Connect freigegebenen Dozentenprofile als Raster: rundes Portrait, Name, Funktionsbezeichnung, Qualifikations-Stichworte und eine Kurzvorstellung, deren Rest sich aufklappt. Der Seiten-Slug ist frei waehlbar — es gibt keine Basis-Konstante und keine Detailseiten je Dozent. Status und Freigaben siehe Tab „Dozenten".',
            'example' => '[livento_dozenten]',
            'atts'    => array(),
        ),
        array(
            'tag'     => 'livento_foerder_berater',
            'title'   => 'Förderberater',
            'desc'    => 'Geführter Berater (SGD-Stil): Status → bedingte Qualifikation → Kontaktformular → passende Förderungen. Schema editierbar im Tab „Förderprogramme", Formular in den Einstellungen.',
            'example' => '[livento_foerder_berater]',
            'atts'    => array(
                'title' => 'Überschrift (Default „Förderberatung für Ihre Weiterbildung").',
                'intro' => 'Optionaler Einleitungstext.',
                'form'  => 'Formular-Schritt anzeigen: yes/no (Default yes, sofern ein Embed hinterlegt ist).',
            ),
        ),
    );
}

add_action('admin_menu', function () {
    add_menu_page(
        'Livento Kurskatalog',
        'Livento Katalog',
        'manage_options',
        'livento-kurskatalog',
        'livento_cc_admin_page',
        'dashicons-welcome-learn-more',
        58
    );
});

function livento_cc_admin_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    $notice = '';
    if (isset($_POST['livento_cc_flush']) && check_admin_referer('livento_cc_flush')) {
        livento_cc_flush_cache();
        $notice = 'Cache geleert — neue Cache-Version: ' . livento_cc_ver() . '.';
    }
    if (isset($_POST['livento_cc_save_settings']) && check_admin_referer('livento_cc_save_settings')) {
        update_option('livento_cc_anon_key', sanitize_text_field(wp_unslash($_POST['livento_cc_anon_key'] ?? '')));
        update_option('livento_cc_purge_secret', sanitize_text_field(wp_unslash($_POST['livento_cc_purge_secret'] ?? '')));
        update_option('livento_cc_topup_secret', sanitize_text_field(wp_unslash($_POST['livento_cc_topup_secret'] ?? '')));
        update_option('livento_cc_topup_product_id', absint($_POST['livento_cc_topup_product_id'] ?? 0));
        // Berater-Formular: roher Embed-Code (Admin-only, manage_options) — kein sanitize_text_field,
        // sonst wuerden iframe/script entfernt.
        update_option('livento_cc_berater_form', wp_unslash($_POST['livento_cc_berater_form'] ?? ''));
        update_option('livento_cc_foerder_form', wp_unslash($_POST['livento_cc_foerder_form'] ?? ''));
        update_option('livento_cc_berater_webhook', esc_url_raw(trim((string) wp_unslash($_POST['livento_cc_berater_webhook'] ?? ''))));
        update_option('livento_cc_foerder_webhook', esc_url_raw(trim((string) wp_unslash($_POST['livento_cc_foerder_webhook'] ?? ''))));
        update_option('livento_cc_beratung_url', esc_url_raw(trim((string) wp_unslash($_POST['livento_cc_beratung_url'] ?? ''))));
        update_option('livento_cc_dozenten_url', esc_url_raw(trim((string) wp_unslash($_POST['livento_cc_dozenten_url'] ?? ''))));
        // v1.62.0: Ansprechpartner der Kursdetailseiten
        update_option('livento_cc_kontakt', array(
            'aktiv'   => !empty($_POST['livento_cc_kontakt_aktiv']) ? 1 : 0,
            'name'    => sanitize_text_field(wp_unslash($_POST['livento_cc_kontakt_name'] ?? '')),
            'rolle'   => sanitize_text_field(wp_unslash($_POST['livento_cc_kontakt_rolle'] ?? '')),
            'foto'    => esc_url_raw(trim((string) wp_unslash($_POST['livento_cc_kontakt_foto'] ?? ''))),
            'telefon' => sanitize_text_field(wp_unslash($_POST['livento_cc_kontakt_telefon'] ?? '')),
            'email'   => sanitize_email(wp_unslash($_POST['livento_cc_kontakt_email'] ?? '')),
            'antwort' => sanitize_text_field(wp_unslash($_POST['livento_cc_kontakt_antwort'] ?? '')),
        ));
        // v1.66.0: Formvarianten (eine Gruppe je Zeile, Titel mit „|“)
        if (isset($_POST['livento_cc_formvarianten'])) {
            $gruppen = livento_cc_formvarianten_parsen(sanitize_textarea_field(wp_unslash($_POST['livento_cc_formvarianten'])));
            update_option('livento_cc_formvarianten', $gruppen);
        }
        livento_cc_flush_cache(); // mit ggf. neuem Key sofort neu laden
        $notice = 'Einstellungen gespeichert.';
    }
    if (isset($_POST['livento_cc_test_webhook']) && check_admin_referer('livento_cc_test_webhook')) {
        $src = (isset($_POST['lvk_test_source']) && $_POST['lvk_test_source'] === 'foerder') ? 'foerder' : 'kurs';
        $url = livento_cc_lead_webhook($src);
        if ($url === '') {
            $notice = '⚠️ Kein Webhook für den ' . ($src === 'foerder' ? 'Förderberater' : 'Kursberater') . ' hinterlegt (oben eintragen + speichern).';
        } else {
            $res = wp_remote_post($url, array(
                'timeout' => 15,
                'headers' => array('Content-Type' => 'application/json'),
                'body'    => wp_json_encode(array(
                    'first_name' => 'Test', 'last_name' => 'Admin',
                    'email' => 'admin-test@livento-bildung.de', 'phone' => '+49 30 0000000',
                    'consent' => true, 'source' => $src === 'foerder' ? 'foerderberater' : 'kursberater',
                    'selection' => 'Admin-Webhook-Test', 'page' => admin_url(),
                )),
            ));
            if (is_wp_error($res)) {
                $notice = '❌ FEHLGESCHLAGEN — der WordPress-Server konnte GHL NICHT erreichen: ' . $res->get_error_message() . '. (Ausgehende Verbindungen beim Hoster blockiert?)';
            } else {
                $code = (int) wp_remote_retrieve_response_code($res);
                $body = trim((string) wp_remote_retrieve_body($res));
                $ok   = ($code >= 200 && $code < 300);
                $notice = ($ok ? '✅ ERFOLG' : '⚠️ Antwort') . ' — GHL antwortete HTTP ' . $code
                    . ($body !== '' ? ': ' . mb_substr($body, 0, 180) : '')
                    . ($ok ? '. Wenn jetzt im GHL-Ausführungsprotokoll nichts steht, ist der Workflow nicht „Published/On".' : '');
            }
        }
    }
    if ((isset($_POST['livento_cc_save_berater']) || isset($_POST['livento_cc_reset_berater'])) && check_admin_referer('livento_cc_save_berater')) {
        if (isset($_POST['livento_cc_reset_berater'])) {
            delete_option('livento_cc_berater_interests');
            $notice = 'Berater-Interessen auf Standard zurückgesetzt.';
        } else {
            $rows  = (isset($_POST['lvk_int']) && is_array($_POST['lvk_int'])) ? wp_unslash($_POST['lvk_int']) : array();
            $clean = array();
            foreach ($rows as $row) {
                $label  = isset($row['label']) ? sanitize_text_field($row['label']) : '';
                $topics = (isset($row['topics']) && is_array($row['topics'])) ? array_map('sanitize_title', $row['topics']) : array();
                $topics = array_values(array_filter($topics));
                if ($label !== '' && !empty($topics)) {
                    $clean[] = array('label' => $label, 'topics' => $topics);
                }
            }
            update_option('livento_cc_berater_interests', $clean);
            $notice = 'Berater-Interessen gespeichert (' . count($clean) . ' Aussagen).';
        }
    }
    if ((isset($_POST['livento_cc_save_foerder']) || isset($_POST['livento_cc_reset_foerder'])) && check_admin_referer('livento_cc_save_foerder')) {
        if (isset($_POST['livento_cc_reset_foerder'])) {
            delete_option('livento_cc_foerderungen');
            $notice = 'Förderprogramme auf Standard zurückgesetzt.';
        } else {
            $rows  = (isset($_POST['lvk_foe']) && is_array($_POST['lvk_foe'])) ? wp_unslash($_POST['lvk_foe']) : array();
            $clean = array();
            foreach ($rows as $row) {
                $title = isset($row['title']) ? sanitize_text_field($row['title']) : '';
                if ($title === '') {
                    continue;
                }
                $clean[] = array(
                    'title'       => $title,
                    'slug'        => (isset($row['slug']) && $row['slug'] !== '') ? sanitize_title($row['slug']) : sanitize_title($title),
                    'icon'        => isset($row['icon']) ? sanitize_key($row['icon']) : '_default',
                    'region'      => (isset($row['region']) && is_array($row['region'])) ? array_values(array_filter(array_map('sanitize_title', $row['region']))) : array(),
                    'audience'    => (isset($row['audience']) && is_array($row['audience'])) ? array_values(array_filter(array_map('sanitize_key', $row['audience']))) : array(),
                    'match'       => (isset($row['match']) && is_array($row['match'])) ? array_values(array_filter(array_map('sanitize_title', $row['match']))) : array(),
                    'funding_key' => isset($row['funding_key']) ? sanitize_key($row['funding_key']) : '',
                    'link'        => isset($row['link']) ? esc_url_raw(trim($row['link'])) : '',
                    'short'       => isset($row['short']) ? sanitize_text_field($row['short']) : '',
                    'body'        => isset($row['body']) ? sanitize_textarea_field($row['body']) : '',
                    // v1.56.0: Abschnitt auf Kursseiten (nur beim Programm mit Tag azav_bildungsgutschein genutzt).
                    'kurs_text'   => isset($row['kurs_text']) ? sanitize_textarea_field($row['kurs_text']) : '',
                );
            }
            update_option('livento_cc_foerderungen', $clean);
            livento_cc_flush_cache();
            $notice = 'Förderprogramme gespeichert (' . count($clean) . ').';
        }
    }
    if ((isset($_POST['livento_cc_save_ftags']) || isset($_POST['livento_cc_reset_ftags'])) && check_admin_referer('livento_cc_save_ftags')) {
        if (isset($_POST['livento_cc_reset_ftags'])) {
            delete_option('livento_cc_funding_tags');
            $notice = 'Kurse-Förder-Tags auf Standard zurückgesetzt.';
        } else {
            $rows  = (isset($_POST['lvk_ft']) && is_array($_POST['lvk_ft'])) ? wp_unslash($_POST['lvk_ft']) : array();
            $clean = array();
            foreach ($rows as $row) {
                $label = isset($row['label']) ? sanitize_text_field($row['label']) : '';
                if ($label === '') {
                    continue;
                }
                $slug = (isset($row['slug']) && $row['slug'] !== '') ? sanitize_title($row['slug']) : sanitize_title($label);
                if ($slug === '' || isset($clean[$slug])) {
                    continue; // leere oder doppelte Slugs verwerfen
                }
                $clean[$slug] = $label;
            }
            update_option('livento_cc_funding_tags', $clean);
            $notice = 'Kurse-Förder-Tags gespeichert (' . count($clean) . ').';
        }
    }
    if ((isset($_POST['livento_cc_save_fstatus']) || isset($_POST['livento_cc_reset_fstatus'])) && check_admin_referer('livento_cc_save_fstatus')) {
        if (isset($_POST['livento_cc_reset_fstatus'])) {
            delete_option('livento_cc_foerder_status');
            $notice = 'Förderberater-Schema auf Standard zurückgesetzt.';
        } else {
            $rows  = (isset($_POST['lvk_fst']) && is_array($_POST['lvk_fst'])) ? wp_unslash($_POST['lvk_fst']) : array();
            $clean = array();
            foreach ($rows as $row) {
                $label = isset($row['label']) ? sanitize_text_field($row['label']) : '';
                if ($label === '') {
                    continue;
                }
                $quals = array();
                $lines = isset($row['quals']) ? preg_split('/\r\n|\r|\n/', (string) $row['quals']) : array();
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '') {
                        continue;
                    }
                    if (strpos($line, '|') !== false) {
                        list($qk, $ql) = explode('|', $line, 2);
                        $qk = sanitize_title($qk);
                        $ql = sanitize_text_field(trim($ql));
                    } else {
                        $ql = sanitize_text_field($line);
                        $qk = sanitize_title($line);
                    }
                    if ($qk !== '' && $ql !== '') {
                        $quals[] = array('key' => $qk, 'label' => $ql);
                    }
                }
                $clean[] = array(
                    'key'      => sanitize_title($label),
                    'label'    => $label,
                    'question' => isset($row['question']) ? sanitize_text_field($row['question']) : '',
                    'quals'    => $quals,
                );
            }
            update_option('livento_cc_foerder_status', $clean);
            $notice = 'Förderberater-Schema gespeichert (' . count($clean) . ' Status).';
        }
    }
    if (isset($_POST['livento_cc_save_kl']) && check_admin_referer('livento_cc_save_kl')) {
        $rows  = (isset($_POST['lvk_kl']) && is_array($_POST['lvk_kl'])) ? wp_unslash($_POST['lvk_kl']) : array();
        $clean = array();
        $seen  = array();
        $norm_csv = function ($v) {
            if (is_array($v)) {
                $v = implode(',', $v);
            }
            $parts = array_values(array_filter(array_map(function ($x) { return sanitize_title(trim($x)); }, explode(',', (string) $v))));
            return implode(',', $parts);
        };
        foreach ($rows as $row) {
            $name = isset($row['name']) ? sanitize_text_field($row['name']) : '';
            if ($name === '') {
                continue;
            }
            $id = (isset($row['id']) && $row['id'] !== '') ? sanitize_title($row['id']) : sanitize_title($name);
            if ($id === '' || isset($seen[$id])) {
                continue; // leere oder doppelte IDs verwerfen
            }
            $seen[$id] = true;
            $clean[] = array(
                'id'          => $id,
                'name'        => $name,
                'heading'     => isset($row['heading']) ? sanitize_text_field($row['heading']) : '',
                'subheading'  => isset($row['subheading']) ? sanitize_text_field($row['subheading']) : '',
                'audience'    => $norm_csv($row['audience'] ?? ''),
                'topics'      => $norm_csv($row['topics'] ?? ''),
                'format'      => $norm_csv($row['format'] ?? ''),
                'recognition' => $norm_csv($row['recognition'] ?? ''),
                'q'           => isset($row['q']) ? sanitize_text_field($row['q']) : '',
                'bildungsgutschein' => (isset($row['bildungsgutschein']) && $row['bildungsgutschein'] === 'yes') ? 'yes' : 'no',
                'art'         => (isset($row['art']) && isset(livento_cc_kind_labels()[$row['art']])) ? $row['art'] : '',
                'pflicht'     => (isset($row['pflicht']) && $row['pflicht'] === 'yes') ? 'yes' : 'no',
                'limit'       => max(0, (int) ($row['limit'] ?? 6)),
                'sort'        => isset($row['sort']) ? sanitize_key($row['sort']) : 'next_start',
                'columns'     => max(0, min(4, (int) ($row['columns'] ?? 0))),
                'cta'         => (isset($row['cta']) && $row['cta'] === 'yes') ? 'yes' : 'no',
                'cta_label'   => isset($row['cta_label']) ? sanitize_text_field($row['cta_label']) : '',
            );
        }
        update_option('livento_cc_kurslisten', $clean);
        $notice = 'Kurslisten gespeichert (' . count($clean) . ').';
    }

    $tab  = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'overview';
    $tabs = array('overview' => 'Übersicht', 'anleitung' => 'Anleitung', 'shortcodes' => 'Shortcodes', 'kurslisten' => 'Kurslisten', 'dozenten' => 'Dozenten', 'slugs' => 'Filter & Slugs', 'berater' => 'Berater', 'foerderung' => 'Förderprogramme', 'settings' => 'Einstellungen');

    echo '<div class="wrap"><h1>Livento Kurskatalog</h1>';
    if ($notice) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($notice) . '</p></div>';
    }
    echo '<h2 class="nav-tab-wrapper">';
    foreach ($tabs as $key => $label) {
        $cls = ($tab === $key) ? ' nav-tab-active' : '';
        echo '<a class="nav-tab' . $cls . '" href="' . esc_url(admin_url('admin.php?page=livento-kurskatalog&tab=' . $key)) . '">' . esc_html($label) . '</a>';
    }
    echo '</h2>';

    if ($tab === 'shortcodes') {
        livento_cc_admin_tab_shortcodes();
    } elseif ($tab === 'kurslisten') {
        livento_cc_admin_tab_kurslisten();
    } elseif ($tab === 'dozenten') {
        livento_cc_admin_tab_dozenten();
    } elseif ($tab === 'slugs') {
        livento_cc_admin_tab_slugs();
    } elseif ($tab === 'settings') {
        livento_cc_admin_tab_settings();
    } elseif ($tab === 'berater') {
        livento_cc_admin_tab_berater();
    } elseif ($tab === 'foerderung') {
        livento_cc_admin_tab_foerderung();
    } elseif ($tab === 'anleitung') {
        livento_cc_admin_tab_anleitung();
    } else {
        livento_cc_admin_tab_overview();
    }
    echo '</div>';
}

/** Tab „Übersicht": Status + Cache. */
function livento_cc_admin_tab_overview() {
    $offerings = livento_cc_get_offerings();
    $count  = is_array($offerings) ? count($offerings) : 0;
    $keyset = livento_cc_key_is_valid(livento_cc_anon_key());

    $rows = array(
        'anon-Key konfiguriert' => $keyset ? '✅ ja' : '❌ nein — unter „Einstellungen" eintragen',
        'Supabase-Projekt'      => esc_html(LIVENTO_CC_SUPABASE_URL),
        'Geladene Kurse'        => esc_html((string) $count) . ($count ? '' : ' — API nicht erreichbar oder keine öffentlichen Kurse'),
        'Katalog-Seite'         => '<a href="' . esc_url(livento_cc_list_url()) . '" target="_blank" rel="noopener">' . esc_html(livento_cc_list_url()) . '</a>',
        'Detail-URL-Muster'     => '<code>' . esc_html(livento_cc_list_url() . '<kurs-slug>/') . '</code>',
        'Kurs-Sitemap'          => '<a href="' . esc_url(home_url('/livento-kurse.xml')) . '" target="_blank" rel="noopener">' . esc_html(home_url('/livento-kurse.xml')) . '</a>',
        'Cache-Dauer (TTL)'     => esc_html(human_time_diff(0, LIVENTO_CC_TTL)),
        'Cache-Version'         => esc_html((string) livento_cc_ver()),
        'Purge-Webhook'         => (livento_cc_purge_secret() !== '')
            ? '✅ aktiv: <code>POST ' . esc_html(home_url('/wp-json/livento/v1/purge')) . '</code>'
            : '— deaktiviert (Secret unter „Einstellungen" setzen)',
        'Lizenz-Nachkauf'       => (livento_cc_topup_secret() !== '' && livento_cc_topup_product_id() > 0)
            ? '✅ aktiv (Produkt #' . esc_html((string) livento_cc_topup_product_id()) . ')'
            : '— deaktiviert (Produkt-ID und Secret unter „Einstellungen" setzen)',
    );

    echo '<table class="widefat striped" style="max-width:820px;margin-top:16px"><tbody>';
    foreach ($rows as $k => $v) {
        echo '<tr><th style="width:220px;text-align:left">' . esc_html($k) . '</th><td>' . $v . '</td></tr>';
    }
    echo '</tbody></table>';

    echo '<form method="post" style="margin-top:16px">';
    wp_nonce_field('livento_cc_flush');
    echo '<button type="submit" name="livento_cc_flush" value="1" class="button button-secondary">Cache jetzt leeren</button> ';
    echo '<span class="description">Liste + alle Einzelkurse neu von Campus Connect laden.</span>';
    echo '</form>';
}

/** Tab „Anleitung": Schritt-für-Schritt-Hilfe direkt im Backend. */
function livento_cc_admin_tab_anleitung() {
    $tab   = function ($t) { return esc_url(admin_url('admin.php?page=livento-kurskatalog&tab=' . $t)); };
    $perma = esc_url(admin_url('options-permalink.php'));
    $list  = esc_url(livento_cc_list_url());
    $flist = esc_url(livento_cc_foerder_list_url());

    echo '<style>
.lvk-help{border:1px solid #dcdcde;border-radius:6px;margin:10px 0;background:#fff;max-width:880px}
.lvk-help>summary{cursor:pointer;padding:12px 16px;font-weight:600;font-size:14px;list-style:none}
.lvk-help>summary::-webkit-details-marker{display:none}
.lvk-help>summary::before{content:"\\25B8";color:#2271b1;margin-right:8px;display:inline-block;transition:transform .15s}
.lvk-help[open]>summary::before{transform:rotate(90deg)}
.lvk-help .in{padding:0 16px 14px;border-top:1px solid #f0f0f1}
.lvk-help ol,.lvk-help ul{margin:10px 0 10px 20px}
.lvk-help li{margin:5px 0}
.lvk-help code{background:#f6f7f7;padding:1px 5px;border-radius:3px}
.lvk-help .tip{background:#f0f6fc;border-left:4px solid #2271b1;padding:8px 12px;margin:10px 0}
</style>';

    echo '<p style="margin:14px 0 6px;max-width:880px">Schritt-für-Schritt-Hilfe für das gesamte Plugin. Eine ausführliche Fassung als Dokument liegt im Plugin-Ordner unter <code>ANLEITUNG.md</code>.</p>';
    echo '<p class="lvk-help" style="padding:12px 16px;font-weight:600">⚡ Schnellstart: anon-Key eintragen (Einstellungen) → <a href="' . $perma . '">Permalinks speichern</a> → Seiten mit den Shortcodes anlegen.</p>';

    // 1) Erste Einrichtung
    echo '<details class="lvk-help" open><summary>1 · Erste Einrichtung (Pflicht)</summary><div class="in">';
    echo '<ol>';
    echo '<li><strong>anon-Key:</strong> <a href="' . $tab('settings') . '">Einstellungen</a> → Feld „anon-Key" → Supabase-Anon-Key einfügen → Speichern. Kontrolle in der <a href="' . $tab('overview') . '">Übersicht</a> („✅" + Kurszahl > 0).</li>';
    echo '<li><strong>Permalinks:</strong> einmal <a href="' . $perma . '">Einstellungen → Permalinks → Speichern</a> (aktiviert die Detail-URLs). Auch nach jedem größeren Update wiederholen.</li>';
    echo '</ol></div></details>';

    // 2) Seitenstruktur
    echo '<details class="lvk-help"><summary>2 · Welche Seite braucht welchen Shortcode</summary><div class="in">';
    echo '<p>Je eine WordPress-<strong>Seite</strong> anlegen und den Shortcode in den Inhalt setzen:</p>';
    echo '<ul>';
    echo '<li>Seite <code>kurse</code> → <code>[livento_kurse]</code> (Katalog + Kurs-Detailseiten)</li>';
    echo '<li>Seite <code>e-learning</code> → <code>[livento_tarife]</code> (Tarif-Landingpage, siehe Abschnitt 7)</li>';
    echo '<li><strong>Unterseiten</strong> davon → <code>[livento_tarif family="pflichtticket"]</code> (bzw. <code>komplettticket</code>, <code>rollenticket</code>)</li>';
    echo '<li>Seite <code>foerdermoeglichkeiten</code> → <code>[livento_foerderungen]</code> (Förderungen + Detailseiten)</li>';
    echo '<li>z. B. <code>kursberatung</code> → <code>[livento_kurse_berater]</code></li>';
    echo '<li>z. B. <code>foerderberatung</code> → <code>[livento_foerder_berater]</code></li>';
    echo '<li>z. B. <code>dozenten</code> → <code>[livento_dozenten]</code> (Slug frei wählbar, keine Detailseiten)</li>';
    echo '<li>Startseite o. Ä. → <code>[livento_themen]</code>, <code>[livento_kurse_suche]</code></li>';
    echo '</ul>';
    echo '<p class="tip"><strong>Wichtig:</strong> Der Seiten-Slug muss zur Basis passen (Katalog = <code>kurse</code>, Förderungen = <code>foerdermoeglichkeiten</code>). Detailseiten entstehen automatisch darunter.</p>';
    echo 'Aktuelle Seiten: Katalog <a href="' . $list . '" target="_blank" rel="noopener">' . $list . '</a> · Förderungen <a href="' . $flist . '" target="_blank" rel="noopener">' . $flist . '</a>';
    echo '</div></details>';

    // 3) Kurskatalog
    echo '<details class="lvk-help"><summary>3 · Kurskatalog &amp; Filter</summary><div class="in">';
    echo '<ul>';
    echo '<li><code>[livento_kurse]</code> = voller Katalog mit Filter-Sidebar.</li>';
    echo '<li><code>[livento_kurse limit="6"]</code> = kuratierter 6er-Block ohne Filter.</li>';
    echo '<li><code>[livento_kurse limit="6" topics="leitung-management"]</code> = Block nur mit Kursen dieses Themas (Slugs siehe <a href="' . $tab('slugs') . '">Filter &amp; Slugs</a>, mehrere mit Komma).</li>';
    echo '<li><code>[livento_kurse audience="fuehrungskraefte"]</code> = nur Kurse für diese Zielgruppe (kombinierbar mit <code>topics</code>).</li>';
    echo '<li>Deep-Links: <code>' . esc_html(livento_cc_list_url()) . '?format=online_live</code> usw.</li>';
    echo '<li><strong>Kurslisten für Landingpages:</strong> benannte Listen je Kampagne (z. B. „Pflichtfortbildungen", „Betreuungskräfte") im Tab <a href="' . $tab('kurslisten') . '">Kurslisten</a> zusammenstellen und per <code>[livento_kursliste id="…"]</code> einbinden.</li>';
    echo '</ul>';
    echo '<p>Neue Kurse erscheinen automatisch (nach Cache-Ablauf bzw. „Cache leeren"/Webhook).</p>';
    echo '</div></details>';

    // 4) Kursberater
    echo '<details class="lvk-help"><summary>4 · Kursberater einrichten</summary><div class="in">';
    echo '<p>Ablauf: Interessen → Starttermin → Angaben → Ergebnis (passende Kurse).</p>';
    echo '<ol>';
    echo '<li><strong>Interessen:</strong> <a href="' . $tab('berater') . '">Berater</a> → „Ich möchte …"-Aussagen + Themen ankreuzen → Speichern.</li>';
    echo '<li><strong>Formular (empfohlen, 1 Button):</strong> <a href="' . $tab('settings') . '">Einstellungen</a> → „GHL Inbound-Webhook-URL" eintragen. Das Plugin zeigt dann ein eigenes Formular und sendet den Lead beim Klick auf „Weiter" direkt an euren GHL-Workflow. Leer = kein Formular-Schritt (oder alternativ Embed-Code).</li>';
    echo '<li><strong>Seite</strong> mit <code>[livento_kurse_berater]</code> anlegen.</li>';
    echo '</ol></div></details>';

    // 5) Förderprogramme
    echo '<details class="lvk-help"><summary>5 · Förderprogramme pflegen</summary><div class="in">';
    echo '<p>Liegen im Plugin (nicht mehr als WP-Beiträge). Pflege im Tab <a href="' . $tab('foerderung') . '">Förderprogramme</a>. Pro Programm:</p>';
    echo '<ul>';
    echo '<li>Titel, Slug (optional), Icon</li>';
    echo '<li>Für (Privatpersonen/Unternehmen) · Region (Strg/Cmd-Klick = mehrere)</li>';
    echo '<li>Kurzbeschreibung (Kachel) + ausführliche Beschreibung (Detailseite, Markdown)</li>';
    echo '<li>Kurse-Förder-Tag (optional) → „Passende Kurse" auf der Detailseite</li>';
    echo '<li>Offizieller Link (optional)</li>';
    echo '</ul>';
    echo '<p class="tip"><strong>Kurse-Förder-Tags selbst verwalten:</strong> Die Auswahl im Dropdown „Kurse-Förder-Tag" lässt sich unten im Tab „Förderprogramme" (Abschnitt „Kurse-Förder-Tags") um eigene Einträge ergänzen. Ein eigener Tag filtert aber nur dann Kurse, wenn Campus Connect denselben Förder-Wert kennt — sonst dient er als reines Label.</p>';
    echo '<p class="tip">Nach dem Anlegen neuer Programme einmal <a href="' . $perma . '">Permalinks speichern</a>.</p>';
    echo '</div></details>';

    // 6) Förderberater
    echo '<details class="lvk-help"><summary>6 · Förderberater einrichten</summary><div class="in">';
    echo '<p>Ablauf (SGD-Stil): Status → Qualifikation → Angaben → Ergebnis (passende Förderungen). Drei Stellschrauben:</p>';
    echo '<ol>';
    echo '<li><strong>Schema:</strong> <a href="' . $tab('foerderung') . '">Förderprogramme</a> → unten „Förderberater-Schema". Status + Frage + Qualifikationen (eine Zeile <code>schlüssel | Anzeigetext</code>). Schlüssel stabil halten.</li>';
    echo '<li><strong>Zuordnung:</strong> in jeder Programm-Karte „Förderberater: passt zu …" aufklappen und Qualifikationen ankreuzen.</li>';
    echo '<li><strong>Formular (empfohlen, 1 Button):</strong> <a href="' . $tab('settings') . '">Einstellungen</a> → „Förderberater: GHL Inbound-Webhook-URL". Leer = es wird automatisch der Kursberater-Webhook/das Kursberater-Formular verwendet.</li>';
    echo '</ol>';
    echo '<p>Seite mit <code>[livento_foerder_berater]</code> anlegen.</p>';
    echo '</div></details>';

    // 7) Tarife & Pakete verkaufen
    echo '<details class="lvk-help"><summary>7 · Tarife &amp; Pakete verkaufen (Selbstlernkurse)</summary><div class="in">';
    echo '<p>Livento verkauft <strong>Jahres-Lernpakete für Einrichtungen</strong>, keine Einzelkurse. Eine Einrichtung kauft eine Anzahl <em>Lizenzen</em> (= Beschäftigte) und verteilt sie danach selbst an ihre Mitarbeitenden.</p>';

    echo '<div class="tip"><strong>Der Aufbau ist dreistufig</strong> — gepflegt wird alles in Campus Connect, nicht hier:<br>'
       . '<strong>Tariffamilie</strong> (die Preis-Karte: PflichtTicket, KomplettTicket, RollenTicket) → '
       . '<strong>Produktplan</strong> (Preisstaffel, Laufzeit, Umsatzsteuer) → '
       . '<strong>Setting-Variante</strong> (ambulant, stationär, Therapie … — hier hängen die Kurse und das WooCommerce-Produkt).</div>';

    echo '<h4 style="margin:14px 0 4px">So richtest du einen Tarif ein</h4>';
    echo '<p>Die Reihenfolge ist wichtig: Das WooCommerce-Produkt muss <em>zuerst</em> existieren, weil Campus Connect seine ID braucht.</p>';
    echo '<ol>';
    echo '<li><strong>WooCommerce-Produkt anlegen</strong> — Typ „Einfaches Produkt". Der Preis ist egal (er wird durch die Staffel überschrieben), Steuerklasse „Standard" (19 %). Speichern.</li>';
    echo '<li><strong>Produkt-ID notieren</strong> — sie steht in der URL der Produkt-Bearbeitung (<code>post=1234</code>) und oben im Tarif-Hinweis auf der Produktseite.</li>';
    echo '<li><strong>In Campus Connect eintragen</strong> — <em>Kursbundles → Variante öffnen → „Verkauf &amp; Website"</em>: Produktplan, URL-Kürzel und die Produkt-ID hinterlegen, dann „Auf der Website zeigen" aktivieren. Ohne diese drei Angaben lässt sich die Variante nicht veröffentlichen.</li>';
    echo '<li><strong>Fertig.</strong> Das Produkt erkennt seinen Tarif jetzt automatisch — Preis, Mengenfeld („Anzahl Lizenzen") und Kursliste erscheinen von allein. Im Produkt-Backend steht dann oben <em>„Livento-Tarif erkannt: …"</em>. Du musst dort nichts auswählen.</li>';
    echo '</ol>';
    echo '<div class="tip"><strong>Warum nicht andersherum?</strong> Das Auswahlfeld „Tarif manuell zuordnen" im Produkt listet nur bereits <em>öffentliche</em> Varianten — eine Variante wird aber erst öffentlich, wenn die Produkt-ID drinsteht. Deshalb: erst Produkt, dann Campus Connect. Das Feld ist nur ein Notnagel für Sonderfälle.</div>';

    echo '<h4 style="margin:14px 0 4px">Seiten anlegen</h4>';
    echo '<ol>';
    echo '<li>Seite <code>/e-learning/</code> mit <code>[livento_tarife]</code> → die Landingpage mit den drei Preis-Karten und dem Angebotsrechner.</li>';
    echo '<li>Je Familie eine <strong>Unterseite</strong> davon: <code>/e-learning/pflicht-ticket/</code> mit <code>[livento_tarif family="pflichtticket"]</code>, dazu <code>komplett-ticket</code> und <code>rollen-ticket</code>. Der Seiten-Slug muss dem URL-Kürzel der Tariffamilie in Campus Connect entsprechen — sonst zeigen die Karten der Landingpage ins Leere.</li>';
    echo '<li>Weicht der Eltern-Slug ab, im Landingpage-Shortcode <code>base="…"</code> mitgeben — sonst zeigen die Karten ins Leere.</li>';
    echo '</ol>';

    echo '<h4 style="margin:14px 0 4px">Preise</h4>';
    echo '<p>Der Preis richtet sich nach der <strong>Beschäftigtenzahl</strong>, nicht nach einer Stückzahl: pauschal je Einrichtung, pro Nutzer (optional mit Mindestbetrag) oder als individuelles Angebot. Gepflegt wird die Staffel in Campus Connect unter <em>Kursbundles → Produktpläne &amp; Vorlagen</em>.</p>';
    echo '<div class="tip"><strong>Bitte keine Preise in die Seite tippen.</strong> Tarifkarte, Angebotsrechner und Warenkorb rechnen mit derselben Funktion. Ändert Livento die Staffel, ziehen alle drei automatisch nach — eine hart geschriebene Zahl nicht, und dann steht ein anderer Preis auf der Seite als im Warenkorb.</div>';
    echo '<p>Fällt eine Teamgröße in die Stufe „individuelles Angebot" (z. B. ab 151 Beschäftigten), blendet das Plugin den Warenkorb-Button automatisch aus und zeigt stattdessen „Angebot anfordern".</p>';

    echo '<h4 style="margin:14px 0 4px">Was beim Kauf passiert</h4>';
    echo '<ul>';
    echo '<li>Die Warenkorbmenge <strong>ist</strong> die Anzahl der Beschäftigten.</li>';
    echo '<li>Die Firma ist im Checkout Pflichtfeld, sobald ein Tarif im Warenkorb liegt — der Kauf legt in Campus Connect einen Arbeitgeber an.</li>';
    echo '<li>Campus Connect erzeugt automatisch Arbeitgeber, Lizenzpaket und den Zugang für die Käuferin. <strong>Sie selbst belegt keinen Lizenzplatz</strong> — wer 40 Lizenzen kauft, ist Bestellerin, nicht zwingend Lernende.</li>';
    echo '<li>Ihre Mitarbeitenden trägt sie danach selbst im Team-Bereich ein (einzeln oder per CSV) und gibt Plätze bei Austritt wieder frei. <strong>Im Checkout werden bewusst keine Mitarbeiterdaten abgefragt.</strong></li>';
    echo '</ul>';
    echo '</div></details>';

    // 8) Cache / Sitemap / Updates
    // 8) Dozentenseite
    echo '<details class="lvk-help"><summary>8 · Dozentinnen und Dozenten</summary><div class="in">';
    echo '<p>Die Profile kommen <strong>live aus Campus Connect</strong>. Im Plugin ist dafür nichts zu pflegen — Status und Freigaben siehe Tab <a href="' . $tab('dozenten') . '">Dozenten</a>.</p>';
    echo '<ol>';
    echo '<li>Seite anlegen (<strong>Slug frei wählbar</strong>, z. B. <code>dozenten</code>) und <code>[livento_dozenten]</code> einfügen. Es gibt bewusst keine Detailseiten je Dozent.</li>';
    echo '<li>Die URL dieser Seite unter <a href="' . $tab('settings') . '">Einstellungen → Dozentenseite</a> eintragen — nur dafür wird sie gebraucht: für den Link „Alle Dozentinnen und Dozenten" unter dem Dozenten-Abschnitt der Kursseiten. <strong>Leer = ohne Link</strong>, nie ein toter Link.</li>';
    echo '</ol>';
    echo '<p>Der Abschnitt <strong>„Wer dich unterrichtet"</strong> auf den Kursdetailseiten erscheint automatisch, sobald einem Kurs ein freigegebener Dozent zugeordnet ist — ohne Seite und ohne Einstellung.</p>';
    echo '<div class="tip"><strong>Jemand fehlt auf der Seite?</strong> Die Ursache liegt immer in Campus Connect, nie hier. Ein Profil erscheint nur, wenn der Dozent im Portal <em>selbst</em> eingewilligt hat <strong>und</strong> die Institutsleitung freigegeben hat <strong>und</strong> Funktionsbezeichnung sowie eine Kurzvorstellung von mindestens 150 Zeichen vorliegen. In Campus Connect: Dozenten → Dozent öffnen → Reiter „Website"; dort steht, was noch fehlt.</div>';
    echo '<p>Ein Profil <strong>ohne Foto</strong> verschwindet nicht, sondern erscheint in einer eigenen Textdarstellung. Wird ein Profil nachträglich geändert, verfällt die Freigabe automatisch; ein Widerruf entfernt es sofort und löscht das Foto.</p>';
    echo '</div></details>';

    echo '<details class="lvk-help"><summary>9 · Cache, Sitemap &amp; Updates</summary><div class="in">';
    echo '<ul>';
    echo '<li><strong>Cache leeren:</strong> <a href="' . $tab('overview') . '">Übersicht</a> → „Cache jetzt leeren".</li>';
    echo '<li><strong>Auto-Purge:</strong> <a href="' . $tab('settings') . '">Einstellungen</a> → Purge-Secret setzen + in Campus Connect hinterlegen.</li>';
    echo '<li><strong>Sitemap:</strong> <a href="' . esc_url(home_url('/livento-kurse.xml')) . '" target="_blank" rel="noopener">/livento-kurse.xml</a> (enthält Kurse + Förderungen, hängt im Rank-Math-Index).</li>';
    echo '<li><strong>Updates:</strong> Dashboard → Aktualisierungen → Erneut prüfen → Aktualisieren.</li>';
    echo '</ul></div></details>';

    // 9) Problembehebung
    echo '<details class="lvk-help"><summary>10 · Problembehebung</summary><div class="in"><ul>';
    echo '<li><strong>„anon-Key ❌" / keine Kurse:</strong> Key in den Einstellungen prüfen, Cache leeren.</li>';
    echo '<li><strong>Detailseite 404:</strong> <a href="' . $perma . '">Permalinks speichern</a>.</li>';
    echo '<li><strong>Förderberater-Ergebnis leer:</strong> in den Programmen „passt zu …" ankreuzen (gleiche Schlüssel wie im Schema).</li>';
    echo '<li><strong>Förderberater ohne Formular-Schritt:</strong> Embed in den Einstellungen hinterlegen (Förder- oder Kursberater).</li>';
    echo '<li><strong>Neuer Kurs fehlt:</strong> Cache leeren oder Webhook einrichten.</li>';
    echo '<li><strong>Tarifseite bleibt leer:</strong> In Campus Connect ist noch keine Tariffamilie öffentlich geschaltet (<em>Kursbundles → Produktpläne &amp; Vorlagen → Familie → „Auf der Website anzeigen"</em>). Danach Cache leeren.</li>';
    echo '<li><strong>Variante fehlt auf der Tarifseite:</strong> Sie braucht Produktplan, URL-Kürzel <em>und</em> WooCommerce-Produkt-ID — erst dann lässt sie sich veröffentlichen.</li>';
    echo '<li><strong>Produkt zeigt „Kein Livento-Tarif":</strong> Drei moegliche Ursachen. (1) Die Produkt-ID steht noch nicht in Campus Connect am Bundle. (2) Die Variante ist dort noch nicht oeffentlich. (3) <strong>Die Bundle-Ausgabe ist inaktiv</strong> — „Bundle-Ausgabe aktiv" sitzt im Dialog <em>Bearbeiten</em> und ist ein anderer Schalter als „auf der Website zeigen und verkaufen" unter <em>Verkauf &amp; Website</em>. Die Website verlangt beide. Danach Cache leeren.</li>';
    echo '<li><strong>Preis im Warenkorb weicht ab:</strong> Der Cache ist alt. Cache leeren; die Staffel wird sonst nur alle 3 Stunden neu geladen.</li>';
    echo '</ul></div></details>';
}

/** Tab „Shortcodes": Liste mit Beispielen + Attributen. */
function livento_cc_admin_tab_shortcodes() {
    echo '<p style="margin-top:12px">Diese Shortcodes stehen bereit. Feld anklicken markiert den Code zum Kopieren.</p>';
    foreach (livento_cc_shortcodes() as $sc) {
        echo '<div class="card" style="max-width:820px;padding:8px 16px 16px;margin:12px 0">';
        echo '<h3 style="margin:8px 0 4px">' . esc_html($sc['title']) . ' &nbsp;<code>[' . esc_html($sc['tag']) . ']</code></h3>';
        echo '<p style="margin:0 0 8px">' . esc_html($sc['desc']) . '</p>';
        echo '<input type="text" readonly class="large-text code" value="' . esc_attr($sc['example']) . '" onclick="this.select()">';
        if (!empty($sc['atts'])) {
            echo '<p style="margin:10px 0 4px"><strong>Attribute (optional):</strong></p><ul style="margin:0 0 0 18px;list-style:disc">';
            foreach ($sc['atts'] as $name => $desc) {
                echo '<li><code>' . esc_html($name) . '</code> — ' . esc_html($desc) . '</li>';
            }
            echo '</ul>';
        }
        echo '</div>';
    }
}

/**
 * Tab „Dozenten": Status der freigegebenen Profile + Einrichtung der Seite.
 *
 * Bewusst ein eigener Reiter und nicht ein Absatz unter „Shortcodes": Anders als bei
 * Kursen entscheidet hier nicht die Redaktion, was erscheint, sondern zwei Tore in
 * Campus Connect. Wer nicht auftaucht, sucht den Grund sonst im Plugin — und findet
 * ihn dort nie.
 */
function livento_cc_admin_tab_dozenten() {
    $list  = livento_cc_get_instructors();
    $count = is_array($list) ? count($list) : 0;
    $url   = livento_cc_dozenten_url();
    $mit_foto = 0;
    foreach ($list as $i) {
        if (!empty($i['photo_url'])) {
            $mit_foto++;
        }
    }

    echo '<p style="margin-top:12px;max-width:880px">Dozentenprofile kommen <strong>live aus Campus Connect</strong>. Im Plugin gibt es dafür nichts zu pflegen — hier siehst du nur, was ankommt, und richtest die Seite ein.</p>';

    // ── Status ───────────────────────────────────────────────────────────
    echo '<div class="card" style="max-width:880px;padding:8px 16px 16px;margin:12px 0">';
    echo '<h3 style="margin:8px 0 10px">Status</h3>';
    echo '<table class="widefat striped" style="max-width:520px"><tbody>';
    echo '<tr><td>Freigegebene Profile</td><td><strong>' . (int) $count . '</strong></td></tr>';
    echo '<tr><td>davon mit Foto</td><td>' . (int) $mit_foto . '</td></tr>';
    echo '<tr><td>Dozentenseite hinterlegt</td><td>' . ($url !== ''
        ? '✅ <a href="' . esc_url($url) . '" target="_blank" rel="noopener">' . esc_html($url) . '</a>'
        : '— <em>nicht gesetzt, Kursseiten verlinken nicht</em>') . '</td></tr>';
    echo '</tbody></table>';

    if ($count === 0) {
        echo '<div class="notice notice-warning inline" style="margin:14px 0 0"><p><strong>Noch kein Profil freigegeben.</strong> Der Shortcode gibt derzeit <em>nichts</em> aus — bewusst, damit keine halbfertige Seite entsteht. Freigeben lässt sich ein Profil ausschließlich in Campus Connect (siehe unten).</p></div>';
    } elseif ($count < 3) {
        echo '<div class="notice notice-info inline" style="margin:14px 0 0"><p><strong>Erst ' . (int) $count . ' Profil' . ($count === 1 ? '' : 'e') . ' freigegeben.</strong> Der Abschnitt auf den Kursseiten wirkt schon jetzt; mit der eigenen Übersichtsseite würde ich warten, bis mehrere Profile stehen — eine Personenseite mit einem einzigen Eintrag wird von Suchmaschinen als dünn bewertet.</p></div>';
    }
    echo '</div>';

    // ── Wer erscheint ────────────────────────────────────────────────────
    if ($count > 0) {
        echo '<div class="card" style="max-width:880px;padding:8px 16px 16px;margin:12px 0">';
        echo '<h3 style="margin:8px 0 10px">Diese Profile sind öffentlich</h3>';
        echo '<table class="widefat striped"><thead><tr><th>Name</th><th>Funktionsbezeichnung</th><th>Foto</th><th>Stichworte</th></tr></thead><tbody>';
        foreach ($list as $i) {
            $name = trim(($i['first_name'] ?? '') . ' ' . ($i['last_name'] ?? ''));
            $q = is_array($i['qualifications'] ?? null) ? $i['qualifications'] : array();
            echo '<tr>';
            echo '<td><strong>' . esc_html($name) . '</strong></td>';
            echo '<td>' . esc_html((string) ($i['public_role'] ?? '')) . '</td>';
            echo '<td>' . (!empty($i['photo_url']) ? '✅' : '— <em>Textdarstellung</em>') . '</td>';
            echo '<td>' . esc_html(implode(', ', array_slice($q, 0, 6))) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '<p class="description" style="margin-top:10px">Ein Profil ohne Foto verschwindet <strong>nicht</strong> — es erscheint in einer eigenen Textdarstellung statt als Karte mit leerem Bildbereich.</p>';
        echo '</div>';
    }

    // ── Einrichtung ──────────────────────────────────────────────────────
    echo '<div class="card" style="max-width:880px;padding:8px 16px 16px;margin:12px 0">';
    echo '<h3 style="margin:8px 0 10px">Seite einrichten</h3>';
    echo '<ol style="margin:0 0 0 18px">';
    echo '<li>Eine WordPress-<strong>Seite</strong> anlegen. Der Slug ist <strong>frei wählbar</strong> — anders als beim Katalog gibt es keine Basis-Konstante, keine Rewrite-Regel und keine Detailseiten je Dozent.</li>';
    echo '<li>Diesen Shortcode in den Inhalt setzen:</li>';
    echo '</ol>';
    echo '<input type="text" readonly class="large-text code" style="margin:8px 0" value="[livento_dozenten]" onclick="this.select()">';
    echo '<ol start="3" style="margin:0 0 0 18px">';
    echo '<li>Die URL dieser Seite unter <a href="' . esc_url(admin_url('admin.php?page=livento-kurskatalog&tab=settings')) . '">Einstellungen → Dozentenseite</a> eintragen. Erst dann erscheint auf den Kursdetailseiten der Link „Alle Dozentinnen und Dozenten". <strong>Leer heißt: ohne Link</strong> — ein toter Link entsteht nie.</li>';
    echo '</ol>';
    echo '<p class="description" style="margin-top:10px">Der Abschnitt „Wer dich unterrichtet" auf den Kursdetailseiten erscheint <strong>automatisch</strong>, sobald einem Kurs ein freigegebener Dozent zugeordnet ist. Dafür ist keine Seite und keine Einstellung nötig.</p>';
    echo '</div>';

    // ── Wer entscheidet ──────────────────────────────────────────────────
    echo '<div class="card" style="max-width:880px;padding:8px 16px 16px;margin:12px 0">';
    echo '<h3 style="margin:8px 0 10px">Wer erscheint — und wer das entscheidet</h3>';
    echo '<p style="margin:0 0 8px">Das Plugin filtert <strong>nichts</strong> nach. Es zeigt genau das, was Campus Connect über die Ansicht <code>public_instructors</code> herausgibt. Ein Profil ist dort nur enthalten, wenn <strong>alle</strong> Bedingungen erfüllt sind:</p>';
    echo '<ul style="margin:0 0 8px 18px;list-style:disc">';
    echo '<li>Der Dozent ist aktiv,</li>';
    echo '<li>er hat im Dozentenportal <strong>selbst eingewilligt</strong> (das kann die Verwaltung nicht ersetzen),</li>';
    echo '<li>die Institutsleitung hat das Profil <strong>freigegeben</strong>,</li>';
    echo '<li>Funktionsbezeichnung und eine Kurzvorstellung von mindestens 150 Zeichen liegen vor.</li>';
    echo '</ul>';
    echo '<p style="margin:0 0 8px">Wird ein Profil danach geändert, verfällt die Freigabe automatisch und muss erneut erteilt werden. Ein Widerruf entfernt das Profil sofort und löscht das Foto.</p>';
    echo '<p class="description" style="margin:0"><strong>Fehlt jemand?</strong> Die Ursache liegt immer in Campus Connect, nie im Plugin: Dozenten → Dozent öffnen → Reiter „Website". Dort steht, welche der Bedingungen noch offen ist. Veröffentlicht werden ausschließlich Name, Funktionsbezeichnung, Kurzvorstellung, Qualifikationen und – sofern hinterlegt – das Foto. E-Mail, Telefon, Adresse und Bankdaten sind für die Website technisch nicht erreichbar.</p>';
    echo '</div>';

    // ── Aktualität ───────────────────────────────────────────────────────
    echo '<div class="card" style="max-width:880px;padding:8px 16px 16px;margin:12px 0">';
    echo '<h3 style="margin:8px 0 10px">Aktualität</h3>';
    echo '<p style="margin:0">Die Liste ist wie der Kurskatalog zwischengespeichert. Ist der Purge-Webhook eingerichtet, schlagen Freigaben und Widerrufe <strong>sofort</strong> durch; ohne ihn dauert es bis zu drei Stunden. Der Cache lässt sich in der <a href="' . esc_url(admin_url('admin.php?page=livento-kurskatalog&tab=overview')) . '">Übersicht</a> von Hand leeren.</p>';
    echo '</div>';
}

/** Tab „Filter & Slugs": Deep-Link-Parameter, Facet-Werte (live) + Kurs-Slugs. */
function livento_cc_admin_tab_slugs() {
    $offerings = livento_cc_augment(livento_cc_get_offerings());

    echo '<p style="margin-top:12px">Diese Parameter lassen sich an die Katalog-URL hängen (Deep-Linking) — z. B. <code>' . esc_html(livento_cc_list_url()) . '?format=online_live</code>. Mehrere Werte mit Komma, mehrere Parameter mit <code>&amp;</code>.</p>';

    echo '<h3>Suche &amp; Schalter</h3>';
    echo '<table class="widefat striped" style="max-width:820px"><thead><tr><th>Parameter</th><th>Wert(e)</th><th>Wirkung</th></tr></thead><tbody>';
    echo '<tr><td><code>q</code></td><td>Freitext</td><td>Volltextsuche (Titel + Beschreibung)</td></tr>';
    echo '<tr><td><code>azav</code></td><td><code>1</code></td><td>Nur Kurse, die mit Bildungsgutschein förderfähig sind (AZAV-zugelassen; Schalter „Bildungsgutschein“)</td></tr>';
    echo '<tr><td><code>vatexempt</code></td><td><code>1</code></td><td>Nur USt-freie Kurse</td></tr>';
    echo '<tr><td><code>free</code></td><td><code>1</code></td><td>Nur kostenfreie Kurse</td></tr>';
    echo '<tr><td><code>pflicht</code></td><td><code>1</code></td><td>Nur Pflichtfortbildungen (v1.58.0)</td></tr>';
    echo '<tr><td><code>art</code></td><td><code>' . esc_html(implode('</code>, <code>', array_keys(livento_cc_kind_labels()))) . '</code></td><td>Umschalter über dem Katalog auf eine Angebotsart stellen (v1.58.0)</td></tr>';
    echo '</tbody></table>';

    foreach (livento_cc_filter_groups() as $g) {
        $counts = livento_cc_collect_facet($offerings, $g['field'], $g['arr']);
        if (empty($counts)) {
            continue;
        }
        $items = array();
        foreach ($counts as $val => $cnt) {
            $items[] = array('val' => (string) $val, 'label' => livento_cc_facet_label($g['lab'], $val), 'cnt' => $cnt);
        }
        usort($items, function ($a, $b) { return strcasecmp($a['label'], $b['label']); });

        echo '<h3>' . esc_html($g['title']) . ' &nbsp;<span class="description">Parameter <code>' . esc_html($g['dim']) . '</code></span></h3>';
        echo '<table class="widefat striped" style="max-width:820px"><thead><tr><th>Wert (Slug)</th><th>Anzeige-Label</th><th>Anzahl</th></tr></thead><tbody>';
        foreach ($items as $it) {
            echo '<tr><td><code>' . esc_html($it['val']) . '</code></td><td>' . esc_html($it['label']) . '</td><td>' . (int) $it['cnt'] . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    $with_slug = array_filter($offerings, function ($o) { return !empty($o['slug']); });
    echo '<h3>Kurs-Slugs (' . count($with_slug) . ')</h3>';
    echo '<table class="widefat striped"><thead><tr><th>Titel</th><th>Slug</th><th>URL</th></tr></thead><tbody>';
    foreach ($with_slug as $o) {
        $u = livento_cc_detail_url($o['slug']);
        echo '<tr><td>' . esc_html($o['title']) . '</td><td><code>' . esc_html($o['slug']) . '</code></td><td><a href="' . esc_url($u) . '" target="_blank" rel="noopener">öffnen ↗</a></td></tr>';
    }
    echo '</tbody></table>';
}

/** Tab „Einstellungen": anon-Key + Purge-Webhook-Secret (in der DB gespeichert → update-fest). */
function livento_cc_admin_tab_settings() {
    $key      = (string) get_option('livento_cc_anon_key', '');
    $secret   = (string) get_option('livento_cc_purge_secret', '');
    $key_mask = livento_cc_key_is_valid($key) ? (substr($key, 0, 8) . '…' . substr($key, -6)) : '';
    $webhook  = home_url('/wp-json/livento/v1/purge');

    echo '<form method="post" style="margin-top:16px;max-width:760px">';
    wp_nonce_field('livento_cc_save_settings');

    echo '<h3>Supabase anon-Key</h3>';
    echo '<p>Öffentlicher Schlüssel: Publishable Key (<code>sb_publishable_…</code>) oder bisheriger anon-Key (<code>eyJ…</code>). Nie den Secret Key (<code>sb_secret_…</code>) oder <code>service_role</code>. Hier gespeichert, übersteht er Plugin-Updates.</p>';
    if ($key_mask) {
        echo '<p>Aktuell hinterlegt: <code>' . esc_html($key_mask) . '</code></p>';
    }
    echo '<input type="password" name="livento_cc_anon_key" class="large-text code" value="' . esc_attr($key) . '" autocomplete="off" placeholder="sb_publishable_… oder eyJ…">';
    if (defined('LIVENTO_CC_ANON_KEY') && livento_cc_key_is_valid(LIVENTO_CC_ANON_KEY) && !livento_cc_key_is_valid($key)) {
        echo '<p class="description">Hinweis: In der Plugin-Datei/wp-config ist bereits ein Key gesetzt; dieses Feld hat Vorrang, sobald gespeichert.</p>';
    }

    echo '<h3 style="margin-top:24px">Cache-Purge-Webhook (optional)</h3>';
    echo '<p>Shared-Secret, damit Campus Connect den WP-Cache bei Kursänderungen <strong>sofort</strong> leeren kann. Leer = Webhook aus (Cache läuft per 3-Std.-TTL).</p>';
    echo '<p><label><strong>Secret:</strong><br><input type="text" name="livento_cc_purge_secret" class="regular-text code" value="' . esc_attr($secret) . '" autocomplete="off" placeholder="langer Zufallsstring" style="width:420px"></label></p>';
    echo '<p class="description">Webhook-URL (in Campus Connect hinterlegen): <code>POST ' . esc_html($webhook) . '</code><br>mit Header <code>X-Livento-Purge-Secret: &lt;Secret&gt;</code></p>';

    echo '<h3 style="margin-top:24px">CampusTicket: Lizenz-Nachkauf</h3>';
    echo '<p>Arbeitgeber kaufen in Campus Connect Lizenzen nach; der Preis wird dort anteilig berechnet und als signierter Beleg übergeben. Beide Felder leer = Nachkauf aus.</p>';
    echo '<p><label><strong>Produkt-ID „Lizenzen nachkaufen":</strong><br><input type="number" min="0" name="livento_cc_topup_product_id" class="small-text" value="' . esc_attr((string) livento_cc_topup_product_id()) . '"></label></p>';
    echo '<p><label><strong>Beleg-Secret:</strong><br><input type="text" name="livento_cc_topup_secret" class="regular-text code" value="' . esc_attr((string) get_option('livento_cc_topup_secret', '')) . '" autocomplete="off" placeholder="identisch mit TICKET_TOPUP_SECRET in Supabase" style="width:420px"></label></p>';

    echo '<h3 style="margin-top:24px">Kursberater: Kontaktformular</h3>';
    echo '<p><strong>Empfohlen — GoHighLevel-Webhook (nur EIN Button):</strong> Das Plugin zeigt ein schlankes Formular (Vorname, Nachname, E-Mail) und sendet die Daten beim Klick auf „Weiter" direkt an euren GHL-Workflow. So muss der Interessent nur einen Button klicken. In GHL: <em>Automation → Workflows → Trigger „Inbound Webhook" → URL kopieren</em> und hier einfügen.</p>';
    echo '<p><label><strong>GHL Inbound-Webhook-URL:</strong><br><input type="url" name="livento_cc_berater_webhook" class="large-text code" value="' . esc_attr((string) get_option('livento_cc_berater_webhook', '')) . '" placeholder="https://services.leadconnectorhq.com/hooks/…"></label></p>';
    echo '<p class="description" style="margin-top:14px"><strong>Alternative</strong> — roher Embed-Code (iframe). Wird nur genutzt, wenn oben <em>keine</em> Webhook-URL steht. Hat dann allerdings einen eigenen Absende-Button (zwei Buttons).</p>';
    echo '<textarea name="livento_cc_berater_form" class="large-text code" rows="4" placeholder="&lt;iframe src=&quot;https://api.leadconnectorhq.com/widget/form/…&quot;&gt;&lt;/iframe&gt; …">' . esc_textarea((string) get_option('livento_cc_berater_form', '')) . '</textarea>';

    echo '<h3 style="margin-top:24px">Förderberater: Kontaktformular</h3>';
    echo '<p>Wie oben für <code>[livento_foerder_berater]</code>. <strong>Beide Felder leer = es wird automatisch der Kursberater-Webhook bzw. das Kursberater-Formular verwendet.</strong> Eigenen Förder-Workflow nur eintragen, wenn der Förder-Lead getrennt erfasst werden soll.</p>';
    echo '<p><label><strong>GHL Inbound-Webhook-URL:</strong><br><input type="url" name="livento_cc_foerder_webhook" class="large-text code" value="' . esc_attr((string) get_option('livento_cc_foerder_webhook', '')) . '" placeholder="(leer = Kursberater-Webhook verwenden)"></label></p>';
    echo '<p class="description" style="margin-top:14px"><strong>Alternative</strong> — eigener Embed-Code (iframe). Leer = Kursberater-Formular.</p>';
    echo '<textarea name="livento_cc_foerder_form" class="large-text code" rows="4" placeholder="(leer = Kursberater-Formular verwenden)">' . esc_textarea((string) get_option('livento_cc_foerder_form', '')) . '</textarea>';

    echo '<h3 style="margin-top:24px">Beratung / Rückruf (Sekundär-CTA Kursdetailseite)</h3>';
    echo '<p>Optionales Ziel für den zweiten Button „Rückruf vereinbaren" oben im CTA-Bereich jeder Kursdetailseite (z. B. Kontakt-/Rückrufseite oder Kursberater). <strong>Leer = der Button wird ausgeblendet</strong> (kein toter Link).</p>';
    echo '<p><label><strong>URL:</strong><br><input type="url" name="livento_cc_beratung_url" class="large-text code" value="' . esc_attr((string) get_option('livento_cc_beratung_url', '')) . '" placeholder="https://livento-bildung.de/kontakt/"></label></p>';

    echo '<h3 style="margin-top:24px">Dozentenseite</h3>';
    echo '<p>Die Seite mit dem Shortcode <code>[livento_dozenten]</code>. Wird nur fuer den Link "Alle Dozentinnen und Dozenten" unter dem Dozenten-Abschnitt der Kursdetailseiten gebraucht. <strong>Leer = der Link wird weggelassen</strong> (kein toter Link). Der Shortcode selbst funktioniert unabhaengig davon auf jeder Seite.</p>';
    echo '<p><label><strong>URL:</strong><br><input type="url" name="livento_cc_dozenten_url" class="large-text code" value="' . esc_attr((string) get_option('livento_cc_dozenten_url', '')) . '" placeholder="https://livento-bildung.de/dozenten/"></label></p>';

    // v1.62.0: Ansprechpartner der Kursdetailseiten
    $k = livento_cc_kontakt();
    echo '<h3 style="margin-top:24px">Ansprechpartner (Kursdetailseiten)</h3>';
    echo '<p>Karte „Fragen zum Kurs?“ unter den häufigen Fragen jeder Kursdetailseite. Ohne Name heißt sie „Deine Kursberatung“ (Wir-Form), mit Name spricht die Person selbst (Ich-Form). Die Antwortzeit steht auch im Ablauf der Anfragekurse.</p>';
    echo '<p><label><input type="checkbox" name="livento_cc_kontakt_aktiv" value="1"' . checked(!empty($k['aktiv']), true, false) . '> Karte anzeigen</label></p>';
    echo '<p><label><strong>Name:</strong><br><input type="text" name="livento_cc_kontakt_name" class="regular-text" value="' . esc_attr($k['name']) . '" placeholder="leer = „Deine Kursberatung“"></label></p>';
    echo '<p><label><strong>Rolle (optional):</strong><br><input type="text" name="livento_cc_kontakt_rolle" class="regular-text" value="' . esc_attr($k['rolle']) . '" placeholder="z. B. Kursberatung"></label></p>';
    echo '<p><label><strong>Foto-URL (optional):</strong><br><input type="url" name="livento_cc_kontakt_foto" class="large-text code" value="' . esc_attr($k['foto']) . '" placeholder="https://… (z. B. Profilfoto aus Campus Connect)"></label></p>';
    echo '<p><label><strong>Telefon:</strong><br><input type="text" name="livento_cc_kontakt_telefon" class="regular-text" value="' . esc_attr($k['telefon']) . '"></label></p>';
    echo '<p><label><strong>E-Mail:</strong><br><input type="email" name="livento_cc_kontakt_email" class="regular-text" value="' . esc_attr($k['email']) . '"></label></p>';
    echo '<p><label><strong>Antwortzeit:</strong><br><input type="text" name="livento_cc_kontakt_antwort" class="regular-text" value="' . esc_attr($k['antwort']) . '" placeholder="innerhalb weniger Stunden an Werktagen"></label></p>';
    echo '<p class="description">Erscheint als „… melde mich <em>Antwortzeit</em>.“ Telefon und E-Mail beide leer = keine Karte.</p>';

    // v1.66.0: Formvarianten
    $zeilen = array();
    foreach (livento_cc_formvarianten() as $g) {
        $zeilen[] = implode(' | ', (array) $g);
    }
    echo '<h3 style="margin-top:24px">Formvarianten (Kursdetailseiten)</h3>';
    echo '<p>Kurse zum selben Thema in anderer Form (live tagsüber oder abends, Selbstlernkurs, Präsenz …). Auf jeder Seite einer Gruppe erscheint „Welche Form passt zu dir?“ mit Umfang, Start, Preis und Abschluss. <strong>Eine Gruppe je Zeile, Kurstitel mit „|“ getrennt</strong>, genau wie im Katalog (Groß-/Kleinschreibung egal). Formen, die gerade nicht im Katalog sind, werden ausgelassen.</p>';
    echo '<textarea name="livento_cc_formvarianten" class="large-text" rows="6">' . esc_textarea(implode("\n", $zeilen)) . '</textarea>';

    echo '<p style="margin-top:20px"><button class="button button-primary" name="livento_cc_save_settings" value="1">Speichern</button></p>';
    echo '</form>';

    // Webhook-Diagnose: serverseitiger Test-POST an die gespeicherte GHL-URL.
    echo '<hr style="margin:28px 0 16px"><h3 style="margin:0 0 6px">Webhook testen</h3>';
    echo '<p>Sendet <strong>serverseitig</strong> einen Test-Lead an die oben <em>gespeicherte</em> URL und zeigt GHLs Antwort. So siehst du, ob dein WordPress-Server GHL überhaupt erreichen kann (unabhängig vom Formular). Vorher speichern.</p>';
    foreach (array('kurs' => 'Kursberater', 'foerder' => 'Förderberater') as $src => $label) {
        echo '<form method="post" style="display:inline-block;margin:0 10px 8px 0">';
        wp_nonce_field('livento_cc_test_webhook');
        echo '<input type="hidden" name="lvk_test_source" value="' . esc_attr($src) . '">';
        echo '<button type="submit" name="livento_cc_test_webhook" value="1" class="button button-secondary">Test an ' . esc_html($label) . '-Webhook senden</button>';
        echo '</form>';
    }
}

/** Tab „Berater": Interessen-Aussagen + Themen-Mapping editieren. */
function livento_cc_admin_tab_berater() {
    $offerings    = livento_cc_get_offerings();
    $topic_counts = livento_cc_collect_facet($offerings, 'topics', true);
    $current      = livento_cc_berater_interests();

    // Auswahl-Slugs: vorhandene Themen + bereits gemappte (damit nichts verloren geht).
    $slugs = array_keys($topic_counts);
    foreach ($current as $row) {
        foreach ($row['topics'] as $t) {
            if (!in_array($t, $slugs, true)) {
                $slugs[] = $t;
            }
        }
    }
    sort($slugs);

    echo '<p style="margin-top:12px">Interessen-Aussagen für den Schritt „Deine Interessen" im <code>[livento_kurse_berater]</code>. Pro Aussage die zugehörigen Themen ankreuzen. Im Berater erscheinen nur Aussagen, deren Themen tatsächlich Kurse haben.</p>';
    echo '<form method="post">';
    wp_nonce_field('livento_cc_save_berater');

    echo '<div id="lvk-int-rows">';
    $i = 0;
    foreach ($current as $row) {
        echo livento_cc_admin_berater_row((string) $i, $row['label'], $row['topics'], $slugs, $topic_counts);
        $i++;
    }
    echo '</div>';

    echo '<template id="lvk-int-tpl">' . livento_cc_admin_berater_row('__IDX__', '', array(), $slugs, $topic_counts) . '</template>';

    echo '<p><button type="button" class="button" id="lvk-int-add">+ Aussage hinzufügen</button></p>';
    echo '<p style="margin-top:16px">';
    echo '<button type="submit" name="livento_cc_save_berater" value="1" class="button button-primary">Speichern</button> ';
    echo '<button type="submit" name="livento_cc_reset_berater" value="1" class="button" onclick="return confirm(\'Wirklich auf die Standard-Aussagen zurücksetzen?\')">Auf Standard zurücksetzen</button>';
    echo '</p></form>';

    echo "<script>(function(){var box=document.getElementById('lvk-int-rows'),tpl=document.getElementById('lvk-int-tpl'),n=0;var add=document.getElementById('lvk-int-add');if(add){add.addEventListener('click',function(){n++;var d=document.createElement('div');d.innerHTML=tpl.innerHTML.replace(/__IDX__/g,'new'+n);box.appendChild(d.firstElementChild);});}box.addEventListener('click',function(e){if(e.target&&e.target.classList.contains('lvk-int-del')){var r=e.target.closest('.lvk-int-row');if(r)r.parentNode.removeChild(r);}});})();</script>";
}

/** Eine editierbare Interessen-Zeile (Label-Input + Themen-Checkboxen). */
function livento_cc_admin_berater_row($idx, $label, $topics, $slugs, $counts) {
    $h  = '<div class="lvk-int-row" style="border:1px solid #dcdcde;border-radius:6px;padding:12px;margin:0 0 10px;background:#fff;max-width:920px">';
    $h .= '<input type="text" name="lvk_int[' . esc_attr($idx) . '][label]" value="' . esc_attr($label) . '" class="large-text" placeholder="Aussage, z. B. Ich möchte Menschen mit Demenz besser begleiten">';
    $h .= '<div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:8px 14px">';
    foreach ($slugs as $slug) {
        $checked = in_array($slug, (array) $topics, true) ? ' checked' : '';
        $cnt = ' (' . (int) (isset($counts[$slug]) ? $counts[$slug] : 0) . ')';
        $h .= '<label style="font-size:12px;white-space:nowrap"><input type="checkbox" name="lvk_int[' . esc_attr($idx) . '][topics][]" value="' . esc_attr($slug) . '"' . $checked . '> ' . esc_html(livento_cc_humanize($slug)) . $cnt . '</label>';
    }
    $h .= '</div>';
    $h .= '<p style="margin:10px 0 0"><button type="button" class="button-link lvk-int-del" style="color:#b32d2e">Entfernen</button></p>';
    $h .= '</div>';
    return $h;
}

/** Tab „Kurslisten": benannte, kriterienbasierte Kurs-Widgets fuer Landingpages. */
function livento_cc_admin_tab_kurslisten() {
    $offerings    = livento_cc_augment(livento_cc_get_offerings());
    $aud_counts   = livento_cc_collect_facet($offerings, 'audience', true);
    $rec_counts   = livento_cc_collect_facet($offerings, 'recognition', true);
    $fmt_counts   = livento_cc_collect_facet($offerings, 'format', false);
    $topic_counts = livento_cc_collect_facet($offerings, 'topics', true);
    $lists        = livento_cc_kurslisten();

    echo '<p style="margin-top:12px;max-width:960px">Stelle pro Landingpage / Werbekampagne (Google&nbsp;Ads, Meta&nbsp;Ads) eine benannte Kursliste zusammen. Sie füllt sich <strong>automatisch</strong> aus dem Katalog anhand der Kriterien – binde sie per Shortcode <code>[livento_kursliste id="…"]</code> auf der Seite ein. Beispiele: <em>Betreuungskräfte</em> = Zielgruppe „Betreuungskräfte"; <em>Pflichtfortbildungen</em> = Titel-Stichwort „Pflichtfortbildung".</p>';
    echo '<form method="post">';
    wp_nonce_field('livento_cc_save_kl');

    echo '<div id="lvk-kl-rows">';
    $i = 0;
    foreach ($lists as $l) {
        echo livento_cc_admin_kursliste_row((string) $i, $l, $aud_counts, $rec_counts, $fmt_counts, $topic_counts);
        $i++;
    }
    echo '</div>';

    echo '<template id="lvk-kl-tpl">' . livento_cc_admin_kursliste_row('__IDX__', array(), $aud_counts, $rec_counts, $fmt_counts, $topic_counts) . '</template>';

    echo '<p><button type="button" class="button" id="lvk-kl-add">+ Kursliste hinzufügen</button></p>';
    echo '<p style="margin-top:16px"><button type="submit" name="livento_cc_save_kl" value="1" class="button button-primary">Speichern</button> <span class="description">Entfernte Listen verschwinden erst beim Speichern endgültig.</span></p>';
    echo '</form>';

    echo "<script>(function(){var box=document.getElementById('lvk-kl-rows'),tpl=document.getElementById('lvk-kl-tpl'),n=0;var add=document.getElementById('lvk-kl-add');if(add){add.addEventListener('click',function(){n++;var d=document.createElement('div');d.innerHTML=tpl.innerHTML.replace(/__IDX__/g,'new'+n);box.appendChild(d.firstElementChild);});}box.addEventListener('click',function(e){if(e.target&&e.target.classList.contains('lvk-kl-del')){var r=e.target.closest('.lvk-kl-row');if(r&&confirm('Diese Kursliste entfernen?'))r.parentNode.removeChild(r);}});})();</script>";
}

/** Eine editierbare Kurslisten-Zeile im Admin (Name/ID + Kriterien + Darstellung + Shortcode). */
function livento_cc_admin_kursliste_row($idx, $l, $aud_counts, $rec_counts, $fmt_counts, $topic_counts) {
    $name    = isset($l['name']) ? $l['name'] : '';
    $id      = isset($l['id']) ? $l['id'] : '';
    $heading = isset($l['heading']) ? $l['heading'] : '';
    $sub     = isset($l['subheading']) ? $l['subheading'] : '';
    $q       = isset($l['q']) ? $l['q'] : '';
    $limit   = isset($l['limit']) ? (int) $l['limit'] : 6;
    $sort    = isset($l['sort']) ? $l['sort'] : 'next_start';
    $columns = isset($l['columns']) ? (int) $l['columns'] : 0;
    $cta     = (isset($l['cta']) && $l['cta'] === 'yes');
    $cta_lbl = isset($l['cta_label']) ? $l['cta_label'] : '';

    // Gespeicherte CSV → Lookup-Set fuer die Checkboxen.
    $to_set = function ($csv) {
        $out = array();
        foreach (explode(',', (string) $csv) as $v) {
            $v = trim($v);
            if ($v !== '') {
                $out[$v] = true;
            }
        }
        return $out;
    };
    $sel_aud = $to_set($l['audience'] ?? '');
    $sel_rec = $to_set($l['recognition'] ?? '');
    $sel_fmt = $to_set($l['format'] ?? '');
    $sel_top = $to_set($l['topics'] ?? '');

    $n = function ($field) use ($idx) { return 'lvk_kl[' . esc_attr($idx) . '][' . $field . ']'; };

    // Checkbox-Gruppe aus Label-Map + Live-Counts (nur vorhandene oder bereits gewaehlte Werte).
    $group = function ($field, $labels, $counts, $selected) use ($n) {
        $h = '<div style="display:flex;flex-wrap:wrap;gap:6px 14px;margin:4px 0 0">';
        foreach ($labels as $slug => $label) {
            $cnt = isset($counts[$slug]) ? (int) $counts[$slug] : 0;
            if ($cnt === 0 && !isset($selected[$slug])) {
                continue;
            }
            $chk = isset($selected[$slug]) ? ' checked' : '';
            $h .= '<label style="font-size:12px;white-space:nowrap"><input type="checkbox" name="' . $n($field) . '[]" value="' . esc_attr($slug) . '"' . $chk . '> ' . esc_html($label) . ' (' . $cnt . ')</label>';
        }
        $h .= '</div>';
        return $h;
    };

    // Themen haben keine feste Label-Map → aus Live-Counts (+ bereits gewaehlte).
    $topic_labels = array();
    foreach (array_keys($topic_counts) as $slug) {
        $topic_labels[$slug] = livento_cc_humanize($slug);
    }
    foreach (array_keys($sel_top) as $slug) {
        if (!isset($topic_labels[$slug])) {
            $topic_labels[$slug] = livento_cc_humanize($slug);
        }
    }
    ksort($topic_labels);

    $sort_opts = array(
        'next_start' => 'Nächster Start', 'newest' => 'Neueste', 'popular' => 'Beliebt',
        'rating' => 'Beste Bewertung', 'most_booked' => 'Meiste Buchungen',
        'price_asc' => 'Preis aufsteigend', 'price_desc' => 'Preis absteigend',
    );
    $sort_html = '';
    foreach ($sort_opts as $val => $lbl) {
        $sort_html .= '<option value="' . esc_attr($val) . '"' . ($val === $sort ? ' selected' : '') . '>' . esc_html($lbl) . '</option>';
    }

    $h  = '<div class="lvk-kl-row" style="border:1px solid #dcdcde;border-radius:6px;padding:14px 16px;margin:0 0 14px;background:#fff;max-width:960px">';

    // Name + ID
    $h .= '<div style="display:flex;gap:12px;flex-wrap:wrap">';
    $h .= '<label style="flex:1;min-width:220px">Name (intern)<br><input type="text" name="' . $n('name') . '" value="' . esc_attr($name) . '" class="regular-text" placeholder="z. B. Pflichtfortbildungen"></label>';
    $h .= '<label style="flex:1;min-width:220px">Shortcode-ID <span class="description">(leer = aus Name)</span><br><input type="text" name="' . $n('id') . '" value="' . esc_attr($id) . '" class="regular-text" placeholder="pflichtfortbildungen"></label>';
    $h .= '</div>';

    // Ueberschrift + Untertitel
    $h .= '<div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:8px">';
    $h .= '<label style="flex:1;min-width:220px">Überschrift <span class="description">(öffentlich, optional)</span><br><input type="text" name="' . $n('heading') . '" value="' . esc_attr($heading) . '" class="regular-text" placeholder="Pflichtfortbildungen für Pflegekräfte"></label>';
    $h .= '<label style="flex:1;min-width:220px">Untertitel <span class="description">(optional)</span><br><input type="text" name="' . $n('subheading') . '" value="' . esc_attr($sub) . '" class="regular-text"></label>';
    $h .= '</div>';

    // Kriterien
    $h .= '<p style="margin:14px 0 2px;font-weight:600">Kriterien <span style="font-weight:400;color:#646970">— mehrere Felder = UND; innerhalb eines Feldes = ODER. Alles leer = alle Kurse.</span></p>';
    $h .= '<p style="margin:8px 0 0"><strong style="font-size:12px">Zielgruppe</strong>' . $group('audience', livento_cc_audience_labels(), $aud_counts, $sel_aud) . '</p>';
    $h .= '<p style="margin:8px 0 0"><strong style="font-size:12px">Thema</strong>' . $group('topics', $topic_labels, $topic_counts, $sel_top) . '</p>';
    $h .= '<p style="margin:8px 0 0"><strong style="font-size:12px">Format</strong>' . $group('format', livento_cc_format_labels(), $fmt_counts, $sel_fmt) . '</p>';
    $h .= '<p style="margin:8px 0 0"><strong style="font-size:12px">Anerkennung</strong>' . $group('recognition', livento_cc_recognition_labels(), $rec_counts, $sel_rec) . '</p>';
    // v1.58.0: Angebotsart + Pflichtfortbildung
    $art_counts = livento_cc_art_counts();
    $art_sel    = isset($l['art']) ? (string) $l['art'] : '';
    $art_html   = '<option value="">Alle Angebotsarten</option>';
    foreach (livento_cc_kind_labels() as $slug => $label) {
        $art_html .= '<option value="' . esc_attr($slug) . '"' . ($slug === $art_sel ? ' selected' : '') . '>'
            . esc_html($label) . ' (' . (int) ($art_counts[$slug] ?? 0) . ')</option>';
    }
    $h .= '<label style="display:block;margin-top:10px">Angebotsart<br><select name="' . $n('art') . '">' . $art_html . '</select></label>';
    $pf_on = (isset($l['pflicht']) && $l['pflicht'] === 'yes');
    $h .= '<label style="display:block;margin-top:10px"><input type="checkbox" name="' . $n('pflicht') . '" value="yes"' . ($pf_on ? ' checked' : '') . '> <strong>Nur Pflichtfortbildungen</strong></label>';
    $h .= '<label style="display:block;margin-top:10px;max-width:560px">Titel-Stichwort <span class="description">(optional – nur Kurse, deren Titel diesen Text enthält)</span><br><input type="text" name="' . $n('q') . '" value="' . esc_attr($q) . '" class="regular-text" placeholder="z. B. Pflichtfortbildung"></label>';
    $bg_on = (isset($l['bildungsgutschein']) && $l['bildungsgutschein'] === 'yes');
    $h .= '<label style="display:block;margin-top:10px"><input type="checkbox" name="' . $n('bildungsgutschein') . '" value="yes"' . ($bg_on ? ' checked' : '') . '> <strong>Nur förderfähig mit Bildungsgutschein</strong> <span class="description">(AZAV-zugelassene Kurse)</span></label>';

    // Darstellung
    $h .= '<p style="margin:14px 0 2px;font-weight:600">Darstellung</p>';
    $h .= '<div style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-end">';
    $h .= '<label>Max. Karten<br><input type="number" min="0" name="' . $n('limit') . '" value="' . esc_attr((string) $limit) . '" style="width:80px"></label>';
    $h .= '<label>Sortierung<br><select name="' . $n('sort') . '">' . $sort_html . '</select></label>';
    $h .= '<label>Spalten<br><select name="' . $n('columns') . '">';
    foreach (array(0 => 'auto', 1 => '1', 2 => '2', 3 => '3', 4 => '4') as $cv => $cl) {
        $h .= '<option value="' . $cv . '"' . ($cv === $columns ? ' selected' : '') . '>' . $cl . '</option>';
    }
    $h .= '</select></label>';
    $h .= '<label style="align-self:center;margin-top:18px"><input type="checkbox" name="' . $n('cta') . '" value="yes"' . ($cta ? ' checked' : '') . '> „Alle ansehen"-Button</label>';
    $h .= '<label>Button-Text<br><input type="text" name="' . $n('cta_label') . '" value="' . esc_attr($cta_lbl) . '" placeholder="Alle Kurse ansehen" style="width:190px"></label>';
    $h .= '</div>';

    // Shortcode-Hinweis + Entfernen
    $sc_id = $id !== '' ? $id : sanitize_title($name);
    $h .= '<div style="display:flex;justify-content:space-between;align-items:center;margin-top:14px;gap:12px;flex-wrap:wrap">';
    if ($sc_id !== '') {
        $h .= '<code style="background:#f6f7f7;padding:4px 8px;border-radius:3px">[livento_kursliste id="' . esc_attr($sc_id) . '"]</code>';
    } else {
        $h .= '<span class="description">Shortcode erscheint nach dem Speichern.</span>';
    }
    $h .= '<button type="button" class="button-link lvk-kl-del" style="color:#b32d2e">Entfernen</button>';
    $h .= '</div>';

    $h .= '</div>';
    return $h;
}

/* ============================================================
 * 11. Campus-Connect-Rueckkehr nach WooCommerce-Bestellungen
 * ============================================================ */

/** Nur produktive Campus-Connect-HTTPS-Ziele akzeptieren (kein offener Redirect). */
function livento_cc_checkout_return_url($raw_url) {
    $url = esc_url_raw(wp_unslash((string) $raw_url));
    if ($url === '') return '';

    $parts = wp_parse_url($url);
    if (!is_array($parts)
        || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
        || strtolower((string) ($parts['host'] ?? '')) !== 'campus-connect.livento-bildung.de') {
        return '';
    }
    return $url;
}

/** Ruecksprung an genau den Warenkorbartikel binden, der aus Campus Connect kommt. */
add_filter('woocommerce_add_cart_item_data', function ($cart_item_data, $product_id, $variation_id) {
    if (!isset($_GET['cc_return_url'])) return $cart_item_data;
    $return_url = livento_cc_checkout_return_url($_GET['cc_return_url']);
    if ($return_url !== '') $cart_item_data['_livento_cc_return_url'] = $return_url;
    return $cart_item_data;
}, 10, 3);

/** Nur homogene Campus-Warenkoerbe erhalten einen Order-Redirect. */
add_action('woocommerce_checkout_create_order', function ($order) {
    if (!function_exists('WC') || !WC()->cart) return;

    $return_url = '';
    foreach (WC()->cart->get_cart() as $cart_item) {
        $item_url = livento_cc_checkout_return_url($cart_item['_livento_cc_return_url'] ?? '');
        if ($item_url === '' || ($return_url !== '' && $item_url !== $return_url)) return;
        $return_url = $item_url;
    }
    if ($return_url !== '') $order->update_meta_data('_livento_cc_return_url', $return_url);
}, 10, 1);

/** Nach gueltiger Bestellbestaetigung zur gespeicherten Lernwelt-URL wechseln. */
add_action('template_redirect', function () {
    if (!function_exists('is_wc_endpoint_url') || !is_wc_endpoint_url('order-received')) return;
    if (!function_exists('wc_get_order')) return;

    $order_id = absint(get_query_var('order-received'));
    $order_key = isset($_GET['key']) ? wc_clean(wp_unslash($_GET['key'])) : '';
    $order = $order_id ? wc_get_order($order_id) : false;
    if (!$order || $order_key === '' || !hash_equals($order->get_order_key(), $order_key)) return;
    if ($order->has_status(array('failed', 'cancelled', 'refunded'))) return;

    $return_url = livento_cc_checkout_return_url($order->get_meta('_livento_cc_return_url', true));
    if ($return_url === '') return;

    nocache_headers();
    wp_redirect($return_url, 302, 'Livento Campus Connect');
    exit;
}, 20);

/* ============================================================
 * 11c. Alte Ticket-Seiten -> CampusTicket (v1.50.0, Campus Connect v4.0.0)
 *
 * Pflicht-, Komplett- und RollenTicket sind im CampusTicket aufgegangen. Ihre Adressen
 * (samt der verbrannten Vorgaengernamen) leiten dauerhaft auf die CampusTicket-Seite.
 * Laeuft mit Prioritaet 0, also VOR redirect_canonical und der 404-Suche von WordPress —
 * sonst entstuende eine Kette ueber die noch vorhandene Seite /e-learning/pflicht-ticket/.
 * Query-Parameter (utm_*, gclid) bleiben erhalten.
 * ============================================================ */
function livento_cc_old_ticket_paths() {
    return array(
        'e-learning/pflicht-ticket', 'e-learning/komplett-ticket', 'e-learning/rollen-ticket',
        'e-learning/pflichtstart', 'e-learning/pflegekomplett', 'e-learning/rollenplus',
        'pflicht-ticket', 'komplett-ticket', 'rollen-ticket',
    );
}

add_action('template_redirect', function () {
    if (is_admin() || empty($_SERVER['REQUEST_URI'])) return;
    $path = trim((string) wp_parse_url(wp_unslash($_SERVER['REQUEST_URI']), PHP_URL_PATH), '/');
    if (!in_array(strtolower($path), livento_cc_old_ticket_paths(), true)) return;

    $target = home_url('/e-learning/campus-ticket/');
    $query  = (string) wp_parse_url(wp_unslash($_SERVER['REQUEST_URI']), PHP_URL_QUERY);
    if ($query !== '') $target .= '?' . $query;

    wp_redirect($target, 301, 'Livento Kurskatalog');
    exit;
}, 0);

/* ============================================================
 * 11b. CampusTicket: Lizenz-Nachkauf (v1.48.0, Campus Connect v4.0.0)
 *
 * Beleg-Format (siehe supabase/functions/_shared/topup-token.ts):
 *   base64url(JSON {v:1, p:Paket-ID, s:Plaetze, a:Betrag brutto, e:Ablauf}) . base64url(HMAC)
 * ============================================================ */

function livento_cc_b64url_decode($value) {
    $value = strtr((string) $value, '-_', '+/');
    $pad = strlen($value) % 4;
    if ($pad) $value .= str_repeat('=', 4 - $pad);
    return base64_decode($value, true);
}

/** Prueft Signatur und Ablauf. Gibt array('p','s','a','token') oder null zurueck. */
function livento_cc_topup_verify($token) {
    $secret = livento_cc_topup_secret();
    if ($secret === '' || !is_string($token) || $token === '') return null;
    $parts = explode('.', $token);
    if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') return null;
    $given = livento_cc_b64url_decode($parts[1]);
    if ($given === false || !hash_equals(hash_hmac('sha256', $parts[0], $secret, true), $given)) return null;
    $json = livento_cc_b64url_decode($parts[0]);
    $claims = ($json === false) ? null : json_decode($json, true);
    if (!is_array($claims) || (int) ($claims['v'] ?? 0) !== 1) return null;
    if (empty($claims['p']) || !is_string($claims['p'])) return null;
    if (!is_int($claims['s'] ?? null) || $claims['s'] < 1) return null;
    if (!is_numeric($claims['a'] ?? null) || (float) $claims['a'] < 0) return null;
    if ((int) ($claims['e'] ?? 0) < time()) return null;
    return array('p' => $claims['p'], 's' => (int) $claims['s'], 'a' => round((float) $claims['a'], 2), 'token' => $token);
}

/** Das Nachkauf-Produkt kommt NUR mit gueltigem Beleg in den Warenkorb (steht auf 0 EUR). */
add_filter('woocommerce_add_to_cart_validation', function ($passed, $product_id) {
    $topup_product = livento_cc_topup_product_id();
    if ($topup_product === 0 || (int) $product_id !== $topup_product) return $passed;
    $token = isset($_GET['cc_topup']) ? sanitize_text_field(wp_unslash($_GET['cc_topup'])) : '';
    if (!livento_cc_topup_verify($token)) {
        wc_add_notice('Der Nachkauf-Link ist ungültig oder abgelaufen. Bitte starten Sie den Nachkauf erneut in Campus Connect.', 'error');
        return false;
    }
    return $passed;
}, 10, 2);

add_filter('woocommerce_add_cart_item_data', function ($cart_item_data, $product_id, $variation_id) {
    if ((int) $product_id !== livento_cc_topup_product_id() || !isset($_GET['cc_topup'])) return $cart_item_data;
    $claims = livento_cc_topup_verify(sanitize_text_field(wp_unslash($_GET['cc_topup'])));
    if ($claims) {
        $cart_item_data['_livento_cc_topup'] = $claims;
        $cart_item_data['unique_key'] = md5($claims['token']); // jeder Nachkauf eine eigene Position
    }
    return $cart_item_data;
}, 10, 3);

add_filter('woocommerce_add_to_cart_quantity', function ($quantity, $product_id) {
    return ((int) $product_id === livento_cc_topup_product_id()) ? 1 : $quantity;
}, 10, 2);

/** Preis ausschliesslich aus dem Beleg. */
add_action('woocommerce_before_calculate_totals', function ($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;
    foreach ($cart->get_cart() as $cart_item) {
        if (!empty($cart_item['_livento_cc_topup']['a'])) {
            $cart_item['data']->set_price((float) $cart_item['_livento_cc_topup']['a']);
        }
    }
}, 20, 1);

/** Menge im Warenkorb fest auf 1. */
add_filter('woocommerce_cart_item_quantity', function ($product_quantity, $cart_item_key, $cart_item) {
    return !empty($cart_item['_livento_cc_topup']) ? '1' : $product_quantity;
}, 10, 3);

add_filter('woocommerce_get_item_data', function ($item_data, $cart_item) {
    if (!empty($cart_item['_livento_cc_topup']['s'])) {
        $item_data[] = array('name' => 'Zusätzliche Lizenzen', 'value' => (string) $cart_item['_livento_cc_topup']['s']);
    }
    return $item_data;
}, 10, 2);

/** Beleg an die Bestellposition: der Webhook liest _cc_topup aus meta_data. */
add_action('woocommerce_checkout_create_order_line_item', function ($item, $cart_item_key, $values) {
    if (empty($values['_livento_cc_topup']['token'])) return;
    $item->add_meta_data('_cc_topup', $values['_livento_cc_topup']['token'], true);
    $item->add_meta_data('Zusätzliche Lizenzen', (string) $values['_livento_cc_topup']['s'], true);
}, 10, 3);

/* ============================================================
 * 12. Auto-Update via GitHub (Plugin Update Checker)
 *
 * Meldet neue Releases des Repos LIVENTO_CC_UPDATE_REPO als Plugin-Update im
 * WP-Dashboard (Ein-Klick-Update). Bibliothek liegt in plugin-update-checker/.
 * Guard: still, falls Repo leer oder Bibliothek nicht mitgeliefert.
 * ============================================================ */
if (defined('LIVENTO_CC_UPDATE_REPO') && LIVENTO_CC_UPDATE_REPO
    && file_exists(__DIR__ . '/plugin-update-checker/plugin-update-checker.php')) {

    require_once __DIR__ . '/plugin-update-checker/plugin-update-checker.php';

    if (class_exists('\\YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory')) {
        $lvk_uc = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
            LIVENTO_CC_UPDATE_REPO,
            __FILE__,
            'livento-kurskatalog'
        );
        $lvk_api = $lvk_uc->getVcsApi();
        if (method_exists($lvk_api, 'enableReleaseAssets')) {
            $lvk_api->enableReleaseAssets(); // WP laedt das angehaengte Release-Zip
        }
        if (defined('LIVENTO_CC_UPDATE_TOKEN') && LIVENTO_CC_UPDATE_TOKEN) {
            $lvk_uc->setAuthentication(LIVENTO_CC_UPDATE_TOKEN); // nur fuer PRIVATE Repos
        }
    }
}

/* ============================================================
 * 12. Förderprogramme (eigener Inhaltstyp, plugin-verwaltet)
 *
 * Verwaltung im Admin-Tab „Förderprogramme", Anzeige via [livento_foerderungen]
 * (Grid + Region-/Zielgruppen-Filter), eigene Detailseiten /<base>/<slug>/.
 * ============================================================ */

function livento_cc_foerder_regions() {
    return array(
        'bundesweit' => 'Bundesweit',
        'baden-wuerttemberg' => 'Baden-Württemberg', 'bayern' => 'Bayern', 'berlin' => 'Berlin',
        'brandenburg' => 'Brandenburg', 'bremen' => 'Bremen', 'hamburg' => 'Hamburg', 'hessen' => 'Hessen',
        'mecklenburg-vorpommern' => 'Mecklenburg-Vorpommern', 'niedersachsen' => 'Niedersachsen',
        'nordrhein-westfalen' => 'Nordrhein-Westfalen', 'rheinland-pfalz' => 'Rheinland-Pfalz',
        'saarland' => 'Saarland', 'sachsen' => 'Sachsen', 'sachsen-anhalt' => 'Sachsen-Anhalt',
        'schleswig-holstein' => 'Schleswig-Holstein', 'thueringen' => 'Thüringen',
    );
}
function livento_cc_foerder_audiences() {
    return array('privat' => 'Privatpersonen', 'unternehmen' => 'Unternehmen');
}
function livento_cc_foerder_icons() {
    return array(
        'bafoeg'      => '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10L12 5 2 10l10 5 10-5z"/><path d="M6 12v5c0 1 3 2 6 2s6-1 6-2v-5"/></svg>',
        'euro'        => '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M15 9.5a4 4 0 100 5M7 11h6M7 13h6"/></svg>',
        'gutschein'   => '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8a2 2 0 012-2h14a2 2 0 012 2v2a2 2 0 000 4v2a2 2 0 01-2 2H5a2 2 0 01-2-2v-2a2 2 0 000-4z"/><path d="M12 6v12" stroke-dasharray="2 2"/></svg>',
        'scheck'      => '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h4"/></svg>',
        'star'        => '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l2.9 5.9 6.5.9-4.7 4.6 1.1 6.5L12 18l-5.8 3 1.1-6.5L2.6 9.8l6.5-.9z"/></svg>',
        'building'    => '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 21V5a1 1 0 011-1h8a1 1 0 011 1v16M14 9h5a1 1 0 011 1v11M8 8h2M8 12h2M8 16h2"/></svg>',
        'certificate' => '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="9" r="5"/><path d="M9 13l-1 7 4-2 4 2-1-7"/></svg>',
        '_default'    => '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>',
    );
}

/** Förderprogramme: Admin-Option → Default-Seed. */
function livento_cc_foerderungen() {
    $opt = get_option('livento_cc_foerderungen', null);
    if (is_array($opt) && !empty($opt)) {
        return $opt;
    }
    return livento_cc_foerderungen_default();
}
function livento_cc_foerderungen_default() {
    return array(
        array('title' => 'Aufstiegs-BAföG (AFBG)', 'slug' => 'aufstiegs-bafoeg', 'icon' => 'bafoeg',
              'region' => array('bundesweit'), 'audience' => array('privat'), 'match' => array('staatl-abschluss', 'berufsrueckkehrer', 'fachkraft-u25'),
              'funding_key' => 'aufstiegs_bafoeg', 'link' => 'https://www.aufstiegs-bafoeg.de',
              'short' => 'Fördert die Vorbereitung auf über 700 Fortbildungsabschlüsse – mit einkommensunabhängigen Zuschüssen und zinsgünstigem Darlehen.',
              'body'  => "Das Aufstiegs-BAföG (AFBG) unterstützt berufliche Aufstiegsfortbildungen unabhängig vom Alter und Einkommen.\n\n- Maßnahmebeitrag: bis zu 15.000 € (50 % Zuschuss, Rest als Darlehen)\n- Unterhaltsbeitrag bei Vollzeit\n- Erlass eines Teils des Darlehens bei bestandener Prüfung\n\nGefördert werden u. a. Fachwirt-, Meister- und vergleichbare Fortbildungen."),
        array('title' => 'Bildungsgutschein', 'slug' => 'bildungsgutschein', 'icon' => 'gutschein',
              'region' => array('bundesweit'), 'audience' => array('privat'), 'match' => array('arbeitslosigkeit-bedroht', 'arbeitslos-gemeldet', 'ohne-abschluss', 'berufsrueckkehrer', 'wiedereinstieg'),
              'funding_key' => 'azav_bildungsgutschein', 'link' => 'https://www.arbeitsagentur.de',
              'short' => 'Die Agentur für Arbeit bzw. das Jobcenter kann bis zu 100 % der Weiterbildungskosten inkl. Nebenkosten übernehmen.',
              'body'  => "Mit dem Bildungsgutschein fördern Agentur für Arbeit oder Jobcenter eine AZAV-zertifizierte Weiterbildung – bei Arbeitslosigkeit, drohender Arbeitslosigkeit oder fehlendem Berufsabschluss.\n\n- Übernahme von bis zu 100 % der Lehrgangskosten\n- ggf. Fahrt-, Unterkunfts- und Kinderbetreuungskosten\n\nSprechen Sie Ihre Vermittlungsfachkraft an – unsere AZAV-Kurse sind förderfähig."),
        array('title' => 'Qualifizierungschancengesetz', 'slug' => 'qualifizierungschancengesetz', 'icon' => 'building',
              'region' => array('bundesweit'), 'audience' => array('unternehmen'), 'match' => array('aelterer-kmu', 'gering-qualifiziert', 'arbeitslosigkeit-bedroht'),
              'funding_key' => 'qcg', 'link' => 'https://www.arbeitsagentur.de',
              'short' => 'Förderung der Weiterbildung Beschäftigter – unabhängig von Qualifikation, Alter und Betriebsgröße.',
              'body'  => "Das Qualifizierungschancengesetz (QCG) fördert die Weiterbildung von Beschäftigten, deren Tätigkeiten durch Technologie ersetzt werden können oder die in einem Engpassberuf arbeiten.\n\n- Zuschüsse zu Lehrgangskosten (je nach Betriebsgröße)\n- Zuschüsse zum Arbeitsentgelt während der Weiterbildung"),
        array('title' => 'Fachkräfteoffensive Pflege (BW)', 'slug' => 'fachkraefteoffensive-pflege-bw', 'icon' => 'certificate',
              'region' => array('baden-wuerttemberg'), 'audience' => array('privat'), 'match' => array('berufsrueckkehrer', 'ohne-abschluss', 'wiedereinstieg', 'gering-qualifiziert'),
              'funding_key' => '', 'link' => '',
              'short' => 'Weiterbildungsförderung speziell für Quer- und Wiedereinsteiger:innen in die Pflege in Baden-Württemberg.',
              'body'  => "Die Fachkräfteoffensive Pflege in Baden-Württemberg unterstützt den (Wieder-)Einstieg in Pflegeberufe mit Qualifizierungs- und Weiterbildungsangeboten."),
        array('title' => 'Bildungsscheck M-V', 'slug' => 'bildungsscheck-mv', 'icon' => 'scheck',
              'region' => array('mecklenburg-vorpommern'), 'audience' => array('unternehmen'), 'match' => array('staatl-abschluss', 'aelterer-kmu', 'gering-qualifiziert'),
              'funding_key' => 'bildungsscheck', 'link' => '',
              'short' => '50–70 % Förderung der Weiterbildungskosten (max. 500 €, bis 3.000 € bei Abschlussqualifizierung).',
              'body'  => "Der Bildungsscheck Mecklenburg-Vorpommern fördert die berufliche Weiterbildung von Beschäftigten in kleinen und mittleren Unternehmen.\n\n- 50–70 % der Kosten\n- max. 500 € pro Scheck (bis 3.000 € bei Abschlussqualifizierung)"),
        array('title' => 'Meisterbonus Sachsen', 'slug' => 'meisterbonus-sachsen', 'icon' => 'star',
              'region' => array('sachsen'), 'audience' => array('privat'), 'match' => array('staatl-abschluss'),
              'funding_key' => '', 'link' => '',
              'short' => 'Einmalige Prämie von 2.000 € nach bestandener Meisterprüfung in Sachsen.',
              'body'  => "Der Meisterbonus Sachsen belohnt erfolgreiche Aufstiegsfortbildungen mit einer einmaligen Prämie von 2.000 € nach bestandener Meister- oder gleichwertiger Prüfung."),
    );
}
function livento_cc_foerder_get($slug) {
    $slug = sanitize_title($slug);
    if ($slug === '') {
        return null;
    }
    foreach (livento_cc_foerderungen() as $f) {
        if (isset($f['slug']) && $f['slug'] === $slug) {
            return $f;
        }
    }
    return null;
}
function livento_cc_foerder_url($slug) {
    return home_url(user_trailingslashit(LIVENTO_CC_FOERDER_BASE . '/' . sanitize_title($slug)));
}
function livento_cc_foerder_list_url() {
    return home_url(user_trailingslashit(LIVENTO_CC_FOERDER_BASE));
}

// Rewrite /<base>/<slug>/ + Query-Var
add_action('init', function () {
    add_rewrite_rule('^' . LIVENTO_CC_FOERDER_BASE . '/([^/]+)/?$',
        'index.php?pagename=' . LIVENTO_CC_FOERDER_BASE . '&foerderung=$matches[1]', 'top');
});
add_filter('query_vars', function ($v) { $v[] = 'foerderung'; return $v; });
register_activation_hook(__FILE__, function () {
    add_rewrite_rule('^' . LIVENTO_CC_FOERDER_BASE . '/([^/]+)/?$',
        'index.php?pagename=' . LIVENTO_CC_FOERDER_BASE . '&foerderung=$matches[1]', 'top');
    flush_rewrite_rules();
});

function livento_cc_foerder_current_slug() {
    $s = get_query_var('foerderung');
    if (!$s && isset($_GET['foerderung'])) {
        $s = sanitize_title(wp_unslash($_GET['foerderung']));
    }
    return $s ? sanitize_title($s) : '';
}

add_filter('redirect_canonical', function ($r) {
    return livento_cc_foerder_current_slug() ? false : $r;
});
add_action('wp', function () {
    $slug = livento_cc_foerder_current_slug();
    if (!$slug) {
        return;
    }
    $f = livento_cc_foerder_get($slug);
    $GLOBALS['livento_cc_foerder_current'] = $f;
    if (!$f) {
        add_action('wp_head', function () { echo '<meta name="robots" content="noindex,follow">' . "\n"; });
        status_header(404);
        return;
    }
    add_filter('pre_get_document_title', function () use ($f) {
        // v1.71.0: nicht mehr mb_substr(..., 0, 62) — das schnitt mitten im Wort.
        // Die uebrigen Programmtitel mitgeben, damit die Kuerzung keine zwei Seiten
        // gleich benennt.
        return livento_cc_titel_kuerzen(
            $f['title'], 60, ' – Förderung | Livento',
            wp_list_pluck(livento_cc_foerderungen(), 'title')
        );
    }, 20);
    add_action('wp_head', function () use ($f) {
        $url  = livento_cc_foerder_url($f['slug']);
        $desc = mb_substr(wp_strip_all_tags(!empty($f['short']) ? $f['short'] : $f['title']), 0, 160);
        echo "\n" . '<link rel="canonical" href="' . esc_url($url) . '">' . "\n";
        echo '<meta name="description" content="' . esc_attr($desc) . '">' . "\n";
        echo '<meta name="robots" content="index,follow">' . "\n";
        echo '<meta property="og:type" content="article"><meta property="og:title" content="' . esc_attr($f['title']) . '"><meta property="og:url" content="' . esc_url($url) . '">' . "\n";
    }, 1);
});

add_shortcode('livento_foerderungen', function ($atts) {
    $a = shortcode_atts(array('audience' => '', 'region' => '', 'filter' => 'yes'), $atts, 'livento_foerderungen');
    $slug = livento_cc_foerder_current_slug();
    if ($slug) {
        $f = isset($GLOBALS['livento_cc_foerder_current']) ? $GLOBALS['livento_cc_foerder_current'] : livento_cc_foerder_get($slug);
        $body = $f ? livento_cc_render_foerder_detail($f)
            : '<div class="lvf"><h1>Förderung nicht gefunden</h1><p><a href="' . esc_url(livento_cc_foerder_list_url()) . '">Zur Übersicht</a></p></div>';
        return livento_cc_foerder_styles() . $body;
    }
    return livento_cc_foerder_styles() . livento_cc_render_foerder_grid($a);
});

function livento_cc_render_foerder_grid($a) {
    $items   = livento_cc_foerderungen();
    $regions = livento_cc_foerder_regions();
    $auds    = livento_cc_foerder_audiences();
    $icons   = livento_cc_foerder_icons();

    // Optionaler Server-Vorfilter über Shortcode-Attribute.
    $fa = strtolower(trim((string) $a['audience']));
    $fr = strtolower(trim((string) $a['region']));
    if ($fa !== '') { $items = array_filter($items, function ($f) use ($fa) { return in_array($fa, (array) ($f['audience'] ?? array()), true); }); }
    if ($fr !== '') { $items = array_filter($items, function ($f) use ($fr) { return in_array($fr, (array) ($f['region'] ?? array()), true); }); }
    $items = array_values($items);
    if (empty($items)) {
        return '<div class="lvf"><p>Aktuell sind keine Förderprogramme hinterlegt.</p></div>';
    }

    $show_filter = (strtolower((string) $a['filter']) !== 'no');

    // Programm-Karten.
    $cards = '';
    foreach ($items as $f) {
        $cards .= livento_cc_foerder_card_html($f, $regions, $icons);
    }

    if (!$show_filter) {
        return '<div class="lvf"><div class="lvf-grid">' . $cards . '</div></div>';
    }

    // Zähler je Wert (für die Checkbox-Badges).
    $reg_counts = array(); $aud_counts = array();
    foreach ($items as $f) {
        foreach ((array) ($f['region'] ?? array()) as $r) { $reg_counts[$r] = (isset($reg_counts[$r]) ? $reg_counts[$r] : 0) + 1; }
        foreach ((array) ($f['audience'] ?? array()) as $u) { $aud_counts[$u] = (isset($aud_counts[$u]) ? $aud_counts[$u] : 0) + 1; }
    }

    $out  = '<div class="lvf" id="lvf-grid-wrap"><div class="lvf-layout">';
    $out .= '<aside class="lvf-sidebar">';
    $out .= '<div class="lvf-side-head"><span class="lvf-side-title">Filter</span><button type="button" class="lvf-reset" hidden>Zurücksetzen</button></div>';
    // Zielgruppe
    $out .= '<details class="lvf-fgroup" open><summary><span class="lvf-fgroup-title">Für</span></summary><div class="lvf-checks">';
    foreach ($auds as $k => $label) {
        $c = isset($aud_counts[$k]) ? $aud_counts[$k] : 0;
        $out .= '<label class="lvf-check"><input type="checkbox" data-dim="audience" value="' . esc_attr($k) . '"><span class="lvf-check-lbl">' . esc_html($label) . '</span><span class="lvf-check-cnt">' . (int) $c . '</span></label>';
    }
    $out .= '</div></details>';
    // Region — ALLE Bundesländer + Bundesweit
    $out .= '<details class="lvf-fgroup" open><summary><span class="lvf-fgroup-title">Region</span></summary><div class="lvf-checks">';
    foreach ($regions as $k => $label) {
        $c = isset($reg_counts[$k]) ? $reg_counts[$k] : 0;
        $out .= '<label class="lvf-check"><input type="checkbox" data-dim="region" value="' . esc_attr($k) . '"><span class="lvf-check-lbl">' . esc_html($label) . '</span><span class="lvf-check-cnt">' . (int) $c . '</span></label>';
    }
    $out .= '</div></details>';
    $out .= '</aside>';
    // Hauptbereich
    $out .= '<div class="lvf-main">';
    $out .= '<p class="lvf-count" aria-live="polite"></p>';
    $out .= '<div class="lvf-grid">' . $cards . '</div>';
    $out .= '<p class="lvf-empty" hidden>Keine Förderung für diese Auswahl. <button type="button" class="lvf-reset-2">Filter zurücksetzen</button></p>';
    $out .= '</div></div></div>';
    $out .= livento_cc_foerder_js();
    return $out;
}

/** Eine Förderprogramm-Kachel (für Grid + Berater-Ergebnis wiederverwendbar). */
function livento_cc_foerder_card_html($f, $regions, $icons) {
    $icon = isset($icons[$f['icon']]) ? $icons[$f['icon']] : $icons['_default'];
    $url  = livento_cc_foerder_url($f['slug']);
    $rd   = '|' . implode('|', (array) ($f['region'] ?? array())) . '|';
    $ad   = '|' . implode('|', (array) ($f['audience'] ?? array())) . '|';
    $badges = array();
    foreach ((array) ($f['region'] ?? array()) as $r) { $badges[] = isset($regions[$r]) ? $regions[$r] : $r; }
    $h  = '<a class="lvf-card" href="' . esc_url($url) . '" data-region="' . esc_attr($rd) . '" data-audience="' . esc_attr($ad) . '">';
    $h .= '<span class="lvf-ic" aria-hidden="true">' . $icon . '</span>';
    $h .= '<h3 class="lvf-t">' . esc_html($f['title']) . '</h3>';
    if (!empty($f['short'])) {
        $h .= '<p class="lvf-d">' . esc_html(mb_substr(wp_strip_all_tags($f['short']), 0, 150)) . '</p>';
    }
    if (!empty($badges)) {
        $h .= '<p class="lvf-meta">' . esc_html(implode(' · ', $badges)) . '</p>';
    }
    $h .= '<span class="lvf-cta">Mehr erfahren →</span></a>';
    return $h;
}

/** v1.56.0: Kursliste der Hub-Seite Bildungsgutschein (alle AZAV-Kurse, naechster Start zuerst). */
function livento_cc_bg_courses_html() {
    $offerings = array_values(array_filter(livento_cc_get_offerings(), 'livento_cc_is_bildungsgutschein'));
    $out  = livento_cc_styles() . livento_cc_kursliste_styles();
    $out .= '<div class="lvk lvk-kursliste lvf-bg-courses">';
    $out .= '<h2 class="lvk-kl-title">Weiterbildungen mit Bildungsgutschein</h2>';
    if (empty($offerings)) {
        $beratung = livento_cc_beratung_url();
        if ($beratung === '') {
            $beratung = home_url('/kontakt/');
        }
        $out .= '<p class="lvk-kl-sub">Aktuell sind keine Termine veröffentlicht. Sprich uns an – wir informieren dich über die nächsten Durchgänge.</p>';
        $out .= '<p><a class="lvk-kl-btn" href="' . esc_url($beratung) . '">Kostenlose Beratung →</a></p>';
        return $out . '</div>';
    }
    $out .= '<p class="lvk-kl-sub">Diese Weiterbildungen sind AZAV-zugelassen und können über einen Bildungsgutschein gefördert werden.</p>';
    $offerings = livento_cc_sort_offerings(livento_cc_augment($offerings), 'next_start');
    $out .= '<div class="lvk-grid lvk-kl-grid">';
    foreach ($offerings as $o) {
        $out .= livento_cc_render_card($o);
    }
    $out .= '</div>';
    $out .= '<div class="lvk-kl-cta"><a class="lvk-kl-btn" href="' . esc_url(livento_cc_list_url() . '?azav=1') . '">Im Kurskatalog filtern →</a></div>';
    return $out . '</div>';
}

function livento_cc_render_foerder_detail($f) {
    $regions = livento_cc_foerder_regions();
    $auds    = livento_cc_foerder_audiences();
    $out  = '<div class="lvf lvf-detail">';
    $out .= '<p class="lvf-back"><a href="' . esc_url(livento_cc_foerder_list_url()) . '">← Alle Fördermöglichkeiten</a></p>';
    $out .= '<h1 class="lvf-dt">' . esc_html($f['title']) . '</h1>';
    $tags = array();
    foreach ((array) ($f['audience'] ?? array()) as $u) { $tags[] = isset($auds[$u]) ? $auds[$u] : $u; }
    foreach ((array) ($f['region'] ?? array()) as $r) { $tags[] = isset($regions[$r]) ? $regions[$r] : $r; }
    if (!empty($tags)) {
        $out .= '<div class="lvf-badges">';
        foreach ($tags as $t) { $out .= '<span class="lvf-badge">' . esc_html($t) . '</span>'; }
        $out .= '</div>';
    }
    if (!empty($f['short'])) {
        $out .= '<p class="lvf-lead">' . esc_html($f['short']) . '</p>';
    }
    if (!empty($f['body'])) {
        $out .= '<div class="lvf-bodytext">' . livento_cc_richtext($f['body']) . '</div>';
    }
    $is_bg = (($f['funding_key'] ?? '') === 'azav_bildungsgutschein');
    if (!empty($f['funding_key']) && !$is_bg) {
        $kurl = livento_cc_list_url() . '?funding=' . rawurlencode($f['funding_key']);
        $out .= '<p><a class="lvf-btn" href="' . esc_url($kurl) . '">Passende Kurse ansehen →</a></p>';
    }
    if (!empty($f['link'])) {
        $out .= '<p><a class="lvf-btn lvf-btn-ghost" href="' . esc_url($f['link']) . '" target="_blank" rel="noopener nofollow">Offizielle Informationen ↗</a></p>';
    }
    // v1.56.0: Hub-Seite Bildungsgutschein — alle AZAV-Kurse serverseitig (crawlbar).
    if ($is_bg) {
        $out .= livento_cc_bg_courses_html();
    }
    // Kein eigener Footer — WordPress-Theme liefert ihn seitenweit (v1.19.0).
    $out .= '</div>';
    return $out;
}

function livento_cc_foerder_js() {
    static $done = false;
    if ($done) { return ''; }
    $done = true;
    return <<<'JS'
<script>
(function(){
  var root = document.getElementById('lvf-grid-wrap');
  if(!root) return;
  var cards  = Array.prototype.slice.call(root.querySelectorAll('.lvf-card'));
  var checks = Array.prototype.slice.call(root.querySelectorAll('.lvf-check input[type=checkbox]'));
  var reset  = root.querySelector('.lvf-reset');
  var reset2 = root.querySelector('.lvf-reset-2');
  var countEl= root.querySelector('.lvf-count');
  var emptyEl= root.querySelector('.lvf-empty');
  var total  = cards.length;
  function active(){
    var a={region:[],audience:[]};
    checks.forEach(function(c){ if(c.checked){ a[c.getAttribute('data-dim')].push(c.value); } });
    return a;
  }
  function apply(){
    var a=active(), shown=0;
    cards.forEach(function(c){
      var ok=true;
      ['region','audience'].forEach(function(d){
        if(a[d].length){
          var vals=(c.getAttribute('data-'+d)||'').split('|').filter(function(x){return x;}), hit=false;
          for(var i=0;i<a[d].length;i++){ if(vals.indexOf(a[d][i])>-1){hit=true;break;} }
          if(!hit) ok=false;
        }
      });
      c.style.display=ok?'':'none'; if(ok)shown++;
    });
    var any=a.region.length||a.audience.length;
    if(reset)  reset.hidden=!any;
    if(countEl)countEl.textContent = any ? (shown+' von '+total) : '';
    if(emptyEl)emptyEl.hidden = !(any && shown===0);
  }
  checks.forEach(function(c){ c.addEventListener('change', apply); });
  function clearAll(){ checks.forEach(function(c){ c.checked=false; }); apply(); }
  if(reset)  reset.addEventListener('click', clearAll);
  if(reset2) reset2.addEventListener('click', clearAll);
})();
</script>
JS;
}

function livento_cc_foerder_styles() {
    static $done = false;
    if ($done) { return ''; }
    $done = true;
    $css = '
.lvf{--lvf-green:#004D33;--lvf-lime:#5C8A30;--lvf-accent:#a4d07b;color:#222;line-height:1.6}
.lvf a{color:var(--lvf-green)}
.lvf-layout{display:flex;gap:26px;align-items:flex-start}
.lvf-sidebar{flex:0 0 230px;position:sticky;top:20px}
.lvf-main{flex:1;min-width:0}
.lvf-side-head{display:flex;align-items:center;justify-content:space-between;margin:0 0 10px;padding-bottom:8px;border-bottom:2px solid #eef2ea}
.lvf-side-title{font-weight:700;color:var(--lvf-green);font-size:1rem}
.lvf-reset,.lvf-reset-2{background:none;border:none;color:var(--lvf-lime);text-decoration:underline;cursor:pointer;font-size:.82rem;padding:0}
.lvf-fgroup{border-bottom:1px solid #eef2ea;padding:8px 0}
.lvf-fgroup>summary{list-style:none;cursor:pointer;display:flex;align-items:center;justify-content:space-between;font-weight:600;color:#2b3a2b;font-size:.92rem;padding:4px 0}
.lvf-fgroup>summary::-webkit-details-marker{display:none}
.lvf-fgroup>summary::after{content:"\\25BE";color:#9aa6a0;font-size:.7rem;transition:transform .15s}
.lvf-fgroup[open]>summary::after{transform:rotate(180deg)}
.lvf-checks{display:flex;flex-direction:column;gap:2px;margin:6px 0 4px;max-height:280px;overflow:auto}
.lvf-check{display:flex;align-items:center;gap:8px;padding:4px 6px;border-radius:6px;cursor:pointer;font-size:.9rem;color:#2b3a2b}
.lvf-check:hover{background:#f3f8ee}
.lvf-check input{accent-color:var(--lvf-green);width:16px;height:16px;margin:0;flex:0 0 auto}
.lvf-check-lbl{flex:1}
.lvf-check-cnt{color:#9aa6a0;font-size:.78rem}
.lvf-count{color:var(--lvf-lime);font-size:.85rem;margin:0 0 12px;min-height:1em}
.lvf-empty{color:#555;font-size:.92rem;margin:16px 0}
.lvf-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:20px}
.lvf-card{display:flex;flex-direction:column;background:#fff;border:1px solid #eee;border-radius:14px;padding:24px 22px;text-decoration:none!important;min-height:150px;transition:transform .18s,box-shadow .18s,border-color .18s}
.lvf-card:hover,.lvf-card:focus{transform:translateY(-3px);box-shadow:0 12px 26px rgba(0,77,51,.10);border-color:var(--lvf-accent);text-decoration:none!important}
.lvf-ic{display:inline-flex;align-items:center;justify-content:center;width:48px;height:48px;border-radius:12px;margin-bottom:14px;background:rgba(164,208,123,.22);color:var(--lvf-green)}
.lvf-t{margin:0 0 8px;font-size:1.1rem;color:var(--lvf-green)!important}
.lvf-d{margin:0 0 8px;font-size:.9rem;color:#444!important}
.lvf-meta{margin:0;font-size:.8rem;color:var(--lvf-lime)!important}
.lvf-cta{margin-top:auto;padding-top:12px;font-weight:600;color:var(--lvf-green)!important}
.lvf-card:hover .lvf-t,.lvf-card:hover .lvf-cta{color:#006644!important}
.lvf-detail{max-width:760px;margin:0 auto}
.lvf-dt{color:var(--lvf-green);font-size:2rem;line-height:1.2;margin:.2em 0}
.lvf-badges{display:flex;flex-wrap:wrap;gap:6px;margin:8px 0 16px}
.lvf-badge{background:#e6f0ec;color:var(--lvf-green);padding:3px 10px;border-radius:99px;font-size:.78rem;font-weight:600}
.lvf-lead{font-size:1.1rem;color:#444}
.lvf-bodytext{margin:16px 0}
.lvf a.lvf-btn{display:inline-block;background:var(--lvf-green)!important;color:#fff!important;text-decoration:none!important;padding:12px 24px;border-radius:8px;font-weight:600;margin:4px 0}
.lvf a.lvf-btn:hover{background:#006644!important}
.lvf a.lvf-btn-ghost{background:#fff!important;color:var(--lvf-green)!important;border:1px solid var(--lvf-green)}
@media(max-width:782px){
  .lvf-layout{flex-direction:column}
  .lvf-sidebar{flex:1 1 auto;width:100%;position:static}
  .lvf-checks{flex-direction:row;flex-wrap:wrap;max-height:none}
  .lvf-check{flex:0 0 auto}
}
';
    return '<style id="lvf-styles">' . $css . '</style>';
}

/** Verwaltungs-Block „Kurse-Förder-Tags": selbst verwaltbare Zusatz-Tags fuers funding_key-Dropdown. */
function livento_cc_admin_funding_tags_section() {
    $custom = livento_cc_funding_tags_custom(); // slug => label

    echo '<hr style="margin:30px 0 18px">';
    echo '<h2 style="margin:0 0 4px">Kurse-Förder-Tags</h2>';
    echo '<p style="margin:0 0 6px;max-width:900px">Diese Tags erscheinen im Dropdown <strong>„Kurse-Förder-Tag"</strong> jeder Förderprogramm-Karte (oben). Hier legst du eigene Tags an, benennst sie um oder entfernst sie.</p>';
    echo '<p class="description" style="max-width:900px;margin:0 0 12px">Hinweis: Ein eigener Tag <strong>filtert nur dann Kurse</strong> unter <code>/' . esc_html(LIVENTO_CC_BASE) . '/?funding=…</code>, wenn Campus Connect denselben Förder-Wert kennt. Sonst dient er als reines Label bzw. Verlinkungsziel. Die 9 Standard-Tags (AZAV-Bildungsgutschein, Aufstiegs-BAföG, …) sind fest hinterlegt und erscheinen ohnehin.</p>';

    echo '<form method="post">';
    wp_nonce_field('livento_cc_save_ftags');
    echo '<div id="lvft-rows">';
    $i = 0;
    foreach ($custom as $slug => $label) {
        echo livento_cc_admin_funding_tag_row((string) $i, (string) $slug, (string) $label);
        $i++;
    }
    echo '</div>';
    echo '<template id="lvft-tpl">' . livento_cc_admin_funding_tag_row('__IDX__', '', '') . '</template>';
    echo '<p><button type="button" class="button" id="lvft-add">+ Förder-Tag hinzufügen</button></p>';
    echo '<p style="margin-top:12px">';
    echo '<button type="submit" name="livento_cc_save_ftags" value="1" class="button button-primary">Förder-Tags speichern</button> ';
    echo '<button type="submit" name="livento_cc_reset_ftags" value="1" class="button" onclick="return confirm(\'Eigene Förder-Tags auf Standard (nur „Anpassungsqualifizierung") zurücksetzen?\')">Auf Standard zurücksetzen</button>';
    echo '</p></form>';
    echo "<script>(function(){var box=document.getElementById('lvft-rows'),tpl=document.getElementById('lvft-tpl'),n=0;var add=document.getElementById('lvft-add');if(add){add.addEventListener('click',function(){n++;var d=document.createElement('div');d.innerHTML=tpl.innerHTML.replace(/__IDX__/g,'new'+n);box.appendChild(d.firstElementChild);});}box.addEventListener('click',function(e){if(e.target&&e.target.classList.contains('lvft-del')){var r=e.target.closest('.lvft-row');if(r)r.parentNode.removeChild(r);}});})();</script>";
}

/** Eine editierbare Förder-Tag-Zeile (Bezeichnung + optionaler Slug). */
function livento_cc_admin_funding_tag_row($idx, $slug, $label) {
    $n = 'lvk_ft[' . esc_attr($idx) . ']';
    $h  = '<div class="lvft-row" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;border:1px solid #dcdcde;border-radius:6px;padding:10px 12px;margin:0 0 8px;background:#fff;max-width:900px">';
    $h .= '<label style="flex:1;min-width:220px">Bezeichnung<br><input type="text" name="' . $n . '[label]" value="' . esc_attr($label) . '" class="regular-text" placeholder="z. B. Anpassungsqualifizierung"></label>';
    $h .= '<label style="flex:1;min-width:200px">Slug <span class="description">(optional, sonst aus Bezeichnung)</span><br><input type="text" name="' . $n . '[slug]" value="' . esc_attr($slug) . '" class="regular-text" placeholder="anpassungsqualifizierung"></label>';
    $h .= '<button type="button" class="button-link lvft-del" style="color:#b32d2e;margin-bottom:6px">Entfernen</button>';
    $h .= '</div>';
    return $h;
}

/** Tab „Förderprogramme": Editor (Karten mit Feldern). */
function livento_cc_admin_tab_foerderung() {
    $current  = livento_cc_foerderungen();
    $regions  = livento_cc_foerder_regions();
    $auds     = livento_cc_foerder_audiences();
    $icons    = array_keys(livento_cc_foerder_icons());
    $fundings = livento_cc_funding_labels();
    $quals    = livento_cc_foerder_quals_flat();

    echo '<p style="margin-top:12px">Förderprogramme für <code>[livento_foerderungen]</code> + Detailseiten unter <code>/' . esc_html(LIVENTO_CC_FOERDER_BASE) . '/&lt;slug&gt;/</code>. Nach dem ersten Speichern bitte einmal <em>Einstellungen → Permalinks → Speichern</em> (für die Detail-URLs).</p>';
    echo '<form method="post">';
    wp_nonce_field('livento_cc_save_foerder');
    echo '<div id="lvf-rows">';
    $i = 0;
    foreach ($current as $f) {
        echo livento_cc_admin_foerder_card((string) $i, $f, $regions, $auds, $icons, $fundings, $quals);
        $i++;
    }
    echo '</div>';
    echo '<template id="lvf-tpl">' . livento_cc_admin_foerder_card('__IDX__', array(), $regions, $auds, $icons, $fundings, $quals) . '</template>';
    echo '<p><button type="button" class="button" id="lvf-add">+ Förderprogramm hinzufügen</button></p>';
    echo '<p style="margin-top:16px">';
    echo '<button type="submit" name="livento_cc_save_foerder" value="1" class="button button-primary">Speichern</button> ';
    echo '<button type="submit" name="livento_cc_reset_foerder" value="1" class="button" onclick="return confirm(\'Wirklich auf die Standard-Programme zurücksetzen?\')">Auf Standard zurücksetzen</button>';
    echo '</p></form>';
    echo "<script>(function(){var box=document.getElementById('lvf-rows'),tpl=document.getElementById('lvf-tpl'),n=0;var add=document.getElementById('lvf-add');if(add){add.addEventListener('click',function(){n++;var d=document.createElement('div');d.innerHTML=tpl.innerHTML.replace(/__IDX__/g,'new'+n);box.appendChild(d.firstElementChild);});}box.addEventListener('click',function(e){if(e.target&&e.target.classList.contains('lvf-del')){var r=e.target.closest('.lvf-card-edit');if(r)r.parentNode.removeChild(r);}});})();</script>";

    // ---- Kurse-Förder-Tags (selbst verwaltbare Zusatz-Tags fuers funding_key-Dropdown) ----
    livento_cc_admin_funding_tags_section();

    // ---- Förderberater-Schema (Status → bedingte Qualifikation) ----
    $stati = livento_cc_foerder_status();
    echo '<hr style="margin:30px 0 18px">';
    echo '<h2 style="margin:0 0 4px">Förderberater-Schema</h2>';
    echo '<p style="margin:0 0 12px">Status-Schritte + bedingte Qualifikationen für <code>[livento_foerder_berater]</code>. Pro Qualifikation eine Zeile im Format <code>schlüssel | Anzeigetext</code> (Schlüssel optional — ohne wird er aus dem Text erzeugt). <strong>Schlüssel stabil halten</strong>: die Programm-Zuordnung oben referenziert sie.</p>';
    echo '<form method="post">';
    wp_nonce_field('livento_cc_save_fstatus');
    echo '<div id="lvfs-rows">';
    $j = 0;
    foreach ($stati as $s) {
        echo livento_cc_admin_foerder_status_card((string) $j, $s);
        $j++;
    }
    echo '</div>';
    echo '<template id="lvfs-tpl">' . livento_cc_admin_foerder_status_card('__IDX__', array()) . '</template>';
    echo '<p><button type="button" class="button" id="lvfs-add">+ Status hinzufügen</button></p>';
    echo '<p style="margin-top:16px"><button type="submit" name="livento_cc_save_fstatus" value="1" class="button button-primary">Schema speichern</button> ';
    echo '<button type="submit" name="livento_cc_reset_fstatus" value="1" class="button" onclick="return confirm(\'Schema wirklich auf Standard zurücksetzen?\')">Auf Standard zurücksetzen</button></p></form>';
    echo "<script>(function(){var box=document.getElementById('lvfs-rows'),tpl=document.getElementById('lvfs-tpl'),n=0;var add=document.getElementById('lvfs-add');if(add){add.addEventListener('click',function(){n++;var d=document.createElement('div');d.innerHTML=tpl.innerHTML.replace(/__IDX__/g,'fnew'+n);box.appendChild(d.firstElementChild);});}box.addEventListener('click',function(e){if(e.target&&e.target.classList.contains('lvfs-del')){var r=e.target.closest('.lvfs-card');if(r)r.parentNode.removeChild(r);}});})();</script>";
}

/** Eine editierbare Förderberater-Status-Karte (Label + Frage + Qualifikationen). */
function livento_cc_admin_foerder_status_card($idx, $s) {
    $label = isset($s['label']) ? $s['label'] : '';
    $q     = isset($s['question']) ? $s['question'] : '';
    $lines = '';
    foreach ((isset($s['quals']) ? $s['quals'] : array()) as $ql) {
        $lines .= (isset($ql['key']) ? $ql['key'] : '') . ' | ' . (isset($ql['label']) ? $ql['label'] : '') . "\n";
    }
    $n = 'lvk_fst[' . esc_attr($idx) . ']';
    $h  = '<div class="lvfs-card" style="border:1px solid #dcdcde;border-radius:6px;padding:14px;margin:0 0 12px;background:#fff;max-width:980px">';
    $h .= '<p style="margin:0 0 8px;display:flex;gap:10px;flex-wrap:wrap">';
    $h .= '<input type="text" name="' . $n . '[label]" value="' . esc_attr($label) . '" placeholder="Status-Label (z. B. berufstätig)" style="flex:1 1 240px">';
    $h .= '<input type="text" name="' . $n . '[question]" value="' . esc_attr($q) . '" placeholder="Frage (z. B. Ich bin berufstätig und …?)" style="flex:2 1 320px"></p>';
    $h .= '<p style="margin:0 0 8px"><textarea name="' . $n . '[quals]" rows="5" class="large-text code" placeholder="schlüssel | Anzeigetext (eine Qualifikation pro Zeile)">' . esc_textarea(trim($lines)) . '</textarea></p>';
    $h .= '<p style="margin:0"><button type="button" class="button-link lvfs-del" style="color:#b32d2e">Status entfernen</button></p>';
    $h .= '</div>';
    return $h;
}

/** Eine editierbare Förderprogramm-Karte. */
function livento_cc_admin_foerder_card($idx, $f, $regions, $auds, $icons, $fundings, $quals = array()) {
    $title = isset($f['title']) ? $f['title'] : '';
    $slug  = isset($f['slug']) ? $f['slug'] : '';
    $icon  = isset($f['icon']) ? $f['icon'] : '_default';
    $reg   = isset($f['region']) ? (array) $f['region'] : array();
    $aud   = isset($f['audience']) ? (array) $f['audience'] : array();
    $mtch  = isset($f['match']) ? (array) $f['match'] : array();
    $fk    = isset($f['funding_key']) ? $f['funding_key'] : '';
    $link  = isset($f['link']) ? $f['link'] : '';
    $short = isset($f['short']) ? $f['short'] : '';
    $body  = isset($f['body']) ? $f['body'] : '';
    $n = 'lvk_foe[' . esc_attr($idx) . ']';

    $h  = '<div class="lvf-card-edit" style="border:1px solid #dcdcde;border-radius:6px;padding:14px;margin:0 0 12px;background:#fff;max-width:980px">';
    $h .= '<p style="margin:0 0 8px;display:flex;gap:10px;flex-wrap:wrap">';
    $h .= '<input type="text" name="' . $n . '[title]" value="' . esc_attr($title) . '" placeholder="Titel" style="flex:2 1 280px">';
    $h .= '<input type="text" name="' . $n . '[slug]" value="' . esc_attr($slug) . '" placeholder="slug (optional)" style="flex:1 1 170px">';
    $h .= '<select name="' . $n . '[icon]">';
    foreach ($icons as $ic) { $h .= '<option value="' . esc_attr($ic) . '"' . ($ic === $icon ? ' selected' : '') . '>' . esc_html($ic) . '</option>'; }
    $h .= '</select></p>';
    $h .= '<p style="margin:0 0 8px"><strong>Für:</strong> ';
    foreach ($auds as $k => $label) {
        $h .= '<label style="margin-right:12px"><input type="checkbox" name="' . $n . '[audience][]" value="' . esc_attr($k) . '"' . (in_array($k, $aud, true) ? ' checked' : '') . '> ' . esc_html($label) . '</label>';
    }
    $h .= ' &nbsp; <strong>Kurse-Förder-Tag:</strong> <select name="' . $n . '[funding_key]"><option value="">— keiner —</option>';
    foreach ($fundings as $fkey => $flabel) { $h .= '<option value="' . esc_attr($fkey) . '"' . ($fkey === $fk ? ' selected' : '') . '>' . esc_html($flabel) . '</option>'; }
    $h .= '</select></p>';
    $h .= '<p style="margin:0 0 8px"><strong>Region:</strong><br><select name="' . $n . '[region][]" multiple size="4" style="min-width:280px">';
    foreach ($regions as $k => $label) { $h .= '<option value="' . esc_attr($k) . '"' . (in_array($k, $reg, true) ? ' selected' : '') . '>' . esc_html($label) . '</option>'; }
    $h .= '</select> <span class="description">(Strg/Cmd-Klick für mehrere)</span></p>';
    $h .= '<p style="margin:0 0 8px"><input type="text" name="' . $n . '[short]" value="' . esc_attr($short) . '" placeholder="Kurzbeschreibung" class="large-text"></p>';
    $h .= '<p style="margin:0 0 8px"><input type="url" name="' . $n . '[link]" value="' . esc_attr($link) . '" placeholder="Offizieller Link (optional)" class="large-text code"></p>';
    $h .= '<p style="margin:0 0 8px"><textarea name="' . $n . '[body]" rows="4" class="large-text code" placeholder="Ausführliche Beschreibung (Markdown: **fett**, - Liste, [Text](URL))">' . esc_textarea($body) . '</textarea></p>';
    // v1.56.0: Text fuer den Abschnitt „Foerderung mit Bildungsgutschein" auf AZAV-Kursseiten.
    $ktext = isset($f['kurs_text']) ? $f['kurs_text'] : '';
    $h .= '<details style="margin:0 0 8px"' . ($fk === 'azav_bildungsgutschein' ? ' open' : '') . '><summary style="cursor:pointer"><strong>Text für Kursseiten</strong> <span class="description">(nur wirksam beim Programm mit Kurse-Förder-Tag „AZAV-Bildungsgutschein“: erscheint als Abschnitt auf jeder AZAV-Kursseite; leer = Standardtext; die Maßnahme-Nr. ergänzt das Plugin selbst)</span></summary>';
    $h .= '<textarea name="' . $n . '[kurs_text]" rows="7" class="large-text code" style="margin-top:6px" placeholder="' . esc_attr(livento_cc_bg_kurs_text_default()) . '">' . esc_textarea($ktext) . '</textarea></details>';
    if (!empty($quals)) {
        $h .= '<details style="margin:0 0 8px"><summary style="cursor:pointer"><strong>Förderberater: passt zu …</strong> <span class="description">(welche Situationen → zeigt dieses Programm im Ergebnis)</span></summary><div style="columns:2;margin-top:8px">';
        foreach ($quals as $qkey => $qlabel) {
            $h .= '<label style="display:block;margin:2px 0;break-inside:avoid"><input type="checkbox" name="' . $n . '[match][]" value="' . esc_attr($qkey) . '"' . (in_array($qkey, $mtch, true) ? ' checked' : '') . '> ' . esc_html($qlabel) . '</label>';
        }
        $h .= '</div></details>';
    }
    $h .= '<p style="margin:0"><button type="button" class="button-link lvf-del" style="color:#b32d2e">Entfernen</button></p>';
    $h .= '</div>';
    return $h;
}

/* ============================================================
 * 13. Förderberater (SGD-Stil: Status → Qualifikation → Angaben → Ergebnis)
 * ============================================================ */

/** Status → bedingte Qualifikationen (Admin-Option → Default). */
function livento_cc_foerder_status() {
    $opt = get_option('livento_cc_foerder_status', null);
    if (is_array($opt) && !empty($opt)) {
        return $opt;
    }
    return livento_cc_foerder_status_default();
}
function livento_cc_foerder_status_default() {
    return array(
        array('key' => 'berufstaetig', 'label' => 'berufstätig', 'question' => 'Ich bin berufstätig und …?', 'quals' => array(
            array('key' => 'staatl-abschluss', 'label' => '… strebe einen öffentlichen/rechtlichen/staatlichen Abschluss an.'),
            array('key' => 'aelterer-kmu', 'label' => '… bin ein älterer Beschäftigter in einem KMU.'),
            array('key' => 'gering-qualifiziert', 'label' => '… bin gering qualifiziert.'),
            array('key' => 'berufsrueckkehrer', 'label' => '… bin Berufsrückkehrer:in.'),
            array('key' => 'fachkraft-u25', 'label' => '… bin eine talentierte Fachkraft unter 25 Jahren.'),
            array('key' => 'arbeitslosigkeit-bedroht', 'label' => '… bin von Arbeitslosigkeit bedroht.'),
        )),
        array('key' => 'soldat', 'label' => 'Soldat:in', 'question' => 'Ich bin Soldat:in und …?', 'quals' => array(
            array('key' => 'bfd', 'label' => '… habe Anspruch auf den Berufsförderungsdienst (BFD).'),
        )),
        array('key' => 'nicht-berufstaetig', 'label' => 'nicht berufstätig', 'question' => 'Ich bin derzeit nicht berufstätig und …?', 'quals' => array(
            array('key' => 'arbeitslos-gemeldet', 'label' => '… bin arbeitslos gemeldet.'),
            array('key' => 'ohne-abschluss', 'label' => '… habe keinen Berufsabschluss.'),
            array('key' => 'wiedereinstieg', 'label' => '… möchte wieder in den Beruf einsteigen.'),
        )),
        array('key' => 'schueler-azubi-student', 'label' => 'Schüler:in, Azubi, Studierende:r', 'question' => 'Ich bin …?', 'quals' => array(
            array('key' => 'schueler', 'label' => '… Schüler:in.'),
            array('key' => 'azubi', 'label' => '… Auszubildende:r.'),
            array('key' => 'studierend', 'label' => '… Studierende:r.'),
        )),
    );
}
/** Flache Liste aller Qualifikationen key => label (für Programm-Zuordnung). */
function livento_cc_foerder_quals_flat() {
    $out = array();
    foreach (livento_cc_foerder_status() as $s) {
        foreach ((isset($s['quals']) ? $s['quals'] : array()) as $q) {
            if (!empty($q['key'])) {
                $out[$q['key']] = $q['label'];
            }
        }
    }
    return $out;
}
/** GHL-Formular für den Förderberater (eigene Option → Fallback auf Kursberater-Form). */
function livento_cc_foerder_form() {
    $f = (string) get_option('livento_cc_foerder_form', '');
    return trim($f) !== '' ? $f : (string) get_option('livento_cc_berater_form', '');
}

add_shortcode('livento_foerder_berater', function ($atts) {
    $a = shortcode_atts(array('title' => 'Förderberatung für deine Weiterbildung', 'intro' => '', 'form' => 'yes'), $atts, 'livento_foerder_berater');
    $stati = livento_cc_foerder_status();
    if (empty($stati)) {
        return '';
    }
    $programs = livento_cc_foerderungen();
    $icons    = livento_cc_foerder_icons();
    $ghl      = livento_cc_foerder_form();
    $use_form = (strtolower((string) $a['form']) !== 'no') && livento_cc_has_angaben('foerder', $ghl);

    $steps = array('Dein Status', 'Deine Qualifikation');
    if ($use_form) { $steps[] = 'Deine Angaben'; }
    $steps[] = 'Dein Ergebnis';
    $total = count($steps);

    $out  = livento_cc_styles() . livento_cc_foerder_styles();
    $out .= '<div class="lvk lvk-berater" id="lvfb-berater" data-total="' . (int) $total . '">';
    if ($a['title'] !== '') { $out .= '<h2 class="lvk-bx-title">' . esc_html($a['title']) . '</h2>'; }
    if ($a['intro'] !== '') { $out .= '<p class="lvk-bx-intro">' . esc_html($a['intro']) . '</p>'; }

    $out .= '<ol class="lvk-bx-stepper">';
    foreach ($steps as $i => $s) {
        $out .= '<li class="lvk-bx-stp' . ($i === 0 ? ' is-active' : '') . '"><span class="lvk-bx-dot"></span><span class="lvk-bx-lbl">' . esc_html($s) . '</span></li>';
    }
    $out .= '</ol>';

    // Schritt 1 — Status (Einfachauswahl)
    $out .= '<div class="lvk-bx-step" data-key="status">';
    $out .= '<h3 class="lvk-bx-q">Ich bin derzeit …?</h3>';
    $out .= '<div class="lvk-bx-list">';
    foreach ($stati as $s) {
        $out .= '<button type="button" class="lvk-bx-row lvfb-status" data-status="' . esc_attr($s['key']) . '">' . esc_html($s['label']) . '</button>';
    }
    $out .= '</div></div>';

    // Schritt 2 — Qualifikation (bedingte Gruppen je Status, Mehrfachauswahl)
    $out .= '<div class="lvk-bx-step" data-key="qual" hidden>';
    foreach ($stati as $s) {
        $out .= '<div class="lvfb-qgroup" data-status="' . esc_attr($s['key']) . '" hidden>';
        $out .= '<h3 class="lvk-bx-q">' . esc_html(isset($s['question']) ? $s['question'] : 'Was trifft auf dich zu?') . '</h3>';
        $out .= '<p class="lvk-bx-hint">Mehrfachauswahl möglich.</p><div class="lvk-bx-list">';
        foreach ((isset($s['quals']) ? $s['quals'] : array()) as $q) {
            $out .= '<button type="button" class="lvk-bx-row lvfb-qual" data-qual="' . esc_attr($q['key']) . '">' . esc_html($q['label']) . '</button>';
        }
        $out .= '</div></div>';
    }
    $out .= '</div>';

    // Schritt 3 — Angaben (GHL)
    if ($use_form) {
        $out .= '<div class="lvk-bx-step" data-key="angaben" hidden>';
        $out .= '<h3 class="lvk-bx-q">Fast geschafft!</h3>';
        $out .= '<p class="lvk-bx-hint">Nach dem Ausfüllen erhältst du deine passenden Fördermöglichkeiten.</p>';
        $out .= livento_cc_angaben_inner('foerder', $ghl) . '</div>';
    }

    // Schritt 4 — Ergebnis (Förder-Karten, JS filtert nach Qualifikation ∩ match)
    $out .= '<div class="lvk-bx-step" data-key="ergebnis" hidden>';
    $out .= '<h3 class="lvk-bx-q">Dein Ergebnis: Diese Förderungen kommen für dich in Frage</h3>';
    $out .= '<p class="lvk-bx-count" aria-live="polite"></p>';
    $out .= '<div class="lvf-grid lvfb-results">';
    foreach ($programs as $f) {
        $icon  = isset($icons[$f['icon']]) ? $icons[$f['icon']] : $icons['_default'];
        $url   = livento_cc_foerder_url($f['slug']);
        $match = '|' . implode('|', (array) (isset($f['match']) ? $f['match'] : array())) . '|';
        $out  .= '<a class="lvf-card" href="' . esc_url($url) . '" data-match="' . esc_attr($match) . '">';
        $out  .= '<span class="lvf-ic" aria-hidden="true">' . $icon . '</span>';
        $out  .= '<h3 class="lvf-t">' . esc_html($f['title']) . '</h3>';
        if (!empty($f['short'])) {
            $out .= '<p class="lvf-d">' . esc_html(mb_substr(wp_strip_all_tags($f['short']), 0, 140)) . '</p>';
        }
        $out .= '<span class="lvf-cta">Mehr erfahren →</span></a>';
    }
    $out .= '</div><p style="text-align:center;margin-top:18px"><a href="' . esc_url(livento_cc_foerder_list_url()) . '">Alle Fördermöglichkeiten ansehen →</a></p>';
    $out .= '</div>';

    $out .= '<div class="lvk-bx-nav"><button type="button" class="lvk-bx-back" hidden>← Zurück</button><span class="lvk-bx-spacer"></span><button type="button" class="lvk-bx-next">Weiter</button></div>';
    $out .= '<noscript><p><a href="' . esc_url(livento_cc_foerder_list_url()) . '">Zu den Fördermöglichkeiten →</a></p></noscript>';
    $out .= '</div>';
    $out .= livento_cc_foerder_berater_js();
    $out .= livento_cc_lead_js();
    return $out;
});

function livento_cc_foerder_berater_js() {
    static $done = false;
    if ($done) { return ''; }
    $done = true;
    return <<<'JS'
<script>
(function(){
  var root=document.getElementById('lvfb-berater'); if(!root) return;
  var total=parseInt(root.getAttribute('data-total'),10)||0;
  var steps=root.querySelectorAll('.lvk-bx-step'), stps=root.querySelectorAll('.lvk-bx-stp');
  var back=root.querySelector('.lvk-bx-back'), next=root.querySelector('.lvk-bx-next');
  var cards=Array.prototype.slice.call(root.querySelectorAll('.lvfb-results .lvf-card'));
  var countEl=root.querySelector('.lvk-bx-count');
  var cur=0, status='';
  Array.prototype.forEach.call(root.querySelectorAll('.lvfb-status'),function(b){
    b.addEventListener('click',function(){
      Array.prototype.forEach.call(root.querySelectorAll('.lvfb-status'),function(x){x.classList.remove('on');});
      b.classList.add('on'); status=b.getAttribute('data-status');
    });
  });
  Array.prototype.forEach.call(root.querySelectorAll('.lvfb-qual'),function(b){
    b.addEventListener('click',function(){ b.classList.toggle('on'); });
  });
  function showQualGroup(){
    Array.prototype.forEach.call(root.querySelectorAll('.lvfb-qgroup'),function(g){ g.hidden=(g.getAttribute('data-status')!==status); });
  }
  function chosenQuals(){
    var out=[];
    Array.prototype.forEach.call(root.querySelectorAll('.lvfb-qgroup:not([hidden]) .lvfb-qual.on'),function(b){ out.push(b.getAttribute('data-qual')); });
    return out;
  }
  function applyResults(){
    var quals=chosenQuals(), shown=0;
    cards.forEach(function(c){
      var m=(c.getAttribute('data-match')||'').split('|').filter(function(x){return x;});
      var ok=quals.length===0;
      if(!ok){ for(var i=0;i<quals.length;i++){ if(m.indexOf(quals[i])>-1){ok=true;break;} } }
      c.style.display=ok?'':'none'; if(ok)shown++;
    });
    if(countEl) countEl.textContent = shown+(shown===1?' passende Förderung':' passende Förderungen');
  }
  function show(i){
    cur=i;
    Array.prototype.forEach.call(steps,function(s,idx){ s.hidden=(idx!==i); });
    Array.prototype.forEach.call(stps,function(s,idx){ s.classList.toggle('is-active',idx===i); s.classList.toggle('is-done',idx<i); });
    if(back) back.hidden=(i===0);
    if(steps[i].getAttribute('data-key')==='qual') showQualGroup();
    var last=(i===total-1);
    if(next) next.hidden=last;
    if(last) applyResults();
    try{ root.scrollIntoView({behavior:'smooth',block:'start'}); }catch(e){}
  }
  if(next) next.addEventListener('click',function(){
    if(cur===0 && !status){ alert('Bitte wähle deinen Status.'); return; }
    if(cur<total-1) show(cur+1);
  });
  if(back) back.addEventListener('click',function(){ if(cur>0) show(cur-1); });
  show(0);
})();
</script>
JS;
}

/* ============================================================
 * 14. Lead-Formular → GHL Inbound-Webhook (ein Button)
 *
 * Statt eingebettetem GHL-iframe (eigener Absende-Button) rendert das Plugin ein
 * schlankes natives Formular. Der einzige „Weiter"-Button sendet den Lead serverseitig
 * an den GHL-Inbound-Webhook (startet den Workflow) und geht erst dann zum Ergebnis.
 * ============================================================ */

function livento_cc_berater_webhook() {
    return trim((string) get_option('livento_cc_berater_webhook', ''));
}
function livento_cc_foerder_webhook() {
    $w = trim((string) get_option('livento_cc_foerder_webhook', ''));
    return $w !== '' ? $w : livento_cc_berater_webhook();
}
function livento_cc_lead_webhook($source) {
    return ($source === 'foerder') ? livento_cc_foerder_webhook() : livento_cc_berater_webhook();
}
/** Gibt es überhaupt einen „Ihre Angaben"-Schritt? (Webhook ODER Embed). */
function livento_cc_has_angaben($source, $embed) {
    return (livento_cc_lead_webhook($source) !== '') || (trim((string) $embed) !== '');
}
/** Inhalt des „Ihre Angaben"-Schritts: natives Lead-Formular (Webhook) ODER Embed (iframe). */
function livento_cc_angaben_inner($source, $embed) {
    if (livento_cc_lead_webhook($source) !== '') {
        return livento_cc_lead_form($source);
    }
    if (trim((string) $embed) !== '') {
        return '<div class="lvk-bx-form">' . $embed . '</div>';
    }
    return '';
}

/** Natives Lead-Formular — nur Felder. Der Stepper-„Weiter"-Button sendet + geht weiter. */
function livento_cc_lead_form($source) {
    $btn = ($source === 'foerder') ? 'Zu meinen Fördermöglichkeiten' : 'Zur persönlichen Empfehlung';
    $h  = '<form class="lvk-lead-form" novalidate'
        . ' data-source="' . esc_attr($source) . '"'
        . ' data-endpoint="' . esc_url(rest_url('livento/v1/lead')) . '"'
        . ' data-btn="' . esc_attr($btn) . '">';
    $h .= '<div class="lvk-lead-row"><input type="text" name="first_name" placeholder="Vorname" autocomplete="given-name"></div>';
    $h .= '<div class="lvk-lead-row"><input type="text" name="last_name" placeholder="Nachname" autocomplete="family-name"></div>';
    $h .= '<div class="lvk-lead-row"><input type="email" name="email" placeholder="E-Mail-Adresse *" autocomplete="email" required></div>';
    $h .= '<div class="lvk-lead-row"><input type="tel" name="phone" placeholder="Telefon (optional)" autocomplete="tel"></div>';
    // Honeypot (Bot-Schutz) — für Menschen unsichtbar.
    $h .= '<div class="lvk-hp" aria-hidden="true"><input type="text" name="hp_field" tabindex="-1" autocomplete="off"></div>';
    $h .= '<label class="lvk-lead-consent"><input type="checkbox" name="consent" value="1" required> <span>Ich willige ein, dass mir meine persönliche Empfehlung sowie Informationen zu Angeboten und Weiterbildungsthemen von Livento per E-Mail zugesendet werden. *</span></label>';
    $h .= '<p class="lvk-lead-err lvk-lead-err-email" hidden>Bitte gib eine gültige E-Mail-Adresse ein.</p>';
    $h .= '<p class="lvk-lead-err lvk-lead-err-consent" hidden>Bitte bestätige die Einwilligung, um fortzufahren.</p>';
    $h .= '<p class="lvk-lead-note">* Pflichtfelder. Du kannst dich jederzeit abmelden.</p>';
    $h .= '</form>';
    return $h;
}

/** Shared JS: fängt den „Weiter"-Klick im Angaben-Schritt ab, sendet den Lead an den
 *  WP-REST-Proxy (→ GHL-Webhook) und geht erst bei Erfolg weiter. Ein Button. */
function livento_cc_lead_js() {
    static $done = false;
    if ($done) { return ''; }
    $done = true;
    return <<<'JS'
<script>
(function(){
  function init(form){
    var step=form.closest('.lvk-bx-step');
    var root=form.closest('.lvk-berater')||document;
    var nextBtn=root.querySelector('.lvk-bx-next');
    var errMail=form.querySelector('.lvk-lead-err-email');
    var errConsent=form.querySelector('.lvk-lead-err-consent');
    var consentEl=form.querySelector('[name=consent]');
    var lbl=form.getAttribute('data-btn')||'Weiter';
    var orig=nextBtn?nextBtn.textContent:'';
    var sent=false, busy=false;
    function active(){ return step && !step.hidden; }
    function setLbl(t){ if(nextBtn) nextBtn.textContent=t; }
    function gather(){
      var g=function(n){var e=form.querySelector('[name="'+n+'"]'); return e?String(e.value||'').trim():'';};
      var sel=[].slice.call(root.querySelectorAll('.lvk-bx-row.on,.lvk-bx-single.on')).map(function(b){return b.textContent.trim();});
      return {first_name:g('first_name'),last_name:g('last_name'),email:g('email'),phone:g('phone'),
        consent:!!(consentEl&&consentEl.checked),hp:g('hp_field'),
        source:form.getAttribute('data-source'),
        selection:sel.join(' | '),page:location.href};
    }
    function submit(){
      var d=gather();
      if(!d.email||!/.+@.+\..+/.test(d.email)){ if(errMail)errMail.hidden=false; var em=form.querySelector('[name=email]'); if(em)em.focus(); return Promise.reject('email'); }
      if(errMail)errMail.hidden=true;
      if(consentEl&&!consentEl.checked){ if(errConsent)errConsent.hidden=false; consentEl.focus(); return Promise.reject('consent'); }
      if(errConsent)errConsent.hidden=true;
      busy=true; if(nextBtn)nextBtn.disabled=true; setLbl('Senden …');
      function done(){ busy=false; if(nextBtn)nextBtn.disabled=false; setLbl(active()?lbl:orig); }
      return fetch(form.getAttribute('data-endpoint'),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(d)})
        .then(function(r){return r.json().catch(function(){return {ok:true};});})
        .then(function(j){ if(j&&j.ok===false&&j.error==='email'){ done(); if(errMail)errMail.hidden=false; throw 'email'; } done(); return true; })
        .catch(function(e){ done(); if(e==='email') throw e; return true; });
    }
    if(nextBtn){
      // Interceptor am Vorfahren in der CAPTURE-Phase: feuert vor dem Stepper-Handler
      // (der am selben Button, aber in der Target-Phase haengt) und stoppt ihn bei Bedarf.
      root.addEventListener('click',function(e){
        var t=e.target.closest?e.target.closest('.lvk-bx-next'):null;
        if(t!==nextBtn) return;
        if(sent||busy||!active()) return;       // durchlassen → Stepper geht weiter
        e.preventDefault(); e.stopImmediatePropagation();
        submit().then(function(){
          sent=true;
          // GTM/GA4: Lead-Conversion. lead_source aus data-source (foerder → foerderberater, sonst kursberater).
          try{ window.dataLayer=window.dataLayer||[]; window.dataLayer.push({event:'generate_lead',lead_type:'anfrage',lead_source:(form.getAttribute('data-source')==='foerder'?'foerderberater':'kursberater')}); }catch(_e){}
          nextBtn.click();
        }).catch(function(){});
      },true);
      if(active()) setLbl(lbl);
      try{
        var obs=new MutationObserver(function(){ if(!busy&&!sent) setLbl(active()?lbl:orig); });
        obs.observe(step,{attributes:true,attributeFilter:['hidden']});
      }catch(e){}
    }
    if(consentEl) consentEl.addEventListener('change',function(){ if(consentEl.checked&&errConsent) errConsent.hidden=true; });
    var emailEl=form.querySelector('[name=email]');
    if(emailEl) emailEl.addEventListener('input',function(){ if(errMail) errMail.hidden=true; });
    form.addEventListener('submit',function(e){ e.preventDefault(); if(nextBtn) nextBtn.click(); });
  }
  Array.prototype.forEach.call(document.querySelectorAll('.lvk-lead-form'), init);
})();
</script>
JS;
}

/** REST-Proxy: nimmt den Lead entgegen und leitet ihn serverseitig an den GHL-Webhook
 *  (vermeidet CORS, verbirgt die Webhook-URL). */
add_action('rest_api_init', function () {
    register_rest_route('livento/v1', '/lead', array(
        'methods'             => 'POST',
        'callback'            => 'livento_cc_rest_lead',
        'permission_callback' => '__return_true',
    ));
});
function livento_cc_rest_lead($req) {
    $p = $req->get_json_params();
    if (!is_array($p)) { $p = array(); }
    // Honeypot: ausgefülltes verstecktes Feld = Bot → „ok" vortäuschen, nichts weiterleiten.
    if (!empty($p['hp_field'])) {
        return new WP_REST_Response(array('ok' => true, 'note' => 'hp'), 200);
    }
    $email = isset($p['email']) ? sanitize_email($p['email']) : '';
    if (!is_email($email)) {
        return new WP_REST_Response(array('ok' => false, 'error' => 'email'), 200);
    }
    $source  = (isset($p['source']) && $p['source'] === 'foerder') ? 'foerder' : 'kurs';
    $webhook = livento_cc_lead_webhook($source);
    if ($webhook === '') {
        return new WP_REST_Response(array('ok' => true, 'note' => 'no-webhook'), 200);
    }
    $payload = array(
        'first_name' => isset($p['first_name']) ? sanitize_text_field($p['first_name']) : '',
        'last_name'  => isset($p['last_name']) ? sanitize_text_field($p['last_name']) : '',
        'email'      => $email,
        'consent'    => !empty($p['consent']),
        'source'     => $source === 'foerder' ? 'foerderberater' : 'kursberater',
        'selection'  => isset($p['selection']) ? sanitize_text_field($p['selection']) : '',
        'page'       => isset($p['page']) ? esc_url_raw($p['page']) : '',
    );
    // Telefon nur senden, wenn ausgefüllt (sonst leeres Feld in GHL).
    $phone = isset($p['phone']) ? sanitize_text_field($p['phone']) : '';
    if ($phone !== '') {
        $payload['phone'] = $phone;
    }
    $res = wp_remote_post($webhook, array(
        'timeout' => 12,
        'headers' => array('Content-Type' => 'application/json'),
        'body'    => wp_json_encode($payload),
    ));
    if (is_wp_error($res)) {
        error_log('[livento] GHL-Webhook fehlgeschlagen: ' . $res->get_error_message());
        return new WP_REST_Response(array('ok' => true, 'note' => 'send-failed'), 200);
    }
    return new WP_REST_Response(array('ok' => true), 200);
}

/* ============================================================
 * 12. SLK-TARIFE (v1.29.0)
 *
 * Verkauft die Selbstlernkurs-Bundles aus Campus Connect: Tariffamilien
 * (PflichtTicket / KomplettTicket / RollenTicket), Staffelpreise nach
 * Beschaeftigtenzahl, Angebotsrechner und die vollstaendige Kursliste je
 * Setting-Variante.
 *
 * Datenquelle: View `public_tariffs` (anon) + RPC `public_bundle_courses`.
 *
 * WICHTIG — genau EINE Preisberechnung:
 *   livento_cc_calc_price() ist die einzige Stelle, an der ein Preis entsteht.
 *   Sie speist die Tarifkarte, den Angebotsrechner (per REST, nicht per JS-Mathe)
 *   UND den WooCommerce-Warenkorbpreis. Sonst weicht der angezeigte Preis
 *   frueher oder spaeter vom bezahlten ab.
 *   Sie ist die Portierung von fn_calc_tariff_price (Campus Connect, v3.157.0);
 *   beide muessen dieselben Ergebnisse liefern.
 * ============================================================ */

/** Generischer PostgREST-GET auf einen beliebigen Pfad (die alte Variante ist auf public_offerings verdrahtet). */
function livento_cc_rest_get_path($path, $query = '') {
    $key = livento_cc_anon_key();
    $url = LIVENTO_CC_SUPABASE_URL . '/rest/v1/' . ltrim($path, '/') . ($query ? '?' . $query : '');

    $res = wp_remote_get($url, array(
        'timeout' => 8,
        'headers' => array(
            'apikey'        => $key,
            'Authorization' => 'Bearer ' . $key,
            'Accept'        => 'application/json',
        ),
    ));
    if (is_wp_error($res)) {
        return $res;
    }
    $code = wp_remote_retrieve_response_code($res);
    if ($code < 200 || $code >= 300) {
        return new WP_Error('livento_cc_http', 'PostgREST HTTP ' . $code, wp_remote_retrieve_body($res));
    }
    $data = json_decode(wp_remote_retrieve_body($res), true);
    return is_array($data) ? $data : array();
}

/** PostgREST-RPC (POST). */
function livento_cc_rest_rpc($fn, $args = array()) {
    $key = livento_cc_anon_key();
    $res = wp_remote_post(LIVENTO_CC_SUPABASE_URL . '/rest/v1/rpc/' . $fn, array(
        'timeout' => 8,
        'headers' => array(
            'apikey'        => $key,
            'Authorization' => 'Bearer ' . $key,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ),
        'body' => wp_json_encode($args),
    ));
    if (is_wp_error($res)) {
        return $res;
    }
    $code = wp_remote_retrieve_response_code($res);
    if ($code < 200 || $code >= 300) {
        return new WP_Error('livento_cc_http', 'RPC HTTP ' . $code, wp_remote_retrieve_body($res));
    }
    $data = json_decode(wp_remote_retrieve_body($res), true);
    return is_array($data) ? $data : array();
}

/** Alle oeffentlichen Tariffamilien (gecached, gleiche TTL/Purge-Version wie der Kurskatalog). */
function livento_cc_get_tariffs() {
    $ver = livento_cc_ver();
    $key = 'livento_cc_tariffs_v' . $ver;

    $cached = get_transient($key);
    if (is_array($cached)) {
        return $cached;
    }

    $data = livento_cc_rest_get_path('public_tariffs', 'select=*&order=sort_order.asc');
    if (is_wp_error($data)) {
        // Stale-while-error: lieber alte Preise als eine leere Seite.
        $stale = get_transient('livento_cc_tariffs_stale');
        error_log('[livento] public_tariffs nicht erreichbar: ' . $data->get_error_message());
        return is_array($stale) ? $stale : array();
    }

    set_transient($key, $data, LIVENTO_CC_TTL);
    set_transient('livento_cc_tariffs_stale', $data, DAY_IN_SECONDS);
    return $data;
}

/** Kursliste einer Setting-Variante (gecached). */
function livento_cc_get_bundle_courses($bundle_id) {
    $ver = livento_cc_ver();
    $key = 'livento_cc_bcourses_' . $ver . '_' . md5((string) $bundle_id);

    $cached = get_transient($key);
    if (is_array($cached)) {
        return $cached;
    }

    $data = livento_cc_rest_rpc('public_bundle_courses', array('p_bundle_id' => $bundle_id));
    if (is_wp_error($data)) {
        error_log('[livento] public_bundle_courses fehlgeschlagen: ' . $data->get_error_message());
        return array();
    }

    set_transient($key, $data, LIVENTO_CC_TTL);
    return $data;
}

/**
 * Beschriftung der Stundeneinheit (v1.41.0).
 *
 * Vorher stand hier `$u === 'stunden' ? 'Std.' : 'UE'`. In der Datenbank stehen aber
 * 'Zeitstunden' (176 Kurse) und 'unterrichtsstunden' (5) — der Vergleich traf KEINEN
 * von beiden, also zeigte die Seite ausnahmslos 'UE'. Ein Kurs mit 2 Zeitstunden
 * (120 Min.) stand damit als '2 UE' (90 Min.) auf der Verkaufsseite: Wir haben unsere
 * Kurse kuerzer dargestellt, als sie sind — bei Pflichtunterweisungen ist das die
 * Nachweisgrundlage.
 */
function livento_cc_hours_unit_label($unit) {
    $u = strtolower(trim((string) $unit));
    if ($u === 'unterrichtsstunden' || $u === 'ue') {
        return 'UE';
    }
    // Zeitstunden, 'stunden' und alles Unbekannte: Zeitstunden sind der Normalfall.
    return 'Std.';
}

/**
 * Regelwerke eines Kurses als Fliesstext (v1.41.0).
 *
 * Bewusst eine TATSACHE, keine Pruefbehauptung: 'TRBA 250 (Ausgabe November 2025)'
 * statt 'geprueft von …'. Campus Connect v3.171.0 hat die Behauptung
 * 'Rechtsstand geprueft' aus 355 Kursinhalten entfernt, weil sie ein Stempel war;
 * sie kommt hier nicht durch die Hintertuer zurueck. Wer die Fassung nennt, sagt
 * etwas Nachpruefbares — und ein Wettbewerber auf der 2018er TRBA kann es nicht.
 */
function livento_cc_course_norms_line($course) {
    if (empty($course['norms']) || !is_array($course['norms'])) {
        return '';
    }
    $teile = array();
    foreach ($course['norms'] as $n) {
        if (empty($n['key'])) {
            continue;
        }
        $t = esc_html($n['key']);
        if (!empty($n['edition_label'])) {
            // Nur die Kurzform in Klammern — die volle GMBl-Angabe spraengt die Zeile.
            $kurz = preg_replace('/,.*$/', '', $n['edition_label']);
            $t .= ' <span class="lv-course__norm-ed">(' . esc_html($kurz) . ')</span>';
        }
        $teile[] = $t;
    }
    return $teile ? implode(' · ', $teile) : '';
}

/**
 * v1.61.0: Rechtsgrundlagen kurz — nur die Kuerzel, die volle Angabe (Titel, Fassung) als
 * Tooltip. Die lange Form (livento_cc_course_norms_line) bleibt fuer die Produktseite.
 */
function livento_cc_course_norms_short($course) {
    if (empty($course['norms']) || !is_array($course['norms'])) {
        return '';
    }
    $teile = array();
    foreach ($course['norms'] as $n) {
        if (empty($n['key'])) {
            continue;
        }
        $voll = trim(implode(' ', array_filter(array(
            (string) ($n['title'] ?? ''),
            (string) ($n['edition_label'] ?? ''),
        ))));
        $teile[] = $voll !== ''
            ? '<abbr title="' . esc_attr($voll) . '">' . esc_html($n['key']) . '</abbr>'
            : esc_html($n['key']);
    }
    return implode(' · ', $teile);
}

/**
 * v1.61.0: Kurse eines Tickets nach Themenbereich (Campus Connect v4.57.0: topic_area,
 * topic_area_sort aus public_bundle_courses). Reihenfolge wie in „Meine Kurse“, Kurse darin
 * alphabetisch; ohne Bereich unter „Weitere Kurse“ am Ende.
 * Rueckgabe: Liste von array('key', 'label', 'courses'), dazu Anzahl echter Bereiche.
 */
function livento_cc_ticket_areas($courses) {
    $groups = array();
    foreach ((array) $courses as $c) {
        $name = trim((string) ($c['topic_area'] ?? ''));
        if ($name === '') {
            $key = '_weitere'; $label = 'Weitere Kurse'; $sort = PHP_INT_MAX;
        } else {
            $key = sanitize_title($name); $label = $name; $sort = (int) ($c['topic_area_sort'] ?? 0);
        }
        if (!isset($groups[$key])) {
            $groups[$key] = array('key' => $key, 'label' => $label, 'sort' => $sort, 'courses' => array());
        }
        $groups[$key]['courses'][] = $c;
    }
    $alpha = function ($a, $b) {
        return strcasecmp(remove_accents((string) $a), remove_accents((string) $b));
    };
    foreach ($groups as &$g) {
        usort($g['courses'], function ($x, $y) use ($alpha) { return $alpha($x['title'], $y['title']); });
    }
    unset($g);
    uasort($groups, function ($x, $y) use ($alpha) {
        return $x['sort'] !== $y['sort'] ? ($x['sort'] < $y['sort'] ? -1 : 1) : $alpha($x['label'], $y['label']);
    });
    $echte = count(array_filter(array_keys($groups), function ($k) { return $k !== '_weitere'; }));
    // v1.62.0: Hat kein Kurs einen Bereich, ist „Weitere Kurse“ irrefuehrend.
    if ($echte === 0 && isset($groups['_weitere'])) {
        $groups['_weitere']['label'] = 'Alle Kurse';
    }
    return array(array_values($groups), $echte);
}

/** v1.61.0: Eine Kurszeile der Ticketliste (Titel, Kennzahlen, Rechtsgrundlagen kurz). */
function livento_cc_ticket_course_li($course) {
    $teile = array();
    $std = rtrim(rtrim(number_format((float) ($course['total_hours'] ?? 0), 1, ',', '.'), '0'), ',');
    $teile[] = esc_html($std . ' ' . livento_cc_hours_unit_label($course['hours_unit'] ?? ''));
    $teile[] = esc_html((int) ($course['module_count'] ?? 0) . ' Module');
    $teile[] = esc_html((int) ($course['lesson_count'] ?? 0) . ' Lektionen');
    if (!empty($course['auto_certify']) || !empty($course['certificate_title'])) {
        $teile[] = '<strong>Zertifikat</strong>';
    }
    $nr   = !empty($course['course_number']) ? '<em>' . esc_html($course['course_number']) . '</em> · ' : '';
    // Roh lassen: Das Skript normalisiert Suchtext und Kurstext gleich. remove_accents() macht auf
    // deutschen Seiten aus „ä“ „ae“, die Eingabe „Prävention“ faende „Praevention“ dann nicht.
    $such = ($course['title'] ?? '') . ' ' . ($course['course_number'] ?? '');
    $out  = '<li data-text="' . esc_attr($such) . '">';
    $out .= '<span class="lv-kb__ct">' . esc_html($course['title'] ?? '') . '</span>';
    $out .= '<span class="lv-kb__cm">' . $nr . implode(' · ', $teile) . '</span>';
    $normen = livento_cc_course_norms_short($course);
    if ($normen !== '') {
        $out .= '<span class="lv-kb__cn">Grundlage: ' . $normen . '</span>';
    }
    return $out . '</li>';
}

/**
 * v1.61.0: Ticket-Kursliste als Kacheln je Themenbereich (Stil der E-Learning-Seite, elt-paket).
 * Ohne JavaScript stehen alle Bereiche mit allen Kursen untereinander (lesbar, indexierbar),
 * die Kacheln springen per Anker dorthin. Mit JavaScript (livento_cc_ticket_areas_js) oeffnet
 * eine Kachel ihren Bereich, das Suchfeld filtert ueber alle Bereiche.
 */
function livento_cc_ticket_areas_html($courses, $bundle, $index) {
    list($groups, $echte) = livento_cc_ticket_areas($courses);
    $gesamt = count((array) $courses);
    $pre    = 'lv-kb-' . (int) $index . '-';
    $name   = (string) ($bundle['name'] ?? 'Ticket');

    $out  = '<div class="lv-kb" data-lv-kb>';
    $out .= '<span class="lv-kb__eyebrow">Was steckt im ' . esc_html($name) . '?</span>';
    $out .= '<h3 class="lv-kb__h">' . esc_html($gesamt . ' Kurse'
          . ($echte > 0 ? ' in ' . $echte . ' Themenbereich' . ($echte === 1 ? '' : 'en') : '')) . '</h3>';
    $out .= '<p class="lv-kb__lead">' . ($echte > 0
          ? 'Wähle einen Bereich und sieh alle Kurse darin – jeder mit Wissenstest und Zertifikat.'
          : 'Alle Kurse im Überblick – jeder mit Wissenstest und Zertifikat.') . '</p>';
    $out .= '<div class="lv-kb__search" hidden><input type="search" placeholder="Kurs im Ticket suchen …"'
          . ' aria-label="Kurs im Ticket suchen" data-lv-kb-q></div>';

    // Kacheln nur, wenn es mehr als eine Gruppe gibt (sonst reicht die Liste).
    if (count($groups) > 1) {
        $out .= '<div class="lv-kb__grid">';
        foreach ($groups as $g) {
            $n = count($g['courses']);
            $out .= '<a class="lv-paket lv-kb__tile" href="#' . esc_attr($pre . $g['key']) . '"'
                  . ' data-lv-kb-tile="' . esc_attr($g['key']) . '" aria-controls="' . esc_attr($pre . $g['key']) . '" aria-expanded="false">'
                  . '<span class="lv-kb__t">' . esc_html($g['label']) . '</span>'
                  . '<span class="lv-paket__tag"><span data-lv-kb-n>' . (int) $n . '</span> ' . ($n === 1 ? 'Kurs' : 'Kurse') . '</span>'
                  . '<span class="lv-paket__btn" data-lv-kb-btn>Kurse ansehen →</span>'
                  . '</a>';
        }
        $out .= '</div>';
    }

    $out .= '<div class="lv-kb__panels">';
    foreach ($groups as $g) {
        $n = count($g['courses']);
        $out .= '<section class="lv-kb__panel" id="' . esc_attr($pre . $g['key']) . '" data-lv-kb-panel="' . esc_attr($g['key']) . '">';
        $out .= '<h4>' . esc_html($g['label']) . '</h4>';
        $out .= '<p class="lv-paket__tag">' . (int) $n . ' ' . ($n === 1 ? 'Kurs' : 'Kurse') . ' · alphabetisch</p>';
        $out .= '<ul class="lv-kb__list">';
        foreach ($g['courses'] as $c) {
            $out .= livento_cc_ticket_course_li($c);
        }
        $out .= '</ul></section>';
    }
    $out .= '</div>';
    $out .= '<p class="lv-kb__leer" hidden>Kein Kurs gefunden. Versuch es mit einem anderen Begriff.</p>';
    $out .= '</div>';
    return $out . livento_cc_ticket_areas_js();
}

/** v1.61.0: Bedienung der Kacheln und der Suche (einmal je Seite, ohne Abhaengigkeiten). */
function livento_cc_ticket_areas_js() {
    static $done = false;
    if ($done) {
        return '';
    }
    $done = true;
    return <<<'JS'
<script>
(function(){
  function norm(s){ return (s||'').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'').replace(/ß/g,'ss'); }
  document.querySelectorAll('[data-lv-kb]').forEach(function(root){
    root.classList.add('lv-kb--js');
    var tiles  = [].slice.call(root.querySelectorAll('[data-lv-kb-tile]'));
    var panels = [].slice.call(root.querySelectorAll('[data-lv-kb-panel]'));
    var input  = root.querySelector('[data-lv-kb-q]');
    var leer   = root.querySelector('.lv-kb__leer');
    var offen  = tiles.length ? null : (panels[0] ? panels[0].getAttribute('data-lv-kb-panel') : null);
    root.querySelector('.lv-kb__search').hidden = false;

    function zeigen(){
      var q = norm(input.value.trim()), treffer = 0;
      panels.forEach(function(p){
        var key = p.getAttribute('data-lv-kb-panel'), n = 0;
        p.querySelectorAll('li').forEach(function(li){
          var ok = !q || norm(li.getAttribute('data-text')).indexOf(q) !== -1;
          li.hidden = !ok; if (ok) { n++; }
        });
        treffer += n;
        p.hidden = q ? n === 0 : key !== offen;
        var t = root.querySelector('[data-lv-kb-tile="' + key + '"]');
        if (t) {
          var gesamt = p.querySelectorAll('li').length;
          t.querySelector('[data-lv-kb-n]').textContent = q ? n : gesamt;
          t.classList.toggle('lv-kb__tile--leer', !!q && n === 0);
          var auf = !q && key === offen;
          t.classList.toggle('lv-paket--hi', auf);
          t.setAttribute('aria-expanded', auf ? 'true' : 'false');
          t.querySelector('[data-lv-kb-btn]').textContent = auf ? 'Ausgewählt ↓' : 'Kurse ansehen →';
        }
      });
      leer.hidden = !q || treffer > 0;
    }

    tiles.forEach(function(t){
      t.addEventListener('click', function(e){
        e.preventDefault();
        var key = t.getAttribute('data-lv-kb-tile');
        if (input.value) { input.value = ''; }
        offen = offen === key ? null : key;
        zeigen();
        if (offen) {
          var p = root.querySelector('[data-lv-kb-panel="' + key + '"]');
          if (p && p.getBoundingClientRect().top > window.innerHeight * 0.75) { p.scrollIntoView({behavior: 'smooth', block: 'start'}); }
        }
      });
    });
    input.addEventListener('input', zeigen);

    // v1.64.0: Link mit Anker (#lv-kb-0-bereich, z. B. aus einem Blogbeitrag) oeffnet den Bereich.
    function ausAnker(scrollen){
      var h = decodeURIComponent((location.hash || '').slice(1)), p = null;
      panels.forEach(function(x){ if (x.id === h) { p = x; } });
      if (!p) { return; }
      input.value = '';
      offen = p.getAttribute('data-lv-kb-panel');
      zeigen();
      if (scrollen) { setTimeout(function(){ p.scrollIntoView({block: 'start'}); }, 50); }
    }
    window.addEventListener('hashchange', function(){ ausAnker(true); });
    zeigen();
    ausAnker(true);
  });
})();
</script>
JS;
}

/** Familie per Schluessel oder Slug. */
function livento_cc_find_family($key_or_slug) {
    foreach (livento_cc_get_tariffs() as $family) {
        if ($family['key'] === $key_or_slug || (!empty($family['slug']) && $family['slug'] === $key_or_slug)) {
            return $family;
        }
    }
    return null;
}

/** Setting-Variante (Bundle) samt Plan und Familie anhand der Bundle-ID. */
function livento_cc_find_bundle($bundle_id) {
    foreach (livento_cc_get_tariffs() as $family) {
        foreach ((array) $family['plans'] as $plan) {
            foreach ((array) $plan['bundles'] as $bundle) {
                if ($bundle['id'] === $bundle_id) {
                    return array('family' => $family, 'plan' => $plan, 'bundle' => $bundle);
                }
            }
        }
    }
    return null;
}

/**
 * DIE Preisberechnung. Portierung von fn_calc_tariff_price (Campus Connect v3.157.0).
 *
 * flat      = Betrag je Einrichtung
 * per_user  = Betrag je Nutzer, aber mindestens min_amount
 * individual= kein Preis, Angebotsanfrage
 * amount_unit month => Jahrespreis = Monatsbetrag x contract_months
 */
function livento_cc_calc_price($plan, $users) {
    $users = (int) $users;
    if ($users < 1) {
        return array('mode' => 'invalid', 'users' => $users);
    }

    $months = max(1, (int) (isset($plan['contract_months']) ? $plan['contract_months'] : 12));
    $tiers  = isset($plan['tiers']) && is_array($plan['tiers']) ? $plan['tiers'] : array();

    $match = null;
    foreach ($tiers as $tier) {
        $min = (int) $tier['min_users'];
        $max = (isset($tier['max_users']) && $tier['max_users'] !== null && $tier['max_users'] !== '')
            ? (int) $tier['max_users'] : null;
        if ($users >= $min && ($max === null || $users <= $max)) {
            $match = $tier;
            break;
        }
    }

    // Keine Staffel deckt diese Groesse ab: als Angebotsfall behandeln, nicht raten.
    if (!$match || $match['pricing_mode'] === 'individual') {
        return array('mode' => 'individual', 'users' => $users);
    }

    $amount = (float) $match['amount'];
    $base   = ($match['pricing_mode'] === 'flat') ? $amount : $amount * $users;
    $base   = round($base, 2); // entspricht der numeric(12,2)-Zuweisung in SQL

    $min_amount = (isset($match['min_amount']) && $match['min_amount'] !== null && $match['min_amount'] !== '')
        ? (float) $match['min_amount'] : null;
    if ($min_amount !== null && $base < $min_amount) {
        $base = $min_amount;
    }

    if ($match['amount_unit'] === 'month') {
        $monthly = round($base, 2);
        $yearly  = round($base * $months, 2);
    } else {
        $yearly  = round($base, 2);
        $monthly = round($base / $months, 2);
    }

    return array(
        'mode'            => $match['pricing_mode'],
        'users'           => $users,
        'monthly_net'     => $monthly,
        'yearly_net'      => $yearly,
        'amount_unit'     => $match['amount_unit'],
        'contract_months' => $months,
        'is_vat_exempt'   => !empty($plan['is_vat_exempt']),
    );
}

/** Guenstigster Plan einer Familie fuer eine gegebene Beschaeftigtenzahl. */
function livento_cc_cheapest_plan($family, $users) {
    $best = null;
    foreach ((array) $family['plans'] as $plan) {
        $price = livento_cc_calc_price($plan, $users);
        if ($price['mode'] === 'individual' || $price['mode'] === 'invalid') {
            if ($best === null) {
                $best = array('plan' => $plan, 'price' => $price);
            }
            continue;
        }
        if ($best === null || $best['price']['mode'] === 'individual' || $price['yearly_net'] < $best['price']['yearly_net']) {
            $best = array('plan' => $plan, 'price' => $price);
        }
    }
    return $best;
}

/**
 * v1.42.0: Der niedrigste Kopfpreis einer Familie — "ab X pro Person und Monat".
 *
 * Die Familien-Karte zeigt bewusst den Kopfpreis, nicht die Teamsumme. Vorher stand dort
 * der Gesamtpreis fuer 20 Beschaeftigte MIT "pro Person" darunter — die Summe las sich damit
 * wie ein Kopfpreis (das 20-Fache). Diese Funktion liefert den kleinsten Monatsbetrag je Kopf
 * (brutto) ueber alle Plaene und Staffeln und ob er von der Teamgroesse abhaengt ("ab").
 *
 * flat     => bester Kopfpreis am oberen Ende der Pauschale (Betrag / max_users)
 * per_user => der Kopfbetrag selbst (min_amount hebt ihn nur fuer kleine Teams, nicht den Boden)
 * individual / ohne Betrag => zaehlt nicht mit
 */
function livento_cc_family_per_user_floor($family) {
    $vals    = array();
    $is_flat = false;

    foreach ((array) $family['plans'] as $plan) {
        $months = max(1, (int) (isset($plan['contract_months']) ? $plan['contract_months'] : 12));
        $tiers  = isset($plan['tiers']) && is_array($plan['tiers']) ? $plan['tiers'] : array();

        foreach ($tiers as $tier) {
            $mode = isset($tier['pricing_mode']) ? $tier['pricing_mode'] : '';
            if ($mode === 'individual' || !isset($tier['amount']) || $tier['amount'] === null || $tier['amount'] === '') {
                continue;
            }
            $amount    = (float) $tier['amount'];
            $per_month = (isset($tier['amount_unit']) && $tier['amount_unit'] === 'month') ? $amount : $amount / $months;

            if ($mode === 'flat') {
                // Pauschale: der guenstigste Kopfpreis entsteht am oberen Ende der Staffel.
                $max       = (isset($tier['max_users']) && $tier['max_users']) ? (int) $tier['max_users'] : 1;
                $per_month = $max > 0 ? $per_month / $max : $per_month;
                $is_flat   = true;
            }
            $vals[] = $per_month;
        }
    }

    if (empty($vals)) {
        return null;
    }
    $floor = round(min($vals), 2);
    // "ab", sobald der Kopfpreis variiert: Pauschale, Staffel oder unterschiedliche Plaene.
    $is_from = $is_flat || round(min($vals), 2) !== round(max($vals), 2);
    return array('monthly' => $floor, 'is_from' => $is_from);
}

function livento_cc_eur($value) {
    return number_format((float) $value, 2, ',', '.') . ' €';
}

/** Steuerhinweis — Preise sind Bruttopreise inkl. MwSt (einheitlich mit WooCommerce). */
function livento_cc_tax_note($price) {
    return !empty($price['is_vat_exempt']) ? 'USt-frei' : 'inkl. MwSt';
}

/**
 * v1.34.0: Nettobetrag zum Jahresbrutto, z. B. "292,44 € netto" — oder null.
 *
 * Warum ueberhaupt beides: Die Tickets werden an Einrichtungen verkauft, und ein
 * Einkaeufer denkt in netto. Die Kasse bucht aber brutto (WooCommerce rechnet den
 * eingetragenen Betrag als Kundenpreis ab, siehe v1.31.0) — der Bruttobetrag muss
 * also fuehrend bleiben. Netto daneben, statt eines von beidem zu verschweigen.
 * Bei USt-freien Tarifen (§ 4 Nr. 21 UStG) gibt es keinen Nettobetrag zu zeigen.
 */
function livento_cc_net_note($price) {
    if (!empty($price['is_vat_exempt'])) {
        return null;
    }
    if (!isset($price['yearly_net']) || $price['yearly_net'] === null) {
        return null;
    }
    return livento_cc_eur(round(((float) $price['yearly_net']) / (1 + LIVENTO_CC_VAT_RATE), 2)) . ' netto';
}

/**
 * v1.40.0: Worauf bezieht sich der Betrag — auf die Einrichtung oder auf den Kopf?
 *
 * Das ist die eigentliche Korrektur dieser Version. Der Betrag stand bislang nackt da
 * ("348,00 € / Jahr"), und der Leser musste raten. Er raet pessimistisch, also gegen uns.
 *
 * Abgeleitet aus dem pricing_mode und NICHT hartkodiert: Nur das PflichtTicket kennt
 * eine Pauschale (1-15 Beschaeftigte). KomplettTicket und RollenTicket rechnen ab der
 * ersten Person pro Kopf — dort waere "fuer die ganze Einrichtung" schlicht gelogen.
 */
function livento_cc_scope_note($price) {
    if (empty($price['mode'])) {
        return null;
    }
    if ($price['mode'] === 'flat') {
        return 'für die ganze Einrichtung';
    }
    if ($price['mode'] === 'per_user') {
        return 'pro Person';
    }
    return null; // individual: der Bezug klaert sich im Gespraech, nicht auf der Seite.
}

/**
 * v1.40.0: Der Preis, gebunden an die eingegebene Kopfzahl — "Gesamtpreis für alle 10
 * Beschäftigten". Bindet die beiden Zahlen aneinander, die auf der Seite bisher
 * beziehungslos nebeneinander standen (Rechner: 10 · Preis: 348,00 €).
 */
function livento_cc_total_note($price) {
    if (empty($price['users'])) {
        return null;
    }
    $users = (int) $price['users'];
    return $users === 1
        ? 'Gesamtpreis für 1 Beschäftigten'
        : 'Gesamtpreis für alle ' . $users . ' Beschäftigten';
}

/**
 * v1.40.0: "1,93 € pro Person und Monat" — macht den Pauschalvorteil rechenbar, statt
 * ihn dem Leser zu ueberlassen. Genau diese Zahl ist das Verkaufsargument der Seite:
 * Bei 15 Personen kostet das PflichtTicket weniger als 2 € je Kopf und Monat.
 *
 * Bewusst auch im per_user-Modus: dort bestaetigt sie nur den Staffelpreis, aber die
 * Zeile bleibt ueber alle Familien und Teamgroessen gleich gebaut.
 */
function livento_cc_per_user_note($price) {
    if (empty($price['users']) || !isset($price['yearly_net']) || $price['yearly_net'] === null) {
        return null;
    }
    $users = max(1, (int) $price['users']);

    // Bei einer einzigen Person ist der Kopfpreis per Definition der Monatsbetrag — die
    // Zeile wuerde nur "entspricht 29,00 € / Monat" direkt darueber wiederholen. Schlimmer:
    // Beim PflichtTicket steht dann ausgerechnet die groesste Kopfzahl der ganzen Staffel
    // da (29,00 €), obwohl es bei 1 Person schlicht keinen Pauschalvorteil zu zeigen gibt.
    if ($users === 1) {
        return null;
    }

    $months = max(1, (int) (isset($price['contract_months']) ? $price['contract_months'] : 12));
    $per    = ((float) $price['yearly_net']) / $months / $users;
    return livento_cc_eur(round($per, 2)) . ' pro Person und Monat';
}

/**
 * v1.40.0: Die Pauschalstaffel einer Familie, oder null wenn sie keine hat.
 * Fuer die "ab"-Zeile in der Faktenbox: "ab 348,00 € / Jahr — fuer die ganze
 * Einrichtung, bis 15 Beschaeftigte". Die 15 kommt aus max_users, nicht aus dem Kopf.
 */
function livento_cc_flat_tier($family) {
    foreach ((array) $family['plans'] as $plan) {
        $tiers = isset($plan['tiers']) && is_array($plan['tiers']) ? $plan['tiers'] : array();
        foreach ($tiers as $tier) {
            if (isset($tier['pricing_mode']) && $tier['pricing_mode'] === 'flat') {
                return $tier;
            }
        }
    }
    return null;
}

/**
 * v1.40.0: Eine Staffelzeile als Text — "16–50 Beschäftigte" / "ab 151 Beschäftigte".
 */
function livento_cc_tier_range_text($tier) {
    $min = (int) $tier['min_users'];
    $max = (isset($tier['max_users']) && $tier['max_users'] !== null && $tier['max_users'] !== '')
        ? (int) $tier['max_users'] : null;

    if ($max === null) {
        return 'ab ' . $min . ' Beschäftigten';
    }
    if ($min <= 1) {
        return 'bis ' . $max . ' Beschäftigte';
    }
    return $min . '–' . $max . ' Beschäftigte';
}

/**
 * v1.40.0: Der Preis einer Staffelzeile als Text.
 * Betraege sind Bruttobetraege (siehe livento_cc_tax_note) — der Steuerhinweis steht
 * einmal unter der Tabelle, nicht in jeder Zeile.
 */
function livento_cc_tier_price_text($tier, $months) {
    if ($tier['pricing_mode'] === 'individual') {
        return 'individuelles Angebot';
    }

    $amount = (float) $tier['amount'];
    $unit   = ($tier['amount_unit'] === 'month') ? 'Monat' : 'Jahr';

    if ($tier['pricing_mode'] === 'flat') {
        $yearly = ($tier['amount_unit'] === 'month') ? $amount * $months : $amount;
        return livento_cc_eur(round($yearly, 2)) . ' / Jahr pauschal für die ganze Einrichtung';
    }

    $text = livento_cc_eur($amount) . ' pro Person und ' . $unit;
    if (isset($tier['min_amount']) && $tier['min_amount'] !== null && $tier['min_amount'] !== '') {
        $text .= ' (mindestens ' . livento_cc_eur((float) $tier['min_amount']) . ' / ' . $unit . ')';
    }
    return $text;
}

/* ------------------------------------------------------------
 * REST: Angebotsrechner
 * Rechnet serverseitig mit livento_cc_calc_price — bewusst KEINE Preis-Mathematik
 * im Browser, sonst gaebe es eine dritte Implementierung, die auseinanderlaufen kann.
 * ---------------------------------------------------------- */
add_action('rest_api_init', function () {
    register_rest_route('livento/v1', '/tarif-preis', array(
        'methods'             => 'GET',
        'permission_callback' => '__return_true',
        'callback'            => function (WP_REST_Request $req) {
            $users    = max(1, (int) $req->get_param('users'));
            $families = array();
            $plans    = array();

            $format = function ($price) {
                return array(
                    'mode'     => $price['mode'],
                    'monthly'  => isset($price['monthly_net']) ? livento_cc_eur($price['monthly_net']) : null,
                    'yearly'   => isset($price['yearly_net']) ? livento_cc_eur($price['yearly_net']) : null,
                    'tax_note' => livento_cc_tax_note($price),
                    // v1.34.0: Netto hier und nicht im Browser rechnen — sonst gaebe es
                    // eine zweite Preis-Mathematik, die von der serverseitigen abweichen kann.
                    'net_note' => livento_cc_net_note($price),
                    // v1.40.0: Aus demselben Grund kommen auch Bezugsgroesse und Kopfpreis
                    // fertig formatiert von hier. Der Rechner aendert die Kopfzahl bei jedem
                    // Tastendruck — stuende die Division im Browser, waere sie die zweite
                    // Preis-Mathematik, die v1.34.0 gerade abgeschafft hat.
                    'total_note'    => livento_cc_total_note($price),
                    'per_user_note' => livento_cc_per_user_note($price),
                    'scope_note'    => livento_cc_scope_note($price),
                    // v1.42.0: Der Kopfpreis fuer die eingegebene Teamgroesse — genau die kleine
                    // Zahl, die die Familien-Karte gross zeigt. Kopfpreis = Teamsumme / Kopfzahl
                    // (gilt fuer flat wie per_user). Fertig formatiert von hier, damit im Browser
                    // keine zweite Preis-Mathematik entsteht (vgl. net_note oben).
                    'head_monthly'  => (isset($price['monthly_net']) && !empty($price['users']))
                        ? livento_cc_eur(round(((float) $price['monthly_net']) / max(1, (int) $price['users']), 2))
                        : null,
                    'head_net'      => (isset($price['monthly_net']) && !empty($price['users']) && empty($price['is_vat_exempt']))
                        ? livento_cc_eur(round(((float) $price['monthly_net']) / max(1, (int) $price['users']) / (1 + LIVENTO_CC_VAT_RATE), 2)) . ' netto'
                        : null,
                );
            };

            foreach (livento_cc_get_tariffs() as $family) {
                // Familien-Karte: der guenstigste Einstieg ("ab X").
                $best = livento_cc_cheapest_plan($family, $users);
                if ($best) {
                    $families[$family['key']] = $format($best['price']);
                }
                // Jeder Plan einzeln — RollenTicket hat vier Plaene mit UNTERSCHIEDLICHEN
                // Preisen; auf der Detailseite muss jede Variante ihren eigenen zeigen.
                foreach ((array) $family['plans'] as $plan) {
                    $plans[$plan['id']] = $format(livento_cc_calc_price($plan, $users));
                }
            }
            return new WP_REST_Response(array('users' => $users, 'families' => $families, 'plans' => $plans), 200);
        },
    ));
});

/* ------------------------------------------------------------
 * Shortcode [livento_tarife] — die Tarif-Landingpage
 * ---------------------------------------------------------- */
add_shortcode('livento_tarife', function ($atts) {
    $atts = shortcode_atts(array(
        'heading'    => 'Fortbildung für dein ganzes Team',
        'subheading' => 'Fertige Jahres-Lernpfade statt Kurschaos. Rollenbezogen zugewiesen, mit Wissenstest, Zertifikat und Nachweisübersicht.',
        'users'      => 20,
        'base'       => 'e-learning',
    ), $atts, 'livento_tarife');

    $families = livento_cc_get_tariffs();
    if (empty($families)) {
        return '<p>Die Tarife sind derzeit nicht abrufbar.</p>';
    }

    // v1.42.0: Merchandising-Reihenfolge der Landingpage-Karten — KomplettTicket links,
    // PflichtTicket in die Mitte (Anker-Position), RollenTicket rechts. Bewusst nur hier
    // fuers Schaufenster; die kanonische sort_order in der DB bleibt unangetastet. Familien
    // ohne feste Position folgen dahinter in ihrer sort_order.
    $card_order = array('komplettticket' => 0, 'pflichtticket' => 1, 'rollenticket' => 2);
    usort($families, function ($a, $b) use ($card_order) {
        $ra = isset($card_order[$a['key']]) ? $card_order[$a['key']] : 99;
        $rb = isset($card_order[$b['key']]) ? $card_order[$b['key']] : 99;
        return ($ra === $rb)
            ? (int) $a['sort_order'] - (int) $b['sort_order']
            : $ra - $rb;
    });

    $users = max(1, (int) $atts['users']);
    $base  = trim($atts['base'], '/');

    ob_start();
    livento_cc_tariff_styles();
    ?>
    <div class="lv-tarife">
        <header class="lv-tarife__head">
            <h2><?php echo esc_html($atts['heading']); ?></h2>
            <p><?php echo esc_html($atts['subheading']); ?></p>
        </header>

        <div class="lv-calc" data-lv-calc>
            <label for="lv-calc-users">Wie viele Beschäftigte hast du?</label>
            <input type="number" id="lv-calc-users" min="1" step="1" value="<?php echo esc_attr($users); ?>" data-lv-calc-input>
            <span class="lv-calc__hint">Der Preis richtet sich nach der Teamgröße. 12 Monate Laufzeit, jährliche Abrechnung.</span>
        </div>

        <div class="lv-tarife__grid">
            <?php foreach ($families as $family) :
                // Kennzahlen ueber alle Varianten der Familie.
                $courses = 0; $hours = 0; $variants = 0;
                foreach ((array) $family['plans'] as $plan) {
                    foreach ((array) $plan['bundles'] as $bundle) {
                        $variants++;
                        $courses = max($courses, (int) $bundle['course_count']);
                        $hours   = max($hours, (float) $bundle['total_hours_sum']);
                    }
                }
                $link = home_url('/' . $base . '/' . ($family['slug'] ?: $family['key']) . '/');
            ?>
            <article class="lv-tarif" data-lv-family="<?php echo esc_attr($family['key']); ?>">
                <?php if (!empty($family['public_image_url'])) : ?>
                    <img class="lv-tarif__img" src="<?php echo esc_url($family['public_image_url']); ?>" alt="" loading="lazy">
                <?php endif; ?>
                <h3><?php echo esc_html($family['name']); ?></h3>
                <?php if (!empty($family['claim'])) : ?>
                    <p class="lv-tarif__claim"><?php echo esc_html($family['claim']); ?></p>
                <?php endif; ?>

                <?php
                    // v1.42.0: Die Karte nennt den KOPFPREIS je Monat, nicht die Teamsumme.
                    // Vorher stand hier der Gesamtpreis fuer 20 Beschaeftigte MIT "pro Person"
                    // darunter — die Teamsumme las sich damit wie ein Kopfpreis (das 20-Fache),
                    // eine schlicht falsche Aussage. Erstaufbau: der niedrigste Kopfpreis der
                    // Familie ("ab X", solange die Teamgroesse noch nicht eingegeben ist). Sobald
                    // der Besucher im Rechner eine Zahl eintraegt, ersetzt paint() (JS, siehe
                    // render()) das durch den EXAKTEN Kopfpreis fuer diese Teamgroesse.
                    $floor = livento_cc_family_per_user_floor($family);
                ?>
                <div class="lv-tarif__price" data-lv-price>
                    <?php if (!$floor) : ?>
                        <strong data-lv-price-main>Individuelles Angebot</strong>
                        <span data-lv-price-sub>Wir rechnen dir das gern durch.</span>
                    <?php else :
                        $head_sub = array(
                            'pro Beschäftigtem',
                            'inkl. MwSt',
                            livento_cc_eur(round($floor['monthly'] / (1 + LIVENTO_CC_VAT_RATE), 2)) . ' netto',
                        );
                    ?>
                        <strong data-lv-price-main><?php echo esc_html(($floor['is_from'] ? 'ab ' : '') . livento_cc_eur($floor['monthly'])); ?><small> / Monat</small></strong>
                        <span data-lv-price-sub><?php echo esc_html(implode(' · ', $head_sub)); ?></span>
                    <?php endif; ?>
                </div>

                <ul class="lv-tarif__facts">
                    <?php if ($courses) : ?><li><strong><?php echo esc_html($courses); ?></strong> Kurse</li><?php endif; ?>
                    <?php if ($hours) : ?><li><strong><?php echo esc_html(rtrim(rtrim(number_format($hours, 1, ',', '.'), '0'), ',')); ?></strong> Stunden</li><?php endif; ?>
                    <?php if ($variants > 1) : ?><li><strong><?php echo esc_html($variants); ?></strong> Varianten</li><?php endif; ?>
                </ul>

                <?php if (!empty($family['highlights']) && is_array($family['highlights'])) : ?>
                    <ul class="lv-tarif__list">
                        <?php foreach (array_slice($family['highlights'], 0, 6) as $item) : ?>
                            <li><?php echo esc_html($item); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <a class="lv-tarif__cta" href="<?php echo esc_url($link); ?>">Kurse &amp; Details ansehen</a>
            </article>
            <?php endforeach; ?>
        </div>

        <p class="lv-tarife__foot">
            Alle Preise inkl. gesetzlicher MwSt., 12 Monate Mindestlaufzeit, Abrechnung jährlich im Voraus.
            Ab 151 Beschäftigten erstellen wir dir ein individuelles Angebot.
        </p>
    </div>
    <?php
    livento_cc_tariff_calc_script();
    return ob_get_clean();
});

/* ------------------------------------------------------------
 * Shortcode [livento_tarif family="pflichtticket"] — Detailseite einer Familie
 * ---------------------------------------------------------- */
add_shortcode('livento_tarif', function ($atts) {
    $atts   = shortcode_atts(array('family' => '', 'users' => 20), $atts, 'livento_tarif');
    $family = livento_cc_find_family($atts['family']);
    if (!$family) {
        return '<p>Dieser Tarif ist derzeit nicht verfügbar.</p>';
    }

    $users = max(1, (int) $atts['users']);

    // Alle Setting-Varianten der Familie flach sammeln (Plan bleibt dran — er traegt den Preis).
    $variants = array();
    foreach ((array) $family['plans'] as $plan) {
        foreach ((array) $plan['bundles'] as $bundle) {
            $variants[] = array('plan' => $plan, 'bundle' => $bundle);
        }
    }
    if (empty($variants)) {
        return '<p>Für diesen Tarif sind derzeit keine Inhalte freigeschaltet.</p>';
    }

    // v1.34.0: Einstiegspreis der Familie. Muss sichtbar sein, weil er als lowPrice
    // ins Product-Schema geht — stuende er nur im Schema, waere das ein Preis-Mismatch.
    $from       = isset($family['price_from']) && is_array($family['price_from']) ? $family['price_from'] : null;
    $from_ok    = $from && isset($from['yearly_net']) && $from['yearly_net'] !== null;

    // v1.40.0: Der "ab"-Preis ist die erste Zahl, die der Besucher sieht — er darf nicht
    // ohne Bezugsgroesse dastehen. Beim PflichtTicket ist der Einstieg die Pauschale, also
    // gehoert ihre Obergrenze daneben ("bis 15 Beschaeftigte"); die kommt aus max_users.
    $from_scope = $from_ok ? livento_cc_scope_note($from) : null;
    if ($from_scope !== null && $from['mode'] === 'flat') {
        $flat_tier = livento_cc_flat_tier($family);
        if ($flat_tier && isset($flat_tier['max_users']) && $flat_tier['max_users'] !== null && $flat_tier['max_users'] !== '') {
            $from_scope .= ', bis ' . (int) $flat_tier['max_users'] . ' Beschäftigte';
        }
    }
    $highlights = livento_cc_tariff_highlights($family);
    $faq        = livento_cc_faq_items($family); // v1.35.0: dieselbe Quelle wie das FAQPage-Schema
    $stats      = livento_cc_tariff_stats($variants); // v1.36.0
    $beratung   = livento_cc_beratung_url() ?: home_url('/kontakt/');
    $dec        = function ($x) { return rtrim(rtrim(number_format((float) $x, 1, ',', '.'), '0'), ','); };

    ob_start();
    livento_cc_tariff_styles();
    ?>
    <div class="lv-tarif-detail">
        <?php // v1.38.0: Kopf und Raster liegen zusammen im Hero-Band, damit die Seite oben
              // dieselbe Bildsprache traegt wie die Orientierungsseiten (lv-ahero). Die
              // Faktenbox bleibt bewusst IM Band: auf einer Entscheidungsseite darf der
              // Preis nicht unter die Falz rutschen, nur damit der Hero luftiger wirkt.
              // v1.36.0: Titel und Claim stehen bewusst AUSSERHALB des Rasters. Lagen sie
              // darin, zog die Umsortierung auf schmalen Schirmen (Faktenbox nach vorn) das
              // H1 mit nach unten — der Besucher sah dann Preise, bevor er wusste, worauf er
              // ist. So bleibt die Reihenfolge: Titel, worum es geht, Preis/CTA, Details. ?>
        <div class="lv-tarif-band">
        <header class="lv-tarif-detail__head">
            <span class="lv-tarif-detail__eyebrow">E-Learning-Ticket</span>
            <h1><?php echo esc_html($family['name']); ?></h1>
            <?php if (!empty($family['claim'])) : ?><p class="lv-lead"><?php echo esc_html($family['claim']); ?></p><?php endif; ?>
            <?php // v1.60.0: Schnitt aller veroeffentlichten Bewertungen der enthaltenen Kurse (ab 5), nur sichtbar, nicht im Schema.
                  echo livento_cc_rating_html(livento_cc_family_rating($variants), true, 'der Kurse in diesem Ticket'); ?>
        </header>

        <div class="lv-tarif-hero">
            <div class="lv-tarif-hero__main">
                <?php if (!empty($family['public_description'])) : ?>
                    <div class="lv-tarif-detail__text"><?php echo wp_kses_post(wpautop($family['public_description'])); ?></div>
                <?php endif; ?>

                <?php if (!empty($highlights)) : ?>
                    <ul class="lv-tarif-detail__highlights">
                        <?php foreach ($highlights as $highlight) : ?>
                            <li><?php echo esc_html($highlight); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <?php // v1.36.0: Faktenbox nach dem Muster der Kursdetailseite (lvk-factbox).
                  // Der "ab"-Preis steht hier, weil er als lowPrice ins Product-Schema geht —
                  // stuende er nur im Schema, waere das ein Preis-Mismatch. ?>
            <aside class="lv-fb" aria-label="Auf einen Blick">
                <p class="lv-fb__eyebrow">Auf einen Blick</p>
                <p class="lv-fb__title"><?php echo esc_html($family['name']); ?></p>

                <dl class="lv-fb__list">
                    <?php if ($stats['varianten'] > 1) : ?>
                        <div class="lv-fb__row">
                            <span class="lv-fb__ic" aria-hidden="true"><?php echo livento_cc_fb_icon('format'); ?></span>
                            <span class="lv-fb__rc"><dt>Zuschnitte</dt>
                            <dd><?php echo esc_html($stats['varianten']); ?> je Einrichtungsart</dd></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($stats['kurse_max'] > 0) : ?>
                        <div class="lv-fb__row">
                            <span class="lv-fb__ic" aria-hidden="true"><?php echo livento_cc_fb_icon('cert'); ?></span>
                            <span class="lv-fb__rc"><dt>Kurse</dt>
                            <dd><?php echo esc_html(livento_cc_span($stats['kurse_min'], $stats['kurse_max'])); ?> Kurse, je mit Wissenstest und Zertifikat</dd></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($stats['std_max'] > 0) : ?>
                        <div class="lv-fb__row">
                            <span class="lv-fb__ic" aria-hidden="true"><?php echo livento_cc_fb_icon('hours'); ?></span>
                            <span class="lv-fb__rc"><dt>Umfang</dt>
                            <dd><?php echo esc_html(livento_cc_span($stats['std_min'], $stats['std_max'], $dec)); ?> Stunden</dd></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($stats['monate'] > 0) : ?>
                        <div class="lv-fb__row">
                            <span class="lv-fb__ic" aria-hidden="true"><?php echo livento_cc_fb_icon('clock'); ?></span>
                            <span class="lv-fb__rc"><dt>Laufzeit</dt>
                            <dd><?php echo esc_html($stats['monate']); ?> Monate · Abrechnung jährlich im Voraus</dd></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($from_ok) : ?>
                        <div class="lv-fb__row">
                            <span class="lv-fb__ic" aria-hidden="true"><?php echo livento_cc_fb_icon('price'); ?></span>
                            <span class="lv-fb__rc"><dt>Preis</dt>
                            <dd><strong class="lv-fb__price">ab <?php echo esc_html(livento_cc_eur($from['yearly_net'])); ?> / Jahr</strong>
                            <?php if ($from_scope) : ?>
                                <span class="lv-fb__scope"><?php echo esc_html($from_scope); ?></span>
                            <?php endif; ?>
                            <span class="lv-fb__tax"><?php echo esc_html(livento_cc_tax_note($from));
                                $from_net = livento_cc_net_note($from);
                                if ($from_net) { echo ' · ' . esc_html($from_net); } ?></span></dd></span>
                        </div>
                    <?php endif; ?>
                </dl>

                <div class="lv-fb__actions">
                    <a class="lv-btn" href="#rechner">Preis für dein Team berechnen</a>
                    <a class="lv-btn lv-btn--ghost" href="<?php echo esc_url($beratung); ?>">Unsicher? Kostenlose Beratung</a>
                </div>

                <div class="lv-fb__trust">
                    <span>✓ AZAV-zertifiziert</span>
                    <span>✓ Zertifikat je Kurs</span>
                    <span>✓ Rechnung auf die Einrichtung</span>
                </div>
            </aside>
        </div>
        </div><?php // /.lv-tarif-band ?>

        <div class="lv-calc" id="rechner" data-lv-calc>
            <label for="lv-detail-users">Wie viele Beschäftigte hast du?</label>
            <input type="number" id="lv-detail-users" min="1" step="1" value="<?php echo esc_attr($users); ?>" data-lv-calc-input>
            <?php // v1.40.0: Der wichtigste Satz der Seite steht jetzt AM Rechner. Er ist in
                  // jedem Modus wahr — auch beim per_user-Tarif ist der angezeigte Betrag der
                  // fertige Gesamtbetrag fuer die eingegebene Kopfzahl, nie ein Kopfpreis.
                  // Darum hier und nicht in der Staffeltabelle: die rendert nur beim
                  // PflichtTicket, der Irrtum droht aber auf allen drei Ticket-Seiten. ?>
            <span class="lv-calc__hint"><strong>Der angezeigte Preis ist immer der Gesamtpreis für dein ganzes Team — du multiplizierst ihn nicht mit der Kopfzahl.</strong> 12 Monate Laufzeit, jährliche Abrechnung, inkl. MwSt.</span>
        </div>

        <?php echo livento_cc_tariff_tiers_table($family); ?>

        <?php foreach ($variants as $index => $variant) :
            $plan    = $variant['plan'];
            $bundle  = $variant['bundle'];
            $price   = livento_cc_calc_price($plan, $users);
            $courses = livento_cc_get_bundle_courses($bundle['id']);

            $lessons = 0;
            foreach ((array) $courses as $course) {
                $lessons += (int) $course['lesson_count'];
            }
        ?>
        <section class="lv-variant" data-lv-plan="<?php echo esc_attr($plan['id']); ?>">
            <div class="lv-variant__bar">
                <div>
                    <h3><?php echo esc_html($bundle['name']); ?></h3>
                    <p class="lv-variant__meta">
                        <?php echo esc_html((int) $bundle['course_count']); ?> Kurse ·
                        <?php echo esc_html(rtrim(rtrim(number_format((float) $bundle['total_hours_sum'], 1, ',', '.'), '0'), ',')); ?> Stunden ·
                        <?php echo esc_html($lessons); ?> Lektionen
                    </p>
                </div>
                <div class="lv-variant__buy">
                    <div class="lv-variant__price" data-lv-price>
                        <?php if ($price['mode'] === 'individual') : ?>
                            <strong data-lv-price-main>Individuelles Angebot</strong>
                            <span data-lv-price-sub>Ab 151 Beschäftigten rechnen wir individuell.</span>
                        <?php else :
                            // v1.40.0: Zwei Zeilen statt einer. Zeile 1 bindet den Betrag an die
                            // Kopfzahl aus dem Rechner — "Gesamtpreis" fuehrt, damit auch das
                            // "entspricht 29,00 € / Monat" dahinter eindeutig als Teambetrag liest
                            // und nicht wieder als Kopfpreis. Zeile 2 rechnet den Kopfpreis selbst
                            // vor: 2,90 € je Person und Monat ist das Argument, das die Seite
                            // bisher verschenkt hat.
                            $v_line1 = array_filter(array(
                                livento_cc_total_note($price),
                                'entspricht ' . livento_cc_eur($price['monthly_net']) . ' / Monat',
                            ));
                            $v_per_user = livento_cc_per_user_note($price);
                            $v_line2 = array_filter(array(
                                $v_per_user ? 'das sind ' . $v_per_user : null,
                                livento_cc_tax_note($price),
                                livento_cc_net_note($price),
                            ));
                        ?>
                            <strong data-lv-price-main><?php echo esc_html(livento_cc_eur($price['yearly_net'])); ?><small> / Jahr</small></strong>
                            <span data-lv-price-sub>
                                <?php echo esc_html(implode(' · ', $v_line1)); ?><br>
                                <?php echo esc_html(implode(' · ', $v_line2)); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <?php echo livento_cc_tariff_cta($bundle, $price, $users); ?>
                </div>
            </div>

            <?php /* v1.36.0: bundle['description'] wird hier NICHT mehr gerendert.
               Es ist der WooCommerce-Produkttext ("Was du kaufst … So geht es nach dem
               Kauf weiter … Personalwechsel? Kein Problem …"). Auf einer Produktseite
               steht er allein und ergibt Sinn; hier stapelten sich vier davon, zu 98 %
               wortgleich — 5.546 Zeichen Wiederholung auf einer Seite. Genau diesen
               Inhalt erzaehlt "So laeuft's ab" jetzt EINMAL, die Kennzahlen stehen in
               der Faktenbox, der Rest in der Kursliste. Auf den WooCommerce-Produkt-
               seiten bleibt der Text unangetastet. */ ?>

            <?php if (!empty($bundle['public_note'])) : ?>
                <?php // Ehrlicher Hinweis an der Variante — z. B. welche Themen noch
                      // produziert werden. Getrennt vom internen description-Feld, in
                      // dem solche Notizen bisher nur intern standen. ?>
                <p class="lv-variant__note"><?php echo esc_html($bundle['public_note']); ?></p>
            <?php endif; ?>

            <?php // v1.61.0: Kacheln je Themenbereich statt einer Liste nach Kursthemen (dort standen
                  // 268 von 273 Kursen unter „Weitere Themen“). Stil der E-Learning-Seite (elt-paket). ?>
            <?php if (!empty($courses)) : ?>
                <?php echo livento_cc_ticket_areas_html($courses, $bundle, $index); ?>
            <?php endif; ?>
        </section>
        <?php endforeach; ?>

        <?php // v1.49.0: Lernpfade erklaeren — nur auf der CampusTicket-Seite. Die Pfade selbst
              // bleiben in der App (keine Liste hier), die Seite erklaert nur das Prinzip. ?>
        <?php if (in_array($family['key'], array('ticket', 'campusticket'), true)) : ?>
        <section class="lv-lernpfade" id="lernpfade">
            <h2>Lernpfade: Pflichtkurse mit einem Klick zuweisen</h2>
            <p class="lv-lernpfade__intro">Mit dem CampusTicket hat jede Person mit Lizenz Zugang zu allen Online-Kursen. Welche Kurse wirklich Pflicht sind, legst du über Lernpfade fest. Ein Lernpfad ist eine fertige Kurssammlung in sinnvoller Reihenfolge.</p>
            <div class="lv-lernpfade__grid">
                <div class="lv-lernpfade__card">
                    <strong>Fertige Pfade von uns</strong>
                    <span>Zum Beispiel die jährlichen Pflichtunterweisungen für dein Setting oder die Kurse für eine Rolle wie Praxisanleitung oder Betreuung. Wir halten sie aktuell. Kommt ein Kurs dazu, landet er automatisch bei allen, denen der Pfad zugewiesen ist.</span>
                </div>
                <div class="lv-lernpfade__card">
                    <strong>Passend zur Funktion</strong>
                    <span>Du trägst bei jeder Person ihre Funktion ein, zum Beispiel Pflegefachkraft oder Betreuungskraft. Ein Assistent schlägt dir die passenden Pfade vor. Du bestätigst sie für die ganze Gruppe und passt einzelne Personen bei Bedarf an.</span>
                </div>
                <div class="lv-lernpfade__card">
                    <strong>Mit Frist und Erinnerung</strong>
                    <span>Du legst fest, bis wann ein Pfad erledigt sein soll. Deine Mitarbeitenden bekommen eine Erinnerung vor der Frist. Du siehst in der Team-Ampel, wer fertig ist und wo etwas offen ist. Jährliche Unterweisungen werden automatisch wieder fällig.</span>
                </div>
                <div class="lv-lernpfade__card">
                    <strong>Kein Kurs doppelt</strong>
                    <span>Steckt ein Kurs in zwei Pfaden, muss ihn trotzdem niemand zweimal machen. Es zählt die früheste Frist. Nimmst du einen Pfad wieder weg, bleiben die Kurse, die ein anderer Pfad noch braucht.</span>
                </div>
                <div class="lv-lernpfade__card">
                    <strong>Eigene Pfade</strong>
                    <span>Du stellst dir eigene Lernpfade aus dem ganzen Katalog zusammen, etwa für die Einarbeitung neuer Kolleginnen und Kollegen. Sie sind nur für dein Team sichtbar.</span>
                </div>
                <div class="lv-lernpfade__card">
                    <strong>Freiwillig weiterlernen</strong>
                    <span>Alles, was nicht zugewiesen ist, können deine Mitarbeitenden jederzeit selbst starten. Du siehst davon nur die abgeschlossenen Kurse, keine Abbrüche und keine Testversuche.</span>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <?php // v1.36.0: „So laeuft's ab". Die Schritte stehen bisher nur in den
              // product_plans.description-Texten (die das Plugin nicht rendert) und in
              // der Willkommensmail — also erst NACH dem Kauf. Vor dem Kauf ist genau
              // das die Frage: „Was passiert, wenn ich hier klicke?" ?>
        <section class="lv-tarif-ablauf">
            <h2>So läuft's ab</h2>
            <ol class="lv-steps">
                <li>
                    <strong>Ticket buchen</strong>
                    <span>Die Bestellmenge ist die Zahl deiner Beschäftigten. Rechnung auf die Einrichtung, Abrechnung jährlich im Voraus.</span>
                </li>
                <li>
                    <strong>Zugang kommt automatisch</strong>
                    <span>Wir legen deinen Arbeitgeber-Zugang und dein Lizenzpaket an. Die Zugangsdaten kommen per E-Mail — du musst niemanden anrufen.</span>
                </li>
                <li>
                    <strong>Team eintragen</strong>
                    <span>Einzeln oder per CSV-Import. Halte Vorname, Nachname, E-Mail und Geburtsdatum bereit — das Geburtsdatum steht später auf dem Zertifikat.</span>
                </li>
                <li>
                    <strong>Nachweise sammeln sich von allein</strong>
                    <span>Jede Person lernt im eigenen Tempo, legt den Wissenstest ab und bekommt ihr Zertifikat. Du siehst im Team-Bereich, wer wo steht. Verlässt jemand die Einrichtung, gibst du den Platz frei und besetzt ihn neu.</span>
                </li>
            </ol>
        </section>

        <?php if (!empty($faq)) : ?>
            <?php // v1.35.0: Sichtbar UND als FAQPage-Schema. Google verlangt, dass
                  // ausgezeichnete FAQ-Inhalte auf der Seite auch tatsaechlich stehen —
                  // Schema allein waere ein Richtlinienverstoss. Beide Ausgaben speisen
                  // sich aus livento_cc_faq_items(), koennen also nicht auseinanderlaufen. ?>
            <section class="lv-tarif-faq">
                <h2>Häufige Fragen</h2>
                <?php foreach ($faq as $item) : ?>
                    <details class="lv-faq__item">
                        <summary><?php echo esc_html($item['q']); ?></summary>
                        <div class="lv-faq__answer"><?php echo wp_kses_post(wpautop($item['a'])); ?></div>
                    </details>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>

        <?php // v1.43.0: Testzugang. Steht VOR dem Schluss-CTA, damit der Kaufweg das
              // letzte Wort behaelt. ?>
        <?php echo livento_cc_trial_section($variants); ?>

        <?php // v1.36.0: Schluss-CTA. Wer bis hierher gelesen hat, soll nicht wieder
              // hochscrollen muessen, um zum Rechner zu kommen. ?>
        <section class="lv-tarif-cta">
            <h2>Bereit für dein Team?</h2>
            <p>Trag deine Beschäftigtenzahl ein und du siehst deinen Preis sofort — ohne Anfrage, ohne Wartezeit.</p>
            <div class="lv-tarif-cta__actions">
                <a class="lv-btn" href="#rechner">Preis für dein Team berechnen</a>
                <a class="lv-btn lv-btn--ghost" href="<?php echo esc_url($beratung); ?>">Lieber kurz sprechen?</a>
            </div>
        </section>
    </div>
    <?php
    livento_cc_tariff_calc_script();
    return ob_get_clean();
});

/** Kauf-Button bzw. Angebots-CTA einer Variante. */
function livento_cc_tariff_cta($bundle, $price, $users) {
    // Individuell (ab 151 Beschaeftigte) oder ohne WooCommerce-Produkt: kein Warenkorb.
    if ($price['mode'] === 'individual' || empty($bundle['wc_product_id'])) {
        $anchor = '#angebot';
        return '<a class="lv-btn lv-btn--ghost" href="' . esc_url($anchor) . '">Angebot anfordern</a>'
             . livento_cc_trial_link($bundle);
    }

    $url = !empty($bundle['wc_checkout_url'])
        ? $bundle['wc_checkout_url']
        : add_query_arg(
            array('add-to-cart' => (int) $bundle['wc_product_id'], 'quantity' => (int) $users),
            function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/')
        );

    return '<a class="lv-btn" href="' . esc_url($url) . '" data-lv-cart="' . esc_attr((int) $bundle['wc_product_id']) . '">'
         . 'Jetzt buchen</a>'
         . livento_cc_trial_link($bundle);
}

/**
 * v1.43.0: Dezenter Textlink zum Testzugang unter dem Kaufknopf.
 *
 * Bewusst KEIN gleichrangiger Knopf: Der Kaufknopf bleibt die Hauptaktion. Ein gleich
 * grosser "Kostenlos testen" daneben holt zuverlaessig auch die ab, die ohnehin gekauft
 * haetten — aus einem Kauf werden dann 14 Tage Wartezeit mit ungewissem Ausgang.
 *
 * Die bundle_id reist im data-Attribut mit, damit das Formular weiter unten genau die
 * Setting-Variante uebernimmt, die der Besucher gerade ansieht.
 */
function livento_cc_trial_link($bundle) {
    if (empty($bundle['id'])) {
        return '';
    }
    return '<a class="lv-trial-link" href="#testzugang"'
         . ' data-lv-trial-bundle="' . esc_attr($bundle['id']) . '"'
         . ' data-lv-trial-name="' . esc_attr((string) $bundle['name']) . '">'
         . 'oder erst 14 Tage testen</a>';
}

/**
 * v1.43.0: Abschnitt "Erst testen, dann entscheiden" samt Anfrageformular.
 *
 * Erklaert zuerst, was passiert — ein Formular ohne Kontext beantwortet die Frage nicht,
 * die der Besucher an dieser Stelle hat ("was kostet mich das, und was passiert danach?").
 */
function livento_cc_trial_section($variants) {
    if (empty($variants)) {
        return '';
    }

    $options = '';
    foreach ($variants as $v) {
        if (empty($v['bundle']['id'])) { continue; }
        $options .= '<option value="' . esc_attr($v['bundle']['id']) . '">'
                  . esc_html((string) $v['bundle']['name']) . '</option>';
    }
    if ($options === '') {
        return '';
    }

    $datenschutz = livento_cc_privacy_url();

    ob_start(); ?>
    <section class="lv-trial" id="testzugang">
        <h2>Erst testen, dann entscheiden</h2>
        <p class="lv-trial__lead">
            Sieh dir das Ticket mit deinem eigenen Team an, bevor du buchst — mit echten
            Kursen, echtem Lernfortschritt und gültigen Zertifikaten.
        </p>

        <ul class="lv-trial__facts">
            <li><strong>Bis zu 5 Plätze</strong> für 14 oder 28 Tage.</li>
            <li><strong>Die Zeit läuft erst los</strong>, wenn du die erste Person einlädst — nicht ab Freischaltung.</li>
            <li><strong>Zertifikate bleiben gültig</strong>, auch wenn du dich gegen eine Buchung entscheidest.</li>
            <li><strong>Buchst du später</strong>, macht dein Team genau dort weiter, wo es aufgehört hat.</li>
        </ul>

        <p class="lv-trial__note">
            Wir schalten jeden Testzugang von Hand frei und melden uns in der Regel
            innerhalb eines Werktages bei dir.
        </p>

        <form class="lv-trial-form" novalidate
              data-endpoint="<?php echo esc_url(rest_url('livento/v1/trial')); ?>"
              data-redirect="https://livento-bildung.de/danke-anfrage/">
            <div class="lv-trial-form__grid">
                <label class="lv-trial-field lv-trial-field--wide">
                    <span>Einrichtung *</span>
                    <input type="text" name="company_name" autocomplete="organization" required>
                </label>
                <label class="lv-trial-field">
                    <span>Vorname *</span>
                    <input type="text" name="first_name" autocomplete="given-name" required>
                </label>
                <label class="lv-trial-field">
                    <span>Nachname *</span>
                    <input type="text" name="last_name" autocomplete="family-name" required>
                </label>
                <label class="lv-trial-field">
                    <span>E-Mail *</span>
                    <input type="email" name="email" autocomplete="email" required>
                </label>
                <label class="lv-trial-field">
                    <span>Telefon (optional)</span>
                    <input type="tel" name="phone" autocomplete="tel">
                </label>
                <label class="lv-trial-field">
                    <span>Beschäftigte *</span>
                    <input type="number" name="employee_count" min="1" step="1" required>
                </label>
                <label class="lv-trial-field">
                    <span>Testdauer *</span>
                    <select name="requested_days">
                        <option value="14">14 Tage</option>
                        <option value="28">28 Tage (4 Wochen)</option>
                    </select>
                </label>
                <label class="lv-trial-field lv-trial-field--wide">
                    <span>Welches Ticket möchtest du testen? *</span>
                    <select name="bundle_id"><?php echo $options; ?></select>
                </label>
            </div>

            <?php // Honeypot — fuer Menschen unsichtbar. ?>
            <div class="lvk-hp" aria-hidden="true">
                <input type="text" name="hp_field" tabindex="-1" autocomplete="off">
            </div>

            <?php /* ZWEI Haekchen, nur das erste ist Pflicht. Die Verarbeitung der Angaben
               ist Vertragsanbahnung und braucht keine Einwilligung; den Testzugang von der
               Werbeeinwilligung abhaengig zu machen waere eine unzulaessige Kopplung. */ ?>
            <label class="lv-trial-consent">
                <input type="checkbox" name="privacy_ack" value="1" required>
                <span>Ich habe die <a href="<?php echo esc_url($datenschutz); ?>" target="_blank" rel="noopener">Datenschutzhinweise</a> zur Kenntnis genommen. *</span>
            </label>
            <label class="lv-trial-consent">
                <input type="checkbox" name="marketing_consent" value="1">
                <span>Zusätzlich möchte ich Informationen zu Angeboten und Weiterbildungsthemen von Livento per E-Mail erhalten. (freiwillig)</span>
            </label>

            <p class="lv-trial-err" hidden></p>
            <button type="submit" class="lv-btn">Testzugang anfragen</button>
            <p class="lv-trial__small">* Pflichtfelder. Kein Abo, keine Zahlungsdaten — der Zugang endet automatisch.</p>
        </form>
    </section>
    <?php
    return ob_get_clean() . livento_cc_trial_js();
}

/** Datenschutzseite: WordPress-Einstellung, sonst der uebliche Pfad. */
function livento_cc_privacy_url() {
    $url = function_exists('get_privacy_policy_url') ? get_privacy_policy_url() : '';
    return $url ? $url : home_url('/datenschutz/');
}

/**
 * v1.43.0: Absenden des Testzugang-Formulars.
 *
 * Der Redirect auf die Danke-Seite passiert ERST nach bestaetigtem Eingang. Sonst
 * zaehlt die Conversion auch dann, wenn die Anfrage nie angekommen ist — und niemand
 * merkt es, weil der Besucher die Danke-Seite gesehen hat.
 */
function livento_cc_trial_js() {
    static $done = false;
    if ($done) { return ''; }
    $done = true;
    return <<<'JS'
<script>
(function(){
  var form=document.querySelector('.lv-trial-form');
  if(!form) return;
  var err=form.querySelector('.lv-trial-err');
  var btn=form.querySelector('button[type=submit]');
  var sel=form.querySelector('[name=bundle_id]');
  var busy=false;

  // "oder erst 14 Tage testen" uebernimmt die angesehene Setting-Variante.
  Array.prototype.forEach.call(document.querySelectorAll('[data-lv-trial-bundle]'), function(a){
    a.addEventListener('click', function(){
      var id=a.getAttribute('data-lv-trial-bundle');
      if(sel && id){ sel.value=id; }
    });
  });

  // Beschaeftigtenzahl aus dem Preisrechner vorbelegen — die Zahl hat der Besucher
  // oben schon eingetippt, ein zweites Mal ist reine Reibung.
  var calc=document.querySelector('[data-lv-users], #lv-users, input[name=lv_users]');
  var empField=form.querySelector('[name=employee_count]');
  if(calc && empField){
    var sync=function(){ if(!empField.value && calc.value) empField.value=calc.value; };
    sync();
    calc.addEventListener('change', sync);
  }

  function fail(msg){ if(err){ err.textContent=msg; err.hidden=false; } }

  form.addEventListener('submit', function(e){
    e.preventDefault();
    if(busy) return;
    if(err) err.hidden=true;

    var g=function(n){ var el=form.querySelector('[name="'+n+'"]'); return el?String(el.value||'').trim():''; };
    var checked=function(n){ var el=form.querySelector('[name="'+n+'"]'); return !!(el&&el.checked); };

    if(!g('company_name')||!g('first_name')||!g('last_name')) return fail('Bitte Einrichtung und Namen ausfüllen.');
    if(!/.+@.+\..+/.test(g('email'))) return fail('Bitte eine gültige E-Mail-Adresse angeben.');
    if(!g('employee_count')) return fail('Bitte die Zahl der Beschäftigten angeben.');
    if(!checked('privacy_ack')) return fail('Bitte die Kenntnisnahme der Datenschutzhinweise bestätigen.');

    var payload={
      company_name:g('company_name'), first_name:g('first_name'), last_name:g('last_name'),
      email:g('email'), phone:g('phone'), employee_count:Number(g('employee_count')),
      requested_days:Number(g('requested_days')), bundle_id:g('bundle_id'),
      source_url:location.href, hp_field:g('hp_field'),
      privacy_ack:true,
      privacy_ack_text:'Ich habe die Datenschutzhinweise zur Kenntnis genommen.',
      marketing_consent:checked('marketing_consent'),
      marketing_text:checked('marketing_consent')
        ? 'Informationen zu Angeboten und Weiterbildungsthemen von Livento per E-Mail.' : null
    };

    busy=true; if(btn){ btn.disabled=true; btn.textContent='Wird gesendet …'; }
    fetch(form.getAttribute('data-endpoint'),{
      method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)
    }).then(function(r){
      return r.json().catch(function(){ return {ok:r.ok}; });
    }).then(function(j){
      if(!j||j.ok!==true){ throw new Error((j&&j.message)||'Die Anfrage konnte nicht gesendet werden.'); }
      try{ window.dataLayer=window.dataLayer||[];
        window.dataLayer.push({event:'generate_lead',lead_type:'testzugang',lead_source:'ticketseite'}); }catch(_e){}
      location.href=form.getAttribute('data-redirect');
    }).catch(function(ex){
      busy=false; if(btn){ btn.disabled=false; btn.textContent='Testzugang anfragen'; }
      fail(ex && ex.message ? ex.message : 'Die Anfrage konnte nicht gesendet werden. Bitte später erneut versuchen.');
    });
  });
})();
</script>
JS;
}

/**
 * v1.43.0: REST-Proxy fuer die Testanfrage.
 *
 * Serverseitig weiter an Campus Connect — kein CORS, und der anon-Schluessel bleibt aus
 * dem Quelltext der Seite draussen. Die Sperren sitzen hier, weil Campus Connect hinter
 * dem Proxy nur noch die IP des Hosters sieht.
 */
add_action('rest_api_init', function () {
    register_rest_route('livento/v1', '/trial', array(
        'methods'             => 'POST',
        'callback'            => 'livento_cc_rest_trial',
        'permission_callback' => '__return_true',
    ));
});
function livento_cc_rest_trial($req) {
    $p = $req->get_json_params();
    if (!is_array($p)) { $p = array(); }

    // Honeypot: ausgefuelltes verstecktes Feld = Bot -> "ok" vortaeuschen.
    if (!empty($p['hp_field'])) {
        return new WP_REST_Response(array('ok' => true), 200);
    }

    $email = isset($p['email']) ? sanitize_email($p['email']) : '';
    if (!is_email($email)) {
        return new WP_REST_Response(array('ok' => false, 'message' => 'Bitte eine gültige E-Mail-Adresse angeben.'), 200);
    }
    if (empty($p['privacy_ack'])) {
        return new WP_REST_Response(array('ok' => false, 'message' => 'Bitte die Kenntnisnahme der Datenschutzhinweise bestätigen.'), 200);
    }

    // Sperre: 5 Anfragen je IP und Stunde, eine je E-Mail und Tag. Bewusst als stiller
    // Erfolg — eine Fehlermeldung wuerde nur einem Bot verraten, dass er erkannt wurde.
    $ip      = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
    $ip_key  = 'lvk_trial_ip_' . md5($ip);
    $mail_key = 'lvk_trial_mail_' . md5(strtolower($email));
    if (get_transient($mail_key)) {
        return new WP_REST_Response(array('ok' => true), 200);
    }
    $hits = (int) get_transient($ip_key);
    if ($hits >= 5) {
        return new WP_REST_Response(array('ok' => true), 200);
    }
    set_transient($ip_key, $hits + 1, HOUR_IN_SECONDS);
    set_transient($mail_key, 1, DAY_IN_SECONDS);

    $key = livento_cc_anon_key();
    if ($key === '') {
        error_log('[livento] Testanfrage: kein Supabase-Schluessel hinterlegt.');
        return new WP_REST_Response(array('ok' => false, 'message' => 'Die Anfrage konnte nicht gesendet werden. Bitte melde dich telefonisch.'), 200);
    }

    $payload = array(
        'company_name'      => isset($p['company_name']) ? sanitize_text_field($p['company_name']) : '',
        'first_name'        => isset($p['first_name']) ? sanitize_text_field($p['first_name']) : '',
        'last_name'         => isset($p['last_name']) ? sanitize_text_field($p['last_name']) : '',
        'email'             => $email,
        'phone'             => isset($p['phone']) ? sanitize_text_field($p['phone']) : '',
        'employee_count'    => isset($p['employee_count']) ? (int) $p['employee_count'] : null,
        'requested_days'    => isset($p['requested_days']) ? (int) $p['requested_days'] : 14,
        'bundle_id'         => isset($p['bundle_id']) ? sanitize_text_field($p['bundle_id']) : null,
        'source_url'        => isset($p['source_url']) ? esc_url_raw($p['source_url']) : '',
        'privacy_ack'       => true,
        'privacy_ack_text'  => isset($p['privacy_ack_text']) ? sanitize_text_field($p['privacy_ack_text']) : '',
        'marketing_consent' => !empty($p['marketing_consent']),
        'marketing_text'    => isset($p['marketing_text']) ? sanitize_text_field((string) $p['marketing_text']) : null,
    );

    $res = wp_remote_post(LIVENTO_CC_SUPABASE_URL . '/functions/v1/submit-trial-request', array(
        'timeout' => 15,
        'headers' => array(
            'Content-Type'         => 'application/json',
            'apikey'               => $key,
            'Authorization'        => 'Bearer ' . $key,
            // Damit Campus Connect nicht nur den Hoster sieht.
            'x-livento-client-ip'  => $ip,
        ),
        'body'    => wp_json_encode($payload),
    ));

    if (is_wp_error($res)) {
        error_log('[livento] Testanfrage fehlgeschlagen: ' . $res->get_error_message());
        return new WP_REST_Response(array('ok' => false, 'message' => 'Die Anfrage konnte nicht gesendet werden. Bitte später erneut versuchen.'), 200);
    }
    $code = (int) wp_remote_retrieve_response_code($res);
    if ($code < 200 || $code >= 300) {
        error_log('[livento] Testanfrage: Campus Connect antwortete HTTP ' . $code . ' — ' . substr((string) wp_remote_retrieve_body($res), 0, 300));
        // Die Sperre wieder loesen, sonst blockiert ein Serverfehler den zweiten Versuch.
        delete_transient($mail_key);
        return new WP_REST_Response(array('ok' => false, 'message' => 'Die Anfrage konnte nicht gesendet werden. Bitte später erneut versuchen.'), 200);
    }

    return new WP_REST_Response(array('ok' => true), 200);
}

/**
 * v1.36.0: Kennzahlen einer Familie ueber alle Setting-Varianten hinweg.
 *
 * Fuer die Faktenbox „Auf einen Blick" — sie steht ganz oben, bevor die Varianten
 * gerendert werden, muss also vorher rechnen. Bewusst Spannen (min–max) statt einer
 * Zahl: Das PflichtTicket hat je nach Zuschnitt zwischen 11 und 24 Kursen; „20 Kurse"
 * waere fuer drei von vier Zuschnitten schlicht falsch.
 */
function livento_cc_tariff_stats($variants) {
    $kurse_min = null; $kurse_max = 0;
    $std_min   = null; $std_max   = 0.0;
    $monate    = null;
    foreach ($variants as $v) {
        $k = (int) $v['bundle']['course_count'];
        $s = (float) $v['bundle']['total_hours_sum'];
        if ($kurse_min === null || $k < $kurse_min) { $kurse_min = $k; }
        if ($k > $kurse_max) { $kurse_max = $k; }
        if ($std_min === null || $s < $std_min) { $std_min = $s; }
        if ($s > $std_max) { $std_max = $s; }
        $m = (int) (isset($v['plan']['contract_months']) ? $v['plan']['contract_months'] : 12);
        // Nur zeigen, wenn alle Plaene dieselbe Laufzeit haben — sonst waere die Angabe geraten.
        if ($monate === null) { $monate = $m; } elseif ($monate !== $m) { $monate = 0; }
    }
    return array(
        'varianten' => count($variants),
        'kurse_min' => (int) $kurse_min, 'kurse_max' => $kurse_max,
        'std_min'   => (float) $std_min, 'std_max'   => $std_max,
        'monate'    => (int) $monate,
    );
}

/** v1.36.0: „11" bzw. „11–24" — eine Spanne nur dann, wenn sie eine ist. */
function livento_cc_span($min, $max, $fmt = null) {
    $f = $fmt ? $fmt : function ($x) { return (string) (int) $x; };
    return $min === $max ? $f($min) : $f($min) . '–' . $f($max);
}

/**
 * v1.34.0: Highlights einer Familie als Liste von Strings.
 *
 * Die Spalte existiert seit v3.157.0 und ist gut gepflegt — sie wurde bis hier nur
 * auf der Landing gerendert, nicht auf der Detailseite. Genau diese sieben Punkte
 * ("Nachweisuebersicht fuer das ganze Team auf Knopfdruck") waren der Unterschied
 * zwischen einer leblosen und einer verkaufenden Seite.
 * Wie bei livento_cc_faq_items(): PostgREST liefert das JSON i. d. R. dekodiert,
 * String-Fallback zur Sicherheit; leere Eintraege fliegen raus.
 */
function livento_cc_tariff_highlights($family) {
    $raw = isset($family['highlights']) ? $family['highlights'] : array();
    if (is_string($raw)) {
        $raw = json_decode($raw, true);
    }
    if (!is_array($raw)) {
        return array();
    }
    $items = array();
    foreach ($raw as $entry) {
        if (!is_scalar($entry)) {
            continue;
        }
        $text = trim((string) $entry);
        if ($text !== '') {
            $items[] = $text;
        }
    }
    return $items;
}

/**
 * v1.40.0: Die Preisstaffel offen als Tabelle.
 *
 * Sie stand vorher NIRGENDS auf der Seite — weder Pauschalgrenze noch Staffelpreise.
 * Die einzige Erklaerung war eine zugeklappte FAQ, die selbst keine Zahlen nannte
 * ("bis zu einer bestimmten Teamgroesse"). Wer wissen wollte, was er zahlt, musste
 * den Rechner durchprobieren. Das ist die Luecke hinter "der Preisplan kommt nicht
 * transparent zur Sprache".
 *
 * Rendert bewusst NUR, wo es wirklich eine Staffel gibt:
 *  - genau ein Plan je Familie — das RollenTicket hat 18 Plaene mit je EINER Staffel
 *    (49 €/Person/Jahr); 18 identische Ein-Zeilen-Tabellen waeren Laerm.
 *  - mindestens zwei Staffeln — ein einzelner Satz ist keine Staffel, sondern ein
 *    Preis (KomplettTicket: 99 €/Person/Jahr). Der steht schon an der Variante.
 * Damit trifft die Tabelle heute genau das PflichtTicket, ohne das irgendwo zu
 * behaupten: Bekommt das KomplettTicket morgen eine Staffel, erscheint sie von selbst.
 */
function livento_cc_tariff_tiers_table($family) {
    $plans = array_values((array) $family['plans']);
    if (count($plans) !== 1) {
        return '';
    }

    $plan  = $plans[0];
    $tiers = isset($plan['tiers']) && is_array($plan['tiers']) ? $plan['tiers'] : array();
    if (count($tiers) < 2) {
        return '';
    }

    // Nach Teamgroesse sortieren: Die Tabelle muss aufsteigend lesbar sein, egal wie
    // die View sie liefert.
    usort($tiers, function ($a, $b) {
        return ((int) $a['min_users']) <=> ((int) $b['min_users']);
    });

    $months = max(1, (int) (isset($plan['contract_months']) ? $plan['contract_months'] : 12));
    $tax    = !empty($plan['is_vat_exempt']) ? 'USt-frei' : 'inkl. MwSt';

    // Kein Vorspann: Dass der Preis fuer das ganze Team gilt, steht schon direkt darueber
    // am Rechner (lv-calc__hint). Zweimal derselbe Satz auf zwei Zentimetern liest sich
    // wie eine Rechtfertigung.
    $out  = '<section class="lv-tiers" aria-labelledby="lv-tiers-h">';
    $out .= '<h2 id="lv-tiers-h">So rechnet sich das</h2>';
    $out .= '<table class="lv-tiers__table"><thead><tr>'
          . '<th scope="col">Teamgröße</th><th scope="col">Preis</th>'
          . '</tr></thead><tbody>';

    foreach ($tiers as $tier) {
        $out .= '<tr><th scope="row">' . esc_html(livento_cc_tier_range_text($tier)) . '</th>'
              . '<td>' . esc_html(livento_cc_tier_price_text($tier, $months)) . '</td></tr>';
    }

    $out .= '</tbody></table>';
    $out .= '<p class="lv-tiers__note">Alle Beträge ' . esc_html($tax) . ' · ' . esc_html($months)
          . ' Monate Laufzeit · Abrechnung jährlich im Voraus.</p>';
    $out .= '</section>';

    return $out;
}


/* v1.34.0: livento_cc_tariff_schema() ist entfallen. Sie gab das Product im Body aus,
 * mit einem Offer je Variante zum Festpreis fuer die gerade eingestellte Teamgroesse —
 * also einem Preis, der sich mit jeder Eingabe im Rechner aenderte. Ersetzt durch
 * livento_cc_jsonld_tariff_product() (AggregateOffer, lowPrice aus price_from), das
 * ueber den SEO-Pfad in den <head> bzw. den Rank-Math-@graph geht. */

/** Angebotsrechner: holt die Preise vom REST-Endpunkt, rechnet NICHT selbst. */
function livento_cc_tariff_calc_script() {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    ?>
    <script>
    (function () {
        var endpoint = <?php echo wp_json_encode(esc_url_raw(rest_url('livento/v1/tarif-preis'))); ?>;
        var inputs = document.querySelectorAll('[data-lv-calc-input]');
        if (!inputs.length) { return; }
        var timer = null;

        /** Bausteine mit " · " verbinden, leere weglassen. */
        function join(parts) {
            return parts.filter(function (p) { return p; }).join(' · ');
        }

        /** Text fuer innerHTML entschaerfen (Guertel und Hosentraeger — die Bausteine
         *  kommen ohnehin serverseitig aus Zahlen und festen Wortern). */
        function esc(text) {
            var box = document.createElement('div');
            box.textContent = text;
            return box.innerHTML;
        }

        function paint(el, info, isDetail) {
            var box = el.querySelector('[data-lv-price]');
            if (!info || !box) { return; }
            var main = box.querySelector('[data-lv-price-main]');
            var sub  = box.querySelector('[data-lv-price-sub]');

            if (info.mode === 'individual') {
                main.textContent = 'Individuelles Angebot';
                sub.textContent  = isDetail
                    ? 'Ab 151 Beschäftigten rechnen wir individuell.'
                    : 'Wir rechnen dir das gern durch.';
            } else if (isDetail) {
                // v1.40.0: Zwei Zeilen, identisch zusammengesetzt wie serverseitig gerendert.
                // Hier wird weiterhin NICHT gerechnet — alle Bausteine kommen fertig
                // formatiert vom Endpunkt, das JS fuegt sie nur zusammen.
                main.innerHTML = info.yearly + '<small> / Jahr</small>';
                var l1 = join([info.total_note, 'entspricht ' + info.monthly + ' / Monat']);
                var l2 = join([
                    info.per_user_note ? 'das sind ' + info.per_user_note : null,
                    info.tax_note,
                    info.net_note
                ]);
                // innerHTML statt textContent nur wegen des <br>. Unbedenklich: jeder
                // Baustein ist serverseitig aus Zahlen und festen Wortern gebaut, es
                // faellt nichts vom Besucher hier hinein.
                sub.innerHTML = esc(l1) + '<br>' + esc(l2);
            } else {
                // v1.42.0: Familien-Karte — der Kopfpreis fuer die eingegebene Teamgroesse,
                // NIE die Teamsumme. "ab" faellt hier weg: nach der Eingabe ist es der exakte
                // Kopfpreis (PflichtTicket sinkt mit der Teamgroesse; Komplett/Rollen konstant).
                main.innerHTML  = info.head_monthly + '<small> / Monat</small>';
                sub.textContent = join(['pro Beschäftigtem', info.tax_note, info.head_net]);
            }
        }

        function render(data) {
            // v1.42.0: Familien-Karte — Kopfpreis fuer die eingegebene Teamgroesse. Der
            // Erstaufbau zeigt den "ab"-Kopfpreis; sobald der Besucher eine Zahl eintraegt,
            // ersetzt paint() ihn durch den exakten Kopfpreis (die Teamsumme wird NIE gezeigt).
            document.querySelectorAll('[data-lv-family]').forEach(function (card) {
                paint(card, data.families[card.getAttribute('data-lv-family')], false);
            });
            // Detailseite: jede Variante zeigt den Preis IHRES Plans — RollenTicket hat
            // vier Plaene mit unterschiedlichen Preisen.
            document.querySelectorAll('[data-lv-plan]').forEach(function (section) {
                paint(section, data.plans[section.getAttribute('data-lv-plan')], true);
            });

            // Warenkorb-Menge = Beschaeftigtenzahl.
            document.querySelectorAll('[data-lv-cart]').forEach(function (link) {
                try {
                    var url = new URL(link.href, window.location.origin);
                    url.searchParams.set('quantity', String(data.users));
                    link.href = url.toString();
                } catch (e) { /* ungueltige URL: Link unveraendert lassen */ }
            });
        }

        function update(users) {
            fetch(endpoint + '?users=' + encodeURIComponent(users))
                .then(function (r) { return r.json(); })
                .then(render)
                .catch(function () { /* Netzwerkfehler: die serverseitig gerenderten Preise bleiben stehen */ });
        }

        inputs.forEach(function (input) {
            input.addEventListener('input', function () {
                var users = parseInt(input.value, 10);
                if (!users || users < 1) { return; }
                inputs.forEach(function (other) { if (other !== input) { other.value = users; } });
                clearTimeout(timer);
                timer = setTimeout(function () { update(users); }, 250);
            });
        });
    })();
    </script>
    <?php
}

function livento_cc_tariff_styles() {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    ?>
    <style>
    <?php echo livento_cc_rating_css(); // v1.60.0: Sterne im Ticket-Kopf ?>
    .lv-tarife__head{text-align:center;max-width:46rem;margin:0 auto 1.5rem}
    .lv-tarife__head h2{margin:0 0 .5rem}
    .lv-calc{display:flex;flex-wrap:wrap;align-items:center;gap:.75rem;justify-content:center;background:#f5f7f8;border:1px solid #e3e8ea;border-radius:14px;padding:1rem 1.25rem;margin:0 auto 2rem;max-width:44rem}
    .lv-calc label{font-weight:600}
    .lv-calc input{width:6rem;padding:.45rem .6rem;border:1px solid #cdd5d8;border-radius:8px;font-size:1rem}
    .lv-calc__hint{flex-basis:100%;text-align:center;font-size:.85rem;color:#5c6a70}
    .lv-tarife__grid{display:grid;gap:1.25rem;grid-template-columns:repeat(auto-fit,minmax(17rem,1fr))}
    .lv-tarif{display:flex;flex-direction:column;border:1px solid #e3e8ea;border-radius:16px;padding:1.5rem;background:#fff}
    .lv-tarif__img{width:100%;height:9rem;object-fit:cover;border-radius:10px;margin-bottom:1rem}
    .lv-tarif h3{margin:0 0 .35rem}
    .lv-tarif__claim{margin:0 0 1rem;color:#5c6a70;font-size:.95rem}
    .lv-tarif__price{margin-bottom:1rem;padding-bottom:1rem;border-bottom:1px solid #eef1f2}
    .lv-tarif__price strong,.lv-variant__price strong{display:block;font-size:1.65rem;line-height:1.2}
    .lv-tarif__price small,.lv-variant__price small{font-size:.9rem;font-weight:400;color:#5c6a70}
    .lv-tarif__price span,.lv-variant__price span{font-size:.85rem;color:#5c6a70}
    .lv-tarif__facts{display:flex;gap:1rem;list-style:none;margin:0 0 1rem;padding:0;font-size:.85rem;color:#5c6a70}
    .lv-tarif__facts strong{display:block;font-size:1.1rem;color:#1d2b30}
    .lv-tarif__list{margin:0 0 1.25rem;padding-left:1.1rem;font-size:.92rem}
    .lv-tarif__list li{margin-bottom:.3rem}
    .lv-btn,.lv-tarif__cta{display:inline-block;margin-top:auto;text-align:center;background:#004D33;color:#fff;padding:.7rem 1.2rem;border-radius:10px;text-decoration:none;font-weight:600}
    .lv-btn:hover,.lv-tarif__cta:hover{background:#004D33;color:#fff}
    .lv-btn--ghost{background:transparent;color:#004D33;border:1px solid #004D33}
    .lv-btn--ghost:hover{background:#004D33;color:#fff}
    .lv-tarife__foot{margin-top:1.5rem;text-align:center;font-size:.85rem;color:#5c6a70}
    /* v1.38.0: Hero-Band in der Bildsprache der Orientierungsseiten (.lv-ahero auf
       /foerdermoeglichkeiten/): derselbe warme Verlauf, dasselbe Eyebrow-Muster, dieselbe
       Qurova-Headline in CI-Gruen. Die weisse Faktenbox setzt sich vor dem Verlauf ab.
       Die Zeilenlaenge begrenzt weiterhin __head (48rem).

       v1.39.0 — FULL-BLEED: Das Band bricht aus dem 1200px-Theme-Container aus und laeuft
       randlos ueber die Seite (wie .lv-ahero auf /foerdermoeglichkeiten/, das dort aber
       gratis randlos ist, weil die Seite auf Astras Full-Width-Template steht — diese
       Seiten stehen auf ast-plain-container und muessen ausbrechen).
         margin-inline: calc(50% - 50vw)  → 50% = halbe Containerbreite, 50vw = halbe
           Viewportbreite; die Differenz zieht das Band beidseitig bis an den Rand. Relativ
           gerechnet, also unabhaengig davon, wie breit der Theme-Container gerade ist.
         padding-inline: max(1.25rem, calc(50vw - 37.5rem)) → holt den INHALT wieder auf die
           1200px-Spur (37.5rem = 600px = halbe Containerbreite), faellt unter ~1240px
           Viewport auf 20px Rand zurueck. Der Inhalt bleibt damit buendig zum Rest der Seite.
       ACHTUNG 100vw: das zaehlt die Scrollbar MIT, 100% nicht — auf Windows mit klassischer
       Scrollbar ragt das Band dadurch ~15px ueber den Viewport und erzeugt Querscroll.
       Dagegen html{overflow-x:clip} weiter unten. Bewusst clip und NICHT hidden: hidden
       macht das Element zum Scroll-Container und bricht damit position:sticky (.lv-fb) und
       Anker-Sprunge (#rechner); clip kappt nur den Ueberstand und laesst beides heil. */
    .lv-tarif-band{background:radial-gradient(120% 120% at 50% 0%,#fff6f1 0%,#fcefe9 55%,#f4f7ec 100%);margin-left:calc(50% - 50vw);margin-right:calc(50% - 50vw);padding:clamp(2rem,4vw,3.5rem) max(1.25rem,calc(50vw - 37.5rem));margin-bottom:2rem}
    /* Der Guard zum Ausbruch oben — kappt die ~15px, die 100vw durch die mitgezaehlte
       Scrollbar zu viel ergibt. Greift global, wird aber nur auf Seiten ausgeliefert, die
       ueberhaupt einen Tarif-Shortcode rendern. clip statt hidden, siehe oben. */
    html{overflow-x:clip}
    .lv-tarif-detail__head{max-width:48rem;margin-bottom:1.75rem}
    .lv-tarif-detail__eyebrow{display:inline-block;margin-bottom:.6rem;font-family:"Inter Tight","Inter",sans-serif;font-weight:700;font-size:1rem;color:#004D33}
    .lv-tarif-detail__head h1{margin:0 0 .5rem;font-family:"Qurova","Figtree",sans-serif;font-weight:600;font-size:clamp(30px,4.4vw,48px);line-height:1.08;color:#004D33}
    .lv-lead{font-size:clamp(16px,1.4vw,19px);line-height:1.65;color:#334155;margin:0}
    .lv-tarif-detail__text{color:#334155}
    /* v1.36.0: Hero zweispaltig wie die Kursdetailseite — Text links, Faktenbox rechts.
       Unter 60rem stapelt es, die Faktenbox rutscht dann VOR den Fliesstext (order:-1),
       damit Preis und CTA auf dem Handy nicht unter 2000 Zeichen Beschreibung liegen.
       H1 und Claim stehen ausserhalb dieses Rasters, sonst zoege order:-1 sie mit. */
    .lv-tarif-hero{display:grid;gap:1.5rem;align-items:start;margin-bottom:0}
    @media(min-width:60rem){.lv-tarif-hero{grid-template-columns:minmax(0,1fr) 20rem}}
    @media(max-width:59.99rem){.lv-tarif-hero .lv-fb{order:-1}}
    .lv-fb{border:1px solid #e3e8ea;border-radius:16px;background:#fff;padding:1.25rem;position:sticky;top:1rem}
    @media(max-width:59.99rem){.lv-fb{position:static}}
    .lv-fb__eyebrow{margin:0;font-size:.72rem;letter-spacing:.08em;text-transform:uppercase;color:#5c6a70}
    .lv-fb__title{margin:.15rem 0 .9rem;font-weight:700;font-size:1.05rem;color:#1d2b30}
    .lv-fb__list{margin:0;padding:0}
    .lv-fb__row{display:flex;gap:.7rem;align-items:flex-start;padding:.55rem 0;border-top:1px solid #f2f5f6}
    .lv-fb__row:first-child{border-top:0}
    .lv-fb__ic{color:#004D33;flex-shrink:0;line-height:0;margin-top:.1rem}
    .lv-fb__rc{display:block}
    .lv-fb__row dt{margin:0;font-size:.72rem;letter-spacing:.04em;text-transform:uppercase;color:#5c6a70}
    .lv-fb__row dd{margin:.1rem 0 0;font-size:.92rem;color:#1d2b30}
    .lv-fb__price{display:block;font-size:1.35rem;line-height:1.2}
    /* v1.40.0: Die Bezugsgroesse steht zwischen Betrag und Steuerhinweis — dunkler und
       eine Spur groesser als der Steuerhinweis, weil sie kein Kleingedrucktes ist,
       sondern die Bedingung, unter der die Zahl darueber ueberhaupt stimmt. */
    .lv-fb__scope{display:block;font-size:.85rem;font-weight:600;color:#004D33;margin-top:.1rem}
    .lv-fb__tax{display:block;font-size:.8rem;color:#5c6a70}
    .lv-fb__actions{display:grid;gap:.5rem;margin-top:1rem}
    .lv-fb__actions .lv-btn{margin-top:0}
    .lv-fb__trust{display:flex;flex-wrap:wrap;gap:.5rem .9rem;margin-top:.9rem;padding-top:.8rem;border-top:1px solid #f2f5f6;font-size:.78rem;color:#5c6a70}
    .lv-tarif-detail__highlights{list-style:none;margin:1.25rem 0 0;padding:0;display:grid;gap:.55rem}
    .lv-tarif-detail__highlights li{position:relative;padding-left:1.6rem;font-size:.97rem}
    /* Haken als ::before statt als Listenpunkt: bleibt Text, kostet kein Bild. */
    .lv-tarif-detail__highlights li::before{content:"✓";position:absolute;left:0;top:0;color:#004D33;font-weight:700}
    /* v1.35.0: FAQ-Accordion. <details> statt JS — funktioniert ohne Skript und ist
       fuer Crawler auch im zugeklappten Zustand lesbar. */
    .lv-tarif-faq{max-width:48rem;margin-top:2rem}
    .lv-tarif-faq h2{margin:0 0 .75rem}
    .lv-faq__item{border:1px solid #e3e8ea;border-radius:12px;background:#fff;margin-bottom:.6rem}
    .lv-faq__item summary{cursor:pointer;font-weight:600;padding:.85rem 1rem;list-style:none;display:flex;justify-content:space-between;gap:1rem;align-items:center}
    .lv-faq__item summary::-webkit-details-marker{display:none}
    .lv-faq__item summary::after{content:"+";color:#004D33;font-weight:700;font-size:1.2rem;line-height:1}
    .lv-faq__item[open] summary::after{content:"–"}
    .lv-faq__answer{padding:0 1rem 1rem;color:#5c6a70}
    .lv-faq__answer p{margin:0 0 .6rem}
    .lv-faq__answer p:last-child{margin-bottom:0}
    /* v1.36.0: „So laeuft's ab" — nummerierte Schritte via counter, damit die Ziffern
       im CI-Gruen stehen koennen (ein normales <ol> faerbt nur den Text). */
    /* v1.40.0: Staffeltabelle. Steht direkt unter dem Rechner, in derselben Kartenoptik
       wie „So laeuft's ab". Auf schmalen Schirmen scrollt sie NICHT horizontal — zwei
       Spalten passen, die Zellen duerfen umbrechen. */
    .lv-tiers{max-width:48rem;margin-top:2rem}
    .lv-tiers h2{margin:0 0 .75rem}
    .lv-tiers__table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #e3e8ea;border-radius:12px;overflow:hidden}
    .lv-tiers__table th,.lv-tiers__table td{text-align:left;padding:.7rem .9rem;border-bottom:1px solid #eef1f2;font-size:.92rem;vertical-align:top}
    .lv-tiers__table thead th{background:#f5f7f8;font-size:.72rem;letter-spacing:.04em;text-transform:uppercase;color:#5c6a70}
    .lv-tiers__table tbody th{font-weight:600;white-space:nowrap;color:#1d2b30}
    .lv-tiers__table tbody tr:last-child th,.lv-tiers__table tbody tr:last-child td{border-bottom:0}
    .lv-tiers__note{margin:.5rem 0 0;font-size:.82rem;color:#5c6a70}
    @media(max-width:480px){.lv-tiers__table tbody th{white-space:normal}}
    /* v1.49.0: Lernpfade (nur CampusTicket) */
    .lv-lernpfade{max-width:48rem;margin-top:2rem;scroll-margin-top:6rem}
    .lv-lernpfade h2{margin:0 0 .5rem}
    .lv-lernpfade__intro{margin:0 0 1rem;color:#5c6a70}
    .lv-lernpfade__grid{display:grid;gap:.75rem;grid-template-columns:repeat(2,minmax(0,1fr))}
    @media(max-width:640px){.lv-lernpfade__grid{grid-template-columns:1fr}}
    .lv-lernpfade__card{padding:.9rem 1rem;border:1px solid #e3e8ea;border-radius:12px;background:#fff;border-left:4px solid #AAC42B}
    .lv-lernpfade__card strong{display:block;margin-bottom:.2rem;color:#004D33}
    .lv-lernpfade__card span{font-size:.92rem;color:#5c6a70}
    .lv-tarif-ablauf{max-width:48rem;margin-top:2rem}
    .lv-tarif-ablauf h2{margin:0 0 .75rem}
    .lv-steps{list-style:none;counter-reset:s;margin:0;padding:0;display:grid;gap:.75rem}
    .lv-steps li{counter-increment:s;position:relative;padding:.9rem 1rem .9rem 3.2rem;border:1px solid #e3e8ea;border-radius:12px;background:#fff}
    .lv-steps li::before{content:counter(s);position:absolute;left:1rem;top:.9rem;width:1.5rem;height:1.5rem;border-radius:50%;background:#004D33;color:#fff;font-size:.82rem;font-weight:700;display:flex;align-items:center;justify-content:center}
    .lv-steps strong{display:block;margin-bottom:.15rem}
    .lv-steps span{font-size:.92rem;color:#5c6a70}
    /* v1.36.0: Schluss-CTA */
    .lv-tarif-cta{max-width:48rem;margin-top:2rem;padding:1.5rem;border-radius:16px;background:#f5f7f8;border:1px solid #e3e8ea;text-align:center}
    .lv-tarif-cta h2{margin:0 0 .35rem}
    .lv-tarif-cta p{margin:0 0 1rem;color:#5c6a70}
    .lv-tarif-cta__actions{display:flex;flex-wrap:wrap;gap:.6rem;justify-content:center}
    .lv-tarif-cta__actions .lv-btn{margin-top:0}

    /* v1.43.0 Testzugang. Der Textlink ist bewusst zurueckhaltend: Der Kaufknopf
       bleibt die Hauptaktion, der Test ist das Angebot fuer Zoegernde. */
    .lv-trial-link{display:block;margin-top:.55rem;text-align:center;font-size:.88rem;color:#5c6a70;text-decoration:underline}
    .lv-trial-link:hover{color:#004D33}
    .lv-trial{max-width:48rem;margin-top:2.5rem;padding:1.75rem;border-radius:16px;background:#fff;border:1px solid #e3e8ea}
    .lv-trial h2{margin:0 0 .35rem}
    .lv-trial__lead{margin:0 0 1rem;color:#5c6a70}
    .lv-trial__facts{list-style:none;margin:0 0 1rem;padding:0;display:grid;gap:.5rem}
    .lv-trial__facts li{position:relative;padding-left:1.6rem;font-size:.97rem}
    .lv-trial__facts li::before{content:"✓";position:absolute;left:0;top:0;color:#004D33;font-weight:700}
    .lv-trial__note{margin:0 0 1.25rem;padding:.75rem 1rem;border-radius:10px;background:#f5f7f8;font-size:.92rem;color:#5c6a70}
    .lv-trial-form__grid{display:grid;gap:.85rem;grid-template-columns:repeat(2,minmax(0,1fr))}
    @media(max-width:640px){.lv-trial-form__grid{grid-template-columns:1fr}}
    .lv-trial-field{display:flex;flex-direction:column;gap:.3rem;font-size:.88rem;color:#5c6a70}
    .lv-trial-field--wide{grid-column:1/-1}
    .lv-trial-field input,.lv-trial-field select{padding:.6rem .7rem;border:1px solid #d5dcdf;border-radius:9px;font-size:.97rem;color:#20343c;background:#fff}
    .lv-trial-field input:focus,.lv-trial-field select:focus{outline:2px solid #004D33;outline-offset:1px}
    .lv-trial-consent{display:flex;gap:.6rem;align-items:flex-start;margin-top:.9rem;font-size:.9rem;color:#5c6a70}
    .lv-trial-consent input{margin-top:.25rem}
    .lv-trial-err{margin:.8rem 0 0;color:#b3261e;font-size:.9rem}
    .lv-trial-form .lv-btn{margin-top:1.1rem}
    .lv-trial__small{margin:.7rem 0 0;font-size:.83rem;color:#7a878d}
    .lv-variant{border:1px solid #e3e8ea;border-radius:16px;padding:1.25rem;margin-bottom:1.25rem;background:#fff}
    .lv-variant__bar{display:flex;flex-wrap:wrap;gap:1rem;align-items:center;justify-content:space-between}
    .lv-variant__bar h3{margin:0}
    .lv-variant__meta{margin:.25rem 0 0;font-size:.9rem;color:#5c6a70}
    .lv-variant__buy{display:flex;align-items:center;gap:1.25rem;flex-wrap:wrap}
    /* v1.36.0: Ehrlicher Hinweis an der Variante (z. B. "Themen in Produktion").
       Bernstein statt Rot: Es ist eine Information, keine Fehlermeldung. */
    .lv-variant__note{margin:1rem 0 0;padding:.7rem .9rem;border-radius:10px;background:#fffbeb;border:1px solid #fde68a;color:#78350f;font-size:.88rem}
    .lv-courses{margin-top:1rem;border-top:1px solid #eef1f2;padding-top:.75rem}
    .lv-courses summary{cursor:pointer;font-weight:600}
    .lv-courses__group{margin-top:1rem}
    .lv-courses__group h4{margin:0 0 .5rem;font-size:.95rem}
    .lv-courses__group h4 span{color:#5c6a70;font-weight:400}
    .lv-courses__group ul{list-style:none;margin:0;padding:0}
    .lv-courses__group li{padding:.5rem 0;border-bottom:1px solid #f2f5f6}
    .lv-course__title{display:block;font-weight:600}
    .lv-course__meta{display:block;font-size:.82rem;color:#5c6a70}
    .lv-course__meta em{font-style:normal;font-family:ui-monospace,monospace}
    /* v1.41.0: Regelwerke je Kurs. Zurueckhaltend — es ist ein Beleg, keine Werbung. */
    .lv-course__norms{display:block;margin-top:2px;font-size:.78rem;color:#6b7a80;line-height:1.45}
    .lv-course__norm-ed{color:#8a969b}
    /* v1.61.0: Kacheln je Themenbereich — Kartenstil der E-Learning-Seite (.elt-paket). */
    .lv-kb{--g:#004D33;--lime:#AAC42B;--body:#334155;margin-top:1.75rem;padding-top:1.75rem;border-top:1px solid #eef1f2}
    .lv-kb [hidden]{display:none!important}
    .lv-kb__eyebrow{display:block;text-align:center;font-size:16px;color:var(--body);margin-bottom:6px}
    .lv-kb__h{margin:0 0 10px;text-align:center;font-family:"Qurova","Figtree",sans-serif;font-weight:600;font-size:clamp(24px,3vw,34px);line-height:1.15;color:#1e293b}
    .lv-kb__lead{margin:0 auto 22px;text-align:center;max-width:680px;font-size:16px;line-height:1.6;color:var(--body)}
    .lv-kb__search{max-width:460px;margin:0 auto 28px}
    .lv-kb__search input{width:100%;box-sizing:border-box;border:1px solid #e3e7df;border-radius:10px;padding:12px 16px 12px 42px;font-size:15px;color:#1e293b;background:#fff url("data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 24 24%27 fill=%27none%27 stroke=%27%2394a3b8%27 stroke-width=%272%27 stroke-linecap=%27round%27%3E%3Ccircle cx=%2711%27 cy=%2711%27 r=%277%27/%3E%3Cpath d=%27M20 20l-3.5-3.5%27/%3E%3C/svg%3E") no-repeat 14px center/18px 18px}
    .lv-kb__grid{display:grid;grid-template-columns:repeat(4,1fr);gap:20px}
    .lv-kb a.lv-paket,.lv-kb a.lv-paket:hover,.lv-kb a.lv-paket *{text-decoration:none!important}
    .lv-kb .lv-paket{display:flex;flex-direction:column;background:#fff;border:1px solid #eef0ea;border-radius:18px;padding:24px 22px;color:inherit;transition:transform .2s,box-shadow .2s}
    .lv-kb .lv-paket:hover{transform:translateY(-4px);box-shadow:0 14px 30px rgba(0,77,51,.10)}
    .lv-kb .lv-paket--hi{border:2px solid var(--lime);padding:23px 21px}
    .lv-kb__t{display:block;margin:0 0 4px;font-family:"Qurova","Figtree",sans-serif;font-weight:600;font-size:20px;line-height:1.25;color:var(--g)}
    .lv-kb .lv-paket__tag{display:block;margin:0 0 18px;font-weight:600;font-size:14px;color:#6b7a72}
    .lv-kb .lv-paket__btn{margin-top:auto;align-self:flex-start;display:inline-block;background:var(--g);color:#fff!important;font-family:"Qurova","Figtree",sans-serif;font-weight:600;font-size:14px;padding:10px 18px;border-radius:10px}
    .lv-kb .lv-paket--hi .lv-paket__btn{background:#fff;color:var(--g)!important;box-shadow:inset 0 0 0 2px var(--g)}
    .lv-kb__tile--leer{opacity:.45}
    .lv-kb__panels{margin-top:24px;display:grid;gap:18px}
    .lv-kb__panel{background:#fff;border:1px solid #eef0ea;border-radius:18px;padding:26px 28px}
    .lv-kb--js .lv-kb__panel{border:2px solid var(--lime)}
    .lv-kb__panel h4{margin:0 0 4px;font-family:"Qurova","Figtree",sans-serif;font-weight:600;font-size:22px;color:var(--g)}
    .lv-kb__panel .lv-paket__tag{margin-bottom:10px}
    .lv-kb__list{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:1fr 1fr;column-gap:36px}
    .lv-kb__list li{padding:12px 0;border-bottom:1px solid #f1f3ee}
    .lv-kb__ct{display:block;font-weight:600;color:#1e293b;font-size:15px;line-height:1.4}
    .lv-kb__cm{display:block;font-size:13px;color:#64748b;margin-top:3px}
    .lv-kb__cm em{font-style:normal;font-family:ui-monospace,Consolas,monospace;font-size:12px}
    .lv-kb__cm strong{color:var(--g)}
    .lv-kb__cn{display:block;font-size:12.5px;color:#64748b;margin-top:3px}
    .lv-kb__cn abbr{text-decoration:none;border-bottom:1px dotted #94a3b8;color:var(--g);cursor:help}
    .lv-kb__leer{text-align:center;color:#64748b;margin:18px 0 0}
    @media(max-width:1000px){.lv-kb__grid{grid-template-columns:repeat(3,1fr)}}
    @media(max-width:760px){.lv-kb__grid{grid-template-columns:repeat(2,1fr);gap:12px}.lv-kb__list{grid-template-columns:1fr}.lv-kb .lv-paket{padding:16px 14px}.lv-kb .lv-paket--hi{padding:15px 13px}.lv-kb__t{font-size:16px}.lv-kb .lv-paket__tag{margin-bottom:12px;font-size:13px}.lv-kb .lv-paket__btn{font-size:13px;padding:8px 12px}.lv-kb__panel{padding:20px 16px}}
    @media(max-width:640px){.lv-variant__bar{flex-direction:column;align-items:flex-start}}
    </style>
    <?php
}

/* ------------------------------------------------------------
 * WooCommerce: Tarif-Produkte
 *
 * Ein WC-Produkt je Setting-Variante, verknuepft ueber das Produkt-Meta
 * `_livento_bundle_id`. Die Menge im Warenkorb IST die Beschaeftigtenzahl —
 * deshalb muss der Stueckpreis so gesetzt werden, dass
 * Stueckpreis x Menge = Staffelpreis ergibt.
 * ---------------------------------------------------------- */

/**
 * Produkt-Backend: Zeigt, ob dieses Produkt von Campus Connect als Tarif erkannt wird.
 *
 * Der Normalfall braucht hier KEINE Eingabe: Sobald die Produkt-ID in Campus Connect
 * am Bundle hinterlegt und die Variante oeffentlich ist, findet das Plugin sie selbst.
 * Das Auswahlfeld ist nur ein manueller Notnagel — es listet naturgemaess nur bereits
 * oeffentliche Varianten.
 */
add_action('woocommerce_product_options_general_product_data', function () {
    global $post;

    $auto = livento_cc_find_bundle_by_product($post->ID);

    echo '<div class="options_group">';
    if ($auto) {
        echo '<p class="form-field" style="color:#0f5c66"><strong>Livento-Tarif erkannt:</strong> '
           . esc_html($auto['family']['name'] . ' · ' . $auto['bundle']['name'])
           . '<br><span class="description">Preis, Mengenfeld und Kursliste werden automatisch gesetzt. Hier ist nichts weiter zu tun.</span></p>';
    } else {
        // v1.35.0: Die Variante muss OEFFENTLICH und AKTIV sein — beides sind getrennte
        // Schalter in zwei verschiedenen Dialogen. Der Hinweis nannte nur "oeffentlich"
        // und schickte damit Leute zu dem Schritt, den sie schon gemacht hatten.
        echo '<p class="form-field"><strong>Kein Livento-Tarif.</strong><br><span class="description">'
           . 'Damit dieses Produkt ein Tarif wird, muss die Variante in Campus Connect <strong>drei</strong> Dinge erfüllen:'
           . '<br>1. Die Produkt-ID (' . (int) $post->ID . ') steht unter <em>Kursbundles → Variante → Verkauf &amp; Website</em> — dort auch „auf der Website zeigen und verkaufen“ einschalten.'
           . '<br>2. Die Ausgabe ist <strong>aktiv</strong>: <em>Kursbundles → Variante → Bearbeiten → „Bundle-Ausgabe aktiv“</em>. Das ist ein <strong>anderer</strong> Schalter als der unter Punkt 1 — fehlt er, bleibt die Freischaltung wirkungslos.'
           . '<br>3. Danach hier den <em>Cache leeren</em> (Livento → Einstellungen), sonst dauert es bis zu 3 Stunden.'
           . '</span></p>';
    }

    $options = array('' => '— automatisch (empfohlen) —');
    foreach (livento_cc_get_tariffs() as $family) {
        foreach ((array) $family['plans'] as $plan) {
            foreach ((array) $plan['bundles'] as $bundle) {
                $options[$bundle['id']] = $family['name'] . ' · ' . $bundle['name'];
            }
        }
    }
    woocommerce_wp_select(array(
        'id'          => '_livento_bundle_id',
        'label'       => 'Tarif manuell zuordnen',
        'options'     => $options,
        'value'       => get_post_meta($post->ID, '_livento_bundle_id', true),
        'description' => 'Nur für Sonderfälle. Normalerweise leer lassen — die Zuordnung kommt aus Campus Connect.',
        'desc_tip'    => true,
    ));
    echo '</div>';
});

add_action('woocommerce_process_product_meta', function ($post_id) {
    $value = isset($_POST['_livento_bundle_id']) ? sanitize_text_field(wp_unslash($_POST['_livento_bundle_id'])) : '';
    update_post_meta($post_id, '_livento_bundle_id', $value);
});

/** Setting-Variante zu einer WooCommerce-Produkt-ID (ueber course_bundles.wc_product_id). */
function livento_cc_find_bundle_by_product($product_id) {
    $product_id = (int) $product_id;
    if (!$product_id) {
        return null;
    }
    foreach (livento_cc_get_tariffs() as $family) {
        foreach ((array) $family['plans'] as $plan) {
            foreach ((array) $plan['bundles'] as $bundle) {
                if (!empty($bundle['wc_product_id']) && (int) $bundle['wc_product_id'] === $product_id) {
                    return array('family' => $family, 'plan' => $plan, 'bundle' => $bundle);
                }
            }
        }
    }
    return null;
}

/**
 * Bundle-Kontext eines WooCommerce-Produkts.
 *
 * Primaer ueber die Produkt-ID, die in Campus Connect am Bundle hinterlegt ist
 * (course_bundles.wc_product_id) — das ist die einzige Pflegestelle. Ein zweites
 * Mapping im Shop waere nicht nur doppelte Buchfuehrung, es funktionierte beim
 * Einrichten auch gar nicht: Das Auswahlfeld unten kennt nur OEFFENTLICHE Tarife,
 * ein Bundle wird aber erst oeffentlich, wenn die Produkt-ID drinsteht. Das Feld
 * bleibt nur als manueller Notnagel erhalten.
 */
function livento_cc_product_bundle($product_id) {
    $context = livento_cc_find_bundle_by_product($product_id);
    if ($context) {
        return $context;
    }
    $bundle_id = get_post_meta($product_id, '_livento_bundle_id', true);
    return $bundle_id ? livento_cc_find_bundle($bundle_id) : null;
}

/**
 * Warenkorbpreis aus der Staffel. WooCommerce multipliziert den Stueckpreis mit der
 * Menge — bei einer Pauschale (z. B. 29 EUR bis 15 Beschaeftigte) muss der Stueckpreis
 * deshalb Gesamtpreis/Menge sein, sonst wuerde die Pauschale mit der Kopfzahl skalieren.
 */
add_action('woocommerce_before_calculate_totals', function ($cart) {
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }
    if (did_action('woocommerce_before_calculate_totals') >= 2) {
        return;
    }

    foreach ($cart->get_cart() as $item) {
        $context = livento_cc_product_bundle($item['product_id']);
        if (!$context) {
            continue;
        }
        $users = max(1, (int) $item['quantity']);
        $price = livento_cc_calc_price($context['plan'], $users);
        if ($price['mode'] === 'individual' || !isset($price['yearly_net'])) {
            continue;
        }
        $item['data']->set_price($price['yearly_net'] / $users);
    }
});

/** Produktkachel/-seite: „ab X € / Jahr" statt des gepflegten Platzhalterpreises. */
add_filter('woocommerce_get_price_html', function ($html, $product) {
    $context = livento_cc_product_bundle($product->get_id());
    if (!$context) {
        return $html;
    }
    $price = livento_cc_calc_price($context['plan'], 1);
    if ($price['mode'] === 'individual' || !isset($price['yearly_net'])) {
        return '<span class="lv-price-html">Individuelles Angebot</span>';
    }
    return '<span class="lv-price-html">ab ' . esc_html(livento_cc_eur($price['yearly_net']))
         . ' / Jahr <small>' . esc_html(livento_cc_tax_note($price)) . '</small></span>';
}, 10, 2);

/** Mengenfeld beschriften: es sind Beschaeftigte, keine Stueckzahl. */
add_filter('woocommerce_quantity_input_args', function ($args, $product) {
    if (livento_cc_product_bundle($product->get_id())) {
        $args['input_value'] = max(1, (int) $args['input_value']);
        $args['min_value']   = 1;
    }
    return $args;
}, 10, 2);

add_action('woocommerce_before_add_to_cart_quantity', function () {
    global $product;
    if ($product && livento_cc_product_bundle($product->get_id())) {
        echo '<label class="lv-qty-label" for="quantity">Anzahl Lizenzen (Beschäftigte)</label>';
    }
});

/**
 * Warenkorb-Zaehler im Website-Header.
 *
 * Der Header lauscht auf ein `lv:cart`-Event (detail.count). Die Tickets landen ueber
 * einen normalen Link (?add-to-cart=…) im Warenkorb — also per Seiten-Reload, NICHT per
 * AJAX. Darum feuert von selbst nie ein Event, und der Zaehler bleibt leer, obwohl etwas
 * im Warenkorb liegt. Wir feuern das Event deshalb beim Laden jeder Seite mit dem echten
 * Stand; zusaetzlich bei WooCommerce-AJAX-Events (falls Add/Remove doch per AJAX passiert).
 *
 * count = Anzahl Positionen (Tickets) im Warenkorb, nicht die Lizenzmenge — der Badge zeigt
 * also "1", wenn ein Ticket drin liegt. Fuer die Gesamtmenge stattdessen
 * WC()->cart->get_cart_contents_count() ausgeben.
 *
 * Hinweis Caching: Der Startwert wird serverseitig gerendert. Warenkorb-/Checkout-Seiten
 * werden von WooCommerce nicht gecacht (dort stimmt es also immer). Sollten Landingpages
 * per Full-Page-Cache ausgeliefert werden, kann der Startwert veralten — dann den Wert
 * client-seitig aus den WC-Fragmenten lesen statt serverseitig rendern.
 */
add_action('wp_footer', function () {
    if (!function_exists('WC') || is_null(WC()->cart)) {
        return;
    }
    $count = count(WC()->cart->get_cart());
    ?>
    <script>
    (function () {
        function lvEmit(n) {
            document.dispatchEvent(new CustomEvent('lv:cart', { detail: { count: n } }));
        }
        function lvReady(fn) {
            if (document.readyState !== 'loading') { fn(); }
            else { document.addEventListener('DOMContentLoaded', fn); }
        }
        lvReady(function () { lvEmit(<?php echo (int) $count; ?>); });
        if (window.jQuery) {
            jQuery(function ($) {
                $(document.body).on('added_to_cart removed_from_cart', function () {
                    var m = document.cookie.match(/(?:^|;\s*)woocommerce_items_in_cart=(\d+)/);
                    lvEmit(m ? parseInt(m[1], 10) : 0);
                });
            });
        }
    })();
    </script>
    <?php
}, 99);

/** Kursliste unter die Produktbeschreibung. */
add_action('woocommerce_after_single_product_summary', function () {
    global $product;
    $context = livento_cc_product_bundle($product->get_id());
    if (!$context) {
        return;
    }
    $courses = livento_cc_get_bundle_courses($context['bundle']['id']);
    if (empty($courses)) {
        return;
    }

    livento_cc_tariff_styles();
    $lessons = 0;
    foreach ($courses as $course) {
        $lessons += (int) $course['lesson_count'];
    }

    echo '<section class="lv-variant">';
    echo '<div class="lv-variant__bar"><div><h3>Diese Kurse sind enthalten</h3>';
    echo '<p class="lv-variant__meta">' . esc_html(count($courses)) . ' Kurse · '
       . esc_html(rtrim(rtrim(number_format((float) $context['bundle']['total_hours_sum'], 1, ',', '.'), '0'), ','))
       . ' Stunden · ' . esc_html($lessons) . ' Lektionen</p></div></div>';
    echo '<div class="lv-courses__group"><ul>';
    foreach ($courses as $course) {
        echo '<li><span class="lv-course__title">' . esc_html($course['title']) . '</span><span class="lv-course__meta">';
        if (!empty($course['course_number'])) {
            echo '<em>' . esc_html($course['course_number']) . '</em> · ';
        }
        echo esc_html(rtrim(rtrim(number_format((float) $course['total_hours'], 1, ',', '.'), '0'), ',')) . ' '
           . esc_html(livento_cc_hours_unit_label($course['hours_unit'])) . ' · '
           . esc_html((int) $course['module_count']) . ' Module · '
           . esc_html((int) $course['lesson_count']) . ' Lektionen';
        if (!empty($course['auto_certify']) || !empty($course['certificate_title'])) {
            echo ' · <strong>Zertifikat</strong>';
        }
        echo '</span></li>';
    }
    echo '</ul></div></section>';
}, 15);

/**
 * Firma ist Pflicht, sobald ein Tarif im Warenkorb liegt.
 * Der Kauf legt in Campus Connect einen ARBEITGEBER an — ohne Firmennamen waere
 * der Datensatz wertlos, und der Team-Bereich haette keinen Namen.
 */
function livento_cc_cart_has_tariff() {
    if (!function_exists('WC') || !WC()->cart) {
        return false;
    }
    foreach (WC()->cart->get_cart() as $item) {
        if (livento_cc_product_bundle($item['product_id'])) {
            return true;
        }
    }
    return false;
}

add_filter('woocommerce_billing_fields', function ($fields) {
    if (livento_cc_cart_has_tariff() && isset($fields['billing_company'])) {
        $fields['billing_company']['required'] = true;
        $fields['billing_company']['label']    = 'Einrichtung / Unternehmen';
    }
    return $fields;
});

/** Danke-Seite: erklaert, wie es weitergeht (die Mitarbeitenden kommen NICHT aus dem Checkout). */
add_action('woocommerce_thankyou', function ($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }
    $has_tariff = false;
    foreach ($order->get_items() as $item) {
        if (livento_cc_product_bundle($item->get_product_id())) {
            $has_tariff = true;
            break;
        }
    }
    if (!$has_tariff) {
        return;
    }

    livento_cc_tariff_styles();
    echo '<section class="lv-variant"><h3>So geht es weiter</h3>';
    // Seit Campus Connect v3.169.0 kommt EINE Mail, die Zugangsdaten und Team-Link
    // zusammen enthaelt. Vorher stand hier "gleich zwei E-Mails" — die zweite gab
    // es nie, weil das Passwort mangels Platzhalter in der Vorlage verworfen wurde.
    echo '<p>Ihre Lizenzen sind aktiv. Sie erhalten dazu eine E-Mail mit Ihren Zugangsdaten '
       . 'für Campus Connect und dem Link in Ihren Team-Bereich.</p>';
    echo '<p>Dort legen Sie Ihre Mitarbeitenden an — einzeln oder per CSV-Import — und weisen ihnen die Lizenzplätze zu. '
       . 'Jede Person bekommt ihre Zugangsdaten automatisch per E-Mail und startet sofort: '
       . 'Die Kurse sind E-Learnings, es gibt keine Termine und keine Wartezeit. '
       . 'Verlässt jemand das Unternehmen, geben Sie den Platz einfach wieder frei.</p>';
    echo '</section>';
}, 15);
