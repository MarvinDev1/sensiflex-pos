<?php
// inventory.php - SENSIFLEX Inventory & Hardware Serial Management
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

require_once 'db.php';

$success_msg = '';
$error_msg   = '';

// Handle Deleting Product SKU
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    try {
        $del = $pdo->prepare("DELETE FROM products WHERE id = :id");
        $del->execute([':id' => $delete_id]);
        $success_msg = "Product SKU successfully removed from catalog.";
    } catch (PDOException $e) {
        $error_msg = "Error deleting product (It may have active serial records attached): " . $e->getMessage();
    }
}

// Handle Adding New Product SKU
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $product_name = trim($_POST['product_name'] ?? '');
    $category     = trim($_POST['category'] ?? '');
    $price        = trim($_POST['price'] ?? '');
    $stock_qty    = intval($_POST['stock_qty'] ?? 0);

    if (empty($product_name) || empty($category) || empty($price)) {
        $error_msg = "Please fill in all required product fields.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO products (product_name, category, price, stock_qty) VALUES (:name, :cat, :price, :qty)");
            $stmt->execute([':name' => $product_name, ':cat' => $category, ':price' => $price, ':qty' => $stock_qty]);
            $success_msg = "New product SKU successfully added to catalog!";
        } catch (PDOException $e) {
            $error_msg = "Database Error: " . $e->getMessage();
        }
    }
}

