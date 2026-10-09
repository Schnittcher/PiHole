<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/PiHoleClient.php';
require_once __DIR__ . '/../libs/PiHoleTile.php';

/**
 * Statistik und Verlauf des Pi-holes (Endpunkte stats, history, queries).
 */
class PiHoleStatistics extends IPSModuleStrict
{
    use PiHoleClient;
    use PiHoleTile;

    private const COUNT_PRESENTATION = ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'DIGITS' => 0];

    /** Variablen aus stats/summary: Ident => [Name, Typ, Pfad, Darstellung] */
    private const SUMMARY = [
        'Total'             => ['DNS queries', VARIABLETYPE_INTEGER, 'queries.total', self::COUNT_PRESENTATION],
        'Blocked'           => ['Queries blocked', VARIABLETYPE_INTEGER, 'queries.blocked', self::COUNT_PRESENTATION],
        'PercentBlocked'    => ['Percent blocked', VARIABLETYPE_FLOAT, 'queries.percent_blocked', ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'SUFFIX' => ' %', 'DIGITS' => 1]],
        'UniqueDomains'     => ['Unique domains', VARIABLETYPE_INTEGER, 'queries.unique_domains', self::COUNT_PRESENTATION],
        'Forwarded'         => ['Queries forwarded', VARIABLETYPE_INTEGER, 'queries.forwarded', self::COUNT_PRESENTATION],
        'Cached'            => ['Queries cached', VARIABLETYPE_INTEGER, 'queries.cached', self::COUNT_PRESENTATION],
        'Frequency'         => ['Queries per second', VARIABLETYPE_FLOAT, 'queries.frequency', ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'SUFFIX' => ' /s', 'DIGITS' => 2]],
        'ClientsActive'     => ['Active clients', VARIABLETYPE_INTEGER, 'clients.active', self::COUNT_PRESENTATION],
        'ClientsTotal'      => ['Clients total', VARIABLETYPE_INTEGER, 'clients.total', self::COUNT_PRESENTATION],
        'GravityDomains'    => ['Domains on blocklists', VARIABLETYPE_INTEGER, 'gravity.domains_being_blocked', self::COUNT_PRESENTATION],
        'GravityLastUpdate' => ['Gravity last updated', VARIABLETYPE_INTEGER, 'gravity.last_update', ['PRESENTATION' => VARIABLE_PRESENTATION_DATE_TIME, 'DATE' => 1, 'MONTH_TEXT' => false, 'DAY_OF_THE_WEEK' => false, 'TIME' => 2]]
    ];

    /** Optionale Gruppen aus stats/summary: Property => [Ident-Präfix, Pfad, Namensteil] */
    private const GROUPS = [
        'ShowQueryTypes'  => ['Type', 'queries.types', 'Query type'],
        'ShowQueryStatus' => ['Status', 'queries.status', 'Query status'],
        'ShowReplies'     => ['Reply', 'queries.replies', 'Reply type']
    ];

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        $this->ConnectParent('{7F1B108F-2DCE-496E-8BAE-8103685BF11E}');
        $this->RegisterMessage($this->InstanceID, FM_CONNECT);

        $this->RegisterPropertyInteger('UpdateInterval', 30);
        $this->RegisterPropertyBoolean('ShowQueryTypes', false);
        $this->RegisterPropertyBoolean('ShowQueryStatus', false);
        $this->RegisterPropertyBoolean('ShowReplies', false);

        // Verlauf und Top-Listen der Kachel, werden nur alle paar Minuten gelesen
        $this->RegisterAttributeString('TileExtra', '');
        $this->RegisterAttributeInteger('TileExtraTime', 0);

        $this->RegisterTimer('Update', 0, 'PIHSTAT_Update($_IPS[\'TARGET\']);');
        $this->SetVisualizationType(1);
    }

    public function ApplyChanges(): void
    {
        //Never delete this line!
        parent::ApplyChanges();

        $position = 1;
        foreach (self::SUMMARY as $ident => [$name, $type, , $presentation]) {
            $this->MaintainVariable($ident, $this->Translate($name), $type, $presentation, $position++, true);
        }
        // Optionale Gruppen: abgewählte Gruppen werden entfernt, gewählte beim Lesen angelegt (die Schlüssel kommen aus der API)
        foreach (self::GROUPS as $property => [$prefix]) {
            if (!$this->ReadPropertyBoolean($property)) {
                $this->RemoveGroup($prefix);
            }
        }

        $this->UpdateInterval('Update', 'UpdateInterval');
        if ($this->CanReadNow()) {
            $this->Update();
        }
    }

    /**
     * Liest die Zusammenfassung vom Pi-hole und aktualisiert die Variablen.
     */
    public function Update(): void
    {
        $data = $this->ApiData('GET', 'stats/summary');
        if ($data === null) {
            return;
        }
        foreach (self::SUMMARY as $ident => [, $type, $path]) {
            $value = $this->Pick($data, $path);
            if ($value === null) {
                continue;
            }
            $this->SetValue($ident, $type === VARIABLETYPE_FLOAT ? (float) $value : (int) $value);
        }
        $position = 100;
        foreach (self::GROUPS as $property => [$prefix, $path, $name]) {
            if (!$this->ReadPropertyBoolean($property)) {
                continue;
            }
            foreach ((array) $this->Pick($data, $path, []) as $key => $count) {
                $ident = $prefix . '_' . preg_replace('/[^A-Za-z0-9_]/', '_', (string) $key);
                $this->MaintainVariable($ident, $this->Translate($name) . ' ' . $key, VARIABLETYPE_INTEGER, self::COUNT_PRESENTATION, $position++, true);
                $this->SetValue($ident, (int) $count);
            }
        }
        $this->RefreshTileExtra();
        $this->PushTile($this->TileData());
    }

    public function GetVisualizationTile(): string
    {
        return $this->RenderTile(__DIR__, $this->TileData());
    }

    /** Übersicht der Pi-hole Aktivität (JSON). */
    public function GetSummary(): string
    {
        return $this->ApiJson('GET', 'stats/summary');
    }

    /** Am häufigsten angefragte Domains, optional nur geblockte (JSON). */
    public function GetTopDomains(bool $Blocked = false, int $Count = 10): string
    {
        return $this->ApiJson('GET', 'stats/top_domains', ['blocked' => $Blocked, 'count' => $Count]);
    }

    /** Aktivste Clients, optional nur geblockte Anfragen (JSON). */
    public function GetTopClients(bool $Blocked = false, int $Count = 10): string
    {
        return $this->ApiJson('GET', 'stats/top_clients', ['blocked' => $Blocked, 'count' => $Count]);
    }

    /** Statistik der Upstream-DNS-Server (JSON). */
    public function GetUpstreams(): string
    {
        return $this->ApiJson('GET', 'stats/upstreams');
    }

    /** Anzahl der Abfragen je DNS-Typ (JSON). */
    public function GetQueryTypes(): string
    {
        return $this->ApiJson('GET', 'stats/query_types');
    }

    /** Zuletzt geblockte Domains (JSON). */
    public function GetRecentBlocked(int $Count = 10): string
    {
        return $this->ApiJson('GET', 'stats/recent_blocked', ['count' => $Count]);
    }

    /** Verlauf der Abfragen über die letzten 24 Stunden (JSON). */
    public function GetHistory(): string
    {
        return $this->ApiJson('GET', 'history');
    }

    /** Verlauf je Client über die letzten 24 Stunden (JSON). */
    public function GetClientHistory(int $Clients = 20): string
    {
        return $this->ApiJson('GET', 'history/clients', ['N' => $Clients]);
    }

    /**
     * Zusammenfassung aus der Langzeit-Datenbank.
     *
     * @param int $From  Beginn als Unix-Zeitstempel
     * @param int $Until Ende als Unix-Zeitstempel
     */
    public function GetDatabaseSummary(int $From, int $Until): string
    {
        return $this->ApiJson('GET', 'stats/database/summary', ['from' => $From, 'until' => $Until]);
    }

    /** Top-Domains aus der Langzeit-Datenbank (JSON). */
    public function GetDatabaseTopDomains(int $From, int $Until, bool $Blocked = false, int $Count = 10): string
    {
        return $this->ApiJson('GET', 'stats/database/top_domains', ['from' => $From, 'until' => $Until, 'blocked' => $Blocked, 'count' => $Count]);
    }

    /** Top-Clients aus der Langzeit-Datenbank (JSON). */
    public function GetDatabaseTopClients(int $From, int $Until, bool $Blocked = false, int $Count = 10): string
    {
        return $this->ApiJson('GET', 'stats/database/top_clients', ['from' => $From, 'until' => $Until, 'blocked' => $Blocked, 'count' => $Count]);
    }

    /** Upstream-Server aus der Langzeit-Datenbank (JSON). */
    public function GetDatabaseUpstreams(int $From, int $Until): string
    {
        return $this->ApiJson('GET', 'stats/database/upstreams', ['from' => $From, 'until' => $Until]);
    }

    /** DNS-Typen aus der Langzeit-Datenbank (JSON). */
    public function GetDatabaseQueryTypes(int $From, int $Until): string
    {
        return $this->ApiJson('GET', 'stats/database/query_types', ['from' => $From, 'until' => $Until]);
    }

    /** Verlauf aus der Langzeit-Datenbank (JSON). */
    public function GetDatabaseHistory(int $From, int $Until): string
    {
        return $this->ApiJson('GET', 'history/database', ['from' => $From, 'until' => $Until]);
    }

    /** Client-Verlauf aus der Langzeit-Datenbank (JSON). */
    public function GetDatabaseClientHistory(int $From, int $Until): string
    {
        return $this->ApiJson('GET', 'history/database/clients', ['from' => $From, 'until' => $Until]);
    }

    /**
     * Einzelne DNS-Abfragen.
     *
     * @param string $Filter Filter als JSON-Objekt, zum Beispiel {"length":50,"domain":"example.com","client_ip":"192.0.2.10"}.
     *                       Die möglichen Felder stehen in der API-Dokumentation unter "queries".
     */
    public function GetQueries(string $Filter = ''): string
    {
        return $this->ApiJson('GET', 'queries', $this->DecodeParameter($Filter) ?? []);
    }

    /** Vorschläge für Filterwerte der Abfrageliste (JSON). */
    public function GetQuerySuggestions(): string
    {
        return $this->ApiJson('GET', 'queries/suggestions');
    }

    private function TileData(): array
    {
        $extra = json_decode($this->ReadAttributeString('TileExtra'), true);
        $extra = is_array($extra) ? $extra : [];

        return [
            'connected'  => $this->HasActiveParent(),
            'total'      => $this->GetValue('Total'),
            'blocked'    => $this->GetValue('Blocked'),
            'percent'    => $this->GetValue('PercentBlocked'),
            'gravity'    => $this->GetValue('GravityDomains'),
            'history'    => $extra['history'] ?? [],
            'topDomains' => $extra['topDomains'] ?? [],
            'topClients' => $extra['topClients'] ?? [],
            't'          => [
                'blocked'        => $this->Translate('blocked'),
                'offline'        => $this->Translate('Pi-hole not reachable'),
                'queries'        => $this->Translate('DNS queries'),
                'blockedQueries' => $this->Translate('Queries blocked'),
                'onLists'        => $this->Translate('Domains on blocklists'),
                'last24'         => $this->Translate('Last 24 hours'),
                'topBlocked'     => $this->Translate('Top blocked'),
                'topClients'     => $this->Translate('Top clients')
            ]
        ];
    }

    /**
     * Liest Verlauf und Top-Listen für die Kachel, höchstens alle fünf Minuten.
     */
    private function RefreshTileExtra(): void
    {
        if (time() - $this->ReadAttributeInteger('TileExtraTime') < 300) {
            return;
        }
        $history = $this->ApiData('GET', 'history');
        $domains = $this->ApiData('GET', 'stats/top_domains', ['count' => 3, 'blocked' => true]);
        $clients = $this->ApiData('GET', 'stats/top_clients', ['count' => 3]);
        if ($history === null && $domains === null && $clients === null) {
            return;
        }

        // Verlauf auf etwa 48 Punkte verdichten: [Abfragen, geblockt]
        $points = array_map(static fn (array $p): array => [(int) ($p['total'] ?? 0), (int) ($p['blocked'] ?? 0)], (array) ($history['history'] ?? []));
        $chunks = array_chunk($points, max(1, (int) ceil(count($points) / 48)));
        $series = array_map(static fn (array $chunk): array => [array_sum(array_column($chunk, 0)), array_sum(array_column($chunk, 1))], $chunks);

        $this->WriteAttributeString('TileExtra', json_encode([
            'history'    => $series,
            'topDomains' => array_map(static fn (array $d): array => ['n' => (string) ($d['domain'] ?? ''), 'c' => (int) ($d['count'] ?? 0)], (array) ($domains['domains'] ?? [])),
            'topClients' => array_map(static fn (array $c): array => ['n' => (string) (($c['name'] ?? '') !== '' ? $c['name'] : ($c['ip'] ?? '')), 'c' => (int) ($c['count'] ?? 0)], (array) ($clients['clients'] ?? []))
        ]));
        $this->WriteAttributeInteger('TileExtraTime', time());
    }
    private function RemoveGroup(string $prefix): void
    {
        foreach (IPS_GetChildrenIDs($this->InstanceID) as $child) {
            $ident = IPS_GetObject($child)['ObjectIdent'];
            if (str_starts_with($ident, $prefix . '_')) {
                $this->UnregisterVariable($ident);
            }
        }
    }
}
