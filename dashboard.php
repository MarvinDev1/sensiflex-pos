<?php
// dashboard.php - Admin Control Center with Business Intelligence & Data Visualization
session_start();

// Security Check: Ensure user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Use centralized database connection file
require_once 'db.php';

// Safe Pre-Initialization of all variables to prevent undefined variable warnings
$productCount = 0;
$serialCount  = 0;
$userCount    = 0;
$recent_users = [];
$sales_trend_data = [];
$category_data = [];
$serial_status_data = [];
$recent_returns = [];
$returns_stats = [
    'total'      => 0,
    'resellable' => 0,
    'defective'  => 0
];
$db_error = null;

try {
    // Fetch quick metrics for the Bento Grid cards
    $productCount = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    $serialCount  = $pdo->query("SELECT COUNT(*) FROM product_serials WHERE status = 'in_stock'")->fetchColumn();
    $userCount    = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    
    // Fetch recent users list to display directly on the dashboard
    $recent_users = $pdo->query("SELECT id, username, email, phone, role, created_at FROM users ORDER BY id DESC LIMIT 5")->fetchAll();
    
    // --- RETURNS & RMA METRICS ---
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
        $recent_returns = [];
    }
    
    // --- BUSINESS INTELLIGENCE DATA QUERIES ---
    
    // 1. Sales Revenue Trend (Last 7 active days) for the Curve/Line Graph
    $sales_trend_stmt = $pdo->query("
        SELECT DATE(created_at) as sale_date, SUM(total_amount) as daily_total 
        FROM sales 
        GROUP BY DATE(created_at) 
        ORDER BY sale_date DESC 
        LIMIT 7
    ");
    $sales_trend_data = array_reverse($sales_trend_stmt->fetchAll());

    // 2. Product Categories Distribution for Pie Chart
    $category_stmt = $pdo->query("
        SELECT category, COUNT(*) as product_count, SUM(stock_qty) as total_stock 
        FROM products 
        GROUP BY category
    ");
    $category_data = $category_stmt->fetchAll();

    // 3. Hardware Serial Status Breakdown for Doughnut Chart
    $serial_status_stmt = $pdo->query("
        SELECT status, COUNT(*) as count 
        FROM product_serials 
        GROUP BY status
    ");
    $serial_status_data = $serial_status_stmt->fetchAll();

} catch (PDOException $e) {
    $db_error = "Database Query Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SENSIFLEX - Admin Control Center & BI</title>
    <link rel="stylesheet" href="style.css">
    <!-- Tailwind CSS CDN for high-end Bento Grid utility styling -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Chart.js CDN for Business Intelligence Curves, Graphs, and Pie Charts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen max-w-full overflow-x-hidden font-sans flex antialiased">

    <!-- Mobile Floating Hamburger Button -->
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
        <header class="border-b border-slate-200 bg-white/90 backdrop-blur-md sticky top-0 z-30 px-4 sm:px-6 py-4 flex flex-wrap sm:flex-nowrap justify-between items-center gap-3 shadow-sm">
            <div class="pl-12 sm:pl-0 min-w-0">
                <span class="text-xs font-bold uppercase tracking-wider text-[#056847]">Control Center</span>
                <h2 class="text-sm sm:text-base font-extrabold text-slate-900">Executive Overview &amp; BI Analytics</h2>
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

        <!-- Main Bento Grid Container -->
        <main class="p-4 sm:p-6 lg:p-8 space-y-6 flex-1 max-w-7xl w-full mx-auto box-border">

            <!-- Page Header Banner -->
            <div class="bg-gradient-to-r from-[#056847]/10 via-white to-[#f97316]/10 border border-slate-200/80 rounded-2xl p-4 sm:p-6 lg:p-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 shadow-sm">
                <div class="min-w-0">
                    <h1 class="text-xl sm:text-2xl lg:text-3xl font-black text-slate-900 tracking-tight">Business Intelligence &amp; Analytics</h1>
                    <p class="text-slate-600 text-sm mt-1 font-medium">Real-time data visualization of sales performance, category distribution, and inventory status.</p>
                </div>
                <div class="flex flex-col sm:flex-row flex-wrap gap-3 w-full md:w-auto">
                    <a href="inventory.php" class="justify-center bg-[#056847] hover:bg-[#045238] text-white px-4 sm:px-5 py-3 rounded-xl text-sm font-bold shadow-md transition flex items-center gap-2">
                        📦 Manage Inventory
                    </a>
                    <a href="pos.php" class="justify-center bg-[#f97316] hover:bg-orange-600 text-white px-4 sm:px-5 py-3 rounded-xl text-sm font-bold shadow-md transition flex items-center gap-2">
                        🛒 Open POS Terminal
                    </a>
                </div>
            </div>

            <?php if (isset($db_error)): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded-xl text-sm font-semibold">
                    <?php echo htmlspecialchars($db_error); ?>
                </div>
            <?php endif; ?>

            <!-- Bento Grid Section (Metrics) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">

                <!-- Card 1: Total Products -->
                <div class="bg-white border border-slate-200/80 rounded-2xl p-4 sm:p-6 flex flex-col justify-between hover:border-[#056847]/50 shadow-sm hover:shadow-md transition">
                    <div>
                        <span class="text-xs font-extrabold uppercase tracking-wider text-[#056847]">Catalog SKUs</span>
                        <h3 class="text-3xl sm:text-4xl font-extrabold mt-3 text-slate-900 break-words"><?php echo number_format($productCount); ?></h3>
                    </div>
                    <p class="text-xs text-slate-600 font-medium mt-4">Unique hardware items in stock catalog.</p>
                </div>

                <!-- Card 2: Serialized Stock -->
                <div class="bg-white border border-slate-200/80 rounded-2xl p-4 sm:p-6 flex flex-col justify-between hover:border-[#f97316]/50 shadow-sm hover:shadow-md transition">
                    <div>
                        <span class="text-xs font-extrabold uppercase tracking-wider text-[#f97316]">Hardware Units</span>
                        <h3 class="text-3xl sm:text-4xl font-extrabold mt-3 text-slate-900 break-words"><?php echo number_format($serialCount); ?></h3>
                    </div>
                    <p class="text-xs text-slate-600 font-medium mt-4">Active serial-tracked units ready for sale.</p>
                </div>

                <!-- Card 3: System Users -->
                <div class="bg-white border border-slate-200/80 rounded-2xl p-4 sm:p-6 flex flex-col justify-between hover:border-blue-500/50 shadow-sm hover:shadow-md transition">
                    <div>
                        <span class="text-xs font-extrabold uppercase tracking-wider text-blue-600">Access Control</span>
                        <h3 class="text-3xl sm:text-4xl font-extrabold mt-3 text-slate-900 break-words"><?php echo number_format($userCount); ?></h3>
                    </div>
                    <p class="text-xs text-slate-600 font-medium mt-4">Active administrators and store cashiers.</p>
                </div>

                <!-- Card 4: Returned Units Metric -->
                <div class="bg-white border border-slate-200/80 rounded-2xl p-4 sm:p-6 flex flex-col justify-between hover:border-amber-500/50 shadow-sm hover:shadow-md transition">
                    <div>
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="text-xs font-extrabold uppercase tracking-wider text-amber-600">Returned Units</span>
                            <a href="returns.php" class="text-[11px] font-bold text-[#056847] hover:underline">Manage →</a>
                        </div>
                        <h3 class="text-3xl sm:text-4xl font-extrabold mt-3 text-slate-900 break-words"><?php echo $returns_stats['total']; ?></h3>
                    </div>
                    <p class="text-xs text-slate-600 font-medium mt-4">
                        <span class="text-emerald-700 font-bold"><?php echo $returns_stats['resellable']; ?> Restocked</span> ·
                        <span class="text-red-600 font-bold"><?php echo $returns_stats['defective']; ?> Defective</span>
                    </p>
                </div>

            </div>

            <!-- ================= DATA VISUALIZATION & BI CHARTS SECTION ================= -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6">
                
                <!-- Chart 1: Revenue Trend Curve (Line Chart taking 2 columns) -->
                <div class="lg:col-span-2 bg-white border border-slate-200/80 rounded-2xl p-4 sm:p-6 shadow-sm flex flex-col justify-between min-w-0 max-w-full overflow-hidden">
                    <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
                        <div class="min-w-0">
                            <h3 class="text-xl sm:text-2xl font-extrabold text-slate-900">Sales Revenue Trend</h3>
                            <p class="text-sm text-slate-500 font-medium">Daily cumulative turnover curve (UGX)</p>
                        </div>
                        <span class="bg-emerald-50 text-[#056847] text-xs font-bold px-3 py-1 rounded-lg border border-emerald-200">Curve Analysis</span>
                    </div>
                    <div class="relative h-64 sm:h-72 w-full max-w-full min-w-0 overflow-hidden">
                        <canvas id="salesTrendChart" class="max-w-full"></canvas>
                    </div>
                </div>

                <!-- Chart 2: Serialized Stock Status (Doughnut / Pie Chart) -->
                <div class="bg-white border border-slate-200/80 rounded-2xl p-4 sm:p-6 shadow-sm flex flex-col justify-between">
                    <div class="mb-4">
                        <h3 class="text-xl sm:text-2xl font-extrabold text-slate-900">Hardware Serial Status</h3>
                        <p class="text-sm text-slate-500 font-medium">In-Stock vs Sold Unit Distribution</p>
                    </div>
                    <div class="relative h-56 sm:h-60 w-full max-w-full min-w-0 flex items-center justify-center overflow-hidden">
                        <canvas id="serialStatusChart" class="max-w-full"></canvas>
                    </div>
                </div>

            </div>

            <!-- Second Row of Charts: Product Categories Bar Graph -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
                
                <!-- Chart 3: Product Category Stock Volume (Bar Graph) -->
                <div class="bg-white border border-slate-200/80 rounded-2xl p-4 sm:p-6 shadow-sm min-w-0 max-w-full overflow-hidden">
                    <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
                        <div class="min-w-0">
                            <h3 class="text-xl sm:text-2xl font-extrabold text-slate-900">Inventory Volume by Category</h3>
                            <p class="text-sm text-slate-500 font-medium">Stock levels across security hardware categories</p>
                        </div>
                        <span class="bg-orange-50 text-[#f97316] text-xs font-bold px-3 py-1 rounded-lg border border-orange-200">Bar Graph</span>
                    </div>
                    <div class="relative h-56 sm:h-64 w-full max-w-full min-w-0 overflow-hidden">
                        <canvas id="categoryStockChart" class="max-w-full"></canvas>
                    </div>
                </div>

                <!-- Registered Staff & System Users Table -->
                <div class="bg-white border border-slate-200/80 rounded-2xl p-4 sm:p-6 shadow-sm flex flex-col justify-between">
                    <div>
                        <div class="flex justify-between items-center mb-4">
                            <div>
                                <h3 class="text-lg font-extrabold text-slate-900">Recent System Users</h3>
                                <p class="text-xs font-medium text-slate-500">Recently added admin and cashier accounts.</p>
                            </div>
                            <a href="users.php" class="text-xs font-bold text-[#f97316] hover:underline">
                                View All →
                            </a>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[520px] text-left border-collapse">
                                <thead>
                                    <tr class="border-b border-slate-200 text-xs font-bold uppercase tracking-wider text-slate-600 bg-slate-50/70">
                                        <th class="py-2.5 px-3">Name</th>
                                        <th class="py-2.5 px-3">Email</th>
                                        <th class="py-2.5 px-3">Role</th>
                                        <th class="py-2.5 px-3 text-right">Date</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-xs font-medium">
                                    <?php if (empty($recent_users)): ?>
                                        <tr>
                                            <td colspan="4" class="py-4 text-center text-slate-400 italic">No users found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recent_users as $u): ?>
                                            <tr class="hover:bg-slate-50 transition">
                                                <td class="py-2.5 px-3 font-bold text-slate-900"><?php echo htmlspecialchars($u['username']); ?></td>
                                                <td class="py-2.5 px-3 text-slate-600 font-mono"><?php echo htmlspecialchars($u['email']); ?></td>
                                                <td class="py-2.5 px-3">
                                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold <?php echo $u['role'] === 'admin' ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-slate-100 text-slate-700'; ?>">
                                                        <?php echo ucfirst($u['role']); ?>
                                                    </span>
                                                </td>
                                                <td class="py-2.5 px-3 text-right text-slate-500 font-mono"><?php echo $u['created_at'] ?? '2026'; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="mt-4 pt-3 border-t border-slate-100 flex justify-between items-center text-xs text-slate-500">
                        <span>PHP PDO Backend</span>
                        <span class="font-bold text-[#056847]">SENSIFLEX BI Engine</span>
                    </div>
                </div>

            </div>

            <!-- Returned Products & RMA Audit Tracker -->
            <div class="bg-white border border-slate-200/80 rounded-2xl p-4 sm:p-6 shadow-sm">
                <div class="flex flex-wrap justify-between items-center gap-4 mb-4">
                    <div>
                        <h3 class="text-lg font-extrabold text-slate-900">Recent Returned Products (RMA Log)</h3>
                        <p class="text-xs text-slate-500 font-medium">Live administrative tracking of verified customer hardware returns and quarantines.</p>
                    </div>
                    <a href="returns.php" class="bg-[#056847] hover:bg-[#045238] text-white text-xs font-bold px-4 py-2 rounded-xl transition shadow-sm">
                        + Log / Verify New Return
                    </a>
                </div>

                <div class="w-full max-w-full overflow-x-auto overscroll-contain">
                    <table class="w-full min-w-[720px] text-left border-collapse">
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
                                        No returned products logged yet.
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

    <!-- Toggle Script for Mobile Sidebar -->
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }

        // ================= BUSINESS INTELLIGENCE CHART INITIALIZATION =================
        
        // 1. Sales Trend Curve Chart (Line Chart with tension curve)
        const salesData = <?php echo json_encode($sales_trend_data); ?>;
        const salesLabels = salesData.length > 0 ? salesData.map(item => item.sale_date) : ['No Data'];
        const salesValues = salesData.length > 0 ? salesData.map(item => item.daily_total) : [0];

        const ctxSales = document.getElementById('salesTrendChart').getContext('2d');
        new Chart(ctxSales, {
            type: 'line',
            data: {
                labels: salesLabels,
                datasets: [{
                    label: 'Revenue (UGX)',
                    data: salesValues,
                    borderColor: '#056847',
                    backgroundColor: 'rgba(5, 104, 71, 0.1)',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#f97316',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(226, 232, 240, 0.6)' }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });

        // 2. Hardware Serial Status Doughnut Chart
        const serialData = <?php echo json_encode($serial_status_data); ?>;
        const serialLabels = serialData.length > 0 ? serialData.map(item => item.status.toUpperCase()) : ['In Stock', 'Sold'];
        const serialCounts = serialData.length > 0 ? serialData.map(item => item.count) : [1, 0];

        const ctxSerial = document.getElementById('serialStatusChart').getContext('2d');
        new Chart(ctxSerial, {
            type: 'doughnut',
            data: {
                labels: serialLabels,
                datasets: [{
                    data: serialCounts,
                    backgroundColor: ['#056847', '#f97316', '#3b82f6', '#cbd5e1'],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 12, font: { size: 11, weight: 'bold' } }
                    }
                }
            }
        });

        // 3. Product Categories Stock Volume Bar Graph
        const catData = <?php echo json_encode($category_data); ?>;
        const catLabels = catData.length > 0 ? catData.map(item => item.category) : ['CCTV', 'DVR', 'Access Control'];
        const catStock = catData.length > 0 ? catData.map(item => item.total_stock) : [0, 0, 0];

        const ctxCat = document.getElementById('categoryStockChart').getContext('2d');
        new Chart(ctxCat, {
            type: 'bar',
            data: {
                labels: catLabels,
                datasets: [{
                    label: 'Total Stock Qty',
                    data: catStock,
                    backgroundColor: '#f97316',
                    borderRadius: 8,
                    barThickness: 32
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(226, 232, 240, 0.6)' }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
    </script>
    <script src="script.js"></script>
</body>
</html>