# Changelog

## 09.10.2026 - Version 2.0

### Neu
- Komplett neu aufgebaut für die REST-API von Pi-hole v6 (die alte `api.php` entfällt).
- PiHole Splitter: Anmeldung per Passwort oder App-Passwort, eine gemeinsame Sitzung für alle Instanzen, HTTPS, Funktion `PIHSPL_Request` für beliebige Endpunkte.
- Neue Instanzen: Statistics, DNS, System, Maintenance, Filters, Network und Config. Zusammen bilden sie die gesamte API ab.
- PiHole Configurator: Zeigt alle Instanztypen, markiert vorhandene und legt fehlende per Klick am Splitter an.
- Kacheln für die Kachel-Visualisierung bei DNS (Blocking per Klick pausieren und fortsetzen, mit Restzeit), Statistics (Anteil geblockt, 24-Stunden-Verlauf, Top-Listen), System (Auslastung, Temperatur, Versionen), Network, Filters und Maintenance (Wartungsaktionen per Klick).
- Die Instanzen lesen ihre Werte direkt nach dem Anlegen und nach dem Speichern, nicht erst im nächsten Intervall.
- Blocking mit Timer, Restzeit und Statistik als Variablen mit nativen Darstellungen.
- Teleporter-Backup, Verwaltung von Domains, Gruppen, Clients und Listen, DHCP-Leases, Konfiguration.

### Fixes
- Das Modul funktioniert wieder mit Pi-hole v6.
- Achtung: Die frühere Instanz "PiHole" und der `API Token` entfallen. Instanzen müssen neu angelegt werden, die Zugangsdaten stehen im Splitter.