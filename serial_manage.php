<?php
// serial_manage.php - Hardware Serial Number & Barcode Tracking
session_start();

// Security Check: Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Database Connection via Centralized db.php
require_once 'db.php';

$success_msg = '';
$error_msg   = '';

$product_id = intval($_GET['product_id'] ?? 0);

if ($product_id <= 0) {
    header("Location: inventory.php");
    exit();
}

// Fetch Product Details
try {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id");
    $stmt->execute([':id' => $product_id]);
    $product = $stmt->fetch();

    if (!$product) {
        header("Location: inventory.php");
        exit();
    }
} catch (PDOException $e) {
    header("Location: inventory.php");
    exit();
}

// Handle Adding New Serial / Barcode Unit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_serial'])) {
    $serial_number = trim($_POST['serial_number'] ?? '');

    if (empty($serial_number)) {
        $error_msg = "Please provide a valid serial number or barcode.";
    } else {
        try {
            // Check if serial already exists
            $chk = $pdo->prepare("SELECT id FROM product_serials WHERE serial_number = :serial");
            $chk->execute([':serial' => $serial_number]);
            if ($chk->fetch()) {
                $error_msg = "This serial number is already registered in the system.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO product_serials (product_id, serial_number, status) VALUES (:pid, :serial, 'in_stock')");
                $stmt->execute([
                    ':pid'    => $product_id,
                    ':serial' => $serial_number
                ]);
                $success_msg = "Hardware serial number successfully registered!";
            }
        } catch (PDOException $e) {
            $error_msg = "Database Error: " . $e->getMessage();
        }
    }
}

