# PiHole System
Zeigt Systemzustand, Versionen und Diagnosemeldungen des Pi-holes und liefert Logs und weitere Informationen für Skripte. Die Instanz braucht den PiHole Splitter und wird manuell angelegt. Die Kachel zeigt CPU, RAM und Temperatur als Ringe, dazu Laufzeit, Versionen und Hinweise auf Updates und Diagnosemeldungen.

## Inhaltsverzeichnis
- [PiHole System](#pihole-system)
  - [Inhaltsverzeichnis](#inhaltsverzeichnis)
  - [1. Konfiguration](#1-konfiguration)
  - [2. Variablen](#2-variablen)
  - [3. Funktionen](#3-funktionen)
  - [4. Spenden](#4-spenden)
  - [5. Lizenz](#5-lizenz)

## 1. Konfiguration

Feld | Beschreibung
------------ | ----------------
Aktualisierungsintervall | Abstand in Sekunden für System-, Sensor- und FTL-Werte. Standard ist 60, 0 schaltet die Abfrage ab.
Aktualisierungsintervall für Versionen und Datenbank | Abstand in Sekunden für Versionen, Datenbank und Host. Standard ist 3600.

## 2. Variablen

Variable | Ident | Typ | Beschreibung
------------ | ------------ | ------------ | ----------------
Laufzeit des Systems | `Uptime` | Ganzzahl (Dauer) | Laufzeit des PiHole Systems.
Arbeitsspeicher belegt | `RamUsed` | Kommazahl (%) | Belegter Arbeitsspeicher.
Swap belegt | `SwapUsed` | Kommazahl (%) | Belegter Swap.
Prozesse | `Processes` | Ganzzahl | Anzahl der Prozesse.
CPU-Auslastung | `CpuUsage` | Kommazahl (%) | Auslastung der CPU.
CPU-Auslastung FTL | `FtlCpu` | Kommazahl (%) | CPU-Anteil von FTL.
Speicherauslastung FTL | `FtlMemory` | Kommazahl (%) | Speicheranteil von FTL.
CPU-Temperatur | `CpuTemperature` | Kommazahl (°C) | Nur, wenn der Pi-hole sie meldet.
Laufzeit FTL | `FtlUptime` | Ganzzahl (Dauer) | Laufzeit von FTL.
Datenschutzstufe | `PrivacyLevel` | Ganzzahl | Datenschutzstufe von FTL.
Diagnosemeldungen | `MessageCount` | Ganzzahl | Anzahl der Diagnosemeldungen.
Core-Version | `CoreVersion` | Text | Installierte Version.
Web-Version | `WebVersion` | Text | Installierte Version.
FTL-Version | `FtlVersion` | Text | Installierte Version.
Größe der Datenbank | `DatabaseSize` | Kommazahl (MB) | Größe der Langzeit-Datenbank.
Abfragen in der Datenbank | `DatabaseQueries` | Ganzzahl | Anzahl der gespeicherten Abfragen.
Hostmodell | `HostModel` | Text | Modell des Hosts, zum Beispiel Raspberry Pi.
Update verfügbar | `UpdateAvailable` | Ja/Nein | `true`, wenn für Core, Web oder FTL eine andere Version verfügbar ist.

## 3. Funktionen
Beim Aufruf aus Skripten müssen alle Parameter angegeben werden, Standardwerte gelten dort nicht. Übliche Werte: Gruppen `[0]`, Aktiv `true`, Anzahl `10`, Listentyp `block`, alle übrigen optionalen Parameter leer (`''`), `0` oder `false`.
Alle Funktionen außer `Update` und `UpdateSlow` liefern die Antwort des Pi-holes als JSON-Text. Bei einem Fehler enthält die Antwort ein Objekt `error`.

`void PIHSYS_Update(integer $InstanzID);`
Liest System-, Sensor-, FTL-Werte und die Anzahl der Meldungen. Läuft automatisch.

`void PIHSYS_UpdateSlow(integer $InstanzID);`
Liest Versionen, Datenbank und Host. Läuft automatisch.

`string PIHSYS_GetSystem(integer $InstanzID);`
`string PIHSYS_GetHost(integer $InstanzID);`
`string PIHSYS_GetFtl(integer $InstanzID);`
`string PIHSYS_GetSensors(integer $InstanzID);`
`string PIHSYS_GetDatabase(integer $InstanzID);`
`string PIHSYS_GetVersion(integer $InstanzID);`
`string PIHSYS_GetMetrics(integer $InstanzID);`
Informationen zu System, Host, FTL, Sensoren, Datenbank, Versionen sowie DNS- und DHCP-Metriken.

`string PIHSYS_GetMessages(integer $InstanzID);`
`string PIHSYS_GetMessageCount(integer $InstanzID);`
Diagnosemeldungen und ihre Anzahl.

`string PIHSYS_DeleteMessages(integer $InstanzID, string $IDs);`
Löscht Meldungen anhand ihrer Nummern, durch Komma getrennt, zum Beispiel `1,2,3`.

`string PIHSYS_GetClientInfo(integer $InstanzID);`
`string PIHSYS_GetLoginInfo(integer $InstanzID);`
`string PIHSYS_GetEndpoints(integer $InstanzID);`
Informationen zum anfragenden Client, zur Anmeldeseite und die Liste der verfügbaren API-Endpunkte.

`string PIHSYS_GetDNSLog(integer $InstanzID, integer $NextID);`
`string PIHSYS_GetFTLLog(integer $InstanzID, integer $NextID);`
`string PIHSYS_GetWebserverLog(integer $InstanzID, integer $NextID);`
Logs von DNS-Server, FTL und Webserver. Die Antwort enthält `nextID`. Wird sie beim nächsten Aufruf als `$NextID` übergeben, kommen nur neue Zeilen.

## 4. Spenden
Dieses Modul ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:

[![PayPal](https://img.shields.io/badge/PayPal-Spenden-00457C?logo=paypal&logoColor=white&style=for-the-badge)](https://www.paypal.com/donate?hosted_button_id=EK4JRP87XLSHW) [![Amazon Wunschzettel](https://img.shields.io/badge/Amazon-Wunschzettel-FF9900?logo=amazon&logoColor=white&style=for-the-badge)](https://www.amazon.de/hz/wishlist/ls/3JVWED9SZMDPK?ref_=wl_share)

## 5. Lizenz

[CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)
