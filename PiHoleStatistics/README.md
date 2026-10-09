# PiHole Statistics
Zeigt die Statistik des Pi-holes als Variablen und liefert Top-Listen, Verlauf und einzelne DNS-Abfragen für Skripte. Die Instanz braucht den PiHole Splitter und wird manuell angelegt. Die Kachel zeigt den Anteil geblockter Abfragen, die Kennzahlen, den Verlauf der letzten 24 Stunden und die Top-Listen. Verlauf und Top-Listen erscheinen, sobald der Pi-hole Daten liefert.

## Inhaltsverzeichnis
- [PiHole Statistics](#pihole-statistics)
  - [Inhaltsverzeichnis](#inhaltsverzeichnis)
  - [1. Konfiguration](#1-konfiguration)
  - [2. Variablen](#2-variablen)
  - [3. Funktionen](#3-funktionen)
  - [4. Spenden](#4-spenden)
  - [5. Lizenz](#5-lizenz)

## 1. Konfiguration

Feld | Beschreibung
------------ | ----------------
Aktualisierungsintervall | Abstand in Sekunden, in dem die Daten gelesen werden. Standard ist 30, 0 schaltet die Abfrage ab.
Variablen je Abfragetyp anlegen | Legt je DNS-Typ (A, AAAA, ...) eine Variable an. Abwählen entfernt sie.
Variablen je Abfragestatus anlegen | Legt je Status (Gravity, Cache, Forwarded, ...) eine Variable an.
Variablen je Antworttyp anlegen | Legt je Antworttyp (IP, NXDOMAIN, ...) eine Variable an.

## 2. Variablen

Variable | Ident | Typ | Beschreibung
------------ | ------------ | ------------ | ----------------
DNS-Abfragen | `Total` | Ganzzahl | Anzahl der Abfragen.
Geblockte Abfragen | `Blocked` | Ganzzahl | Anzahl der geblockten Abfragen.
Anteil geblockt | `PercentBlocked` | Kommazahl (%) | Anteil der geblockten Abfragen.
Eindeutige Domains | `UniqueDomains` | Ganzzahl | Anzahl der bekannten Domains.
Weitergeleitete Abfragen | `Forwarded` | Ganzzahl | An Upstream-Server weitergeleitet.
Aus dem Cache beantwortet | `Cached` | Ganzzahl | Aus Cache oder lokaler Konfiguration beantwortet.
Abfragen pro Sekunde | `Frequency` | Kommazahl | Durchschnittliche Abfragefrequenz.
Aktive Clients | `ClientsActive` | Ganzzahl | In den letzten 24 Stunden gesehen.
Clients gesamt | `ClientsTotal` | Ganzzahl | Alle bekannten Clients.
Domains auf Blocklisten | `GravityDomains` | Ganzzahl | Domains der Gravity-Liste.
Gravity zuletzt aktualisiert | `GravityLastUpdate` | Datum/Uhrzeit | Zeitpunkt der letzten Aktualisierung, 0 wenn unbekannt.
Abfragetyp *X* | `Type_<X>` | Ganzzahl | Nur mit der Option je Abfragetyp.
Abfragestatus *X* | `Status_<X>` | Ganzzahl | Nur mit der Option je Abfragestatus.
Antworttyp *X* | `Reply_<X>` | Ganzzahl | Nur mit der Option je Antworttyp.

## 3. Funktionen
Beim Aufruf aus Skripten müssen alle Parameter angegeben werden, Standardwerte gelten dort nicht. Übliche Werte: Gruppen `[0]`, Aktiv `true`, Anzahl `10`, Listentyp `block`, alle übrigen optionalen Parameter leer (`''`), `0` oder `false`.
Alle Funktionen außer `Update` liefern die Antwort des Pi-holes als JSON-Text. Bei einem Fehler enthält die Antwort ein Objekt `error`.

`void PIHSTAT_Update(integer $InstanzID);`
Liest die Zusammenfassung und aktualisiert die Variablen. Läuft automatisch im eingestellten Intervall.

`string PIHSTAT_GetSummary(integer $InstanzID);`
Übersicht der Aktivität.

`string PIHSTAT_GetTopDomains(integer $InstanzID, boolean $Blocked, integer $Count);`
Am häufigsten angefragte Domains, mit `$Blocked` nur geblockte.

`string PIHSTAT_GetTopClients(integer $InstanzID, boolean $Blocked, integer $Count);`
Aktivste Clients.

`string PIHSTAT_GetUpstreams(integer $InstanzID);`
Statistik der Upstream-DNS-Server.

`string PIHSTAT_GetQueryTypes(integer $InstanzID);`
Anzahl der Abfragen je DNS-Typ.

`string PIHSTAT_GetRecentBlocked(integer $InstanzID, integer $Count);`
Zuletzt geblockte Domains.

`string PIHSTAT_GetHistory(integer $InstanzID);`
Verlauf der letzten 24 Stunden.

`string PIHSTAT_GetClientHistory(integer $InstanzID, integer $Clients);`
Verlauf je Client, `0` liefert alle Clients.

`string PIHSTAT_GetDatabaseSummary(integer $InstanzID, integer $From, integer $Until);`
`string PIHSTAT_GetDatabaseTopDomains(integer $InstanzID, integer $From, integer $Until, boolean $Blocked, integer $Count);`
`string PIHSTAT_GetDatabaseTopClients(integer $InstanzID, integer $From, integer $Until, boolean $Blocked, integer $Count);`
`string PIHSTAT_GetDatabaseUpstreams(integer $InstanzID, integer $From, integer $Until);`
`string PIHSTAT_GetDatabaseQueryTypes(integer $InstanzID, integer $From, integer $Until);`
`string PIHSTAT_GetDatabaseHistory(integer $InstanzID, integer $From, integer $Until);`
`string PIHSTAT_GetDatabaseClientHistory(integer $InstanzID, integer $From, integer $Until);`
Wie die Funktionen oben, aber aus der Langzeit-Datenbank. `$From` und `$Until` sind Unix-Zeitstempel.

`string PIHSTAT_GetQueries(integer $InstanzID, string $Filter);`
Einzelne DNS-Abfragen. `$Filter` ist ein JSON-Objekt, zum Beispiel `{"length":50,"domain":"example.com"}`. Mögliche Felder: `from`, `until`, `length`, `start`, `cursor`, `domain`, `client_ip`, `client_name`, `upstream`, `type`, `status`, `reply`, `dnssec`, `disk`.

`string PIHSTAT_GetQuerySuggestions(integer $InstanzID);`
Vorschläge für Filterwerte.

## 4. Spenden
Dieses Modul ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:

[![PayPal](https://img.shields.io/badge/PayPal-Spenden-00457C?logo=paypal&logoColor=white&style=for-the-badge)](https://www.paypal.com/donate?hosted_button_id=EK4JRP87XLSHW) [![Amazon Wunschzettel](https://img.shields.io/badge/Amazon-Wunschzettel-FF9900?logo=amazon&logoColor=white&style=for-the-badge)](https://www.amazon.de/hz/wishlist/ls/3JVWED9SZMDPK?ref_=wl_share)

## 5. Lizenz

[CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)
