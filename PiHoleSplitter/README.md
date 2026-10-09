# PiHole Splitter
Der Splitter stellt die Verbindung zur REST-API von Pi-hole (ab Version 6) her. Er meldet sich einmal am Pi-hole an, hält die Sitzung und reicht die Anfragen aller angeschlossenen Instanzen weiter. Er wird zuerst angelegt, die übrigen Pi-hole Instanzen legen ihn beim Anlegen automatisch als übergeordnete Instanz an oder verbinden sich mit einem vorhandenen.

## Inhaltsverzeichnis
- [PiHole Splitter](#pihole-splitter)
  - [Inhaltsverzeichnis](#inhaltsverzeichnis)
  - [1. Konfiguration](#1-konfiguration)
  - [2. Variablen](#2-variablen)
  - [3. Funktionen](#3-funktionen)
  - [4. Spenden](#4-spenden)
  - [5. Lizenz](#5-lizenz)

## 1. Konfiguration

Feld | Beschreibung
------------ | ----------------
Host | IP-Adresse oder Hostname des Pi-holes.
Port | Port des Webinterfaces. Standard ist 80, bei HTTPS meist 443.
HTTPS verwenden | Zugriff per HTTPS.
Zertifikat prüfen | Prüft das Zertifikat des Pi-holes. Aus, wenn der Pi-hole ein selbstsigniertes Zertifikat nutzt (Standard).
Passwort oder App-Passwort | Passwort des Pi-holes oder ein App-Passwort (Settings -> Web interface / API -> Configure app password). Mit App-Passwort funktioniert die Anmeldung auch bei aktivierter 2FA. Bleibt das Feld leer, wird ohne Anmeldung zugegriffen, so wie es Pi-hole ohne gesetztes Passwort erlaubt.
Zeitüberschreitung | Wartezeit in Sekunden je Anfrage. Standard ist 10.

Die Schaltfläche "Verbindung testen" prüft Erreichbarkeit und Anmeldung.

Pi-hole begrenzt die Zahl gleichzeitiger Sitzungen. Der Splitter nutzt deshalb genau eine Sitzung für alle Instanzen und gibt sie bei geänderter Konfiguration frei.

## 2. Variablen

Variable | Ident | Typ | Beschreibung
------------ | ------------ | ------------ | ----------------
Verbunden | `Connected` | Ja/Nein | `true`, wenn die letzte Anfrage den Pi-hole erreicht hat und die Anmeldung gültig war.

## 3. Funktionen
Beim Aufruf aus Skripten müssen alle Parameter angegeben werden, Standardwerte gelten dort nicht. Übliche Werte: Gruppen `[0]`, Aktiv `true`, Anzahl `10`, Listentyp `block`, alle übrigen optionalen Parameter leer (`''`), `0` oder `false`.

`string PIHSPL_Request(integer $InstanzID, string $Method, string $Endpoint, string $Query, string $Body);`
Allgemeine Anfrage an die API, damit ist jeder Endpunkt erreichbar. `$Endpoint` steht ohne `/api/`, zum Beispiel `info/version`. `$Query` ist ein JSON-Objekt mit den Parametern, `$Body` der Anfragekörper als JSON. Die Antwort ist ein JSON-Objekt mit `Code` (HTTP-Status, 0 = nicht erreichbar), `Data` (Antwort der API) und `Text` (Antwort, die kein JSON ist).

`string PIHSPL_TestConnection(integer $InstanzID);`
Prüft Erreichbarkeit und Anmeldung und liefert eine Meldung.

`void PIHSPL_Logout(integer $InstanzID);`
Beendet die aktuelle Sitzung am Pi-hole. Bei der nächsten Anfrage meldet sich der Splitter neu an.

`string PIHSPL_GetSessions(integer $InstanzID);`
Liste der aktiven Sitzungen am Pi-hole (JSON).

`string PIHSPL_DeleteSession(integer $InstanzID, integer $ID);`
Beendet eine Sitzung anhand ihrer Nummer.

`string PIHSPL_GenerateAppPassword(integer $InstanzID);`
Erzeugt ein neues App-Passwort. Ein bisheriges App-Passwort wird damit ungültig. Die Antwort enthält das neue Passwort im Klartext.

## 4. Spenden
Dieses Modul ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:

[![PayPal](https://img.shields.io/badge/PayPal-Spenden-00457C?logo=paypal&logoColor=white&style=for-the-badge)](https://www.paypal.com/donate?hosted_button_id=EK4JRP87XLSHW) [![Amazon Wunschzettel](https://img.shields.io/badge/Amazon-Wunschzettel-FF9900?logo=amazon&logoColor=white&style=for-the-badge)](https://www.amazon.de/hz/wishlist/ls/3JVWED9SZMDPK?ref_=wl_share)

## 5. Lizenz

[CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)
