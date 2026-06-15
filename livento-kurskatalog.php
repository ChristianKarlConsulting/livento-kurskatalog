<?php
/**
 * Plugin Name:       Livento Kurskatalog (nativ)
 * Plugin URI:        https://campus-connect.livento-bildung.de
 * Description:        Rendert den oeffentlichen Kurskatalog aus Campus Connect serverseitig nativ in WordPress (statt iframe) — damit der Katalog auf der WordPress-Domain indexierbar wird. Holt die Daten aus der Supabase-View `public_offerings` via PostgREST, cached sie als Transient und erzeugt Karten, Detailseiten, Filter, Schema.org-JSON-LD und kanonische URLs.
 * Version:           1.8.0
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

    // <title>
    add_filter('pre_get_document_title', function () use ($offering) {
        $parts = array_filter(array(
            $offering['title'],
            !empty($offering['format']) ? livento_cc_format_label($offering['format']) : null,
            !empty($offering['start_datetime']) ? livento_cc_fmt_date($offering['start_datetime']) : null,
        ));
        return mb_substr(implode(' · ', $parts), 0, 65) . ' | Livento';
    }, 20);

    // Canonical + Meta + OG + JSON-LD
    add_action('wp_head', function () use ($offering) {
        $url   = livento_cc_detail_url($offering['slug']);
        $descr = wp_strip_all_tags($offering['public_description'] ?: ($offering['short_description'] ?: $offering['title']));
        $descr = mb_substr(trim($descr), 0, 160);
        $img   = $offering['public_image_url'] ?? '';

        echo "\n<!-- Livento Kurskatalog -->\n";
        echo '<link rel="canonical" href="' . esc_url($url) . '">' . "\n";
        echo '<meta name="description" content="' . esc_attr($descr) . '">' . "\n";
        echo '<meta name="robots" content="index,follow">' . "\n";
        echo '<meta property="og:type" content="article">' . "\n";
        echo '<meta property="og:title" content="' . esc_attr($offering['title']) . '">' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($descr) . '">' . "\n";
        echo '<meta property="og:url" content="' . esc_url($url) . '">' . "\n";
        if ($img) {
            echo '<meta property="og:image" content="' . esc_url($img) . '">' . "\n";
            echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
            echo '<meta name="twitter:image" content="' . esc_url($img) . '">' . "\n";
        }
        echo '<script type="application/ld+json">' . wp_json_encode(livento_cc_jsonld_course($offering, $url)) . '</script>' . "\n";
    }, 1);
});

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

    $instance = array('@type' => 'CourseInstance', 'courseMode' => $mode, 'offers' => $offer);
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

/* ============================================================
 * 7. Shortcode + Rendering
 * ============================================================ */

