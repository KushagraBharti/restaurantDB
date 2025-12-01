<?php
require_once "config.php";

$success_message = "";
$error_message = "";

// Optional quick stats for dashboard
$counts = [
    "Customers" => null,
    "Employees" => null,
    "MenuItems" => null,
    "Orders" => null
];

foreach (array_keys($counts) as $table) {
    $sql = "SELECT COUNT(*) AS cnt FROM {$table}";
    $result = $conn->query($sql);
    if ($result) {
        $row = $result->fetch_assoc();
        $counts[$table] = (int)$row["cnt"];
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
        <p class="helper-text" style="color:#22c55e;"><?php echo htmlspecialchars($success_message); ?></p>
      <?php endif; ?>
      <?php if (!empty($error_message)): ?>
        <p class="helper-text" style="color:#f97373;"><?php echo htmlspecialchars($error_message); ?></p>
      <?php endif; ?>

      <section class="card">
        <div class="card-header">
          <h1 class="page-title">Project Phase 4 - Functional Interfaces</h1>
        </div>
        <p class="page-subtitle">
          Minimal PHP + MySQL front-end for the Restaurant Database System.
          These pages match our EER diagram and relational schema and now connect to live data.
        </p>
        <div class="form-grid form-grid-2">
          <div>
            <h2 class="card-title">Core Entities</h2>
            <ul class="helper-text" style="margin-top:0.5rem; list-style:disc; padding-left:1.2rem;">
              <li>Customers (<?php echo $counts["Customers"] ?? "N/A"; ?>)</li>
              <li>Employees (<?php echo $counts["Employees"] ?? "N/A"; ?>)</li>
              <li>MenuItems (<?php echo $counts["MenuItems"] ?? "N/A"; ?>)</li>
              <li>Ingredients</li>
              <li>Orders (<?php echo $counts["Orders"] ?? "N/A"; ?>)</li>
              <li>OrderItems</li>
              <li>RecipeComponents</li>
              <li>Payments</li>
            </ul>
          </div>
          <div>
            <h2 class="card-title">Main Screens</h2>
            <ul class="helper-text" style="margin-top:0.5rem; list-style:disc; padding-left:1.2rem;">
              <li>Place new customer orders</li>
              <li>Search and view orders</li>
              <li>Manage menu items &amp; recipe components</li>
              <li>Manage ingredients and stock levels</li>
              <li>Manage employees</li>
            </ul>
          </div>
        </div>
      </section>

      <section class="card">
        <div class="card-header">
          <h2 class="card-title">Quick Navigation</h2>
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
