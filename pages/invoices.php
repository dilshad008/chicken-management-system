<?php
session_start();
require_once '../includes/check_auth.php';
require_once '../config/db_config.php';
require_once '../includes/functions.php';

$success = '';
$error = '';
$chicken_types = get_chicken_types($pdo);
$sellers = $pdo->query('SELECT * FROM sellers WHERE seller_status = "active" ORDER BY seller_name')->fetchAll();
$purchasers = $pdo->query('SELECT * FROM purchasers ORDER BY name')->fetchAll();
$today_rates = get_today_rates($pdo);

// View Invoice
$view_invoice = null;
if (isset($_GET['view'])) {
    $view_id = $_GET['view'];
    $stmt = $pdo->prepare("
        SELECT i.*, s.seller_name, s.seller_phone, s.seller_email, s.seller_address, p.name as purchaser_name, p.phone as purchaser_phone
        FROM invoices i
        LEFT JOIN sellers s ON i.seller_id = s.seller_id
        LEFT JOIN purchasers p ON i.purchaser_id = p.id
        WHERE i.invoice_id = ?
    ");
    $stmt->execute([$view_id]);
    $view_invoice = $stmt->fetch();
    
    if ($view_invoice) {
        $items = $pdo->prepare("
            SELECT ii.*, ct.name as chicken_name
            FROM invoice_items ii
            JOIN chicken_types ct ON ii.chicken_type_id = ct.id
            WHERE ii.invoice_id = ?
        ");
        $items->execute([$view_id]);
        $view_invoice['items'] = $items->fetchAll();
    }
}

// Create Invoice
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_GET['view'])) {
    $invoice_type = $_POST['invoice_type'] ?? 'purchase';
    $seller_id = $_POST['seller_id'] ?? '';
    $purchaser_id = $_POST['purchaser_id'] ?? '';
    $items = $_POST['items'] ?? [];
    $notes = $_POST['notes'] ?? '';
    
    $related_id = ($invoice_type === 'purchase') ? $seller_id : $purchaser_id;
    
    if (!empty($related_id) && !empty($items)) {
        try {
            $pdo->beginTransaction();
            
            $invoice_number = generate_invoice_number($pdo);
            $total_amount = 0;
            
            // Calculate total
            foreach ($items as $item) {
                if (!empty($item['chicken_type_id']) && !empty($item['quantity']) && !empty($item['rate'])) {
                    $amount = $item['quantity'] * $item['rate'];
                    $total_amount += $amount;
                }
            }
            
            // Insert Invoice
            if ($invoice_type === 'purchase') {
                $stmt = $pdo->prepare("
                    INSERT INTO invoices (invoice_number, invoice_type, seller_id, invoice_date, total_amount, notes, created_by)
                    VALUES (?, ?, ?, CURDATE(), ?, ?, ?)
                ");
                $stmt->execute([$invoice_number, $invoice_type, $seller_id, $total_amount, $notes, $_SESSION['user_id']]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO invoices (invoice_number, invoice_type, purchaser_id, invoice_date, total_amount, notes, created_by)
                    VALUES (?, ?, ?, CURDATE(), ?, ?, ?)
                ");
                $stmt->execute([$invoice_number, $invoice_type, $purchaser_id, $total_amount, $notes, $_SESSION['user_id']]);
            }
            
            $invoice_id = $pdo->lastInsertId();
            
            // Insert Items
            foreach ($items as $item) {
                if (!empty($item['chicken_type_id']) && !empty($item['quantity']) && !empty($item['rate'])) {
                    $amount = $item['quantity'] * $item['rate'];
                    $stmt = $pdo->prepare("
                        INSERT INTO invoice_items (invoice_id, chicken_type_id, quantity, rate, amount)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$invoice_id, $item['chicken_type_id'], $item['quantity'], $item['rate'], $amount]);
                }
            }
            
            $pdo->commit();
            $success = "Invoice $invoice_number created successfully!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error creating invoice: ' . $e->getMessage();
        }
    } else {
        $error = 'Please select seller/purchaser and add at least one item!';
    }
}

// Get all invoices
$invoices = $pdo->query("
    SELECT i.*, 
           COALESCE(s.seller_name, p.name) as party_name,
           i.invoice_type
    FROM invoices i
    LEFT JOIN sellers s ON i.seller_id = s.seller_id
    LEFT JOIN purchasers p ON i.purchaser_id = p.id
    ORDER BY i.created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoices - Chicken Management System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .invoice-items table { width: 100%; margin-top: 20px; }
        .invoice-items table input, .invoice-items table select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .add-item-btn { margin-top: 10px; }
        .remove-item { background: #dc3545; color: white; padding: 8px 12px; border: none; cursor: pointer; border-radius: 4px; }
        .invoice-badge { display: inline-block; padding: 5px 10px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .badge-purchase { background-color: #d1ecf1; color: #0c5460; }
        .badge-sale { background-color: #d4edda; color: #155724; }
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
                <a href="inventory.php">Inventory</a>
                <a href="invoices.php" class="active">Invoices</a>
                <a href="../actions/logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <?php if ($view_invoice): ?>
            <!-- View Invoice -->
            <h2>Invoice #<?php echo htmlspecialchars($view_invoice['invoice_number']); ?></h2>
            
            <div class="section">
                <div class="invoice-view">
                    <div class="invoice-header">
                        <h1>INVOICE</h1>
                        <p><strong>Invoice #:</strong> <?php echo htmlspecialchars($view_invoice['invoice_number']); ?></p>
                        <p><strong>Type:</strong> <span class="invoice-badge <?php echo $view_invoice['invoice_type'] === 'purchase' ? 'badge-purchase' : 'badge-sale'; ?>"><?php echo ucfirst($view_invoice['invoice_type']); ?></span></p>
                        <p><strong>Date:</strong> <?php echo date('d-M-Y', strtotime($view_invoice['invoice_date'])); ?></p>
                    </div>
                    
                    <div class="invoice-details">
                        <div>
                            <h4><?php echo $view_invoice['invoice_type'] === 'purchase' ? 'Purchased From:' : 'Sold To:'; ?></h4>
                            <p><strong><?php echo htmlspecialchars($view_invoice['party_name']); ?></strong></p>
                            <p><?php echo htmlspecialchars($view_invoice['seller_address'] ?? ''); ?></p>
                            <p>Phone: <?php echo htmlspecialchars($view_invoice['seller_phone'] ?? $view_invoice['purchaser_phone'] ?? ''); ?></p>
                        </div>
                    </div>
                    
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Quantity (kg)</th>
                                <th>Rate (Rs/kg)</th>
                                <th>Amount (Rs)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($view_invoice['items'] as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['chicken_name']); ?></td>
                                    <td><?php echo number_format($item['quantity'], 2); ?></td>
                                    <td><?php echo format_currency($item['rate']); ?></td>
                                    <td><?php echo format_currency($item['amount']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <div class="invoice-total">
                        <h3>Total: <?php echo format_currency($view_invoice['total_amount']); ?></h3>
                        <?php if ($view_invoice['notes']): ?>
                            <p><strong>Notes:</strong> <?php echo htmlspecialchars($view_invoice['notes']); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="invoice-actions">
                        <a href="../actions/download_invoice.php?id=<?php echo $view_invoice['invoice_id']; ?>" class="btn btn-success">📥 Download PDF</a>
                        <a href="invoices.php" class="btn btn-secondary">Back to List</a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Create/List Invoices -->
            <h2>📋 Invoices</h2>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="section">
                <h3>Create New Invoice</h3>
                <form method="POST" id="invoiceForm">
                    <div class="form-group">
                        <label for="invoice_type">Invoice Type *</label>
                        <select id="invoice_type" name="invoice_type" onchange="updatePartyDropdown()" required>
                            <option value="purchase">Purchase from Seller</option>
                            <option value="sale">Sale to Purchaser</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="seller_id" id="partyLabel">Select Seller *</label>
                        <select id="seller_id" name="seller_id">
                            <option value="">-- Select Seller --</option>
                            <?php foreach ($sellers as $s): ?>
                                <option value="<?php echo $s['seller_id']; ?>">
                                    <?php echo htmlspecialchars($s['seller_name']); ?> (<?php echo htmlspecialchars($s['seller_phone']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select id="purchaser_id" name="purchaser_id" style="display:none;">
                            <option value="">-- Select Purchaser --</option>
                            <?php foreach ($purchasers as $p): ?>
                                <option value="<?php echo $p['id']; ?>">
                                    <?php echo htmlspecialchars($p['name']); ?> (<?php echo htmlspecialchars($p['phone']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="invoice-items">
                        <h4>Invoice Items</h4>
                        <table id="itemsTable">
                            <thead>
                                <tr>
                                    <th>Chicken Type</th>
                                    <th>Quantity (kg)</th>
                                    <th>Rate (Rs/kg)</th>
                                    <th>Amount (Rs)</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="itemsBody">
                                <tr class="item-row">
                                    <td>
                                        <select name="items[0][chicken_type_id]" class="chicken-select" onchange="updateRate(this)" required>
                                            <option value="">-- Select --</option>
                                            <?php foreach ($chicken_types as $ct): ?>
                                                <option value="<?php echo $ct['id']; ?>">
                                                    <?php echo htmlspecialchars($ct['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td><input type="number" name="items[0][quantity]" step="0.01" min="0" onchange="calculateAmount(this)" required></td>
                                    <td><input type="number" name="items[0][rate]" step="0.01" min="0" onchange="calculateAmount(this)" required></td>
                                    <td><span class="item-amount">0.00</span></td>
                                    <td><button type="button" class="remove-item" onclick="removeItem(this)">Remove</button></td>
                                </tr>
                            </tbody>
                        </table>
                        <button type="button" class="btn btn-secondary add-item-btn" onclick="addItem()">+ Add Item</button>
                    </div>

                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea id="notes" name="notes" rows="3" placeholder="Additional notes..."></textarea>
                    </div>

                    <div class="form-group" style="margin-top: 20px;">
                        <h3>Invoice Total: <span id="invoiceTotal">0.00</span> Rs</h3>
                    </div>

                    <button type="submit" class="btn btn-primary" style="margin-top: 20px;">Create Invoice</button>
                </form>
            </div>

            <div class="section">
                <h3>Recent Invoices</h3>
                <?php if (count($invoices) > 0): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Type</th>
                                <th>Party</th>
                                <th>Amount</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invoices as $inv): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($inv['invoice_number']); ?></strong></td>
                                    <td><span class="invoice-badge <?php echo $inv['invoice_type'] === 'purchase' ? 'badge-purchase' : 'badge-sale'; ?>"><?php echo ucfirst($inv['invoice_type']); ?></span></td>
                                    <td><?php echo htmlspecialchars($inv['party_name']); ?></td>
                                    <td><?php echo format_currency($inv['total_amount']); ?></td>
                                    <td><?php echo date('d-M-Y', strtotime($inv['invoice_date'])); ?></td>
                                    <td>
                                        <a href="?view=<?php echo $inv['invoice_id']; ?>" class="btn btn-sm btn-info">View</a>
                                        <a href="../actions/download_invoice.php?id=<?php echo $inv['invoice_id']; ?>" class="btn btn-sm btn-success">Download</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="alert alert-info">No invoices yet.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        let itemCount = 1;
        const ratesData = <?php echo json_encode(array_column($today_rates, 'rate', 'id')); ?>;

        function updatePartyDropdown() {
            const invoiceType = document.getElementById('invoice_type').value;
            const sellerSelect = document.getElementById('seller_id');
            const purchaserSelect = document.getElementById('purchaser_id');
            const partyLabel = document.getElementById('partyLabel');
            
            if (invoiceType === 'purchase') {
                sellerSelect.style.display = 'block';
                purchaserSelect.style.display = 'none';
                sellerSelect.name = 'seller_id';
                purchaserSelect.name = '';
                partyLabel.textContent = 'Select Seller *';
            } else {
                sellerSelect.style.display = 'none';
                purchaserSelect.style.display = 'block';
                sellerSelect.name = '';
                purchaserSelect.name = 'purchaser_id';
                partyLabel.textContent = 'Select Purchaser *';
            }
        }

        function addItem() {
            const tbody = document.getElementById('itemsBody');
            const row = document.createElement('tr');
            row.className = 'item-row';
            row.innerHTML = `
                <td>
                    <select name="items[${itemCount}][chicken_type_id]" class="chicken-select" onchange="updateRate(this)" required>
                        <option value="">-- Select --</option>
                        <?php foreach ($chicken_types as $ct): ?>
                            <option value="<?php echo $ct['id']; ?>">
                                <?php echo htmlspecialchars($ct['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td><input type="number" name="items[${itemCount}][quantity]" step="0.01" min="0" onchange="calculateAmount(this)" required></td>
                <td><input type="number" name="items[${itemCount}][rate]" step="0.01" min="0" onchange="calculateAmount(this)" required></td>
                <td><span class="item-amount">0.00</span></td>
                <td><button type="button" class="remove-item" onclick="removeItem(this)">Remove</button></td>
            `;
            tbody.appendChild(row);
            itemCount++;
        }

        function removeItem(btn) {
            btn.closest('tr').remove();
            calculateTotal();
        }

        function updateRate(select) {
            const typeId = select.value;
            const rateInput = select.closest('tr').querySelector('input[name*="rate"]');
            if (typeId && ratesData[typeId]) {
                rateInput.value = ratesData[typeId];
                calculateAmount(rateInput);
            }
        }

        function calculateAmount(input) {
            const row = input.closest('tr');
            const quantity = parseFloat(row.querySelector('input[name*="quantity"]').value) || 0;
            const rate = parseFloat(row.querySelector('input[name*="rate"]').value) || 0;
            const amount = quantity * rate;
            row.querySelector('.item-amount').textContent = amount.toFixed(2);
            calculateTotal();
        }

        function calculateTotal() {
            let total = 0;
            document.querySelectorAll('.item-amount').forEach(cell => {
                total += parseFloat(cell.textContent) || 0;
            });
            document.getElementById('invoiceTotal').textContent = total.toFixed(2);
        }
    </script>
</body>
</html>