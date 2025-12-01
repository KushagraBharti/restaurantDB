<?php
require_once "config.php";

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES);
}

$success_message = "";
$error_message = "";

$statQueries = [
    "Customers" => "SELECT COUNT(*) AS cnt FROM Customers",
    "Orders" => "SELECT COUNT(*) AS cnt FROM Orders",
    "ActiveMenu" => "SELECT COUNT(*) AS cnt FROM MenuItems WHERE IsActive = 1",
    "Employees" => "SELECT COUNT(*) AS cnt FROM Employees",
    "Ingredients" => "SELECT COUNT(*) AS cnt FROM Ingredients"
];

$counts = [];
foreach ($statQueries as $key => $sql) {
    try {
        $result = $conn->query($sql);
        if ($result) {
            $row = $result->fetch_assoc();
            $counts[$key] = (int)$row["cnt"];
        }
    } catch (Exception $e) {
        $error_message = "Problem loading dashboard stats: " . $e->getMessage();
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Restaurant DB System - Home</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <header class="navbar">
    <div class="navbar-inner">
      <div class="navbar-title">Restaurant Database System</div>
      <nav class="nav-links">
        <a href="customer_order.php">Place Order</a>
        <a href="order_status.php">Orders</a>
        <a href="manage_menu_items.php">Menu Items</a>
        <a href="manage_ingredients.php">Ingredients</a>
        <a href="manage_employees.php">Employees</a>
      </nav>
    </div>
  </header>

  <main>
    <div class="container">
      <?php if (!empty($success_message)): ?>
        <div class="alert alert-success"><?php echo h($success_message); ?></div>
      <?php endif; ?>
      <?php if (!empty($error_message)): ?>
        <div class="alert alert-error"><?php echo h($error_message); ?></div>
      <?php endif; ?>

      <section class="card">
        <div class="card-header">
          <h1 class="page-title">Project Phase 4 - Functional Interfaces</h1>
        </div>
        <p class="page-subtitle">
          Minimal PHP + MySQL front-end for the Restaurant Database System.
          These pages match our EER diagram and relational schema and now connect to live data.
        </p>
      </section>

      <section class="card">
        <div class="card-header">
          <h2 class="card-title">Main Screens</h2>
        </div>
        <div class="form-grid form-grid-2">
          <a class="btn btn-outline" href="customer_order.php">🚀 Place / Edit Order</a>
          <a class="btn btn-outline" href="order_status.php">🔎 Find Orders</a>
          <a class="btn btn-outline" href="manage_menu_items.php">📋 Manage Menu Items</a>
          <a class="btn btn-outline" href="manage_ingredients.php">🥕 Manage Ingredients</a>
          <a class="btn btn-outline" href="manage_employees.php">👥 Manage Employees</a>
        </div>
      </section>
    </div>
  </main>
</body>
</html>
