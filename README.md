[![Version](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
![Version](https://img.shields.io/badge/Symcon%20Version-8.1%20%3E-blue.svg)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![Check Style](https://github.com/Schnittcher/PiHole/workflows/Check%20Style/badge.svg)](https://github.com/Schnittcher/PiHole/actions)

# PiHole
Diese Bibliothek bindet einen Pi-hole (ab Version 6) über dessen REST-API in IP-Symcon ein. Ein Splitter hält die Verbindung, einzelne Instanzen bilden die Bereiche der API ab.

## Inhaltsverzeichnis
- [PiHole](#pihole)
  - [Inhaltsverzeichnis](#inhaltsverzeichnis)
  - [1. Voraussetzungen](#1-voraussetzungen)
  - [2. Funktionsumfang](#2-funktionsumfang)
  - [3. Enthaltene Module](#3-enthaltene-module)
  - [4. Installation](#4-installation)
  - [5. Konfiguration in IP-Symcon](#5-konfiguration-in-ip-symcon)
  - [6. Spenden](#6-spenden)
  - [7. Lizenz](#7-lizenz)

## 1. Voraussetzungen

* mindestens IPS Version 8.1
* Pi-hole ab Version 6 (REST-API)
* Passwort oder App-Passwort des Pi-holes (entfällt, wenn kein Passwort gesetzt ist)

## 2. Funktionsumfang
* Blocking ein- und ausschalten, auch zeitlich begrenzt
* Kacheln für die Kachel-Visualisierung (DNS, Statistics, System, Network, Filters, Maintenance)
* Statistik, Top-Listen, Verlauf und DNS-Abfragen
* Systemzustand, Versionen, Meldungen und Logs
* Wartung: Gravity, DNS-Neustart, Teleporter-Backup
* Verwaltung von Domains, Gruppen, Clients und Blocklisten
* Netzwerkgeräte und DHCP-Leases
* Lesen und Ändern der Pi-hole Konfiguration

Die einzelnen Funktionen stehen bei den Instanzen unter "Enthaltene Module" und in der README des jeweiligen Moduls. Jeder Endpunkt der API ist zusätzlich über die Funktion `PIHSPL_Request` des Splitters erreichbar.

## 3. Enthaltene Module

* [PiHole Splitter](PiHoleSplitter/README.md)
  * Verbindung zur API: Anmeldung, Sitzung und Weiterreichen der Anfragen.
  * Wird von allen anderen Instanzen als übergeordnete Instanz benötigt.
* [PiHole Configurator](PiHoleConfigurator/README.md)
  * Zeigt alle Instanztypen der Bibliothek, markiert die vorhandenen und legt fehlende mit dem Splitter verbunden an.
* [PiHole Statistics](PiHoleStatistics/README.md)
  * Statistik des Pi-holes als Variablen, dazu Top-Listen, Verlauf und DNS-Abfragen für Skripte.
* [PiHole DNS](PiHoleDNS/README.md)
  * Schaltet das Blocking ein und aus, auch zeitlich begrenzt.
* [PiHole System](PiHoleSystem/README.md)
  * Auslastung, Temperatur, Versionen und Diagnosemeldungen, dazu Logs und Systeminformationen.
* [PiHole Maintenance](PiHoleMaintenance/README.md)
  * Gravity aktualisieren, DNS neu starten, Caches und Logs leeren, Teleporter-Backup.
* [PiHole Filters](PiHoleFilters/README.md)
  * Verwaltung von Domains, Gruppen, Clients und Blocklisten, dazu Suche in den Listen.
* [PiHole Network](PiHoleNetwork/README.md)
  * Netzwerkgeräte, Routen, Schnittstellen und DHCP-Leases.
* [PiHole Config](PiHoleConfig/README.md)
  * Lesen und Ändern der Pi-hole Konfiguration per Skript.

## 4. Installation
Die Installation erfolgt über den IP-Symcon Module Store.

## 5. Konfiguration in IP-Symcon
Zuerst eine Instanz "PiHole Splitter" anlegen und Host, Port und Passwort eintragen. Danach die gewünschten Instanzen anlegen, am einfachsten über eine Instanz "PiHole Configurator": Sie listet alle Instanztypen, zeigt die vorhandenen und legt fehlende per Klick an. Die Instanzen verbinden sich mit dem Splitter, ein fehlender Splitter wird beim Anlegen miterzeugt. Details stehen in der README des jeweiligen Moduls.

Die Version 2 ersetzt die frühere Instanz "PiHole". Bestehende Instanzen werden nicht übernommen und müssen neu angelegt werden.

## 6. Spenden
Dieses Modul ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:

[![PayPal](https://img.shields.io/badge/PayPal-Spenden-00457C?logo=paypal&logoColor=white&style=for-the-badge)](https://www.paypal.com/donate?hosted_button_id=EK4JRP87XLSHW) [![Amazon Wunschzettel](https://img.shields.io/badge/Amazon-Wunschzettel-FF9900?logo=amazon&logoColor=white&style=for-the-badge)](https://www.amazon.de/hz/wishlist/ls/3JVWED9SZMDPK?ref_=wl_share)

## 7. Lizenz

[CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)
