# TENNILO – offizielle Website

Statische Landingpage für https://tennilo.de („Coming 2027“).

- `public/index.html`: Startseite
- `public/impressum.html`: Impressum
- `public/datenschutz.html`: Datenschutzhinweise
- `public/admin/index.php`: gemeinsames TENNILO Backoffice (Newsletter, Founders und Kampagnen)
- `public/assets/`: Styles und Original-TENNILO-Markenbilder
- `.github/workflows/deploy.yml`: manuelles FTPS-Deployment

## Veröffentlichung

Die Landingpage wird unabhängig von der passwortgeschützten Test-App `app.tennis-schiri.de` veröffentlicht.

Bei ALL-INKL ist `tennilo.de` mit dem Webspace-Verzeichnis `/tennilo-website/` verknüpft. Für das Deployment einen **eigenen FTP-Zugang mit Startverzeichnis `/tennilo-website/`** anlegen. Erforderliche GitHub-Actions-Secrets in **diesem** Repository:

- `FTP_HOST`
- `FTP_USER`
- `FTP_PASSWORD`

Danach unter **Actions → TENNILO Website manuell veröffentlichen → Run workflow** auslösen. Das Deployment lädt ausschließlich die freigegebenen Dateien in das FTP-Startverzeichnis hoch und löscht keine Serverdateien.

**Wichtig vor Livegang:** Impressumsdaten/Betreiberidentität und die Datenschutzerklärung inklusive tatsächlicher Server-Logaufbewahrung prüfen. Die Texte sind keine anwaltlich geprüften Rechtstexte.

## Version 2.0 – Teaser und Entwicklungsnotizen (GitHub, noch nicht live)

Die Startseite ist als schlanke Vorschauseite gestaltet: Claim „Weniger Orga. Mehr Tennis.“, Coming 2027, Idee, drei Entwicklungsbereiche, Entwicklungsjournal und Instagram-Feedback. Sie ist **keine** vollständige Produktwebsite.

- Die Startseite liegt in `public/index.html`, ihr Stylesheet in `public/assets/styles.css`.
- Impressum und Datenschutz bleiben als separate Seiten erhalten und nutzen weiter dieselbe Stylesheet-Datei.
- Die Entwicklungshinweise (derzeit 05.11.2026) stammen aus `tennis-schiri/tennis-schiri-app/PROJEKTSTATUS.md` und `CHANGELOG.md`. Sie sind nicht automatisch synchronisiert.
- Für neue Einträge den HTML-Bereich `class="updates-list"` ergänzen und den sichtbaren Datumshinweis `class="update-stamp"` anpassen. Nur verifizierte Fortschritte nennen; Prototypen und Teststände klar von fertigen Kundenfunktionen trennen.
- Die skizzierte Live-Anzeige im Hero ist eine Illustration, kein echter App-Screenshot.
- Änderungen an GitHub `main` veröffentlichen nichts automatisch. Erst ein manuell ausgelöster FTPS-Workflow aktualisiert `tennilo.de`.

**Vor erstem V2-Upload:** Inhalte, rechtliche Angaben und Links nochmals kontrollieren. Die Passwortschutz-Test-App bleibt unabhängig.

## TENNILO Backoffice

Interner Einstieg: https://tennilo.de/admin/ (bestehender Founders-Admin-Login). Hintergründe, Sicherheit und Veröffentlichung: [BACKOFFICE.md](BACKOFFICE.md).

## Zweisprachige Website (DE / EN)

Die Website ist für internationale Besucher zweisprachig aufgebaut:

- `public/index.html` – deutsche Startseite unter https://tennilo.de/
- `public/en/index.html` – englische Startseite unter https://tennilo.de/en/
- `public/impressum.html` / `public/en/imprint.html` – deutsche und englische Anbieterkennzeichnung
- `public/datenschutz.html` / `public/en/privacy.html` – deutsche und englische Datenschutzhinweise
- `public/assets/score-home.css` – gemeinsames Score-Branding, inklusive responsive DE/EN-Schalter
- `public/assets/site-interactions.js` – gemeinsame Navigation und dynamischer Nach-oben-Button, Sprachlabels automatisch nach `<html lang>`
- `public/sitemap.xml` – beide Sprachen mit hreflang-Verknüpfungen
- `tests/tennilo-language-test.mjs` – prüft beide Sprachversionen, Formularschnittstellen, rechtliche Anker und Upload-Freigabeliste

Bei jeder inhaltlichen Änderung an Score, Tournament, Umpire, Meilensteinen oder News **beide Startseiten** abgleichen. Das Gleiche gilt für Änderungen an den rechtlichen Unterseiten: DE und EN aktualisieren. Die Sprachumschaltung erfolgt manuell per Links im Header, ohne Cookies oder Redirect anhand der Browsersprache.

**Formulare:** Newsletter und Founders verwenden weiterhin die bestehenden PHP-Endpunkte und die etablierten Einwilligungs-/Double-Opt-in-Prozesse. Die englischen Formularlabels sind übersetzt; transaktionale PHP-Seiten und E-Mail-Bestätigungen werden derzeit vom Backend in deutscher Sprache ausgegeben. Backend-Texte erst in einem eigenen Schritt lokalisieren und separat testen.

Der Upload erfolgt weiter nur über den manuellen Workflow: https://github.com/tennis-schiri/tennilo-website/actions/workflows/deploy.yml
