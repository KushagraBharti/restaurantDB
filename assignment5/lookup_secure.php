<?php
// assignment5/lookup_secure.php
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

// PREPARED STATEMENT TEMPLATE (always defined so it can be printed)
$sqlTemplate = "
    SELECT CustomerID, Name, Email, Phone, Address
    FROM Customers
    WHERE Name = ? AND Phone = ?
";

if ($customerName === '' || $Phone === '') {
    $message = "Please provide both name and phone number.";
} else {
    try {
        $stmt = $mysqli->prepare($sqlTemplate);
        if (!$stmt) {
            $error = "Database error: " . $mysqli->error;
        } else {
            $stmt->bind_param("ss", $customerName, $Phone);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                $rows   = $result->fetch_all(MYSQLI_ASSOC);
            } else {
                $error = "Database error: " . $stmt->error;
            }
            $stmt->close();
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
    <title>Customer Lookup - Part (b) Secure</title>
    <link rel="stylesheet" href="styles_a5.css">
</head>
<body>
<div class="container">
    <header>
        <h1>Customer Lookup - Part (b) Secure Version</h1>
        <p>This version uses prepared statements to prevent SQL injection.</p>
        <p><a href="customer_lookup.php">&larr; Back to lookup form</a></p>
    </header>

    <?php if ($message): ?>
        <div class="notice"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="notice error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <section class="card">
        <h2>Prepared SQL template:</h2>
        <pre><?php echo htmlspecialchars($sqlTemplate); ?></pre>
        <p><strong>Parameters:</strong>
            Name = <?php echo htmlspecialchars($customerName); ?>,
            Phone = <?php echo htmlspecialchars($Phone); ?>
        </p>
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
