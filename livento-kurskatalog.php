<?php
/**
 * Plugin Name:       Livento Kurskatalog (nativ)
 * Plugin URI:        https://campus-connect.livento-bildung.de
 * Description:        Rendert den oeffentlichen Kurskatalog aus Campus Connect serverseitig nativ in WordPress (statt iframe) — damit der Katalog auf der WordPress-Domain indexierbar wird. Holt die Daten aus der Supabase-View `public_offerings` via PostgREST, cached sie als Transient und erzeugt Karten, Detailseiten, Filter, Schema.org-JSON-LD und kanonische URLs.
 * Version:           1.23.0
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
    return array(
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

    // Faktenliste
    $facts = array();
    if (!empty($o['start_datetime'])) {
        $end = (!empty($o['end_datetime']) && $o['end_datetime'] !== $o['start_datetime'])
            ? ' – ' . livento_cc_fmt_date($o['end_datetime']) : '';
        $facts['Beginn'] = livento_cc_fmt_date($o['start_datetime']) . $end;
    }
    if (!empty($o['total_hours']) && ($o['hours_unit'] ?? '') === 'unterrichtsstunden') {
        $facts['Umfang'] = (int) $o['total_hours'] . ' UE';
    } elseif (!empty($o['total_hours'])) {
        $facts['Umfang'] = (int) $o['total_hours'] . ' Std.';
    } elseif (!empty($o['duration_minutes'])) {
        $facts['Umfang'] = round($o['duration_minutes'] / LIVENTO_CC_UE_MINUTES) . ' UE';
    }
    $ort = trim(implode(', ', array_filter(array($o['site_name'] ?? '', $o['site_city'] ?? ''))));
    if ($ort !== '') {
        $facts['Ort'] = $ort;
    }
    if (!empty($o['instructor_name'])) {
        $facts['Dozent:in'] = $o['instructor_name'];
    }
    if ($o['public_price'] !== null && $o['public_price'] !== '') {
        $facts['Preis'] = livento_cc_fmt_price($o['public_price'], !empty($o['is_vat_exempt']));
    }
    if (!empty($o['max_participants'])) {
        $facts['Teilnehmer'] = 'max. ' . (int) $o['max_participants'];
    }
    if (!empty($facts)) {
        $out .= '<dl class="lvk-facts">';
        foreach ($facts as $k => $v) {
            $out .= '<div><dt>' . esc_html($k) . ':</dt><dd>' . esc_html($v) . '</dd></div>';
        }
        $out .= '</dl>';
    }

    // CRO: „Auf einen Blick"-Cluster direkt unter den Fakten — Scarcity, Foerder-Hinweis,
    // Primaer-CTA above the fold, Trust-Zeile. Alle Bausteine konditional.
    $cluster  = livento_cc_scarcity_html($o);
    $cluster .= livento_cc_foerder_hint_html($o);
    $cluster .= livento_cc_cta_buttons($o, 'top');
    if ($cluster !== '') {
        $out .= '<div class="lvk-cta-cluster">' . $cluster . livento_cc_trust_row_html($o) . '</div>';
    }

    // Zielgruppe (Array + Freitext)
    if (!empty($o['audience']) && is_array($o['audience'])) {
        $labels = array_map(function ($a) use ($aud_labels) {
            return isset($aud_labels[$a]) ? $aud_labels[$a] : $a;
        }, $o['audience']);
        $out .= '<p class="lvk-audience"><strong>Für:</strong> ' . esc_html(implode(' · ', $labels)) . '</p>';
    }
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

    $out = '<div class="lvk-section"><h2>Aufbau & Module</h2>';
    foreach ($modules as $m) {
        $title = isset($m['title']) ? $m['title'] : 'Modul';
        $umfang = '';
        if (!empty($m['total_hours'])) {
            $unit = (isset($m['hours_unit']) && $m['hours_unit'] === 'unterrichtsstunden') ? 'UE' : 'Std.';
            $umfang = ' <span class="lvk-mod-hours">(' . esc_html($m['total_hours'] . ' ' . $unit) . ')</span>';
        }
        $out .= '<details class="lvk-module"><summary>' . esc_html($title) . $umfang . '</summary>';
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

    $tab  = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'overview';
    $tabs = array('overview' => 'Übersicht', 'anleitung' => 'Anleitung', 'shortcodes' => 'Shortcodes', 'slugs' => 'Filter & Slugs', 'berater' => 'Berater', 'foerderung' => 'Förderprogramme', 'settings' => 'Einstellungen');

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

    // 7) Cache / Sitemap / Updates
    echo '<details class="lvk-help"><summary>7 · Cache, Sitemap &amp; Updates</summary><div class="in">';
    echo '<ul>';
    echo '<li><strong>Cache leeren:</strong> <a href="' . $tab('overview') . '">Übersicht</a> → „Cache jetzt leeren".</li>';
    echo '<li><strong>Auto-Purge:</strong> <a href="' . $tab('settings') . '">Einstellungen</a> → Purge-Secret setzen + in Campus Connect hinterlegen.</li>';
    echo '<li><strong>Sitemap:</strong> <a href="' . esc_url(home_url('/livento-kurse.xml')) . '" target="_blank" rel="noopener">/livento-kurse.xml</a> (enthält Kurse + Förderungen, hängt im Rank-Math-Index).</li>';
    echo '<li><strong>Updates:</strong> Dashboard → Aktualisierungen → Erneut prüfen → Aktualisieren.</li>';
    echo '</ul></div></details>';

    // 8) Problembehebung
    echo '<details class="lvk-help"><summary>8 · Problembehebung</summary><div class="in"><ul>';
    echo '<li><strong>„anon-Key ❌" / keine Kurse:</strong> Key in den Einstellungen prüfen, Cache leeren.</li>';
    echo '<li><strong>Detailseite 404:</strong> <a href="' . $perma . '">Permalinks speichern</a>.</li>';
    echo '<li><strong>Förderberater-Ergebnis leer:</strong> in den Programmen „passt zu …" ankreuzen (gleiche Schlüssel wie im Schema).</li>';
    echo '<li><strong>Förderberater ohne Formular-Schritt:</strong> Embed in den Einstellungen hinterlegen (Förder- oder Kursberater).</li>';
    echo '<li><strong>Neuer Kurs fehlt:</strong> Cache leeren oder Webhook einrichten.</li>';
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

/* ============================================================
 * 11. Auto-Update via GitHub (Plugin Update Checker)
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
        submit().then(function(){ sent=true; nextBtn.click(); }).catch(function(){});
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
