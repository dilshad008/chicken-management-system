<?php
session_start();
require_once '../includes/check_auth.php';
require_once '../config/db_config.php';
require_once '../includes/functions.php';

$success = '';
$error = '';

// Handle Add/Edit Seller
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'add';
    $seller_name = $_POST['seller_name'] ?? '';
    $seller_phone = $_POST['seller_phone'] ?? '';
    $seller_email = $_POST['seller_email'] ?? '';
    $seller_address = $_POST['seller_address'] ?? '';
    $seller_city = $_POST['seller_city'] ?? '';
    $seller_type = $_POST['seller_type'] ?? '';
    $seller_status = $_POST['seller_status'] ?? 'active';
    
    if (!empty($seller_name) && !empty($seller_phone)) {
        try {
            if ($action === 'add') {
                $stmt = $pdo->prepare("
                    INSERT INTO sellers (seller_name, seller_phone, seller_email, seller_address, seller_city, seller_type, seller_status, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $seller_name, 
                    $seller_phone, 
                    $seller_email, 
                    $seller_address, 
                    $seller_city, 
                    $seller_type,
                    $seller_status,
                    $_SESSION['user_id']
                ]);
                $success = 'Seller added successfully!';
            } elseif ($action === 'edit') {
                $seller_id = $_POST['seller_id'] ?? '';
                $stmt = $pdo->prepare("
                    UPDATE sellers 
                    SET seller_name = ?, seller_phone = ?, seller_email = ?, seller_address = ?, seller_city = ?, seller_type = ?, seller_status = ?
                    WHERE seller_id = ?
                ");
                $stmt->execute([
                    $seller_name, 
                    $seller_phone, 
                    $seller_email, 
                    $seller_address, 
                    $seller_city, 
                    $seller_type,
                    $seller_status,
                    $seller_id
                ]);
                $success = 'Seller updated successfully!';
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    } else {
        $error = 'Please fill in seller name and phone!';
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $seller_id = $_GET['delete'];
    try {
        $stmt = $pdo->prepare('DELETE FROM sellers WHERE seller_id = ?');
        $stmt->execute([$seller_id]);
        $success = 'Seller deleted successfully!';
    } catch (Exception $e) {
        $error = 'Cannot delete seller with existing records!';
    }
}

// Handle Status Change
if (isset($_GET['toggle_status'])) {
    $seller_id = $_GET['toggle_status'];
    $stmt = $pdo->prepare('SELECT seller_status FROM sellers WHERE seller_id = ?');
    $stmt->execute([$seller_id]);
    $seller = $stmt->fetch();
    
    if ($seller) {
        $new_status = $seller['seller_status'] === 'active' ? 'inactive' : 'active';
        $stmt = $pdo->prepare('UPDATE sellers SET seller_status = ? WHERE seller_id = ?');
        $stmt->execute([$new_status, $seller_id]);
        $success = 'Seller status updated!';
    }
}

// Get all sellers
$sellers = $pdo->query('SELECT * FROM sellers ORDER BY seller_name')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sellers Management - Chicken Management System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); }
        .modal.active { display: block; }
        .modal-content { background-color: white; margin: 10% auto; padding: 30px; border-radius: 8px; width: 90%; max-width: 500px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close:hover { color: #000; }
        .seller-badge { display: inline-block; padding: 5px 10px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .badge-active { background-color: #d4edda; color: #155724; }
        .badge-inactive { background-color: #f8d7da; color: #721c24; }
        .action-buttons { display: flex; gap: 5px; }
        .action-buttons button { padding: 5px 10px; font-size: 12px; }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <h1>🐔 Chicken Management System</h1>
            <div class="nav-links">
                <a href="../dashboard.php">Dashboard</a>
                <a href="rates.php">Daily Rates</a>
                <a href="sellers.php" class="active">Sellers</a>
                <a href="purchasers.php">Purchasers</a>
                <a href="inventory.php">Inventory</a>
                <a href="invoices.php">Invoices</a>
                <a href="../actions/logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <h2>👥 Sellers Management</h2>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <button class="btn btn-primary" onclick="openModal('sellerModal')">+ Add New Seller</button>

        <div class="section">
            <h3>All Sellers (<?php echo count($sellers); ?>)</h3>
            <?php if (count($sellers) > 0): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Seller Name</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>City</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sellers as $seller): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($seller['seller_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($seller['seller_phone']); ?></td>
                                <td><?php echo htmlspecialchars($seller['seller_email']); ?></td>
                                <td><?php echo htmlspecialchars($seller['seller_city']); ?></td>
                                <td><?php echo htmlspecialchars($seller['seller_type']); ?></td>
                                <td>
                                    <span class="seller-badge <?php echo $seller['seller_status'] === 'active' ? 'badge-active' : 'badge-inactive'; ?>">
                                        <?php echo ucfirst($seller['seller_status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-sm btn-info" onclick="editSeller(<?php echo $seller['seller_id']; ?>, '<?php echo addslashes($seller['seller_name']); ?>', '<?php echo addslashes($seller['seller_phone']); ?>', '<?php echo addslashes($seller['seller_email']); ?>', '<?php echo addslashes($seller['seller_address']); ?>', '<?php echo addslashes($seller['seller_city']); ?>', '<?php echo $seller['seller_type']; ?>', '<?php echo $seller['seller_status']; ?>')">Edit</button>
                                        <a href="?toggle_status=<?php echo $seller['seller_id']; ?>" class="btn btn-sm btn-warning">Toggle</a>
                                        <a href="?delete=<?php echo $seller['seller_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">Delete</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="alert alert-info">No sellers found. Add one using the button above.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add/Edit Modal -->
    <div id="sellerModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('sellerModal')">&times;</span>
            <h2 id="modalTitle">Add Seller</h2>
            <form method="POST" id="sellerForm">
                <input type="hidden" name="action" id="action" value="add">
                <input type="hidden" name="seller_id" id="seller_id">
                
                <div class="form-group">
                    <label for="seller_name">Seller Name *</label>
                    <input type="text" id="seller_name" name="seller_name" required>
                </div>
                
                <div class="form-group">
                    <label for="seller_phone">Phone *</label>
                    <input type="tel" id="seller_phone" name="seller_phone" required>
                </div>
                
                <div class="form-group">
                    <label for="seller_email">Email</label>
                    <input type="email" id="seller_email" name="seller_email">
                </div>
                
                <div class="form-group">
                    <label for="seller_type">Seller Type</label>
                    <select id="seller_type" name="seller_type">
                        <option value="Individual">Individual</option>
                        <option value="Company">Company</option>
                        <option value="Wholesaler">Wholesaler</option>
                        <option value="Retailer">Retailer</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="seller_address">Address</label>
                    <textarea id="seller_address" name="seller_address" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="seller_city">City</label>
                    <input type="text" id="seller_city" name="seller_city">
                </div>
                
                <div class="form-group">
                    <label for="seller_status">Status</label>
                    <select id="seller_status" name="seller_status">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">Save Seller</button>
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
            document.getElementById('sellerForm').reset();
            document.getElementById('action').value = 'add';
            document.getElementById('seller_id').value = '';
            document.getElementById('modalTitle').textContent = 'Add Seller';
        }
        
        function editSeller(id, name, phone, email, address, city, type, status) {
            document.getElementById('action').value = 'edit';
            document.getElementById('seller_id').value = id;
            document.getElementById('seller_name').value = name;
            document.getElementById('seller_phone').value = phone;
            document.getElementById('seller_email').value = email;
            document.getElementById('seller_address').value = address;
            document.getElementById('seller_city').value = city;
            document.getElementById('seller_type').value = type;
            document.getElementById('seller_status').value = status;
            document.getElementById('modalTitle').textContent = 'Edit Seller';
            openModal('sellerModal');
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            let modal = document.getElementById('sellerModal');
            if (event.target === modal) {
                closeModal('sellerModal');
            }
        }
    </script>
</body>
</html>