<?php
// users.php - SENSIFLEX Staff & Access Control Management
session_start();

// Security Check: Ensure user is logged in and has admin rights
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Database Connection via Centralized db.php
require_once 'db.php';

$success_msg = '';
$error_msg   = '';

// Handle Deleting Staff Member
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    // Prevent admin from deleting themselves accidentally
    if ($delete_id === intval($_SESSION['user_id'])) {
        $error_msg = "You cannot delete your own active administrator account.";
    } else {
        try {
            $del = $pdo->prepare("DELETE FROM users WHERE id = :id");
            $del->execute([':id' => $delete_id]);
            $success_msg = "Staff account successfully deleted from system.";
        } catch (PDOException $e) {
            $error_msg = "Error deleting user: " . $e->getMessage();
        }
    }
}

// Handle Adding New Staff Member
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_staff'])) {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $role = 'staff';
    if (isset($_POST['role']) && $_POST['role'] !== 'staff') {
        $role = 'staff';
    }
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($email) || empty($password)) {
        $error_msg = "Please fill in all required staff fields.";
    } else {
        try {
            $chk = $pdo->prepare("SELECT id FROM users WHERE email = :email");
            $chk->execute([':email' => $email]);
            if ($chk->fetch()) {
                $error_msg = "An account with this email already exists.";
            } else {
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 17]);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, phone, password, role) VALUES (:username, :email, :phone, :password, :role)");
                $stmt->execute([
                    ':username' => $username,
                    ':email'    => $email,
                    ':phone'    => $phone,
                    ':password' => $hashedPassword,
                    ':role'     => $role
                ]);
                $success_msg = "New staff account successfully created!";
            }
        } catch (PDOException $e) {
            $error_msg = "Database Error: " . $e->getMessage();
        }
    }
}

// Fetch all system users
try {
    $users = $pdo->query("SELECT id, username, email, phone, role, created_at FROM users ORDER BY id DESC")->fetchAll();
} catch (PDOException $e) {
    $users = [];
    $error_msg = "Could not fetch user accounts.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SENSIFLEX - Staff & Access Control</title>
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
            <div class="pl-12 lg:pl-0"><span class="text-xs font-bold uppercase tracking-wider text-[#056847]">Security & Personnel</span><h2 class="text-base font-extrabold text-slate-900">Staff Access Control</h2></div>
            <div class="flex items-center gap-3 pl-12 lg:pl-0">
                <a href="<?php echo ($_SESSION['role'] === 'admin') ? 'dashboard.php' : 'staff_dashboard.php'; ?>" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold border border-slate-200 transition shadow-xs">
                    <?php echo ($_SESSION['role'] === 'admin') ? '← Back to Dashboard' : '← Back to Staff Dashboard'; ?>
                </a>
            </div>
        </header>

        <main class="p-4 sm:p-6 lg:p-8 space-y-6 flex-1 max-w-7xl w-full mx-auto">
            <div class="bg-gradient-to-r from-[#056847]/10 via-white to-[#f97316]/10 border border-slate-200/80 rounded-2xl p-6 sm:p-8">
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Staff Account Management</h1>
                <p class="text-slate-600 text-sm mt-1 font-medium">Provision new cashier credentials, review administrator roles, and manage system access.</p>
            </div>

            <?php if (!empty($success_msg)): ?><div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-xl text-sm font-semibold"><?php echo htmlspecialchars($success_msg); ?></div><?php endif; ?>
            <?php if (!empty($error_msg)): ?><div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded-xl text-sm font-semibold"><?php echo htmlspecialchars($error_msg); ?></div><?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Add Staff Form -->
                <div class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm flex flex-col justify-between">
                    <div>
                        <h3 class="text-lg font-extrabold text-slate-900 mb-1">Create Staff Account</h3>
                        <p class="text-xs font-medium text-slate-500 mb-5">Provision login credentials for cashiers or co-administrators.</p>
                        <form action="users.php" method="POST" class="space-y-4">
                            <div><label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Username</label><input type="text" name="username" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-sm font-medium focus:outline-none focus:border-[#056847]" required></div>
                            <div><label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Email</label><input type="email" name="email" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-sm font-medium focus:outline-none focus:border-[#056847]" required></div>
                            <div><label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Phone</label><input type="tel" name="phone" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-sm font-medium focus:outline-none focus:border-[#056847]"></div>
                            <div><label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Role</label><select name="role" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-sm font-medium focus:outline-none focus:border-[#056847]" required><option value="staff" selected>Cashier / Staff</option></select></div>
                            <div><label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Password</label><input type="password" name="password" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-sm font-medium focus:outline-none focus:border-[#056847]" required></div>
                            <button type="submit" name="add_staff" class="w-full bg-[#056847] hover:bg-[#045238] text-white py-3 rounded-xl text-sm font-bold shadow-md transition">+ Register Staff Member</button>
                        </form>
                    </div>
                </div>

                <!-- Users Table with Delete Button -->
                <div class="lg:col-span-2 bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm flex flex-col justify-between">
                    <div>
                        <div class="flex justify-between items-center mb-5">
                            <div><h3 class="text-lg font-extrabold text-slate-900">Authorized System Users</h3><p class="text-xs text-slate-500">Active personnel accounts with access to Shop L4.072.</p></div>
                            <span class="bg-emerald-50 text-[#056847] border border-emerald-200 text-xs px-3.5 py-1.5 rounded-full font-bold"><?php echo count($users); ?> Total</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="border-b border-slate-200 text-xs font-bold uppercase tracking-wider text-slate-600 bg-slate-50/70">
                                        <th class="py-3 px-3">ID</th><th class="py-3 px-3">Username</th><th class="py-3 px-3">Email</th><th class="py-3 px-3">Role</th><th class="py-3 px-3 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-sm font-medium">
                                    <?php foreach ($users as $u): ?>
                                    <tr class="hover:bg-slate-50 transition">
                                        <td class="py-3 px-3 font-mono text-slate-500">#<?php echo $u['id']; ?></td>
                                        <td class="py-3 px-3 font-bold text-slate-900"><?php echo htmlspecialchars($u['username']); ?></td>
                                        <td class="py-3 px-3 text-slate-600 text-xs font-mono"><?php echo htmlspecialchars($u['email']); ?></td>
                                        <td class="py-3 px-3"><span class="px-2.5 py-0.5 rounded-md text-xs font-bold <?php echo $u['role'] === 'admin' ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-slate-100 text-slate-700'; ?>"><?php echo ucfirst($u['role']); ?></span></td>
                                        <td class="py-3 px-3 text-right">
                                            <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                                                <a href="users.php?delete_id=<?php echo $u['id']; ?>" onclick="return confirm('Are you sure you want to delete this user account?');" class="bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 px-3 py-1 rounded-lg text-xs font-bold transition">Delete</a>
                                            <?php else: ?>
                                                <span class="text-xs text-slate-400 italic">Current User</span>
                                            <?php endif; ?>
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