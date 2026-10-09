<?php

declare(strict_types=1);

/**
 * Verbindung zur REST-API von Pi-hole v6.
 * Meldet sich einmal an, hält die Sitzung und reicht die Anfragen der Instanzen weiter.
 */
class PiHoleSplitter extends IPSModuleStrict
{
    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyString('Host', '');
        $this->RegisterPropertyInteger('Port', 80);
        $this->RegisterPropertyBoolean('UseHTTPS', false);
        $this->RegisterPropertyBoolean('VerifyCertificate', false);
        $this->RegisterPropertyString('Password', '');
        $this->RegisterPropertyInteger('Timeout', 10);

        $this->RegisterAttributeString('SessionID', '');
        $this->RegisterAttributeInteger('SessionValidUntil', 0);

        $this->RegisterVariableBoolean('Connected', $this->Translate('Connected'), ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION], 1);
    }

    public function ApplyChanges(): void
    {
        //Never delete this line!
        parent::ApplyChanges();

        // Geänderte Zugangsdaten: alte Sitzung freigeben, Pi-hole begrenzt die Anzahl der Sitzungen
        $this->Logout();

        $this->SetStatus($this->ReadPropertyString('Host') === '' ? IS_INACTIVE : IS_ACTIVE);
    }

    public function ForwardData(string $Data): string
    {
        $request = json_decode($Data, true);
        if (!is_array($request) || !isset($request['Method'], $request['Endpoint'])) {
            return json_encode(['Code' => 0, 'Data' => null, 'Text' => 'invalid request']);
        }
        $result = $this->Execute(
            strtoupper((string) $request['Method']),
            (string) $request['Endpoint'],
            is_array($request['Query'] ?? null) ? $request['Query'] : [],
            $request['Body'] ?? null,
            (bool) ($request['Binary'] ?? false),
            (int) ($request['Timeout'] ?? 0)
        );

        return json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    /**
     * Allgemeine Anfrage an die API, zum Beispiel PIHSPL_Request($id, 'GET', 'info/version').
     *
     * @param string $Query Query-Parameter als JSON-Objekt, zum Beispiel {"count":5}
     * @param string $Body  Anfragekörper als JSON
     */
    public function Request(string $Method, string $Endpoint, string $Query = '', string $Body = ''): string
    {
        $query = json_decode($Query, true);
        $body = $Body === '' ? null : json_decode($Body, true);
        $result = $this->Execute(strtoupper($Method), ltrim($Endpoint, '/'), is_array($query) ? $query : [], $body, false);

        return json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    /**
     * Prüft Erreichbarkeit und Anmeldung und liefert eine Meldung für das Formular.
     */
    public function TestConnection(): string
    {
        $this->WriteAttributeString('SessionID', '');
        $result = $this->Execute('GET', 'info/version', [], null, false);
        switch (true) {
            case $result['Code'] === 0:
                return $this->Translate('Pi-hole not reachable');
            case $result['Code'] === 401:
                return $this->Translate('Login failed, please check the password');
            case $result['Code'] >= 200 && $result['Code'] < 300:
                $version = $result['Data']['version']['core']['local']['version'] ?? '?';

                return sprintf($this->Translate('Connection successful, Pi-hole core %s'), $version);
        }

        return sprintf($this->Translate('Unexpected answer (HTTP %d)'), $result['Code']);
    }

    /**
     * Beendet die aktuelle Sitzung am Pi-hole.
     */
    public function Logout(): void
    {
        $sid = $this->ReadAttributeString('SessionID');
        $this->WriteAttributeString('SessionID', '');
        $this->WriteAttributeInteger('SessionValidUntil', 0);
        if ($sid !== '' && $this->ReadPropertyString('Host') !== '') {
            $this->Curl('DELETE', 'auth', [], null, $sid, false);
        }
    }

    /**
     * Liste der aktiven Sitzungen (JSON).
     */
    public function GetSessions(): string
    {
        return $this->Request('GET', 'auth/sessions');
    }

    /**
     * Beendet eine fremde Sitzung anhand ihrer Nummer (JSON-Antwort).
     */
    public function DeleteSession(int $ID): string
    {
        return $this->Request('DELETE', 'auth/session/' . $ID);
    }

    /**
     * Erzeugt ein neues App-Passwort. Ein bisheriges App-Passwort wird dadurch ungültig.
     * Die Antwort enthält das neue Passwort im Klartext und wird nicht protokolliert.
     */
    public function GenerateAppPassword(): string
    {
        return $this->Request('GET', 'auth/app');
    }

    private function Execute(string $method, string $endpoint, array $query, mixed $body, bool $binary, int $timeout = 0): array
    {
        $result = ['Code' => 0, 'Data' => null, 'Text' => '', 'Base64' => ''];
        if ($this->GetStatus() !== IS_ACTIVE) {
            return $result;
        }

        $lock = 'PiHole' . $this->InstanceID;
        if (!IPS_SemaphoreEnter($lock, 15000)) {
            return $result;
        }
        try {
            $needsLogin = $this->ReadPropertyString('Password') !== '';
            if ($needsLogin && !$this->HasValidSession() && !$this->Login()) {
                $this->SetValue('Connected', false);

                return ['Code' => 401, 'Data' => null, 'Text' => 'login failed', 'Base64' => ''];
            }
            $result = $this->Curl($method, $endpoint, $query, $body, $this->ReadAttributeString('SessionID'), $binary, $timeout);
            if ($result['Code'] === 401 && $needsLogin && $this->Login()) {
                $result = $this->Curl($method, $endpoint, $query, $body, $this->ReadAttributeString('SessionID'), $binary, $timeout);
            }
        } finally {
            IPS_SemaphoreLeave($lock);
        }
        $this->SetValue('Connected', $result['Code'] > 0 && $result['Code'] !== 401);

        return $result;
    }

    private function HasValidSession(): bool
    {
        return $this->ReadAttributeString('SessionID') !== '' && time() < $this->ReadAttributeInteger('SessionValidUntil');
    }

    private function Login(): bool
    {
        $result = $this->Curl('POST', 'auth', [], ['password' => $this->ReadPropertyString('Password')], '', false);
        $session = $result['Data']['session'] ?? [];
        if ($result['Code'] !== 200 || !is_string($session['sid'] ?? null)) {
            $this->WriteAttributeString('SessionID', '');
            $this->LogMessage($this->Translate('Pi-hole login failed'), KL_ERROR);

            return false;
        }
        $this->WriteAttributeString('SessionID', $session['sid']);
        // Jede erfolgreiche Anfrage verlängert die Sitzung am Pi-hole; sicherheitshalber früher erneuern
        $this->WriteAttributeInteger('SessionValidUntil', time() + max(30, (int) ($session['validity'] ?? 300) - 10));

        return true;
    }

    private function Curl(string $method, string $endpoint, array $query, mixed $body, string $sid, bool $binary, int $timeout = 0): array
    {
        $result = ['Code' => 0, 'Data' => null, 'Text' => '', 'Base64' => ''];

        $scheme = $this->ReadPropertyBoolean('UseHTTPS') ? 'https' : 'http';
        $url = $scheme . '://' . $this->ReadPropertyString('Host') . ':' . $this->ReadPropertyInteger('Port') . '/api/' . ltrim($endpoint, '/');
        if ($query !== []) {
            // Wahrheitswerte als true/false, nicht als 1/0
            $query = array_map(static fn ($value) => is_bool($value) ? ($value ? 'true' : 'false') : $value, $query);
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        $headers = ['Accept: application/json'];
        if ($sid !== '') {
            $headers[] = 'X-FTL-SID: ' . $sid;
        }
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout > 0 ? $timeout : max(1, $this->ReadPropertyInteger('Timeout')));
        $verify = $this->ReadPropertyBoolean('VerifyCertificate');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verify);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verify ? 2 : 0);
        $upload = '';
        if (is_array($body) && isset($body['_multipart'])) {
            // Datei-Upload (Teleporter): Inhalt kommt Base64-codiert und geht über eine temporäre Datei
            $upload = tempnam(sys_get_temp_dir(), 'pih');
            file_put_contents($upload, base64_decode((string) ($body['_multipart']['base64'] ?? '')));
            $fields = ['file' => new CURLFile($upload, 'application/zip', (string) ($body['_multipart']['name'] ?? 'teleporter.zip'))];
            foreach ((array) ($body['_multipart']['fields'] ?? []) as $name => $value) {
                $fields[$name] = (string) $value;
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        } elseif ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $hide = $endpoint === 'auth' || $endpoint === 'auth/app';
        $this->SendDebug('Request', $method . ' ' . preg_replace('/sid=[^&]+/', 'sid=***', $url) . ($hide || $body === null || $upload !== '' ? '' : ' ' . json_encode($body)), 0);
        $response = curl_exec($ch);
        $result['Code'] = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($upload !== '') {
            unlink($upload);
        }

        if (!is_string($response)) {
            $this->SendDebug('Error', $error, 0);

            return $result;
        }
        $this->SendDebug('Response', $result['Code'] . ' ' . ($hide ? '(hidden)' : ($binary ? strlen($response) . ' bytes' : substr($response, 0, 4000))), 0);

        if ($binary) {
            $result['Base64'] = base64_encode($response);

            return $result;
        }
        $data = json_decode($response, true);
        if (is_array($data)) {
            $result['Data'] = $data;
        } else {
            $result['Text'] = $response;
        }

        return $result;
    }
}
