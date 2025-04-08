<?php
// MODIFY THIS PASSWORD to match your MySQL root password
$password = 'jpj'; 

try {
    $pdo = new PDO(
        'mysql:host=127.0.0.1;port=3306;dbname=golems',
        'root',
        $password
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "SUCCESS! Connected to MySQL with password: $password\n";
} catch (PDOException $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
}
?> 