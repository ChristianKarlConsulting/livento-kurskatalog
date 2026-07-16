=== Livento Kurskatalog ===
Contributors: livento
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.40.0
License: Proprietär

Rendert den oeffentlichen Kurskatalog aus Campus Connect nativ in WordPress.

== Changelog ==

= 1.40.0 =
* Der Preis auf den Ticket-Seiten sagt jetzt, WORAUF er sich bezieht. Bis hier stand dort "348,00 € / Jahr" — direkt neben einem Rechner, der auf 10 Beschaeftigte voreingestellt ist, aber ohne einen Halbsatz, der die beiden Zahlen zueinander ins Verhaeltnis setzt. Ein Erstbesucher las daraus "348 € pro Kopf" und rechnete sich das PflichtTicket bis zu 15-fach zu teuer. Tatsaechlich sind 348 € der Pauschalpreis fuer bis zu 15 Personen, also 1,93 € je Person und Monat — die Seite versteckte damit ausgerechnet ihr staerkstes Argument.
* Die Faktenbox nennt die Bezugsgroesse direkt am Einstiegspreis: "ab 348,00 € / Jahr — fuer die ganze Einrichtung, bis 15 Beschaeftigte".
* Die Variantenzeile bindet den Betrag an die Kopfzahl aus dem Rechner: "Gesamtpreis fuer alle 10 Beschaeftigten · entspricht 29,00 € / Monat", darunter "das sind 2,90 € pro Person und Monat".
* Neue Tabelle "So rechnet sich das" unter dem Rechner mit der vollstaendigen Staffel (bis 15 pauschal, 16-50, 51-150, ab 151 individuell). Die Staffel stand vorher NIRGENDS auf der Seite — die einzige Erklaerung war eine zugeklappte FAQ, die selbst keine Zahlen nannte.
* Der Rechner-Hinweis sagt jetzt ausdruecklich, dass der angezeigte Preis immer der Gesamtpreis fuers ganze Team ist und nicht mit der Kopfzahl multipliziert wird.
* Alles aus den Staffeln abgeleitet, nichts hartkodiert — und das ist keine Kosmetik: Nur das PflichtTicket hat ueberhaupt eine Pauschale. KomplettTicket (99 €/Person/Jahr) und RollenTicket (49 €/Person/Jahr) rechnen ab der ERSTEN Person pro Kopf und bekommen darum "pro Person"; ein hartkodiertes "fuer die ganze Einrichtung" waere dort falsch gewesen. Die Staffeltabelle rendert nur, wo es wirklich eine Staffel gibt.
* Bei genau einer Person entfaellt die Kopfpreis-Zeile: Sie wuerde nur den Monatsbetrag darueber wiederholen und beim PflichtTicket ausgerechnet die hoechste Kopfzahl der Staffel plakatieren, obwohl es bei einer Person keinen Pauschalvorteil zu zeigen gibt.
* Die neuen Textbausteine kommen wie Netto- und Steuerhinweis fertig formatiert vom Server. Im Browser wird weiterhin kein Preis gerechnet — sonst gaebe es eine zweite Preis-Mathematik, die auseinanderlaufen kann.

