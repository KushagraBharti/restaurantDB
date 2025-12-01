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
$editItemId = isset($_GET["item_id"]) ? (int)$_GET["item_id"] : 0;

$rcMode = (isset($_GET["rc_mode"]) && $_GET["rc_mode"] === "edit") ? "edit" : "create";
$rcEditItemId = isset($_GET["rc_item_id"]) ? (int)$_GET["rc_item_id"] : 0;
$rcEditComponentNo = isset($_GET["rc_component_no"]) ? (int)$_GET["rc_component_no"] : 0;

$menuForm = [
    "ItemID" => "",
    "Name" => "",
    "Category" => "",
    "Price" => "",
    "Description" => "",
    "IsActive" => 1
];

$recipeForm = [
    "ItemID" => "",
    "ComponentNo" => 1,
    "IngredientID" => "",
    "QtyPerItem" => ""
];

// Prefill menu item if editing
if ($mode === "edit" && $editItemId > 0) {
    $stmt = $conn->prepare("SELECT ItemID, Name, Category, Price, Description, IsActive FROM MenuItems WHERE ItemID = ?");
    $stmt->bind_param("i", $editItemId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $menuForm = $row;
    } else {
        $error_message = "Menu item not found. Switched to create mode.";
        $mode = "create";
    }
    $stmt->close();
}

// Prefill recipe component if editing
if ($rcMode === "edit" && $rcEditItemId > 0 && $rcEditComponentNo > 0) {
    $stmt = $conn->prepare("SELECT ItemID, ComponentNo, IngredientID, QtyPerItem FROM RecipeComponents WHERE ItemID = ? AND ComponentNo = ?");
    $stmt->bind_param("ii", $rcEditItemId, $rcEditComponentNo);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $recipeForm = $row;
    } else {
        $error_message = "Recipe component not found. Switched to create mode.";
        $rcMode = "create";
    }
    $stmt->close();
}

