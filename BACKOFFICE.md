# TENNILO Backoffice 1.0 · Mail-Editor 2.0

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

Die Funktionen zum Verfassen, HTML-Vorschau, HTML-/Klartextversand, Testmails, `SENDEN`-Freigabe, 5er-Versandpaketen und Abmeldelinks befinden sich in `/founders-mail.php`. Von dort führt ein Link zurück zum Backoffice.

## Mail-Editor 2.0 (07.11.2026)

- **Betreff:** E-Mail-Betreffzeile; bleibt von der Überschrift unabhängig.
- **Überschrift:** Große Headline innerhalb der gestalteten HTML-E-Mail. Falls leer, wird der Betreff verwendet.
- **Vorschautext (Preheader):** Verborgener Text zu Beginn der HTML-E-Mail für die Posteingang-Vorschau (E-Mail-Programme können hiervon abweichen).
- **Gestaltung:** `standard` (Petrol/Türkis), `spotlight` (Koralle/Lime) oder `compact` (reduzierte Darstellung). Alle drei Varianten enthalten das originale TENNILO-Logo, einen lesbaren Textteil, Kontakt/Impressum und den persönlichen Abmeldelink.
- **Optionaler Aktionsbutton:** Beschriftung und HTTPS-Link müssen beide angegeben sein. Links mit Benutzerinformationen, HTTP, fremden Schemas oder beliebigen Ports werden nicht akzeptiert.
- **Redaktionelle Vorlagen:** Allgemeines Update, Feature-Highlight, Betatest-Einladung, Founders-Update, Crowdfunding-Info; Inhalte sind **Vorlagen**, keine Aussagen über tatsächlich freigegebene Features.
- **Datenhaltung:** Vorhandene SQLite-Kampagnen werden beim ersten Admin-Aufruf nicht-destruktiv um die fünf neuen Textspalten erweitert. Alte Entwürfe und zuvor freigegebene Kampagnen bleiben erhalten; beim Duplizieren werden auch Layout/Überschrift/Preheader/Button übernommen.
- **Versand:** Die HTML-Ausgabe enthält die Gestaltung, die Textfassung enthält Überschrift und (falls vorhanden) Aktionslink. Betreff, Einwilligungsprüfung, Absender und manuelle Freigabe bleiben bestehen.

**Test vor echtem Versand:** Bestehende Datenbank sicher sichern, neuen Entwurf aus einer Vorlage erstellen, Überschrift und Preheader anpassen, Button-URL prüfen und speichern; dann HTML-Vorschau sowie Testmail im eigenen Postfach kontrollieren. Eine Rundmail nicht als Funktionstest an reale Interessenten senden.

Die CI prüft PHP-Syntax sowie die E-Mail-Layouts einschließlich HTML-Escaping, Linkprüfung und Abmeldelinks. Ein zusätzlicher isolierter Integrationstest startet den echten Editor mit einer älteren SQLite-Kampagne und prüft Migration, Speichern, ungültige URLs und Duplizieren. Erfolgreicher Prüflauf: https://github.com/tennis-schiri/tennilo-website/actions/runs/42380059622.

**Status:** Änderungen nur im GitHub-Quellcode bestätigt. ALL-INKL-Live-Test nach manuellem FTPS-Deployment steht noch aus.

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
