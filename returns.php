<?php
// returns.php - SENSIFLEX Anti-Fraud Product Return & Restock Module
session_start();
require_once 'db.php';

// Access Control: Allow both logged-in Staff and Admins
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$success_msg = '';
$error_msg   = '';

// Process Return Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_return'])) {
    $serial_number    = trim($_POST['serial_number'] ?? '');
    $condition_status = $_POST['condition_status'] ?? 'resellable';
    $reason           = trim($_POST['reason'] ?? '');
    $processed_by     = $_SESSION['user_id'];

    if (empty($serial_number)) {
        $error_msg = "Please scan or enter the hardware serial number.";
    } else {
        try {
            $pdo->beginTransaction();

            // 1. ANTI-FRAUD EXISTENCE CHECK: Verify serial number exists in our database
            $stmt = $pdo->prepare("
                SELECT ps.*, p.product_name, p.price 
                FROM product_serials ps 
                JOIN products p ON ps.product_id = p.id 
                WHERE ps.serial_number = :serial 
                FOR UPDATE
            ");
            $stmt->execute([':serial' => $serial_number]);
            $serial_record = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$serial_record) {
                // Reject immediately: Serial was never registered by SENSIFLEX
                throw new Exception("SECURITY ALERT: Serial number '{$serial_number}' does NOT exist in the SENSIFLEX database. This item was not sold by this store.");
            }

            // 2. STATUS VERIFICATION: Ensure item was actually sold (prevent double-returns or unsold item fraud)
            if ($serial_record['status'] === 'in_stock') {
                throw new Exception("INVALID RETURN: Serial '{$serial_number}' ({$serial_record['product_name']}) is currently marked as IN STOCK and was never checked out.");
            }

            if ($serial_record['status'] === 'defective') {
                throw new Exception("DUPLICATE RETURN: Serial '{$serial_number}' has already been returned and quarantined as defective.");
            }

            // 3. EXECUTE RETURN & RESTOCKING BASED ON CONDITION
            if ($condition_status === 'resellable') {
                // Return unit to active sellable serial pool
                $updSerial = $pdo->prepare("UPDATE product_serials SET status = 'in_stock' WHERE id = :id");
                $updSerial->execute([':id' => $serial_record['id']]);

                // Increment available stock quantity in main catalog
                $updStock = $pdo->prepare("UPDATE products SET stock_qty = stock_qty + 1 WHERE id = :product_id");
                $updStock->execute([':product_id' => $serial_record['product_id']]);

                $action_note = "Restocked into active sellable inventory.";
            } else {
                // Quarantine item as defective so it cannot be accidentally sold again
                $updSerial = $pdo->prepare("UPDATE product_serials SET status = 'defective' WHERE id = :id");
                $updSerial->execute([':id' => $serial_record['id']]);

                $action_note = "Quarantined as DEFECTIVE (not added back to sellable stock).";
            }

            // 4. LOG AUDIT TRAIL IN RETURNS TABLE
            $logStmt = $pdo->prepare("
                INSERT INTO returns (serial_number, product_id, condition_status, reason, processed_by) 
                VALUES (:serial, :product_id, :condition, :reason, :user_id)
            ");
            $logStmt->execute([
                ':serial'     => $serial_number,
                ':product_id' => $serial_record['product_id'],
                ':condition'  => $condition_status,
                ':reason'     => $reason ?: 'No reason specified',
                ':user_id'    => $processed_by
            ]);

            $pdo->commit();
            $success_msg = "Return Verified & Processed: {$serial_record['product_name']} (S/N: {$serial_number}). {$action_note}";

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error_msg = $e->getMessage();
        }
    }
}

