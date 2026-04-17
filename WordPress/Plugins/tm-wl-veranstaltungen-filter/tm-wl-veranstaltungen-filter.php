


Teammitglieder einladen
Folgendes Problem: Ich habe auf meiner WordPress Seite Veranstaltungen eingespielt und diese auch veröffentlicht, trotzdem wird mir auf meiner Website nur der Platzhalter angezeigt, woran kann das liegen?

Das liegt meistens nicht daran, dass die Veranstaltungen nicht veröffentlicht sind, sondern daran, dass sie an einer anderen Stelle „hängen bleiben“. Die häufigsten Ursachen sind:

1. Die Events sind zwar veröffentlicht, aber werden vom Block/Widget gefiltert
Oft zeigt die Website nur dann echte Einträge an, wenn sie zu den eingestellten Filtern passen, zum Beispiel:

falsche Kategorie

falscher Veranstaltungsort

falscher Veranstaltungstyp

nur „kommende Veranstaltungen“, aber das Datum ist in der Vergangenheit

falsche Sprache oder falsche Website-Ansicht bei Mehrsprachigkeit

Prüfe also die Einstellungen des Bereichs, in dem der Platzhalter erscheint.

2. Datum oder Uhrzeit sind fehlerhaft
Wenn ein Event als bereits abgelaufen gilt, wird es oft nicht mehr angezeigt. Das passiert zum Beispiel bei:

falschem Start-/Enddatum

vertauschter Uhrzeit

falscher Zeitzone in WordPress

ganztägigen Events mit falschem Enddatum

Unter Einstellungen > Allgemein solltest du auch die Zeitzone kontrollieren.

3. Die Veranstaltungsseite zeigt nur „zukünftige“ oder „hervorgehobene“ Events
Viele Event-Plugins zeigen standardmäßig nur bestimmte Einträge an. Wenn dein Event nicht als „featured“ markiert ist oder nicht in den Zeitraum fällt, bleibt der Platzhalter sichtbar.

4. Cache
Sehr häufig ist es einfach Cache:

WordPress-Cache Plugin

Server-Cache

CDN-Cache

Browser-Cache

Lösche einmal alles und lade die Seite danach hart neu.

5. Der Platzhalter kommt aus dem Theme oder Page Builder
Wenn du Elementor, WPBakery, Gutenberg-Query-Loop oder ein Theme-Template verwendest, kann es sein, dass:

das falsche Template eingebunden ist

das Event-Widget die falsche Quelle nutzt

der Shortcode falsch konfiguriert ist

das Template keine Treffer bekommt und deshalb den Platzhalter zeigt

6. Plugin-Konflikt
Manchmal sind Events korrekt angelegt, aber ein anderes Plugin verhindert die Ausgabe. Typisch sind Konflikte mit:

Cache-/Optimierungsplugins

Mehrsprachigkeitsplugins

SEO-/Schema-Plugins

Theme-Funktionen

eigenen Snippets in functions.php

7. Entwurf vs. veröffentlicht in anderer Sprache / anderem Beitragstyp
Gerade bei Event-Plugins gibt es oft einen eigenen Beitragstyp. Dann ist das Event zwar sichtbar im Backend, aber:

nicht im richtigen Post Type

nicht in der richtigen Sprache veröffentlicht

nicht auf der richtigen Seite eingebunden

8. Falscher Shortcode oder falsche Abfrage
Falls die Eventliste per Shortcode eingebunden ist, kann ein Parameter falsch sein. Beispielhaft problematisch sind:

Kategorie-Slug falsch

Anzahl = 0

nur featured = true

Zeitraum eingeschränkt

falscher Post Type

Schnelltest
Mach diese 5 Prüfungen direkt:

Öffne ein betroffenes Event und prüfe: Status = veröffentlicht

Prüfe: Startdatum liegt in der Zukunft bzw. im gewünschten Zeitraum

Prüfe die Seite mit der Event-Ausgabe: welcher Block/Shortcode/Widget wird dort verwendet

