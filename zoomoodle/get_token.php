<?php
$clientId = 'D9SL3ztxScWLjlPeCBFzBA';
$clientSecret = 'zSg8LFYiDSja5S3kK0fW3xZbY4B1dIhK';
$accountId = '4aWltUURV6WH9yBR_JRlvQ';

$url = 'https://zoom.us/oauth/token?grant_type=account_credentials&account_id=' . $accountId;

$headers = [
    'Authorization: Basic ' . base64_encode($clientId . ':' . $clientSecret),
    'Content-Type: application/x-www-form-urlencoded'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$response = curl_exec($ch);
curl_close($ch);

echo $response;
?>
