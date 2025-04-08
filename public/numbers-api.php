<?php
// Turn off error display for production
ini_set('display_errors', 0);
error_reporting(0);

// Enable CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");

// Set JSON content type
header('Content-Type: application/json');

try {
    // Get 'after' parameter
    $afterId = isset($_GET['after']) ? (int)$_GET['after'] : 0;
    
    // Database credentials - hardcoded for direct connection
    // You can replace these with your actual credentials
    $host = 'localhost';
    $db = 'golems';
    $user = 'root';
    $pass = 'jpj';
    $port = '3306';
    
    // Try to read from .env file if it exists
    if (file_exists(__DIR__ . '/../.env')) {
        $envFile = file_get_contents(__DIR__ . '/../.env');
        if (preg_match('/DB_HOST=([^\n]+)/', $envFile, $matches)) {
            $host = trim($matches[1]);
        }
        if (preg_match('/DB_DATABASE=([^\n]+)/', $envFile, $matches)) {
            $db = trim($matches[1]);
        }
        if (preg_match('/DB_USERNAME=([^\n]+)/', $envFile, $matches)) {
            $user = trim($matches[1]);
        }
        if (preg_match('/DB_PASSWORD=([^\n]+)/', $envFile, $matches)) {
            $pass = trim($matches[1]);
        }
        if (preg_match('/DB_PORT=([^\n]+)/', $envFile, $matches)) {
            $port = trim($matches[1]);
        }
    }
    
    // Connect to database
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    // Query for numbers after the given ID
    $stmt = $pdo->prepare('SELECT * FROM numbers WHERE id > ? ORDER BY id DESC');
    $stmt->execute([$afterId]);
    $numbers = $stmt->fetchAll();
    
    // Return JSON response
    echo json_encode([
        'numbers' => $numbers,
        'count' => count($numbers),
        'timestamp' => date('c'),
        'requested_after_id' => $afterId
    ]);
    
} catch (Exception $e) {
    // Return error response
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
} 