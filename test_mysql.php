<?php
// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
echo "PHP Version: " . phpversion() . "\n";

// MySQL connection parameters
$host = 'localhost';
$db   = 'golems';
$user = 'root';
$pass = 'jpj'; // The password you've been using successfully
$charset = 'utf8mb4';

echo "Testing MySQL connection...\n";
echo "Using: host=$host, db=$db, user=$user, pass=" . str_repeat('*', strlen($pass)) . "\n";

try {
    echo "Creating PDO DSN...\n";
    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    echo "DSN: $dsn\n";
    
    echo "Attempting to connect...\n";
    $pdo = new PDO($dsn, $user, $pass);
    
    echo "Setting PDO attributes...\n";
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "SUCCESS! Connected to MySQL database '$db'.\n";
    
    // Try a simple query
    echo "Running test query...\n";
    $stmt = $pdo->query('SELECT VERSION()');
    $version = $stmt->fetchColumn();
    echo "MySQL Version: $version\n";
    
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    
    // Additional debugging info
    echo "\nConnection parameters:\n";
    echo "Host: $host\n";
    echo "Database: $db\n";
    echo "Username: $user\n";
    echo "Password: " . str_repeat('*', strlen($pass)) . "\n";
}
?> 