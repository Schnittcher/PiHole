# PiHole DNS
Schaltet das DNS-Blocking des Pi-holes ein und aus, auch zeitlich begrenzt. Die Instanz braucht den PiHole Splitter und wird manuell angelegt. Die Kachel zeigt den Zustand als Schaltfläche, pausiert das Blocking per Klick (5 Minuten, 30 Minuten, 1 Stunde oder dauerhaft) und zeigt die Restzeit.

## Inhaltsverzeichnis
- [PiHole DNS](#pihole-dns)
  - [Inhaltsverzeichnis](#inhaltsverzeichnis)
  - [1. Konfiguration](#1-konfiguration)
  - [2. Variablen](#2-variablen)
  - [3. Funktionen](#3-funktionen)
  - [4. Spenden](#4-spenden)
  - [5. Lizenz](#5-lizenz)

## 1. Konfiguration

Feld | Beschreibung
------------ | ----------------
Aktualisierungsintervall | Abstand in Sekunden, in dem der Status gelesen wird. Standard ist 20, 0 schaltet die Abfrage ab.

## 2. Variablen

Variable | Ident | Typ | Beschreibung
------------ | ------------ | ------------ | ----------------
Blocking | `Blocking` | Ja/Nein | Blocking an oder aus. Steuerbar. Beim Ausschalten gilt die Zeit aus `DisableTime`.
Zeit für die Deaktivierung | `DisableTime` | Ganzzahl (Sekunden) | Dauer, nach der das Blocking wieder eingeschaltet wird. Bei 0 bleibt es dauerhaft aus. Steuerbar.
Restzeit | `TimerRemaining` | Ganzzahl (Sekunden) | Verbleibende Zeit eines laufenden Timers, sonst 0.

## 3. Funktionen
Beim Aufruf aus Skripten müssen alle Parameter angegeben werden, Standardwerte gelten dort nicht. Übliche Werte: Gruppen `[0]`, Aktiv `true`, Anzahl `10`, Listentyp `block`, alle übrigen optionalen Parameter leer (`''`), `0` oder `false`.

`void PIHDNS_Update(integer $InstanzID);`
Liest den Blocking-Status. Läuft automatisch im eingestellten Intervall.

`boolean PIHDNS_SetBlocking(integer $InstanzID, boolean $Enabled, integer $Timer);`
Schaltet das Blocking ein (`true`) oder aus (`false`). Mit `$Timer` größer 0 wechselt der Pi-hole nach so vielen Sekunden in den jeweils anderen Zustand zurück. Liefert `true`, wenn der Pi-hole die Änderung angenommen hat.

`string PIHDNS_GetBlocking(integer $InstanzID);`
Status und Restzeit als JSON-Text.

## 4. Spenden
Dieses Modul ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:

[![PayPal](https://img.shields.io/badge/PayPal-Spenden-00457C?logo=paypal&logoColor=white&style=for-the-badge)](https://www.paypal.com/donate?hosted_button_id=EK4JRP87XLSHW) [![Amazon Wunschzettel](https://img.shields.io/badge/Amazon-Wunschzettel-FF9900?logo=amazon&logoColor=white&style=for-the-badge)](https://www.amazon.de/hz/wishlist/ls/3JVWED9SZMDPK?ref_=wl_share)

## 5. Lizenz

[CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)
