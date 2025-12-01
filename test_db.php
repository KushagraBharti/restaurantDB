<?php
require_once 'config.php';

// Simple sanity check: count orders
$sql = "SELECT COUNT(*) AS cnt FROM Orders";
$result = $conn->query($sql);

if (!$result) {
    die('Query failed: ' . $conn->error);
}

$row = $result->fetch_assoc();
echo "<h1>RestaurantDB connection OK ✅</h1>";
echo "<p>Number of rows in Orders: " . (int)$row['cnt'] . "</p>";

$conn->close();
?>
