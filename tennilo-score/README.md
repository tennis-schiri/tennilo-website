# TENNILO Score — Entwicklungsstand 0.10

Stand: 07.11.2026

## Gesicherter Quellcode

- `prototype/index.html` — vollständiger interaktiver HTML-Prototyp Version 0.10.
- GitHub-Branch: `tennilo-score-development`. Dieser Zweig ist absichtlich getrennt von `main`, sodass die bestehende tennilo.de-Website nicht automatisch verändert wird.

## Produktidee

Eigenständige Tennis-Zähl-App mit Watch-first-Bedienung; spätere Anbindung an TENNILO möglich.

## Funktions- und Designanforderungen

- Große Tippflächen für Punkte, deutliches Undo.
- Anzeige aller Satzstände aus der Sicht des Uhrenträgers; aktueller Satz fett, gewonnene Sätze grün, verlorene rot.
- Tiebreak-Anzeige wie `7:6 (4)`; Testeinstellung: dritter Satz als Match-Tiebreak.
- Seitenwechselhinweis, Aufschlagwechsel rechts/links, No-Ad bei 40:40.
- Doppel: Aufschläger und Returnaufstellung vor den ersten beiden Aufschlagspielen jedes Satzes wählen, direkt auf der Watch.
- Links/rechts immer aus der Perspektive des Uhrenträgers mit Blick aufs Netz.
- In den Zählfeldern nur aktuelle Beteiligte: Aufschläger (ich/Partner) + Ball links/rechts vom Namen; aktueller Returnspieler + Schläger links/rechts vom Namen.
- Mixed-No-Ad mit Rückschläger gleichen Geschlechts (fachliche Detailprüfung vor produktiver Freigabe nötig).

## Offen

- Vollständige interaktive Regressionstests, insbesondere Doppelrotation, Tiebreak und Seitenwechsel.
- Native watchOS/iOS-App, Offline-Persistenz, Synchronisierung.
- Eventuell separates Repository `tennilo-score` anlegen; anschließend Quellcode aus diesem Entwicklungszweig dorthin übernehmen.
- Noch kein Live-Deployment konfiguriert.

**Hinweis:** Dies ist ein Prototyp. Die Speicherung des Quellcodes in GitHub ist keine Veröffentlichung auf einer Website.
