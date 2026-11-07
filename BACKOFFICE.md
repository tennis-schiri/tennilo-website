# TENNILO Backoffice 1.0

Gemeinsame, mobile-optimierte interne Verwaltungsoberfläche für **Newsletter und Founders**, angelehnt an die Aufteilung der Tennis-Schiri-Verwaltung. Getrennt von der TENNILO-Turnier-App.

## Zugang

- **URL:** https://tennilo.de/admin/
- **Login:** Bestehendes TENNILO-Founders-Adminpasswort über `/founders-admin.php`. Es wird absichtlich **kein** zweites Passwort und keine zweite Benutzer-/Abonnentendatenbank angelegt.
- **Logout:** Bestehender CSRF-geschützter Logout über `/founders-admin.php`.
- **Sicherheit:** HTTPS-Cookie mit SameSite=Strict, nur bestehende autorisierte Sitzung (max. acht Stunden), `noindex`, restriktive CSP, keine Secrets in GitHub.

## Inhalt

- **Übersicht:** Bestätigte allgemeine Newsletter-Abonnenten, Betatest-/Founders-Interessenten, unbestätigte Kontakte, Themenverteilung und jüngste Kampagnen.
- **Abonnenten:** Nach Themen getrennt gefilterte Kontaktliste (max. 100 jüngste pro Ansicht). Allgemeine Newsletter-Kontakte werden ausschließlich bei aktivem, separat dokumentiertem Newsletter-Opt-in gezählt. Für CSV-Export führt ein Link zur bisherigen geschützten Founders-Verwaltung.
- **Kampagnen:** Kampagnenliste mit Status, Empfängerkreis und Zugriff auf bestehende Entwürfe.
- **Einstellungen & Links:** Absenderadressen, Datenschutz-Information und Zugang zur Themenverwaltung.

Die bestehenden Funktionen zum Verfassen, HTML-/Text-Vorschau, Testmails, `SENDEN`-Freigabe, 5er-Versandpaketen und Abmeldelinks bleiben unverändert in `/founders-mail.php`. Von hier führt der Weg zurück zum neuen Backoffice.

## Bereits vorhandene Absender und Rechte

- Allgemeiner Newsletter: `news@tennilo.de`, eigener bestätigter Opt-in.
- Beta, Founders, Crowdfunding: `founders@tennilo.de`, jeweilige Interessen-/Einwilligungsprüfung.
- Keine automatische Übertragung von TS-Abonnenten; die Daten von TENNILO und Tennis-Schiri bleiben strikt getrennt.

## Deployment

1. `public/admin/index.php` steht in `deploy-files.txt`; der manuelle FTPS-Workflow prüft die Syntax der neuen PHP-Datei.
2. [TENNILO-Website manuell veröffentlichen](https://github.com/tennis-schiri/tennilo-website/actions/workflows/deploy.yml) → `Run workflow` auf `main`.
3. `https://tennilo.de/admin/` öffnen. Falls die bestehende Admin-Sitzung fehlt, weiter zur Founders-Anmeldung; nach Login über den neuen Link „TENNILO Backoffice“ wechseln.
4. Übersicht, Filter, Kampagnen und Links mit den bestehenden Daten prüfen. **Keinen echten Newsletter versenden**, nur um die Navigation zu testen.

## Bewusst noch nicht eingebaut

- Keine zusätzliche Nutzerverwaltung oder ein zweites Login.
- Kein automatischer Newsletter-Versand oder Newsletter-Tracking.
- Keine aufwendige E-Mail-Drag-and-Drop-Gestaltung wie bei externen Diensten.
- Kein neues Backend für die Turnier-App.
- Vor breiter Aussendung: E-Mail-Zustellbarkeit, DKIM/SPF/DMARC, Bounce-Handling und Datenbank-Backup-/Restore-Prozedur mit Hostingdaten prüfen.

**Code in GitHub ist nicht gleich Live-Betrieb.** Ein manueller Deploy und ein Hosting-Test sind zwingend.