add_shortcode('livento_kurse', function ($atts) {
    $a = shortcode_atts(array(
        'limit'   => 0,                       // 0 = alle; >0 = nur die ersten N (kuratierter Block)
        'sort'    => 'next_start',            // next_start|newest|popular|rating|most_booked|price_asc|price_desc
        'filters' => '',                      // '' = auto (Filter an, wenn kein limit) | 'yes' | 'no'
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
        $body = livento_cc_render_list(livento_cc_get_offerings(), $a['sort'], $limit, $show_filters);
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

/** Frage-Texte je Filter-Dimension fuer den Kursberater. */
function livento_cc_berater_questions() {
    return array(
        'topics'       => 'Welches Thema interessiert dich?',
        'audience'     => 'Was trifft auf dich zu?',
        'format'       => 'Wie möchtest du lernen?',
        'funding'      => 'Wie möchtest du finanzieren?',
        'level'        => 'Welche Vorkenntnisse hast du?',
        'duration'     => 'Wie viel Zeit möchtest du investieren?',
        'recognition'  => 'Welcher Abschluss ist dir wichtig?',
        'type'         => 'Welche Kursart suchst du?',
        'methodology'  => 'Welche Lernform bevorzugst du?',
        'availability' => 'Wie schnell möchtest du starten?',
        'startmonth'   => 'Wann möchtest du beginnen?',
        'city'         => 'An welchem Ort?',
    );
}

/**
 * Kursberater: mehrstufiger Assistent. Sammelt pro Schritt Vorlieben (= Filter-
 * Dimensionen) und springt am Ende auf den Katalog mit vorbelegten Filtern.
 * Shortcode: [livento_kurse_berater steps="topics,audience,format,funding"]
 */
add_shortcode('livento_kurse_berater', function ($atts) {
    $a = shortcode_atts(array(
        'steps' => 'topics,audience,format,funding',
        'title' => 'Kursberater',
        'intro' => 'In wenigen Schritten zu den passenden Weiterbildungen.',
    ), $atts, 'livento_kurse_berater');

    $offerings = livento_cc_augment(livento_cc_get_offerings());
    if (empty($offerings)) {
        return '<div class="lvk"><p>Aktuell sind keine Kurse verfügbar.</p></div>';
    }

    $groups = array();
    foreach (livento_cc_filter_groups() as $g) {
        $groups[$g['dim']] = $g;
    }
    $questions = livento_cc_berater_questions();
    $want = array_filter(array_map('trim', explode(',', (string) $a['steps'])));

    $steps_html = '';
    $idx = 0;
    foreach ($want as $dim) {
        if (!isset($groups[$dim])) {
            continue;
        }
        $g = $groups[$dim];
        $counts = livento_cc_collect_facet($offerings, $g['field'], $g['arr']);
        if (empty($counts)) {
            continue;
        }
        $items = array();
        foreach ($counts as $val => $cnt) {
            $items[] = array('val' => (string) $val, 'label' => livento_cc_facet_label($g['lab'], $val));
        }
        if ($dim === 'startmonth') {
            usort($items, function ($x, $y) { return strcmp($x['val'], $y['val']); });
        } else {
            usort($items, function ($x, $y) { return strcasecmp($x['label'], $y['label']); });
        }

        $q = isset($questions[$dim]) ? $questions[$dim] : $g['title'];
        $steps_html .= '<div class="lvk-bx-step" data-dim="' . esc_attr($dim) . '"' . ($idx > 0 ? ' hidden' : '') . '>';
        $steps_html .= '<h3 class="lvk-bx-q">' . esc_html($q) . '</h3>';
        $steps_html .= '<p class="lvk-bx-hint">Mehrfachauswahl möglich – oder überspringen.</p>';
        $steps_html .= '<div class="lvk-bx-opts">';
        foreach ($items as $it) {
            $steps_html .= '<button type="button" class="lvk-bx-opt" data-value="' . esc_attr($it['val']) . '">' . esc_html($it['label']) . '</button>';
        }
        $steps_html .= '</div></div>';
        $idx++;
    }
    $total = $idx;
    if ($total === 0) {
        return '<div class="lvk"><p><a href="' . esc_url(livento_cc_list_url()) . '">Zum Kurskatalog</a></p></div>';
    }

    $out  = livento_cc_styles();
    $out .= '<div class="lvk lvk-berater" id="lvk-berater" data-base="' . esc_attr(livento_cc_list_url()) . '" data-total="' . (int) $total . '">';
    if ($a['title'] !== '') {
        $out .= '<h2 class="lvk-bx-title">' . esc_html($a['title']) . '</h2>';
    }
    if ($a['intro'] !== '') {
        $out .= '<p class="lvk-bx-intro">' . esc_html($a['intro']) . '</p>';
    }
    $out .= '<div class="lvk-bx-progress"><span>Schritt <b class="lvk-bx-cur">1</b> von ' . (int) $total . '</span><div class="lvk-bx-bar"><i></i></div></div>';
    $out .= $steps_html;
    $out .= '<div class="lvk-bx-nav">';
    $out .= '<button type="button" class="lvk-bx-back" hidden>Zurück</button>';
    $out .= '<span class="lvk-bx-spacer"></span>';
    $out .= '<button type="button" class="lvk-bx-skip">Überspringen</button>';
    $out .= '<button type="button" class="lvk-bx-next">Weiter</button>';
    $out .= '<button type="button" class="lvk-bx-finish" hidden>Passende Kurse anzeigen</button>';
    $out .= '</div>';
    $out .= '<noscript><p style="margin-top:12px"><a href="' . esc_url(livento_cc_list_url()) . '">Zum vollständigen Kurskatalog →</a></p></noscript>';
    $out .= '</div>';
    $out .= livento_cc_berater_js();
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
    if (!empty($o['duration_minutes'])) {
        $facts['Umfang'] = 'ca. ' . round($o['duration_minutes'] / LIVENTO_CC_UE_MINUTES) . ' UE';
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
        $out .= '<div class="lvk-section lvk-benefit"><h2>Ihr Nutzen</h2>' . livento_cc_richtext($o['benefit']) . '</div>';
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

    // Buchen
    if (!empty($o['wc_checkout_url'])) {
        $out .= '<p class="lvk-cta-wrap"><a class="lvk-cta" href="' . esc_url($o['wc_checkout_url']) . '" rel="nofollow">Jetzt buchen</a></p>';
    }

    // Footer
    $out .= '<footer class="lvk-footer">© ' . esc_html(wp_date('Y')) . ' ' . esc_html(LIVENTO_CC_PROVIDER) . '<br>';
    $out .= '<a href="https://livento-bildung.de/impressum">Impressum</a> · <a href="https://livento-bildung.de/datenschutz">Datenschutz</a></footer>';

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

/** JS fuer den Kursberater (Schritt-Navigation + Deep-Link zum Katalog). */
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
  var base  = root.getAttribute('data-base') || '/';
  var steps = root.querySelectorAll('.lvk-bx-step');
  var sel = {};
  var back   = root.querySelector('.lvk-bx-back'),
      next   = root.querySelector('.lvk-bx-next'),
      skip   = root.querySelector('.lvk-bx-skip'),
      finish = root.querySelector('.lvk-bx-finish'),
      curEl  = root.querySelector('.lvk-bx-cur'),
      bar    = root.querySelector('.lvk-bx-bar i');
  var cur = 0;

  Array.prototype.forEach.call(root.querySelectorAll('.lvk-bx-opt'), function(b){
    b.addEventListener('click', function(){
      var step = b.parentNode.parentNode; // .lvk-bx-opts -> .lvk-bx-step
      var dim = step.getAttribute('data-dim'), val = b.getAttribute('data-value');
      if(!sel[dim]) sel[dim] = [];
      var i = sel[dim].indexOf(val);
      if(i>-1){ sel[dim].splice(i,1); b.classList.remove('on'); }
      else { sel[dim].push(val); b.classList.add('on'); }
    });
  });

  function show(i){
    cur = i;
    Array.prototype.forEach.call(steps, function(s,idx){ s.hidden = (idx!==i); });
    if(curEl) curEl.textContent = (i+1);
    if(bar) bar.style.width = Math.round((i+1)/total*100)+'%';
    if(back) back.hidden = (i===0);
    var last = (i===total-1);
    if(next) next.hidden = last;
    if(skip) skip.hidden = last;
    if(finish) finish.hidden = !last;
  }
  if(next) next.addEventListener('click', function(){ if(cur<total-1) show(cur+1); });
  if(skip) skip.addEventListener('click', function(){ if(cur<total-1) show(cur+1); });
  if(back) back.addEventListener('click', function(){ if(cur>0) show(cur-1); });
  if(finish) finish.addEventListener('click', function(){
    var qs = [];
    Object.keys(sel).forEach(function(dim){
      if(sel[dim] && sel[dim].length){
        qs.push(encodeURIComponent(dim)+'='+sel[dim].map(encodeURIComponent).join(','));
      }
    });
    window.location.href = base + (qs.length ? ('?'+qs.join('&')) : '');
  });
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
.lvk-cta-wrap{margin:16px 0}
.lvk-footer{margin-top:48px;padding-top:16px;border-top:1px solid #ddd;color:#666;font-size:.8rem}
/* Kursberater (Assistent) */
.lvk-berater{max-width:720px;margin:0 auto;background:#fff;border:1px solid #e1ecd6;border-radius:12px;padding:22px 24px;box-shadow:0 2px 10px rgba(0,0,0,.04)}
.lvk-bx-title{color:var(--lvk-green);margin:0 0 4px}
.lvk-bx-intro{color:#555;margin:0 0 16px}
.lvk-bx-progress{margin-bottom:20px;font-size:.85rem;color:var(--lvk-lime)}
.lvk-bx-bar{height:6px;background:#e6f0e6;border-radius:99px;margin-top:6px;overflow:hidden}
.lvk-bx-bar i{display:block;height:100%;width:0;background:var(--lvk-green);transition:width .25s}
.lvk-bx-q{color:var(--lvk-green);font-size:1.25rem;margin:0 0 4px}
.lvk-bx-hint{color:#777;font-size:.85rem;margin:0 0 14px}
.lvk-bx-opts{display:flex;flex-wrap:wrap;gap:8px}
.lvk-bx-opt{background:#fff;border:1px solid #cdd9c2;color:#2b3a2b;border-radius:8px;padding:10px 16px;font-size:.95rem;cursor:pointer;line-height:1.2}
.lvk-bx-opt:hover{border-color:var(--lvk-lime)}
.lvk-bx-opt.on{background:var(--lvk-green);border-color:var(--lvk-green);color:#fff}
.lvk-bx-nav{display:flex;align-items:center;gap:10px;margin-top:22px;padding-top:16px;border-top:1px solid #eee}
.lvk-bx-spacer{flex:1}
.lvk-bx-back,.lvk-bx-skip{background:none;border:none;color:var(--lvk-lime);cursor:pointer;font-size:.9rem;text-decoration:underline;padding:6px}
.lvk button.lvk-bx-next,.lvk button.lvk-bx-finish{background:var(--lvk-green)!important;color:#fff!important;border:none;border-radius:8px;padding:11px 22px;font-weight:600;font-size:.95rem;cursor:pointer}
.lvk button.lvk-bx-next:hover,.lvk button.lvk-bx-finish:hover{background:#006644!important}
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
            'title'   => 'Kursberater',
            'desc'    => 'Mehrstufiger Assistent: sammelt Vorlieben (Thema, Zielgruppe, …) und springt am Ende auf den Katalog mit passenden Filtern.',
            'example' => '[livento_kurse_berater steps="topics,audience,format,funding"]',
            'atts'    => array(
                'steps' => 'Schritte als Dimensions-Liste (Default „topics,audience,format,funding"). Möglich: type, format, level, audience, funding, recognition, methodology, topics, duration, startmonth, availability, city.',
                'title' => 'Überschrift (Default „Kursberater")',
                'intro' => 'Einleitungstext',
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
        livento_cc_flush_cache(); // mit ggf. neuem Key sofort neu laden
        $notice = 'Einstellungen gespeichert.';
    }

    $tab  = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'overview';
    $tabs = array('overview' => 'Übersicht', 'shortcodes' => 'Shortcodes', 'slugs' => 'Filter & Slugs', 'settings' => 'Einstellungen');

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

    echo '<p style="margin-top:20px"><button class="button button-primary" name="livento_cc_save_settings" value="1">Speichern</button></p>';
    echo '</form>';
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
