<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/public/founders-email-template.php';

function test(bool $condition, string $label): void {
    if (!$condition) {
        fwrite(STDERR, "FEHLER: ".$label.PHP_EOL);
        exit(1);
    }
}
function has(string $text, string $needle): bool {
    return str_contains($text, $needle);
}

$old = tennilo_email_inner('Bestehender Betreff', "Hallo,\n\nAltbestand", null, null, 'founders');
test(has($old, 'Bestehender Betreff'), 'Bestehende Aufrufe ohne Extra-Felder');
test(has($old, 'Von der Founders-Warteliste abmelden'), 'Bestehender Founders-Abmeldelink');
test(has($old, 'Altbestand'), 'Alter Nachrichtentext');

$options = [
    'headline' => 'Neue Funktion <test> & Vorteile',
    'preheader' => 'Neuigkeiten "von" TENNILO & Vereinsturnieren',
    'layout' => 'spotlight',
    'button_label' => 'Jetzt anschauen <hier>',
    'button_url' => 'https://tennilo.de/#entwicklung'
];
$html = tennilo_email_html('Newsletter: Hallo', "Hallo,\n\nHier ist ein neuer Stand.", 'https://tennilo.de/newsletter.php?action=manage', null, 'newsletter', $options);
test(has($html, 'Neue Funktion &lt;test&gt; &amp; Vorteile'), 'Überschrift HTML-escaped');
test(!has($html, '<test>'), 'Keine ungefilterten HTML-Tags');
test(has($html, 'Neuigkeiten &quot;von&quot; TENNILO &amp; Vereinsturnieren'), 'Preheader HTML-escaped');
test(has($html, 'display:none!important'), 'Preheader technisch im E-Mail-Beginn');
test(has($html, 'Jetzt anschauen &lt;hier&gt;'), 'Button-Beschriftung escaped');
test(has($html, 'href="https://tennilo.de/#entwicklung"'), 'HTTPS-Button vorhanden');
test(has($html, 'background:#ef7146'), 'Highlight-Akzent im Layout');
test(has($html, 'Newsletter abbestellen'), 'Newsletter-Abmeldelink erhalten');
test(has($html, 'tennilo.de/impressum.html'), 'Impressum erhalten');
test(has($html, 'tennilo.de/datenschutz.html'), 'Datenschutz erhalten');

$compact = tennilo_email_inner('Kompakt', 'Text', null, null, 'beta', ['layout' => 'compact']);
test(has($compact, 'background:#e7ff6a'), 'Kompakt-Layout');
test(has($compact, 'TENNILO BETATEST'), 'Themenlabel Betatest');

foreach ([
    'http://tennilo.de/', 'javascript:alert(1)', 'https://user:pass@tennilo.de/',
    'https://tennilo.de:8080', 'https://tennilo.de/'."\r\n".'BCC:evil@x.de',
    'https://tennilo.de.evil.test@other.example/', 'https://localhost/'
] as $unsafe) {
    test(!tennilo_email_button_url($unsafe), 'Unerwünschter Link abgelehnt: '.json_encode($unsafe));
}
test(tennilo_email_button_url('https://tennilo.de/'), 'TENNILO HTTPS-URL erlaubt');

$badButton = tennilo_email_inner('Betreff', 'Text', null, null, 'newsletter', [
    'button_label' => 'Klick', 'button_url' => 'javascript:alert(1)'
]);
test(!has($badButton, 'javascript:alert(1)'), 'Unsichere Button-URL wird nicht gerendert');
test(!has($badButton, '>Klick</a>'), 'Kein Button mit unsicherem Link');
echo "TENNILO Mail-Layouts: alle Regressionstests erfolgreich.\n";
