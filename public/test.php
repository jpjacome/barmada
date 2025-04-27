<?php
// Simple test file to check if PHP is working

echo "<h1>PHP Test File</h1>";
echo "<p>This file is accessible. PHP is working correctly.</p>";
echo "<p>Current time: " . date('Y-m-d H:i:s') . "</p>";
echo "<hr>";
echo "<p>Request URI: " . $_SERVER['REQUEST_URI'] . "</p>";
echo "<p>Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</p>"; 