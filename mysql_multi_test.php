<?php
// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "MySQL Multiple Connection Tests\n\n";

// Test configurations to try
$configs = [
    [
        'name' => 'Default Config (root/password)',
        'dsn' => 'mysql:host=127.0.0.1;port=3306',
        'username' => 'root',
        'password' => 'password'
    ],
    [
        'name' => 'No Password',
        'dsn' => 'mysql:host=127.0.0.1;port=3306',
        'username' => 'root',
        'password' => ''
    ],
    [
        'name' => 'Local Socket Connection',
        'dsn' => 'mysql:host=localhost;port=3306',
        'username' => 'root',
        'password' => 'password'
    ],
    [
        'name' => 'Local Socket No Password',
        'dsn' => 'mysql:host=localhost;port=3306',
        'username' => 'root',
        'password' => ''
    ],
    [
        'name' => 'Using Default MySQL Port',
        'dsn' => 'mysql:host=127.0.0.1',
        'username' => 'root',
        'password' => 'password'
    ],
    [
        'name' => 'Alternative User',
        'dsn' => 'mysql:host=127.0.0.1;port=3306',
        'username' => 'admin',
        'password' => 'password'
    ]
];

// Try each configuration
foreach ($configs as $config) {
    echo "Testing: " . $config['name'] . "\n";
    echo "DSN: " . $config['dsn'] . "\n";
    echo "Username: " . $config['username'] . "\n";
    echo "Password: " . str_repeat("*", strlen($config['password'])) . "\n";
    
    try {
        $pdo = new PDO($config['dsn'], $config['username'], $config['password']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "RESULT: SUCCESS - Connected to MySQL server!\n";
        
        // If we successfully connected, show server info
        echo "Server Version: " . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . "\n";
        echo "Connection Status: " . $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS) . "\n\n";
        
    } catch (PDOException $e) {
        echo "RESULT: FAILED - " . $e->getMessage() . "\n\n";
    }
    
    echo "----------------------------------------\n\n";
}

// Also test with mysqli
echo "Testing with mysqli:\n";
try {
    $mysqli = new mysqli('127.0.0.1', 'root', 'password', '', 3306);
    if ($mysqli->connect_errno) {
        echo "RESULT: FAILED - mysqli: " . $mysqli->connect_error . "\n\n";
    } else {
        echo "RESULT: SUCCESS - Connected with mysqli!\n";
        echo "MySQL Info: " . $mysqli->server_info . "\n\n";
    }
} catch (Exception $e) {
    echo "RESULT: FAILED - " . $e->getMessage() . "\n\n";
}

echo "----------------------------------------\n\n";

// PHP Info about MySQL
echo "PHP MySQL Information:\n";
echo "PDO MySQL Loaded: " . (extension_loaded('pdo_mysql') ? 'Yes' : 'No') . "\n";
echo "MySQLi Loaded: " . (extension_loaded('mysqli') ? 'Yes' : 'No') . "\n";
?> 