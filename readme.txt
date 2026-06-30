=== Livento Kurskatalog ===
Contributors: livento
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.26.1
License: Proprietär

Rendert den oeffentlichen Kurskatalog aus Campus Connect nativ in WordPress.

== Changelog ==

= 1.26.1 =
* FIX Kursliste: gruener Vollbreite-Balken ueber dem Widget. Ursache: manche Themes geben generischen <section>/<header>-Tags einen markenfarbenen Vollbreite-Hintergrund. Wrapper auf neutrale <div> umgestellt + defensiver CSS-Reset (background/border/padding 0) auf .lvk-kursliste.

= 1.26.0 =
* Kurslisten fuer Landingpages: neuer Shortcode [livento_kursliste] + Admin-Tab "Kurslisten". Je Google-/Meta-Ads-Kampagne eine benannte, kriterienbasierte Kursliste zusammenstellen (Zielgruppe/Thema/Format/Anerkennung + Titel-Stichwort, plus Ueberschrift/Sortierung/Spalten/CTA) und als eigenstaendiges Widget einbinden. Die Liste fuellt sich automatisch aus dem Katalog. "Betreuungskraefte" ueber die Zielgruppe, "Pflichtfortbildungen" ueber das Titel-Stichwort (kein eigenes Facet). Optionaler "Alle ansehen"-Button als Deep-Link in den gefilterten Katalog. Auch ad-hoc per Attributen nutzbar.

= 1.25.0 =
* Lead-Tracking: Kurs- und Förderberater pushen bei erfolgreichem Lead ein GTM/GA4-Event in window.dataLayer — {event:'generate_lead', lead_type:'anfrage', lead_source:'kursberater' bzw. 'foerderberater'}. Greift beim nativen Lead-Formular (Webhook konfiguriert); für GTM/GA4 muss der Container auf der Seite eingebunden sein.

= 1.24.0 =
* CRO-Faktenbox "Auf einen Blick" auf der Kurs-Einzelseite: sticky rechte Spalte (Desktop) bzw. Block direkt unter dem Intro (Mobile). Buendelt Format, Dauer/Umfang, Abschluss, naechsten Start und Kosten mit Foerder-Pruef-Link, "Jetzt anmelden" und "Kostenlose Beratung" above the fold. Ersetzt die alte Faktenliste und den oberen CTA-Cluster (keine Dublette).
* Neues Datenfeld "Abschlussbezeichnung" (certificate_title) aus Campus Connect -> Zeile "Qualifiziertes Zertifikat '...'" (+ RbP-Punkte, falls gepflegt). Umfang nutzt wieder total_hours/hours_unit (in der View re-exponiert).
* FIX "Aufbau & Module": Sektion wird nur noch ausgegeben, wenn echte Modulinhalte (Beschreibung oder Lektionen) vorhanden sind -- kein leerer Block mehr; erstes Modul standardmaessig geoeffnet.

= 1.23.0 =
* "Umfang" auf der Kurs-Einzelseite kommt jetzt aus total_hours + hours_unit (UE bei "unterrichtsstunden", sonst "Std."); duration_minutes nur noch als Fallback (ohne "ca."-Praefix).

= 1.22.0 =
* Detail-Fixes Kursseite: (1) <title> = nur "{Kursname} | Livento" (Format/Datum + 65-Zeichen-Kuerzung raus, die kappte das Startdatum mittendrin -> "· 3."). (2) og:image-Metadaten ans Kursbild angeglichen -- Rank Math gab Breite/Hoehe/Alt/Twitter-Bild weiter vom Default-Logo aus, weil nur die Bild-URL ueberschrieben war; jetzt og:image:alt/twitter:image/secure_url = Kursbild, og:image:width/height/type weggelassen (verhindert falsches Social-Cropping). (3) Body-Klasse "lvk-course-detail" auf der Kurs-Detailroute.

= 1.21.0 =
* SEO-Dedup: Kurs-Detailseiten fuettern jetzt Rank Math (canonical/description/title/robots/opengraph/json_ld) statt parallel eigene Tags -> genau EIN kurseigenes Canonical/og:url; behebt die /kurse/-Dublette ("kanonische URL = /kurse/") in der Search Console. Zusaetzlich BreadcrumbList (Start > Kurse > Kursname), Course.offers.category=USt-frei und CourseInstance.courseWorkload. Ist Rank Math nicht aktiv, gibt das Plugin die Tags wie bisher selbst aus.

= 1.20.0 =
* CRO: Kursdetailseite. Above-the-fold-CTA-Cluster ("Jetzt Platz sichern"), mobiler Sticky-CTA, Foerder-Hinweis am Preis (bei AZAV/Foerderung), kompakte Trust-Zeile, Plaetze-/Knappheitsanzeige, wiederholter CTA-Block am Seitenende. Neue Einstellung "Beratung/Rueckruf-URL" (leer = Sekundaer-Button aus).

= 1.19.0 =
* Kein eigener Plugin-Footer mehr auf den Detailseiten (Kurs + Foerdermoeglichkeit) -- die Copyright-/Impressum-/Datenschutz-Zeile war eine Dublette zum seitenweiten WordPress-Footer.

= 1.18.0 =
* SEO: Redaktionelle Meta-Description (Fallback: Beschreibung) + FAQ-Block mit schema.org/FAQPage-JSON-LD auf den Kurs-Detailseiten. Beide Felder kommen aus Campus Connect (public_offerings).
= 1.17.0 =
* FIX: Lead-Versand schlug mit 403 fehl (Session-nonce im REST-Kontext) -> kam nie bei GHL an. nonce entfernt, stattdessen Honeypot. Neuer Admin-Button "Webhook testen". Durchgaengig Du-Ansprache. Kein blaues Fokus/Active mehr auf Auswahl-Buttons.
= 1.16.0 =
* Lead-Formular: Telefonfeld + Pflicht-Einwilligung.
= 1.15.0 =
* Ein-Button-Lead-Formular -> GHL Inbound-Webhook.