Cache leeren

Testweise auf Standard-Theme oder mit deaktivierten Optimierungsplugins prüfen

Besonders wahrscheinlich
Wenn nur ein Platzhalter erscheint, obwohl Events vorhanden sind, sind die häufigsten Ursachen:

falscher Filter in der Event-Ausgabe

Event-Datum außerhalb des sichtbaren Bereichs

Cache

falscher Shortcode / falsches Widget

Wenn du mir sagst, welches Event-Plugin du verwendest (z. B. The Events Calendar, Events Manager, Amelia, Modern Events Calendar) und wie du die Events auf der Seite einbindest, kann ich dir die Ursache deutlich gezielter eingrenzen.


<?php
/**
 * Plugin Name:       trolleymaker Veranstaltungen Filter (Sortierung Datum numerisch)
 * Description:       REGIONSSPEZIFISCH! Filtert und sortiert den Custom Post Type "Veranstaltungen" nach dem ACF-Feld "startzeitpunkt". Vergangene Veranstaltungen werden automatisch ausgeblendet (REST-API & Frontend).
 * Version:           1.0.0
 * Author:            trolleymaker
 * Author URI:        https://simpli-citycard.com/
 * Requires at least: 6.0
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;

Das hier stammt aus einem Plugin, welches ich jetzt desktivierte und siehe da es geht wieder.. Was passiert hier? 
}

// REST-API: Immer nach 'startzeitpunkt' sortieren und vergangene Veranstaltungen ausblenden
add_filter( 'rest_veranstaltungen_query', function( $args, $request ) {
    $yesterday_midnight = strtotime( 'yesterday midnight' );

    $args['meta_query'] = [
        [
            'key'     => 'startzeitpunkt',
            'value'   => $yesterday_midnight,
            'compare' => '>',
            'type'    => 'NUMERIC',
        ],
    ];
    $args['meta_key'] = 'startzeitpunkt';
    $args['orderby']  = 'meta_value_num';
    $args['order']    = 'ASC';

    return $args;
}, 10, 2 );

// Frontend & Archive: Vergangene Veranstaltungen ausblenden und nach Startzeitpunkt sortieren
add_action( 'pre_get_posts', 'sh_filter_veranstaltungen_everywhere' );
function sh_filter_veranstaltungen_everywhere( $query ) {
    if ( is_admin() || $query->get( 'suppress_filters' ) ) {
        return;
    }

    $post_type = $query->get( 'post_type' );

    if ( empty( $post_type ) ) {
        return;
    }

    if ( is_array( $post_type ) ) {
        $post_type = array_filter( $post_type );
        if ( count( $post_type ) !== 1 || reset( $post_type ) !== 'veranstaltungen' ) {
            return;
        }
    } else {
        if ( $post_type !== 'veranstaltungen' ) {
            return;
        }
    }

    $yesterday_midnight = strtotime( 'yesterday midnight' );

    $meta_query   = (array) $query->get( 'meta_query' );
    $meta_query[] = [
        'key'     => 'startzeitpunkt',
        'value'   => $yesterday_midnight,
        'compare' => '>',
        'type'    => 'NUMERIC',
    ];

    $query->set( 'meta_query', $meta_query );
    $query->set( 'meta_key', 'startzeitpunkt' );
    $query->set( 'orderby', 'meta_value_num' );
    $query->set( 'order', 'ASC' );
}

Ja — das Plugin greift aktiv in alle Abfragen für den Post Type veranstaltungen ein und blendet dabei Einträge aus, deren ACF-Feld startzeitpunkt aus Sicht des Filters „vergangen“ ist.

Der entscheidende Teil ist hier:

'key'     => 'startzeitpunkt',
'value'   => $yesterday_midnight,
'compare' => '>',
'type'    => 'NUMERIC',
Das bedeutet:

es wird nur gezeigt, was ein Feld startzeitpunkt hat

dieses Feld muss numerisch sein

und der Wert muss größer als gestern 00:00 Uhr sein

Zusätzlich wird alles danach sortiert:

$args['meta_key'] = 'startzeitpunkt';
$args['orderby']  = 'meta_value_num';
$args['order']    = 'ASC';
Also: früheste kommende Veranstaltung zuerst.

Was konkret passiert
Das Plugin macht 2 Dinge:

1. REST-API filtern
add_filter( 'rest_veranstaltungen_query', function( $args, $request ) {
Alle REST-Abfragen für veranstaltungen werden verändert.
Falls deine Website die Veranstaltungen per Block, JS, Headless-Element oder Theme über die REST-API lädt, bekommt sie nur noch Events zurück, deren startzeitpunkt den Filter besteht.

2. Frontend-Abfragen filtern
add_action( 'pre_get_posts', 'sh_filter_veranstaltungen_everywhere' );
Fast jede normale WordPress-Abfrage für veranstaltungen wird ebenfalls manipuliert.
Also Archivseiten, Loops, Widgets, Query-Blocks, Theme-Abfragen usw.

Warum danach nur der Platzhalter kam
Sehr wahrscheinlich trifft mindestens einer dieser Punkte zu:

A. startzeitpunkt ist nicht bei allen Veranstaltungen gesetzt
Dann fallen diese Beiträge komplett raus.

Denn das Plugin verlangt implizit:

Feld vorhanden

Feld numerisch

Wert > gestern Mitternacht

Fehlt das Feld oder ist es leer, wird der Beitrag nicht angezeigt.

B. startzeitpunkt ist kein Unix-Timestamp
Das Plugin erwartet einen numerischen Zeitstempel wie z. B. 1713436800.

Wenn dein ACF-Feld aber so gespeichert ist:

17.04.2026

2026-04-17 18:00

17.04.2026 18:00

dann passt type => NUMERIC nicht sauber dazu.
Dann wird falsch verglichen oder gar nichts gefunden.

Das ist der häufigste Grund.

C. Die Zeitlogik ist für eure Daten falsch
$yesterday_midnight = strtotime( 'yesterday midnight' );
Damit werden nur Veranstaltungen gezeigt, deren Startzeitpunkt nach gestern 00:00 liegt.

Das ist etwas ungewöhnlich. Eigentlich würde man eher mit:

today midnight

oder time()

arbeiten.

Mit yesterday midnight bleiben manche bereits vergangenen Termine eventuell noch drin, andere fallen unerwartet raus — je nachdem, wie startzeitpunkt gespeichert ist.

D. Das Plugin überschreibt bestehende Sortierung/Filter
Es setzt hart:

$query->set( 'meta_key', 'startzeitpunkt' );
$query->set( 'orderby', 'meta_value_num' );
$query->set( 'order', 'ASC' );
Wenn dein Theme, Block oder Plugin eine eigene Query hatte, wird die überschrieben.
Das kann dazu führen, dass die eigentliche Ausgabe plötzlich keine Treffer mehr hat.

E. Es greift sehr breit
Der Name der Funktion sagt es schon:

sh_filter_veranstaltungen_everywhere
Das Plugin greift überall im Frontend ein, sobald post_type = veranstaltungen ist.
Also nicht nur an einer Stelle, sondern potentiell auf jeder Event-Ausgabe.

Warum es nach dem Deaktivieren wieder ging
Weil dann diese Zusatzfilter nicht mehr aktiv waren:

keine Zwangs-meta_query mehr

keine erzwungene Sortierung über startzeitpunkt

keine Ausblendung „vergangener“ Veranstaltungen

keine Manipulation der REST-Ausgabe

Dann konnte WordPress bzw. dein Event-Listing wieder die Beiträge normal laden.

Der wahrscheinlichste eigentliche Fehler
Am wahrscheinlichsten ist:

Das Feld startzeitpunkt ist nicht als numerischer Unix-Timestamp gespeichert, das Plugin behandelt es aber genau so.

Das wäre exakt die Art Fehler, bei der:

Beiträge im Backend da sind

veröffentlicht sind

aber im Frontend plötzlich „keine Einträge“ bzw. nur ein Platzhalter erscheinen

So kannst du es prüfen
Schau dir bei einer funktionierenden Veranstaltung in ACF den Rohwert von startzeitpunkt an.
Wenn dort kein Timestamp steht, sondern ein Datumsstring, dann ist das Plugin die Ursache.

Ein korrekter numerischer Wert wäre etwa:

1776384000
Ein problematischer Wert wäre etwa:

17.04.2026
oder

2026-04-17 19:00:00
Noch ein technischer Punkt
Diese Zeile ist ebenfalls relevant:

if ( is_admin() || $query->get( 'suppress_filters' ) ) {
    return;
}
Im Backend wirkt der Filter also nicht.
Darum sahen die Veranstaltungen im Admin vermutlich normal aus, aber auf der Website nicht.

Fazit
Das Plugin sorgt dafür, dass nur zukünftige Veranstaltungen mit numerischem startzeitpunkt angezeigt werden und zwar sowohl im Frontend als auch über die REST-API.
Wenn das Feldformat nicht exakt dazu passt oder einzelne Beiträge keinen gültigen Wert haben, liefert die Abfrage keine Treffer mehr — und deine Website zeigt nur den Platzhalter.

Wenn du willst, kann ich dir den Code auch direkt in eine sichere, korrigierte Version umbauen, die sowohl mit ACF-Datumsfeldern als auch mit Timestamps sauber funktioniert.


Nein, zeig mir erstmal, wo ich in meinen Veranstaltungen den Startzeitpunkt suchen und einstellen muss.

Den Startzeitpunkt findest du sehr wahrscheinlich direkt in der einzelnen Veranstaltung als ACF-Feld im Bearbeitungsbildschirm.

So gehst du vor:

In WordPress nachsehen
Öffne im Backend Veranstaltungen.

Klicke eine betroffene Veranstaltung auf Bearbeiten.

Scrolle unter den normalen Titel-/Inhaltsbereich.

Dort solltest du ein Zusatzfeld sehen mit einem Namen wie:

Startzeitpunkt

Startdatum

Beginn

Datum

oder ein ACF-Feldblock mit mehreren Veranstaltungsdaten

Da dein Plugin explizit dieses Feld nutzt:

'key' => 'startzeitpunkt'
muss in der Veranstaltung ein Custom Field / ACF-Feld mit dem internen Feldnamen startzeitpunkt existieren.

Falls du das Feld nicht direkt siehst
Dann prüfe diese Stellen:

ACF-Feldgruppe:
Im Backend unter Custom Fields / Feldgruppen nach einer Feldgruppe für Veranstaltungen suchen.

In dieser Feldgruppe nach einem Feld mit:

Feldlabel: z. B. „Startzeitpunkt“

Feldname: startzeitpunkt

Wichtig: Das, was im Plugin steht, ist der Feldname, nicht zwingend die sichtbare Beschriftung.

Wenn du ACF geöffnet hast
Achte dort auf den Feldtyp. Relevant ist:

Date Picker

Date Time Picker

Text

Number

Wenn das Plugin numerisch sortiert, ist interessant, wie der Rückgabewert oder Speicherwert konfiguriert ist.

Wenn du die Felder in der Veranstaltung nicht siehst
Dann kann es sein, dass:

die Feldgruppe nicht dem Post Type Veranstaltungen zugewiesen ist

das Feld in einem Tab/Accordion versteckt ist

ein Screen-Optionen-Problem vorliegt

ACF zwar installiert ist, aber die Feldgruppe nicht aktiv ist

Schnellster Weg
Suche im Backend nach:

Custom Fields → Feldgruppen → nach startzeitpunkt suchen

Wenn du magst, kann ich dir im nächsten Schritt ganz konkret sagen, woran du erkennst, ob startzeitpunkt falsch gespeichert ist.


Hochgeladenes Bild

Genau dort bist du schon richtig.

Auf deinem Screenshot sieht man das Feld *„Startzeitpunkt “ direkt in der Veranstaltung. Der eingetragene Wert ist:

25.04.2026 19:00:00

Das heißt:

der Startzeitpunkt wird in der Veranstaltung selbst gepflegt

offenbar über ein ACF-Datums-/Zeitfeld

und zwar sichtbar ganz oben in diesem Eingabefeld

Wichtig ist jetzt der Unterschied zwischen:

sichtbarer Eingabe im Backend und

gespeichertem Rohwert in der Datenbank

Dein Plugin erwartet nämlich sehr wahrscheinlich einen numerischen Timestamp, also etwa so etwas wie:

1777143600
Dein Feld zeigt aber ein normales Datum mit Uhrzeit an:

25.04.2026 19:00:00
Das ist sehr wahrscheinlich der Grund, warum das Plugin die Veranstaltung herausfiltert.

Wo du es einstellen kannst
Wenn du nur den Startzeitpunkt ändern willst:

Veranstaltungen im Backend öffnen

Veranstaltung bearbeiten

Im Feld „Startzeitpunkt“ Datum und Uhrzeit setzen

Aktualisieren

Was dein Screenshot schon zeigt
Das Feld ist vorhanden und befüllt.
Das Problem ist daher nicht, dass kein Startzeitpunkt eingetragen ist.

Das Problem ist sehr wahrscheinlich:
Das Plugin vergleicht das Feld als Zahl, obwohl ACF hier ein Datumsformat speichert oder zurückgibt.

Als Nächstes prüfen
Du solltest jetzt in ACF / Feldgruppen nachsehen:

Custom Fields / Feldgruppen

die Feldgruppe für Veranstaltungen öffnen

das Feld Startzeitpunkt anklicken

prüfen:

Feldname = startzeitpunkt

Feldtyp = Date Time Picker?

Return Format bzw. Datumsformat

Entscheidend ist vor allem, wie dieses Feld intern gespeichert oder zurückgegeben wird.

Wenn du magst, gehen wir als Nächstes genau zusammen in die ACF-Feldgruppe, und ich sage dir dann sofort, welche Einstellung dort falsch ist.


Hochgeladenes Bild

Perfekt, jetzt sieht man die Ursache ziemlich klar:

Was hier eingestellt ist
Beim Feld startzeitpunkt ist rechts das Rückgabeformat auf

Y-m-d\TH:i:s
gesetzt.

Und links das Darstellungsformat ist

d.m.Y H:i:s
Das ist nur die Anzeige im Backend.

Der wichtige Punkt
Dein Plugin macht diesen Vergleich:

'type' => 'NUMERIC'
und sortiert mit:

'orderby' => 'meta_value_num'
Das Plugin erwartet also eine Zahl.

Dein Feld ist aber ein Datums-/Zeitfeld. Aus den ACF-Einstellungen sieht man: Es arbeitet mit einem Datumsformat, nicht mit einem Unix-Timestamp.

Das heißt praktisch:

in der Veranstaltung pflegst du das Feld richtig

aber das Plugin behandelt diesen Wert falsch, nämlich als Zahl

Wo du den Startzeitpunkt einstellst
Genau hier:

Veranstaltungen

Veranstaltung öffnen

Feld Startzeitpunkt

Datum/Uhrzeit eintragen

speichern

Wo du die Feldlogik dazu findest
Genau in der ACF-Feldgruppe, die du gerade offen hast:

Feldname: startzeitpunkt

Feldtyp: Datums- und Zeitauswahl

Darstellungsformat: nur für den Editor

Rückgabeformat: für Template-/PHP-Ausgabe

Wichtig für dein Problem
Das Rückgabeformat löst dein Plugin-Problem nicht direkt.
Denn die meta_query im Plugin arbeitet auf dem gespeicherten Feldwert in der Datenbank, nicht einfach auf der hübschen Anzeige im Backend.

Der Fehler sitzt also sehr wahrscheinlich im Plugin-Code, nicht in der Veranstaltungspflege.

Fazit
Du musst den Startzeitpunkt in der Veranstaltung selbst pflegen — das machst du bereits an der richtigen Stelle.
Dass die Events mit aktivem Plugin verschwinden, liegt sehr wahrscheinlich daran, dass das Plugin den Wert von startzeitpunkt als numerischen Timestamp behandelt, obwohl das ACF-Feld als Datums-/Zeitwert geführt wird.

Im nächsten Schritt kann ich dir genau zeigen, welche Zeilen im Plugin falsch sind und wie sie korrekt aussehen müssten.


Ja, mach mal

Ja — die problematischen Stellen sind diese hier:

'type' => 'NUMERIC'
und

'orderby' => 'meta_value_num'
Warum das falsch ist
Dein ACF-Feld startzeitpunkt ist ein Date-Time-Picker.
Auf dem Screenshot sieht man als Rückgabeformat:

Y-m-d\TH:i:s
also zum Beispiel:

2026-04-25T19:00:00
Das ist keine Zahl, sondern ein Datumsstring.

Das Plugin behandelt den Wert aber so, als wäre er ein Unix-Timestamp wie:

1777143600
Dadurch schlägt der Vergleich fehl oder liefert unzuverlässige Ergebnisse. Genau deshalb verschwinden die Veranstaltungen.

Die falschen Stellen im Plugin
1. REST-Filter
Hier wird falsch numerisch verglichen:

$yesterday_midnight = strtotime( 'yesterday midnight' );

$args['meta_query'] = [
    [
        'key'     => 'startzeitpunkt',
        'value'   => $yesterday_midnight,
        'compare' => '>',
        'type'    => 'NUMERIC',
    ],
];
$args['meta_key'] = 'startzeitpunkt';
$args['orderby']  = 'meta_value_num';
Problem:

$yesterday_midnight ist Timestamp

startzeitpunkt ist Datumsstring

NUMERIC + meta_value_num passt nicht zum Feld

2. Frontend-Filter
Hier genau derselbe Fehler:

$meta_query[] = [
    'key'     => 'startzeitpunkt',
    'value'   => $yesterday_midnight,
    'compare' => '>',
    'type'    => 'NUMERIC',
];

$query->set( 'meta_key', 'startzeitpunkt' );
$query->set( 'orderby', 'meta_value_num' );
Wie es korrekt aussehen müsste
Da dein Feld als Datumsstring gespeichert bzw. verwendet wird, solltest du auch mit einem Datumsstring vergleichen und nicht mit einer Zahl.

Da dein Rückgabeformat Y-m-d\TH:i:s ist, kannst du es so anpassen:

Korrigierte Version
<?php
/**
 * Plugin Name:       trolleymaker Veranstaltungen Filter (Sortierung Datum korrekt)
 * Description:       Filtert und sortiert den Custom Post Type "veranstaltungen" nach dem ACF-Feld "startzeitpunkt". Vergangene Veranstaltungen werden ausgeblendet.
 * Version:           1.0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// REST-API: nach startzeitpunkt sortieren und vergangene Veranstaltungen ausblenden
add_filter( 'rest_veranstaltungen_query', function( $args, $request ) {
    $now = current_time( 'Y-m-d\TH:i:s' );

    $args['meta_query'] = [
        [
            'key'     => 'startzeitpunkt',
            'value'   => $now,
            'compare' => '>=',
            'type'    => 'CHAR',
        ],
    ];

    $args['meta_key'] = 'startzeitpunkt';
    $args['orderby']  = 'meta_value';
    $args['order']    = 'ASC';

    return $args;
}, 10, 2 );

// Frontend & Archive: vergangene Veranstaltungen ausblenden und sortieren
add_action( 'pre_get_posts', 'sh_filter_veranstaltungen_everywhere' );
function sh_filter_veranstaltungen_everywhere( $query ) {
 if ( is_admin() || $query->get( 'suppress_filters' ) ) {
    return;
}

    $post_type = $query->get( 'post_type' );

    if ( empty( $post_type ) ) {
        return;
    }

    if ( is_array( $post_type ) ) {
        $post_type = array_filter( $post_type );
        if ( count( $post_type ) !== 1 || reset( $post_type ) !== 'veranstaltungen' ) {
            return;
        }
    } else {
        if ( $post_type !== 'veranstaltungen' ) {
            return;
        }
    }

    $now = current_time( 'Y-m-d\TH:i:s' );

    $meta_query   = (array) $query->get( 'meta_query' );
    $meta_query[] = [
        'key'     => 'startzeitpunkt',
        'value'   => $now,
        'compare' => '>=',
        'type'    => 'CHAR',
    ];

    $query->set( 'meta_query', $meta_query );
    $query->set( 'meta_key', 'startzeitpunkt' );
    $query->set( 'orderby', 'meta_value' );
    $query->set( 'order', 'ASC' );
}
Noch wichtiger: ein möglicher Haken
Bei ACF ist das Rückgabeformat nicht immer gleich dem gespeicherten Datenbankwert.

Sehr oft speichert ACF DateTime intern als:

2026-04-25 19:00:00
also ohne T.

Dann müsste der Vergleich stattdessen so aussehen:

$now = current_time( 'mysql' );
also:

2026-04-25 19:00:00
und nicht:

current_time( 'Y-m-d\TH:i:s' )
Deshalb die sicherste Vermutung
Da dein Backend-Feld ein ACF-DateTime-Feld ist, ist die sehr wahrscheinlich richtige Version sogar diese:

$now = current_time( 'mysql' );
und dann:

'type'    => 'DATETIME',
'orderby' => 'meta_value',
Noch bessere korrigierte Variante
add_filter( 'rest_veranstaltungen_query', function( $args, $request ) {
    $now = current_time( 'mysql' );

    $args['meta_query'] = [
        [
            'key'     => 'startzeitpunkt',
            'value'   => $now,
            'compare' => '>=',
            'type'    => 'DATETIME',
        ],
    ];

    $args['meta_key'] = 'startzeitpunkt';
    $args['orderby']  = 'meta_value';
    $args['order']    = 'ASC';

    return $args;
}, 10, 2 );
und im zweiten Teil genauso:

$now = current_time( 'mysql' );

$meta_query[] = [
    'key'     => 'startzeitpunkt',
    'value'   => $now,
    'compare' => '>=',
    'type'    => 'DATETIME',
];

$query->set( 'meta_key', 'startzeitpunkt' );
$query->set( 'orderby', 'meta_value' );
$query->set( 'order', 'ASC' );
Kurz gesagt: Was genau ist falsch?
Falsch:

strtotime(...) als Vergleichswert

type => NUMERIC

orderby => meta_value_num

Wahrscheinlich richtig:

current_time( 'mysql' )

type => DATETIME

orderby => meta_value

Meine Empfehlung
Ändere im Plugin genau diese Punkte:

Ersetzen:
$yesterday_midnight = strtotime( 'yesterday midnight' );
durch:

$now = current_time( 'mysql' );
Ersetzen:
'value'   => $yesterday_midnight,
'compare' => '>',
'type'    => 'NUMERIC',
durch:

'value'   => $now,
'compare' => '>=',
'type'    => 'DATETIME',
Ersetzen:
'orderby'  => 'meta_value_num';
durch:

'orderby'  => 'meta_value';
Wenn du willst, schreibe ich dir den Plugin-Code jetzt direkt komplett sauber neu, sodass du ihn 1:1 ersetzen kannst.


Ja, mach mal bitte

Klar — hier ist der Plugin-Code in einer bereinigten Version, die zu deinem ACF-DateTime-Feld passt.

Was ich geändert habe
kein numerischer Vergleich mehr

Vergleich jetzt als DATETIME

Vergleichswert ist jetzt aktuelle WordPress-Zeit

Sortierung über meta_value statt meta_value_num

nur die Main Query im Frontend wird geändert, damit weniger Nebenwirkungen entstehen

<?php
/**
 * Plugin Name:       trolleymaker Veranstaltungen Filter (Sortierung Datum korrekt)
 * Description:       REGIONSSPEZIFISCH! Filtert und sortiert den Custom Post Type "veranstaltungen" nach dem ACF-Feld "startzeitpunkt". Vergangene Veranstaltungen werden automatisch ausgeblendet (REST-API & Frontend).
 * Version:           1.0.1
 * Author:            trolleymaker
 * Author URI:        https://simpli-citycard.com/
 * Requires at least: 6.0
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Aktuelle WordPress-Zeit im MySQL-Datetime-Format.
 * Beispiel: 2026-04-25 19:00:00
 */
