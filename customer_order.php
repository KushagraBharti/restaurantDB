<?php
require_once "config.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES);
}

function fetchEmployees($conn)
{
    $stmt = $conn->prepare("SELECT EmployeeID, Name FROM Employees ORDER BY Name");
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $result;
}

function fetchMenuItems($conn)
{
    $stmt = $conn->prepare("SELECT ItemID, Name FROM MenuItems WHERE IsActive = 1 ORDER BY Name");
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $result;
}

function fetchCustomers($conn)
{
    $stmt = $conn->prepare("SELECT CustomerID, Name FROM Customers ORDER BY Name");
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $result;
}

function fetchOrderData($conn, $orderId)
{
    $stmt = $conn->prepare("SELECT OrderID, CustomerID, EmployeeID, OrderDate, Status, Channel, TotalAmount FROM Orders WHERE OrderID = ?");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $orderRes = $stmt->get_result();
    $orderRow = $orderRes->fetch_assoc();
    $stmt->close();
    if (!$orderRow) {
        return null;
    }

    $customer = ["CustomerID" => $orderRow["CustomerID"], "Name" => "", "Email" => "", "Phone" => "", "Address" => ""];
    $stmt = $conn->prepare("SELECT CustomerID, Name, Email, Phone, Address FROM Customers WHERE CustomerID = ?");
    $stmt->bind_param("i", $orderRow["CustomerID"]);
    $stmt->execute();
    $custRes = $stmt->get_result();
    if ($custRow = $custRes->fetch_assoc()) {
        $customer = $custRow;
    }
    $stmt->close();

    $items = [];
    $stmt = $conn->prepare("SELECT LineNo, ItemID, Quantity FROM OrderItems WHERE OrderID = ? ORDER BY LineNo");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $payment = ["PaymentNo" => 1, "Method" => "Cash", "Amount" => "", "Status" => "Pending"];
    $stmt = $conn->prepare("SELECT PaymentNo, Method, Amount, Status FROM Payments WHERE OrderID = ? ORDER BY PaymentNo ASC LIMIT 1");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $payRes = $stmt->get_result();
    if ($payRow = $payRes->fetch_assoc()) {
        $payment = $payRow;
    }
    $stmt->close();

    return [
        "order_id" => $orderRow["OrderID"],
        "customer_mode" => "existing",
        "existing_customer_id" => $orderRow["CustomerID"],
        "customer" => $customer,
        "order" => [
            "EmployeeID" => $orderRow["EmployeeID"],
            "OrderDate" => $orderRow["OrderDate"],
            "Status" => $orderRow["Status"],
            "Channel" => $orderRow["Channel"],
            "TotalAmount" => $orderRow["TotalAmount"]
        ],
        "items" => $items,
        "payment" => $payment
    ];
}

function padItemRows($items, $minRows = 3, $extraBlank = 2)
{
    $items = array_values($items);
    if (empty($items)) {
        for ($i = 0; $i < $minRows; $i++) {
            $items[] = ["LineNo" => $i + 1, "ItemID" => "", "Quantity" => ""];
        }
        return $items;
    }
    $maxLine = 0;
    foreach ($items as $row) {
        $maxLine = max($maxLine, (int)$row["LineNo"]);
    }
    for ($i = 0; $i < $extraBlank; $i++) {
        $items[] = ["LineNo" => $maxLine + $i + 1, "ItemID" => "", "Quantity" => ""];
    }
    return $items;
}

$success_message = isset($_GET["msg"]) ? trim($_GET["msg"]) : "";
$error_message = "";

$mode = (isset($_GET["mode"]) && $_GET["mode"] === "edit") ? "edit" : "create";
$orderIdParam = isset($_GET["order_id"]) ? (int)$_GET["order_id"] : 0;

$employees = fetchEmployees($conn);
$menuItems = fetchMenuItems($conn);
$customersList = fetchCustomers($conn);

