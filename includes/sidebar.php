<?php
$current_page = basename($_SERVER['PHP_SELF']);
$current_role = $_SESSION['role'] ?? '';

$nav_items = [];
if ($current_role === 'admin') {
    $nav_items = [
        ['href' => 'dashboard.php', 'label' => 'Dashboard', 'icon' => '📊'],
        ['href' => 'inventory.php', 'label' => 'Inventory & SKUs', 'icon' => '📦'],
        ['href' => 'pos.php', 'label' => 'POS Terminal', 'icon' => '🛒'],
        ['href' => 'reports.php', 'label' => 'Sales & Reports', 'icon' => '📈'],
        ['href' => 'users.php', 'label' => 'Staff Management', 'icon' => '👥'],
    ];
} elseif ($current_role === 'staff') {
    $nav_items = [
        ['href' => 'staff_dashboard.php', 'label' => 'POS Checkout', 'icon' => '🛒'],
        ['href' => 'inventory.php', 'label' => 'Inventory & Serials', 'icon' => '📦'],
        ['href' => 'sales.php', 'label' => 'My Sales History', 'icon' => '📈'],
    ];
}

$is_nav_active = function ($href) use ($current_page) {
    $target_page = basename($href);
    if ($target_page === 'inventory.php') {
        $inventory_pages = ['inventory.php', 'serial_manage.php', 'product_serials.php'];
        return in_array($current_page, $inventory_pages, true);
    }

    return $current_page === $target_page;
};
?>

<aside id="sidebar" class="fixed top-0 left-0 bottom-0 w-72 text-white border-r border-[#045238] z-50 transform -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out flex flex-col justify-between shadow-xl lg:shadow-none" style="background-color: #043927;">
    <div class="relative">
        <button type="button" class="sidebar-close-btn lg:hidden" aria-label="Close menu">✕</button>
        <div class="p-6 border-b border-emerald-800/60 flex flex-col items-center justify-center bg-emerald-900/20">
            <img src="gallary/logo.png" alt="Sensiflex Logo" class="h-12 w-auto mx-auto">
        </div>

        <nav class="p-4 space-y-2">
            <?php foreach ($nav_items as $nav): ?>
                <?php $is_active = $is_nav_active($nav['href']); ?>
                <a href="<?php echo $nav['href']; ?>" class="flex items-center space-x-3 px-4 py-3 rounded-xl text-sm transition <?php echo $is_active ? 'bg-[#f97316] text-white font-bold shadow-md' : 'text-emerald-100 hover:bg-emerald-800/50 font-medium'; ?>">
                    <span class="text-lg"><?php echo $nav['icon']; ?></span>
                    <span><?php echo $nav['label']; ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>

    <div class="p-4 border-t border-emerald-800/60 space-y-3 bg-emerald-900/20">
        <div class="px-4 py-2.5 rounded-xl bg-emerald-900/40 border border-emerald-700/50 flex items-center space-x-3">
            <div class="w-8 h-8 rounded-full bg-[#f97316] text-white flex items-center justify-center font-black text-sm shadow-sm">
                <?php echo strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)); ?>
            </div>
            <div class="overflow-hidden">
                <p class="text-xs font-bold text-white truncate"><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></p>
                <p class="text-[10px] text-emerald-200 capitalize"><?php echo htmlspecialchars($_SESSION['role'] ?? 'User'); ?></p>
            </div>
        </div>

        <a href="logout.php" class="flex items-center justify-center space-x-2 w-full py-2.5 px-4 rounded-xl bg-red-500/20 hover:bg-red-500/30 text-red-200 border border-red-400/30 font-bold text-xs transition shadow-sm">
            <span>Log Out System</span>
        </a>
    </div>
</aside>
