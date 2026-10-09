<?php

declare(strict_types=1);

/**
 * Gemeinsame Funktionen der Pi-hole Instanzen, die über den Splitter mit dem Pi-hole sprechen.
 */
trait PiHoleClient
{
    /** Datenfluss: Instanz -> Splitter */
    private string $splitterDataID = '{66FC3F0C-8A7A-4710-B485-4046F6C045AB}';

    /**
     * Wird der Splitter erst nach dem Anlegen verbunden, liest die Instanz ihre Werte sofort.
     */
    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        if ($Message === FM_CONNECT && $SenderID === $this->InstanceID && $this->CanReadNow()) {
            IPS_ApplyChanges($this->InstanceID);
        }
    }

    /**
     * Sendet eine Anfrage an die REST-API des Pi-holes.
     *
     * @return array{Code:int,Data:mixed,Text:string,Base64:string}
     */
    protected function Api(string $method, string $endpoint, array $query = [], mixed $body = null, bool $binary = false, int $timeout = 0): array
    {
        $result = ['Code' => 0, 'Data' => null, 'Text' => '', 'Base64' => ''];
        if (!$this->HasActiveParent()) {
            return $result;
        }
        $payload = json_encode([
            'DataID'   => $this->splitterDataID,
            'Method'   => $method,
            'Endpoint' => $endpoint,
            'Query'    => $query,
            'Body'     => $body,
            'Binary'   => $binary,
            'Timeout'  => $timeout
        ]);
        $response = $this->SendDataToParent($payload);
        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return $result;
        }

        return array_merge($result, $decoded);
    }

    /**
     * Wie Api(), liefert aber nur die Daten einer erfolgreichen Antwort, sonst null.
     */
    protected function ApiData(string $method, string $endpoint, array $query = [], mixed $body = null): ?array
    {
        $result = $this->Api($method, $endpoint, $query, $body);
        if ($result['Code'] < 200 || $result['Code'] >= 300) {
            $this->SendDebug(__FUNCTION__, sprintf('%s %s: HTTP %d', $method, $endpoint, $result['Code']), 0);

            return null;
        }

        return is_array($result['Data']) ? $result['Data'] : [];
    }

    /**
     * Antwort als JSON-Text für öffentliche Funktionen: Daten bei Erfolg, sonst Fehlerobjekt.
     */
    protected function ApiJson(string $method, string $endpoint, array $query = [], mixed $body = null): string
    {
        $result = $this->Api($method, $endpoint, $query, $body);
        if ($result['Code'] === 0) {
            return json_encode(['error' => ['key' => 'no_connection', 'message' => 'No active connection to the Pi-hole splitter']]);
        }
        if ($result['Data'] !== null) {
            return json_encode($result['Data'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return json_encode(['code' => $result['Code'], 'text' => $result['Text']], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Liest einen Wert aus verschachtelten Arrays, Pfad mit Punkten getrennt.
     */
    protected function Pick(array $data, string $path, mixed $default = null): mixed
    {
        foreach (explode('.', $path) as $key) {
            if (!is_array($data) || !array_key_exists($key, $data)) {
                return $default;
            }
            $data = $data[$key];
        }

        return $data ?? $default;
    }

    /**
     * Wandelt einen JSON-Text aus einer Funktionsparameter-Eingabe in ein Array um.
     */
    protected function DecodeParameter(string $json): ?array
    {
        if (trim($json) === '') {
            return null;
        }
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }
    /**
     * Ob die Werte jetzt gelesen werden können: Der Kernel läuft und der Splitter ist aktiv.
     * Beim Start übernehmen das die Timer, damit nicht alle Instanzen gleichzeitig abfragen.
     */
    protected function CanReadNow(): bool
    {
        return IPS_GetKernelRunlevel() === KR_READY && $this->HasActiveParent();
    }

    protected function UpdateInterval(string $timer, string $property): void
    {
        $this->SetTimerInterval($timer, max(0, $this->ReadPropertyInteger($property)) * 1000);
    }
}
