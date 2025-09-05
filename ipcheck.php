<?php

$curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => 'https://api1.indiplex.co.in/verification/ip',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 0,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'POST',
  CURLOPT_HTTPHEADER => array(
        'Content-Type: application/json',
        'x-api-key: fO8d2yHFZUtbVUULQTiMLwgopRoYppHwePiXcchFVqw',
        'Authorization: Basic SU5ESTExNTpNakF5TlRBME1EUXhOelU1TWpnPQ=='
    ),
));

$response = curl_exec($curl);

curl_close($curl);
echo $response;