// Fetch Recent Returns Audit Log
try {
    $recent_returns = $pdo->query("
        SELECT r.*, p.product_name, u.username as staff_name 
        FROM returns r 
        JOIN products p ON r.product_id = p.id 
        JOIN users u ON r.processed_by = u.id 
        ORDER BY r.created_at DESC 
        LIMIT 20
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recent_returns = [];
}

// Dynamic Back Button Logic
$back_url   = ($_SESSION['role'] === 'admin') ? 'dashboard.php' : 'staff_dashboard.php';
$back_label = ($_SESSION['role'] === 'admin') ? '← Back to Dashboard' : '← Back to Staff Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SENSIFLEX - Returns & Anti-Fraud Verification</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen max-w-full overflow-x-hidden font-sans flex antialiased">

    <button id="hamburger-btn" onclick="toggleSidebar()" class="lg:hidden fixed top-4 left-4 z-50 bg-[#f97316] text-white p-3 rounded-2xl shadow-lg hover:bg-orange-600 transition flex items-center justify-center focus:outline-none">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6h16M4 12h16M4 18h16"/></svg>
    </button>
    <div id="sidebar-overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-40 hidden lg:hidden transition-opacity"></div>

    <?php include 'includes/sidebar.php'; ?>

    <div class="flex-1 lg:ml-72 flex flex-col min-h-screen page-shell">
        <header class="border-b border-slate-200 bg-white/95 backdrop-blur-md sticky top-0 z-30 px-6 py-4 flex justify-between items-center shadow-sm">
            <div class="pl-12 lg:pl-0">
                <span class="text-xs font-bold uppercase tracking-wider text-[#056847]">RMA & Verification</span>
                <h2 class="text-base font-extrabold text-slate-900">Customer Returns & Restocking</h2>
            </div>
            <div class="flex items-center gap-3 pl-12 lg:pl-0">
                <a href="<?php echo $back_url; ?>" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold border border-slate-200 transition shadow-xs">
                    <?php echo $back_label; ?>
                </a>
            </div>
        </header>

        <main class="p-4 sm:p-6 lg:p-8 space-y-6 flex-1 max-w-7xl w-full mx-auto">

            <!-- Banner -->
            <div class="bg-gradient-to-r from-[#056847]/10 via-white to-[#f97316]/10 border border-slate-200/80 rounded-2xl p-6 sm:p-8">
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Hardware Return & Serial Verification</h1>
                <p class="text-slate-600 text-sm mt-1 font-medium">Scan returned hardware serial numbers to authenticate SENSIFLEX origin and safely restock or quarantine units.</p>
            </div>

            <!-- Alerts -->
            <?php if (!empty($success_msg)): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-xl text-sm font-bold shadow-xs">
                    ✅ <?php echo htmlspecialchars($success_msg); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_msg)): ?>
                <div class="bg-red-50 border-2 border-red-300 text-red-800 p-4 rounded-xl text-sm font-bold shadow-xs">
                    🚨 <?php echo htmlspecialchars($error_msg); ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <!-- Left Column: Return Verification Form -->
                <div class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm">
                    <h3 class="text-lg font-extrabold text-slate-900 mb-1">Verify Returned Unit</h3>
                    <p class="text-xs font-medium text-slate-500 mb-5">Serial numbers are cross-checked against sold store records.</p>

                    <form action="returns.php" method="POST" class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Hardware Serial Number *</label>
                            <input type="text" name="serial_number" placeholder="Scan or type S/N..." class="w-full px-3.5 py-2.5 bg-slate-50 border-2 border-[#f97316] rounded-xl text-sm font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#f97316]/20" autofocus required>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Physical Inspection Status *</label>
                            <select name="condition_status" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-sm font-semibold text-slate-800 focus:outline-none focus:border-[#056847]" required>
                                <option value="resellable">Good / Resellable (Return to Active Stock)</option>
                                <option value="defective">Damaged / Defective (Quarantine Unit)</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Customer Return Reason</label>
                            <textarea name="reason" rows="3" placeholder="e.g., Customer ordered wrong camera lens, or unit failed power-on test..." class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-sm font-medium focus:outline-none focus:border-[#056847]"></textarea>
                        </div>

                        <button type="submit" name="process_return" class="w-full bg-[#056847] hover:bg-[#045238] text-white py-3 rounded-xl text-sm font-bold shadow-md transition">
                            Verify Serial & Process Return
                        </button>
                    </form>
                </div>

                <!-- Right 2 Columns: Return Audit History -->
                <div class="lg:col-span-2 bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm">
                    <div class="flex justify-between items-center mb-5">
                        <div>
                            <h3 class="text-lg font-extrabold text-slate-900">Verified Return Audit Log</h3>
                            <p class="text-xs text-slate-500">Permanent record of authenticated customer returns.</p>
                        </div>
                        <span class="bg-slate-100 text-slate-700 border border-slate-200 text-xs px-3 py-1 rounded-full font-bold">
                            <?php echo count($recent_returns); ?> Logged
                        </span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-200 text-xs font-bold uppercase tracking-wider text-slate-600 bg-slate-50/70">
                                    <th class="py-3 px-3">Serial Number</th>
                                    <th class="py-3 px-3">Product</th>
                                    <th class="py-3 px-3">Condition</th>
                                    <th class="py-3 px-3">Handled By</th>
                                    <th class="py-3 px-3 text-right">Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-xs font-medium">
                                <?php if (empty($recent_returns)): ?>
                                    <tr>
                                        <td colspan="5" class="py-10 text-center text-slate-400 italic">No customer returns logged yet.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recent_returns as $ret): ?>
                                        <tr class="hover:bg-slate-50 transition">
                                            <td class="py-3 px-3 font-mono font-bold text-slate-900"><?php echo htmlspecialchars($ret['serial_number']); ?></td>
                                            <td class="py-3 px-3 font-bold text-slate-800"><?php echo htmlspecialchars($ret['product_name']); ?></td>
                                            <td class="py-3 px-3">
                                                <?php if ($ret['condition_status'] === 'resellable'): ?>
                                                    <span class="px-2 py-0.5 rounded bg-emerald-50 text-[#056847] border border-emerald-200 font-bold">Resellable</span>
                                                <?php else: ?>
                                                    <span class="px-2 py-0.5 rounded bg-red-50 text-red-700 border border-red-200 font-bold">Defective</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-3 px-3 text-slate-600 font-semibold"><?php echo htmlspecialchars($ret['staff_name']); ?></td>
                                            <td class="py-3 px-3 text-right font-mono text-slate-500"><?php echo $ret['created_at']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('-translate-x-full');
            document.getElementById('sidebar-overlay').classList.toggle('hidden');
        }
    </script>
    <script src="script.js"></script>
</body>
</html>