<?php
// config.php
// Central DB connection for RestaurantDB (XAMPP default settings)

// Adjust these if you ever change XAMPP defaults
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';           // empty string by default in XAMPP
$DB_NAME = 'RestaurantDB';

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

// Optional: set charset to utf8mb4 for safety
if (!$conn->set_charset('utf8mb4')) {
    // Not fatal, but good to know if something’s wrong
    // echo 'Error loading charset utf8mb4: ' . $conn->error;
}
?>
