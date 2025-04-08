<?php
// Turn on error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "MySQL Connection Test\n\n";

echo "PHP PDO MySQL Extension: ";
if (extension_loaded('pdo_mysql')) {
    echo "LOADED\n";
} else {
    echo "NOT LOADED!\n";
}

echo "\nMySQL Connection Test:\n";

// Connection parameters - same as in .env
$host = '127.0.0.1';
$port = '3306';
$dbname = 'golems'; 
$username = 'root';
$password = 'password'; // Update with your password

try {
    // First try connecting without specifying a database
    echo "Attempting to connect to MySQL server without specifying a database...\n";
    $pdo = new PDO("mysql:host=$host;port=$port", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "SUCCESS: Connected to MySQL server!\n";
    
    // Try to create the database if it doesn't exist
    echo "Attempting to create database if it doesn't exist...\n";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $dbname");
    echo "SUCCESS: Database check/creation successful!\n";
    
    // Now try connecting with the database
    echo "Attempting to connect to specific database...\n";
    $pdo2 = new PDO("mysql:host=$host;port=$port;dbname=$dbname", $username, $password);
    $pdo2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "SUCCESS: Connected to database '$dbname'!\n";
    
    // Database info
    echo "\nMySQL Server Information:\n";
    echo "Server Version: " . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . "\n";
    echo "Server Info: " . $pdo->getAttribute(PDO::ATTR_SERVER_INFO) . "\n";
    echo "Connection Status: " . $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS) . "\n";
    
} catch (PDOException $e) {
    echo "ERROR: Connection failed: " . $e->getMessage() . "\n\n";
    
    echo "Additional Debug Information:\n";
    echo "Host: $host\n";
    echo "Port: $port\n";
    echo "Database: $dbname\n";
    echo "Username: $username\n";
    echo "Password: " . str_repeat("*", strlen($password)) . "\n";
}
?> 