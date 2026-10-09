<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/PiHoleClient.php';
require_once __DIR__ . '/../libs/PiHoleTile.php';

/**
 * Steuerung des DNS-Blockings (Endpunkt dns/blocking).
 */
class PiHoleDNS extends IPSModuleStrict
{
    use PiHoleClient;
    use PiHoleTile;

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        $this->ConnectParent('{7F1B108F-2DCE-496E-8BAE-8103685BF11E}');
        $this->RegisterMessage($this->InstanceID, FM_CONNECT);

        $this->RegisterPropertyInteger('UpdateInterval', 20);

        $this->RegisterVariableBoolean('Blocking', $this->Translate('Blocking'), ['PRESENTATION' => VARIABLE_PRESENTATION_SWITCH], 1);
        $this->EnableAction('Blocking');
        $this->RegisterVariableInteger('DisableTime', $this->Translate('Time to disable'), ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_INPUT, 'SUFFIX' => ' s'], 2);
        $this->EnableAction('DisableTime');
        $this->RegisterVariableInteger('TimerRemaining', $this->Translate('Remaining time'), ['PRESENTATION' => VARIABLE_PRESENTATION_DURATION], 3);

        // Dauer der aktuellen Pause in Sekunden, für den Fortschrittsring der Kachel
        $this->RegisterAttributeInteger('PauseTotal', 0);
        $this->RegisterTimer('Update', 0, 'PIHDNS_Update($_IPS[\'TARGET\']);');
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

    public function RequestAction(string $Ident, mixed $Value): void
    {
        switch ($Ident) {
            case 'Blocking':
                $this->SetBlocking((bool) $Value, $Value ? 0 : $this->GetValue('DisableTime'));
                break;
            case 'DisableTime':
                $this->SetValue($Ident, max(0, (int) $Value));
                break;
            case 'Pause':
                // Aus der Kachel: Blocking für $Value Sekunden aus, 0 = dauerhaft
                $this->WriteAttributeInteger('PauseTotal', max(0, (int) $Value));
                $this->SetBlocking(false, max(0, (int) $Value));
                break;
            case 'Resume':
                $this->SetBlocking(true);
                break;
        }
    }

    public function GetVisualizationTile(): string
    {
        return $this->RenderTile(__DIR__, $this->TileData());
    }

    /**
     * Liest den Blocking-Status vom Pi-hole.
     */
    public function Update(): void
    {
        $data = $this->ApiData('GET', 'dns/blocking');
        $this->Apply($data);
    }

    /**
     * Schaltet das Blocking ein oder aus.
     *
     * @param bool $Enabled true = Blocking aktiv
     * @param int  $Timer   Sekunden bis zum automatischen Zurückschalten, 0 = dauerhaft
     */
    public function SetBlocking(bool $Enabled, int $Timer = 0): bool
    {
        $body = ['blocking' => $Enabled, 'timer' => $Timer > 0 ? $Timer : null];
        $data = $this->ApiData('POST', 'dns/blocking', [], $body);
        $this->Apply($data);

        return $data !== null;
    }

    /** Aktueller Blocking-Status mit Restzeit des Timers (JSON). */
    public function GetBlocking(): string
    {
        return $this->ApiJson('GET', 'dns/blocking');
    }

    private function Apply(?array $data): void
    {
        if ($data === null || !isset($data['blocking'])) {
            return;
        }
        $blocking = $data['blocking'] === 'enabled';
        $remaining = (int) round((float) ($data['timer'] ?? 0));
        $this->SetValue('Blocking', $blocking);
        $this->SetValue('TimerRemaining', $remaining);
        // Pausendauer merken: bei laufendem Blocking zurücksetzen, sonst den größten gesehenen Wert halten
        $this->WriteAttributeInteger('PauseTotal', $blocking ? 0 : max($this->ReadAttributeInteger('PauseTotal'), $remaining));
        $this->PushTile($this->TileData());
    }

    private function TileData(): array
    {
        return [
            'connected' => $this->HasActiveParent(),
            'blocking'  => $this->GetValue('Blocking'),
            'remaining' => $this->GetValue('TimerRemaining'),
            'total'     => $this->ReadAttributeInteger('PauseTotal'),
            'duration'  => $this->GetValue('DisableTime'),
            't'         => [
                'active'       => $this->Translate('Blocking active'),
                'paused'       => $this->Translate('Blocking paused'),
                'protected'    => $this->Translate('Network protected'),
                'untilResumed' => $this->Translate('until resumed'),
                'offline'      => $this->Translate('Pi-hole not reachable'),
                'remaining'    => $this->Translate('remaining'),
                'resume'       => $this->Translate('Resume blocking'),
                'minShort'     => $this->Translate('min'),
                'hourShort'    => $this->Translate('h')
            ]
        ];
    }
}
