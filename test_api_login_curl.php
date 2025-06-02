<?php
/**
 * Test script to verify API login functionality with cURL simulation
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing API login functionality with cURL simulation...\n\n";

// Test 1: Test admin/admin123 credentials
echo "Test 1: Testing admin/admin123 credentials\n";
echo "---------------------------------------\n";

$loginData = [
    'username' => 'admin',
    'password' => 'admin123'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/users.php?action=login');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($loginData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen(json_encode($loginData))
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "cURL Error: $error\n";
    echo "This likely means the web server isn't running on localhost:8000\n";
    echo "Try starting it with: php -S localhost:8000\n\n";
} else {
    echo "HTTP Code: $httpCode\n";
    echo "Response: $response\n\n";
}

// Test 2: Test testadmin/mypassword123 credentials  
echo "Test 2: Testing testadmin/mypassword123 credentials\n";
echo "-----------------------------------------------\n";

$loginData2 = [
    'username' => 'testadmin',
    'password' => 'mypassword123'
];

$ch2 = curl_init();
curl_setopt($ch2, CURLOPT_URL, 'http://localhost:8000/api/users.php?action=login');
curl_setopt($ch2, CURLOPT_POST, 1);
curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode($loginData2));
curl_setopt($ch2, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen(json_encode($loginData2))
]);
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_HEADER, true);

$response2 = curl_exec($ch2);
$httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
$error2 = curl_error($ch2);
curl_close($ch2);

if ($error2) {
    echo "cURL Error: $error2\n";
} else {
    echo "HTTP Code: $httpCode2\n";
    echo "Response: $response2\n\n";
}

// Test 3: Test wrong credentials
echo "Test 3: Testing wrong credentials\n";
echo "--------------------------------\n";

$loginData3 = [
    'username' => 'admin',
    'password' => 'wrongpassword'
];

$ch3 = curl_init();
curl_setopt($ch3, CURLOPT_URL, 'http://localhost:8000/api/users.php?action=login');
curl_setopt($ch3, CURLOPT_POST, 1);
curl_setopt($ch3, CURLOPT_POSTFIELDS, json_encode($loginData3));
curl_setopt($ch3, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen(json_encode($loginData3))
]);
curl_setopt($ch3, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch3, CURLOPT_HEADER, true);

$response3 = curl_exec($ch3);
$httpCode3 = curl_getinfo($ch3, CURLINFO_HTTP_CODE);
$error3 = curl_error($ch3);
curl_close($ch3);

if ($error3) {
    echo "cURL Error: $error3\n";
} else {
    echo "HTTP Code: $httpCode3\n";
    echo "Response: $response3\n\n";
}

echo "Test completed.\n";
echo "\nNOTE: If you see cURL errors, start the web server first with:\n";
echo "php -S localhost:8000\n";
?>
