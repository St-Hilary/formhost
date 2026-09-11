<?php
// Small MinistryPlatform REST client using the client-credentials grant.
// Reads MP_BASE_URL, MP_CLIENT_ID and MP_CLIENT_SECRET from the environment.

declare(strict_types=1);

final class MpApiException extends RuntimeException {}

final class MpClient
{
    private string $api;          // e.g. https://mp.sthilary.org/ministryplatformapi
    private string $clientId;
    private string $clientSecret;
    private ?string $token = null;
    private int $tokenExpires = 0;

    public function __construct(string $baseUrl, string $clientId, string $clientSecret)
    {
        $base = rtrim($baseUrl, '/');
        if (!str_ends_with($base, '/ministryplatformapi')) {
            $base .= '/ministryplatformapi';
        }
        $this->api          = $base;
        $this->clientId     = $clientId;
        $this->clientSecret = $clientSecret;
    }

    public static function fromEnv(): self
    {
        $base = getenv('MP_BASE_URL');
        $id   = getenv('MP_CLIENT_ID');
        $sec  = getenv('MP_CLIENT_SECRET');
        if (!$base || !$id || !$sec) {
            throw new MpApiException('MP_BASE_URL, MP_CLIENT_ID and MP_CLIENT_SECRET must all be set.');
        }
        return new self($base, $id, $sec);
    }

    /** SELECT-style read. $params keys: select, filter, orderby, top, skip, distinct. */
    public function get(string $table, array $params = []): array
    {
        $query = [];
        foreach ($params as $k => $v) {
            if ($v !== null && $v !== '') {
                $query['$' . $k] = $v;
            }
        }
        $url = $this->api . '/tables/' . rawurlencode($table);
        if ($query) {
            $url .= '?' . http_build_query($query);
        }
        return $this->request('GET', $url);
    }

    /** Insert one or more records. Returns the created records with their ids. */
    public function create(string $table, array $records): array
    {
        return $this->request('POST', $this->api . '/tables/' . rawurlencode($table), $records);
    }

    /** Update one or more records; each must include its primary key. */
    public function update(string $table, array $records): array
    {
        return $this->request('PUT', $this->api . '/tables/' . rawurlencode($table), $records);
    }

    /** Escape a string for use inside single quotes in a $filter. */
    public static function q(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    // ------------------------------------------------------------------

    private function token(): string
    {
        if ($this->token !== null && time() < $this->tokenExpires - 60) {
            return $this->token;
        }
        $body = http_build_query([
            'grant_type'    => 'client_credentials',
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'scope'         => 'http://www.thinkministry.com/dataplatform/scopes/all',
        ]);
        $resp = $this->curl('POST', $this->api . '/oauth/connect/token', $body, [
            'Content-Type: application/x-www-form-urlencoded',
        ]);
        if (empty($resp['access_token'])) {
            throw new MpApiException('MP token request failed: ' . json_encode($resp));
        }
        $this->token        = $resp['access_token'];
        $this->tokenExpires = time() + (int) ($resp['expires_in'] ?? 3600);
        return $this->token;
    }

    private function request(string $method, string $url, ?array $json = null): array
    {
        $headers = ['Authorization: Bearer ' . $this->token(), 'Accept: application/json'];
        $body = null;
        if ($json !== null) {
            $headers[] = 'Content-Type: application/json';
            $body = json_encode($json);
        }
        return $this->curl($method, $url, $body, $headers);
    }

    private function curl(string $method, string $url, ?string $body, array $headers): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 30,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $raw    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err    = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new MpApiException("MP request failed: $method $url: $err");
        }
        if ($status < 200 || $status >= 300) {
            throw new MpApiException("MP $method $url returned HTTP $status: " . substr((string) $raw, 0, 500));
        }
        $decoded = json_decode((string) $raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
