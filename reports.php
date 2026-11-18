<?php
// reports.php - SENSIFLEX Sales History & Revenue Analytics
session_start();

// Security Check: Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Database Connection via Centralized db.php
require_once 'db.php';

// Calculate Revenue Metrics & Sales Analytics
try {
    // Total Revenue
    $total_revenue = $pdo->query("SELECT SUM(total_amount) FROM sales")->fetchColumn() ?: 0;
    
    // Total Completed Sales Count
    $sales_count = $pdo->query("SELECT COUNT(*) FROM sales")->fetchColumn() ?: 0;
    
    // Total Discounts Issued
    $total_discounts = $pdo->query("SELECT SUM(discount) FROM sales")->fetchColumn() ?: 0;

    // Average Sale Value
    $avg_sale = $sales_count > 0 ? ($total_revenue / $sales_count) : 0;

    // Fetch All Sales Transactions
    $sales_stmt = $pdo->query("
        SELECT s.*, u.username as cashier_name 
        FROM sales s 
        LEFT JOIN users u ON s.user_id = u.id 
        ORDER BY s.created_at DESC
    ");
    $sales = $sales_stmt->fetchAll();

} catch (PDOException $e) {
    $total_revenue = 0;
    $sales_count   = 0;
    $total_discounts = 0;
    $avg_sale      = 0;
    $sales         = [];
    $db_error      = "Error loading sales history: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, viewport-fit=cover">
    <title>SENSIFLEX - Sales History & Analytics</title>
    <link rel="stylesheet" href="style.css">
    <!-- Tailwind CSS CDN for high-end Bento Grid utility styling -->
    <script src="https://cdn.tailwindcss.com"></script>
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
    <div class="flex-1 lg:ml-72 flex flex-col min-h-screen page-shell">

        <!-- Top Header Bar -->
        <header class="border-b border-slate-200 bg-white/90 backdrop-blur-md sticky top-0 z-30 px-6 py-4 flex justify-between items-center shadow-sm">
            <div class="pl-12 lg:pl-0">
                <span class="text-xs font-bold uppercase tracking-wider text-[#056847]">Financial Records</span>
                <h2 class="text-base font-extrabold text-slate-900">Sales Reports & Audits</h2>
            </div>
            <div class="flex items-center gap-3 pl-12 lg:pl-0">
                <a href="<?php echo ($_SESSION['role'] === 'admin') ? 'dashboard.php' : 'staff_dashboard.php'; ?>" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold border border-slate-200 transition shadow-xs">
                    <?php echo ($_SESSION['role'] === 'admin') ? '← Back to Dashboard' : '← Back to Staff Dashboard'; ?>
                </a>
                <button onclick="window.print()" class="bg-[#f97316] hover:bg-orange-600 text-white px-4 py-2 rounded-xl text-xs font-bold shadow-md transition flex items-center gap-2">
                    🖨️ Print Financial Statement
                </button>
            </div>
        </header>

        <!-- Main Bento Grid Container -->
        <main class="p-4 sm:p-6 lg:p-8 space-y-6 flex-1 max-w-7xl w-full mx-auto">

            <!-- Page Header Banner -->
            <div class="bg-gradient-to-r from-[#056847]/10 via-white to-[#f97316]/10 border border-slate-200/80 rounded-2xl p-6 sm:p-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 shadow-sm">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Sales & Revenue Reports</h1>
                    <p class="text-slate-600 text-sm mt-1 font-medium">Audit transaction logs, cashier checkouts, and hardware sales performance.</p>
                </div>
            </div>

            <!-- Bento Grid Section (Revenue Analytics Cards) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">

                <!-- Card 1: Gross Revenue -->
                <div class="bg-white border border-slate-200/80 rounded-2xl p-6 flex flex-col justify-between hover:border-[#056847]/50 shadow-sm hover:shadow-md transition">
                    <div>
                        <span class="text-xs font-extrabold uppercase tracking-wider text-[#056847]">Total Sales Volume</span>
                        <h3 class="text-3xl font-extrabold mt-3 text-slate-900 font-mono">Shs <?php echo number_format($total_revenue); ?></h3>
                    </div>
                    <p class="text-xs text-slate-600 font-medium mt-4">Gross cash collected across transactions.</p>
                </div>

                <!-- Card 2: Total Receipts Processed -->
                <div class="bg-white border border-slate-200/80 rounded-2xl p-6 flex flex-col justify-between hover:border-[#f97316]/50 shadow-sm hover:shadow-md transition">
                    <div>
                        <span class="text-xs font-extrabold uppercase tracking-wider text-[#f97316]">Receipts Issued</span>
                        <h3 class="text-4xl font-extrabold mt-3 text-slate-900"><?php echo number_format($sales_count); ?></h3>
                    </div>
                    <p class="text-xs text-slate-600 font-medium mt-4">Completed POS customer orders.</p>
                </div>

                <!-- Card 3: Average Order Value -->
                <div class="bg-white border border-slate-200/80 rounded-2xl p-6 flex flex-col justify-between hover:border-blue-500/50 shadow-sm hover:shadow-md transition">
                    <div>
                        <span class="text-xs font-extrabold uppercase tracking-wider text-blue-600">Average Basket Size</span>
                        <h3 class="text-2xl font-extrabold mt-3 text-slate-900 font-mono">Shs <?php echo number_format($avg_sale); ?></h3>
                    </div>
                    <p class="text-xs text-slate-600 font-medium mt-4">Average revenue per checkout.</p>
                </div>

                <!-- Card 4: Total Discounts Granted -->
                <div class="bg-white border border-slate-200/80 rounded-2xl p-6 flex flex-col justify-between hover:border-amber-500/50 shadow-sm hover:shadow-md transition">
                    <div>
                        <span class="text-xs font-extrabold uppercase tracking-wider text-amber-600">Total Discounts</span>
                        <h3 class="text-2xl font-extrabold mt-3 text-slate-900 font-mono">Shs <?php echo number_format($total_discounts); ?></h3>
                    </div>
                    <p class="text-xs text-slate-600 font-medium mt-4">Price concessions granted at checkout.</p>
                </div>

            </div>

            <!-- Bento Card: Sales History Table -->
            <div class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm flex flex-col justify-between">
                <div>
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 mb-5">
                        <div>
                            <h3 class="text-lg font-extrabold text-slate-900">Completed Sales Transactions</h3>
                            <p class="text-xs font-medium text-slate-500">Historical register checkout audit log.</p>
                        </div>
                        <span class="bg-emerald-50 text-[#056847] border border-emerald-200 text-xs px-3.5 py-1.5 rounded-full font-bold">
                            <?php echo count($sales); ?> Records
                        </span>
                    </div>

                    <!-- Scrollable Responsive Table -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-200 text-xs font-bold uppercase tracking-wider text-slate-600 bg-slate-50/70">
                                    <th class="py-3.5 px-3">Invoice #</th>
                                    <th class="py-3.5 px-3">Subtotal</th>
                                    <th class="py-3.5 px-3">Discount</th>
                                    <th class="py-3.5 px-3">Final Total</th>
                                    <th class="py-3.5 px-3">Cashier</th>
                                    <th class="py-3.5 px-3 text-right">Date & Time</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-sm font-medium">
                                <?php if (empty($sales)): ?>
                                    <tr>
                                        <td colspan="6" class="py-12 text-center text-slate-400 italic font-normal">
                                            No sales receipts recorded yet. Process your first sale in the POS Terminal.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($sales as $sale): ?>
                                        <tr class="hover:bg-slate-50/80 transition">
                                            <td class="py-3.5 px-3 font-mono font-bold text-slate-900">#INV-<?php echo str_pad($sale['id'], 5, '0', STR_PAD_LEFT); ?></td>
                                            <td class="py-3.5 px-3 font-mono text-slate-600">Shs <?php echo number_format($sale['subtotal'] ?? $sale['total_amount']); ?></td>
                                            <td class="py-3.5 px-3 font-mono text-amber-600 font-bold">Shs <?php echo number_format($sale['discount'] ?? 0); ?></td>
                                            <td class="py-3.5 px-3 font-mono text-[#056847] font-extrabold">Shs <?php echo number_format($sale['total_amount']); ?></td>
                                            <td class="py-3.5 px-3">
                                                <span class="bg-slate-100 text-slate-800 px-2.5 py-1 rounded-md text-xs font-bold">
                                                    <?php echo htmlspecialchars($sale['cashier_name'] ?? 'System Admin'); ?>
                                                </span>
                                            </td>
                                            <td class="py-3.5 px-3 text-right text-xs text-slate-500 font-mono">
                                                <?php echo $sale['created_at'] ?? '2026'; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-slate-100 flex flex-col sm:flex-row justify-between items-center text-xs font-semibold text-slate-400 gap-2">
                    <span>Database: MySQL Sales Audit Log</span>
                    <span>All sales records are archived permanently for auditing.</span>
                </div>
            </div>

        </main>
    </div>

    <!-- Toggle Script for Mobile Sidebar -->
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }
    </script>
    <script src="script.js"></script>
</body>
</html>