# PiHole Config
Liest und ändert die Konfiguration des Pi-holes per Skript. Die Instanz braucht den PiHole Splitter und wird manuell angelegt. Sie hat keine Einstellungen und keine Variablen.

## Inhaltsverzeichnis
- [PiHole Config](#pihole-config)
  - [Inhaltsverzeichnis](#inhaltsverzeichnis)
  - [1. Konfiguration](#1-konfiguration)
  - [2. Variablen](#2-variablen)
  - [3. Funktionen](#3-funktionen)
  - [4. Spenden](#4-spenden)
  - [5. Lizenz](#5-lizenz)

## 1. Konfiguration
Die Instanz hat keine Einstellungen.

## 2. Variablen
Die Instanz legt keine Variablen an.

## 3. Funktionen
Beim Aufruf aus Skripten müssen alle Parameter angegeben werden, Standardwerte gelten dort nicht. Übliche Werte: Gruppen `[0]`, Aktiv `true`, Anzahl `10`, Listentyp `block`, alle übrigen optionalen Parameter leer (`''`), `0` oder `false`.
Alle Funktionen liefern die Antwort des Pi-holes als JSON-Text. Bei einem Fehler enthält die Antwort ein Objekt `error`. Einstellungen werden als Pfad angegeben, mit Schrägstrichen oder Punkten, zum Beispiel `dns/upstreams` oder `dns.upstreams`.

`string PIHCONF_GetConfig(integer $InstanzID, boolean $Detailed);`
Gesamte Konfiguration, mit `$Detailed` samt Beschreibung, Typ und Standardwert.

`string PIHCONF_GetConfigElement(integer $InstanzID, string $Element, boolean $Detailed);`
Ein Teil der Konfiguration.

`string PIHCONF_GetConfigProperties(integer $InstanzID);`
Schreibgeschützte Einstellungen, etwa durch Umgebungsvariablen oder die Datei `pihole.toml`.

`string PIHCONF_SetConfig(integer $InstanzID, string $Config, boolean $Restart);`
Ändert Einstellungen. `$Config` ist ein JSON-Objekt, zum Beispiel `{"dns":{"queryLogging":false}}`. Mit `$Restart = false` startet FTL nicht neu, sinnvoll bei mehreren aufeinanderfolgenden Änderungen.

`string PIHCONF_AddConfigValue(integer $InstanzID, string $Element, string $Value, boolean $Restart);`
`string PIHCONF_DeleteConfigValue(integer $InstanzID, string $Element, string $Value, boolean $Restart);`
Fügt einer Listeneinstellung einen Eintrag hinzu oder entfernt ihn, zum Beispiel einen Upstream-DNS-Server unter `dns/upstreams`.

Eine falsche Konfiguration kann den DNS-Betrieb des Pi-holes stören. Vor größeren Änderungen empfiehlt sich ein Teleporter-Backup (Instanz "PiHole Maintenance").

## 4. Spenden
Dieses Modul ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:

[![PayPal](https://img.shields.io/badge/PayPal-Spenden-00457C?logo=paypal&logoColor=white&style=for-the-badge)](https://www.paypal.com/donate?hosted_button_id=EK4JRP87XLSHW) [![Amazon Wunschzettel](https://img.shields.io/badge/Amazon-Wunschzettel-FF9900?logo=amazon&logoColor=white&style=for-the-badge)](https://www.amazon.de/hz/wishlist/ls/3JVWED9SZMDPK?ref_=wl_share)

## 5. Lizenz

[CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)
