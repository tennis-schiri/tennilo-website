# TENNILO – Allgemeiner Newsletter (Stand: 07.11.2026)

**In GitHub vorbereitet, NOCH NICHT auf ALL-INKL bestätigt oder produktiv getestet.**

## Ziel und Adressen
- **Allgemeiner TENNILO-Newsletter:** Absender und Antwortadresse `news@tennilo.de` (inklusive PHP-`mail()` Envelope-From). Das Postfach oder ein empfangsfähiger Alias muss beim Hoster eingerichtet sein; das ist noch nicht bestätigt.
- **Founders / Betatests / Crowdfunding:** Weiterhin `founders@tennilo.de`.
- Bestehendes TENNILO-Logo, Petrol/Türkis/Korall-Maildesign, HTML und Klartext. Die Empfängeradresse wird nicht in ein externes System übertragen.

## Website und Daten
- Eigene, sichtbare Anmeldesektion auf `https://tennilo.de/#newsletter`.
- Das Formular sendet an `public/newsletter.php`. Neue Anmeldungen sind erst nach Bestätigung der Double-Opt-in-Mail aktiv, unabhängig davon, ob die Person bereits Founders-Abonnent ist.
- Bestätigungen werden nur mit dem jeweils ausgewählten Thema verbunden; bestehende Founders-Einwilligungen werden niemals automatisch auf allgemeine Newsletter ausgeweitet.
- Die Daten bleiben in der bisherigen **privaten** SQLite-Datenbank `tennilo-founders.sqlite`. `newsletter_pending` enthält kurzlebige, gehashte Einmal-Tokens (14 Tage für die Newsletter-Bestätigung, 1 Stunde für die Präferenzen); `subscriber_consents` protokolliert aktive themenbezogene Einwilligungen.
- Rate-Limits auf Basis pseudonymisierter IP- und E-Mail-Kennungen. Die neue Newsletter-Anmeldung versendet einen Bestätigungslink, kein sofortiges Marketing-Mailing.
- Die Seite `https://tennilo.de/newsletter.php?action=manage` sendet einen geschützten Präferenzlink an die eigene E-Mail-Adresse. Danach können Newsletter, Betatests, Founders-Angebote und Crowdfunding einzeln aktiviert/abgewählt oder der komplette Eintrag gelöscht werden.
- Persönliche Links in allgemeinen Newsletter-Rundmails erlauben das Abmelden **nur vom allgemeinen Newsletter**; andere bestätigte Einwilligungen bleiben bestehen.
- Nach erstmalig bestätigtem allgemeinen Newsletter-Abo wird eine interne Benachrichtigung an `news@tennilo.de` ausgelöst.

## Mail-Verwaltung
- Der bestehende `https://tennilo.de/founders-mail.php`-Admin verfügt künftig über einen vierten Empfängerkreis „Allgemeiner TENNILO Newsletter“ und die Vorlage „Allgemeines Update“.
- Der Newsletter hat einen eigenen Versand-Absender `news@tennilo.de`. Founders-Mails bleiben unter `founders@tennilo.de`.
- Es werden ausschließlich bestätigte Newsletter-Kontakte mit separatem protokolliertem Opt-in angeschrieben. Die Auswahl wird vor jeder Nachricht erneut geprüft.
- Ein Newsletter-Testentwurf geht von und an `news@tennilo.de`. Der isolierte Einzeltest an eine eigene bestätigte Adresse enthält einen **echten persönlichen Abmeldelink**.
- Regulärer Versand weiterhin ausschließlich nach manueller Freigabe `SENDEN` und in Chargen mit maximal fünf E-Mails pro Klick. Keine automatische Aussendung oder Aufzeichung von Öffnungen/Klicks.

