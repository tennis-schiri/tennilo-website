# TENNILO – offizielle Website

Statische Landingpage für https://tennilo.de („Coming 2027“).

- `public/index.html`: Startseite
- `public/impressum.html`: Impressum
- `public/datenschutz.html`: Datenschutzhinweise
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
