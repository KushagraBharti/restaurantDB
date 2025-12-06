<?php
require_once "../config.php";
if (!isset($mysqli) && isset($conn)) {
    $mysqli = $conn;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Assignment 5 - Customer Lookup</title>
    <link rel="stylesheet" href="styles_a5.css">
</head>
<body>
<div class="container">
    <header>
        <h1>Customer Lookup Form</h1>
        <p>Assignment 5 - SQL Injection demo using the RestaurantDB Customer table.</p>
    </header>

    <section class="card">
        <h2>Lookup</h2>
        <form method="get">
            <label for="customer_name">Customer Name</label>
            <input type="text" id="customer_name" name="customer_name" placeholder="e.g., Rayyan Nour">

            <label for="phone_no">Phone Number</label>
            <input type="text" id="phone_no" name="phone_no" placeholder="e.g., 5551231234">

            <div class="button-row">
                <button type="submit" formaction="lookup_vulnerable.php">Run Part (a) – Vulnerable</button>
                <button type="submit" formaction="lookup_secure.php" class="secondary">Run Part (b) – Secure</button>
            </div>
        </form>
    </section>

    <section class="card">
        <h2>Sample Database Rows (for reference)</h2>
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Address</th>
                </tr>
            </thead>
            <tbody>
                <tr><td>Rayyan Nour</td><td>rayyan@gmail.com</td><td>5551231234</td><td>123 Main St</td></tr>
                <tr><td>Cristiano Ronaldo</td><td>cr7@gmail.com</td><td>5552221111</td><td>44 Oak Lane</td></tr>
                <tr><td>Lionel Messi</td><td>Lm10@gmail.com</td><td>5553334444</td><td>89 Elm St</td></tr>
                <tr><td>Vixen Blackmountain</td><td>VxB@gmail.com</td><td>65845248</td><td>89 Elm St</td></tr>
            </tbody>
        </table>
    </section>
</div>
</body>
</html>
