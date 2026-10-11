<?php

declare(strict_types=1);

// Standalone regression checks: php tests/config-browser.php
class IPSModuleStrict
{
    public array $requests = [];
    public array $responses = [];
    public array $messages = [];
    public int $visualizationType = 0;
    public bool $active = true;

    public function Create(): void
    {
    }
    public function ApplyChanges(): void
    {
    }
    protected function SetVisualizationType(int $type): void
    {
        $this->visualizationType = $type;
    }
    protected function HasActiveParent(): bool
    {
        return $this->active;
    }
    protected function Translate(string $text): string
    {
        return $text;
    }
    protected function UpdateVisualizationValue(string $value): void
    {
        $this->messages[] = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
    }
    protected function SendDataToParent(string $value): string
    {
        $this->requests[] = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        if ($this->responses === []) {
            throw new RuntimeException('Unexpected API request');
        }

        return json_encode(array_shift($this->responses), JSON_THROW_ON_ERROR);
    }
}

require __DIR__ . '/../PiHoleConfig/module.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo 'PASS: ' . $message . PHP_EOL;
}

function leaf(mixed $value, string $type, array $extra = []): array
{
    return array_replace(['value' => $value, 'type' => $type, 'default' => $value, 'description' => 'Example', 'allowed' => null, 'flags' => []], $extra);
}

function responses(): array
{
    return [
        ['Code' => 200, 'Data' => ['config' => [
            'dns' => [
                'queryLogging'  => leaf(true, 'boolean'),
                'upstreams'     => leaf(['1.1.1.1'], 'string array', ['flags' => ['restart_dnsmasq' => true]]),
                'port'          => leaf(53, 'unsigned integer (16 bit)'),
                'listeningMode' => leaf('LOCAL', 'enum (string)', ['allowed' => [['item' => 'LOCAL'], ['item' => 'ALL']]]),
                'interface'     => leaf('eth0', 'string', ['flags' => ['env_var' => true]])
            ],
            'webserver' => ['api' => [
                'password'     => leaf('not-for-the-browser', 'password (write-only string)'),
                'pwhash'       => leaf('private-hash', 'string'),
                'max_sessions' => leaf(16, 'unsigned integer')
            ]],
            'misc'     => ['readOnly' => leaf(false, 'boolean')],
            'resolver' => ['timeout' => leaf(1.0, 'double')]
        ]]],
        ['Code' => 200, 'Data' => ['config' => ['read_only' => [['key' => 'misc.readOnly', 'description' => 'Only in pihole.toml']]]]]
    ];
}

function save(array $changes, ?array $fixtures = null, bool $restart = true): PiHoleConfig
{
    $module = new PiHoleConfig();
    $module->responses = $fixtures ?? [...responses(), ['Code' => 200, 'Data' => []]];
    $module->RequestAction('SaveConfiguration', json_encode(['requestID' => 'save-test', 'restart' => $restart, 'changes' => $changes]));

    return $module;
}

$module = new PiHoleConfig();
$module->Create();
check($module->visualizationType === 1, 'New instances activate the HTML tile');
$module->visualizationType = 0;
$module->ApplyChanges();
check($module->visualizationType === 1, 'Existing instances activate the HTML tile on ApplyChanges');
check(str_contains($module->GetVisualizationTile(), 'configEditor'), 'Tile HTML renders without fetching configuration');
$module->responses = responses();
$module->RequestAction('LoadConfiguration', '{"requestID":"load-test"}');
$reply = $module->messages[0];
check($reply['ok'] && $reply['requestID'] === 'load-test' && count($reply['fields']) === 10, 'Detailed configuration flattened and response correlated');
$fields = array_column($reply['fields'], null, 'path');
check($fields['misc.readOnly']['readOnly'] && $fields['dns.interface']['readOnly'], 'API restrictions and environment flags honored');
check($fields['webserver.api.password']['value'] === '********' && $fields['webserver.api.pwhash']['value'] === '********', 'Secrets masked');
check($fields['dns.upstreams']['restart'], 'FTL restart metadata retained');

$module = save([
    ['path' => 'dns.queryLogging', 'original' => true, 'value' => false],
    ['path' => 'dns.upstreams', 'original' => ['1.1.1.1'], 'value' => ['9.9.9.9']],
    ['path' => 'resolver.timeout', 'original' => 1, 'value' => 1.25]
], restart: false);
check($module->messages[0]['ok'], 'Typed changes saved successfully');
check($module->requests[2]['Method'] === 'PATCH' && $module->requests[2]['Body'] === ['config' => [
    'dns' => ['queryLogging' => false, 'upstreams' => ['9.9.9.9']], 'resolver' => ['timeout' => 1.25]
]] && $module->requests[2]['Query'] === ['restart' => false], 'Only selected changes forwarded with restart preference');

$invalid = [
    ['path' => 'misc.readOnly', 'original' => false, 'value' => true],
    ['path' => 'dns.interface', 'original' => 'eth0', 'value' => 'eth1'],
    ['path' => 'webserver.api.password', 'original' => '********', 'value' => 'new'],
    ['path' => 'dns.queryLogging', 'original' => false, 'value' => true],
    ['path' => 'dns.queryLogging', 'original' => true, 'value' => 'false'],
    ['path' => 'dns.port', 'original' => 53, 'value' => -1],
    ['path' => 'dns.port', 'original' => 53, 'value' => 65536],
    ['path' => 'dns.port', 'original' => 53, 'value' => 53.5],
    ['path' => 'dns.upstreams', 'original' => ['1.1.1.1'], 'value' => [42]],
    ['path' => 'dns.listeningMode', 'original' => 'LOCAL', 'value' => 'INVALID'],
    ['path' => '__proto__.value', 'original' => null, 'value' => 'bad']
];
foreach ($invalid as $i => $change) {
    $module = save([$change]);
    check(!$module->messages[0]['ok'] && count($module->requests) === 2, 'Invalid/locked/stale change rejected before PATCH #' . ($i + 1));
}
$change = ['path' => 'dns.queryLogging', 'original' => true, 'value' => false];
$module = save([$change, $change]);
check(!$module->messages[0]['ok'] && count($module->requests) === 2, 'Duplicate path rejected');
$module = save([]);
check(!$module->messages[0]['ok'] && count($module->requests) === 2, 'Empty changes rejected');
$module = save([$change], [...responses(), ['Code' => 403, 'Data' => ['error' => ['message' => 'App session cannot modify config', 'hint' => 'app_sudo is false']]]]);
check(!$module->messages[0]['ok'] && str_contains($module->messages[0]['error'], 'app_sudo'), 'API write errors surfaced');
$module = new PiHoleConfig();
$module->active = false;
$module->RequestAction('LoadConfiguration', '{"requestID":"offline"}');
check(!$module->messages[0]['ok'] && $module->requests === [], 'Disconnected splitter reported');
$module = new PiHoleConfig();
$module->responses = [['Code' => 200, 'Data' => []], responses()[1]];
$module->RequestAction('LoadConfiguration', '{"requestID":"malformed"}');
check(!$module->messages[0]['ok'], 'Malformed API success response rejected');
$module->RequestAction('SaveConfiguration', 'invalid json');
check(count($module->messages) === 1, 'Malformed browser request ignored');
$module = new PiHoleConfig();
$module->responses = [['Code' => 200, 'Data' => ['config' => []]]];
check(json_decode($module->GetConfig(true), true) === ['config' => []] && $module->requests[0]['Query'] === ['detailed' => true], 'Existing script function remains unchanged');

echo "All configuration backend checks passed.\n";
