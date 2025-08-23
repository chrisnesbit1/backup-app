<?php
function s3_presign_put(string $bucket, string $key, array $config): string
{
    return s3_presign('PUT', $bucket, $key, $config);
}

function s3_presign_get(string $bucket, string $key, array $config): string
{
    return s3_presign('GET', $bucket, $key, $config);
}

function s3_presign(string $method, string $bucket, string $key, array $config, int $expires = 300): string
{
    $endpoint = rtrim($config['s3_endpoint'] ?? 'https://s3.amazonaws.com', '/');
    $region = $config['s3_region'] ?? 'us-east-1';
    $service = 's3';
    $access = $config['s3_access_key'] ?? '';
    $secret = $config['s3_secret_key'] ?? '';

    $host = parse_url($endpoint, PHP_URL_HOST);
    $uri = '/' . ltrim($bucket . '/' . $key, '/');
    $amz_date = gmdate('Ymd\THis\Z');
    $date = substr($amz_date, 0, 8);
    $credential_scope = "$date/$region/$service/aws4_request";
    $signed_headers = 'host';

    $params = [
        'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
        'X-Amz-Credential' => $access . '/' . $credential_scope,
        'X-Amz-Date' => $amz_date,
        'X-Amz-Expires' => $expires,
        'X-Amz-SignedHeaders' => $signed_headers,
    ];
    $canonical_query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    $canonical_request = "$method\n$uri\n$canonical_query\n" .
        "host:$host\n\n$signed_headers\nUNSIGNED-PAYLOAD";
    $hash = hash('sha256', $canonical_request);
    $string_to_sign = "AWS4-HMAC-SHA256\n$amz_date\n$credential_scope\n$hash";
    $kSigning = hash_hmac('sha256', 'aws4_request',
        hash_hmac('sha256', $service,
            hash_hmac('sha256', $region,
                hash_hmac('sha256', $date, 'AWS4' . $secret, true), true), true), true);
    $signature = hash_hmac('sha256', $string_to_sign, $kSigning);
    return "$endpoint$uri?$canonical_query&X-Amz-Signature=$signature";
}
