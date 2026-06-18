=== Livento Kurskatalog ===
Contributors: livento
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.21.0
License: Proprietär

Rendert den oeffentlichen Kurskatalog aus Campus Connect nativ in WordPress.

== Changelog ==

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
