<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/PiHoleClient.php';

/**
 * Lesen und Ändern der Pi-hole Konfiguration (Endpunkt config).
 * Die Instanz legt keine Variablen an, die Konfiguration wird über Funktionen bearbeitet.
 */
class PiHoleConfig extends IPSModuleStrict
{
    use PiHoleClient;

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        $this->ConnectParent('{7F1B108F-2DCE-496E-8BAE-8103685BF11E}');
    }

    /**
     * Gesamte Konfiguration (JSON).
     *
     * @param bool $Detailed true = mit Beschreibung, Typ und Standardwert je Eintrag
     */
    public function GetConfig(bool $Detailed = false): string
    {
        return $this->ApiJson('GET', 'config', ['detailed' => $Detailed]);
    }

    /**
     * Ein Teil der Konfiguration (JSON).
     *
     * @param string $Element Pfad mit Schrägstrichen, zum Beispiel "dns/upstreams" oder "dns"
     */
    public function GetConfigElement(string $Element, bool $Detailed = false): string
    {
        return $this->ApiJson('GET', 'config/' . $this->ElementPath($Element), ['detailed' => $Detailed]);
    }

    /** Schreibgeschützte Einstellungen, zum Beispiel durch Umgebungsvariablen oder die Datei pihole.toml (JSON). */
    public function GetConfigProperties(): string
    {
        return $this->ApiJson('GET', 'config/_properties');
    }

    /**
     * Ändert Einstellungen.
     *
     * @param string $Config  Änderung als JSON-Objekt, zum Beispiel {"dns":{"queryLogging":false}}
     * @param bool   $Restart false = FTL nicht neu starten, wenn weitere Änderungen folgen
     */
    public function SetConfig(string $Config, bool $Restart = true): string
    {
        $config = $this->DecodeParameter($Config);
        if ($config === null) {
            return json_encode(['error' => ['key' => 'bad_request', 'message' => 'invalid JSON parameter']]);
        }

        return $this->ApiJson('PATCH', 'config', ['restart' => $Restart], ['config' => $config]);
    }

    /**
     * Fügt einer Listeneinstellung einen Eintrag hinzu, zum Beispiel einen Upstream-DNS-Server.
     *
     * @param string $Element Pfad der Einstellung, zum Beispiel "dns/upstreams"
     * @param string $Value   Neuer Eintrag
     */
    public function AddConfigValue(string $Element, string $Value, bool $Restart = true): string
    {
        return $this->ApiJson('PUT', 'config/' . $this->ElementPath($Element) . '/' . rawurlencode($Value), ['restart' => $Restart]);
    }

    /** Entfernt einen Eintrag aus einer Listeneinstellung. */
    public function DeleteConfigValue(string $Element, string $Value, bool $Restart = true): string
    {
        return $this->ApiJson('DELETE', 'config/' . $this->ElementPath($Element) . '/' . rawurlencode($Value), ['restart' => $Restart]);
    }

    private function ElementPath(string $element): string
    {
        // Punkte wie in der pihole.toml ("dns.upstreams") sind erlaubt und werden zu Schrägstrichen
        $parts = array_filter(explode('/', str_replace('.', '/', $element)), static fn (string $part): bool => $part !== '');

        return implode('/', array_map('rawurlencode', $parts));
    }
}