function tm_get_current_datetime_for_events() {
    return current_time( 'mysql' );
}

/**
 * REST-API:
 * Veranstaltungen ab jetzt anzeigen und nach Startzeitpunkt sortieren.
 */
add_filter( 'rest_veranstaltungen_query', function( $args, $request ) {
    $now = tm_get_current_datetime_for_events();

    $existing_meta_query = $args['meta_query'] ?? [];

    if ( ! is_array( $existing_meta_query ) ) {
        $existing_meta_query = [];
    }

    $existing_meta_query[] = [
        'key'     => 'startzeitpunkt',
        'value'   => $now,
        'compare' => '>=',
        'type'    => 'DATETIME',
    ];

    $args['meta_query'] = $existing_meta_query;
    $args['meta_key']   = 'startzeitpunkt';
    $args['orderby']    = 'meta_value';
    $args['order']      = 'ASC';

    return $args;
}, 10, 2 );

/**
 * Frontend:
 * Vergangene Veranstaltungen ausblenden und nach Startzeitpunkt sortieren.
 */
add_action( 'pre_get_posts', 'tm_filter_veranstaltungen_everywhere' );
function tm_filter_veranstaltungen_everywhere( $query ) {
    if ( is_admin() || ! $query->is_main_query() || $query->get( 'suppress_filters' ) ) {
        return;
    }

    $post_type = $query->get( 'post_type' );

    if ( empty( $post_type ) ) {
        return;
    }

    if ( is_array( $post_type ) ) {
        $post_type = array_filter( $post_type );

        if ( count( $post_type ) !== 1 || reset( $post_type ) !== 'veranstaltungen' ) {
            return;
        }
    } else {
        if ( $post_type !== 'veranstaltungen' ) {
            return;
        }
    }

    $now = tm_get_current_datetime_for_events();

    $meta_query = $query->get( 'meta_query' );

    if ( ! is_array( $meta_query ) ) {
        $meta_query = [];
    }

    $meta_query[] = [
        'key'     => 'startzeitpunkt',
        'value'   => $now,
        'compare' => '>=',
        'type'    => 'DATETIME',
    ];

    $query->set( 'meta_query', $meta_query );
    $query->set( 'meta_key', 'startzeitpunkt' );
    $query->set( 'orderby', 'meta_value' );
    $query->set( 'order', 'ASC' );
}
So testest du es
Altes Plugin sichern

Code ersetzen

Plugin wieder aktivieren

Eine Veranstaltung mit künftigem Datum speichern

Website neu laden

Falls nötig: Cache leeren

Falls es noch nicht sofort klappt
Dann ist der wahrscheinlichste Sonderfall: ACF speichert startzeitpunkt intern nicht als YYYY-MM-DD HH:ii:ss, sondern in einem anderen Format. Dann müsste man den Vergleich noch einmal exakt an den echten DB-Wert anpassen.

Der nächste saubere Prüfschritt wäre dann:

in der Datenbank oder mit einem kurzen Debug-Snippet den tatsächlich gespeicherten Wert von startzeitpunkt anschauen

Dann kann ich dir die endgültig passende Version bauen.