// Fetch existing serials for this product
try {
    $serials_stmt = $pdo->prepare("SELECT * FROM product_serials WHERE product_id = :pid ORDER BY id DESC");
    $serials_stmt->execute([':pid' => $product_id]);
    $serials = $serials_stmt->fetchAll();
} catch (PDOException $e) {
    $serials = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, viewport-fit=cover">
    <title>SENSIFLEX - Serial Batch Management</title>
    <link rel="stylesheet" href="style.css">
    <!-- Tailwind CSS CDN for high-end Bento Grid utility styling -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/quagga/0.12.1/quagga.min.js"></script>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen max-w-full overflow-x-hidden font-sans flex antialiased">

    <!-- Mobile Floating Amber/Orange Hamburger Button -->
    <button id="hamburger-btn" onclick="toggleSidebar()" class="lg:hidden fixed top-4 left-4 z-50 bg-[#f97316] text-white p-3 rounded-2xl shadow-lg hover:bg-orange-600 transition flex items-center justify-center focus:outline-none">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
    </button>

    <!-- Sidebar Overlay for Mobile -->
    <div id="sidebar-overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-40 hidden lg:hidden transition-opacity"></div>

    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="flex-1 lg:ml-72 flex flex-col min-h-screen">

        <!-- Top Header Bar -->
        <header class="border-b border-slate-200 bg-white/90 backdrop-blur-md sticky top-0 z-30 px-6 py-4 flex justify-between items-center shadow-sm">
            <div class="pl-12 lg:pl-0">
                <span class="text-xs font-bold uppercase tracking-wider text-[#056847]">Catalog Batch</span>
                <h2 class="text-base font-extrabold text-slate-900">Serial Unit Tracking</h2>
            </div>
            <div class="flex items-center gap-3 pl-12 lg:pl-0">
                <a href="inventory.php" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold border border-slate-200 transition shadow-xs">
                    ← Back to Inventory
                </a>
            </div>
        </header>

        <!-- Main Bento Grid Container -->
        <main class="p-4 sm:p-6 lg:p-8 space-y-6 flex-1 max-w-7xl w-full mx-auto">

            <!-- Page Header Banner -->
            <div class="bg-gradient-to-r from-[#056847]/10 via-white to-[#f97316]/10 border border-slate-200/80 rounded-2xl p-6 sm:p-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 shadow-sm">
                <div>
                    <span class="bg-[#056847] text-white text-[10px] font-extrabold uppercase px-2.5 py-1 rounded-md">
                        <?php echo htmlspecialchars($product['category']); ?>
                    </span>
                    <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight mt-2"><?php echo htmlspecialchars($product['product_name']); ?></h1>
                    <p class="text-slate-600 text-sm mt-1 font-medium">Scan or register individual hardware serial numbers and barcodes for this SKU.</p>
                </div>
                <div class="bg-white border border-slate-200 px-4 py-3 rounded-xl shadow-xs">
                    <span class="text-[10px] font-bold uppercase tracking-widest text-slate-400 block">Catalog Unit Price</span>
                    <span class="text-sm font-black text-[#056847]">Shs <?php echo number_format($product['price']); ?></span>
                </div>
            </div>

            <!-- Alert Notifications -->
            <?php if (!empty($success_msg)): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-xl text-sm font-semibold shadow-sm">
                    <?php echo htmlspecialchars($success_msg); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_msg)): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded-xl text-sm font-semibold shadow-sm">
                    <?php echo htmlspecialchars($error_msg); ?>
                </div>
            <?php endif; ?>

            <!-- Bento Grid Layout: Scan Form (Left) & Serial List Table (Right) -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- Bento Card 1: Scan/Add Serial Form -->
                <div class="bg-white border border-slate-200/80 rounded-2xl p-6 sm:p-8 box-border w-full max-w-full min-w-0 shadow-sm flex flex-col justify-between">
                    <div>
                        <h3 class="text-lg font-extrabold text-slate-900 mb-1">Scan / Ingest Serial</h3>
                        <p class="text-xs font-medium text-slate-500 mb-5">Use a barcode scanner or type individual unit serial numbers.</p>
                        
                        <form action="serial_manage.php?product_id=<?php echo $product_id; ?>" method="POST" class="w-full max-w-full min-w-0 box-border space-y-4">
                            <div class="w-full max-w-full min-w-0 box-border">
                                <label class="block w-full max-w-full box-border text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Serial Number / Barcode</label>
                                <div class="flex flex-col sm:flex-row gap-2">
                                    <input id="serial-number-input" type="text" name="serial_number" class="w-full min-w-0 box-border px-3.5 py-3 border-2 border-[#056847]/40 rounded-xl text-sm font-mono font-bold focus:outline-none focus:border-[#f97316] focus:ring-1 focus:ring-[#f97316]" placeholder="Scan barcode here..." autofocus required>
                                    <button type="button" id="serial-scanner-btn" onclick="toggleSerialScanner()" class="w-full sm:w-auto sm:flex-none min-h-12 box-border inline-flex items-center justify-center gap-2 bg-[#f97316] hover:bg-orange-600 active:bg-orange-700 text-white px-4 py-3 rounded-xl text-sm font-bold shadow-md transition whitespace-nowrap" aria-controls="serial-scanner-viewport" aria-expanded="false">
                                        <span aria-hidden="true">📷</span>
                                        <span>Scan Serial Code</span>
                                    </button>
                                </div>
                                <div id="serial-scanner-viewport" class="relative w-full min-h-[280px] sm:min-h-[340px] max-w-full mt-3 bg-slate-950 border-2 border-[#f97316]/70 rounded-xl overflow-hidden hidden items-center justify-center shadow-inner">
                                    <div id="serial-scanner-status" class="absolute z-20 left-3 right-3 bottom-3 rounded-lg bg-slate-900/80 px-3 py-2 text-center text-xs font-bold text-white">Initializing camera...</div>
                                </div>
                                <p id="serial-scanner-feedback" class="mt-2 text-xs font-medium text-slate-500 min-h-4"></p>
                            </div>

                            <button type="submit" name="add_serial" class="w-full max-w-full min-w-0 box-border bg-[#f97316] hover:bg-orange-600 text-white py-3 rounded-xl text-sm font-bold shadow-md transition">
                                + Register Serial Unit
                            </button>
                        </form>
                    </div>

                    <div class="mt-6 pt-4 border-t border-slate-100 text-xs font-medium text-slate-400 text-center">
                        SENSIFLEX Inventory Batch Control
                    </div>
                </div>

                <!-- Bento Card 2: Registered Serials Table -->
                <div class="lg:col-span-2 bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm flex flex-col justify-between">
                    <div>
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 mb-5">
                            <div>
                                <h3 class="text-lg font-extrabold text-slate-900">Registered Hardware Serials</h3>
                                <p class="text-xs font-medium text-slate-500">Individual serial-tracked units linked to this product.</p>
                            </div>
                            <span class="bg-emerald-50 text-[#056847] border border-emerald-200 text-xs px-3.5 py-1.5 rounded-full font-bold">
                                <?php echo count($serials); ?> Total Units
                            </span>
                        </div>

                        <!-- Scrollable Responsive Table -->
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="border-b border-slate-200 text-xs font-bold uppercase tracking-wider text-slate-600 bg-slate-50/70">
                                        <th class="py-3.5 px-3">ID</th>
                                        <th class="py-3.5 px-3">Serial Number / Barcode</th>
                                        <th class="py-3.5 px-3">Status</th>
                                        <th class="py-3.5 px-3 text-right">Date Added</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-sm font-medium">
                                    <?php if (empty($serials)): ?>
                                        <tr>
                                            <td colspan="4" class="py-8 text-center text-slate-400 italic font-normal">
                                                No serial numbers scanned yet for this product. Use the form on the left to add items.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($serials as $s): ?>
                                            <tr class="hover:bg-slate-50/80 transition">
                                                <td class="py-3.5 px-3 font-mono text-slate-500">#<?php echo $s['id']; ?></td>
                                                <td class="py-3.5 px-3 font-mono font-bold text-slate-900"><?php echo htmlspecialchars($s['serial_number']); ?></td>
                                                <td class="py-3.5 px-3">
                                                    <span class="px-2.5 py-1 rounded-md text-xs font-bold <?php echo $s['status'] === 'in_stock' ? 'bg-emerald-50 text-[#056847] border border-emerald-200' : 'bg-slate-100 text-slate-600'; ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $s['status'])); ?>
                                                    </span>
                                                </td>
                                                <td class="py-3.5 px-3 text-right text-xs text-slate-500 font-mono">
                                                    <?php echo $s['created_at'] ?? '2026'; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="mt-6 pt-4 border-t border-slate-100 flex flex-col sm:flex-row justify-between items-center text-xs font-semibold text-slate-400 gap-2">
                        <span>Database: product_serials Table</span>
                        <span>Serials are automatically marked sold during POS checkout.</span>
                    </div>
                </div>

            </div>

        </main>
    </div>

    <!-- Toggle Script for Mobile Sidebar -->
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            if (sidebar) sidebar.classList.toggle('-translate-x-full');
            if (overlay) overlay.classList.toggle('hidden');
        }
    </script>
    <script src="script.js"></script>
</body>
</html>