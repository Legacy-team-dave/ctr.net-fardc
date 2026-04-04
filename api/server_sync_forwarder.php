<?php

function parse_server_receiver_response_headers(array $responseHeaders): int
{
    foreach ($responseHeaders as $headerLine) {
        if (preg_match('/^HTTP\/\S+\s+(\d{3})/i', $headerLine, $matches)) {
            return (int) $matches[1];
        }
    }

    return 0;
}

function sync_forward_request_headers(bool $includeContentType = true, array $extraHeaders = []): array
{
    $headers = [];

    if ($includeContentType) {
        $headers[] = 'Content-Type: application/json';
    }

    $headers[] = 'Accept: application/json';

    if (function_exists('sync_config')) {
        $config = sync_config();
        $sharedToken = trim((string) ($config['shared_token'] ?? ''));
        $instanceId = trim((string) ($config['instance_id'] ?? ''));

        if ($sharedToken !== '') {
            $headers[] = 'Authorization: Bearer ' . $sharedToken;
            $headers[] = 'X-Sync-Token: ' . $sharedToken;
        }

        if ($instanceId !== '') {
            $headers[] = 'X-Sync-Instance: ' . $instanceId;
        }
    }

    foreach ($extraHeaders as $headerLine) {
        if (is_string($headerLine) && trim($headerLine) !== '') {
            $headers[] = trim($headerLine);
        }
    }

    return array_values(array_unique($headers));
}

function append_server_receiver_candidates(array &$urls, string $baseUrl, string $path = '', string $query = ''): void
{
    $normalizedPath = trim($path, '/');
    $normalizedQuery = trim($query, '?');

    if ($normalizedPath === '') {
        $urls[] = $baseUrl . '/api/api_receiver.php';
        $urls[] = $baseUrl . '/modules/synchronisation/recevoir.php';
        $urls[] = $baseUrl . '/synchronisation/recevoir.php';
        $urls[] = $baseUrl . '/recevoir.php';
        return;
    }

    if (preg_match('#(?:^|/)(api/api_receiver\.php|modules/synchronisation/recevoir\.php|synchronisation/recevoir\.php|recevoir\.php)$#i', $normalizedPath)) {
        $urls[] = $baseUrl . '/' . $normalizedPath . ($normalizedQuery !== '' ? '?' . $normalizedQuery : '');
        return;
    }

    $urls[] = $baseUrl . '/' . $normalizedPath . '/api/api_receiver.php';
    $urls[] = $baseUrl . '/' . $normalizedPath . '/modules/synchronisation/recevoir.php';
    $urls[] = $baseUrl . '/' . $normalizedPath . '/synchronisation/recevoir.php';
    $urls[] = $baseUrl . '/' . $normalizedPath . '/recevoir.php';
}

function sync_execute_http_request(string $serverUrl, string $method, array $headers, ?string $body, int $timeout, int $connectTimeout): array
{
    $responseBody = false;
    $transportError = '';
    $httpCode = 0;

    if (function_exists('curl_init')) {
        $ch = curl_init($serverUrl);
        $curlOptions = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
        ];

        if (strtoupper($method) === 'POST') {
            $curlOptions[CURLOPT_POST] = true;
            $curlOptions[CURLOPT_POSTFIELDS] = $body ?? '';
        }

        curl_setopt_array($ch, $curlOptions);

        $responseBody = curl_exec($ch);
        $transportError = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => strtoupper($method),
                'header' => implode("\r\n", $headers) . "\r\n",
                'content' => strtoupper($method) === 'POST' ? ($body ?? '') : null,
                'timeout' => $timeout,
                'ignore_errors' => true,
            ],
        ]);

        $responseBody = @file_get_contents($serverUrl, false, $context);
        $transportError = $responseBody === false ? 'Connexion HTTP echouee avec file_get_contents().' : '';
        $httpCode = isset($http_response_header) && is_array($http_response_header)
            ? parse_server_receiver_response_headers($http_response_header)
            : 0;
    }

    return [
        'body' => $responseBody,
        'transport_error' => $transportError,
        'http_code' => $httpCode,
    ];
}

function sync_forward_should_retry(int $httpCode, $responseBody, string $transportError, int $attempt, int $maxRetries): bool
{
    if ($attempt >= $maxRetries) {
        return false;
    }

    if ($responseBody === false && trim($transportError) !== '') {
        return true;
    }

    return in_array($httpCode, [0, 408, 425, 429, 500, 502, 503, 504], true);
}

function sync_wait_before_retry(int $attempt, int $retryDelayMs): int
{
    $delay = (int) min(10000, max(250, $retryDelayMs) * (2 ** max(0, $attempt - 1)));
    usleep($delay * 1000);
    return $delay;
}

