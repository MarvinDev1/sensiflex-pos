<?php
// staff_dashboard.php - Restricted Staff & Cashier POS Portal
session_start();
require_once 'db.php';

// Access Control: Ensure only logged-in staff can access this view
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header("Location: index.php?error=l_invalid_credentials");
    exit();
}

// Populate catalog for the unified barcode/manual-search dropdown on the staff POS screen
try {
    $stmt = $pdo->query("SELECT * FROM products WHERE stock_qty > 0 ORDER BY product_name ASC");
    $pos_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pos_products = [];
}

// Fetch Returned Products Summary & Recent Logs for Staff Tracking
$returns_stats = [
    'total'      => 0,
    'resellable' => 0,
    'defective'  => 0
];
$recent_returns = [];

try {
    $statStmt = $pdo->query("
        SELECT 
            COUNT(*) AS total,
            SUM(CASE WHEN condition_status = 'resellable' THEN 1 ELSE 0 END) AS resellable,
            SUM(CASE WHEN condition_status = 'defective' THEN 1 ELSE 0 END) AS defective
        FROM returns
    ");
    $fetched_stats = $statStmt->fetch(PDO::FETCH_ASSOC);
    if ($fetched_stats) {
        $returns_stats['total']      = (int)($fetched_stats['total'] ?? 0);
        $returns_stats['resellable'] = (int)($fetched_stats['resellable'] ?? 0);
        $returns_stats['defective']  = (int)($fetched_stats['defective'] ?? 0);
    }

    $recent_returns = $pdo->query("
        SELECT r.*, p.product_name, u.username AS staff_name
        FROM returns r
        JOIN products p ON r.product_id = p.id
        JOIN users u ON r.processed_by = u.id
        ORDER BY r.created_at DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Graceful fallback if returns table is not yet initialized
    $recent_returns = [];
}

// --- BACKEND AJAX / FORM ACTION HANDLERS ---
$response_message = '';
$receipt_data = null;

// 1. AJAX Request Handler: Fetch Product and Available Serials by Barcode/SKU
if (isset($_GET['action']) && $_GET['action'] === 'lookup_product') {
    header('Content-Type: application/json');
    $sku = trim($_GET['sku'] ?? '');
    
    if (empty($sku)) {
        echo json_encode(['success' => false, 'message' => 'SKU or Barcode cannot be empty.']);
        exit();
    }

    try {
        // Query products table
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :sku OR product_name LIKE :sku_name");
        $stmt->execute([':sku' => $sku, ':sku_name' => "%$sku%"]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            echo json_encode(['success' => false, 'message' => 'Product not found in inventory.']);
            exit();
        }

        // Verify stock quantity before allowing addition
        if ($product['stock_qty'] <= 0) {
            echo json_encode(['success' => false, 'message' => 'Error: Product is currently out of stock (stock_qty = 0).']);
            exit();
        }

        // Fetch unassigned serial numbers from product_serials where status = 'in_stock'
        $serialStmt = $pdo->prepare("SELECT id, serial_number FROM product_serials WHERE product_id = :product_id AND status = 'in_stock'");
        $serialStmt->execute([':product_id' => $product['id']]);
        $serials = $serialStmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'product' => $product,
            'serials' => $serials
        ]);
        exit();
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error during lookup.']);
        exit();
    }
}

