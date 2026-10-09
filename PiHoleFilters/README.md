# PiHole Filters
Verwaltet Domains (Allow- und Denylist), Gruppen, Clients und Blocklisten des Pi-holes und sucht Domains in den Listen. Die Instanz zeigt die Anzahl der Einträge als Variablen. Sie braucht den PiHole Splitter und wird manuell angelegt. Die Kachel zeigt die Anzahl der Blocklisten, Gruppen, Clients sowie der erlaubten und gesperrten Domains.

## Inhaltsverzeichnis
- [PiHole Filters](#pihole-filters)
  - [Inhaltsverzeichnis](#inhaltsverzeichnis)
  - [1. Konfiguration](#1-konfiguration)
  - [2. Variablen](#2-variablen)
  - [3. Funktionen](#3-funktionen)
  - [4. Spenden](#4-spenden)
  - [5. Lizenz](#5-lizenz)

## 1. Konfiguration

Feld | Beschreibung
------------ | ----------------
Aktualisierungsintervall | Abstand in Sekunden, in dem die Anzahlen gelesen werden. Standard ist 300, 0 schaltet die Abfrage ab.

## 2. Variablen

Variable | Ident | Typ | Beschreibung
------------ | ------------ | ------------ | ----------------
Blocklisten | `Lists` | Ganzzahl | Anzahl der abonnierten Listen.
Gruppen | `Groups` | Ganzzahl | Anzahl der Gruppen.
Clients | `Clients` | Ganzzahl | Anzahl der verwalteten Clients.
Erlaubte Domains (exakt) | `AllowedExact` | Ganzzahl | Einträge der Allowlist.
Gesperrte Domains (exakt) | `DeniedExact` | Ganzzahl | Einträge der Denylist.
Erlaubte Domains (Regex) | `AllowedRegex` | Ganzzahl | Regex-Einträge der Allowlist.
Gesperrte Domains (Regex) | `DeniedRegex` | Ganzzahl | Regex-Einträge der Denylist.

## 3. Funktionen
Beim Aufruf aus Skripten müssen alle Parameter angegeben werden, Standardwerte gelten dort nicht. Übliche Werte: Gruppen `[0]`, Aktiv `true`, Anzahl `10`, Listentyp `block`, alle übrigen optionalen Parameter leer (`''`), `0` oder `false`.
Alle Funktionen außer `Update` liefern die Antwort des Pi-holes als JSON-Text. Bei einem Fehler enthält die Antwort ein Objekt `error`. Gruppen werden als JSON-Array mit Gruppennummern übergeben, zum Beispiel `[0]` für die Standardgruppe.

`void PIHFILTER_Update(integer $InstanzID);`
Liest die Anzahlen. Läuft automatisch im eingestellten Intervall.

**Domains** (`$Type` ist `allow` oder `deny`, `$Kind` ist `exact` oder `regex`)

`string PIHFILTER_GetDomains(integer $InstanzID, string $Type, string $Kind, string $Domain);`
Domains abfragen. Ohne Angaben kommen alle, mit `$Type`, `$Kind` und `$Domain` wird eingegrenzt.

`string PIHFILTER_AddDomain(integer $InstanzID, string $Type, string $Kind, string $Domain, string $Comment, string $Groups, boolean $Enabled);`
`string PIHFILTER_UpdateDomain(integer $InstanzID, string $Type, string $Kind, string $Domain, string $Comment, string $Groups, boolean $Enabled, string $NewType, string $NewKind);`
`string PIHFILTER_DeleteDomain(integer $InstanzID, string $Type, string $Kind, string $Domain);`
Domain hinzufügen, ändern (mit `$NewType` und `$NewKind` auch verschieben) und löschen.

`string PIHFILTER_DeleteDomains(integer $InstanzID, string $Items);`
Mehrere Domains löschen. `$Items` ist ein JSON-Array mit Einträgen wie `{"item":"example.com","type":"deny","kind":"exact"}`.

**Gruppen**

`string PIHFILTER_GetGroups(integer $InstanzID, string $Name);`
`string PIHFILTER_AddGroup(integer $InstanzID, string $Name, string $Comment, boolean $Enabled);`
`string PIHFILTER_UpdateGroup(integer $InstanzID, string $Name, string $Comment, boolean $Enabled, string $NewName);`
`string PIHFILTER_DeleteGroup(integer $InstanzID, string $Name);`
`string PIHFILTER_DeleteGroups(integer $InstanzID, string $Items);`
Gruppen abfragen, anlegen, ändern (auch umbenennen) und löschen. `$Items` ist ein JSON-Array wie `[{"item":"Kinder"}]`.

**Clients**

`string PIHFILTER_GetClients(integer $InstanzID, string $Client);`
`string PIHFILTER_GetClientSuggestions(integer $InstanzID);`
`string PIHFILTER_AddClient(integer $InstanzID, string $Client, string $Comment, string $Groups);`
`string PIHFILTER_UpdateClient(integer $InstanzID, string $Client, string $Comment, string $Groups);`
`string PIHFILTER_DeleteClient(integer $InstanzID, string $Client);`
`string PIHFILTER_DeleteClients(integer $InstanzID, string $Items);`
Clients (IP, MAC, Hostname oder Schnittstelle) abfragen, vorschlagen lassen, anlegen, ändern und löschen. `$Items` ist ein JSON-Array wie `[{"item":"192.0.2.10"}]`.

**Listen** (`$Type` ist `allow` oder `block`)

`string PIHFILTER_GetLists(integer $InstanzID, string $Address, string $Type);`
`string PIHFILTER_AddList(integer $InstanzID, string $Address, string $Type, string $Comment, string $Groups, boolean $Enabled);`
`string PIHFILTER_UpdateList(integer $InstanzID, string $Address, string $Type, string $Comment, string $Groups, boolean $Enabled);`
`string PIHFILTER_DeleteList(integer $InstanzID, string $Address, string $Type);`
`string PIHFILTER_DeleteLists(integer $InstanzID, string $Items);`
Listen abfragen, hinzufügen, ändern und löschen. Nach dem Hinzufügen die Gravity aktualisieren (Instanz "PiHole Maintenance"). `$Items` ist ein JSON-Array wie `[{"item":"https://example.com/list.txt","type":"block"}]`.

**Suche**

`string PIHFILTER_Search(integer $InstanzID, string $Domain, boolean $Partial, integer $Limit);`
Sucht eine Domain in den Listen, mit `$Partial` auch Teiltreffer.

## 4. Spenden
Dieses Modul ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:

[![PayPal](https://img.shields.io/badge/PayPal-Spenden-00457C?logo=paypal&logoColor=white&style=for-the-badge)](https://www.paypal.com/donate?hosted_button_id=EK4JRP87XLSHW) [![Amazon Wunschzettel](https://img.shields.io/badge/Amazon-Wunschzettel-FF9900?logo=amazon&logoColor=white&style=for-the-badge)](https://www.amazon.de/hz/wishlist/ls/3JVWED9SZMDPK?ref_=wl_share)

## 5. Lizenz

[CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)
