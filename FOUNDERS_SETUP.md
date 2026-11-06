# TENNILO Founders – technische Inbetriebnahme (Stand: 06.11.2026)

**Implementierung in GitHub, NICHT produktiv getestet.** Die Inhalte von `public/` werden vom bestehenden manuellen Website-Workflow auf `tennilo.de` übertragen.

## Vor dem Start – Pflicht
1. Prüfen: PHP 8.1+, PDO SQLite, Datenbank-Schreibrechte, HTTPS und `mail()` mit Absender `founders@tennilo.de` auf ALL-INKL.
2. Privates Verzeichnis **außerhalb des öffentlichen Website-Verzeichnisses** anlegen. Darin Datenbankdatei und ggf. Backups speichern. Auf keinen Fall in `public/`, `/tennilo-website/` oder irgendein per HTTP erreichbares Verzeichnis legen.
3. `founders-config.example.php` als `tennilo-founders-config.php` **eine Ebene oberhalb des öffentlichen Website-Verzeichnisses** ablegen. Das ist der Pfad, den die PHP-Dateien auf dem Server suchen. Niemals ins Repo committen.
4. `database` auf eine absolute Dateipfadangabe im privaten Verzeichnis setzen, `key` mit kryptografisch sicherem zufälligem Schlüssel versehen (z. B. `bin2hex(random_bytes(32))`), `admin_password_hash` durch einen Hash aus `password_hash('langes-einmaliges-passwort', PASSWORD_DEFAULT)` ersetzen. Klartextpasswörter und Secrets niemals im Chat/GitHub teilen.
5. `enabled => false` belassen. Zuerst Mailzustellung serverseitig mit sicherer Testumgebung prüfen. Dann bewusst auf `true` stellen.
6. Vor echtem Launch Registrierung, abgelaufene Links, Bestätigung, Duplikate, Abmeldung, Admin-Login, CSV-Export, Speicherrechte, Spam-Schutz und Datenschutz auf echtem Hosting durchtesten. Prüfen, dass öffentliche Requests weder Config noch Datenbank laden können.
7. E-Mail-System zunächst mit PHP `mail()` über den lokalen Server-Mailtransport. Das neue Postfach ist keine SMTP-Konfiguration. Falls `mail()` auf Hosting scheitert oder die Zustellbarkeit schlecht ist, auf authentifiziertes SMTP mit geprüfter Bibliothek umstellen, bevor die Anmeldung öffentlich beworben wird.
8. In **jeder späteren Informationsmail** einen individuellen Abmeldelink `https://tennilo.de/founders.php?action=unsubscribe&token=<original-token>` ausgeben. Achtung: Der Original-Abmeldetoken wird aktuell nur als Hash gespeichert und nicht exportiert. Deshalb ist **noch kein Newsletter-Versandtool** implementiert; für künftige Aussendungen muss ein gesicherter Link-/Token-Erneuerungs- und Versandprozess ergänzt werden. Bis dahin nur Bestätigungs-E-Mails, keine Massenmails.
9. Prüfen, dass Rechts- und Datenschutzhinweise den tatsächlichen Betrieb und die tatsächliche Auftragsverarbeitung abbilden.

## Funktionen
- `public/index.html`: Wartelistenformular mit Themenauswahl und Einwilligung.
- `public/founders.php`: Aufnahme, temporärer Missbrauchsschutz, E-Mail-Double-Opt-in und zweistufige Abmeldung.
- `public/founders-admin.php`: passwortgeschützte Übersicht, Status und CSV-Export.
- `public/datenschutz.html`: Informationen zur Warteliste.
- Keine externen Formulardienste, keine Online-Zahlung, kein eigenständiger SMTP-Client.

## Release
Direkter manueller Workflow:
https://github.com/tennis-schiri/tennilo-website/actions/workflows/deploy.yml

**Nicht starten, bevor der private Speicher und die serverseitige Konfiguration vorbereitet sind.** Ein Deploy ohne Config zeigt bei Formularabsendung 503 statt Daten zu sammeln.

## Roadmap
https://github.com/tennis-schiri/tennis-schiri-app/actions/workflows/roadmap-sync.yml