try {
    $products = $pdo->query("SELECT * FROM products ORDER BY id DESC")->fetchAll();
} catch (PDOException $e) {
    $products = [];
    $error_msg = "Could not fetch inventory items.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SENSIFLEX - Inventory & Serial Tracking</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen font-sans flex antialiased">

    <button id="hamburger-btn" onclick="toggleSidebar()" class="lg:hidden fixed top-4 left-4 z-50 bg-[#f97316] text-white p-3 rounded-2xl shadow-lg hover:bg-orange-600 transition flex items-center justify-center focus:outline-none">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6h16M4 12h16M4 18h16"/></svg>
    </button>
    <div id="sidebar-overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-40 hidden lg:hidden transition-opacity"></div>

    <?php include 'includes/sidebar.php'; ?>

    <div class="flex-1 lg:ml-72 flex flex-col min-h-screen page-shell">
        <header class="border-b border-slate-200 bg-white/95 backdrop-blur-md sticky top-0 z-30 px-6 py-4 flex justify-between items-center shadow-sm">
            <div class="pl-12 lg:pl-0"><span class="text-xs font-bold uppercase tracking-wider text-[#056847]">Catalog Module</span><h2 class="text-base font-extrabold text-slate-900">Inventory Management</h2></div>
            <div class="flex items-center gap-3 pl-12 lg:pl-0">
                <a href="<?php echo ($_SESSION['role'] === 'admin') ? 'dashboard.php' : 'staff_dashboard.php'; ?>" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold border border-slate-200 transition shadow-xs">
                    <?php echo ($_SESSION['role'] === 'admin') ? '← Back to Dashboard' : '← Back to Staff Dashboard'; ?>
                </a>
            </div>
        </header>

        <main class="p-4 sm:p-6 lg:p-8 space-y-6 flex-1 max-w-7xl w-full mx-auto">
            <div class="bg-gradient-to-r from-[#056847]/10 via-white to-[#f97316]/10 border border-slate-200/80 rounded-2xl p-6 sm:p-8">
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Stock & Serial Catalog</h1>
                <p class="text-slate-600 text-sm mt-1 font-medium">Manage security hardware units, pricing, categories, and batches.</p>
            </div>

            <?php if (!empty($success_msg)): ?><div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-xl text-sm font-semibold"><?php echo htmlspecialchars($success_msg); ?></div><?php endif; ?>
            <?php if (!empty($error_msg)): ?><div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded-xl text-sm font-semibold"><?php echo htmlspecialchars($error_msg); ?></div><?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Add SKU Form -->
                <div class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm flex flex-col justify-between">
                    <div>
                        <h3 class="text-lg font-extrabold text-slate-900 mb-1">Add New SKU</h3>
                        <p class="text-xs font-medium text-slate-500 mb-5">Register a new security item into store inventory.</p>
                        <form action="inventory.php" method="POST" class="space-y-4">
                            <div><label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Product Name / Model</label><input type="text" name="product_name" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-sm font-medium focus:outline-none focus:border-[#056847]" required></div>
                            <div><label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Category</label><select name="category" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-sm font-medium focus:outline-none focus:border-[#056847]" required><option value="">Select Category</option><option value="CCTV Cameras">CCTV Cameras</option><option value="DVR / NVR Systems">DVR / NVR Systems</option><option value="Access Control">Access Control</option><option value="Power & Cables">Power & Cables</option><option value="Smart Gadgets & LEDs">Smart Gadgets & LEDs</option></select></div>
                            <div><label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Unit Price (UGX)</label><input type="number" name="price" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-sm font-medium focus:outline-none focus:border-[#056847]" required></div>
                            <div><label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Initial Stock Quantity</label><input type="number" name="stock_qty" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-sm font-medium focus:outline-none focus:border-[#056847]" value="1" min="0" required></div>
                            <button type="submit" name="add_product" class="w-full bg-[#056847] hover:bg-[#045238] text-white py-3 rounded-xl text-sm font-bold shadow-md transition">+ Save Product to Catalog</button>
                        </form>
                    </div>
                </div>

                <!-- Products Table with Serial Batch & Delete Actions -->
                <div class="lg:col-span-2 bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm flex flex-col justify-between">
                    <div>
                        <div class="flex justify-between items-center mb-5">
                            <div><h3 class="text-lg font-extrabold text-slate-900">Current Stock Catalog</h3><p class="text-xs text-slate-500">Live overview of available security hardware and pricing.</p></div>
                            <span class="bg-emerald-50 text-[#056847] border border-emerald-200 text-xs px-3.5 py-1.5 rounded-full font-bold"><?php echo count($products); ?> Total SKUs</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="border-b border-slate-200 text-xs font-bold uppercase tracking-wider text-slate-600 bg-slate-50/70">
                                        <th class="py-3.5 px-3">ID</th><th class="py-3.5 px-3">Product Name</th><th class="py-3.5 px-3">Price (UGX)</th><th class="py-3.5 px-3">Stock</th><th class="py-3.5 px-3 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-sm font-medium">
                                    <?php foreach ($products as $p): ?>
                                    <tr class="hover:bg-slate-50 transition">
                                        <td class="py-3.5 px-3 font-mono text-slate-500">#<?php echo $p['id']; ?></td>
                                        <td class="py-3.5 px-3 font-bold text-slate-900"><?php echo htmlspecialchars($p['product_name']); ?></td>
                                        <td class="py-3.5 px-3 font-mono text-[#056847] font-bold">Shs <?php echo number_format($p['price']); ?></td>
                                        <td class="py-3.5 px-3 font-bold text-slate-800"><?php echo $p['stock_qty']; ?> units</td>
                                        <td class="py-3.5 px-3 text-right space-x-2">
                                            <a href="serial_manage.php?product_id=<?php echo $p['id']; ?>" class="text-[#f97316] hover:underline font-bold text-xs">Serials</a>
                                            <span class="text-slate-300">|</span>
                                            <a href="inventory.php?delete_id=<?php echo $p['id']; ?>" onclick="return confirm('Delete this product SKU?');" class="text-red-600 hover:underline font-bold text-xs">Delete</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script>function toggleSidebar() { document.getElementById('sidebar').classList.toggle('-translate-x-full'); document.getElementById('sidebar-overlay').classList.toggle('hidden'); }</script>
    <script src="script.js"></script>
</body>
</html>