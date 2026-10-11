<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/PiHoleClient.php';
require_once __DIR__ . '/../libs/PiHoleTile.php';

/**
 * Systemzustand, Versionen, Meldungen und Logs des Pi-holes (Endpunkte info, logs, endpoints).
 */
class PiHoleSystem extends IPSModuleStrict
{
    use PiHoleClient;
    use PiHoleTile;

    private const PERCENT = ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'SUFFIX' => ' %', 'DIGITS' => 1];
    private const COUNT = ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'DIGITS' => 0];
    private const TEXT = ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION];
    private const DURATION = ['PRESENTATION' => VARIABLE_PRESENTATION_DURATION];

    /** Schnelle Werte: Ident => [Name, Typ, Endpunkt, Pfad, Darstellung] */
    private const FAST = [
        'Uptime'         => ['System uptime', VARIABLETYPE_INTEGER, 'info/system', 'system.uptime', self::DURATION],
        'RamUsed'        => ['RAM used', VARIABLETYPE_FLOAT, 'info/system', 'system.memory.ram.%used', self::PERCENT],
        'SwapUsed'       => ['Swap used', VARIABLETYPE_FLOAT, 'info/system', 'system.memory.swap.%used', self::PERCENT],
        'Processes'      => ['Processes', VARIABLETYPE_INTEGER, 'info/system', 'system.procs', self::COUNT],
        'CpuUsage'       => ['CPU usage', VARIABLETYPE_FLOAT, 'info/system', 'system.cpu.%cpu', self::PERCENT],
        'FtlCpu'         => ['FTL CPU usage', VARIABLETYPE_FLOAT, 'info/system', 'system.ftl.%cpu', self::PERCENT],
        'FtlMemory'      => ['FTL memory usage', VARIABLETYPE_FLOAT, 'info/system', 'system.ftl.%mem', self::PERCENT],
        'CpuTemperature' => ['CPU temperature', VARIABLETYPE_FLOAT, 'info/sensors', 'sensors.cpu_temp', ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'SUFFIX' => ' °C', 'DIGITS' => 1]],
        'FtlUptime'      => ['FTL uptime', VARIABLETYPE_INTEGER, 'info/ftl', 'ftl.uptime', self::DURATION],
        'PrivacyLevel'   => ['Privacy level', VARIABLETYPE_INTEGER, 'info/ftl', 'ftl.privacy_level', self::COUNT],
        'MessageCount'   => ['Diagnosis messages', VARIABLETYPE_INTEGER, 'info/messages/count', 'count', self::COUNT]
    ];

    /** Langsame Werte */
    private const SLOW = [
        'CoreVersion'     => ['Core version', VARIABLETYPE_STRING, 'info/version', 'version.core.local.version', self::TEXT],
        'WebVersion'      => ['Web version', VARIABLETYPE_STRING, 'info/version', 'version.web.local.version', self::TEXT],
        'FtlVersion'      => ['FTL version', VARIABLETYPE_STRING, 'info/version', 'version.ftl.local.version', self::TEXT],
        'DatabaseSize'    => ['Database size', VARIABLETYPE_FLOAT, 'info/database', 'size', ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'SUFFIX' => ' MB', 'DIGITS' => 1]],
        'DatabaseQueries' => ['Queries in database', VARIABLETYPE_INTEGER, 'info/database', 'queries', self::COUNT],
        'HostModel'       => ['Host model', VARIABLETYPE_STRING, 'info/host', 'host.model', self::TEXT]
    ];

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterMessage($this->InstanceID, FM_CONNECT);

        $this->RegisterPropertyInteger('UpdateInterval', 60);
        $this->RegisterPropertyInteger('SlowUpdateInterval', 3600);

        $this->RegisterTimer('Update', 0, 'PIHSYS_Update($_IPS[\'TARGET\']);');
        $this->RegisterTimer('UpdateSlow', 0, 'PIHSYS_UpdateSlow($_IPS[\'TARGET\']);');
        $this->SetVisualizationType(1);
    }

    /**
     * Mit IPSModuleStrict gibt es kein ConnectParent: Die Verwaltungskonsole schlägt den Splitter vor,
     * einen vorhandenen oder beim Anlegen einen neuen.
     */
    public function GetCompatibleParents(): string
    {
        return json_encode(['type' => 'connect', 'moduleIDs' => ['{7F1B108F-2DCE-496E-8BAE-8103685BF11E}']]);
    }

    public function ApplyChanges(): void
    {
        //Never delete this line!
        parent::ApplyChanges();

        $position = 1;
        foreach ([...self::FAST, ...self::SLOW] as $ident => [$name, $type, , , $presentation]) {
            $this->MaintainVariable($ident, $this->Translate($name), $type, $presentation, $position++, true);
        }
        $this->MaintainVariable('UpdateAvailable', $this->Translate('Update available'), VARIABLETYPE_BOOLEAN, ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION], $position, true);

        $this->UpdateInterval('Update', 'UpdateInterval');
        $this->UpdateInterval('UpdateSlow', 'SlowUpdateInterval');
        if ($this->CanReadNow()) {
            $this->Update();
            $this->UpdateSlow();
        }
    }

    /** Liest System-, Sensor-, FTL-Werte und die Anzahl der Meldungen. */
    public function Update(): void
    {
        $this->Refresh(self::FAST);
        $this->PushTile($this->TileData());
    }

    /** Liest Versionen, Datenbank- und Hostdaten. */
    public function UpdateSlow(): void
    {
        $this->Refresh(self::SLOW);

        $version = $this->ApiData('GET', 'info/version');
        if ($version !== null) {
            $available = false;
            foreach (['core', 'web', 'ftl'] as $component) {
                $local = $this->Pick($version, "version.$component.local.version");
                $remote = $this->Pick($version, "version.$component.remote.version");
                if (is_string($local) && is_string($remote) && $local !== '' && $remote !== '' && $local !== $remote) {
                    $available = true;
                }
            }
            $this->SetValue('UpdateAvailable', $available);
        }
        $this->PushTile($this->TileData());
    }

    public function GetVisualizationTile(): string
    {
        return $this->RenderTile(__DIR__, $this->TileData());
    }

    /** Systeminformationen (JSON). */
    public function GetSystem(): string
    {
        return $this->ApiJson('GET', 'info/system');
    }

    /** Hostinformationen (JSON). */
    public function GetHost(): string
    {
        return $this->ApiJson('GET', 'info/host');
    }

    /** FTL-Informationen (JSON). */
    public function GetFtl(): string
    {
        return $this->ApiJson('GET', 'info/ftl');
    }

    /** Temperatursensoren (JSON). */
    public function GetSensors(): string
    {
        return $this->ApiJson('GET', 'info/sensors');
    }

    /** Informationen zur Langzeit-Datenbank (JSON). */
    public function GetDatabase(): string
    {
        return $this->ApiJson('GET', 'info/database');
    }

    /** Lokale und verfügbare Versionen (JSON). */
    public function GetVersion(): string
    {
        return $this->ApiJson('GET', 'info/version');
    }

    /** DNS- und DHCP-Metriken (JSON). */
    public function GetMetrics(): string
    {
        return $this->ApiJson('GET', 'info/metrics');
    }

    /** Diagnosemeldungen (JSON). */
    public function GetMessages(): string
    {
        return $this->ApiJson('GET', 'info/messages');
    }

    /** Anzahl der Diagnosemeldungen (JSON). */
    public function GetMessageCount(): string
    {
        return $this->ApiJson('GET', 'info/messages/count');
    }

    /**
     * Löscht Diagnosemeldungen.
     *
     * @param string $IDs Nummern, mehrere durch Komma getrennt, zum Beispiel "1,2,3"
     */
    public function DeleteMessages(string $IDs): string
    {
        $ids = preg_replace('/[^0-9,]/', '', $IDs);

        return $ids === '' ? json_encode(['error' => ['key' => 'bad_request', 'message' => 'no valid IDs']]) : $this->ApiJson('DELETE', 'info/messages/' . $ids);
    }

    /** Informationen zum anfragenden Client (JSON). */
    public function GetClientInfo(): string
    {
        return $this->ApiJson('GET', 'info/client');
    }

    /** Informationen der Anmeldeseite (JSON). */
    public function GetLoginInfo(): string
    {
        return $this->ApiJson('GET', 'info/login');
    }

    /** Verfügbare API-Endpunkte (JSON). */
    public function GetEndpoints(): string
    {
        return $this->ApiJson('GET', 'endpoints');
    }

    /**
     * Log des DNS-Servers.
     *
     * @param int $NextID Es werden nur Zeilen ab dieser Nummer geliefert, 0 = alle. Die Antwort enthält die nächste Nummer.
     */
    public function GetDNSLog(int $NextID = 0): string
    {
        return $this->ApiJson('GET', 'logs/dnsmasq', $NextID > 0 ? ['nextID' => $NextID] : []);
    }

    /** Log von FTL, siehe GetDNSLog (JSON). */
    public function GetFTLLog(int $NextID = 0): string
    {
        return $this->ApiJson('GET', 'logs/ftl', $NextID > 0 ? ['nextID' => $NextID] : []);
    }

    /** Log des Webservers, siehe GetDNSLog (JSON). */
    public function GetWebserverLog(int $NextID = 0): string
    {
        return $this->ApiJson('GET', 'logs/webserver', $NextID > 0 ? ['nextID' => $NextID] : []);
    }

    private function TileData(): array
    {
        // Ohne gelesenen Wert zeigt die Kachel einen Strich statt 0
        $temp = IPS_GetVariable($this->GetIDForIdent('CpuTemperature'))['VariableUpdated'] > 0 ? $this->GetValue('CpuTemperature') : null;

        return [
            'connected' => $this->HasActiveParent(),
            'cpu'       => $this->GetValue('CpuUsage'),
            'ram'       => $this->GetValue('RamUsed'),
            'temp'      => $temp,
            'uptime'    => $this->GetValue('Uptime'),
            'core'      => $this->GetValue('CoreVersion'),
            'web'       => $this->GetValue('WebVersion'),
            'ftl'       => $this->GetValue('FtlVersion'),
            'update'    => $this->GetValue('UpdateAvailable'),
            'messages'  => $this->GetValue('MessageCount'),
            't'         => [
                'offline'         => $this->Translate('Pi-hole not reachable'),
                'cpu'             => $this->Translate('CPU'),
                'ram'             => $this->Translate('RAM'),
                'temp'            => $this->Translate('Temperature'),
                'uptime'          => $this->Translate('Uptime'),
                'version'         => $this->Translate('Version'),
                'updateAvailable' => $this->Translate('Update available'),
                'messages'        => $this->Translate('messages')
            ]
        ];
    }
    /**
     * Liest die Endpunkte der Tabelle und schreibt die Variablen.
     */
    private function Refresh(array $definition): void
    {
        $cache = [];
        foreach ($definition as $ident => [, $type, $endpoint, $path]) {
            $cache[$endpoint] ??= $this->ApiData('GET', $endpoint);
            $data = $cache[$endpoint];
            if ($data === null) {
                continue;
            }
            $value = $this->Pick($data, $path);
            // info/database liefert je nach Version die Felder mit oder ohne Schlüssel "database"
            $value ??= $this->Pick($data, 'database.' . $path);
            // Ohne Modellangabe (kein Raspberry Pi) das Mainboard verwenden
            if ($ident === 'HostModel') {
                $value ??= $this->Pick($data, 'host.dmi.board.name');
            }
            if ($value === null) {
                continue;
            }
            switch ($type) {
                case VARIABLETYPE_INTEGER:
                    $this->SetValue($ident, (int) round((float) $value));
                    break;
                case VARIABLETYPE_FLOAT:
                    $this->SetValue($ident, $ident === 'DatabaseSize' ? round((float) $value / 1048576, 1) : (float) $value);
                    break;
                default:
                    $this->SetValue($ident, (string) $value);
            }
        }
    }
}
