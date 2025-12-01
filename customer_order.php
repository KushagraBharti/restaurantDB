<?php
require_once "config.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$success_message = "";
$error_message = "";
$order_summary = null;

// Default form values
$formData = [
    "CustomerID" => "",
    "CustomerName" => "",
    "Email" => "",
    "Phone" => "",
    "Address" => "",
    "OrderID" => "",
    "EmployeeID" => "",
    "OrderDate" => date("Y-m-d"),
    "Channel" => "In-Person",
    "Status" => "Pending",
    "TotalAmount" => "",
    "LineNo1" => 1,
    "ItemID1" => "",
    "Quantity1" => 1,
    "LineNo2" => 2,
    "ItemID2" => "",
    "Quantity2" => "",
    "LineNo3" => 3,
    "ItemID3" => "",
    "Quantity3" => "",
    "PaymentNo" => 1,
    "Method" => "Cash",
    "Amount" => "",
    "StatusPayment" => "Pending"
];

function fetchOptions($conn, $sql, $types = "", $params = [])
{
    $stmt = $conn->prepare($sql);
    if ($types !== "" && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

// Load dropdown data
$employees = fetchOptions($conn, "SELECT EmployeeID, Name FROM Employees ORDER BY Name");
$menuItems = fetchOptions($conn, "SELECT ItemID, Name FROM MenuItems WHERE IsActive = 1 ORDER BY Name");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Preserve submitted values
    foreach ($formData as $key => $value) {
        if (isset($_POST[$key])) {
            $formData[$key] = is_string($_POST[$key]) ? trim($_POST[$key]) : $_POST[$key];
        }
    }

    $validation_errors = [];

    // Basic validation
    if ($formData["CustomerID"] === "" && $formData["CustomerName"] === "") {
        $validation_errors[] = "Customer name is required for new customers.";
    }
    if ($formData["OrderDate"] === "") {
        $validation_errors[] = "Order Date is required.";
    }
    if ($formData["Channel"] === "") {
        $validation_errors[] = "Channel is required.";
    }
    if ($formData["Status"] === "") {
        $validation_errors[] = "Order Status is required.";
    }
    if ($formData["TotalAmount"] === "" || !is_numeric($formData["TotalAmount"]) || $formData["TotalAmount"] < 0) {
        $validation_errors[] = "Total Amount must be a non-negative number.";
    }

    // Validate payment
    if ($formData["PaymentNo"] === "" || !is_numeric($formData["PaymentNo"]) || (int)$formData["PaymentNo"] < 1) {
        $validation_errors[] = "Payment No must be a positive integer.";
    }
    if ($formData["Method"] === "") {
        $validation_errors[] = "Payment Method is required.";
    }
    if ($formData["Amount"] === "" || !is_numeric($formData["Amount"]) || $formData["Amount"] < 0) {
        $validation_errors[] = "Payment Amount must be a non-negative number.";
    }
    if ($formData["StatusPayment"] === "") {
        $validation_errors[] = "Payment Status is required.";
    }

    // Validate order items
    $itemsToInsert = [];
    for ($i = 1; $i <= 3; $i++) {
        $itemIdKey = "ItemID{$i}";
        $qtyKey = "Quantity{$i}";
        $lineKey = "LineNo{$i}";

        $itemIdVal = trim((string)$formData[$itemIdKey]);
        $qtyVal = $formData[$qtyKey];
        $lineNoVal = $formData[$lineKey] !== "" ? (int)$formData[$lineKey] : $i;

        if ($itemIdVal !== "" || $qtyVal !== "") {
            if ($itemIdVal === "" || !is_numeric($itemIdVal)) {
                $validation_errors[] = "ItemID for line {$i} must be selected.";
            }
            if ($qtyVal === "" || !is_numeric($qtyVal) || (int)$qtyVal < 1) {
                $validation_errors[] = "Quantity for line {$i} must be a positive integer.";
            }
            if ($lineNoVal < 1) {
                $validation_errors[] = "Line number for line {$i} must be at least 1.";
            }
            if (empty($validation_errors)) {
                $itemsToInsert[] = [
                    "LineNo" => $lineNoVal,
                    "ItemID" => (int)$itemIdVal,
                    "Quantity" => (int)$qtyVal
                ];
            }
        }
    }

    if (!empty($validation_errors)) {
        $error_message = "Validation errors: " . implode(" | ", $validation_errors);
    } else {
        try {
            $conn->begin_transaction();

            // Handle customer
            $customerId = null;
            $customerIdInput = $formData["CustomerID"];
            $customerName = $formData["CustomerName"];
            $email = $formData["Email"];
            $phone = $formData["Phone"];
            $address = $formData["Address"];

            if ($customerIdInput !== "") {
                $stmt = $conn->prepare("SELECT CustomerID FROM Customers WHERE CustomerID = ?");
                $stmt->bind_param("i", $customerIdInput);
                $stmt->execute();
                $stmt->store_result();
                if ($stmt->num_rows > 0) {
                    $stmt->free_result();
                    $stmt->close();
                    $stmt = $conn->prepare("UPDATE Customers SET Name = ?, Email = ?, Phone = ?, Address = ? WHERE CustomerID = ?");
                    $stmt->bind_param("ssssi", $customerName, $email, $phone, $address, $customerIdInput);
                    $stmt->execute();
                    $customerId = (int)$customerIdInput;
                } else {
                    $stmt->free_result();
                    $stmt->close();
                    $stmt = $conn->prepare("INSERT INTO Customers (Name, Email, Phone, Address) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssss", $customerName, $email, $phone, $address);
                    $stmt->execute();
                    $customerId = $conn->insert_id;
                }
                $stmt->close();
            } else {
                $stmt = $conn->prepare("INSERT INTO Customers (Name, Email, Phone, Address) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $customerName, $email, $phone, $address);
                $stmt->execute();
                $customerId = $conn->insert_id;
                $stmt->close();
            }

            // Handle order insert/update
            $orderId = null;
            $employeeId = $formData["EmployeeID"] === "" ? null : (int)$formData["EmployeeID"];
            $orderDate = $formData["OrderDate"] ?: date("Y-m-d");
            $channel = $formData["Channel"];
            $status = $formData["Status"];
            $totalAmount = (float)$formData["TotalAmount"];

            $existingOrder = false;
            if ($formData["OrderID"] !== "") {
                $orderIdInput = (int)$formData["OrderID"];
                $stmt = $conn->prepare("SELECT OrderID FROM Orders WHERE OrderID = ?");
                $stmt->bind_param("i", $orderIdInput);
                $stmt->execute();
                $stmt->store_result();
                if ($stmt->num_rows > 0) {
                    $existingOrder = true;
                    $orderId = $orderIdInput;
                }
                $stmt->free_result();
                $stmt->close();
            }

            if ($existingOrder) {
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

            // Order items: clear then insert
            $stmt = $conn->prepare("DELETE FROM OrderItems WHERE OrderID = ?");
            $stmt->bind_param("i", $orderId);
            $stmt->execute();
            $stmt->close();

            if (!empty($itemsToInsert)) {
                $stmt = $conn->prepare("INSERT INTO OrderItems (OrderID, LineNo, ItemID, Quantity) VALUES (?, ?, ?, ?)");
                foreach ($itemsToInsert as $itemRow) {
                    $lineNo = $itemRow["LineNo"];
                    $itemId = $itemRow["ItemID"];
                    $qty = $itemRow["Quantity"];
                    $stmt->bind_param("iiii", $orderId, $lineNo, $itemId, $qty);
                    $stmt->execute();
                }
                $stmt->close();
            }

            // Payment (upsert)
            $paymentNo = (int)$formData["PaymentNo"];
            $method = $formData["Method"];
            $amount = (float)$formData["Amount"];
            $payStatus = $formData["StatusPayment"];

            $stmt = $conn->prepare(
                "INSERT INTO Payments (OrderID, PaymentNo, Method, Amount, Status) VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE Method = VALUES(Method), Amount = VALUES(Amount), Status = VALUES(Status)"
            );
            $stmt->bind_param("iisds", $orderId, $paymentNo, $method, $amount, $payStatus);
            $stmt->execute();
            $stmt->close();

            $conn->commit();

            $success_message = "Order saved successfully (OrderID {$orderId}).";
            $formData["OrderID"] = $orderId;
            $formData["CustomerID"] = $customerId;

            $stmt = $conn->prepare(
                "SELECT o.OrderID, o.OrderDate, o.Status, o.Channel, o.TotalAmount,
                        c.Name AS CustomerName, c.CustomerID,
                        e.Name AS EmployeeName
                 FROM Orders o
                 JOIN Customers c ON o.CustomerID = c.CustomerID
                 LEFT JOIN Employees e ON o.EmployeeID = e.EmployeeID
                 WHERE o.OrderID = ?"
            );
            $stmt->bind_param("i", $orderId);
            $stmt->execute();
            $order_summary = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Error saving order: " . $e->getMessage();
        }
    }
}
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
        <p class="helper-text" style="color:#22c55e;"><?php echo htmlspecialchars($success_message); ?></p>
      <?php endif; ?>
      <?php if (!empty($error_message)): ?>
        <p class="helper-text" style="color:#f97373;"><?php echo htmlspecialchars($error_message); ?></p>
      <?php endif; ?>

      <section class="card">
        <div class="card-header">
          <h1 class="page-title">Place / Edit Order</h1>
          <span class="tag">Orders, Customers, OrderItems, Payments</span>
        </div>
        <p class="page-subtitle">
          This form collects data for <strong>Customers</strong>, <strong>Orders</strong>,
          <strong>OrderItems</strong>, and <strong>Payments</strong>. Data is saved to MySQL.
        </p>

        <form action="customer_order.php" method="post">
          <div class="card" style="margin-bottom:1.25rem;">
            <div class="card-header">
              <h2 class="card-title">Customer</h2>
              <span class="card-note">Matches relation: Customers(CustomerID, Name, Email, Phone, Address)</span>
            </div>
            <div class="form-grid form-grid-2">
              <div class="form-group">
                <label for="customerId">Customer ID</label>
                <input id="customerId" name="CustomerID" type="text" value="<?php echo htmlspecialchars($formData["CustomerID"]); ?>" placeholder="Existing ID or leave blank">
                <span class="helper-text">Existing ID, or leave blank for new customers (DB will generate).</span>
              </div>
              <div class="form-group">
                <label for="customerName">Name</label>
                <input id="customerName" name="CustomerName" type="text" value="<?php echo htmlspecialchars($formData["CustomerName"]); ?>" placeholder="Customer full name" required>
              </div>
              <div class="form-group">
                <label for="customerEmail">Email</label>
                <input id="customerEmail" name="Email" type="email" value="<?php echo htmlspecialchars($formData["Email"]); ?>" placeholder="name@example.com">
              </div>
              <div class="form-group">
                <label for="customerPhone">Phone</label>
                <input id="customerPhone" name="Phone" type="tel" value="<?php echo htmlspecialchars($formData["Phone"]); ?>" placeholder="(555) 555-5555">
              </div>
              <div class="form-group" style="grid-column:1 / -1;">
                <label for="customerAddress">Address</label>
                <input id="customerAddress" name="Address" type="text" value="<?php echo htmlspecialchars($formData["Address"]); ?>" placeholder="Street, City, State, ZIP">
              </div>
            </div>
          </div>

          <div class="card" style="margin-bottom:1.25rem;">
            <div class="card-header">
              <h2 class="card-title">Order</h2>
              <span class="card-note">
                Matches relation: Orders(OrderID, CustomerID, EmployeeID, OrderDate, Status, Channel, TotalAmount)
              </span>
            </div>
            <div class="form-grid form-grid-2">
              <div class="form-group">
                <label for="orderId">Order ID</label>
                <input id="orderId" name="OrderID" type="text" value="<?php echo htmlspecialchars($formData["OrderID"]); ?>" placeholder="Leave blank to create new">
                <span class="helper-text">Provide to update existing order; blank creates new.</span>
              </div>
              <div class="form-group">
                <label for="employeeId">Handled by Employee (EmployeeID)</label>
                <select id="employeeId" name="EmployeeID">
                  <option value="">Select employee (optional)</option>
                  <?php foreach ($employees as $emp): ?>
                    <option value="<?php echo htmlspecialchars($emp["EmployeeID"]); ?>" <?php echo ($formData["EmployeeID"] !== "" && (int)$formData["EmployeeID"] === (int)$emp["EmployeeID"]) ? "selected" : ""; ?>>
                      <?php echo htmlspecialchars($emp["EmployeeID"] . " - " . $emp["Name"]); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label for="orderDate">Order Date</label>
                <input id="orderDate" name="OrderDate" type="date" value="<?php echo htmlspecialchars($formData["OrderDate"]); ?>">
              </div>
              <div class="form-group">
                <label for="orderChannel">Channel</label>
                <select id="orderChannel" name="Channel">
                  <?php foreach (["In-Person", "Online"] as $channel): ?>
                    <option value="<?php echo $channel; ?>" <?php echo ($formData["Channel"] === $channel) ? "selected" : ""; ?>><?php echo $channel; ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label for="orderStatus">Status</label>
                <select id="orderStatus" name="Status">
                  <?php foreach (["Pending", "In Progress", "Completed", "Cancelled"] as $statusOption): ?>
                    <option value="<?php echo $statusOption; ?>" <?php echo ($formData["Status"] === $statusOption) ? "selected" : ""; ?>><?php echo $statusOption; ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label for="totalAmount">Total Amount</label>
                <input id="totalAmount" name="TotalAmount" type="number" step="0.01" min="0" value="<?php echo htmlspecialchars($formData["TotalAmount"]); ?>">
              </div>
            </div>
          </div>

          <div class="card" style="margin-bottom:1.25rem;">
            <div class="card-header">
              <h2 class="card-title">Order Items</h2>
              <span class="card-note">
                Matches relation: OrderItems(OrderID, LineNo, ItemID, Quantity)
              </span>
            </div>
            <p class="helper-text">
              For simplicity, this interface shows three order lines. Line numbers correspond to LineNo.
            </p>
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
                  <?php for ($i = 1; $i <= 3; $i++): ?>
                    <tr>
                      <td>
                        <input type="number" name="LineNo<?php echo $i; ?>" value="<?php echo htmlspecialchars($formData["LineNo{$i}"]); ?>" min="1">
                      </td>
                      <td>
                        <select name="ItemID<?php echo $i; ?>">
                          <option value=""><?php echo $i === 1 ? "Select menu item" : "(optional)"; ?></option>
                          <?php foreach ($menuItems as $mi): ?>
                            <option value="<?php echo htmlspecialchars($mi["ItemID"]); ?>" <?php echo ($formData["ItemID{$i}"] !== "" && (int)$formData["ItemID{$i}"] === (int)$mi["ItemID"]) ? "selected" : ""; ?>>
                              <?php echo htmlspecialchars($mi["ItemID"] . " - " . $mi["Name"]); ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </td>
                      <td>
                        <input type="number" name="Quantity<?php echo $i; ?>" value="<?php echo htmlspecialchars($formData["Quantity{$i}"]); ?>" min="1">
                      </td>
                    </tr>
                  <?php endfor; ?>
                </tbody>
              </table>
            </div>
          </div>

          <div class="card">
            <div class="card-header">
              <h2 class="card-title">Payment</h2>
              <span class="card-note">
                Matches relation: Payments(OrderID, PaymentNo, Method, Amount, Status)
              </span>
            </div>
            <div class="form-grid form-grid-2">
              <div class="form-group">
                <label for="paymentNo">Payment No</label>
                <input id="paymentNo" name="PaymentNo" type="number" min="1" value="<?php echo htmlspecialchars($formData["PaymentNo"]); ?>">
                <span class="helper-text">Allows split payments by incrementing this number.</span>
              </div>
              <div class="form-group">
                <label for="paymentMethod">Method</label>
                <select id="paymentMethod" name="Method">
                  <?php foreach (["Cash", "Card", "Online"] as $methodOption): ?>
                    <option value="<?php echo $methodOption; ?>" <?php echo ($formData["Method"] === $methodOption) ? "selected" : ""; ?>><?php echo $methodOption; ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label for="paymentAmount">Amount</label>
                <input id="paymentAmount" name="Amount" type="number" step="0.01" min="0" value="<?php echo htmlspecialchars($formData["Amount"]); ?>">
              </div>
              <div class="form-group">
                <label for="paymentStatus">Status</label>
                <select id="paymentStatus" name="StatusPayment">
                  <?php foreach (["Pending", "Authorized", "Captured", "Refunded"] as $payStatus): ?>
                    <option value="<?php echo $payStatus; ?>" <?php echo ($formData["StatusPayment"] === $payStatus) ? "selected" : ""; ?>><?php echo $payStatus; ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          </div>

          <div class="btn-row">
            <button type="submit" class="btn btn-primary">Save Order</button>
            <button type="reset" class="btn btn-outline">Clear Form</button>
          </div>
        </form>
      </section>

      <?php if ($order_summary): ?>
        <section class="card">
          <div class="card-header">
            <h2 class="card-title">Saved Order Summary</h2>
            <span class="card-note">Order saved in the database.</span>
          </div>
          <div class="form-grid form-grid-2">
            <div>
              <p class="helper-text"><strong>OrderID:</strong> <?php echo htmlspecialchars($order_summary["OrderID"]); ?></p>
              <p class="helper-text"><strong>Customer:</strong> <?php echo htmlspecialchars($order_summary["CustomerID"] . " - " . $order_summary["CustomerName"]); ?></p>
              <p class="helper-text"><strong>Employee:</strong> <?php echo htmlspecialchars($order_summary["EmployeeName"] ?? "N/A"); ?></p>
            </div>
            <div>
              <p class="helper-text"><strong>Date:</strong> <?php echo htmlspecialchars($order_summary["OrderDate"]); ?></p>
              <p class="helper-text"><strong>Status:</strong> <?php echo htmlspecialchars($order_summary["Status"]); ?></p>
              <p class="helper-text"><strong>Channel:</strong> <?php echo htmlspecialchars($order_summary["Channel"]); ?></p>
              <p class="helper-text"><strong>Total:</strong> <?php echo htmlspecialchars(number_format((float)$order_summary["TotalAmount"], 2)); ?></p>
            </div>
          </div>
        </section>
      <?php endif; ?>
    </div>
  </main>
</body>
</html>
