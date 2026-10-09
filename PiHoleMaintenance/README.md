# PiHole Maintenance
Führt Wartungsaktionen am Pi-hole aus (Gravity aktualisieren, DNS neu starten, Caches und Logs leeren) und sichert oder stellt die Konfiguration über den Teleporter wieder her. Die Instanz braucht den PiHole Splitter und wird manuell angelegt. Die Kachel startet Gravity-Update, DNS-Neustart und das Leeren des ARP-Caches per Klick und zeigt die letzte Ausführung. Logs und Netzwerktabelle lassen sich nur über die Variable und die Funktionen leeren.

## Inhaltsverzeichnis
- [PiHole Maintenance](#pihole-maintenance)
  - [Inhaltsverzeichnis](#inhaltsverzeichnis)
  - [1. Konfiguration](#1-konfiguration)
  - [2. Variablen](#2-variablen)
  - [3. Funktionen](#3-funktionen)
  - [4. Spenden](#4-spenden)
  - [5. Lizenz](#5-lizenz)

## 1. Konfiguration
Die Instanz hat keine Einstellungen. Im Konfigurationsformular lassen sich "Gravity aktualisieren" und "DNS neu starten" nach einer Rückfrage auslösen.

## 2. Variablen

Variable | Ident | Typ | Beschreibung
------------ | ------------ | ------------ | ----------------
Aktion | `Action` | Ganzzahl (Aufzählung) | Löst eine Wartungsaktion aus: 1 Gravity aktualisieren, 2 DNS neu starten, 3 Logs leeren, 4 ARP-Cache leeren, 5 Netzwerktabelle leeren. Steuerbar.
Letztes Ergebnis | `LastResult` | Text | Name der letzten Aktion und ob sie erledigt wurde.
Letzte Ausführung | `LastRun` | Datum/Uhrzeit | Zeitpunkt der letzten Aktion.

## 3. Funktionen
Beim Aufruf aus Skripten müssen alle Parameter angegeben werden, Standardwerte gelten dort nicht. Übliche Werte: Gruppen `[0]`, Aktiv `true`, Anzahl `10`, Listentyp `block`, alle übrigen optionalen Parameter leer (`''`), `0` oder `false`.
Die Aktionen liefern die Ausgabe des Pi-holes als Text.

`string PIHMAINT_UpdateGravity(integer $InstanzID);`
Aktualisiert die Blocklisten. Das kann einige Minuten dauern und belegt solange die Verbindung des Splitters.

`string PIHMAINT_RestartDNS(integer $InstanzID);`
Startet den DNS-Server neu.

`string PIHMAINT_FlushLogs(integer $InstanzID);`
`string PIHMAINT_FlushNetwork(integer $InstanzID);`
Leert die Logdateien beziehungsweise die Netzwerktabelle. Der Pi-hole erlaubt das nur, wenn destruktive Aktionen aktiviert sind.

`string PIHMAINT_FlushARP(integer $InstanzID);`
Leert den ARP-Cache.

`boolean PIHMAINT_ExportTeleporter(integer $InstanzID, string $Path);`
Speichert ein Teleporter-Backup als ZIP-Datei unter `$Path`. Der Ordner muss existieren. Liefert `true` bei Erfolg.

`string PIHMAINT_ImportTeleporter(integer $InstanzID, string $Path, string $Options);`
Spielt ein Teleporter-Backup ein und überschreibt dabei vorhandene Daten. `$Options` wählt die Bereiche als JSON, zum Beispiel `{"config":true,"gravity":{"group":true,"adlist":true}}`. Leer importiert alles.

## 4. Spenden
Dieses Modul ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:

[![PayPal](https://img.shields.io/badge/PayPal-Spenden-00457C?logo=paypal&logoColor=white&style=for-the-badge)](https://www.paypal.com/donate?hosted_button_id=EK4JRP87XLSHW) [![Amazon Wunschzettel](https://img.shields.io/badge/Amazon-Wunschzettel-FF9900?logo=amazon&logoColor=white&style=for-the-badge)](https://www.amazon.de/hz/wishlist/ls/3JVWED9SZMDPK?ref_=wl_share)

## 5. Lizenz

[CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)
