<?php

header('Content-Type: text/plain; charset=utf-8');

echo "REMOTE_ADDR:\n";
var_dump($_SERVER['REMOTE_ADDR'] ?? null);

echo "\n\nHTTP_X_FORWARDED_FOR:\n";
var_dump($_SERVER['HTTP_X_FORWARDED_FOR'] ?? null);

echo "\n\nHTTP_X_REAL_IP:\n";
var_dump($_SERVER['HTTP_X_REAL_IP'] ?? null);

echo "\n\nHTTP_CF_CONNECTING_IP:\n";
var_dump($_SERVER['HTTP_CF_CONNECTING_IP'] ?? null);

echo "\n\nHTTP_FORWARDED:\n";
var_dump($_SERVER['HTTP_FORWARDED'] ?? null);

echo "\n\nAlle relevanten SERVER-Werte:\n";

foreach ($_SERVER as $key => $value) {
    if (
        str_contains($key, 'REMOTE') ||
        str_contains($key, 'FORWARD') ||
        str_contains($key, 'PROXY') ||
        str_contains($key, 'CLIENT')
    ) {
        echo $key . ' = ' . $value . "\n";
    }
}