<?php
// Test passwords
$passwords = [
    '', 
    'root', 
    'password', 
    'mysql', 
    'admin',
    // Add more common passwords here
];

foreach ($passwords as $password) {
    echo "Testing password: " . ($password === '' ? '(empty)' : $password) . "\n";
    
    try {
        $pdo = new PDO(
            'mysql:host=127.0.0.1;port=3306;dbname=golems',
            'root',
            $password
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "SUCCESS! Password '$password' works.\n";
        exit; // Stop if we find a working password
    } catch (PDOException $e) {
        echo "Failed: " . $e->getMessage() . "\n\n";
    }
}

echo "None of the passwords worked.\n";
?> 