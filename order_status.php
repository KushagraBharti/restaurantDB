<?php
require_once "config.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$success_message = "";
$error_message = "";
$orders = [];

// Capture filters
$filters = [
    "OrderID" => isset($_GET["OrderID"]) ? trim($_GET["OrderID"]) : "",
    "CustomerID" => isset($_GET["CustomerID"]) ? trim($_GET["CustomerID"]) : "",
    "Status" => isset($_GET["Status"]) ? trim($_GET["Status"]) : "",
    "Channel" => isset($_GET["Channel"]) ? trim($_GET["Channel"]) : "",
    "DateFrom" => isset($_GET["DateFrom"]) ? trim($_GET["DateFrom"]) : "",
    "DateTo" => isset($_GET["DateTo"]) ? trim($_GET["DateTo"]) : ""
];

try {
    $sql = "
        SELECT 
          o.OrderID,
          o.CustomerID,
          c.Name AS CustomerName,
          o.EmployeeID,
          e.Name AS EmployeeName,
          o.OrderDate,
          o.Status,
          o.Channel,
          o.TotalAmount
        FROM Orders o
        LEFT JOIN Customers c ON o.CustomerID = c.CustomerID
        LEFT JOIN Employees e ON o.EmployeeID = e.EmployeeID
        WHERE 1=1
    ";

    $params = [];
    $types = "";

    if ($filters["OrderID"] !== "") {
        $sql .= " AND o.OrderID = ?";
        $types .= "i";
        $params[] = (int)$filters["OrderID"];
    }
    if ($filters["CustomerID"] !== "") {
        $sql .= " AND o.CustomerID = ?";
        $types .= "i";
        $params[] = (int)$filters["CustomerID"];
    }
    if ($filters["Status"] !== "") {
        $sql .= " AND o.Status = ?";
        $types .= "s";
        $params[] = $filters["Status"];
    }
    if ($filters["Channel"] !== "") {
        $sql .= " AND o.Channel = ?";
        $types .= "s";
        $params[] = $filters["Channel"];
    }
    if ($filters["DateFrom"] !== "") {
        $sql .= " AND o.OrderDate >= ?";
        $types .= "s";
        $params[] = $filters["DateFrom"];
    }
    if ($filters["DateTo"] !== "") {
        $sql .= " AND o.OrderDate <= ?";
        $types .= "s";
        $params[] = $filters["DateTo"];
    }

    $sql .= " ORDER BY o.OrderDate DESC, o.OrderID DESC";

    $stmt = $conn->prepare($sql);
    if ($types !== "" && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    $error_message = "Error fetching orders: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Order Search - Restaurant DB System</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <header class="navbar">
    <div class="navbar-inner">
      <div class="navbar-title">Restaurant Database System</div>
      <nav class="nav-links">
        <a href="index.php">Home</a>
        <a href="customer_order.php">Place Order</a>
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
          <h1 class="page-title">Order Search &amp; Status</h1>
          <span class="tag">Orders, Customers, Employees, Payments</span>
        </div>
        <p class="page-subtitle">
          Search orders using live data from <strong>Orders</strong> joined with <strong>Customers</strong> and <strong>Employees</strong>.
        </p>

        <form action="order_status.php" method="get">
          <div class="form-grid form-grid-2">
            <div class="form-group">
              <label for="searchOrderId">Order ID</label>
              <input id="searchOrderId" name="OrderID" type="text" value="<?php echo htmlspecialchars($filters["OrderID"]); ?>" placeholder="e.g. 1001">
            </div>
            <div class="form-group">
              <label for="searchCustomerId">Customer ID</label>
              <input id="searchCustomerId" name="CustomerID" type="text" value="<?php echo htmlspecialchars($filters["CustomerID"]); ?>" placeholder="e.g. 1">
            </div>
            <div class="form-group">
              <label for="searchStatus">Status</label>
              <select id="searchStatus" name="Status">
                <option value="">Any status</option>
                <?php foreach (["Pending", "In Progress", "Completed", "Cancelled"] as $opt): ?>
                  <option value="<?php echo $opt; ?>" <?php echo ($filters["Status"] === $opt) ? "selected" : ""; ?>><?php echo $opt; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="searchChannel">Channel</label>
              <select id="searchChannel" name="Channel">
                <option value="">Any channel</option>
                <?php foreach (["In-Person", "Online"] as $opt): ?>
                  <option value="<?php echo $opt; ?>" <?php echo ($filters["Channel"] === $opt) ? "selected" : ""; ?>><?php echo $opt; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="dateFrom">Order Date (from)</label>
              <input id="dateFrom" name="DateFrom" type="date" value="<?php echo htmlspecialchars($filters["DateFrom"]); ?>">
            </div>
            <div class="form-group">
              <label for="dateTo">Order Date (to)</label>
              <input id="dateTo" name="DateTo" type="date" value="<?php echo htmlspecialchars($filters["DateTo"]); ?>">
            </div>
          </div>
          <div class="btn-row">
            <button type="submit" class="btn btn-primary">Search</button>
            <button type="reset" class="btn btn-outline" onclick="window.location='order_status.php'; return false;">Clear Filters</button>
          </div>
        </form>
      </section>

      <section class="card">
        <div class="card-header">
          <h2 class="card-title">Results</h2>
          <span class="card-note">Showing live orders from the database.</span>
        </div>
        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>OrderID</th>
                <th>Customer</th>
                <th>Employee</th>
                <th>OrderDate</th>
                <th>Status</th>
                <th>Channel</th>
                <th>TotalAmount</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($orders)): ?>
                <tr>
                  <td colspan="7" style="text-align:center;">No orders found.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($orders as $row): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($row["OrderID"]); ?></td>
                    <td><?php echo htmlspecialchars($row["CustomerID"] . " - " . ($row["CustomerName"] ?? "")); ?></td>
                    <td><?php echo htmlspecialchars(($row["EmployeeID"] ? $row["EmployeeID"] : "N/A") . " " . ($row["EmployeeName"] ?? "")); ?></td>
                    <td><?php echo htmlspecialchars($row["OrderDate"]); ?></td>
                    <td><?php echo htmlspecialchars($row["Status"]); ?></td>
                    <td><?php echo htmlspecialchars($row["Channel"]); ?></td>
                    <td><?php echo htmlspecialchars(number_format((float)$row["TotalAmount"], 2)); ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </main>
</body>
</html>
