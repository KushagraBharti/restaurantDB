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
$editId = isset($_GET["ingredient_id"]) ? (int)$_GET["ingredient_id"] : 0;

$formData = [
    "IngredientID" => "",
    "Name" => "",
    "QuantityAvailable" => "",
    "Unit" => "",
    "ReorderLevel" => ""
];

if ($mode === "edit" && $editId > 0) {
    $stmt = $conn->prepare("SELECT IngredientID, Name, QuantityAvailable, Unit, ReorderLevel FROM Ingredients WHERE IngredientID = ?");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $formData = $row;
    } else {
        $error_message = "Ingredient not found. Switched to create mode.";
        $mode = "create";
    }
    $stmt->close();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "save_ingredient";

    if ($action === "delete_ingredient") {
        $deleteId = isset($_POST["IngredientID"]) ? (int)$_POST["IngredientID"] : 0;
        if ($deleteId <= 0) {
            $error_message = "Invalid ingredient id for deletion.";
        } else {
            try {
                $stmt = $conn->prepare("DELETE FROM Ingredients WHERE IngredientID = ?");
                $stmt->bind_param("i", $deleteId);
                $stmt->execute();
                $stmt->close();
                $success_message = "Ingredient #{$deleteId} deleted.";
                $formData = ["IngredientID" => "", "Name" => "", "QuantityAvailable" => "", "Unit" => "", "ReorderLevel" => ""];
                $mode = "create";
                $editId = 0;
            } catch (Exception $e) {
                $error_message = "Unable to delete ingredient (check recipe components): " . $e->getMessage();
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
            $errors[] = "Ingredient name is required.";
        }
        if ($formData["QuantityAvailable"] === "" || !is_numeric($formData["QuantityAvailable"]) || $formData["QuantityAvailable"] < 0) {
            $errors[] = "QuantityAvailable must be a non-negative number.";
        }
        if ($formData["Unit"] === "") {
            $errors[] = "Unit is required.";
        }
        if ($formData["ReorderLevel"] === "" || !is_numeric($formData["ReorderLevel"]) || $formData["ReorderLevel"] < 0) {
            $errors[] = "ReorderLevel must be a non-negative number.";
        }

        if (!empty($errors)) {
            $error_message = implode(" | ", $errors);
        } else {
            try {
                $ingredientIdInput = $formData["IngredientID"];
                $exists = false;

                if ($ingredientIdInput !== "") {
                    $stmt = $conn->prepare("SELECT IngredientID FROM Ingredients WHERE IngredientID = ?");
                    $stmt->bind_param("i", $ingredientIdInput);
                    $stmt->execute();
                    $stmt->store_result();
                    if ($stmt->num_rows > 0) {
                        $exists = true;
                    }
                    $stmt->free_result();
                    $stmt->close();
                }

                if ($exists) {
                    $stmt = $conn->prepare("UPDATE Ingredients SET Name = ?, QuantityAvailable = ?, Unit = ?, ReorderLevel = ? WHERE IngredientID = ?");
                    $stmt->bind_param("sdsdi", $formData["Name"], $formData["QuantityAvailable"], $formData["Unit"], $formData["ReorderLevel"], $ingredientIdInput);
                    $stmt->execute();
                    $stmt->close();
                    $success_message = "Ingredient updated (ID {$ingredientIdInput}).";
                    $mode = "edit";
                    $editId = (int)$ingredientIdInput;
                } else {
                    $stmt = $conn->prepare("INSERT INTO Ingredients (Name, QuantityAvailable, Unit, ReorderLevel) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("sdsd", $formData["Name"], $formData["QuantityAvailable"], $formData["Unit"], $formData["ReorderLevel"]);
                    $stmt->execute();
                    $newId = $conn->insert_id;
                    $stmt->close();
                    $formData["IngredientID"] = $newId;
                    $success_message = "Ingredient created (ID {$newId}).";
                    $mode = "edit";
                    $editId = $newId;
                }
            } catch (Exception $e) {
                $error_message = "Error saving ingredient: " . $e->getMessage();
            }
        }
    }
}

// Fetch ingredient list
$ingredients = [];
try {
    $result = $conn->query("SELECT IngredientID, Name, QuantityAvailable, Unit, ReorderLevel FROM Ingredients ORDER BY Name");
    $ingredients = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
} catch (Exception $e) {
    $error_message = $error_message ?: ("Error loading ingredients: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Ingredients - Restaurant DB System</title>
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
          <h1 class="page-title"><?php echo $mode === "edit" ? "Edit Ingredient #" . h($formData["IngredientID"]) : "Create Ingredient"; ?></h1>
          <span class="tag">Ingredients</span>
        </div>
        <p class="page-subtitle">
          Manage stock for <strong>Ingredients(IngredientID, Name, QuantityAvailable, Unit, ReorderLevel)</strong>.
        </p>

        <form action="manage_ingredients.php<?php echo $mode === 'edit' ? '?mode=edit&ingredient_id=' . h($editId) : ''; ?>" method="post">
          <input type="hidden" name="action" value="save_ingredient">
          <div class="form-grid form-grid-2">
            <div class="form-group">
              <label for="ingredientId">IngredientID</label>
              <input id="ingredientId" name="IngredientID" type="text" value="<?php echo h($formData["IngredientID"]); ?>" placeholder="Leave blank to add new">
            </div>
            <div class="form-group">
              <label for="ingredientName">Name</label>
              <input id="ingredientName" name="Name" type="text" value="<?php echo h($formData["Name"]); ?>" placeholder="Ingredient name" required>
            </div>
            <div class="form-group">
              <label for="quantityAvailable">QuantityAvailable</label>
              <input id="quantityAvailable" name="QuantityAvailable" type="number" step="0.01" min="0" value="<?php echo h($formData["QuantityAvailable"]); ?>">
            </div>
            <div class="form-group">
              <label for="unit">Unit</label>
              <input id="unit" name="Unit" type="text" value="<?php echo h($formData["Unit"]); ?>" placeholder="e.g. kg, L, pcs">
            </div>
            <div class="form-group">
              <label for="reorderLevel">ReorderLevel</label>
              <input id="reorderLevel" name="ReorderLevel" type="number" step="0.01" min="0" value="<?php echo h($formData["ReorderLevel"]); ?>">
              <span class="helper-text">When stock falls below this amount, the item should be reordered.</span>
            </div>
          </div>
          <div class="btn-row">
            <button type="submit" class="btn btn-primary"><?php echo $mode === "edit" ? "Update Ingredient" : "Create Ingredient"; ?></button>
            <a class="btn btn-outline" href="manage_ingredients.php">New / Reset</a>
          </div>
        </form>
      </section>

      <section class="card">
        <div class="card-header">
          <h2 class="card-title">Ingredients (live data)</h2>
          <span class="card-note">Rows from the Ingredients table.</span>
        </div>
        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>IngredientID</th>
                <th>Name</th>
                <th>QuantityAvailable</th>
                <th>Unit</th>
                <th>ReorderLevel</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($ingredients)): ?>
                <tr><td colspan="6" style="text-align:center;">No ingredients found.</td></tr>
              <?php else: ?>
                <?php foreach ($ingredients as $ing): ?>
                  <?php $lowStock = ((float)$ing["QuantityAvailable"] < (float)$ing["ReorderLevel"]); ?>
                  <tr class="<?php echo $lowStock ? 'row-warning' : ''; ?>">
                    <td><?php echo h($ing["IngredientID"]); ?></td>
                    <td><?php echo h($ing["Name"]); ?></td>
                    <td><?php echo h($ing["QuantityAvailable"]); ?></td>
                    <td><?php echo h($ing["Unit"]); ?></td>
                    <td><?php echo h($ing["ReorderLevel"]); ?></td>
                    <td class="table-actions">
                      <a class="btn btn-small" href="manage_ingredients.php?mode=edit&ingredient_id=<?php echo h($ing["IngredientID"]); ?>">Edit</a>
                      <form action="manage_ingredients.php" method="post" style="display:inline;">
                        <input type="hidden" name="action" value="delete_ingredient">
                        <input type="hidden" name="IngredientID" value="<?php echo h($ing["IngredientID"]); ?>">
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