try {
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $action = $_POST["action"] ?? "";

        if ($action === "save_item") {
            foreach ($menuForm as $key => $val) {
                if (isset($_POST[$key])) {
                    $menuForm[$key] = is_string($_POST[$key]) ? trim($_POST[$key]) : $_POST[$key];
                }
            }
            $menuForm["IsActive"] = isset($_POST["IsActive"]) ? 1 : 0;

            $errors = [];
            if ($menuForm["Name"] === "") {
                $errors[] = "Menu item name is required.";
            }
            if ($menuForm["Price"] === "" || !is_numeric($menuForm["Price"]) || $menuForm["Price"] < 0) {
                $errors[] = "Price must be a non-negative number.";
            }

            if (empty($errors)) {
                $itemIdProvided = $menuForm["ItemID"] !== "";
                $itemExists = false;
                $itemId = null;

                if ($itemIdProvided) {
                    $itemId = (int)$menuForm["ItemID"];
                    $stmt = $conn->prepare("SELECT ItemID FROM MenuItems WHERE ItemID = ?");
                    $stmt->bind_param("i", $itemId);
                    $stmt->execute();
                    $stmt->store_result();
                    if ($stmt->num_rows > 0) {
                        $itemExists = true;
                    }
                    $stmt->free_result();
                    $stmt->close();
                }

                if ($itemExists) {
                    $stmt = $conn->prepare("UPDATE MenuItems SET Name = ?, Description = ?, Price = ?, Category = ?, IsActive = ? WHERE ItemID = ?");
                    $stmt->bind_param("ssdsii", $menuForm["Name"], $menuForm["Description"], $menuForm["Price"], $menuForm["Category"], $menuForm["IsActive"], $itemId);
                    $stmt->execute();
                    $stmt->close();
                    $success_message = "Menu item updated (ItemID {$itemId}).";
                    $mode = "edit";
                    $editItemId = $itemId;
                } else {
                    $stmt = $conn->prepare("INSERT INTO MenuItems (Name, Description, Price, Category, IsActive) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssdsi", $menuForm["Name"], $menuForm["Description"], $menuForm["Price"], $menuForm["Category"], $menuForm["IsActive"]);
                    $stmt->execute();
                    $itemId = $conn->insert_id;
                    $stmt->close();
                    $menuForm["ItemID"] = $itemId;
                    $success_message = "Menu item created (ItemID {$itemId}).";
                    $mode = "edit";
                    $editItemId = $itemId;
                }
            } else {
                $error_message = implode(" | ", $errors);
            }
        } elseif ($action === "delete_item") {
            $deleteId = isset($_POST["ItemID"]) ? (int)$_POST["ItemID"] : 0;
            if ($deleteId <= 0) {
                $error_message = "Invalid menu item id for deletion.";
            } else {
                try {
                    $stmt = $conn->prepare("DELETE FROM MenuItems WHERE ItemID = ?");
                    $stmt->bind_param("i", $deleteId);
                    $stmt->execute();
                    $stmt->close();
                    $success_message = "Menu item #{$deleteId} deleted.";
                    $menuForm = ["ItemID" => "", "Name" => "", "Category" => "", "Price" => "", "Description" => "", "IsActive" => 1];
                    $mode = "create";
                    $editItemId = 0;
                } catch (Exception $e) {
                    $error_message = "Unable to delete menu item (in use by orders/recipes): " . $e->getMessage();
                }
            }
        } elseif ($action === "save_rc") {
            foreach ($recipeForm as $key => $val) {
                if (isset($_POST[$key])) {
                    $recipeForm[$key] = is_string($_POST[$key]) ? trim($_POST[$key]) : $_POST[$key];
                }
            }

            $errors = [];
            if ($recipeForm["ItemID"] === "" || !is_numeric($recipeForm["ItemID"])) {
                $errors[] = "Menu Item (ItemID) is required.";
            }
            if ($recipeForm["IngredientID"] === "" || !is_numeric($recipeForm["IngredientID"])) {
                $errors[] = "IngredientID is required.";
            }
            if ($recipeForm["ComponentNo"] === "" || !is_numeric($recipeForm["ComponentNo"]) || (int)$recipeForm["ComponentNo"] < 1) {
                $errors[] = "ComponentNo must be a positive integer.";
            }
            if ($recipeForm["QtyPerItem"] === "" || !is_numeric($recipeForm["QtyPerItem"]) || $recipeForm["QtyPerItem"] < 0) {
                $errors[] = "QtyPerItem must be a non-negative number.";
            }

            if (empty($errors)) {
                $stmt = $conn->prepare(
                    "INSERT INTO RecipeComponents (ItemID, ComponentNo, IngredientID, QtyPerItem)
                     VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE IngredientID = VALUES(IngredientID), QtyPerItem = VALUES(QtyPerItem)"
                );
                $stmt->bind_param("iiid", $recipeForm["ItemID"], $recipeForm["ComponentNo"], $recipeForm["IngredientID"], $recipeForm["QtyPerItem"]);
                $stmt->execute();
                $stmt->close();
                $success_message = "Recipe component saved.";
                $rcMode = "edit";
                $rcEditItemId = (int)$recipeForm["ItemID"];
                $rcEditComponentNo = (int)$recipeForm["ComponentNo"];
            } else {
                $error_message = implode(" | ", $errors);
            }
        } elseif ($action === "delete_rc") {
            $rcItemId = isset($_POST["ItemID"]) ? (int)$_POST["ItemID"] : 0;
            $rcCompNo = isset($_POST["ComponentNo"]) ? (int)$_POST["ComponentNo"] : 0;
            if ($rcItemId <= 0 || $rcCompNo <= 0) {
                $error_message = "Invalid recipe component id for deletion.";
            } else {
                $stmt = $conn->prepare("DELETE FROM RecipeComponents WHERE ItemID = ? AND ComponentNo = ?");
                $stmt->bind_param("ii", $rcItemId, $rcCompNo);
                $stmt->execute();
                $stmt->close();
                $success_message = "Recipe component deleted.";
                if ($rcEditItemId === $rcItemId && $rcEditComponentNo === $rcCompNo) {
                    $recipeForm = ["ItemID" => "", "ComponentNo" => 1, "IngredientID" => "", "QtyPerItem" => ""];
                    $rcMode = "create";
                }
            }
        }
    }
} catch (Exception $e) {
    $error_message = "Error processing request: " . $e->getMessage();
}

// Load data for tables and dropdowns
$menuItems = [];
$recipeComponents = [];
$allIngredients = [];
$activeMenuItems = [];

