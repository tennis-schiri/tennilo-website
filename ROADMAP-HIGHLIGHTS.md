# Öffentliche Roadmap-Highlights auf tennilo.de

Stand: 07.11.2026

Der Abschnitt „Daran arbeiten wir“ auf der öffentlichen Startseite ist eine **bewusst kurze, redaktionelle Auswahl** aus der **internen** TENNILO-Roadmap im Repository `tennis-schiri/tennis-schiri-app`, Datei `site/roadmap-seed.json`, sowie aus dem eigenständigen Projekt TENNILO Score (`tennis-schiri/tennilo-score`).

| Öffentliche Karte | Interne Roadmap-IDs | Einordnung |
| --- | --- | --- |
| Turnierformate | `schleifchen`, `doppel`, `opc` | `online` bedeutet auf der **geschützten Testseite**, nicht öffentliche Produktverfügbarkeit. Deshalb „IM TEST“. |
| Live-Ansicht | `live` | `online` im Testserver; echte Browser-/Mehrgeräte-Praxistests stehen noch aus. Deshalb „IM TEST“. |
| Mehr Turnierideen | `americano`, `mixed`, `updown` | `idea`: reine Ideenphase ohne Leistungs- oder Veröffentlichungszusage. Deshalb „IDEENPHASE“. |
| TENNILO Score | Eigenständiges Repository `tennis-schiri/tennilo-score` | Eigenständige Tennis-Zähl-App mit Watch-first-Konzept. Interaktiver Prototyp für interne Entwicklung; **keine fertige native Watch-App**. Kein öffentlicher Link oder Testaufruf. Deshalb „PROTOTYP“. |

Die drei Roadmap-basierten HTML-Karten enthalten `data-roadmap-ids`, damit die Herkunft bei späteren Aktualisierungen nachvollziehbar bleibt. Die vierte Karte (TENNILO Score) verwendet `data-project="tennilo-score"`, enthält aber bewusst **keinen Link auf den internen Prototyp**. Vertrauliche Roadmap-Felder, Kommentare, interne Pläne und Verwaltungsdaten werden **nicht** auf die öffentliche Website übernommen. Es wird bewusst **kein direkter Link** auf das passwortgeschützte interne Roadmap-Board veröffentlicht.

## Abgleich bei künftigen Entwicklungsupdates

1. Vor einer Änderung den aktuellen Status der verknüpften Roadmap-Einträge in `tennis-schiri-app/site/roadmap-seed.json` und nötigenfalls den tatsächlichen GitHub-Code / Deploymentstatus prüfen.
2. Auf der öffentlichen Homepage nur kurz und allgemein formulieren. **Code fertig** / **auf Testseite** nicht mit **öffentlich veröffentlicht** gleichsetzen.
3. Bei Änderungen der Roadmap die vier öffentlichen Texte und Status-Badges redaktionell nachziehen. Neue Ideen nicht automatisch als fest zugesagte Funktionen darstellen.
4. Nach dem Commit die Website über den [manuellen TENNILO-Workflow](https://github.com/tennis-schiri/tennilo-website/actions/workflows/deploy.yml) veröffentlichen.

**Kein automatischer Live-Sync:** Die Roadmap wird auf einem geschützten App-/Repository-Bereich geführt; die öffentliche Landingpage soll nicht automatisch komplette Roadmap-Daten abrufen oder interne Inhalte versehentlich freigeben.
