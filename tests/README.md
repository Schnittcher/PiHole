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

Die Tests prüfen Laden und Speichern, Datentypen, Schreibschutz, maskierte Geheimnisse, bekannte externe Änderungen, Fehlermeldungen, Antwortzuordnung, Eingaben, Bestätigung, Suche und Timeouts. Eine Browserprüfung mit 167 Einstellungen prüft zusätzlich die Bereichsfilter, Trefferzahlen, Begrenzung auf 20 Felder pro Seite, lokale Reaktionszeit, zwischengespeicherte Übersetzungen und den Erhalt auch ungültiger Eingaben beim Blättern. Sie sind eigenständige Skripte und werden nicht automatisch von der zentralen PHPUnit-Prüfung ausgeführt. Die abschließende Prüfung in einer echten Symcon-Installation mit Pi-hole bleibt erforderlich.
