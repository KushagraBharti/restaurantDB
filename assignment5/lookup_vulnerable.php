<?php
// assignment5/lookup_vulnerable.php
require_once "../config.php";

// Normalize connection variable: $mysqli (used in most of the project)
if (!isset($mysqli) && isset($conn)) {
    $mysqli = $conn;
}

$customerName = isset($_GET['customer_name']) ? trim($_GET['customer_name']) : '';
$Phone      = isset($_GET['phone_no']) ? trim($_GET['phone_no']) : '';

$rows    = [];
$error   = '';
$message = '';
$sql     = '';

if ($customerName === '' || $Phone === '') {
    $message = "Please provide both name and phone number.";
} else {
    // INTENTIONALLY VULNERABLE: direct string concatenation
    $sql = "
        SELECT CustomerID, Name, Email, Phone, Address
        FROM Customers
        WHERE Name = '" . $customerName . "'
          AND Phone = '" . $Phone . "'
    ";

    try {
        $result = $mysqli->query($sql);
        if ($result === false) {
            $error = "Database error: " . $mysqli->error;
        } else {
            $rows = $result->fetch_all(MYSQLI_ASSOC);
        }
    } catch (mysqli_sql_exception $e) {
        $error = "Database error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customer Lookup – Part (a) Vulnerable</title>
    <link rel="stylesheet" href="styles_a5.css">
</head>
<body>
<div class="container">
    <header>
        <h1>Customer Lookup – Part (a) Vulnerable Version</h1>
        <p>This version concatenates user input directly into SQL (intentionally unsafe).</p>
        <p><a href="customer_lookup.php">&larr; Back to lookup form</a></p>
    </header>

    <?php if ($message): ?>
        <div class="notice"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="notice error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <section class="card">
        <h2>Constructed SQL (unsafe):</h2>
        <pre><?php echo $sql !== '' ? htmlspecialchars($sql) : 'No query executed.'; ?></pre>
    </section>

    <section class="card">
        <h2>Results</h2>
        <?php if (!empty($rows)): ?>
            <table>
                <thead>
                    <tr>
                        <th>CustomerID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Address</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['CustomerID']); ?></td>
                        <td><?php echo htmlspecialchars($row['Name']); ?></td>
                        <td><?php echo htmlspecialchars($row['Email']); ?></td>
                        <td><?php echo htmlspecialchars($row['Phone']); ?></td>
                        <td><?php echo htmlspecialchars($row['Address']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No matching rows returned.</p>
        <?php endif; ?>
    </section>
</div>
</body>
</html>