= 1.39.0 =
* Das Hero-Band auf den Ticket-Detailseiten laeuft jetzt randlos ueber die ganze Seitenbreite, statt im 1200-Pixel-Container zu stecken. Der Inhalt im Band bleibt dabei exakt buendig zum Rest der Seite (geprueft von 390 bis 1920 Pixel Breite).
* Zur Einordnung, warum das hier ueberhaupt Arbeit ist: Die Vorbildseite /foerdermoeglichkeiten/ steht auf Astras Full-Width-Template, ihr Container deckelt gar nicht erst — dort ist der Hero gratis randlos. Die Ticket-Seiten stehen auf dem normalen Container (1200 Pixel) und muessen ausbrechen.
* DIE FALLE dabei: 100vw zaehlt die Scrollbar mit, 100% nicht. Auf Windows mit klassischer Scrollbar ragt das Band dadurch rund 15 Pixel ueber den Viewport und erzeugt Querscroll auf jeder Ticket-Seite. Dagegen steht html{overflow-x:clip} — bewusst clip und NICHT hidden: hidden macht das Element zum Scroll-Container und bricht damit die klebende Faktenbox und die Ankersprunge zum Preisrechner. clip kappt nur den Ueberstand und laesst beides heil. Untergrenze dafuer ist Safari 16; darunter bleibt der Querscroll, das ist bewusst in Kauf genommen.
* Verworfene Alternative: die drei Seiten auf das Full-Width-Template stellen (derselbe Mechanismus wie die Vorbildseite, ganz ohne vw und ohne globale Regel). Haette einen manuellen Handgriff je Seite gebraucht.

= 1.38.0 =
* Der Kopf der Ticket-Detailseiten traegt jetzt dieselbe Bildsprache wie die Orientierungsseiten /kurse/ und /foerdermoeglichkeiten/: ein Band mit demselben warmen Verlauf, Eyebrow "E-Learning-Ticket", Ueberschrift in Qurova und CI-Gruen, Lead in derselben Groesse. Inhaltlich fehlte hier nichts — Ueberschrift, Claim, Beschreibung, Leistungen und Faktenbox stehen seit 1.36.0 — es fehlte die Fassung: alles lag als nackter Text auf Weiss. Die weisse Faktenbox setzt sich jetzt vom Verlauf ab, statt weiss auf weiss zu stehen.
* BEWUSST ANDERS als die Orientierungsseiten: kein zentrierter, luftiger 80-Pixel-Hero und keine CTA-Buttons im Kopf. /kurse/ und /foerdermoeglichkeiten/ sind Seiten zum Stoebern, dort braucht der Besucher zuerst Einordnung. Die Ticket-Seiten sind Entscheidungsseiten — wer hier landet, kam ueber /e-learning/ und will wissen, was drin ist und was es kostet. Ein hoher Hero schoebe Preis und CTA unter die Falz; die Faktenbox haelt beides schon oben, ein zweiter CTA im Kopf waere nur eine Dublette daneben.
* Kein Full-Bleed ueber den 100vw-Trick: der bricht in Containern mit overflow und erzeugt bei sichtbarer Scrollbar Querscroll. Das Band fuellt den Theme-Container und ist mit abgerundeten Ecken abgesetzt.
* Die Reihenfolge auf schmalen Schirmen bleibt wie mit 1.36.0 eingerichtet — die Faktenbox steht vor dem Fliesstext, Titel und Claim davor.

= 1.37.0 =
* Die Danke-Seite nach dem Kauf sagte die Unwahrheit: "Sie erhalten gleich zwei E-Mails". Die zweite gab es nie — das Passwort wurde mangels Platzhalter in der Vorlage verworfen. Seit Campus Connect v3.169.0 kommt EINE Mail, die Zugangsdaten und Team-Link zusammen enthaelt; der Text sagt das jetzt auch.
* Dazu der Hinweis, dass jede angelegte Person ihre Zugangsdaten automatisch bekommt und sofort startet: Die Kurse sind E-Learnings, es gibt keine Termine und keine Wartezeit.
* HINWEIS: 1.37.0 wurde seinerzeit nicht als Release ausgeliefert — die Korrektur war also bis 1.38.0 nicht live. Beide Versionen gehen mit 1.38.0 zusammen raus.

