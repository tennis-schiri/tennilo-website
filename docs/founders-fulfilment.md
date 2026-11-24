# TENNILO Founders – Abwicklung (V1.0)
 
Nach manuellem FTPS-Upload im bestehenden Backoffice unter /admin/ → Founders-Abwicklung öffnen. Private Konfiguration und bestehende SQLite-Datenbank bleiben unverändert; neue Tabellen werden ausschließlich beim ersten authentifizierten Zugriff angelegt.
 
Startnext stellt nach erfolgreichem Funding eine Excel-Unterstützerliste bereit. Export lokal als CSV UTF-8 speichern; eine Zeile je gebuchtem Dankeschön bzw. freiem Beitrag. Spalten reference,name,email,package,lang; abweichende Überschriften im Importformular zuordnen. Keine Adressen oder Bankdaten übernehmen. Bei mehreren Dankeschöns pro Bestellung eine stabile eindeutige Referenz je Position verwenden. Paketcode free, fb01–fb05 oder fc01–fc04. E-Mail und Sprache optional, freie Unterstützung darf ohne Namen importiert werden. Max. 500 Zeilen je Import, Vorschau 15 Minuten, Dubletten werden nicht überschrieben. Echtes Startnext-Exportformat muss nach Projektende anhand einer anonymisierten Musterdatei geprüft werden. 
 
Zertifikatsnummer TF-JAHR-ID wird einmalig nach ausdrücklichem Ausstellen vergeben. PDF-Hintergründe sind aus den 18 Zertifikatsmustern abgeleitet; keine Muster-Namen im Hintergrund. Direkt-PDF nutzt Standard-PDF-Schriften, WinAnsi; Unicode/andere Schriftzeichen über Browser-Druckansicht. Keine automatische E-Mail oder Zahlung; Zertifikat übergeben separat als erledigt markieren. Name/Sprache bewusst korrigierbar, Nummer und Datum bleiben. Aufgabe, Termin und Erledigung separat dokumentieren. Stornierung sperrt PDF und entfernt öffentliche Nennung, löst keine Rückzahlung aus. Freie Beiträge erhalten keine Zertifikate. Keine Buchhaltungssoftware oder Zahlungsabgleich.
 
Öffentliche Liste /founders/supporters.php: ausschließlich dokumentiert freigegebener Name und Paket, keine Beträge, Referenzen, Kontaktdaten. Namensfreigabe kann widerrufen werden. Partnerlogo, tatsächlicher Leistungsumfang und Veröffentlichungstermine bei Bedarf separat abstimmen. Daten nur für Erfüllung nutzen; keine Newsletterübernahme. Private Datenbank regelmäßig sichern. Löschung nur nach Prüfung von Aufbewahrungspflichten.
 
Quelle: https://www.startnext.com/hilfe/handbuch/dankeschoens.html ; https://help.startnext.com/article/api-fuer-projekte-profile-vCd0PfwgZS
 
Tests: php tests/founders-fulfilment-test.php (PDO_SQLITE, vorbereitete Zertifikatshintergründe); CI prüft zusätzlich PHP-Syntax. Vor echten Daten mit fiktiver CSV und Login live testen, dann Testdatensätze löschen. Live-Upload ausschließlich manuell.
