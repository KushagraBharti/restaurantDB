<?php
require_once "config.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES);
}

$success_message = isset($_GET["msg"]) ? trim($_GET["msg"]) : "";
$error_message = "";

$mode = (isset($_GET["mode"]) && $_GET["mode"] === "edit") ? "edit" : "create";
$editId = isset($_GET["employee_id"]) ? (int)$_GET["employee_id"] : 0;

$formData = [
    "EmployeeID" => "",
    "Name" => "",
    "Role" => "",
    "Schedule" => "",
    "Salary" => ""
];

if ($mode === "edit" && $editId > 0) {
    $stmt = $conn->prepare("SELECT EmployeeID, Name, Role, Schedule, Salary FROM Employees WHERE EmployeeID = ?");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $formData = $row;
    } else {
        $error_message = "Employee not found. Switched to create mode.";
        $mode = "create";
    }
    $stmt->close();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "save_employee";

    if ($action === "delete_employee") {
        $deleteId = isset($_POST["EmployeeID"]) ? (int)$_POST["EmployeeID"] : 0;
        if ($deleteId <= 0) {
            $error_message = "Invalid employee id for deletion.";
        } else {
            try {
                $stmt = $conn->prepare("DELETE FROM Employees WHERE EmployeeID = ?");
                $stmt->bind_param("i", $deleteId);
                $stmt->execute();
                $stmt->close();
                $success_message = "Employee #{$deleteId} deleted.";
                $formData = ["EmployeeID" => "", "Name" => "", "Role" => "", "Schedule" => "", "Salary" => ""];
                $mode = "create";
                $editId = 0;
            } catch (Exception $e) {
                $error_message = "Unable to delete employee (likely referenced by orders): " . $e->getMessage();
            }
        }
    } else {
        foreach ($formData as $key => $val) {
            if (isset($_POST[$key])) {
                $formData[$key] = is_string($_POST[$key]) ? trim($_POST[$key]) : $_POST[$key];
            }
        }

        $errors = [];
        if ($formData["Name"] === "") {
            $errors[] = "Employee name is required.";
        }
        if ($formData["Role"] === "") {
            $errors[] = "Role is required.";
        }
        if ($formData["Schedule"] === "") {
            $errors[] = "Schedule is required.";
        }
        if ($formData["Salary"] === "" || !is_numeric($formData["Salary"]) || $formData["Salary"] < 0) {
            $errors[] = "Salary must be a non-negative number.";
        }

        if (!empty($errors)) {
            $error_message = implode(" | ", $errors);
        } else {
            try {
                $empIdInput = $formData["EmployeeID"];
                $exists = false;

                if ($empIdInput !== "") {
                    $stmt = $conn->prepare("SELECT EmployeeID FROM Employees WHERE EmployeeID = ?");
                    $stmt->bind_param("i", $empIdInput);
                    $stmt->execute();
                    $stmt->store_result();
                    if ($stmt->num_rows > 0) {
                        $exists = true;
                    }
                    $stmt->free_result();
                    $stmt->close();
                }

                if ($exists) {
                    $stmt = $conn->prepare("UPDATE Employees SET Name = ?, Role = ?, Schedule = ?, Salary = ? WHERE EmployeeID = ?");
                    $stmt->bind_param("sssdi", $formData["Name"], $formData["Role"], $formData["Schedule"], $formData["Salary"], $empIdInput);
                    $stmt->execute();
                    $stmt->close();
                    $success_message = "Employee updated (ID {$empIdInput}).";
                    $mode = "edit";
                    $editId = (int)$empIdInput;
                } else {
                    $stmt = $conn->prepare("INSERT INTO Employees (Name, Role, Schedule, Salary) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("sssd", $formData["Name"], $formData["Role"], $formData["Schedule"], $formData["Salary"]);
                    $stmt->execute();
                    $newId = $conn->insert_id;
                    $stmt->close();
                    $formData["EmployeeID"] = $newId;
                    $success_message = "Employee created (ID {$newId}).";
                    $mode = "edit";
                    $editId = $newId;
                }
            } catch (Exception $e) {
                $error_message = "Error saving employee: " . $e->getMessage();
            }
        }
    }
}

