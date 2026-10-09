<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/PiHoleClient.php';
require_once __DIR__ . '/../libs/PiHoleTile.php';

/**
 * Verwaltung von Domains, Gruppen, Clients und Listen (Endpunkte domains, groups, clients, lists, search).
 */
class PiHoleFilters extends IPSModuleStrict
{
    use PiHoleClient;
    use PiHoleTile;

    private const COUNT = ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'DIGITS' => 0];

    /** Anzahlen aus info/ftl: Ident => [Name, Pfad] */
    private const COUNTS = [
        'Lists'         => ['Blocklists', 'ftl.database.lists'],
        'Groups'        => ['Groups', 'ftl.database.groups'],
        'Clients'       => ['Clients', 'ftl.database.clients'],
        'AllowedExact'  => ['Allowed domains (exact)', 'ftl.database.domains.allowed.total'],
        'DeniedExact'   => ['Denied domains (exact)', 'ftl.database.domains.denied.total'],
        'AllowedRegex'  => ['Allowed domains (regex)', 'ftl.database.regex.allowed.total'],
        'DeniedRegex'   => ['Denied domains (regex)', 'ftl.database.regex.denied.total']
    ];

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        $this->ConnectParent('{7F1B108F-2DCE-496E-8BAE-8103685BF11E}');
        $this->RegisterMessage($this->InstanceID, FM_CONNECT);

        $this->RegisterPropertyInteger('UpdateInterval', 300);

        $position = 1;
        foreach (self::COUNTS as $ident => [$name]) {
            $this->RegisterVariableInteger($ident, $this->Translate($name), self::COUNT, $position++);
        }

        $this->RegisterTimer('Update', 0, 'PIHFILTER_Update($_IPS[\'TARGET\']);');
        $this->SetVisualizationType(1);
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

    /** Liest die Anzahl der Einträge je Bereich. */
    public function Update(): void
    {
        $data = $this->ApiData('GET', 'info/ftl');
        if ($data === null) {
            return;
        }
        foreach (self::COUNTS as $ident => [, $path]) {
            $value = $this->Pick($data, $path);
            if ($value !== null) {
                $this->SetValue($ident, (int) $value);
            }
        }
        $this->PushTile($this->TileData());
    }

    public function GetVisualizationTile(): string
    {
        return $this->RenderTile(__DIR__, $this->TileData());
    }

    // ── Domains ─────────────────────────────────────────────────────────────

    /**
     * Domains der Allow- und Denylist.
     *
     * @param string $Type   allow oder deny, leer = beide
     * @param string $Kind   exact oder regex, leer = beide (nur zusammen mit $Type)
     * @param string $Domain Einzelne Domain, leer = alle (nur zusammen mit $Type und $Kind)
     */
    public function GetDomains(string $Type = '', string $Kind = '', string $Domain = ''): string
    {
        return $this->ApiJson('GET', $this->DomainPath($Type, $Kind, $Domain));
    }

    /**
     * Fügt eine Domain hinzu.
     *
     * @param string $Type   allow oder deny
     * @param string $Kind   exact oder regex
     * @param string $Groups Gruppennummern als JSON-Array, zum Beispiel [0]
     */
    public function AddDomain(string $Type, string $Kind, string $Domain, string $Comment = '', string $Groups = '[0]', bool $Enabled = true): string
    {
        $body = ['domain' => $Domain, 'comment' => $Comment === '' ? null : $Comment, 'groups' => $this->DecodeParameter($Groups) ?? [0], 'enabled' => $Enabled];

        return $this->ApiJson('POST', $this->DomainPath($Type, $Kind), [], $body);
    }

    /**
     * Ändert eine Domain. Mit $NewType und $NewKind lässt sie sich in eine andere Liste verschieben.
     */
    public function UpdateDomain(string $Type, string $Kind, string $Domain, string $Comment = '', string $Groups = '[0]', bool $Enabled = true, string $NewType = '', string $NewKind = ''): string
    {
        $body = ['comment' => $Comment === '' ? null : $Comment, 'groups' => $this->DecodeParameter($Groups) ?? [0], 'enabled' => $Enabled];
        if ($NewType !== '') {
            $body['type'] = $NewType;
        }
        if ($NewKind !== '') {
            $body['kind'] = $NewKind;
        }

        return $this->ApiJson('PUT', $this->DomainPath($Type, $Kind, $Domain), [], $body);
    }

    /** Löscht eine Domain. */
    public function DeleteDomain(string $Type, string $Kind, string $Domain): string
    {
        return $this->ApiJson('DELETE', $this->DomainPath($Type, $Kind, $Domain));
    }

    /**
     * Löscht mehrere Domains.
     *
     * @param string $Items JSON-Array mit Einträgen wie {"item":"example.com","type":"deny","kind":"exact"}
     */
    public function DeleteDomains(string $Items): string
    {
        $items = $this->DecodeParameter($Items);

        return $items === null ? $this->BadRequest() : $this->ApiJson('POST', 'domains:batchDelete', [], $items);
    }

    // ── Gruppen ─────────────────────────────────────────────────────────────

    /** Alle Gruppen oder eine einzelne (JSON). */
    public function GetGroups(string $Name = ''): string
    {
        return $this->ApiJson('GET', $Name === '' ? 'groups' : 'groups/' . rawurlencode($Name));
    }

    /** Legt eine Gruppe an. */
    public function AddGroup(string $Name, string $Comment = '', bool $Enabled = true): string
    {
        return $this->ApiJson('POST', 'groups', [], ['name' => $Name, 'comment' => $Comment === '' ? null : $Comment, 'enabled' => $Enabled]);
    }

    /** Ändert eine Gruppe. Mit $NewName wird sie umbenannt. */
    public function UpdateGroup(string $Name, string $Comment = '', bool $Enabled = true, string $NewName = ''): string
    {
        $body = ['name' => $NewName === '' ? $Name : $NewName, 'comment' => $Comment === '' ? null : $Comment, 'enabled' => $Enabled];

        return $this->ApiJson('PUT', 'groups/' . rawurlencode($Name), [], $body);
    }

    /** Löscht eine Gruppe. */
    public function DeleteGroup(string $Name): string
    {
        return $this->ApiJson('DELETE', 'groups/' . rawurlencode($Name));
    }

    /**
     * Löscht mehrere Gruppen.
     *
     * @param string $Items JSON-Array mit Einträgen wie {"item":"Gruppenname"}
     */
    public function DeleteGroups(string $Items): string
    {
        $items = $this->DecodeParameter($Items);

        return $items === null ? $this->BadRequest() : $this->ApiJson('POST', 'groups:batchDelete', [], $items);
    }

    // ── Clients ─────────────────────────────────────────────────────────────

    /** Alle Clients oder einen einzelnen (IP, MAC, Hostname oder Schnittstelle) (JSON). */
    public function GetClients(string $Client = ''): string
    {
        return $this->ApiJson('GET', $Client === '' ? 'clients' : 'clients/' . rawurlencode($Client));
    }

    /** Clients, die der Pi-hole kennt, aber noch nicht in einer Gruppe verwaltet (JSON). */
    public function GetClientSuggestions(): string
    {
        return $this->ApiJson('GET', 'clients/_suggestions');
    }

    /**
     * Legt einen Client an.
     *
     * @param string $Groups Gruppennummern als JSON-Array, zum Beispiel [0]
     */
    public function AddClient(string $Client, string $Comment = '', string $Groups = '[0]'): string
    {
        return $this->ApiJson('POST', 'clients', [], ['client' => $Client, 'comment' => $Comment === '' ? null : $Comment, 'groups' => $this->DecodeParameter($Groups) ?? [0]]);
    }

    /** Ändert Kommentar und Gruppen eines Clients. */
    public function UpdateClient(string $Client, string $Comment = '', string $Groups = '[0]'): string
    {
        return $this->ApiJson('PUT', 'clients/' . rawurlencode($Client), [], ['comment' => $Comment === '' ? null : $Comment, 'groups' => $this->DecodeParameter($Groups) ?? [0]]);
    }

    /** Löscht einen Client. */
    public function DeleteClient(string $Client): string
    {
        return $this->ApiJson('DELETE', 'clients/' . rawurlencode($Client));
    }

    /**
     * Löscht mehrere Clients.
     *
     * @param string $Items JSON-Array mit Einträgen wie {"item":"192.0.2.10"}
     */
    public function DeleteClients(string $Items): string
    {
        $items = $this->DecodeParameter($Items);

        return $items === null ? $this->BadRequest() : $this->ApiJson('POST', 'clients:batchDelete', [], $items);
    }

    // ── Listen ──────────────────────────────────────────────────────────────

    /**
     * Abonnierte Block- und Allowlisten.
     *
     * @param string $Address Einzelne Liste anhand ihrer Adresse, leer = alle
     * @param string $Type    allow oder block, leer = beide
     */
    public function GetLists(string $Address = '', string $Type = ''): string
    {
        return $this->ApiJson('GET', $Address === '' ? 'lists' : 'lists/' . rawurlencode($Address), $Type === '' ? [] : ['type' => $Type]);
    }

    /**
     * Fügt eine Liste hinzu. Danach die Gravity aktualisieren (Instanz "Pi-hole Maintenance").
     *
     * @param string $Type   allow oder block
     * @param string $Groups Gruppennummern als JSON-Array, zum Beispiel [0]
     */
    public function AddList(string $Address, string $Type = 'block', string $Comment = '', string $Groups = '[0]', bool $Enabled = true): string
    {
        $body = ['address' => $Address, 'comment' => $Comment === '' ? null : $Comment, 'groups' => $this->DecodeParameter($Groups) ?? [0], 'enabled' => $Enabled];

        return $this->ApiJson('POST', 'lists', ['type' => $Type], $body);
    }

    /** Ändert eine Liste. */
    public function UpdateList(string $Address, string $Type = 'block', string $Comment = '', string $Groups = '[0]', bool $Enabled = true): string
    {
        $body = ['type' => $Type, 'comment' => $Comment === '' ? null : $Comment, 'groups' => $this->DecodeParameter($Groups) ?? [0], 'enabled' => $Enabled];

        return $this->ApiJson('PUT', 'lists/' . rawurlencode($Address), ['type' => $Type], $body);
    }

    /** Löscht eine Liste. */
    public function DeleteList(string $Address, string $Type = 'block'): string
    {
        return $this->ApiJson('DELETE', 'lists/' . rawurlencode($Address), ['type' => $Type]);
    }

    /**
     * Löscht mehrere Listen.
     *
     * @param string $Items JSON-Array mit Einträgen wie {"item":"https://example.com/list.txt","type":"block"}
     */
    public function DeleteLists(string $Items): string
    {
        $items = $this->DecodeParameter($Items);

        return $items === null ? $this->BadRequest() : $this->ApiJson('POST', 'lists:batchDelete', [], $items);
    }

    // ── Suche ───────────────────────────────────────────────────────────────

    /**
     * Sucht eine Domain in den Listen des Pi-holes.
     *
     * @param bool $Partial true = auch Teiltreffer
     * @param int  $Limit   Höchstzahl der Treffer
     */
    public function Search(string $Domain, bool $Partial = false, int $Limit = 20): string
    {
        return $this->ApiJson('GET', 'search/' . rawurlencode($Domain), ['partial' => $Partial, 'N' => $Limit]);
    }

    private function DomainPath(string $Type, string $Kind, string $Domain = ''): string
    {
        $path = 'domains';
        foreach ([$Type, $Kind, $Domain] as $part) {
            if ($part === '') {
                break;
            }
            $path .= '/' . rawurlencode($part);
        }

        return $path;
    }

    private function BadRequest(): string
    {
        return json_encode(['error' => ['key' => 'bad_request', 'message' => 'invalid JSON parameter']]);
    }

    private function TileData(): array
    {
        return [
            'connected' => $this->HasActiveParent(),
            'lists'     => $this->GetValue('Lists'),
            'groups'    => $this->GetValue('Groups'),
            'clients'   => $this->GetValue('Clients'),
            'allowed'   => $this->GetValue('AllowedExact') + $this->GetValue('AllowedRegex'),
            'denied'    => $this->GetValue('DeniedExact') + $this->GetValue('DeniedRegex'),
            't'         => [
                'offline' => $this->Translate('Pi-hole not reachable'),
                'lists'   => $this->Translate('Blocklists'),
                'groups'  => $this->Translate('Groups'),
                'clients' => $this->Translate('Clients'),
                'allowed' => $this->Translate('Allowed domains'),
                'denied'  => $this->Translate('Denied domains')
            ]
        ];
    }
}
