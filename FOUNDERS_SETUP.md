# TENNILO Founders – Browser-Einrichtung (06.11.2026)

Die Codebasis ist in GitHub vorbereitet, aber der Live-Betrieb **nicht verifiziert**. Die Website wird nur über den manuellen FTPS-Workflow aktualisiert.

## Einmalige Einrichtung – ohne manuelles Bearbeiten von PHP

**Bereits erledigt laut Projektverlauf:**
- ALL-INKL-Postfach `founders@tennilo.de` angelegt
- Verzeichnis `tennilo-founders-data/` **neben** `tennilo-website/` angelegt
- `tennilo-founders-config.php` aus `founders-config.example.php` **neben** `tennilo-website/` hochgeladen (noch mit `enabled => false`)

**Schritte:**
1. Den vorhandenen [manuellen Website-Workflow](https://github.com/tennis-schiri/tennilo-website/actions/workflows/deploy.yml) auf `main` starten. Er prüft PHP-Syntax und lädt `founders.php`, `founders-admin.php` und `founders-setup.php` samt Website hoch. **Wichtig:** Nach diesem Upload ist das Formular sichtbar, aber ohne abgeschlossene Installation nicht benutzbar (503). Deshalb Installation unmittelbar abschließen, bevor der Link beworben wird.
2. Im Browser `https://tennilo.de/founders-setup.php` öffnen.
3. Auf „Einrichtungs-E-Mail anfordern“ klicken. Das System schickt den einmaligen Bestätigungslink ausschließlich an `founders@tennilo.de`; 30 Minuten gültig. Neue Anforderung höchstens alle 15 Minuten. Der Host muss für `mail()` tatsächlich ausliefern können.
4. Link aus der E-Mail öffnen und ein neues Admin-Passwort von mindestens 16 Zeichen festlegen. **Nichts davon in GitHub oder Chat posten.**
5. Der Installer erzeugt automatisch `tennilo-founders.sqlite` im privaten Datenverzeichnis, einen zufälligen Sicherheitsschlüssel und einen Passwort-Hash. Danach ist die Einrichtungsseite gesperrt.
6. Mit einer eigenen Test-E-Mail anmelden, Bestätigungslink öffnen, Status auf `https://tennilo.de/founders-admin.php` prüfen und Abmeldung kontrollieren. Erst danach aktiv für Nutzer bewerben.

## Wichtig bei Fehlern

- Meldung **„private Datei nicht beschreibbar“**: PHP-Dateirechte/Benutzer bei ALL-INKL prüfen. Nicht pauschal `777` setzen; bestehende Config kann per FTPS mit geeigneten Rechten aktualisiert werden.
- Meldung **„Mailversand fehlgeschlagen“** oder kein E-Mail-Eingang: PHP `mail()` bzw. lokale Mailtransport-Konfiguration bei ALL-INKL prüfen; noch kein SMTP-Client integriert. Das bloße Vorhandensein des Postfachs beweist keine Zustellbarkeit.
- Meldung **„SQLite fehlt“**: PDO SQLite im Hosting prüfen.
- Falls die Konfigurationsdatei nicht per atomarem Rename ersetzt werden kann, muss die Verzeichnisberechtigung für PHP korrigiert werden; keine Secrets veröffentlichen.
- Der Installer prüft E-Mail-Postfachkontrolle, aber **keine weitere zweite Authentifizierung**. Die Einrichtungsseite darf erst dann aufgerufen werden, wenn das Postfach sicher eingerichtet ist. Nach Abschluss weist sie alle weiteren Einrichtungsversuche ab.
- Datenbank und Konfiguration müssen **außerhalb sämtlicher öffentlich erreichbarer Webroots** liegen.
- Admin-Übersicht ist ein erster interner Stand und braucht vor größerer öffentlicher Nutzung noch einen separaten Sicherheitstest.
- Spätere Rundmails sind **noch nicht implementiert**; die aktuelle Bestätigung verwendet PHP `mail()`, keinen externen Dienst. Abmeldelinks für spätere Massenmails sind erst nach sicherer Token-/Versandimplementierung nutzbar.
- Datenschutzinformationen vor Werbung und externen Anmeldungen mit tatsächlichem Hosting-/Versandverfahren abgleichen.

## Links

- [Website-Update – direkter Workflow](https://github.com/tennis-schiri/tennilo-website/actions/workflows/deploy.yml)
- [Einmaliger Browser-Installer](https://tennilo.de/founders-setup.php) – erst nach Upload
- [Admin-Dashboard](https://tennilo.de/founders-admin.php) – erst nach Einrichtung
- [Roadmap-Update – direkter Workflow](https://github.com/tennis-schiri/tennis-schiri-app/actions/workflows/roadmap-sync.yml)