// Fetch employees for list
$employees = [];
try {
    $result = $conn->query("SELECT EmployeeID, Name, Role, Schedule, Salary FROM Employees ORDER BY Name");
    $employees = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
} catch (Exception $e) {
    $error_message = $error_message ?: ("Error loading employees: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Employees - Restaurant DB System</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <header class="navbar">
    <div class="navbar-inner">
      <div class="navbar-title">Restaurant Database System</div>
      <nav class="nav-links">
        <a href="index.php">Home</a>
        <a href="customer_order.php">Place Order</a>
        <a href="order_status.php">Orders</a>
        <a href="manage_menu_items.php">Menu Items</a>
        <a href="manage_ingredients.php">Ingredients</a>
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
          <h1 class="page-title"><?php echo $mode === "edit" ? "Edit Employee #" . h($formData["EmployeeID"]) : "Create Employee"; ?></h1>
          <span class="tag">Employees</span>
        </div>
        <p class="page-subtitle">
          Maintain staff records for
          <strong>Employees(EmployeeID, Name, Role, Schedule, Salary)</strong>.
        </p>

        <form action="manage_employees.php<?php echo $mode === 'edit' ? '?mode=edit&employee_id=' . h($editId) : ''; ?>" method="post">
          <input type="hidden" name="action" value="save_employee">
          <div class="form-grid form-grid-2">
            <div class="form-group">
              <label for="employeeId">EmployeeID</label>
              <input id="employeeId" name="EmployeeID" type="text" value="<?php echo h($formData["EmployeeID"]); ?>" placeholder="Leave blank to add new">
            </div>
            <div class="form-group">
              <label for="employeeName">Name</label>
              <input id="employeeName" name="Name" type="text" value="<?php echo h($formData["Name"]); ?>" placeholder="Employee full name" required>
            </div>
            <div class="form-group">
              <label for="employeeRole">Role</label>
              <input id="employeeRole" name="Role" type="text" value="<?php echo h($formData["Role"]); ?>" placeholder="e.g. Server, Cook, Manager">
            </div>
            <div class="form-group">
              <label for="employeeSchedule">Schedule</label>
              <input id="employeeSchedule" name="Schedule" type="text" value="<?php echo h($formData["Schedule"]); ?>" placeholder="e.g. Weekdays 9-5">
            </div>
            <div class="form-group">
              <label for="employeeSalary">Salary</label>
              <input id="employeeSalary" name="Salary" type="number" step="0.01" min="0" value="<?php echo h($formData["Salary"]); ?>">
            </div>
          </div>
          <div class="btn-row">
            <button type="submit" class="btn btn-primary"><?php echo $mode === "edit" ? "Update Employee" : "Create Employee"; ?></button>
            <a class="btn btn-outline" href="manage_employees.php">New / Reset</a>
          </div>
        </form>
      </section>

      <section class="card">
        <div class="card-header">
          <h2 class="card-title">Employees (live data)</h2>
          <span class="card-note">Each row corresponds to one tuple in Employees.</span>
        </div>
        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>EmployeeID</th>
                <th>Name</th>
                <th>Role</th>
                <th>Schedule</th>
                <th>Salary</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($employees)): ?>
                <tr><td colspan="6" style="text-align:center;">No employees found.</td></tr>
              <?php else: ?>
                <?php foreach ($employees as $emp): ?>
                  <tr>
                    <td><?php echo h($emp["EmployeeID"]); ?></td>
                    <td><?php echo h($emp["Name"]); ?></td>
                    <td><?php echo h($emp["Role"]); ?></td>
                    <td><?php echo h($emp["Schedule"]); ?></td>
                    <td><?php echo h(number_format((float)$emp["Salary"], 2)); ?></td>
                    <td class="table-actions">
                      <a class="btn btn-small" href="manage_employees.php?mode=edit&employee_id=<?php echo h($emp["EmployeeID"]); ?>">Edit</a>
                      <form action="manage_employees.php" method="post" style="display:inline;">
                        <input type="hidden" name="action" value="delete_employee">
                        <input type="hidden" name="EmployeeID" value="<?php echo h($emp["EmployeeID"]); ?>">
                        <button type="submit" class="btn btn-danger btn-small">Delete</button>
                      </form>
                    </td>
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