= 1.36.0 =
* Die Ticket-Detailseiten bekommen die Struktur der Kursdetailseiten: zweispaltiger Hero mit Faktenbox "Auf einen Blick" (Zuschnitte, Kurse, Umfang, Laufzeit, "ab"-Preis), primaerer CTA "Preis fuer dein Team berechnen", "So laeuft's ab" in vier Schritten und ein Schluss-CTA nach der FAQ. Die Kennzahlen sind Spannen (z. B. "11-24 Kurse"), weil das PflichtTicket je Zuschnitt unterschiedlich gross ist — eine einzelne Zahl waere fuer die meisten Zuschnitte falsch. Auf schmalen Schirmen rutscht die Faktenbox vor den Fliesstext, Titel und Claim bleiben aber davor.
* Die Variantenbeschreibung wird auf der Ticket-Seite NICHT mehr gerendert. Es ist der WooCommerce-Produkttext ("Was du kaufst ... So geht es nach dem Kauf weiter ..."); auf einer Produktseite steht er allein und ergibt Sinn, auf der Ticket-Seite stapelten sich vier davon, zu 98 % wortgleich — rund 5.500 Zeichen Wiederholung. Denselben Inhalt erzaehlt "So laeuft's ab" jetzt einmal. Auf den Produktseiten bleibt der Text unveraendert.
* Neu: ein sichtbarer Hinweis je Variante aus dem neuen Feld course_bundles.public_note (Campus Connect v3.167.0) — z. B. welche Themen noch produziert werden. Erscheint ueber der Kursliste. BENOETIGT Campus Connect v3.167.0; aeltere Staende liefern das Feld nicht, dann bleibt der Hinweis einfach aus.
* "So laeuft's ab" schliesst eine Luecke: Diese Information stand bisher nur in den Produktplan-Texten (die das Plugin nicht rendert) und in der Willkommensmail — also erst NACH dem Kauf, waehrend genau das die Frage davor ist.

= 1.35.0 =
* Sichtbares FAQ-Accordion auf den Ticket-Detailseiten. Das FAQPage-Schema kam schon mit v1.34.0, die Fragen standen aber nirgends auf der Seite — Google verlangt ausdruecklich, dass ausgezeichnete FAQ-Inhalte sichtbar sind. Folgenlos war das nur, weil die Fragen bis Campus Connect v3.165.0 gar nicht gepflegt werden konnten; jetzt, wo sie hinterlegt sind, muss beides zusammen raus. Sichtbare Ausgabe und Schema kommen aus derselben Funktion und koennen nicht auseinanderlaufen. <details>/<summary> statt JavaScript: laeuft ohne Skript und ist auch zugeklappt fuer Crawler lesbar.
* WooCommerce-Hinweis "Kein Livento-Tarif" nennt jetzt ALLE Bedingungen. Er riet bisher nur dazu, die Variante "oeffentlich zu schalten" — die Website verlangt aber zusaetzlich, dass die Bundle-Ausgabe AKTIV ist, und das ist ein anderer Schalter in einem anderen Dialog (Kursbundles -> Variante -> Bearbeiten -> "Bundle-Ausgabe aktiv"). Wer nur die Freischaltung setzte, wurde vom Hinweis zu genau dem Schritt geschickt, den er schon gemacht hatte. Betraf real zwei fertig eingerichtete RollenTicket-Varianten.
* KORREKTUR zum Changelog von 1.34.0: Dort steht, Rank Math habe die Ticket-Seiten als Article deklariert und das Plugin raeume diesen Widerspruch weg. Das trifft nicht zu — der Befund stammte aus einer veralteten Seiten-Cache-Kopie (WP-Optimize). Ein frisches Rendering zeigt, dass Rank Math auf diesen Seiten nur eine BreadcrumbList ausgibt. Das Entfernen von Article/BlogPosting bleibt als Absicherung im Code, ist derzeit aber wirkungslos. Der echte Schema-Fehler (fixer Preis im Product statt AggregateOffer) war real und ist mit 1.34.0 behoben.