try {
    $menuItemsResult = $conn->query("SELECT ItemID, Name, Category, Price, IsActive FROM MenuItems ORDER BY Category, Name");
    $menuItems = $menuItemsResult ? $menuItemsResult->fetch_all(MYSQLI_ASSOC) : [];

    $activeMenuItemsResult = $conn->query("SELECT ItemID, Name FROM MenuItems WHERE IsActive = 1 ORDER BY Name");
    $activeMenuItems = $activeMenuItemsResult ? $activeMenuItemsResult->fetch_all(MYSQLI_ASSOC) : [];

    $allIngredientsResult = $conn->query("SELECT IngredientID, Name FROM Ingredients ORDER BY Name");
    $allIngredients = $allIngredientsResult ? $allIngredientsResult->fetch_all(MYSQLI_ASSOC) : [];

    $sql = "
        SELECT 
          rc.ItemID,
          mi.Name AS MenuItemName,
          rc.ComponentNo,
          rc.IngredientID,
          ing.Name AS IngredientName,
          rc.QtyPerItem
        FROM RecipeComponents rc
        JOIN MenuItems mi ON rc.ItemID = mi.ItemID
        JOIN Ingredients ing ON rc.IngredientID = ing.IngredientID
        ORDER BY rc.ItemID, rc.ComponentNo
    ";
    $recipeComponentsResult = $conn->query($sql);
    $recipeComponents = $recipeComponentsResult ? $recipeComponentsResult->fetch_all(MYSQLI_ASSOC) : [];
} catch (Exception $e) {
    $error_message = $error_message ?: ("Error loading data: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Menu Items - Restaurant DB System</title>
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
          <h1 class="page-title"><?php echo $mode === "edit" ? "Edit Menu Item #" . h($menuForm["ItemID"]) : "Create Menu Item"; ?></h1>
          <span class="tag">MenuItems</span>
        </div>
        <p class="page-subtitle">
          Create or edit menu items. This form matches
          <strong>MenuItems(ItemID, Name, Description, Price, Category, IsActive)</strong>.
        </p>

        <form action="manage_menu_items.php<?php echo $mode === 'edit' ? '?mode=edit&item_id=' . h($editItemId) : ''; ?>" method="post">
          <input type="hidden" name="action" value="save_item">
          <div class="form-grid form-grid-2">
            <div class="form-group">
              <label for="itemId">ItemID</label>
              <input id="itemId" name="ItemID" type="text" value="<?php echo h($menuForm["ItemID"]); ?>" placeholder="Existing ID to update, blank to add new">
            </div>
            <div class="form-group">
              <label for="itemName">Name</label>
              <input id="itemName" name="Name" type="text" value="<?php echo h($menuForm["Name"]); ?>" placeholder="Menu item name" required>
            </div>
            <div class="form-group">
              <label for="itemCategory">Category</label>
              <input id="itemCategory" name="Category" type="text" value="<?php echo h($menuForm["Category"]); ?>" placeholder="e.g. Pizza, Salad">
            </div>
            <div class="form-group">
              <label for="itemPrice">Price</label>
              <input id="itemPrice" name="Price" type="number" step="0.01" min="0" value="<?php echo h($menuForm["Price"]); ?>">
            </div>
            <div class="form-group" style="grid-column: 1 / -1;">
              <label for="itemDescription">Description</label>
              <textarea id="itemDescription" name="Description" placeholder="Short description of the dish"><?php echo h($menuForm["Description"]); ?></textarea>
            </div>
            <div class="form-group checkbox-row">
              <input id="itemIsActive" name="IsActive" type="checkbox" value="1" <?php echo ($menuForm["IsActive"] ? "checked" : ""); ?>>
              <label for="itemIsActive">Is Active (available on menu)</label>
            </div>
          </div>
          <div class="btn-row">
            <button type="submit" class="btn btn-primary"><?php echo $mode === "edit" ? "Update Menu Item" : "Create Menu Item"; ?></button>
            <a class="btn btn-outline" href="manage_menu_items.php">New / Reset</a>
          </div>
        </form>
      </section>

      <section class="card">
        <div class="card-header">
          <h2 class="card-title">Recipe Components</h2>
          <span class="tag">RecipeComponents &amp; Ingredients</span>
        </div>
        <p class="page-subtitle">
          Define how each <strong>MenuItem</strong> uses <strong>Ingredients</strong>. This form matches
          <strong>RecipeComponents(ItemID, ComponentNo, IngredientID, QtyPerItem)</strong>.
        </p>

        <form action="manage_menu_items.php<?php echo $rcMode === 'edit' ? '?rc_mode=edit&rc_item_id=' . h($rcEditItemId) . '&rc_component_no=' . h($rcEditComponentNo) : ''; ?>" method="post">
          <input type="hidden" name="action" value="save_rc">
          <div class="form-grid form-grid-2">
            <div class="form-group">
              <label for="rcItemId">Menu Item (ItemID)</label>
              <select id="rcItemId" name="ItemID">
                <option value="">Select ItemID</option>
                <?php foreach ($activeMenuItems as $mi): ?>
                  <option value="<?php echo h($mi["ItemID"]); ?>" <?php echo ($recipeForm["ItemID"] !== "" && (int)$recipeForm["ItemID"] === (int)$mi["ItemID"]) ? "selected" : ""; ?>>
                    <?php echo h($mi["ItemID"] . " - " . $mi["Name"]); ?>
                  </option>
                <?php endforeach; ?>
                <?php
                  // If editing an inactive item, keep it visible
                  $foundActive = false;
                  foreach ($activeMenuItems as $mi) {
                      if ((int)$recipeForm["ItemID"] === (int)$mi["ItemID"]) {
                          $foundActive = true;
                          break;
                      }
                  }
                  if (!$foundActive && $recipeForm["ItemID"] !== "") {
                      echo '<option value="' . h($recipeForm["ItemID"]) . '" selected>' . h($recipeForm["ItemID"]) . ' (inactive item)</option>';
                  }
                ?>
              </select>
            </div>
            <div class="form-group">
              <label for="rcComponentNo">ComponentNo</label>
              <input id="rcComponentNo" name="ComponentNo" type="number" min="1" value="<?php echo h($recipeForm["ComponentNo"]); ?>">
            </div>
            <div class="form-group">
              <label for="rcIngredientId">Ingredient (IngredientID)</label>
              <select id="rcIngredientId" name="IngredientID">
                <option value="">Select Ingredient</option>
                <?php foreach ($allIngredients as $ing): ?>
                  <option value="<?php echo h($ing["IngredientID"]); ?>" <?php echo ($recipeForm["IngredientID"] !== "" && (int)$recipeForm["IngredientID"] === (int)$ing["IngredientID"]) ? "selected" : ""; ?>>
                    <?php echo h($ing["IngredientID"] . " - " . $ing["Name"]); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="rcQtyPerItem">QtyPerItem</label>
              <input id="rcQtyPerItem" name="QtyPerItem" type="number" step="0.01" min="0" value="<?php echo h($recipeForm["QtyPerItem"]); ?>">
              <span class="helper-text">Quantity of this ingredient per single menu item (matches Unit in Ingredients).</span>
            </div>
          </div>
          <div class="btn-row">
            <button type="submit" class="btn btn-primary"><?php echo $rcMode === "edit" ? "Update Recipe Component" : "Save Recipe Component"; ?></button>
            <a class="btn btn-outline" href="manage_menu_items.php">New / Reset</a>
          </div>
        </form>

        <div class="section-divider"></div>
        <h3 class="card-title" style="margin-bottom:0.5rem;">RecipeComponents (live data)</h3>
        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>ItemID</th>
                <th>Menu Item</th>
                <th>ComponentNo</th>
                <th>IngredientID</th>
                <th>Ingredient</th>
                <th>QtyPerItem</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($recipeComponents)): ?>
                <tr><td colspan="7" style="text-align:center;">No recipe components found.</td></tr>
              <?php else: ?>
                <?php foreach ($recipeComponents as $rc): ?>
                  <tr>
                    <td><?php echo h($rc["ItemID"]); ?></td>
                    <td><?php echo h($rc["MenuItemName"]); ?></td>
                    <td><?php echo h($rc["ComponentNo"]); ?></td>
                    <td><?php echo h($rc["IngredientID"]); ?></td>
                    <td><?php echo h($rc["IngredientName"]); ?></td>
                    <td><?php echo h($rc["QtyPerItem"]); ?></td>
                    <td class="table-actions">
                      <a class="btn btn-small" href="manage_menu_items.php?rc_mode=edit&rc_item_id=<?php echo h($rc["ItemID"]); ?>&rc_component_no=<?php echo h($rc["ComponentNo"]); ?>">Edit</a>
                      <form action="manage_menu_items.php" method="post" style="display:inline;">
                        <input type="hidden" name="action" value="delete_rc">
                        <input type="hidden" name="ItemID" value="<?php echo h($rc["ItemID"]); ?>">
                        <input type="hidden" name="ComponentNo" value="<?php echo h($rc["ComponentNo"]); ?>">
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

      <section class="card">
        <div class="card-header">
          <h2 class="card-title">Menu Items (live)</h2>
          <span class="card-note">Showing items from MenuItems table.</span>
        </div>
        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>ItemID</th>
                <th>Name</th>
                <th>Category</th>
                <th>Price</th>
                <th>IsActive</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($menuItems)): ?>
                <tr><td colspan="6" style="text-align:center;">No menu items found.</td></tr>
              <?php else: ?>
                <?php foreach ($menuItems as $mi): ?>
                  <tr>
                    <td><?php echo h($mi["ItemID"]); ?></td>
                    <td><?php echo h($mi["Name"]); ?></td>
                    <td><?php echo h($mi["Category"]); ?></td>
                    <td><?php echo h(number_format((float)$mi["Price"], 2)); ?></td>
                    <td><?php echo $mi["IsActive"] ? "Yes" : "No"; ?></td>
                    <td class="table-actions">
                      <a class="btn btn-small" href="manage_menu_items.php?mode=edit&item_id=<?php echo h($mi["ItemID"]); ?>">Edit</a>
                      <form action="manage_menu_items.php" method="post" style="display:inline;">
                        <input type="hidden" name="action" value="delete_item">
                        <input type="hidden" name="ItemID" value="<?php echo h($mi["ItemID"]); ?>">
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
