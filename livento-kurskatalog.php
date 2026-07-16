<?php
/**
 * Plugin Name:       Livento Kurskatalog (nativ)
 * Plugin URI:        https://campus-connect.livento-bildung.de
 * Description:        Rendert den oeffentlichen Kurskatalog aus Campus Connect serverseitig nativ in WordPress (statt iframe) — damit der Katalog auf der WordPress-Domain indexierbar wird. Holt die Daten aus der Supabase-View `public_offerings` via PostgREST, cached sie als Transient und erzeugt Karten, Detailseiten, Filter, Schema.org-JSON-LD und kanonische URLs.
 * Version:           1.38.0
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
        'program'          => 'Weiterbildung',
        'scheduled_course' => 'Einzeltermin',
        'self_learning'    => 'Selbstlernkurs',
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

function livento_cc_duration_labels() {
    return array(
        'kurz_unter_2h' => 'Unter 2 Stunden',
        'halbtag'       => 'Halbtägig',
        'ganztag'       => 'Ganztägig',
        'mehrtaegig'    => 'Mehrtägig',
    );
}

function livento_cc_availability_labels() {
    return array(
        'verfuegbar'     => 'Sofort verfügbar',
        'wenige_plaetze' => 'Wenige Plätze',
        'ausgebucht'     => 'Ausgebucht',
        'warteliste'     => 'Warteliste',
    );
}

/** Dauer-Bucket aus Minuten (gespiegelt aus courseFilterLabels.ts). */
function livento_cc_bucket_duration($minutes) {
    $m = (int) $minutes;
    if ($m <= 0)   return '';
    if ($m < 120)  return 'kurz_unter_2h';
    if ($m <= 240) return 'halbtag';
    if ($m <= 480) return 'ganztag';
    return 'mehrtaegig';
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

/** Reichert jedes Angebot um abgeleitete Filter-Werte an (_duration/_startmonth/_availability). */
function livento_cc_augment($offerings) {
    foreach ($offerings as &$o) {
        $o['_duration']     = livento_cc_bucket_duration($o['duration_minutes'] ?? null);
        $o['_startmonth']   = !empty($o['start_datetime']) ? substr($o['start_datetime'], 0, 7) : '';
        $o['_availability'] = livento_cc_derive_availability($o['enrolled_count'] ?? 0, $o['max_participants'] ?? null);
    }
    unset($o);
    return $offerings;
}

/* ============================================================
 * 3. Datenabruf (PostgREST + Transient-Cache)
 * ============================================================ */

/** Gueltigkeitscheck am JWT-Format (lang + enthaelt Punkte). */
function livento_cc_key_is_valid($k) {
    return is_string($k) && strlen($k) > 40 && strpos($k, '.') !== false;
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

/**
 * Roher GET gegen die Supabase REST-API. Gibt ein dekodiertes Array oder
 * WP_Error zurueck.
 */
function livento_cc_rest_get($query) {
    $key = livento_cc_anon_key();
    $url = LIVENTO_CC_SUPABASE_URL . '/rest/v1/public_offerings?' . $query;

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
        return $cache;
    }

    $select = implode(',', array(
        'id', 'offering_type', 'title', 'slug', 'short_description', 'public_description',
        'public_image_url', 'public_price', 'is_vat_exempt', 'start_datetime', 'end_datetime',
        'site_name', 'site_city', 'format', 'level', 'is_azav_relevant', 'rbp_points',
        'duration_minutes', 'course_number', 'published_at', 'max_participants', 'enrolled_count',
        'show_availability_indicator', 'wc_checkout_url', 'is_free',
        // Filter-Dimensionen:
        'audience', 'funding', 'recognition', 'methodology', 'topics',
        // Sortierung:
        'booking_count', 'rating_avg', 'rating_count', 'is_featured', 'featured_order',
    ));
    $query = 'select=' . $select . '&order=start_datetime.asc.nullslast&limit=500';

    $data = livento_cc_rest_get($query);
    if (is_wp_error($data)) {
        // Fehler NICHT cachen — letzten guten Stand (Stale-While-Error) liefern.
        $stale = get_transient('livento_cc_list_stale');
        return $stale !== false ? $stale : array();
    }

    set_transient($list_key, $data, LIVENTO_CC_TTL);
    set_transient('livento_cc_list_stale', $data, DAY_IN_SECONDS); // Notfall-Fallback
    return $data;
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
        return $cache === 'NULL' ? null : $cache;
    }

    $query = 'select=*&slug=eq.' . rawurlencode($slug) . '&limit=1';
    $data = livento_cc_rest_get($query);
    if (is_wp_error($data)) {
        return null; // nicht cachen
    }
    $row = !empty($data) ? $data[0] : null;
    set_transient($key, $row === null ? 'NULL' : $row, LIVENTO_CC_TTL);
    return $row;
}

