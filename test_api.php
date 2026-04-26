<?php
$urls = [
    'https://pydolarvenezuela-api-v2.onrender.com/v1/dollar?moneda=bcv',
    'https://ve.dolarapi.com/v1/dolares/oficial',
    'https://api.bcv.com.ve/dolar/info'
];

foreach ($urls as $url) {
    echo "URL: $url\n";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $response = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP: $http\n";
    echo "Response: " . substr($response, 0, 500) . "\n\n";
}
?>