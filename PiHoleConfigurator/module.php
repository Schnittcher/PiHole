<?php

declare(strict_types=1);

/**
 * Legt die Instanzen eines Pi-holes an. Die Liste ist fest, der Konfigurator zeigt je Eintrag,
 * ob am gleichen Splitter schon eine Instanz existiert.
 */
class PiHoleConfigurator extends IPSModuleStrict
{
    /** Modul-GUID => [Name, Beschreibung] */
    private const INSTANCES = [
        '{3504BCB6-C440-4180-9035-E57820CBF43E}' => ['PiHole Statistics', 'Statistics, top lists and history'],
        '{7DB6D9B6-3EA3-4EBC-A4AA-0813503F3762}' => ['PiHole DNS', 'Switch DNS blocking on and off'],
        '{50DAA0EE-CBCD-4702-8BD0-1D4148CA1A26}' => ['PiHole System', 'System state, versions and logs'],
        '{D53E796A-59A7-4D0F-A477-784439AE6C26}' => ['PiHole Maintenance', 'Gravity, DNS restart and backup'],
        '{4C0B86B0-D2A7-41B8-856C-A0F2C8A77CB6}' => ['PiHole Filters', 'Domains, groups, clients and lists'],
        '{C5602722-327C-48D2-A70B-9BCC04E5598F}' => ['PiHole Network', 'Network devices and DHCP leases'],
        '{DD5CD811-860F-4968-8E48-47F99802FFEF}' => ['PiHole Config', 'Read and change the configuration']
    ];

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
    }

    public function ApplyChanges(): void
    {
        //Never delete this line!
        parent::ApplyChanges();

        $this->SetStatus(IS_ACTIVE);
    }

    public function GetConfigurationForm(): string
    {
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);

        // Nur Instanzen am gleichen Splitter gelten als vorhanden
        $splitter = IPS_GetInstance($this->InstanceID)['ConnectionID'];

        $values = [];
        foreach (self::INSTANCES as $guid => [$name, $description]) {
            $values[] = [
                'name'        => $this->Translate($name),
                'description' => $this->Translate($description),
                'instanceID'  => $this->FindInstance($guid, $splitter),
                'create'      => [
                    'moduleID'      => $guid,
                    'configuration' => new stdClass(),
                    'name'          => $name
                ]
            ];
        }

        foreach ($form['elements'] as &$element) {
            if (($element['type'] ?? '') === 'Configurator') {
                $element['values'] = $values;
            }
        }
        unset($element);

        return json_encode($form);
    }

    private function FindInstance(string $guid, int $splitter): int
    {
        foreach (IPS_GetInstanceListByModuleID($guid) as $id) {
            if ($splitter > 0 && IPS_GetInstance($id)['ConnectionID'] === $splitter) {
                return $id;
            }
        }

        return 0;
    }
}