/**
 * Cache komplett leeren (Liste + alle Einzelkurse) durch Hochzaehlen der
 * Cache-Version — alte Transient-Schluessel werden damit auf einen Schlag
 * entwertet (und laufen per TTL aus). Aufruf via Purge-Webhook oder manuell.
 */
function livento_cc_flush_cache() {
    $old = livento_cc_ver();
    update_option('livento_cc_cachever', $old + 1);
    delete_transient('livento_cc_list_v' . $old); // alten Listen-Key sofort freigeben
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
        'url'   => livento_cc_detail_url($o['slug']),
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
        'availability'  => $sold_out ? 'https://schema.org/SoldOut' : 'https://schema.org/InStock',
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
    if (!empty($o['instructor_name'])) {
        $instance['instructor'] = array('@type' => 'Person', 'name' => $o['instructor_name']);
    }

    $schema = array(
        '@context'    => 'https://schema.org',
        '@type'       => 'Course',
        'name'        => $o['title'],
        'description' => $o['public_description'] ?: ($o['short_description'] ?: $o['title']),
        'provider'    => array(
            '@type' => 'EducationalOrganization',
            'name'  => LIVENTO_CC_PROVIDER,
            'url'   => 'https://livento-bildung.de',
        ),
        'url'               => $url,
        'hasCourseInstance' => array($instance),
    );
    if (!empty($o['public_image_url'])) {
        $schema['image'] = $o['public_image_url'];
    }
    if (isset($o['rating_avg']) && $o['rating_avg'] !== null && (int) ($o['rating_count'] ?? 0) > 0) {
        $schema['aggregateRating'] = array(
            '@type'       => 'AggregateRating',
            'ratingValue' => number_format((float) $o['rating_avg'], 2, '.', ''),
            'reviewCount' => (int) $o['rating_count'],
            'bestRating'  => 5,
            'worstRating' => 1,
        );
    }
    return $schema;
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

    // Titel-Stichwort (Teilstring, case-insensitive) — fuer Kategorien ohne eigenes
    // Facet wie „Pflichtfortbildung".
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

    $limit = max(0, (int) ($cfg['limit'] ?? 0));
    if ($limit > 0) {
        $offerings = array_slice($offerings, 0, $limit);
    }

    if (empty($offerings)) {
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
    foreach ($offerings as $o) {
        $out .= livento_cc_render_card($o);
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
    $offerings = livento_cc_augment($offerings);           // _duration/_startmonth/_availability
    $offerings = livento_cc_sort_offerings($offerings, $sort);
    if ($limit > 0) {
        $offerings = array_slice($offerings, 0, $limit);
    }

    $jsonld = '<script type="application/ld+json">' . wp_json_encode(livento_cc_jsonld_list($offerings)) . '</script>';

    // Kuratierter Block ohne Filter-UI (z. B. [livento_kurse limit="6"]): nur Karten, kein JS.
    if (!$show_filters) {
        $out = '<div class="lvk lvk-list">' . $jsonld . '<div class="lvk-grid">';
        foreach ($offerings as $o) {
            $out .= livento_cc_render_card($o);
        }
        return $out . '</div></div>';
    }

    // Voller Katalog mit Filter-Sidebar + Toolbar + JS.
    $out  = '<div class="lvk lvk-list" id="lvk-catalog">' . $jsonld;
    $out .= '<div class="lvk-layout">';
    $out .= '<aside class="lvk-sidebar">' . livento_cc_render_sidebar($offerings) . '</aside>';
    $out .= '<div class="lvk-main">';
    $out .= livento_cc_render_toolbar($sort);
    $out .= '<div class="lvk-grid" id="lvk-grid">';
    foreach ($offerings as $o) {
        $out .= livento_cc_render_card($o);
    }
    $out .= '</div>'; // .lvk-grid
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
            usort($offerings, function ($a, $b) { return (float) ($b['rating_avg'] ?? -1) <=> (float) ($a['rating_avg'] ?? -1); });
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
            usort($offerings, function ($a, $b) { return strcmp((string) ($a['start_datetime'] ?? '9999'), (string) ($b['start_datetime'] ?? '9999')); });
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
    return array(
        array('dim' => 'type',        'field' => 'offering_type', 'arr' => false, 'title' => 'Typ',            'lab' => livento_cc_type_labels()),
        array('dim' => 'format',      'field' => 'format',        'arr' => false, 'title' => 'Format',         'lab' => livento_cc_format_labels()),
        array('dim' => 'level',       'field' => 'level',         'arr' => false, 'title' => 'CarePath-Level', 'lab' => livento_cc_level_labels()),
        array('dim' => 'audience',    'field' => 'audience',      'arr' => true,  'title' => 'Zielgruppe',     'lab' => livento_cc_audience_labels()),
        array('dim' => 'funding',     'field' => 'funding',       'arr' => true,  'title' => 'Förderung',      'lab' => livento_cc_funding_labels()),
        array('dim' => 'recognition', 'field' => 'recognition',   'arr' => true,  'title' => 'Anerkennung',    'lab' => livento_cc_recognition_labels()),
        array('dim' => 'methodology', 'field' => 'methodology',   'arr' => true,  'title' => 'Methodik',       'lab' => livento_cc_methodology_labels()),
        array('dim' => 'topics',      'field' => 'topics',        'arr' => true,  'title' => 'Thema',          'lab' => null),
        array('dim' => 'duration',    'field' => '_duration',     'arr' => false, 'title' => 'Dauer',          'lab' => livento_cc_duration_labels()),
        array('dim' => 'startmonth',  'field' => '_startmonth',   'arr' => false, 'title' => 'Start-Monat',    'lab' => 'month'),
        array('dim' => 'availability','field' => '_availability', 'arr' => false, 'title' => 'Verfügbarkeit',  'lab' => livento_cc_availability_labels()),
        array('dim' => 'city',        'field' => 'site_city',     'arr' => false, 'title' => 'Ort',            'lab' => null),
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
        . '<button type="button" class="lvk-pill lvk-toggle" data-toggle="azav">AZAV</button>'
        . '<button type="button" class="lvk-pill lvk-toggle" data-toggle="vatexempt">USt-frei</button>'
        . '<button type="button" class="lvk-pill lvk-toggle" data-toggle="free">Kostenfrei</button>'
        . '</div>';

    $idx = 0;
    foreach (livento_cc_filter_groups() as $g) {
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

function livento_cc_render_card($o) {
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
            . '<img src="' . esc_url($o['public_image_url']) . '" alt="' . esc_attr($o['title']) . '" loading="lazy"></a>';
    }

    // data-Attribute fuer den clientseitigen Filter + Sortierung
    $data_text = strtolower(wp_strip_all_tags($o['title'] . ' ' . ($o['short_description'] ?? '') . ' ' . ($o['public_description'] ?? '')));
    $featured = !empty($o['is_featured']) ? (isset($o['featured_order']) ? (int) $o['featured_order'] : 0) : 99999;
    $price = ($o['public_price'] !== null && $o['public_price'] !== '') ? (string) (float) $o['public_price'] : '';
    $data = ' data-type="' . esc_attr((string) ($o['offering_type'] ?? '')) . '"'
          . ' data-format="' . esc_attr((string) ($o['format'] ?? '')) . '"'
          . ' data-level="' . esc_attr((string) ($o['level'] ?? '')) . '"'
          . ' data-city="' . esc_attr((string) ($o['site_city'] ?? '')) . '"'
          . ' data-audience="' . esc_attr(livento_cc_data_list($o, 'audience')) . '"'
          . ' data-funding="' . esc_attr(livento_cc_data_list($o, 'funding')) . '"'
          . ' data-recognition="' . esc_attr(livento_cc_data_list($o, 'recognition')) . '"'
          . ' data-methodology="' . esc_attr(livento_cc_data_list($o, 'methodology')) . '"'
          . ' data-topics="' . esc_attr(livento_cc_data_list($o, 'topics')) . '"'
          . ' data-duration="' . esc_attr((string) ($o['_duration'] ?? '')) . '"'
          . ' data-startmonth="' . esc_attr((string) ($o['_startmonth'] ?? '')) . '"'
          . ' data-availability="' . esc_attr((string) ($o['_availability'] ?? '')) . '"'
          . ' data-azav="' . (!empty($o['is_azav_relevant']) ? '1' : '') . '"'
          . ' data-vatexempt="' . (!empty($o['is_vat_exempt']) ? '1' : '') . '"'
          . ' data-free="' . (!empty($o['is_free']) ? '1' : '') . '"'
          . ' data-price="' . esc_attr($price) . '"'
          . ' data-start="' . esc_attr((string) ($o['start_datetime'] ?? '')) . '"'
          . ' data-published="' . esc_attr((string) ($o['published_at'] ?? '')) . '"'
          . ' data-booked="' . (int) ($o['booking_count'] ?? 0) . '"'
          . ' data-rating="' . esc_attr((string) ($o['rating_avg'] ?? '')) . '"'
          . ' data-featured="' . (int) $featured . '"'
          . ' data-text="' . esc_attr($data_text) . '"';

    $out  = '<article class="lvk-card"' . $data . '>';
    $out .= $img;
    $out .= '<div class="lvk-card-body">';
    if (!empty($o['format']) || !empty($o['is_azav_relevant'])) {
        $out .= '<div class="lvk-badges">';
        if (!empty($o['format'])) {
            $out .= '<span class="lvk-badge">' . esc_html(livento_cc_format_label($o['format'])) . '</span>';
        }
        if (!empty($o['is_azav_relevant'])) {
            $out .= '<span class="lvk-badge lvk-badge-azav">AZAV</span>';
        }
        $out .= '</div>';
    }
    $out .= '<h2 class="lvk-card-title">' . $title_html . '</h2>';
    if ($desc) {
        $out .= '<p class="lvk-card-desc">' . esc_html($desc) . '</p>';
    }
    if (!empty($meta)) {
        $out .= '<p class="lvk-card-meta">' . esc_html(implode(' · ', $meta)) . '</p>';
    }
    if ($url) {
        $label = $has_slug ? 'Details ansehen' : 'Jetzt buchen';
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
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M3 10h18M8 2v4M16 2v4"/>',
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
    if ($type === 'program') {
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
    if (!empty($o['certificate_title'])) {
        $abschluss = 'Qualifiziertes Zertifikat „' . $o['certificate_title'] . '"';
        if (!empty($o['rbp_points'])) {
            $abschluss .= ' · ' . (int) $o['rbp_points'] . ' RbP-Punkte';
        }
        $rows[] = array('cert', 'Abschluss', esc_html($abschluss));
    }
    $start = livento_cc_factbox_start($o);
    if ($start !== '') {
        $rows[] = array('calendar', 'Nächster Start', esc_html($start));
    }
    if (isset($o['public_price']) && $o['public_price'] !== null && $o['public_price'] !== '') {
        $rows[] = array('price', 'Kosten', esc_html(livento_cc_fmt_price($o['public_price'], !empty($o['is_vat_exempt']))));
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
    if (livento_cc_is_foerderbar($o)) {
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

    if (!empty($o['course_number'])) {
        $out .= '<p class="lvk-coursenr"><strong>Kurs-Nr.</strong> ' . esc_html($o['course_number']) . '</p>';
    }

    // Badges
    $badges = array();
    if ($format_label) {
        $badges[] = esc_html($format_label);
    }
    if (!empty($o['is_azav_relevant'])) {
        $badges[] = 'AZAV';
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
        $out .= '<img class="lvk-hero" src="' . esc_url($o['public_image_url']) . '" alt="' . esc_attr($o['title']) . '" loading="lazy">';
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

    if (!empty($o['target_audience'])) {
        $out .= '<div class="lvk-section"><h2>Zielgruppe</h2>' . livento_cc_richtext($o['target_audience']) . '</div>';
    }

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

    // Modulübersicht
    $out .= livento_cc_render_modules($o['modules'] ?? null);

    // Zugangsvoraussetzungen
    if (!empty($o['admission_requirements'])) {
        $out .= '<div class="lvk-section"><h2>Zugangsvoraussetzungen</h2>' . livento_cc_richtext($o['admission_requirements']) . '</div>';
    }

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
    }

    $out .= '</div>';
    return $out;
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
        return $has_desc || $has_lessons;
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
  var grid   = root.querySelector('#lvk-grid');
  var cards  = Array.prototype.slice.call(root.querySelectorAll('.lvk-card'));
  var search = root.querySelector('.lvk-search');
  var sortSel= root.querySelector('.lvk-sort');
  var priceSel= root.querySelector('.lvk-pricemax');
  var reset  = root.querySelector('.lvk-reset');
  var countEl= root.querySelector('.lvk-count');
  var total  = cards.length;

  var DIMS  = ['type','format','level','audience','funding','recognition','methodology','topics','duration','startmonth','availability','city'];
  var MULTI = {audience:1,funding:1,recognition:1,methodology:1,topics:1};
  var TOGGLES = ['azav','vatexempt','free'];
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
        default:            { var sa=str(a,'start')||'9999', sb=str(b,'start')||'9999'; return sa.localeCompare(sb); }
      }
    }).forEach(function(c){ if(grid) grid.appendChild(c); });
  }
  function apply(){
    var shown=0;
    cards.forEach(function(c){ var ok=matches(c); c.style.display=ok?'':'none'; if(ok)shown++; });
    var anyActive = term!=='' || priceMax!==null
      || DIMS.some(function(d){return active[d].length;})
      || TOGGLES.some(function(t){return toggles[t];});
    if(countEl) countEl.textContent = shown+' von '+total+' Kursen';
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
@media(max-width:860px){
  .lvk-layout{flex-direction:column}
  .lvk-sidebar{position:static;flex:1 1 auto;width:auto;box-sizing:border-box}
}
/* Karten */
.lvk-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px;margin:8px 0}
.lvk-card{border:1px solid #e6e6e6;border-left:4px solid var(--lvk-accent);border-radius:0 8px 8px 0;background:#fff;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 2px 4px rgba(0,0,0,.05)}
.lvk-card-img img{width:100%;height:160px;object-fit:cover;display:block}
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
.lvk-hero{max-width:100%;height:auto;border-radius:8px;margin:16px 0}
.lvk-lead{font-size:1.1rem;color:#444}
.lvk-facts{background:#f5f5f5;border-radius:8px;padding:16px;margin:24px 0}
.lvk-facts div{margin:6px 0}
.lvk-facts dt{font-weight:600;display:inline}
.lvk-facts dd{display:inline;margin:0 12px 0 4px}
.lvk-audience{color:var(--lvk-lime)}
.lvk-section{margin:28px 0}
.lvk-section h2{color:var(--lvk-green);font-size:1.3rem;border-bottom:2px solid var(--lvk-accent);padding-bottom:6px}
.lvk-benefit{background:#f3f8ee;border-radius:8px;padding:4px 20px}
.lvk-module{border:1px solid #e6e6e6;border-radius:8px;margin:10px 0;padding:0 14px}
.lvk-module summary{font-weight:600;color:var(--lvk-green);cursor:pointer;padding:12px 0}
.lvk-mod-hours{font-weight:400;color:var(--lvk-lime);font-size:.9rem}
.lvk-faq-item{border:1px solid #e6e6e6;border-radius:8px;margin:10px 0;padding:0 14px}
.lvk-faq-item summary{font-weight:600;color:var(--lvk-green);cursor:pointer;padding:12px 0}
.lvk-faq-a{padding:0 0 12px}
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
                'recognition' => 'Anerkennungs-Slug(s), Komma-getrennt (z. B. gesetzlich_anerkannt).',
                'q'           => 'Titel-Stichwort (z. B. Pflichtfortbildung) – nur Kurse mit diesem Text im Titel.',
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
        // Berater-Formular: roher Embed-Code (Admin-only, manage_options) — kein sanitize_text_field,
        // sonst wuerden iframe/script entfernt.
        update_option('livento_cc_berater_form', wp_unslash($_POST['livento_cc_berater_form'] ?? ''));
        update_option('livento_cc_foerder_form', wp_unslash($_POST['livento_cc_foerder_form'] ?? ''));
        update_option('livento_cc_berater_webhook', esc_url_raw(trim((string) wp_unslash($_POST['livento_cc_berater_webhook'] ?? ''))));
        update_option('livento_cc_foerder_webhook', esc_url_raw(trim((string) wp_unslash($_POST['livento_cc_foerder_webhook'] ?? ''))));
        update_option('livento_cc_beratung_url', esc_url_raw(trim((string) wp_unslash($_POST['livento_cc_beratung_url'] ?? ''))));
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
    $tabs = array('overview' => 'Übersicht', 'anleitung' => 'Anleitung', 'shortcodes' => 'Shortcodes', 'kurslisten' => 'Kurslisten', 'slugs' => 'Filter & Slugs', 'berater' => 'Berater', 'foerderung' => 'Förderprogramme', 'settings' => 'Einstellungen');

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
    echo '<details class="lvk-help"><summary>8 · Cache, Sitemap &amp; Updates</summary><div class="in">';
    echo '<ul>';
    echo '<li><strong>Cache leeren:</strong> <a href="' . $tab('overview') . '">Übersicht</a> → „Cache jetzt leeren".</li>';
    echo '<li><strong>Auto-Purge:</strong> <a href="' . $tab('settings') . '">Einstellungen</a> → Purge-Secret setzen + in Campus Connect hinterlegen.</li>';
    echo '<li><strong>Sitemap:</strong> <a href="' . esc_url(home_url('/livento-kurse.xml')) . '" target="_blank" rel="noopener">/livento-kurse.xml</a> (enthält Kurse + Förderungen, hängt im Rank-Math-Index).</li>';
    echo '<li><strong>Updates:</strong> Dashboard → Aktualisierungen → Erneut prüfen → Aktualisieren.</li>';
    echo '</ul></div></details>';

    // 9) Problembehebung
    echo '<details class="lvk-help"><summary>9 · Problembehebung</summary><div class="in"><ul>';
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

/** Tab „Filter & Slugs": Deep-Link-Parameter, Facet-Werte (live) + Kurs-Slugs. */
function livento_cc_admin_tab_slugs() {
    $offerings = livento_cc_augment(livento_cc_get_offerings());

    echo '<p style="margin-top:12px">Diese Parameter lassen sich an die Katalog-URL hängen (Deep-Linking) — z. B. <code>' . esc_html(livento_cc_list_url()) . '?format=online_live</code>. Mehrere Werte mit Komma, mehrere Parameter mit <code>&amp;</code>.</p>';

    echo '<h3>Suche &amp; Schalter</h3>';
    echo '<table class="widefat striped" style="max-width:820px"><thead><tr><th>Parameter</th><th>Wert(e)</th><th>Wirkung</th></tr></thead><tbody>';
    echo '<tr><td><code>q</code></td><td>Freitext</td><td>Volltextsuche (Titel + Beschreibung)</td></tr>';
    echo '<tr><td><code>azav</code></td><td><code>1</code></td><td>Nur AZAV-relevante Kurse</td></tr>';
    echo '<tr><td><code>vatexempt</code></td><td><code>1</code></td><td>Nur USt-freie Kurse</td></tr>';
    echo '<tr><td><code>free</code></td><td><code>1</code></td><td>Nur kostenfreie Kurse</td></tr>';
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
    echo '<p>Öffentlicher anon-Key (kein <code>service_role</code>). Hier gespeichert, übersteht er Plugin-Updates.</p>';
    if ($key_mask) {
        echo '<p>Aktuell hinterlegt: <code>' . esc_html($key_mask) . '</code></p>';
    }
    echo '<input type="password" name="livento_cc_anon_key" class="large-text code" value="' . esc_attr($key) . '" autocomplete="off" placeholder="eyJ…">';
    if (defined('LIVENTO_CC_ANON_KEY') && livento_cc_key_is_valid(LIVENTO_CC_ANON_KEY) && !livento_cc_key_is_valid($key)) {
        echo '<p class="description">Hinweis: In der Plugin-Datei/wp-config ist bereits ein Key gesetzt; dieses Feld hat Vorrang, sobald gespeichert.</p>';
    }

    echo '<h3 style="margin-top:24px">Cache-Purge-Webhook (optional)</h3>';
    echo '<p>Shared-Secret, damit Campus Connect den WP-Cache bei Kursänderungen <strong>sofort</strong> leeren kann. Leer = Webhook aus (Cache läuft per 3-Std.-TTL).</p>';
    echo '<p><label><strong>Secret:</strong><br><input type="text" name="livento_cc_purge_secret" class="regular-text code" value="' . esc_attr($secret) . '" autocomplete="off" placeholder="langer Zufallsstring" style="width:420px"></label></p>';
    echo '<p class="description">Webhook-URL (in Campus Connect hinterlegen): <code>POST ' . esc_html($webhook) . '</code><br>mit Header <code>X-Livento-Purge-Secret: &lt;Secret&gt;</code></p>';

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
    $h .= '<label style="display:block;margin-top:10px;max-width:560px">Titel-Stichwort <span class="description">(optional – nur Kurse, deren Titel diesen Text enthält)</span><br><input type="text" name="' . $n('q') . '" value="' . esc_attr($q) . '" class="regular-text" placeholder="z. B. Pflichtfortbildung"></label>';

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
        return mb_substr($f['title'], 0, 62) . ' – Förderung | Livento';
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
    if (!empty($f['funding_key'])) {
        $kurl = livento_cc_list_url() . '?funding=' . rawurlencode($f['funding_key']);
        $out .= '<p><a class="lvf-btn" href="' . esc_url($kurl) . '">Passende Kurse ansehen →</a></p>';
    }
    if (!empty($f['link'])) {
        $out .= '<p><a class="lvf-btn lvf-btn-ghost" href="' . esc_url($f['link']) . '" target="_blank" rel="noopener nofollow">Offizielle Informationen ↗</a></p>';
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
                $best  = livento_cc_cheapest_plan($family, $users);
                $price = $best ? $best['price'] : array('mode' => 'individual');

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

                <div class="lv-tarif__price" data-lv-price>
                    <?php if ($price['mode'] === 'individual') : ?>
                        <strong data-lv-price-main>Individuelles Angebot</strong>
                        <span data-lv-price-sub>Wir rechnen dir das gern durch.</span>
                    <?php else : ?>
                        <strong data-lv-price-main><?php echo esc_html(livento_cc_eur($price['monthly_net'])); ?><small> / Monat</small></strong>
                        <span data-lv-price-sub>
                            <?php echo esc_html(livento_cc_eur($price['yearly_net'])); ?> pro Jahr · <?php echo esc_html(livento_cc_tax_note($price)); ?><?php
                            $card_net = livento_cc_net_note($price);
                            if ($card_net) {
                                echo ' · ' . esc_html($card_net);
                            }
                            ?>
                        </span>
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
            <span class="lv-calc__hint">12 Monate Laufzeit, jährliche Abrechnung, inkl. MwSt.</span>
        </div>

        <?php foreach ($variants as $index => $variant) :
            $plan    = $variant['plan'];
            $bundle  = $variant['bundle'];
            $price   = livento_cc_calc_price($plan, $users);
            $courses = livento_cc_get_bundle_courses($bundle['id']);

            // Kurse nach Thema gruppieren; ohne Thema sammeln wir sie unter "Weitere Themen".
            $groups = array();
            foreach ((array) $courses as $course) {
                $topics = !empty($course['topics']) && is_array($course['topics']) ? $course['topics'] : array('_sonstige');
                $key    = $topics[0];
                if (!isset($groups[$key])) {
                    $groups[$key] = array();
                }
                $groups[$key][] = $course;
            }
            ksort($groups);

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
                        <?php else : ?>
                            <strong data-lv-price-main><?php echo esc_html(livento_cc_eur($price['yearly_net'])); ?><small> / Jahr</small></strong>
                            <span data-lv-price-sub>
                                entspricht <?php echo esc_html(livento_cc_eur($price['monthly_net'])); ?> / Monat · <?php echo esc_html(livento_cc_tax_note($price)); ?><?php
                                $variant_net = livento_cc_net_note($price);
                                if ($variant_net) {
                                    echo ' · ' . esc_html($variant_net);
                                }
                                ?>
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

            <?php if (!empty($courses)) : ?>
                <details class="lv-courses" <?php echo $index === 0 ? 'open' : ''; ?>>
                    <summary>Enthaltene Kurse ansehen (<?php echo esc_html(count($courses)); ?>)</summary>
                    <?php foreach ($groups as $topic => $items) : ?>
                        <div class="lv-courses__group">
                            <h4><?php echo esc_html(livento_cc_topic_label($topic)); ?> <span>(<?php echo esc_html(count($items)); ?>)</span></h4>
                            <ul>
                                <?php foreach ($items as $course) : ?>
                                    <li>
                                        <span class="lv-course__title"><?php echo esc_html($course['title']); ?></span>
                                        <span class="lv-course__meta">
                                            <?php if (!empty($course['course_number'])) : ?>
                                                <em><?php echo esc_html($course['course_number']); ?></em> ·
                                            <?php endif; ?>
                                            <?php echo esc_html(rtrim(rtrim(number_format((float) $course['total_hours'], 1, ',', '.'), '0'), ',')); ?>
                                            <?php echo esc_html($course['hours_unit'] === 'stunden' ? 'Std.' : 'UE'); ?> ·
                                            <?php echo esc_html((int) $course['module_count']); ?> Module ·
                                            <?php echo esc_html((int) $course['lesson_count']); ?> Lektionen
                                            <?php if (!empty($course['auto_certify']) || !empty($course['certificate_title'])) : ?>
                                                · <strong>Zertifikat</strong>
                                            <?php endif; ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                </details>
            <?php endif; ?>
        </section>
        <?php endforeach; ?>

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
        return '<a class="lv-btn lv-btn--ghost" href="' . esc_url($anchor) . '">Angebot anfordern</a>';
    }

    $url = !empty($bundle['wc_checkout_url'])
        ? $bundle['wc_checkout_url']
        : add_query_arg(
            array('add-to-cart' => (int) $bundle['wc_product_id'], 'quantity' => (int) $users),
            function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/')
        );

    return '<a class="lv-btn" href="' . esc_url($url) . '" data-lv-cart="' . esc_attr((int) $bundle['wc_product_id']) . '">'
         . 'Jetzt buchen</a>';
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

/** Themen-Slug lesbar machen (die Labels leben in Campus Connect; hier reicht eine Normalisierung). */
function livento_cc_topic_label($slug) {
    if ($slug === '_sonstige' || $slug === '') {
        return 'Weitere Themen';
    }
    return ucfirst(str_replace('-', ' ', $slug));
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

        function paint(el, info, isDetail) {
            var box = el.querySelector('[data-lv-price]');
            if (!info || !box) { return; }
            var main = box.querySelector('[data-lv-price-main]');
            var sub  = box.querySelector('[data-lv-price-sub]');

            // v1.34.0: net_note kommt fertig formatiert vom Server (eine Preisquelle).
            var net = info.net_note ? ' · ' + info.net_note : '';

            if (info.mode === 'individual') {
                main.textContent = 'Individuelles Angebot';
                sub.textContent  = isDetail
                    ? 'Ab 151 Beschäftigten rechnen wir individuell.'
                    : 'Wir rechnen dir das gern durch.';
            } else if (isDetail) {
                main.innerHTML  = info.yearly + '<small> / Jahr</small>';
                sub.textContent = 'entspricht ' + info.monthly + ' / Monat · ' + info.tax_note + net;
            } else {
                main.innerHTML  = info.monthly + '<small> / Monat</small>';
                sub.textContent = info.yearly + ' pro Jahr · ' + info.tax_note + net;
            }
        }

        function render(data) {
            // Landingpage: eine Karte je Familie ("ab X").
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
       Bewusst KEIN max-width am Band — es fuellt den Theme-Container; die Zeilenlaenge
       begrenzt weiterhin __head (48rem). Kein Full-Bleed via 100vw: der Trick bricht in
       Containern mit overflow und zieht bei sichtbarer Scrollbar Querscroll nach sich. */
    .lv-tarif-band{background:radial-gradient(120% 120% at 50% 0%,#fff6f1 0%,#fcefe9 55%,#f4f7ec 100%);border-radius:20px;padding:clamp(2rem,4vw,3.5rem) clamp(1.25rem,3vw,2.5rem);margin-bottom:2rem}
    @media(max-width:640px){.lv-tarif-band{border-radius:14px}}
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
           . esc_html($course['hours_unit'] === 'stunden' ? 'Std.' : 'UE') . ' · '
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
