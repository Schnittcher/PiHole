# PiHole Network
Zeigt Netzwerkinformationen und DHCP-Leases des Pi-holes und verwaltet beides per Skript. Die Instanz braucht den PiHole Splitter und wird manuell angelegt. Die Kachel zeigt die Zahl der Geräte und DHCP-Leases sowie das Gateway.

## Inhaltsverzeichnis
- [PiHole Network](#pihole-network)
  - [Inhaltsverzeichnis](#inhaltsverzeichnis)
  - [1. Konfiguration](#1-konfiguration)
  - [2. Variablen](#2-variablen)
  - [3. Funktionen](#3-funktionen)
  - [4. Spenden](#4-spenden)
  - [5. Lizenz](#5-lizenz)

## 1. Konfiguration

Feld | Beschreibung
------------ | ----------------
Aktualisierungsintervall | Abstand in Sekunden, in dem die Daten gelesen werden. Standard ist 300, 0 schaltet die Abfrage ab.

## 2. Variablen

Variable | Ident | Typ | Beschreibung
------------ | ------------ | ------------ | ----------------
Bekannte Geräte | `DeviceCount` | Ganzzahl | Anzahl der im Netzwerk gesehenen Geräte (höchstens 1000).
DHCP-Leases | `LeaseCount` | Ganzzahl | Anzahl der DHCP-Leases.
Gateway | `Gateway` | Text | IPv4-Adresse des Standardgateways.

## 3. Funktionen
Beim Aufruf aus Skripten müssen alle Parameter angegeben werden, Standardwerte gelten dort nicht. Übliche Werte: Gruppen `[0]`, Aktiv `true`, Anzahl `10`, Listentyp `block`, alle übrigen optionalen Parameter leer (`''`), `0` oder `false`.
Alle Funktionen außer `Update` liefern die Antwort des Pi-holes als JSON-Text. Bei einem Fehler enthält die Antwort ein Objekt `error`.

`void PIHNET_Update(integer $InstanzID);`
Liest Geräte, Leases und Gateway. Läuft automatisch im eingestellten Intervall.

`string PIHNET_GetDevices(integer $InstanzID, integer $MaxDevices, integer $MaxAddresses);`
Im Netzwerk gesehene Geräte. 0 verwendet den Standard des Pi-holes.

`string PIHNET_DeleteDevice(integer $InstanzID, integer $ID);`
Löscht ein Gerät anhand seiner Nummer aus der Netzwerktabelle.

`string PIHNET_GetGateway(integer $InstanzID, boolean $Detailed);`
`string PIHNET_GetRoutes(integer $InstanzID, boolean $Detailed);`
`string PIHNET_GetInterfaces(integer $InstanzID, boolean $Detailed);`
Gateway, Routing-Tabelle und Netzwerkschnittstellen des Pi-holes.

`string PIHNET_GetLeases(integer $InstanzID);`
Aktive DHCP-Leases.

`string PIHNET_DeleteLease(integer $InstanzID, string $IP);`
Beendet das DHCP-Lease einer IP-Adresse.

## 4. Spenden
Dieses Modul ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:

[![PayPal](https://img.shields.io/badge/PayPal-Spenden-00457C?logo=paypal&logoColor=white&style=for-the-badge)](https://www.paypal.com/donate?hosted_button_id=EK4JRP87XLSHW) [![Amazon Wunschzettel](https://img.shields.io/badge/Amazon-Wunschzettel-FF9900?logo=amazon&logoColor=white&style=for-the-badge)](https://www.amazon.de/hz/wishlist/ls/3JVWED9SZMDPK?ref_=wl_share)

## 5. Lizenz

[CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)