= 1.34.0 =
* SEO fuer die Ticket-Detailseiten (/e-learning/<slug>/): Diese Seiten hatten bisher WEDER Title (der Browser zeigte das rohe URL-Kuerzel "pflicht-ticket") NOCH Meta-Description, Canonical, og-Tags oder eine H1-Ueberschrift. Ursache: Die SEO-Logik des Plugins haengt am ?kurs=-Gate und hat diese Seiten nie erreicht — sie sind, anders als die Kursdetailseiten, echte WordPress-Seiten. Neu erkennt ein eigener Hook sie am Seiten-Slug (Seiten-Slug == Familien-Slug ist ohnehin Pflicht) und setzt Title, Meta-Description, Canonical und og-Tags aus public_tariffs.
* Die gepflegten "Leistungen" (highlights) erscheinen jetzt auch auf der Detailseite — bisher wurden sie nur auf der Uebersicht /e-learning/ gerendert, obwohl sie je Ticket vollstaendig hinterlegt sind. H2 ist zu H1 geworden.
* Neue Spalte meta_title in Campus Connect (Migration v3.164.0) fuer einen suchtauglichen Titel; leer bedeutet Rueckfall auf "{Name} | Livento". BENOETIGT Campus Connect v3.164.0 — aeltere Staende liefern die Spalte nicht, dann greift der Rueckfall.
* Schema korrigiert: Die Seite sendete zwei widerspruechliche JSON-LD-Bloecke — Rank Math deklarierte sie als Article, das Plugin separat als Product mit FIXEM Preis, obwohl der Preis staffelabhaengig ist. Jetzt ein Product mit AggregateOffer (lowPrice aus price_from, also aus derselben Berechnung wie der sichtbare Preis), dazu FAQPage und BreadcrumbList im Rank-Math-@graph; der Article-Knoten entfaellt auf diesen Seiten. Die letzte Brotkrume zeigt den Ticketnamen statt des rohen Slugs.
* Der Einstiegspreis ("ab X EUR / Jahr") steht jetzt sichtbar im Seitenkopf — sonst waere lowPrice im Schema ein Preis-Mismatch zur Seite.
* Zu jedem Preis wird zusaetzlich der Nettobetrag ausgewiesen (Einrichtungen kalkulieren netto, die Kasse bucht brutto). Berechnet serverseitig im REST-Format, damit es keine zweite Preis-Mathematik im Browser gibt.
* Durchgaengig Du-Form: Der Angebotsrechner siezte ("Wie viele Beschaeftigte haben Sie?"), waehrend direkt daneben geduzt wurde.

