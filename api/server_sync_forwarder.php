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

function sync_forward_request_headers(bool $includeContentType = true): array
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

    return $headers;
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

function decode_server_receiver_payload($responseBody): ?array
{
    if (!is_string($responseBody) || trim($responseBody) === '') {
        return null;
    }

    $payload = trim($responseBody);
    $decoded = json_decode($payload, true);
    if (is_array($decoded)) {
        return $decoded;
    }

    $jsonStart = strpos($payload, '{');
    $jsonEnd = strrpos($payload, '}');
    if ($jsonStart !== false && $jsonEnd !== false && $jsonEnd >= $jsonStart) {
        $candidate = substr($payload, $jsonStart, $jsonEnd - $jsonStart + 1);
        $decoded = json_decode($candidate, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    return null;
}

function should_retry_server_receiver_candidate($responseBody, int $httpCode): bool
{
    if ($responseBody === false || $httpCode === 0 || $httpCode === 404) {
        return true;
    }

    $decoded = decode_server_receiver_payload((string) $responseBody);
    if ($decoded === null) {
        $trimmed = ltrim((string) $responseBody);
        return $trimmed === ''
            || stripos($trimmed, '<!DOCTYPE html') === 0
            || stripos($trimmed, '<html') === 0;
    }

    $message = strtolower(trim((string) ($decoded['message'] ?? '')));

    return str_contains($message, 'endpoint réservé au serveur central')
        || str_contains($message, 'endpoint reserve au serveur central')
        || str_contains($message, 'action non autorisée en mode central')
        || str_contains($message, 'action non autorisee en mode central');
}

function probe_server_receiver_connection(string $serverAddress, int $timeout = 5): array
{
    $serverUrls = build_server_receiver_urls($serverAddress);
    $responseBody = false;
    $transportError = '';
    $httpCode = 0;
    $targetUrl = '';
    $parsedBody = null;

    foreach ($serverUrls as $serverUrl) {
        $targetUrl = $serverUrl;
        $headers = sync_forward_request_headers(false);

        if (function_exists('curl_init')) {
            $ch = curl_init($serverUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_HTTPHEADER => $headers,
            ]);

            $responseBody = curl_exec($ch);
            $transportError = curl_error($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => implode("\r\n", $headers) . "\r\n",
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

        $parsedBody = decode_server_receiver_payload($responseBody);

        if (should_retry_server_receiver_candidate($responseBody, $httpCode)) {
            continue;
        }

        break;
    }

    $success = is_array($parsedBody)
        ? (bool) ($parsedBody['success'] ?? false)
        : (($responseBody !== false || $httpCode > 0) && $httpCode >= 200 && $httpCode < 300);

    return [
        'success' => $success,
        'body' => $responseBody,
        'parsed_body' => $parsedBody,
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

    // Fallback legacy
    $urls[] = $baseUrl . '/com.serverur/api_receiver.php';
    $urls[] = $baseUrl . '/com.serveur/api_receiver.php';

    return array_values(array_unique($urls));
}

function forward_sync_payload_to_server(string $serverAddress, string $rawPayload, int $timeout = 15): array
{
    if (trim($rawPayload) === '') {
        throw new InvalidArgumentException('Payload vide.');
    }

    $serverUrls = build_server_receiver_urls($serverAddress);
    $responseBody = false;
    $transportError = '';
    $httpCode = 0;
    $targetUrl = '';
    $parsedBody = null;

    foreach ($serverUrls as $serverUrl) {
        $targetUrl = $serverUrl;
        $headers = sync_forward_request_headers(true);

        if (function_exists('curl_init')) {
            $ch = curl_init($serverUrl);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $rawPayload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_HTTPHEADER => $headers,
            ]);

            $responseBody = curl_exec($ch);
            $transportError = curl_error($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => implode("\r\n", $headers) . "\r\n",
                    'content' => $rawPayload,
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

        $parsedBody = decode_server_receiver_payload($responseBody);

        if (should_retry_server_receiver_candidate($responseBody, $httpCode)) {
            continue;
        }

        break;
    }

    return [
        'body' => $responseBody,
        'parsed_body' => $parsedBody,
        'http_code' => $httpCode,
        'transport_error' => $transportError,
        'target_url' => $targetUrl,
        'attempted_urls' => $serverUrls,
    ];
}