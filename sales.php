<?php
// sales.php - Sales History & Itemized Receipt Viewer for SENSIFLEX
session_start();
require_once 'db.php';

// Access Control: Ensure only authenticated users can access sales history
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?error=l_invalid_credentials");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

try {
    // Admins view all sales across the branch; staff view only their assigned POS transactions
    if ($role === 'admin') {
        $stmt = $pdo->query("
            SELECT s.*, u.username as cashier_name 
            FROM sales s 
            JOIN users u ON s.user_id = u.id 
            ORDER BY s.created_at DESC
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT s.*, u.username as cashier_name 
            FROM sales s 
            JOIN users u ON s.user_id = u.id 
            WHERE s.user_id = :user_id 
            ORDER BY s.created_at DESC
        ");
        $stmt->execute([':user_id' => $user_id]);
    }
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $sales = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SENSIFLEX - Sales History & Receipts</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen max-w-full overflow-x-hidden flex antialiased bg-slate-100 text-slate-800">

    <button id="hamburger-btn" onclick="toggleSidebar()" class="lg:hidden fixed top-4 left-4 z-50 bg-[#f97316] text-white p-3 rounded-2xl shadow-lg hover:bg-orange-600 transition flex items-center justify-center focus:outline-none">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6h16M4 12h16M4 18h16"/></svg>
    </button>
    <div id="sidebar-overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-40 hidden lg:hidden transition-opacity"></div>

    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content Area -->
    <main class="flex-1 lg:ml-72 p-4 sm:p-6 lg:p-8 overflow-y-auto overflow-x-hidden bg-slate-100 text-slate-800 page-shell box-border">
        <header class="flex flex-wrap sm:flex-nowrap justify-between items-start sm:items-center gap-3 mb-6 sm:mb-8">
            <div class="min-w-0 max-w-full">
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900">Sales History &amp; Itemized Receipts</h1>
                <p class="text-sm text-slate-600">Review past transactions, verify cashier attribution, and print warranty receipts.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 sm:gap-3 pl-12 lg:pl-0 max-w-full">
                <a href="<?php echo ($_SESSION['role'] === 'admin') ? 'dashboard.php' : 'staff_dashboard.php'; ?>" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold border border-slate-200 transition shadow-xs">
                    <?php echo ($_SESSION['role'] === 'admin') ? '← Back to Dashboard' : '← Back to Staff Dashboard'; ?>
                </a>
                <div class="bg-white text-slate-700 px-4 py-2 rounded-lg border border-slate-200 text-sm shadow-sm">
                    Role: <span class="text-[#f97316] font-semibold uppercase"><?php echo htmlspecialchars($role); ?></span>
                </div>
            </div>
        </header>

        <!-- Sales History Table -->
        <div class="bg-white border border-slate-200/80 p-6 rounded-2xl shadow-sm">
            <h2 class="text-lg font-semibold mb-4 text-slate-900">Completed Transactions</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-200 text-xs uppercase text-slate-600 bg-slate-50/70">
                            <th class="py-3 px-4">Sale ID</th>
                            <th class="py-3 px-4">Cashier</th>
                            <th class="py-3 px-4">Subtotal (UGX)</th>
                            <th class="py-3 px-4">Discount</th>
                            <th class="py-3 px-4">Total Amount</th>
                            <th class="py-3 px-4">Date / Time</th>
                            <th class="py-3 px-4">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm text-slate-700">
                        <?php if (empty($sales)): ?>
                            <tr>
                                <td colspan="7" class="py-6 text-center text-slate-500">No sales transactions recorded yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($sales as $sale): ?>
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="py-3 px-4 font-mono text-[#056847]">#<?php echo $sale['id']; ?></td>
                                    <td class="py-3 px-4"><?php echo htmlspecialchars($sale['cashier_name']); ?></td>
                                    <td class="py-3 px-4"><?php echo number_format($sale['subtotal']); ?></td>
                                    <td class="py-3 px-4 text-[#056847] font-semibold">-<?php echo number_format($sale['discount']); ?></td>
                                    <td class="py-3 px-4 font-bold text-slate-900">UGX <?php echo number_format($sale['total_amount']); ?></td>
                                    <td class="py-3 px-4 text-xs text-slate-500"><?php echo $sale['created_at']; ?></td>
                                    <td class="py-3 px-4">
                                        <button onclick="window.print()" class="bg-[#f97316] hover:bg-orange-600 text-white text-xs px-3 py-1.5 rounded transition shadow">Print Receipt</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script src="script.js"></script>
</body>
</html>