= 1.33.0 =
* Tarif-CTA-Buttons in CI-Gruen (#004D33) statt Petrol; der stoerende Hover-Farbwechsel wurde entfernt (Hover behaelt dieselbe Farbe). Standard-Basis der Tarif-Detailseiten von "selbstlernkurse" auf "e-learning" geaendert — die "Kurse & Details ansehen"-Links zeigen jetzt auf /e-learning/<slug>/ (per Shortcode-Attribut base="…" weiterhin ueberschreibbar).

= 1.32.0 =
* Warenkorb-Zaehler im Website-Header: Der Header lauscht auf ein lv:cart-Event (detail.count). Weil die Tickets ueber einen Link (?add-to-cart) und einen Seiten-Reload in den Warenkorb kommen — nicht per AJAX — feuerte das Event nie und der Zaehler blieb leer. Das Plugin sendet lv:cart jetzt beim Aufbau jeder Seite mit dem echten Warenkorbstand (und zusaetzlich bei WooCommerce-AJAX-Add/Remove). count = Anzahl Positionen im Warenkorb. Rein additiv, kein Eingriff in den Kauffluss.

= 1.31.0 =
* Tarifpreise werden als Bruttopreise inkl. MwSt ausgewiesen (einheitlich mit WooCommerce, wo der eingegebene Preis der Kundenpreis ist). Die Betraege bleiben unveraendert — nur der Steuerhinweis wechselt von "netto zzgl. USt" auf "inkl. MwSt" (Angebotsrechner, Karten, Detailseiten, Fusszeilen). USt-freie Tarife zeigen weiterhin "USt-frei". Der Warenkorbpreis (yearly_net) bleibt unveraendert.

= 1.30.0 =
* Tarife heissen jetzt Tickets: Wegen einer Namenskollision mit einem Wettbewerber wurden die Tariffamilien umbenannt — PflichtStart -> PflichtTicket, PflegeKomplett -> KomplettTicket, RollenPlus -> RollenTicket (FunktionsbereichPlus entfaellt, laeuft als RollenTicket weiter). Betrifft nur Texte/Beispiele im Plugin; Namen, Schluessel und Slugs kommen live aus Campus Connect. NACH DEM UPDATE: WordPress-Tarif-Unterseiten auf die neuen Slugs (pflicht-ticket, komplett-ticket, rollen-ticket) umstellen und im Shortcode family="pflichtticket|komplettticket|rollenticket" setzen; alte Seiten-Slugs per 301 weiterleiten.

= 1.29.1 =
* Tarif-Zuordnung kommt jetzt aus Campus Connect: Das Plugin erkennt ein Tarif-Produkt an der Produkt-ID, die dort am Bundle hinterlegt ist (course_bundles.wc_product_id). Vorher musste die Variante zusaetzlich im Produkt ausgewaehlt werden — was beim Einrichten gar nicht ging, weil das Auswahlfeld nur bereits oeffentliche Varianten kennt, eine Variante aber erst mit eingetragener Produkt-ID oeffentlich werden kann. Das Auswahlfeld bleibt als manueller Notnagel erhalten; das Produkt-Backend zeigt jetzt an, ob und als welcher Tarif ein Produkt erkannt wurde.
= 1.29.0 =
* SLK-Tarife verkaufbar: Neue Shortcodes [livento_tarife] (drei Tariffamilien-Karten + Angebotsrechner) und [livento_tarif family="..."] (Setting-Varianten mit vollstaendiger Kursliste: Kursnummer, Umfang, Module, Lektionen, Zertifikat). Daten aus der Supabase-View public_tariffs und der RPC public_bundle_courses, gecached wie der Kurskatalog.
* Staffelpreise: Der Preis richtet sich nach der Beschaeftigtenzahl (pauschal je Einrichtung, pro Nutzer mit Mindestbetrag, oder individuelles Angebot). livento_cc_calc_price ist die EINZIGE Preisberechnung - sie speist Tarifkarte, Angebotsrechner (per REST, keine Preis-Mathematik im Browser) UND den WooCommerce-Warenkorbpreis.
* WooCommerce: Produktfeld "Livento-Tarif" (_livento_bundle_id), Warenkorbmenge = Anzahl Beschaeftigte, dynamischer Preis aus der Staffel, "ab X EUR / Jahr" statt Platzhalterpreis, Firma als Pflichtfeld im Checkout, Kursliste unter der Produktbeschreibung, Hinweis auf das Team-Onboarding auf der Danke-Seite.
= 1.28.0 =
* Sicherer dynamischer WooCommerce-Thank-you-Redirect: Nur vollstaendig aus demselben angemeldeten Campus-Lernwelt-Checkout stammende Warenkoerbe werden zur erlaubten Campus-Domain zurueckgeleitet. Regulaere, gemischte, fehlgeschlagene, stornierte und erstattete Bestellungen bleiben auf der WooCommerce-Bestaetigung.

= 1.27.0 =
* Kurse-Förder-Tags selbst verwaltbar: Neuer Abschnitt "Kurse-Förder-Tags" im Tab "Förderprogramme" — eigene Tags fuers "Kurse-Förder-Tag"-Dropdown anlegen/umbenennen/entfernen (Bezeichnung + optionaler Slug). Gespeichert in der Option livento_cc_funding_tags, gemerged mit den 9 Standard-Werten. Out-of-the-box vorbelegt mit "Anpassungsqualifizierung". Hinweis: Ein eigener Tag filtert nur dann Kurse, wenn Campus Connect denselben funding-Wert kennt — sonst dient er als reines Label/Verlinkungsziel.

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
