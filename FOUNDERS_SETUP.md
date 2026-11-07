# TENNILO Founders – Browser-Einrichtung (06.11.2026)

Die Codebasis ist in GitHub vorbereitet, aber der Live-Betrieb **nicht verifiziert**. Die Website wird nur über den manuellen FTPS-Workflow aktualisiert.

## Einmalige Einrichtung – ohne manuelles Bearbeiten von PHP

**Bereits erledigt laut Projektverlauf:**
- ALL-INKL-Postfach `founders@tennilo.de` angelegt
- Verzeichnis `tennilo-founders-data/` **neben** `tennilo-website/` angelegt
- `tennilo-founders-config.php` aus `founders-config.example.php` **neben** `tennilo-website/` hochgeladen (noch mit `enabled => false`)

**Schritte:**
1. Den vorhandenen [manuellen Website-Workflow](https://github.com/tennis-schiri/tennilo-website/actions/workflows/deploy.yml) auf `main` starten. Er prüft PHP-Syntax und lädt mithilfe der einheitlichen `deploy-files.txt`-Freigabeliste **auch alle PHP-Dateien tatsächlich** hoch. Der frühere Workflow hatte die PHP-Dateien nur geprüft, aber nicht übertragen. **Wichtig:** Nach diesem Upload ist das Formular sichtbar, aber ohne abgeschlossene Installation nicht benutzbar (503). Deshalb Installation unmittelbar abschließen, bevor der Link beworben wird.
2. **Zuerst** `https://tennilo.de/founders-check.php` öffnen und prüfen, ob der PHP-Servercheck geladen wird. Falls nicht, deutet das auf ein PHP-/Hostingproblem. Für die Einrichtung brauchen wir PHP ≥8.1 und PDO SQLite. Der Servercheck zeigt nur technische Ja/Nein-Werte, keine Geheimnisse. Danach `https://tennilo.de/founders-setup.php` öffnen.
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
- [PHP-Servercheck](https://tennilo.de/founders-check.php) – nach Upload
- [Einmaliger Browser-Installer](https://tennilo.de/founders-setup.php) – erst nach Upload
- [Admin-Dashboard](https://tennilo.de/founders-admin.php) – erst nach Einrichtung
- [Roadmap-Update – direkter Workflow](https://github.com/tennis-schiri/tennis-schiri-app/actions/workflows/roadmap-sync.yml)

## Fehleranalyse vom 07.11.2026

Der bis dahin verwendete FTPS-Workflow enthielt einen Fehler: Die PHP-Installerdateien standen in den Prüflisten, **nicht aber in der eigentlichen FTPS-Upload-Schleife**. Der Workflow wurde durch eine einzige `deploy-files.txt`-Freigabeliste korrigiert, die sowohl für Vorprüfung als auch tatsächlichen Upload gilt. Ein minimaler Servercheck `founders-check.php` wurde ergänzt. Die genaue Ursache des beobachteten HTTP 500 ist damit **noch nicht bestätigt**; nach einem erneuten manuellen Upload zuerst den Check aufrufen.

Der Servercheck ist nur zur Fehlersuche vorgesehen und sollte nach erfolgreicher Inbetriebnahme entfernt werden.

## Nach erfolgreicher Einrichtung – Warteliste testen (07.11.2026)

Der Betreiber meldet, dass die Einrichtungs-E-Mail, der Bestätigungslink und die Vergabe des Admin-Passworts erfolgreich funktioniert haben. Ein abschließender Test einer **normalen** Wartelistenanmeldung ist noch offen.

**Testfolge:** 
1. [Website](https://tennilo.de/#founders) öffnen und mit einer eigenen E-Mail-Adresse anmelden.
2. Bestätigungs-E-Mail öffnen; Link benutzen.
3. [Admin](https://tennilo.de/founders-admin.php) aufrufen, erfolgreich bestätigten Eintrag prüfen.
4. [Online-Abmeldung](https://tennilo.de/founders.php?action=unsubscribe-request) mit derselben E-Mail-Adresse anfordern; Löschlink bestätigen. Prüfen, dass der Eintrag aus der Admin-Ansicht verschwunden ist.

**Veröffentlichung:** Am 07.11. wurde ein wichtiger fehlender Teil ergänzt: Die frühere Abmeldung erforderte einen nicht mehr verfügbaren Original-Token. Ein neuer E-Mail-verifizierter Self-Service-Abmeldeprozess mit 48 Stunden gültigem Link wurde implementiert. Die zeitweilige öffentliche Technikdiagnose `founders-check.php` gibt nach dem nächsten Deployment nur noch HTTP 404 zurück. Der neue Code muss über den manuellen Workflow aktualisiert werden, bevor Punkt 4 getestet wird.

Der bestehende Installer bleibt durch `setup_complete` und `enabled` serverseitig deaktiviert, sobald die Einrichtung vollständig abgeschlossen ist. Spätere Newsletter-Aussendungen sind weiterhin nicht implementiert; sie benötigen einen zuverlässigen Versand und individuelle Abmeldemöglichkeit.

## Founders-Benachrichtigungen und Mail-Verwaltung (07.11.2026)

**In GitHub implementiert, auf ALL-INKL noch nicht nach dem Update getestet:**

- Nach erfolgreicher Double-Opt-in-Bestätigung sendet `public/founders.php` eine **interne Benachrichtigung** an `founders@tennilo.de`. Unbestätigte Anmeldungen lösen keine Nachricht aus. Ein Fehler bei der Benachrichtigung verhindert die Anmeldung nicht.
- Admin-Zugang: [Founders-Verwaltung](https://tennilo.de/founders-admin.php). Der neue Link führt zur [Mail-Verwaltung](https://tennilo.de/founders-mail.php). Nach dem Website-Update ist ggf. eine erneute Admin-Anmeldung erforderlich, da der Sitzungscookie jetzt auch für den Mailbereich gilt.
- E-Mail-Kampagnen: Entwurf anlegen → Interessengebiet wählen (Beta, Founders oder Crowdfunding) → Entwurf speichern → Testmail nur an founders@tennilo.de → Empfängerkreis mit Eingabe `SENDEN` freigeben → manuell jeweils **bis zu 5** E-Mails versenden.
- Nur bestätigte Personen, die dem konkreten Thema zugestimmt haben, kommen in die Empfängerliste. Vor jedem Versandschritt wird die aktuelle Einwilligung erneut geprüft. Kein automatischer Start, kein versehentlicher Versand durch den GitHub-Workflow.
- Jede echte Rundmail erhält einen persönlichen, HMAC-signierten Abmeldelink. Die Abmeldung erfordert zunächst eine Bestätigungsseite und anschließend eine ausdrückliche Löschung. Es wird kein Abmeldetoken im Klartext in der Datenbank gespeichert.
- Versandstatus: `pending`, `sending`, `accepted`, `failed`, `skipped`. `accepted` bedeutet **an den lokalen Mailserver übergeben**, nicht zugestellt. Keine automatische Wiederholung unsicherer/fehlgeschlagener Versuche; damit sollen Doppelversendungen vermieden werden.
- Ohne PHP `mail()` als funktionierenden lokalen Mailtransport kann weder Benachrichtigung noch Rundmail funktionieren. Das Postfachpasswort ist für diesen Versandweg nicht erforderlich.

### Tests nach dem nächsten Upload

1. [Direkten Website-Workflow](https://github.com/tennis-schiri/tennilo-website/actions/workflows/deploy.yml) ausführen; dieser prüft `php -l` für `founders-mail.php` und lädt die Datei über die zentrale Freigabeliste hoch.
2. Mit einer eigenen Testadresse erneut anmelden und bestätigen. Prüfen, ob zusätzlich die Betreiberbenachrichtigung im Postfach founders@tennilo.de ankommt. Für einen bereits bestätigten Kontakt wird bewusst keine zweite Benachrichtigung ausgelöst.
3. Neu in [Founders-Admin](https://tennilo.de/founders-admin.php) anmelden, Link zur Mail-Verwaltung öffnen und einen Entwurf anlegen.
4. `Testmail nur an founders@tennilo.de` klicken. Nachricht im Postfach ansehen. Entwurf bei Bedarf korrigieren.
5. **Noch keine Rundmail an echte Interessenten senden**, bis Betreff, Einwilligungszweck, Abmeldelink, organisatorischer Datenschutz und tatsächliche Zustellung im Hosting geprüft sind. Für den ersten vollständigen Rundmailtest nur kontrollierte Testadressen mit ausdrücklicher Einwilligung verwenden.

### Grenzen der ersten Version

- Versand durch PHP `mail()` (noch kein authentifiziertes SMTP, kein Bounce-/Beschwerdemanagement).
- Fünfer-Chargen benötigen jeweils einen manuellen Klick. Bei vielen Kontakten ist ein Transaktions-Maildienst mit Queue/Monitoring sinnvoll.
- Meldung `accepted` ist kein Nachweis der Zustellung; die Warteschlange vermeidet automatische Wiederholungen bei unklarem Zustand.
- Zusätzliche Sicherheitsprüfung und Backup-/Wiederherstellungstest vor größerer öffentlicher Nutzung nötig.

### Zusätzliche Absicherung

Der bestehende Admin-Login wurde für die Mailverwaltung erweitert: höchstens acht fehlgeschlagene Passwortversuche je pseudonymisierter IP-Adresse innerhalb von 15 Minuten; Admin-Sitzungen laufen nach acht Stunden ab. Da der Sitzungscookie nun für beide Adminseiten gilt, ist nach dem Deployment eine erneute Anmeldung nötig. Die Loginversuche werden in der privaten SQLite-Datei gespeichert und nach einer Stunde bereinigt.

## Admin-Login – Cookie-Kollision behoben (07.11.2026)

Nach dem ersten Mail-Admin-Update meldete der Nutzer einen Login ohne sichtbaren Fortschritt. Ursache vermutlich zwei gleichnamige, aber verschieden gepfadete Sitzungscookies: ursprünglich `tennilo_founders_admin` mit Pfad `/founders-admin.php`, später derselbe Name mit Pfad `/`. Beide Adminseiten nutzen nun gemeinsam `tennilo_founders_portal_v2` (Pfad `/`), wodurch alte Cookies ignoriert werden. Nach gültigem Passwort führt der Login zu `?login=ok`; falls die Sitzung dann nicht besteht, zeigt die Seite eine klare Diagnose statt eines scheinbar unveränderten Loginformulars. Noch nicht auf dem Server getestet.

Zur Inbetriebnahme erneut den [Website-Workflow](https://github.com/tennis-schiri/tennilo-website/actions/workflows/deploy.yml) ausführen. Bei acht fehlgeschlagenen Passworteingaben innerhalb von 15 Minuten kann die Anmeldung vorübergehend gesperrt sein; dann nicht wiederholt klicken.
