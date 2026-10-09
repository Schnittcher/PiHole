<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/PiHoleClient.php';
require_once __DIR__ . '/../libs/PiHoleTile.php';

/**
 * Wartungsaktionen des Pi-holes (Endpunkte action und teleporter).
 */
class PiHoleMaintenance extends IPSModuleStrict
{
    use PiHoleClient;
    use PiHoleTile;

    /** Aktion => [Endpunkt, Beschriftung] */
    private const ACTIONS = [
        1 => ['action/gravity', 'Update gravity'],
        2 => ['action/restartdns', 'Restart DNS'],
        3 => ['action/flush/logs', 'Flush logs'],
        4 => ['action/flush/arp', 'Flush ARP cache'],
        5 => ['action/flush/network', 'Flush network table']
    ];

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        $this->ConnectParent('{7F1B108F-2DCE-496E-8BAE-8103685BF11E}');

        $this->RegisterVariableInteger('Action', $this->Translate('Action'), $this->ActionPresentation(), 1);
        $this->EnableAction('Action');
        $this->RegisterVariableString('LastResult', $this->Translate('Last result'), ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION], 2);
        $this->SetVisualizationType(1);
        $this->RegisterVariableInteger('LastRun', $this->Translate('Last run'), ['PRESENTATION' => VARIABLE_PRESENTATION_DATE_TIME, 'DATE' => 1, 'MONTH_TEXT' => false, 'DAY_OF_THE_WEEK' => false, 'TIME' => 2], 3);
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        if ($Ident === 'Action' && isset(self::ACTIONS[(int) $Value])) {
            $this->Run((int) $Value);
        }
    }

    public function GetVisualizationTile(): string
    {
        return $this->RenderTile(__DIR__, $this->TileData(0));
    }

    /** Aktualisiert die Gravity-Blocklisten. Das kann einige Minuten dauern. Liefert die Ausgabe des Pi-holes. */
    public function UpdateGravity(): string
    {
        return $this->Run(1);
    }

    /** Startet den DNS-Server neu. */
    public function RestartDNS(): string
    {
        return $this->Run(2);
    }

    /** Leert die Logdateien. Das ist nur möglich, wenn der Pi-hole destruktive Aktionen erlaubt. */
    public function FlushLogs(): string
    {
        return $this->Run(3);
    }

    /** Leert den ARP-Cache. */
    public function FlushARP(): string
    {
        return $this->Run(4);
    }

    /** Leert die Netzwerktabelle. Das ist nur möglich, wenn der Pi-hole destruktive Aktionen erlaubt. */
    public function FlushNetwork(): string
    {
        return $this->Run(5);
    }

    /**
     * Speichert ein Teleporter-Backup als Datei.
     *
     * @param string $Path Vollständiger Dateiname der ZIP-Datei, der Ordner muss existieren
     */
    public function ExportTeleporter(string $Path): bool
    {
        if ($Path === '' || !is_dir(dirname($Path))) {
            $this->SendDebug(__FUNCTION__, 'invalid path', 0);

            return false;
        }
        $result = $this->Api('GET', 'teleporter', [], null, true, 120);
        if ($result['Code'] !== 200 || $result['Base64'] === '') {
            return false;
        }

        return file_put_contents($Path, base64_decode($result['Base64'])) !== false;
    }

    /**
     * Spielt ein Teleporter-Backup ein. Vorhandene Daten werden überschrieben.
     *
     * @param string $Path    Dateiname der ZIP-Datei
     * @param string $Options Optionen als JSON, zum Beispiel {"config":true,"dhcp_leases":true,"gravity":{"group":true,"adlist":true}}, leer = alles
     */
    public function ImportTeleporter(string $Path, string $Options = ''): string
    {
        if (!is_file($Path)) {
            return json_encode(['error' => ['key' => 'file_not_found', 'message' => 'File not found']]);
        }
        $fields = [];
        if ($Options !== '') {
            $fields['import'] = $Options;
        }
        $body = ['_multipart' => ['name' => basename($Path), 'base64' => base64_encode(file_get_contents($Path)), 'fields' => $fields]];

        return $this->ApiJson('POST', 'teleporter', [], $body);
    }

    private function Run(int $action): string
    {
        [$endpoint, $caption] = self::ACTIONS[$action];
        // Die Kachel zeigt die laufende Aktion, Gravity kann Minuten dauern
        $this->PushTile($this->TileData($action));
        $result = $this->Api('POST', $endpoint, [], null, false, $action === 1 ? 900 : 60);
        $ok = $result['Code'] >= 200 && $result['Code'] < 300;
        $text = $result['Text'] !== '' ? $result['Text'] : json_encode($result['Data'], JSON_UNESCAPED_UNICODE);
        $this->SetValue('LastRun', time());
        $this->SetValue('LastResult', sprintf('%s: %s', $this->Translate($caption), $ok ? $this->Translate('done') : sprintf('HTTP %d', $result['Code'])));
        $this->PushTile($this->TileData(0));

        return (string) $text;
    }

    /**
     * @param int $busy Nummer der laufenden Aktion, 0 = keine
     */
    private function TileData(int $busy): array
    {
        return [
            'connected'  => $this->HasActiveParent(),
            'busy'       => $busy,
            'lastRun'    => $this->GetValue('LastRun'),
            'lastResult' => $this->GetValue('LastResult'),
            't'          => [
                'offline' => $this->Translate('Pi-hole not reachable'),
                'gravity' => $this->Translate('Update gravity'),
                'restart' => $this->Translate('Restart DNS'),
                'arp'     => $this->Translate('Flush ARP cache'),
                'running' => $this->Translate('running'),
                'lastRun' => $this->Translate('Last run'),
                'never'   => $this->Translate('Not run yet')
            ]
        ];
    }
    private function ActionPresentation(): array
    {
        $options = [];
        foreach (self::ACTIONS as $value => [, $caption]) {
            $options[] = ['Value' => $value, 'Caption' => $this->Translate($caption), 'IconActive' => false, 'IconValue' => '', 'Color' => -1];
        }

        return ['PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION, 'OPTIONS' => json_encode($options)];
    }
}
