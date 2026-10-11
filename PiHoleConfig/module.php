<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/PiHoleClient.php';
require_once __DIR__ . '/../libs/PiHoleTile.php';

/**
 * Lesen und Ändern der Pi-hole Konfiguration (Endpunkt config).
 * Die Konfiguration wird über Skriptfunktionen oder die Browser-Kachel bearbeitet.
 */
class PiHoleConfig extends IPSModuleStrict
{
    use PiHoleClient;
    use PiHoleTile;

    public function GetCompatibleParents(): string
    {
        return json_encode([
            'type'      => 'connect',
            'moduleIDs' => ['{7F1B108F-2DCE-496E-8BAE-8103685BF11E}']
        ]);
    }

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        $this->SetVisualizationType(1);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        // Aktiviert die Kachel auch bei bereits vorhandenen Instanzen.
        $this->SetVisualizationType(1);
    }

    public function GetVisualizationTile(): string
    {
        $texts = [
            'Review changes', 'Loading configuration…', 'Saving configuration…',
            'No response. Reload to check the current configuration before saving again.',
            'Reload and discard your unsaved changes?', 'All sections', 'matching settings', 'total',
            'One entry per line', 'Reload the configuration before saving.', 'Enter a valid number.',
            'Changes are only applied after saving.', 'Read-only', 'Description and default value',
            'Default', 'Reload', 'Section', 'Search settings', 'Previous', 'Next',
            'Restart FTL if required', 'Confirm', 'Cancel', 'Save the following settings?',
            'FTL may restart briefly to apply these changes.',
            'A manual FTL restart may be needed to apply these changes.',
            'Pi-hole may end existing API sessions.', 'Could not load or save configuration.',
            'Configuration loaded.', 'Saved. Reload to verify the current values on Pi-hole.'
        ];
        $translations = [];
        foreach ($texts as $text) {
            $translations[$text] = $this->Translate($text);
        }

        return $this->RenderTile(__DIR__, ['init' => true, 't' => $translations]);
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        if (!in_array($Ident, ['LoadConfiguration', 'SaveConfiguration'], true)) {
            return;
        }
        $request = is_string($Value) ? json_decode($Value, true) : null;
        if (!is_array($request) || !is_string($request['requestID'] ?? null)) {
            return;
        }
        // Antworten gehen an alle offenen Kacheln; nur die anfragende Kachel verarbeitet sie.
        $reply = ['requestID' => $request['requestID'], 'action' => $Ident, 'ok' => false];
        try {
            $fields = $this->ConfigurationFields();
            if ($Ident === 'LoadConfiguration') {
                $reply['fields'] = array_values($fields);
            } else {
                $this->SaveBrowserConfiguration($request, $fields);
            }
            $reply['ok'] = true;
        } catch (RuntimeException $exception) {
            $reply['error'] = $exception->getMessage();
        }
        $this->PushTile($reply);
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

    /** Holt Typen, Werte und Schreibschutz direkt vom Pi-hole, ohne sie dauerhaft zu speichern. */
    private function ConfigurationFields(): array
    {
        $data = $this->ConfigurationResponse($this->Api('GET', 'config', ['detailed' => true]));
        $properties = $this->ConfigurationResponse($this->Api('GET', 'config/_properties'));
        if (!is_array($data['config'] ?? null) || !is_array($properties['config']['read_only'] ?? null)) {
            throw new RuntimeException($this->Translate('Unexpected configuration response from Pi-hole.'));
        }
        $locked = [];
        foreach ($properties['config']['read_only'] as $property) {
            if (is_string($property['key'] ?? null)) {
                $locked[$property['key']] = $property['description'] ?? $this->Translate('Read-only');
            }
        }
        $fields = [];
        $this->CollectConfigurationFields($data['config'], [], $locked, $fields);
        if ($fields === []) {
            throw new RuntimeException($this->Translate('Unexpected configuration response from Pi-hole.'));
        }

        return $fields;
    }

    private function CollectConfigurationFields(array $node, array $path, array $locked, array &$fields): void
    {
        if (array_key_exists('value', $node) && is_string($node['type'] ?? null)) {
            $key = implode('.', $path);
            $secret = str_contains($node['type'], 'password') || preg_match('/(password|pwhash|secret)$/i', $key) === 1;
            $reason = $locked[$key] ?? '';
            if ($secret) {
                $reason = $this->Translate('Change passwords and secrets in the Pi-hole web interface.');
            } elseif (($node['flags']['env_var'] ?? false) && $reason === '') {
                $reason = $this->Translate('Set via environment variable');
            }
            $value = $secret ? '********' : $node['value'];
            if ($node['type'] === 'unknown' || (!is_bool($value) && !is_int($value) && !is_float($value) && !is_string($value)
                && !(is_array($value) && array_is_list($value) && count(array_filter($value, 'is_string')) === count($value)))) {
                $reason = $this->Translate('This value cannot be edited in the browser.');
            }
            $fields[$key] = [
                'path'         => $key,
                'type'         => $node['type'],
                'value'        => $value,
                'description'  => $node['description'] ?? '',
                'default'      => $secret ? null : ($node['default'] ?? null),
                'allowed'      => $node['allowed'] ?? null,
                'readOnly'     => $reason !== '',
                'reason'       => $reason,
                'restart'      => (bool) ($node['flags']['restart_dnsmasq'] ?? false),
                'sessionReset' => (bool) ($node['flags']['session_reset'] ?? false)
            ];

            return;
        }
        foreach ($node as $name => $child) {
            if (is_array($child)) {
                $this->CollectConfigurationFields($child, [...$path, (string) $name], $locked, $fields);
            }
        }
    }

    private function SaveBrowserConfiguration(array $request, array $fields): void
    {
        $changes = $request['changes'] ?? null;
        if (!is_array($changes) || !array_is_list($changes) || $changes === [] || !is_bool($request['restart'] ?? null)) {
            throw new RuntimeException($this->Translate('Invalid configuration changes.'));
        }
        $patch = [];
        $seen = [];
        foreach ($changes as $change) {
            $path = is_array($change) ? ($change['path'] ?? null) : null;
            if (!is_string($path) || !isset($fields[$path]) || isset($seen[$path])
                || !array_key_exists('value', $change) || !array_key_exists('original', $change)) {
                throw new RuntimeException($this->Translate('Invalid configuration changes.'));
            }
            $seen[$path] = true;
            $field = $fields[$path];
            if ($field['readOnly']) {
                throw new RuntimeException($path . ': ' . $field['reason']);
            }
            // Verhindert, dass ein seit dem Laden extern geänderter Wert überschrieben wird.
            $sameNumber = (is_int($field['value']) || is_float($field['value']))
                && (is_int($change['original']) || is_float($change['original'])) && $field['value'] == $change['original'];
            if (!$sameNumber && $field['value'] !== $change['original']) {
                throw new RuntimeException($path . ': ' . $this->Translate('Value changed on Pi-hole. Reload before saving.'));
            }
            $value = $change['value'];
            if (!$this->IsConfigurationValue($field, $value)) {
                throw new RuntimeException($path . ': ' . $this->Translate('Invalid value or type.'));
            }
            $target = &$patch;
            $parts = explode('.', $path);
            $leaf = array_pop($parts);
            foreach ($parts as $part) {
                if (!isset($target[$part])) {
                    $target[$part] = [];
                }
                $target = &$target[$part];
            }
            $target[$leaf] = $value;
            unset($target);
        }
        $this->ConfigurationResponse($this->Api('PATCH', 'config', ['restart' => $request['restart']], ['config' => $patch]));
    }

    private function IsConfigurationValue(array $field, mixed $value): bool
    {
        $current = $field['value'];
        $valid = match (true) {
            $field['type'] === 'double' => is_int($value) || (is_float($value) && is_finite($value)),
            is_bool($current)           => is_bool($value),
            is_int($current)            => is_int($value) || (is_float($value) && is_finite($value) && floor($value) === $value),
            is_float($current)          => is_int($value) || (is_float($value) && is_finite($value)),
            is_string($current)         => is_string($value),
            is_array($current)          => is_array($value) && array_is_list($value) && count(array_filter($value, 'is_string')) === count($value),
            default                     => false
        };
        if (!$valid || (str_contains($field['type'], 'unsigned') && $value < 0)
            || (str_contains($field['type'], '16 bit') && $value > 65535)) {
            return false;
        }
        if (is_array($field['allowed']) && array_is_list($field['allowed']) && $field['allowed'] !== []) {
            $allowed = array_map(static fn ($option) => is_array($option) ? ($option['item'] ?? null) : $option, $field['allowed']);
            // Pi-hole beschreibt einige Zahlenbereiche statt einer vollständigen Werteliste.
            if (str_starts_with($field['type'], 'enum') && !in_array($value, $allowed, true)) {
                return false;
            }
        }

        return true;
    }

    private function ConfigurationResponse(array $result): array
    {
        if ($result['Code'] < 200 || $result['Code'] >= 300 || isset($result['Data']['error'])) {
            $error = $result['Data']['error']['message'] ?? ($result['Code'] === 0
                ? $this->Translate('Pi-hole not reachable') : 'HTTP ' . $result['Code']);
            $hint = $result['Data']['error']['hint'] ?? '';
            throw new RuntimeException((string) $error . ($hint !== '' ? ' (' . $hint . ')' : ''));
        }

        return is_array($result['Data']) ? $result['Data'] : [];
    }

    private function ElementPath(string $element): string
    {
        // Punkte wie in der pihole.toml ("dns.upstreams") sind erlaubt und werden zu Schrägstrichen
        $parts = array_filter(explode('/', str_replace('.', '/', $element)), static fn (string $part): bool => $part !== '');

        return implode('/', array_map('rawurlencode', $parts));
    }
}
