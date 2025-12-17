<?php
// test_login.php
$url = 'http://localhost/Individual_project_webtech/auth.php?action=login';
$data = ['username' => 'player1', 'password' => 'password'];

$options = [
    'http' => [
        'header'  => "Content-type: application/json\r\n",
        'method'  => 'POST',
        'content' => json_encode($data),
        'ignore_errors' => true // Fetch content even on 401/400
    ]
];

$context  = stream_context_create($options);
$result = file_get_contents($url, false, $context);

echo "HTTP Response Header: " . $http_response_header[0] . "\n";
echo "Raw Response Body:\n";
var_dump($result);
?>