// 4. POS Checkout Transaction Handler (PDO Transactions & Serial Binding)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout_action'])) {
    $cart_items = json_decode($_POST['cart_items_json'] ?? '[]', true);
    $cashier_id = $_SESSION['user_id'];
    $payment_method = trim($_POST['payment_method'] ?? 'cash');
    if ($payment_method === '') {
        $payment_method = 'cash';
    }

    if (empty($cart_items)) {
        $response_message = "Error: Active transaction cart is empty.";
    } else {
        try {
            $invoice_number = 'INV-' . date('Ymd') . '-' . strtoupper(substr(uniqid('', true), -8));
            if (empty($invoice_number) || $invoice_number === 'INV-') {
                throw new Exception('Invoice number generation failed.');
            }

            // Begin database transaction to ensure atomicity across sales, product stock, serials, and line items
            $pdo->beginTransaction();

            $subtotal = 0;
            foreach ($cart_items as $item) {
                $subtotal += floatval($item['price']) * intval($item['qty']);
            }

            $discount = 0;
            if ($subtotal > 100000) {
                $discount = $subtotal * 0.05;
            }
            $total_amount = $subtotal - $discount;

            $salesStmt = $pdo->prepare(
                "INSERT INTO sales (invoice_number, user_id, subtotal, discount, total_amount, payment_method, created_at)
                 VALUES (:invoice_number, :user_id, :subtotal, :discount, :total_amount, :payment_method, NOW())"
            );
            $salesStmt->execute([
                ':invoice_number' => $invoice_number,
                ':user_id'       => $cashier_id,
                ':subtotal'      => $subtotal,
                ':discount'      => $discount,
                ':total_amount'  => $total_amount,
                ':payment_method'=> $payment_method
            ]);
            $sale_id = $pdo->lastInsertId();

            try {
                $pdo->query("SELECT 1 FROM sales_items LIMIT 1");
                $salesItemInsertStmt = $pdo->prepare(
                    "INSERT INTO sales_items (sale_id, product_id, qty, unit_price, subtotal, created_at)
                     VALUES (:sale_id, :product_id, :qty, :unit_price, :subtotal, NOW())"
                );
            } catch (PDOException $e) {
                $salesItemInsertStmt = null;
            }

            foreach ($cart_items as $item) {
                $product_id = $item['product_id'];
                $serial_id  = $item['serial_id'] ?? null;
                $qty        = intval($item['qty']);
                $unit_price = floatval($item['price']);
                $line_total = $unit_price * $qty;

                if ($salesItemInsertStmt) {
                    $salesItemInsertStmt->execute([
                        ':sale_id'     => $sale_id,
                        ':product_id'  => $product_id,
                        ':qty'         => $qty,
                        ':unit_price'  => $unit_price,
                        ':subtotal'    => $line_total
                    ]);
                }

                $updProd = $pdo->prepare("UPDATE products SET stock_qty = stock_qty - :qty WHERE id = :product_id AND stock_qty >= :qty");
                $updProd->execute([':qty' => $qty, ':product_id' => $product_id]);

                if ($updProd->rowCount() === 0) {
                    throw new Exception("Insufficient stock or invalid product ID during checkout.");
                }

                if (!empty($serial_id)) {
                    $updSerial = $pdo->prepare("UPDATE product_serials SET status = 'sold' WHERE id = :serial_id AND status = 'in_stock'");
                    $updSerial->execute([':serial_id' => $serial_id]);
                }
            }

            $pdo->commit();

            $receipt_data = [
                'sale_id'        => $sale_id,
                'invoice_number' => $invoice_number,
                'cashier'        => $_SESSION['username'],
                'date'           => date('Y-m-d H:i:s'),
                'items'          => $cart_items,
                'subtotal'       => $subtotal,
                'discount'       => $discount,
                'total_amount'   => $total_amount,
                'warranty'       => '12 Months Standard Hardware Warranty (CCTV & Security Systems)'
            ];

            $response_message = "Success: Transaction completed and inventory updated successfully!";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $response_message = "Transaction Failed: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SENSIFLEX - Staff POS Portal</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Include QuaggaJS library for camera barcode scanning support -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/quagga/0.12.1/quagga.min.js"></script>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen max-w-full overflow-x-hidden font-sans flex antialiased">

    <!-- Hamburger Toggle Button for Mobile Navigation -->
    <button id="hamburger-btn" onclick="toggleSidebar()" class="lg:hidden fixed top-4 left-4 z-50 bg-[#f97316] text-white p-3 rounded-2xl shadow-lg hover:bg-orange-600 transition flex items-center justify-center focus:outline-none">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
    </button>
    <div id="sidebar-overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-40 hidden lg:hidden transition-opacity"></div>

    <?php include 'includes/sidebar.php'; ?>
    <script>
        window.staffProducts = <?php echo json_encode($pos_products); ?>;
    </script>

    <div class="flex-1 lg:ml-72 flex flex-col min-h-screen page-shell">
        <header class="border-b border-slate-200 bg-white/90 backdrop-blur-md sticky top-0 z-30 px-4 sm:px-6 py-4 flex flex-wrap sm:flex-nowrap justify-between items-center gap-3 shadow-sm">
            <div class="pl-12 sm:pl-0 min-w-0">
                <span class="text-xs font-bold uppercase tracking-wider text-[#056847]">POS Terminal</span>
                <h2 class="text-sm sm:text-base font-extrabold text-slate-900">Staff Point of Sale</h2>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-2 sm:gap-3 ml-auto max-w-full">
                <a href="returns.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-50 hover:bg-amber-100 text-amber-800 text-xs font-bold border border-amber-200 transition shadow-xs">
                    ↺ Process Return / RMA
                </a>
                <span class="bg-emerald-50 text-[#056847] border border-emerald-200 text-xs px-3 py-1.5 rounded-full font-bold shadow-xs hidden sm:inline-block">
                    ● Store Online
                </span>
            </div>
        </header>

        <main class="p-4 sm:p-6 lg:p-8 space-y-6 flex-1 max-w-7xl w-full mx-auto box-border">

        <!-- Excel Import & Export Action Bar -->
        <div class="flex flex-col sm:flex-row flex-wrap gap-4 mb-8 bg-white border border-slate-200/80 p-4 sm:p-5 rounded-2xl shadow-sm items-stretch sm:items-center justify-between box-border">
            <div class="text-sm font-bold text-slate-900">Inventory Data Tools:</div>
            <div class="flex flex-col sm:flex-row flex-wrap gap-3 sm:gap-4 items-stretch sm:items-center w-full sm:w-auto min-w-0">
                <a href="export_excel.php" class="bg-[#056847] hover:bg-[#045238] text-white text-sm font-bold px-4 py-2 rounded-xl transition shadow-sm">
                    Export to Excel (.csv)
                </a>
                <form action="import_excel.php" method="POST" enctype="multipart/form-data" class="flex gap-2 items-center">
                    <input type="file" name="excel_file" accept=".csv, .xls, .xlsx" class="text-xs text-slate-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200" required>
                    <button type="submit" class="bg-[#f97316] hover:bg-orange-600 text-white text-sm font-bold px-4 py-2 rounded-xl transition shadow-sm">
                        Import Excel
                    </button>
                </form>
            </div>
        </div>

        <!-- Notification Banner -->
        <?php if (!empty($response_message)): ?>
            <div class="mb-6 p-4 rounded-xl border <?php echo strpos($response_message, 'Success') !== false ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-red-50 border-red-200 text-red-700'; ?> text-sm font-semibold">
                <?php echo htmlspecialchars($response_message); ?>
            </div>
        <?php endif; ?>

        <!-- Quick Bento Grid Overview for Staff (3 Cards) -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 sm:gap-6 mb-8">
            <div class="bento-card bg-white border border-slate-200/80 p-4 sm:p-6 rounded-2xl shadow-sm hover:shadow-md transition">
                <h3 class="text-xs font-extrabold uppercase tracking-wider text-[#056847]">Terminal Status</h3>
                <p class="text-xl sm:text-2xl font-extrabold text-slate-900 mt-3">Active &amp; Ready</p>
            </div>
            <div class="bento-card bg-white border border-slate-200/80 p-4 sm:p-6 rounded-2xl shadow-sm hover:shadow-md transition">
                <h3 class="text-xs font-extrabold uppercase tracking-wider text-[#f97316]">Assigned Branch</h3>
                <p class="text-xl sm:text-2xl font-extrabold text-slate-900 mt-3 break-words">Mbarara Shop L4.072</p>
            </div>
            <div class="bento-card bg-white border border-slate-200/80 p-4 sm:p-6 rounded-2xl shadow-sm hover:shadow-md transition">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-xs font-extrabold uppercase tracking-wider text-amber-600">Returned Units</h3>
                    <a href="returns.php" class="text-[11px] font-bold text-[#056847] hover:underline">Manage →</a>
                </div>
                <p class="text-xl sm:text-2xl font-extrabold text-slate-900 mt-3 break-words"><?php echo $returns_stats['total']; ?> Logged</p>
                <p class="text-xs text-slate-500 font-medium mt-1">
                    <span class="text-emerald-700 font-bold"><?php echo $returns_stats['resellable']; ?> Restocked</span> ·
                    <span class="text-red-600 font-bold"><?php echo $returns_stats['defective']; ?> Defective</span>
                </p>
            </div>
        </div>

        <!-- POS Terminal Workspace & Bento Grid Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6">
            
            <!-- Left 2 Columns: Product Scanner & Cart Management -->
            <div class="lg:col-span-2 space-y-4 sm:space-y-6 min-w-0">
                
                <!-- 1. Barcode/QR Code & SKU Product Lookup & Live Camera Viewfinder -->
                <div class="bento-card bg-white border border-slate-200/80 p-4 sm:p-6 rounded-2xl shadow-sm hover:shadow-md transition space-y-4 box-border min-w-0">
                    <h2 class="text-base sm:text-lg font-extrabold text-slate-900">Hardware Barcode / SKU Scanner &amp; Camera Viewfinder</h2>

                    <div class="relative">
                        <input type="text" id="barcode-input" class="w-full min-w-0 box-border pl-10 pr-4 py-3 border-2 border-[#f97316] rounded-xl text-sm sm:text-base font-bold text-slate-900 focus:outline-none focus:ring-4 focus:ring-orange-100 shadow-sm" placeholder="Scan Barcode or enter Product Name/SKU..." autofocus>
                        <span class="absolute left-3.5 top-3.5 text-slate-400 text-sm">🔍</span>
                    </div>
                    <div id="barcode-search-results" class="hidden mt-2 space-y-2"></div>

                    <!-- Live Camera Viewfinder Container -->
                    <div class="space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-xs text-slate-500 font-medium">Or use phone/laptop camera scanner:</span>
                            <button type="button" onclick="toggleCameraScanner()" id="cam-toggle-btn" class="bg-slate-100 hover:bg-slate-200 text-xs text-slate-700 font-bold px-3 py-1.5 rounded-lg transition border border-slate-200 shadow-sm">Start Live Camera Scanner</button>
                        </div>
                        <div id="interactive" class="viewport relative w-full h-48 bg-slate-100 border border-slate-200 rounded-xl overflow-hidden hidden flex items-center justify-center shadow-inner">
                            <div class="text-xs text-slate-500">Initializing Camera Stream...</div>
                        </div>
                    </div>

                    <div id="lookup-feedback" class="text-sm text-slate-600 font-medium"></div>
                </div>

                <!-- Active Transaction Cart Table -->
                <div class="bento-card bg-white border border-slate-200/80 p-6 rounded-2xl shadow-sm hover:shadow-md transition">
                    <h2 class="text-lg font-extrabold text-slate-900 mb-4">Active Transaction Cart</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-200 text-xs uppercase text-slate-600 bg-slate-50/70">
                                    <th class="py-3 px-4">Item Description</th>
                                    <th class="py-3 px-4">Serial Number</th>
                                    <th class="py-3 px-4">Unit Price (UGX)</th>
                                    <th class="py-3 px-4">Qty</th>
                                    <th class="py-3 px-4">Total</th>
                                    <th class="py-3 px-4">Action</th>
                                </tr>
                            </thead>
                            <tbody id="cart-table-body" class="divide-y divide-slate-200 text-sm text-slate-700">
                                <tr>
                                    <td colspan="6" class="py-6 text-center text-slate-500">Cart is empty. Scan items to begin checkout.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

            <!-- Right Column: Summary & Checkout Form -->
            <div class="space-y-6">
                <div class="bento-card bg-white border border-slate-200/80 p-6 rounded-2xl shadow-sm hover:shadow-md transition">
                    <h2 class="text-lg font-extrabold text-slate-900 mb-4">Payment & Summary</h2>
                    <div class="space-y-3 text-sm text-slate-600 mb-6">
                        <div class="flex justify-between">
                            <span>Subtotal:</span>
                            <span id="cart-subtotal" class="font-bold text-slate-900">UGX 0.00</span>
                        </div>
                        <div class="flex justify-between text-[#056847] font-bold">
                            <span>Bulk Discount (5% > 100k):</span>
                            <span id="cart-discount">UGX 0.00</span>
                        </div>
                        <div class="flex justify-between text-lg font-extrabold border-t border-slate-200 pt-3 text-slate-900">
                            <span>Total Payable:</span>
                            <span id="cart-total">UGX 0.00</span>
                        </div>
                    </div>

                    <form action="staff_dashboard.php" method="POST" id="checkout-form">
                        <input type="hidden" name="checkout_action" value="1">
                        <input type="hidden" name="cart_items_json" id="cart-items-json" value="[]">
                        <div class="mb-4">
                            <label for="payment_method" class="block text-xs font-bold uppercase tracking-wide text-slate-600 mb-2">Payment Method</label>
                            <select name="payment_method" id="payment_method" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm text-slate-800 focus:outline-none focus:border-[#056847]">
                                <option value="cash">Cash</option>
                                <option value="card">Card</option>
                                <option value="mobile_money">Mobile Money</option>
                            </select>
                        </div>
                        <button type="submit" onclick="prepareCheckout()" class="w-full bg-[#f97316] hover:bg-orange-600 text-white font-bold py-3 px-4 rounded-xl transition shadow-lg shadow-orange-600/20">
                            Complete Checkout & Print
                        </button>
                        <button type="button" onclick="clearQueue()" class="w-full mt-3 bg-slate-900 hover:bg-slate-700 text-white font-bold py-3 px-4 rounded-xl transition shadow-lg shadow-slate-700/20">
                            Clear Queue / New Sale
                        </button>
                    </form>
                </div>

                <!-- Professional Itemized Receipt Data Display -->
                <?php if ($receipt_data): ?>
                    <div class="receipt-container">
                        <div id="thermal-receipt" class="thermal-receipt">
                            <div class="thermal-header">=== SENSIFLEX OFFICIAL RECEIPT ===</div>
                            <div class="thermal-meta">Receipt ID: #<?php echo $receipt_data['sale_id']; ?></div>
                            <div class="thermal-meta">Invoice: <?php echo htmlspecialchars($receipt_data['invoice_number'] ?? 'N/A'); ?></div>
                            <div class="thermal-meta">Cashier: <?php echo htmlspecialchars($receipt_data['cashier']); ?></div>
                            <div class="thermal-meta">Date: <?php echo $receipt_data['date']; ?></div>
                            <div class="thermal-rule"></div>
                            <?php foreach ($receipt_data['items'] as $ritem): ?>
                                <div class="thermal-row">
                                    <span class="thermal-item-name"><?php echo htmlspecialchars($ritem['name']); ?> (S/N: <?php echo htmlspecialchars($ritem['serial_number'] ?? 'N/A'); ?>)</span>
                                    <span class="thermal-item-total"><?php echo number_format($ritem['price'] * $ritem['qty']); ?></span>
                                </div>
                            <?php endforeach; ?>
                            <div class="thermal-rule"></div>
                            <div class="thermal-row"><span>Subtotal:</span><span><?php echo number_format($receipt_data['subtotal']); ?></span></div>
                            <div class="thermal-row thermal-discount"><span>Discount:</span><span>-<?php echo number_format($receipt_data['discount']); ?></span></div>
                            <div class="thermal-row thermal-total"><span>Total:</span><span>UGX <?php echo number_format($receipt_data['total_amount']); ?></span></div>
                            <div class="thermal-rule"></div>
                            <div class="thermal-footer"><?php echo htmlspecialchars($receipt_data['warranty']); ?></div>
                        </div>
                        <div class="receipt-actions mt-4 flex flex-wrap gap-3">
                            <button type="button" onclick="printReceipt()" class="bg-[#056847] hover:bg-[#045238] text-white px-4 py-2.5 rounded-xl text-sm font-bold shadow-sm">🖨️ Print Receipt</button>
                            <button type="button" onclick="clearReceiptAndCart()" class="bg-slate-900 hover:bg-slate-700 text-white px-4 py-2.5 rounded-xl text-sm font-bold shadow-sm">➕ New Transaction / Clear</button>
                        </div>
                    </div>
                <?php endif; ?>

            </div>

        </div>

        <!-- Returned Products & RMA Audit Tracker -->
        <div class="bento-card bg-white border border-slate-200/80 p-6 rounded-2xl shadow-sm hover:shadow-md transition">
            <div class="flex flex-wrap justify-between items-center gap-4 mb-4">
                <div>
                    <h2 class="text-lg font-extrabold text-slate-900">Recent Returned Products (RMA Log)</h2>
                    <p class="text-xs text-slate-500 font-medium">Live tracking of verified customer hardware returns, restocks, and defective quarantines.</p>
                </div>
                <a href="returns.php" class="bg-[#056847] hover:bg-[#045238] text-white text-xs font-bold px-4 py-2 rounded-xl transition shadow-sm">
                    + Log / Verify New Return
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-200 text-xs font-bold uppercase tracking-wider text-slate-600 bg-slate-50/70">
                            <th class="py-3 px-4">Serial Number</th>
                            <th class="py-3 px-4">Product Name</th>
                            <th class="py-3 px-4">Condition / Stock Action</th>
                            <th class="py-3 px-4">Reason</th>
                            <th class="py-3 px-4">Processed By</th>
                            <th class="py-3 px-4 text-right">Date Returned</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs font-medium text-slate-700">
                        <?php if (empty($recent_returns)): ?>
                            <tr>
                                <td colspan="6" class="py-8 text-center text-slate-400 italic">
                                    No returned products logged yet. Click "+ Log / Verify New Return" when a customer brings back an item.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_returns as $ret): ?>
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="py-3 px-4 font-mono font-bold text-slate-900"><?php echo htmlspecialchars($ret['serial_number']); ?></td>
                                    <td class="py-3 px-4 font-bold text-slate-800"><?php echo htmlspecialchars($ret['product_name']); ?></td>
                                    <td class="py-3 px-4">
                                        <?php if ($ret['condition_status'] === 'resellable'): ?>
                                            <span class="px-2.5 py-0.5 rounded-full bg-emerald-50 text-[#056847] border border-emerald-200 font-bold">Resellable (Restocked)</span>
                                        <?php else: ?>
                                            <span class="px-2.5 py-0.5 rounded-full bg-red-50 text-red-700 border border-red-200 font-bold">Defective (Quarantined)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-4 text-slate-600 max-w-xs truncate"><?php echo htmlspecialchars($ret['reason'] ?? 'N/A'); ?></td>
                                    <td class="py-3 px-4 text-slate-600 font-semibold"><?php echo htmlspecialchars($ret['staff_name']); ?></td>
                                    <td class="py-3 px-4 text-right font-mono text-slate-500"><?php echo htmlspecialchars($ret['created_at']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        </main>
    </div>

    <!-- Restored Mobile Sidebar Toggle JavaScript Function -->
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('-translate-x-full');
            document.getElementById('sidebar-overlay').classList.toggle('hidden');
        }
    </script>
    <!-- Reference external JavaScript file -->
    <script src="script.js"></script>
</body>
</html>