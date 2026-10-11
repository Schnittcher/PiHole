# PiHole Config Browser-Editor

Diese Regressionstests laufen ohne echten Pi-hole oder Symcon. Das Backend nutzt einen simulierten Splitter mit Antworten entsprechend dem detaillierten Pi-hole-6-Konfigurationsschema. Die Browserprüfung nutzt Playwright und Chromium und simuliert die Symcon-HTML-SDK-Nachrichten. Beide Befehle liefern bei Fehlern einen Exitcode ungleich null.

Aus der Repository-Wurzel mit PHP 8.2 oder neuer:

```sh
php tests/config-browser.php
```

Mit Node.js, Playwright und dessen installiertem Chromium:

```sh
node tests/config-browser.cjs
```

Alternativ kann ein vorhandener Chromium-Browser über `CHROMIUM_PATH` gewählt werden, z. B. `CHROMIUM_PATH=/usr/bin/chromium node tests/config-browser.cjs`.

Für den zusätzlichen Test des von PHP erzeugten Kachel-HTML in einem eingebetteten Browserrahmen:

```sh
php tests/config-browser.php --render > /tmp/pihole-config-rendered.html
CONFIG_TILE_HTML=/tmp/pihole-config-rendered.html node tests/config-browser.cjs
```

Die Tests prüfen Laden und Speichern, Datentypen, Schreibschutz, maskierte Geheimnisse, bekannte externe Änderungen, Fehlermeldungen, Antwortzuordnung, Eingaben, Bestätigung, Suche und Timeouts. Eine Browserprüfung mit 167 Einstellungen prüft zusätzlich die Bereichsfilter, Trefferzahlen, Begrenzung auf 20 Felder pro Seite, lokale Reaktionszeit und den Erhalt auch ungültiger Eingaben beim Blättern. Die JavaScript-SDK-Übersetzung wird im Test gezielt als fehlerwerfende Funktion simuliert: Die Oberfläche muss vollständig mit den in PHP vorbereiteten Texten arbeiten. Der eingebettete Test prüft außerdem die deutschen Texte und die Sichtbarkeit des oberen Trefferzählers beim Filtern und Scrollen in verschiedenen Größen. Die Tests sind eigenständige Skripte und werden nicht automatisch von der zentralen PHPUnit-Prüfung ausgeführt. Die abschließende Prüfung in einer echten Symcon-Installation mit Pi-hole bleibt erforderlich.