## Release und Test
1. Prüfen, dass `news@tennilo.de` bei ALL-INKL existiert oder empfangsfähig weitergeleitet wird. Dafür hier **keine Zugangsdaten** angeben.
2. [Direkter Website-Workflow](https://github.com/tennis-schiri/tennilo-website/actions/workflows/deploy.yml) → `Run workflow` auf `main`. Dieser prüft `php -l` für `public/newsletter.php` und alle Mail-Endpoints und lädt sie über die gemeinsame `deploy-files.txt` hoch.
3. Auf `https://tennilo.de/#newsletter` mit einer eigenen neuen E-Mail-Adresse anmelden, Bestätigung erhalten/öffnen, danach in `https://tennilo.de/founders-mail.php` die getrennte Anzahl der Newsletter-Abonnenten prüfen.
4. Prüfen, ob eine neue bestätigte Newsletter-Anmeldung zusätzlich eine Benachrichtigung an `news@tennilo.de` auslöst.
5. Einen Newsletter-Entwurf über die Vorlage „Allgemeines Update“ erstellen. `Testmail nur an news@tennilo.de` anklicken, HTML/Klartext und Absender prüfen.
6. „Sicherer Einzeltest“ an ausschließlich eine eigene, bestätigte Newsletter-Adresse schicken, persönlichen Abmeldelink anklicken; danach kontrollieren, dass ein eventuell bestehendes Founders-Abo **erhalten bleibt**.
7. E-Mail-Themen über `https://tennilo.de/newsletter.php?action=manage` verändern, anschließend im Admin Empfänger- und Einwilligungsfilter prüfen.
8. Echte Rundmails erst nach abgeschlossenem Betrieb-/Datenschutztest, Backup-/Restore-Prüfung und manueller Freigabe verschicken.

## Offene Betriebsfragen
- PHP `mail()` ist kein authentifizierter SMTP-Client und bestätigt nur die Annahme durch den lokalen Mailserver, nicht die erfolgreiche Zustellung.
- Die öffentliche Datenverarbeitung und die Datenschutzerklärung sollten vor größerer Bewerbung anhand der tatsächlich verwendeten Anbieter und tatsächlichen Verarbeitung überprüft werden.
- Backup/Restore für `tennilo-founders.sqlite`, Bounce-Handling, Versandlimit und mögliche Nutzung von authentifiziertem SMTP sind noch Folgeaufgaben.
- Newsletter-Inhalte bleiben redaktionell zu prüfen; unverbindliche Entwicklungsaussagen dürfen nicht als zugesagte Funktion oder Termin formuliert werden.

## Roadmap
[Direkter Roadmap-Update-Link](https://github.com/tennis-schiri/tennis-schiri-app/actions/workflows/roadmap-sync.yml).

## Fehlerbehebung: „Ungültige Anfrage“ in den E-Mail-Einstellungen (07.11.2026)

Der Nutzer konnte beim Schritt „E-Mail-Themen verwalten“ den Einstellungslink nicht anfordern. Ursache war die zu strikte Prüfung des Browser-`Origin`-Headers beim `manage-request`-POST in `public/newsletter.php`. Im GitHub-Code korrigiert: Für die **Anforderung** eines Einstellungslinks ist kein starrer `Origin`-Wert mehr erforderlich. Dieser Schritt ändert weder Einwilligungen noch Daten, sondern sendet bei einer bestätigten Adresse nur einen **einmaligen, 60 Minuten gültigen Link an genau dieses Postfach**. Bestehende IP-/Adress-Ratenbegrenzungen bleiben aktiv. Für die ursprüngliche Newsletter-Neuanmeldung bleibt die Prüfung des HTTPS-Hosts bestehen, berücksichtigt aber auch den regulären Port 443. Der Präferenzformular-POST zeigt jetzt ausdrücklich auf `/newsletter.php`.

**Nach Deployment erneut prüfen:** `https://tennilo.de/newsletter.php?action=manage` öffnen, eigene bestätigte E-Mail eintragen, Link anfordern, Empfang kontrollieren und über den Link getrennte Themenwahl speichern. Vor dem erneuten Upload ist der Fehler auf der Live-Seite erwartbar weiterhin vorhanden.
