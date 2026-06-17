=== Livento Kurskatalog ===
Contributors: livento
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.18.0
License: Proprietär

Rendert den oeffentlichen Kurskatalog aus Campus Connect nativ in WordPress.

== Changelog ==

= 1.18.0 =
* SEO: Redaktionelle Meta-Description (Fallback: Beschreibung) + FAQ-Block mit schema.org/FAQPage-JSON-LD auf den Kurs-Detailseiten. Beide Felder kommen aus Campus Connect (public_offerings).
= 1.17.0 =
* FIX: Lead-Versand schlug mit 403 fehl (Session-nonce im REST-Kontext) -> kam nie bei GHL an. nonce entfernt, stattdessen Honeypot. Neuer Admin-Button "Webhook testen". Durchgaengig Du-Ansprache. Kein blaues Fokus/Active mehr auf Auswahl-Buttons.
= 1.16.0 =
* Lead-Formular: Telefonfeld + Pflicht-Einwilligung.
= 1.15.0 =
* Ein-Button-Lead-Formular -> GHL Inbound-Webhook.
