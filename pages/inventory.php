<?php
session_start();
require_once '../includes/check_auth.php';
require_once '../config/db_config.php';
require_once '../includes/functions.php';

$success = '';
$error = '';
$chicken_types = get_chicken_types($pdo);

// Handle Add/Edit Inventory
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'add';
    $chicken_type_id = $_POST['chicken_type_id'] ?? '';
    $quantity = $_POST['quantity'] ?? '';
    $unit_price = $_POST['unit_price'] ?? '';
    $warehouse_location = $_POST['warehouse_location'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
    if (!empty($chicken_type_id) && !empty($quantity) && is_numeric($quantity) && is_numeric($unit_price)) {
        try {
            if ($action === 'add') {
                $stmt = $pdo->prepare("
                    INSERT INTO inventory (chicken_type_id, quantity, unit_price, warehouse_location, notes, created_by)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $chicken_type_id,
                    $quantity,
                    $unit_price,
                    $warehouse_location,
                    $notes,
                    $_SESSION['user_id']
                ]);
                $success = 'Inventory item added successfully!';
            } elseif ($action === 'edit') {
                $inventory_id = $_POST['inventory_id'] ?? '';
                $stmt = $pdo->prepare("
                    UPDATE inventory 
                    SET chicken_type_id = ?, quantity = ?, unit_price = ?, warehouse_location = ?, notes = ?
                    WHERE inventory_id = ?
                ");
                $stmt->execute([
                    $chicken_type_id,
                    $quantity,
                    $unit_price,
                    $warehouse_location,
                    $notes,
                    $inventory_id
                ]);
                $success = 'Inventory updated successfully!';
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    } else {
        $error = 'Please fill in all required fields with valid numbers!';
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $inventory_id = $_GET['delete'];
    try {
        $stmt = $pdo->prepare('DELETE FROM inventory WHERE inventory_id = ?');
        $stmt->execute([$inventory_id]);
        $success = 'Inventory item deleted successfully!';
    } catch (Exception $e) {
        $error = 'Error deleting inventory!';
    }
}

// Get all inventory with chicken type names
$inventory = $pdo->query("
    SELECT i.*, ct.name as chicken_name
    FROM inventory i
    JOIN chicken_types ct ON i.chicken_type_id = ct.id
    ORDER BY ct.name
")->fetchAll();

// Calculate total inventory value
$total_value = 0;
foreach ($inventory as $item) {
    $total_value += ($item['quantity'] * $item['unit_price']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - Chicken Management System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); }
        .modal.active { display: block; }
        .modal-content { background-color: white; margin: 10% auto; padding: 30px; border-radius: 8px; width: 90%; max-width: 500px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close:hover { color: #000; }
        .inventory-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .action-buttons { display: flex; gap: 5px; }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <h1>🐔 Chicken Management System</h1>
            <div class="nav-links">
                <a href="../dashboard.php">Dashboard</a>
                <a href="rates.php">Daily Rates</a>
                <a href="sellers.php">Sellers</a>
                <a href="purchasers.php">Purchasers</a>
                <a href="inventory.php" class="active">Inventory</a>
                <a href="invoices.php">Invoices</a>
                <a href="../actions/logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <h2>📦 Inventory Management</h2>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Inventory Stats -->
        <div class="inventory-stats">
            <div class="stat-card">
                <h4>Total Items</h4>
                <p style="font-size: 24px; color: #3498db;"><?php echo count($inventory); ?></p>
            </div>
            <div class="stat-card">
                <h4>Total Quantity</h4>
                <p style="font-size: 24px; color: #27ae60;"><?php echo number_format(array_sum(array_column($inventory, 'quantity')), 2); ?> kg</p>
            </div>
            <div class="stat-card">
                <h4>Total Inventory Value</h4>
                <p style="font-size: 24px; color: #e74c3c;"><?php echo format_currency($total_value); ?></p>
            </div>
        </div>

        <button class="btn btn-primary" onclick="openModal('inventoryModal')">+ Add Inventory Item</button>

        <div class="section">
            <h3>Inventory Items</h3>
            <?php if (count($inventory) > 0): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Chicken Type</th>
                            <th>Quantity (kg)</th>
                            <th>Unit Price (Rs)</th>
                            <th>Total Value (Rs)</th>
                            <th>Location</th>
                            <th>Last Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inventory as $item): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($item['chicken_name']); ?></strong></td>
                                <td><?php echo number_format($item['quantity'], 2); ?></td>
                                <td><?php echo format_currency($item['unit_price']); ?></td>
                                <td><?php echo format_currency($item['quantity'] * $item['unit_price']); ?></td>
                                <td><?php echo htmlspecialchars($item['warehouse_location']); ?></td>
                                <td><?php echo date('d-M-Y', strtotime($item['updated_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-sm btn-info" onclick="editInventory(<?php echo $item['inventory_id']; ?>, <?php echo $item['chicken_type_id']; ?>, <?php echo $item['quantity']; ?>, <?php echo $item['unit_price']; ?>, '<?php echo addslashes($item['warehouse_location']); ?>', '<?php echo addslashes($item['notes']); ?>')">Edit</button>
                                        <a href="?delete=<?php echo $item['inventory_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">Delete</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="alert alert-info">No inventory items found. Add one using the button above.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add/Edit Modal -->
    <div id="inventoryModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('inventoryModal')">&times;</span>
            <h2 id="modalTitle">Add Inventory Item</h2>
            <form method="POST" id="inventoryForm">
                <input type="hidden" name="action" id="action" value="add">
                <input type="hidden" name="inventory_id" id="inventory_id">
                
                <div class="form-group">
                    <label for="chicken_type_id">Chicken Type *</label>
                    <select id="chicken_type_id" name="chicken_type_id" required>
                        <option value="">-- Select Type --</option>
                        <?php foreach ($chicken_types as $ct): ?>
                            <option value="<?php echo $ct['id']; ?>"><?php echo htmlspecialchars($ct['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="quantity">Quantity (kg) *</label>
                    <input type="number" id="quantity" name="quantity" step="0.01" min="0" required>
                </div>
                
                <div class="form-group">
                    <label for="unit_price">Unit Price (Rs/kg) *</label>
                    <input type="number" id="unit_price" name="unit_price" step="0.01" min="0" required>
                </div>
                
                <div class="form-group">
                    <label for="warehouse_location">Warehouse Location</label>
                    <input type="text" id="warehouse_location" name="warehouse_location" placeholder="e.g., Shelf A1">
                </div>
                
                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" rows="3" placeholder="Additional notes..."></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">Save Inventory</button>
            </form>
        </div>
    </div>

    <script>
        function openModal(id) {
            document.getElementById(id).classList.add('active');
            resetForm();
        }
        
        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }
        
        function resetForm() {
            document.getElementById('inventoryForm').reset();
            document.getElementById('action').value = 'add';
            document.getElementById('inventory_id').value = '';
            document.getElementById('modalTitle').textContent = 'Add Inventory Item';
        }
        
        function editInventory(id, typeId, quantity, price, location, notes) {
            document.getElementById('action').value = 'edit';
            document.getElementById('inventory_id').value = id;
            document.getElementById('chicken_type_id').value = typeId;
            document.getElementById('quantity').value = quantity;
            document.getElementById('unit_price').value = price;
            document.getElementById('warehouse_location').value = location;
            document.getElementById('notes').value = notes;
            document.getElementById('modalTitle').textContent = 'Edit Inventory Item';
            openModal('inventoryModal');
        }
        
        window.onclick = function(event) {
            let modal = document.getElementById('inventoryModal');
            if (event.target === modal) {
                closeModal('inventoryModal');
            }
        }
    </script>
</body>
</html>