$orderData = [
    "order_id" => "",
    "customer_mode" => "new",
    "existing_customer_id" => "",
    "customer" => ["CustomerID" => "", "Name" => "", "Email" => "", "Phone" => "", "Address" => ""],
    "order" => [
        "EmployeeID" => "",
        "OrderDate" => date("Y-m-d"),
        "Status" => "Pending",
        "Channel" => "In-Person",
        "TotalAmount" => ""
    ],
    "items" => [],
    "payment" => ["PaymentNo" => 1, "Method" => "Cash", "Amount" => "", "Status" => "Pending"]
];

if ($mode === "edit" && $orderIdParam > 0) {
    $loaded = fetchOrderData($conn, $orderIdParam);
    if ($loaded) {
        $orderData = $loaded;
        $orderData["customer_mode"] = "existing";
        $orderData["existing_customer_id"] = $loaded["customer"]["CustomerID"];
    } else {
        $error_message = "Order not found. Switched to create mode.";
        $mode = "create";
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "save_order") {
    $orderData["order_id"] = isset($_POST["OrderID"]) && $_POST["OrderID"] !== "" ? (int)$_POST["OrderID"] : "";
    $mode = $_POST["mode"] ?? $mode;
    $orderIdParam = $orderData["order_id"] ?: 0;

    $orderData["customer_mode"] = ($_POST["customer_mode"] ?? "new") === "existing" ? "existing" : "new";
    $orderData["existing_customer_id"] = trim($_POST["ExistingCustomerID"] ?? "");
    $orderData["customer"]["CustomerID"] = $orderData["existing_customer_id"];
    $orderData["customer"]["Name"] = trim($_POST["CustomerName"] ?? "");
    $orderData["customer"]["Email"] = trim($_POST["Email"] ?? "");
    $orderData["customer"]["Phone"] = trim($_POST["Phone"] ?? "");
    $orderData["customer"]["Address"] = trim($_POST["Address"] ?? "");

    $orderData["order"]["EmployeeID"] = trim($_POST["EmployeeID"] ?? "");
    $orderData["order"]["OrderDate"] = trim($_POST["OrderDate"] ?? date("Y-m-d"));
    $orderData["order"]["Status"] = trim($_POST["Status"] ?? "Pending");
    $orderData["order"]["Channel"] = trim($_POST["Channel"] ?? "In-Person");
    $orderData["order"]["TotalAmount"] = trim($_POST["TotalAmount"] ?? "");

    $orderData["payment"]["PaymentNo"] = trim($_POST["PaymentNo"] ?? 1);
    $orderData["payment"]["Method"] = trim($_POST["Method"] ?? "Cash");
    $orderData["payment"]["Amount"] = trim($_POST["Amount"] ?? "");
    $orderData["payment"]["Status"] = trim($_POST["StatusPayment"] ?? "Pending");

    $lineNos = $_POST["LineNo"] ?? [];
    $itemIds = $_POST["ItemID"] ?? [];
    $quantities = $_POST["Quantity"] ?? [];
    $orderData["items"] = [];
    $rows = max(count($lineNos), count($itemIds), count($quantities));
    for ($i = 0; $i < $rows; $i++) {
        $orderData["items"][] = [
            "LineNo" => $lineNos[$i] ?? ($i + 1),
            "ItemID" => $itemIds[$i] ?? "",
            "Quantity" => $quantities[$i] ?? ""
        ];
    }

    $errors = [];

    if ($orderData["customer_mode"] === "existing") {
        if ($orderData["existing_customer_id"] === "" || !is_numeric($orderData["existing_customer_id"])) {
            $errors[] = "Please select an existing customer.";
        }
    } else {
        if ($orderData["customer"]["Name"] === "") {
            $errors[] = "Customer name is required.";
        }
    }

    if ($orderData["order"]["OrderDate"] === "") {
        $errors[] = "Order Date is required.";
    }
    if ($orderData["order"]["Status"] === "") {
        $errors[] = "Order Status is required.";
    }
    if ($orderData["order"]["Channel"] === "") {
        $errors[] = "Order Channel is required.";
    }
    if ($orderData["order"]["TotalAmount"] === "" || !is_numeric($orderData["order"]["TotalAmount"]) || $orderData["order"]["TotalAmount"] < 0) {
        $errors[] = "Total Amount must be a non-negative number.";
    }

    $itemsToInsert = [];
    foreach ($orderData["items"] as $idx => $row) {
        $itemIdVal = trim((string)$row["ItemID"]);
        $qtyVal = $row["Quantity"];
        $lineNoVal = $row["LineNo"] !== "" ? (int)$row["LineNo"] : ($idx + 1);

        if ($itemIdVal === "" && ($qtyVal === "" || $qtyVal === null)) {
            continue;
        }
        if ($itemIdVal === "" || !is_numeric($itemIdVal)) {
            $errors[] = "ItemID is required for line " . ($idx + 1) . ".";
        }
        if ($qtyVal === "" || !is_numeric($qtyVal) || (int)$qtyVal < 1) {
            $errors[] = "Quantity must be a positive integer for line " . ($idx + 1) . ".";
        }
        if (empty($errors) || (is_numeric($itemIdVal) && is_numeric($qtyVal) && (int)$qtyVal > 0)) {
            $itemsToInsert[] = [
                "LineNo" => $lineNoVal,
                "ItemID" => (int)$itemIdVal,
                "Quantity" => (int)$qtyVal
            ];
        }
    }
    if (empty($itemsToInsert)) {
        $errors[] = "At least one order item is required.";
    }

    if ($orderData["payment"]["PaymentNo"] === "" || !is_numeric($orderData["payment"]["PaymentNo"]) || (int)$orderData["payment"]["PaymentNo"] < 1) {
        $errors[] = "Payment No must be a positive integer.";
    }
    if ($orderData["payment"]["Method"] === "") {
        $errors[] = "Payment Method is required.";
    }
    if ($orderData["payment"]["Amount"] === "" || !is_numeric($orderData["payment"]["Amount"]) || $orderData["payment"]["Amount"] < 0) {
        $errors[] = "Payment Amount must be a non-negative number.";
    }
    if ($orderData["payment"]["Status"] === "") {
        $errors[] = "Payment Status is required.";
    }

    if (!empty($errors)) {
        $error_message = implode(" | ", $errors);
    } else {
        try {
            $conn->begin_transaction();

            if ($orderData["customer_mode"] === "existing") {
                $customerId = (int)$orderData["existing_customer_id"];
                $stmt = $conn->prepare("SELECT CustomerID FROM Customers WHERE CustomerID = ?");
                $stmt->bind_param("i", $customerId);
                $stmt->execute();
                $stmt->store_result();
                if ($stmt->num_rows === 0) {
                    throw new Exception("Selected customer not found.");
                }
                $stmt->free_result();
                $stmt->close();
            } else {
                $stmt = $conn->prepare("INSERT INTO Customers (Name, Email, Phone, Address) VALUES (?, ?, ?, ?)");
                $stmt->bind_param(
                    "ssss",
                    $orderData["customer"]["Name"],
                    $orderData["customer"]["Email"],
                    $orderData["customer"]["Phone"],
                    $orderData["customer"]["Address"]
                );
                $stmt->execute();
                $customerId = $conn->insert_id;
                $stmt->close();
            }

            $employeeId = $orderData["order"]["EmployeeID"] === "" ? null : (int)$orderData["order"]["EmployeeID"];
            $orderDate = $orderData["order"]["OrderDate"] ?: date("Y-m-d");
            $status = $orderData["order"]["Status"];
            $channel = $orderData["order"]["Channel"];
            $totalAmount = (float)$orderData["order"]["TotalAmount"];

            $orderExists = false;
            if ($orderIdParam > 0) {
                $stmt = $conn->prepare("SELECT OrderID FROM Orders WHERE OrderID = ?");
                $stmt->bind_param("i", $orderIdParam);
                $stmt->execute();
                $stmt->store_result();
                if ($stmt->num_rows > 0) {
                    $orderExists = true;
                }
                $stmt->free_result();
                $stmt->close();
            }

            if ($orderExists) {
                $orderId = $orderIdParam;
                $stmt = $conn->prepare("UPDATE Orders SET CustomerID = ?, EmployeeID = ?, OrderDate = ?, Status = ?, Channel = ?, TotalAmount = ? WHERE OrderID = ?");
                $stmt->bind_param("iisssdi", $customerId, $employeeId, $orderDate, $status, $channel, $totalAmount, $orderId);
                $stmt->execute();
                $stmt->close();
            } else {
                $stmt = $conn->prepare("INSERT INTO Orders (CustomerID, EmployeeID, OrderDate, Status, Channel, TotalAmount) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("iisssd", $customerId, $employeeId, $orderDate, $status, $channel, $totalAmount);
                $stmt->execute();
                $orderId = $conn->insert_id;
                $stmt->close();
            }

            $stmt = $conn->prepare("DELETE FROM OrderItems WHERE OrderID = ?");
            $stmt->bind_param("i", $orderId);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("INSERT INTO OrderItems (OrderID, LineNo, ItemID, Quantity) VALUES (?, ?, ?, ?)");
            foreach ($itemsToInsert as $row) {
                $stmt->bind_param("iiii", $orderId, $row["LineNo"], $row["ItemID"], $row["Quantity"]);
                $stmt->execute();
            }
            $stmt->close();

            $payNo = (int)$orderData["payment"]["PaymentNo"];
            $payMethod = $orderData["payment"]["Method"];
            $payAmount = (float)$orderData["payment"]["Amount"];
            $payStatus = $orderData["payment"]["Status"];

            $stmt = $conn->prepare(
                "INSERT INTO Payments (OrderID, PaymentNo, Method, Amount, Status)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE Method = VALUES(Method), Amount = VALUES(Amount), Status = VALUES(Status)"
            );
            $stmt->bind_param("iisds", $orderId, $payNo, $payMethod, $payAmount, $payStatus);
            $stmt->execute();
            $stmt->close();

            $conn->commit();

            $success_message = "Order #{$orderId} saved successfully.";
            $mode = "edit";
            $orderIdParam = $orderId;
            $orderData = fetchOrderData($conn, $orderId);
            $orderData["customer_mode"] = "existing";
            $orderData["existing_customer_id"] = $orderData["customer"]["CustomerID"];
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Error saving order: " . $e->getMessage();
        }
    }
}

$orderData["items"] = padItemRows($orderData["items"]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Place Order - Restaurant DB System</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <header class="navbar">
    <div class="navbar-inner">
      <div class="navbar-title">Restaurant Database System</div>
      <nav class="nav-links">
        <a href="index.php">Home</a>
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
        <div class="alert alert-success"><?php echo h($success_message); ?> <a class="pill-link" href="order_status.php">Back to Orders List</a></div>
      <?php endif; ?>
      <?php if (!empty($error_message)): ?>
        <div class="alert alert-error"><?php echo h($error_message); ?></div>
      <?php endif; ?>

      <section class="card">
        <div class="card-header">
          <h1 class="page-title"><?php echo $mode === "edit" ? "Edit Order #" . h($orderIdParam) : "Create New Order"; ?></h1>
          <span class="tag">Orders, Customers, OrderItems, Payments</span>
        </div>
        <p class="page-subtitle">
          Create or update orders with linked customers, items, and payments. Use the actions in Order Search to edit or delete.
        </p>

        <form action="customer_order.php<?php echo $mode === 'edit' ? '?mode=edit&order_id=' . h($orderIdParam) : ''; ?>" method="post">
          <input type="hidden" name="action" value="save_order">
          <input type="hidden" name="mode" value="<?php echo h($mode); ?>">
          <input type="hidden" name="OrderID" value="<?php echo h($orderIdParam); ?>">

          <div class="card" style="margin-bottom:1.25rem;">
            <div class="card-header">
              <h2 class="card-title">Customer</h2>
              <span class="card-note">Use an existing customer or create a new one.</span>
            </div>
            <div class="form-grid form-grid-2">
              <div class="form-group checkbox-row">
                <input type="radio" id="custExisting" name="customer_mode" value="existing" <?php echo $orderData["customer_mode"] === "existing" ? "checked" : ""; ?>>
                <label for="custExisting">Use existing customer</label>
              </div>
              <div class="form-group">
                <select name="ExistingCustomerID">
                  <option value="">Select customer</option>
                  <?php foreach ($customersList as $cust): ?>
                    <option value="<?php echo h($cust["CustomerID"]); ?>" <?php echo ($orderData["existing_customer_id"] !== "" && (int)$orderData["existing_customer_id"] === (int)$cust["CustomerID"]) ? "selected" : ""; ?>>
                      <?php echo h($cust["CustomerID"] . " - " . $cust["Name"]); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group checkbox-row">
                <input type="radio" id="custNew" name="customer_mode" value="new" <?php echo $orderData["customer_mode"] === "new" ? "checked" : ""; ?>>
                <label for="custNew">Create new customer</label>
              </div>
            </div>
            <div class="form-grid form-grid-2">
              <div class="form-group">
                <label for="customerName">Name</label>
                <input id="customerName" name="CustomerName" type="text" value="<?php echo h($orderData["customer"]["Name"]); ?>" placeholder="Customer full name">
              </div>
              <div class="form-group">
                <label for="customerEmail">Email</label>
                <input id="customerEmail" name="Email" type="email" value="<?php echo h($orderData["customer"]["Email"]); ?>" placeholder="name@example.com">
              </div>
              <div class="form-group">
                <label for="customerPhone">Phone</label>
                <input id="customerPhone" name="Phone" type="tel" value="<?php echo h($orderData["customer"]["Phone"]); ?>" placeholder="(555) 555-5555">
              </div>
              <div class="form-group" style="grid-column:1 / -1;">
                <label for="customerAddress">Address</label>
                <input id="customerAddress" name="Address" type="text" value="<?php echo h($orderData["customer"]["Address"]); ?>" placeholder="Street, City, State, ZIP">
              </div>
            </div>
          </div>

          <div class="card" style="margin-bottom:1.25rem;">
            <div class="card-header">
              <h2 class="card-title">Order</h2>
              <span class="card-note">
                Order details including employee, status, channel, and totals.
              </span>
            </div>
            <div class="form-grid form-grid-2">
              <div class="form-group">
                <label for="orderDate">Order Date</label>
                <input id="orderDate" name="OrderDate" type="date" value="<?php echo h($orderData["order"]["OrderDate"]); ?>">
              </div>
              <div class="form-group">
                <label for="employeeId">Handled by Employee (optional)</label>
                <select id="employeeId" name="EmployeeID">
                  <option value="">Select employee (optional)</option>
                  <?php foreach ($employees as $emp): ?>
                    <option value="<?php echo h($emp["EmployeeID"]); ?>" <?php echo ($orderData["order"]["EmployeeID"] !== "" && (int)$orderData["order"]["EmployeeID"] === (int)$emp["EmployeeID"]) ? "selected" : ""; ?>>
                      <?php echo h($emp["EmployeeID"] . " - " . $emp["Name"]); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label for="orderStatus">Status</label>
                <select id="orderStatus" name="Status">
                  <?php foreach (["Pending", "In Progress", "Completed", "Cancelled"] as $statusOption): ?>
                    <option value="<?php echo $statusOption; ?>" <?php echo ($orderData["order"]["Status"] === $statusOption) ? "selected" : ""; ?>><?php echo $statusOption; ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label for="orderChannel">Channel</label>
                <select id="orderChannel" name="Channel">
                  <?php foreach (["In-Person", "Online"] as $channel): ?>
                    <option value="<?php echo $channel; ?>" <?php echo ($orderData["order"]["Channel"] === $channel) ? "selected" : ""; ?>><?php echo $channel; ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label for="totalAmount">Total Amount</label>
                <input id="totalAmount" name="TotalAmount" type="number" step="0.01" min="0" value="<?php echo h($orderData["order"]["TotalAmount"]); ?>">
              </div>
            </div>
          </div>

          <div class="card" style="margin-bottom:1.25rem;">
            <div class="card-header">
              <h2 class="card-title">Order Items</h2>
              <span class="card-note">At least one line is required. Extra blank rows are provided.</span>
            </div>
            <div class="table-wrapper">
              <table>
                <thead>
                  <tr>
                    <th>LineNo</th>
                    <th>Menu Item (ItemID)</th>
                    <th>Quantity</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($orderData["items"] as $idx => $row): ?>
                    <tr>
                      <td><input type="number" name="LineNo[]" value="<?php echo h($row["LineNo"]); ?>" min="1"></td>
                      <td>
                        <select name="ItemID[]">
                          <option value=""><?php echo $idx === 0 ? "Select menu item" : "(optional)"; ?></option>
                          <?php foreach ($menuItems as $mi): ?>
                            <option value="<?php echo h($mi["ItemID"]); ?>" <?php echo ($row["ItemID"] !== "" && (int)$row["ItemID"] === (int)$mi["ItemID"]) ? "selected" : ""; ?>>
                              <?php echo h($mi["ItemID"] . " - " . $mi["Name"]); ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </td>
                      <td><input type="number" name="Quantity[]" value="<?php echo h($row["Quantity"]); ?>" min="1"></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>

          <div class="card">
            <div class="card-header">
              <h2 class="card-title">Payment</h2>
              <span class="card-note">
                Capture payment details. Single payment per order is enough for this project.
              </span>
            </div>
            <div class="form-grid form-grid-2">
              <div class="form-group">
                <label for="paymentNo">Payment No</label>
                <input id="paymentNo" name="PaymentNo" type="number" min="1" value="<?php echo h($orderData["payment"]["PaymentNo"]); ?>">
              </div>
              <div class="form-group">
                <label for="paymentMethod">Method</label>
                <select id="paymentMethod" name="Method">
                  <?php foreach (["Cash", "Card", "Online"] as $methodOption): ?>
                    <option value="<?php echo $methodOption; ?>" <?php echo ($orderData["payment"]["Method"] === $methodOption) ? "selected" : ""; ?>><?php echo $methodOption; ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label for="paymentAmount">Amount</label>
                <input id="paymentAmount" name="Amount" type="number" step="0.01" min="0" value="<?php echo h($orderData["payment"]["Amount"]); ?>">
              </div>
              <div class="form-group">
                <label for="paymentStatus">Status</label>
                <select id="paymentStatus" name="StatusPayment">
                  <?php foreach (["Pending", "Authorized", "Captured", "Refunded"] as $payStatus): ?>
                    <option value="<?php echo $payStatus; ?>" <?php echo ($orderData["payment"]["Status"] === $payStatus) ? "selected" : ""; ?>><?php echo $payStatus; ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          </div>

          <div class="btn-row">
            <button type="submit" class="btn btn-primary"><?php echo $mode === "edit" ? "Update Order" : "Save Order"; ?></button>
            <a class="btn btn-outline" href="order_status.php">Back to Orders</a>
            <a class="btn btn-outline" href="customer_order.php">Start New Order</a>
          </div>
        </form>
      </section>
    </div>
  </main>
</body>
</html>
