<?php
require_once "config.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$success_message = "";
$error_message = "";

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

try {
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $formType = isset($_POST["form_type"]) ? $_POST["form_type"] : "menu_item";

        if ($formType === "menu_item") {
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
                    $stmt->bind_param("ssdssi", $menuForm["Name"], $menuForm["Description"], $menuForm["Price"], $menuForm["Category"], $menuForm["IsActive"], $itemId);
                    $stmt->execute();
                    $stmt->close();
                    $success_message = "Menu item updated (ItemID {$itemId}).";
                } else {
                    $stmt = $conn->prepare("INSERT INTO MenuItems (Name, Description, Price, Category, IsActive) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssdsi", $menuForm["Name"], $menuForm["Description"], $menuForm["Price"], $menuForm["Category"], $menuForm["IsActive"]);
                    $stmt->execute();
                    $itemId = $conn->insert_id;
                    $stmt->close();
                    $menuForm["ItemID"] = $itemId;
                    $success_message = "Menu item created (ItemID {$itemId}).";
                }
            } else {
                $error_message = implode(" | ", $errors);
            }
        } elseif ($formType === "recipe_component") {
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
            } else {
                $error_message = implode(" | ", $errors);
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

try {
    $menuItemsResult = $conn->query("SELECT ItemID, Name, Category, Price, IsActive FROM MenuItems ORDER BY Category, Name");
    $menuItems = $menuItemsResult ? $menuItemsResult->fetch_all(MYSQLI_ASSOC) : [];

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
        <p class="helper-text" style="color:#22c55e;"><?php echo htmlspecialchars($success_message); ?></p>
      <?php endif; ?>
      <?php if (!empty($error_message)): ?>
        <p class="helper-text" style="color:#f97373;"><?php echo htmlspecialchars($error_message); ?></p>
      <?php endif; ?>

      <section class="card">
        <div class="card-header">
          <h1 class="page-title">Menu Items</h1>
          <span class="tag">MenuItems</span>
        </div>
        <p class="page-subtitle">
          Create or edit menu items. This form matches
          <strong>MenuItems(ItemID, Name, Description, Price, Category, IsActive)</strong>.
        </p>

        <form action="manage_menu_items.php" method="post">
          <input type="hidden" name="form_type" value="menu_item">
          <div class="form-grid form-grid-2">
            <div class="form-group">
              <label for="itemId">ItemID</label>
              <input id="itemId" name="ItemID" type="text" value="<?php echo htmlspecialchars($menuForm["ItemID"]); ?>" placeholder="Existing ID to update, blank to add new">
            </div>
            <div class="form-group">
              <label for="itemName">Name</label>
              <input id="itemName" name="Name" type="text" value="<?php echo htmlspecialchars($menuForm["Name"]); ?>" placeholder="Menu item name" required>
            </div>
            <div class="form-group">
              <label for="itemCategory">Category</label>
              <input id="itemCategory" name="Category" type="text" value="<?php echo htmlspecialchars($menuForm["Category"]); ?>" placeholder="e.g. Pizza, Salad">
            </div>
            <div class="form-group">
              <label for="itemPrice">Price</label>
              <input id="itemPrice" name="Price" type="number" step="0.01" min="0" value="<?php echo htmlspecialchars($menuForm["Price"]); ?>">
            </div>
            <div class="form-group" style="grid-column: 1 / -1;">
              <label for="itemDescription">Description</label>
              <textarea id="itemDescription" name="Description" placeholder="Short description of the dish"><?php echo htmlspecialchars($menuForm["Description"]); ?></textarea>
            </div>
            <div class="form-group checkbox-row">
              <input id="itemIsActive" name="IsActive" type="checkbox" value="1" <?php echo ($menuForm["IsActive"] ? "checked" : ""); ?>>
              <label for="itemIsActive">Is Active (available on menu)</label>
            </div>
          </div>
          <div class="btn-row">
            <button type="submit" class="btn btn-primary">Save Menu Item</button>
            <button type="reset" class="btn btn-outline" onclick="window.location='manage_menu_items.php'; return false;">Clear</button>
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

        <form action="manage_menu_items.php" method="post">
          <input type="hidden" name="form_type" value="recipe_component">
          <div class="form-grid form-grid-2">
            <div class="form-group">
              <label for="rcItemId">Menu Item (ItemID)</label>
              <select id="rcItemId" name="ItemID">
                <option value="">Select ItemID</option>
                <?php foreach ($menuItems as $mi): ?>
                  <option value="<?php echo htmlspecialchars($mi["ItemID"]); ?>" <?php echo ($recipeForm["ItemID"] !== "" && (int)$recipeForm["ItemID"] === (int)$mi["ItemID"]) ? "selected" : ""; ?>>
                    <?php echo htmlspecialchars($mi["ItemID"] . " - " . $mi["Name"]); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="rcComponentNo">ComponentNo</label>
              <input id="rcComponentNo" name="ComponentNo" type="number" min="1" value="<?php echo htmlspecialchars($recipeForm["ComponentNo"]); ?>">
            </div>
            <div class="form-group">
              <label for="rcIngredientId">Ingredient (IngredientID)</label>
              <select id="rcIngredientId" name="IngredientID">
                <option value="">Select Ingredient</option>
                <?php foreach ($allIngredients as $ing): ?>
                  <option value="<?php echo htmlspecialchars($ing["IngredientID"]); ?>" <?php echo ($recipeForm["IngredientID"] !== "" && (int)$recipeForm["IngredientID"] === (int)$ing["IngredientID"]) ? "selected" : ""; ?>>
                    <?php echo htmlspecialchars($ing["IngredientID"] . " - " . $ing["Name"]); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="rcQtyPerItem">QtyPerItem</label>
              <input id="rcQtyPerItem" name="QtyPerItem" type="number" step="0.01" min="0" value="<?php echo htmlspecialchars($recipeForm["QtyPerItem"]); ?>">
              <span class="helper-text">Quantity of this ingredient per single menu item (matches Unit in Ingredients).</span>
            </div>
          </div>
          <div class="btn-row">
            <button type="submit" class="btn btn-primary">Save Recipe Component</button>
            <button type="reset" class="btn btn-outline" onclick="window.location='manage_menu_items.php'; return false;">Clear</button>
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
              </tr>
            </thead>
            <tbody>
              <?php if (empty($recipeComponents)): ?>
                <tr><td colspan="6" style="text-align:center;">No recipe components found.</td></tr>
              <?php else: ?>
                <?php foreach ($recipeComponents as $rc): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($rc["ItemID"]); ?></td>
                    <td><?php echo htmlspecialchars($rc["MenuItemName"]); ?></td>
                    <td><?php echo htmlspecialchars($rc["ComponentNo"]); ?></td>
                    <td><?php echo htmlspecialchars($rc["IngredientID"]); ?></td>
                    <td><?php echo htmlspecialchars($rc["IngredientName"]); ?></td>
                    <td><?php echo htmlspecialchars($rc["QtyPerItem"]); ?></td>
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
              </tr>
            </thead>
            <tbody>
              <?php if (empty($menuItems)): ?>
                <tr><td colspan="5" style="text-align:center;">No menu items found.</td></tr>
              <?php else: ?>
                <?php foreach ($menuItems as $mi): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($mi["ItemID"]); ?></td>
                    <td><?php echo htmlspecialchars($mi["Name"]); ?></td>
                    <td><?php echo htmlspecialchars($mi["Category"]); ?></td>
                    <td><?php echo htmlspecialchars(number_format((float)$mi["Price"], 2)); ?></td>
                    <td><?php echo $mi["IsActive"] ? "Yes" : "No"; ?></td>
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
