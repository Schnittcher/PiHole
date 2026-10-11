<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/PiHoleClient.php';
require_once __DIR__ . '/../libs/PiHoleTile.php';

/**
 * Netzwerkinformationen und DHCP-Leases (Endpunkte network, dhcp).
 */
class PiHoleNetwork extends IPSModuleStrict
{
    use PiHoleClient;
    use PiHoleTile;

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterMessage($this->InstanceID, FM_CONNECT);

        $this->RegisterPropertyInteger('UpdateInterval', 300);

        $this->RegisterVariableInteger('DeviceCount', $this->Translate('Known devices'), ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'DIGITS' => 0], 1);
        $this->RegisterVariableInteger('LeaseCount', $this->Translate('DHCP leases'), ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'DIGITS' => 0], 2);
        $this->RegisterVariableString('Gateway', $this->Translate('Gateway'), ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION], 3);

        $this->RegisterTimer('Update', 0, 'PIHNET_Update($_IPS[\'TARGET\']);');
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

        $this->UpdateInterval('Update', 'UpdateInterval');
        if ($this->CanReadNow()) {
            $this->Update();
        }
    }

    /** Liest Geräte, DHCP-Leases und Gateway. */
    public function Update(): void
    {
        // Zum Zählen alle Geräte laden, je Gerät genügt eine Adresse
        $devices = $this->ApiData('GET', 'network/devices', ['max_devices' => 1000, 'max_addresses' => 1]);
        if ($devices !== null) {
            $this->SetValue('DeviceCount', count((array) ($devices['devices'] ?? [])));
        }
        $leases = $this->ApiData('GET', 'dhcp/leases');
        if ($leases !== null) {
            $this->SetValue('LeaseCount', count((array) ($leases['leases'] ?? [])));
        }
        $gateway = $this->ApiData('GET', 'network/gateway');
        if ($gateway !== null) {
            foreach ((array) ($gateway['gateway'] ?? []) as $entry) {
                if (($entry['family'] ?? '') === 'inet' && isset($entry['address'])) {
                    $this->SetValue('Gateway', (string) $entry['address']);
                    break;
                }
            }
        }
        $this->PushTile($this->TileData());
    }

    public function GetVisualizationTile(): string
    {
        return $this->RenderTile(__DIR__, $this->TileData());
    }

    /**
     * Im Netzwerk gesehene Geräte (JSON).
     *
     * @param int $MaxDevices   Höchstzahl der Geräte, 0 = Standard des Pi-holes
     * @param int $MaxAddresses Höchstzahl der Adressen je Gerät, 0 = Standard des Pi-holes
     */
    public function GetDevices(int $MaxDevices = 0, int $MaxAddresses = 0): string
    {
        $query = [];
        if ($MaxDevices > 0) {
            $query['max_devices'] = $MaxDevices;
        }
        if ($MaxAddresses > 0) {
            $query['max_addresses'] = $MaxAddresses;
        }

        return $this->ApiJson('GET', 'network/devices', $query);
    }

    /** Löscht ein Gerät aus der Netzwerktabelle anhand seiner Nummer. */
    public function DeleteDevice(int $ID): string
    {
        return $this->ApiJson('DELETE', 'network/devices/' . $ID);
    }

    /** Standardgateway (JSON). */
    public function GetGateway(bool $Detailed = false): string
    {
        return $this->ApiJson('GET', 'network/gateway', ['detailed' => $Detailed]);
    }

    /** Routing-Tabelle des Pi-holes (JSON). */
    public function GetRoutes(bool $Detailed = false): string
    {
        return $this->ApiJson('GET', 'network/routes', ['detailed' => $Detailed]);
    }

    /** Netzwerkschnittstellen des Pi-holes (JSON). */
    public function GetInterfaces(bool $Detailed = false): string
    {
        return $this->ApiJson('GET', 'network/interfaces', ['detailed' => $Detailed]);
    }

    /** Aktive DHCP-Leases (JSON). */
    public function GetLeases(): string
    {
        return $this->ApiJson('GET', 'dhcp/leases');
    }

    /** Beendet das DHCP-Lease einer IP-Adresse. */
    public function DeleteLease(string $IP): string
    {
        return $this->ApiJson('DELETE', 'dhcp/leases/' . rawurlencode($IP));
    }

    private function TileData(): array
    {
        return [
            'connected' => $this->HasActiveParent(),
            'devices'   => $this->GetValue('DeviceCount'),
            'leases'    => $this->GetValue('LeaseCount'),
            'gateway'   => $this->GetValue('Gateway'),
            't'         => [
                'offline' => $this->Translate('Pi-hole not reachable'),
                'devices' => $this->Translate('Known devices'),
                'leases'  => $this->Translate('DHCP leases'),
                'gateway' => $this->Translate('Gateway')
            ]
        ];
    }
}
