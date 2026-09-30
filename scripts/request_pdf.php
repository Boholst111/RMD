<?php
// Simple requester to fetch headers for PDF route
$appUrl = getenv('APP_URL') ?: (getenv('APP_URL') === false ? null : getenv('APP_URL'));
if (empty($appUrl)) {
    $appUrl = 'http://127.0.0.1:8000';
}

$url = rtrim($appUrl, '/') . '/scaling/reports/pdf';
$headers = @get_headers($url, 1);
if (! $headers) {
    echo "NO_RESPONSE for {$url}\n";
    exit(1);
}
$status = is_array($headers[0] ?? null) ? end($headers[0]) : ($headers[0] ?? 'Unknown status');
$contentType = $headers['Content-Type'] ?? 'Unknown content type';
if (is_array($contentType)) {
    $contentType = end($contentType);
}

echo "HTTP {$status}\nContent-Type: {$contentType}\n";