function probe_server_receiver_connection(string $serverAddress, int $timeout = 5): array
{
    $config = function_exists('sync_config') ? sync_config() : [];
    $connectTimeout = max(2, min($timeout, (int) ($config['connect_timeout'] ?? max(2, min(10, $timeout)))));
    $serverUrls = build_server_receiver_urls($serverAddress);
    $responseBody = false;
    $transportError = '';
    $httpCode = 0;
    $targetUrl = '';

    foreach ($serverUrls as $serverUrl) {
        $targetUrl = $serverUrl;
        $result = sync_execute_http_request($serverUrl, 'GET', sync_forward_request_headers(false), null, $timeout, $connectTimeout);
        $responseBody = $result['body'];
        $transportError = $result['transport_error'];
        $httpCode = $result['http_code'];

        if (($responseBody !== false || $httpCode > 0) && $httpCode !== 404) {
            break;
        }
    }

    $success = ($responseBody !== false || $httpCode > 0) && $httpCode !== 404;

    return [
        'success' => $success,
        'body' => $responseBody,
        'http_code' => $httpCode,
        'transport_error' => $transportError,
        'target_url' => $targetUrl,
        'attempted_urls' => $serverUrls,
    ];
}

function build_server_receiver_urls(string $serverAddress): array
{
    $serverAddress = trim($serverAddress);
    if ($serverAddress === '') {
        throw new InvalidArgumentException('Adresse serveur manquante.');
    }

    if (!preg_match('#^https?://#i', $serverAddress)) {
        $serverAddress = 'http://' . $serverAddress;
    }

    $parsed = parse_url($serverAddress);
    if ($parsed === false || empty($parsed['host'])) {
        throw new InvalidArgumentException('Adresse serveur invalide.');
    }

    $scheme = strtolower((string) ($parsed['scheme'] ?? 'http'));
    $host = (string) $parsed['host'];
    $port = isset($parsed['port']) ? ':' . (int) $parsed['port'] : '';
    $baseUrl = $scheme . '://' . $host . $port;
    $path = trim((string) ($parsed['path'] ?? ''), '/');
    $query = (string) ($parsed['query'] ?? '');

    $urls = [];

    append_server_receiver_candidates($urls, $baseUrl, $path, $query);
    append_server_receiver_candidates($urls, $baseUrl);

    foreach (['ctr-net-fardc_active_front_web', 'ctr-net-fardc_front_web', 'ctr.net-fardc'] as $appRoot) {
        append_server_receiver_candidates($urls, $baseUrl, $appRoot);
    }

    $urls[] = $baseUrl . '/com.serverur/api_receiver.php';
    $urls[] = $baseUrl . '/com.serveur/api_receiver.php';

    return array_values(array_unique($urls));
}

function forward_sync_payload_to_server(string $serverAddress, string $rawPayload, $options = 15): array
{
    if (trim($rawPayload) === '') {
        throw new InvalidArgumentException('Payload vide.');
    }

    $requestOptions = is_array($options) ? $options : ['timeout' => (int) $options];
    $timeout = max(5, (int) ($requestOptions['timeout'] ?? 15));
    $connectTimeout = max(3, min($timeout, (int) ($requestOptions['connect_timeout'] ?? min(10, $timeout))));
    $maxRetries = min(5, max(1, (int) ($requestOptions['max_retries'] ?? 1)));
    $retryDelayMs = max(250, (int) ($requestOptions['retry_delay_ms'] ?? 1000));
    $extraHeaders = is_array($requestOptions['headers'] ?? null) ? array_values($requestOptions['headers']) : [];

    $serverUrls = build_server_receiver_urls($serverAddress);
    $responseBody = false;
    $transportError = '';
    $httpCode = 0;
    $targetUrl = '';
    $attemptCount = 0;
    $totalBackoffMs = 0;
    $startedAt = microtime(true);

    foreach ($serverUrls as $serverUrl) {
        $targetUrl = $serverUrl;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $attemptCount++;
            $headers = sync_forward_request_headers(true, array_merge($extraHeaders, [
                'X-Sync-Attempt: ' . $attempt,
                'X-Sync-Max-Retries: ' . $maxRetries,
            ]));

            $result = sync_execute_http_request($serverUrl, 'POST', $headers, $rawPayload, $timeout, $connectTimeout);
            $responseBody = $result['body'];
            $transportError = $result['transport_error'];
            $httpCode = $result['http_code'];

            if (($responseBody !== false || $httpCode > 0) && $httpCode !== 404) {
                if (sync_forward_should_retry($httpCode, $responseBody, $transportError, $attempt, $maxRetries)) {
                    $totalBackoffMs += sync_wait_before_retry($attempt, $retryDelayMs);
                    continue;
                }

                break 2;
            }

            if ($httpCode === 404) {
                break;
            }

            if (sync_forward_should_retry($httpCode, $responseBody, $transportError, $attempt, $maxRetries)) {
                $totalBackoffMs += sync_wait_before_retry($attempt, $retryDelayMs);
                continue;
            }
        }
    }

    return [
        'body' => $responseBody,
        'http_code' => $httpCode,
        'transport_error' => $transportError,
        'target_url' => $targetUrl,
        'attempted_urls' => $serverUrls,
        'attempt_count' => $attemptCount,
        'retry_used' => $attemptCount > 1,
        'retry_delay_ms_total' => $totalBackoffMs,
        'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        'connect_timeout' => $connectTimeout,
        'request_timeout' => $timeout,
    ];
}