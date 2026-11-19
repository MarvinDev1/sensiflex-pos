<?php
// pos.php - SENSIFLEX POS Checkout & Sales Terminal
session_start();

// Security Check: Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Database Connection via Centralized db.php
require_once 'db.php';

// Fetch products with stock for POS lookup
try {
    $stmt = $pdo->query("SELECT * FROM products WHERE stock_qty > 0 ORDER BY product_name ASC");
    $pos_products = $stmt->fetchAll();
} catch (PDOException $e) {
    $pos_products = [];
    $pos_error = "Error loading products: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, viewport-fit=cover">
    <title>SENSIFLEX - POS Checkout Terminal</title>
    <link rel="stylesheet" href="style.css">
    <!-- Tailwind CSS CDN for high-end Bento Grid utility styling -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- QuaggaJS: loaded before script.js so camera scanning is available -->
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
    <script>
        window.posProducts = <?php echo json_encode($pos_products); ?>;
    </script>

    <!-- Main Content Area -->
    <div class="flex-1 lg:ml-72 flex flex-col min-h-screen page-shell">

        <!-- Top Header Bar -->
        <header class="border-b border-slate-200 bg-white/90 backdrop-blur-md sticky top-0 z-30 px-4 sm:px-6 py-4 flex flex-wrap sm:flex-nowrap justify-between items-center gap-3 shadow-sm">
            <div class="pl-12 sm:pl-0 min-w-0">
                <span class="text-xs font-bold uppercase tracking-wider text-[#056847]">Sales Operations</span>
                <h2 class="text-sm sm:text-base font-extrabold text-slate-900">Checkout Terminal</h2>
            </div>
            <div class="flex flex-wrap items-center gap-2 sm:gap-3 max-w-full ml-auto">
                <a href="<?php echo ($_SESSION['role'] === 'admin') ? 'dashboard.php' : 'staff_dashboard.php'; ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold border border-slate-200 transition">← Back to Dashboard</a>
                <button onclick="document.getElementById('barcode-input').focus()" class="bg-[#f97316] hover:bg-orange-600 text-white px-3 sm:px-4 py-2 rounded-xl text-xs font-bold shadow-md transition flex items-center gap-2">
                    📷 Scanner
                </button>
            </div>
        </header>

        <!-- Main Bento Grid Container -->
        <main class="p-4 sm:p-6 lg:p-8 space-y-6 flex-1 max-w-7xl w-full mx-auto box-border">

            <!-- Page Header Banner -->
            <div class="bg-gradient-to-r from-[#056847]/10 via-white to-[#f97316]/10 border border-slate-200/80 rounded-2xl p-4 sm:p-6 lg:p-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 shadow-sm">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Point of Sale Terminal</h1>
                    <p class="text-slate-600 text-sm mt-1 font-medium">Scan hardware serials/barcodes, calculate discounts, and print sales receipts.</p>
                </div>
                <div class="bg-white border border-slate-200 px-4 py-2 rounded-xl text-right shadow-xs">
                    <span class="text-[10px] font-bold uppercase tracking-widest text-slate-400 block">Current Terminal Date</span>
                    <span class="text-xs font-extrabold text-[#056847]"><?php echo date('F d, Y'); ?></span>
                </div>
            </div>

            <!-- Bento Grid Layout: Product Catalog (Left) & Sales Cart (Right) -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 sm:gap-6">

                <!-- Left Column: Product Search & Item Catalog (7 Columns) -->
                <div class="lg:col-span-7 space-y-4 sm:space-y-6 min-w-0">

                    <!-- Barcode & QR Code Fast Scan Box -->
                    <div class="bg-white border border-slate-200/80 rounded-2xl p-4 sm:p-5 shadow-sm space-y-4 box-border min-w-0">
                        <label class="block text-xs sm:text-sm font-bold uppercase tracking-wider text-[#056847]">
                            ⚡ Rapid QR / Barcode / Serial Scan
                        </label>
                        <div class="relative">
                            <input type="text" id="barcode-input" class="w-full min-w-0 box-border pl-10 pr-4 py-3 border-2 border-[#f97316] rounded-xl text-sm sm:text-base font-bold text-slate-900 focus:outline-none focus:ring-4 focus:ring-orange-100 shadow-sm" placeholder="Scan Barcode or enter Product Name/SKU..." autofocus>
                            <span class="absolute left-3.5 top-3.5 text-slate-400 text-sm">🔍</span>
                        </div>
                        <div id="barcode-search-results" class="hidden mt-2 space-y-2"></div>
                        <div class="flex flex-wrap sm:flex-nowrap justify-between items-center gap-3">
                            <span class="text-xs sm:text-sm text-slate-500 font-medium">Or use phone/laptop camera scanner:</span>
                            <button type="button" onclick="toggleCameraScanner()" id="cam-toggle-btn" class="bg-slate-100 hover:bg-slate-200 text-xs sm:text-sm text-slate-700 font-bold px-3 py-2 rounded-lg transition border border-slate-200 shadow-sm max-w-full">
                                Start Live Camera Scanner
                            </button>
                        </div>
                        <div id="interactive" class="viewport relative w-full min-h-[280px] sm:min-h-[340px] max-w-full bg-slate-100 border-2 border-[#f97316]/70 rounded-xl overflow-hidden hidden flex items-center justify-center shadow-inner">
                            <div class="text-xs text-slate-500">Initializing Camera Stream...</div>
                        </div>
                        <div id="lookup-feedback" class="text-sm text-slate-600 font-medium"></div>
                        <p class="text-[11px] text-slate-500 font-medium">Tip: Keep cursor focused in the field above for continuous handheld scanner operation.</p>
                    </div>

                    <!-- Catalog Bento Card -->
                    <div class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm flex flex-col justify-between">
                        <div class="flex justify-between items-center mb-4 gap-3">
                            <h3 class="text-lg font-extrabold text-slate-900">Security Hardware Catalog</h3>
                        </div>

                        <!-- Product Cards Grid -->
                        <div id="product-grid" class="grid grid-cols-1 sm:grid-cols-2 gap-4 max-h-[460px] overflow-y-auto pr-1">
                            <?php if (empty($pos_products)): ?>
                                <p class="text-xs text-slate-400 italic col-span-2 py-8 text-center">No products with active stock available in store.</p>
                            <?php else: ?>
                                <?php foreach ($pos_products as $p): ?>
                                    <div class="catalog-item bg-slate-50 border border-slate-200/80 rounded-xl p-4 flex flex-col justify-between hover:border-[#f97316] hover:shadow-md transition">
                                        <div>
                                            <span class="text-[10px] font-bold uppercase text-slate-500 bg-white px-2 py-0.5 rounded border border-slate-200">
                                                <?php echo htmlspecialchars($p['category']); ?>
                                            </span>
                                            <h4 class="font-bold text-sm text-slate-900 mt-2 line-clamp-1"><?php echo htmlspecialchars($p['product_name']); ?></h4>
                                            <p class="text-xs font-mono font-bold text-[#056847] mt-1">Shs <?php echo number_format($p['price']); ?></p>
                                        </div>
                                        <div class="mt-3 pt-2 border-t border-slate-200/60 flex justify-between items-center">
                                            <span class="text-[11px] font-semibold text-slate-500"><?php echo $p['stock_qty']; ?> in stock</span>
                                            <div class="flex items-center gap-2">
                                                <button type="button" onclick="decrementCartItem(<?php echo $p['id']; ?>)" class="bg-slate-200 hover:bg-slate-300 text-slate-700 text-xs px-2.5 py-1 rounded-lg font-bold shadow-xs transition">−</button>
                                                <button type="button" onclick="addToCartFromCatalog(<?php echo $p['id']; ?>, '<?php echo addslashes($p['product_name']); ?>', <?php echo $p['price']; ?>, <?php echo $p['stock_qty']; ?>)" class="bg-[#f97316] hover:bg-orange-600 text-white text-xs px-3 py-1 rounded-lg font-bold shadow-xs transition">
                                                    + Add
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>

                <!-- Right Column: POS Cart & Checkout Receipt Builder (5 Columns) -->
                <div class="lg:col-span-5 space-y-6">

                    <div class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm flex flex-col justify-between min-h-[560px]">
                        <div>
                            <!-- Cart Header -->
                            <div class="flex justify-between items-center pb-4 border-b border-slate-100">
                                <div>
                                    <h3 class="text-lg font-extrabold text-slate-900">Current Sales Order</h3>
                                    <p class="text-xs text-slate-500 font-medium">Customer receipt draft</p>
                                </div>
                                <button onclick="clearCart()" class="text-xs font-bold text-red-600 hover:underline">Clear Order</button>
                            </div>

                            <!-- Cart Items Table -->
                            <div class="mt-4 overflow-y-auto max-h-[260px]">
                                <table class="w-full text-left border-collapse">
                                    <thead>
                                        <tr class="text-[11px] font-bold uppercase text-slate-400 border-b border-slate-100 pb-2">
                                            <th>Item</th>
                                            <th class="text-center">Qty</th>
                                            <th class="text-right">Total</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="cart-table-body" class="divide-y divide-slate-100 text-xs font-medium">
                                        <tr id="empty-cart-row">
                                            <td colspan="4" class="py-12 text-center text-slate-400 italic">
                                                Cart is empty. Click catalog items or scan barcodes to start checkout.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Calculations Summary & Checkout Trigger -->
                        <div class="pt-4 border-t border-slate-100 space-y-3">
                            <input type="hidden" name="cart_items_json" id="cart-items-json" value="[]">
                            <div class="flex justify-between text-xs font-semibold text-slate-600">
                                <span>Subtotal</span>
                                <span id="cart-subtotal" class="font-mono">Shs 0</span>
                            </div>

                            <div class="flex justify-between items-center text-xs font-semibold text-slate-600">
                                <span>Discount (UGX)</span>
                                <input type="number" id="cart-discount" oninput="calculateTotals()" value="0" min="0" class="w-24 text-right px-2 py-1 border border-slate-300 rounded text-xs font-mono focus:outline-none focus:border-[#056847]">
                            </div>

                            <div class="flex justify-between text-base font-extrabold text-slate-900 pt-2 border-t border-slate-100">
                                <span>Final Payable</span>
                                <span id="cart-total" class="font-mono text-[#056847]">Shs 0</span>
                            </div>

                            <button onclick="processCheckout()" class="w-full bg-[#f97316] hover:bg-orange-600 text-white py-3.5 rounded-xl text-sm font-bold shadow-md transition flex items-center justify-center space-x-2">
                                <span> Complete Sale & Print Receipt</span>
                            </button>
                            <button type="button" onclick="clearQueue()" class="w-full mt-3 bg-slate-900 hover:bg-slate-700 text-white py-3 rounded-xl text-sm font-bold shadow-md transition flex items-center justify-center space-x-2">
                                <span> Clear Queue / New Sale</span>
                            </button>
                        </div>

                        <div id="admin-receipt-wrapper" class="receipt-container hidden">
                            <div id="thermal-receipt" class="thermal-receipt"></div>
                            <div class="receipt-actions">
                                <button type="button" onclick="printReceipt()" class="bg-[#056847] hover:bg-[#045238] text-white px-4 py-2.5 rounded-xl text-sm font-bold shadow-sm">🖨️ Print Receipt</button>
                                <button type="button" onclick="clearReceiptAndCart()" class="bg-slate-900 hover:bg-slate-700 text-white px-4 py-2.5 rounded-xl text-sm font-bold shadow-sm">➕ New Transaction / Clear</button>
                            </div>
                        </div>
                    </div>

                </div>

            </div>

        </main>
    </div>
     <!-- Interactive Terminal Scripts -->
    <script>
            window.activeCart = window.activeCart || [];
            window.cart = window.activeCart;

        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('-translate-x-full');
            document.getElementById('sidebar-overlay').classList.toggle('hidden');
        }

        function getSharedCart() {
            if (!Array.isArray(window.activeCart)) {
                window.activeCart = [];
            }
            if (!Array.isArray(window.cart)) {
                window.cart = window.activeCart;
            }
            return window.activeCart;
        }

        function addToCartFromCatalog(id, name, price, stock) {
            const cartItems = getSharedCart();
            let existing = cartItems.find(item => Number(item.id ?? item.product_id) === Number(id));
            if (existing) {
                if (Number(existing.qty) < Number(stock)) {
                    existing.qty += 1;
                } else {
                    alert('Maximum available store stock reached for this item.');
                    return;
                }
            } else {
                cartItems.push({ id: Number(id), product_id: Number(id), name, price: Number(price), qty: 1, stock: Number(stock) });
            }
            window.activeCart = cartItems;
            window.cart = cartItems;
            renderCart();
        }

        function syncCartPayload() {
            const cartItems = getSharedCart();
            const hidden = document.getElementById('cart-items-json');
            if (hidden) {
                hidden.value = JSON.stringify(cartItems);
            }
        }

        function removeFromCart(id) {
            const cartItems = getSharedCart();
            const nextCart = cartItems.filter(item => Number(item.id ?? item.product_id) !== Number(id));
            window.activeCart = nextCart;
            window.cart = nextCart;
            renderCart();
        }

        function decrementCartItem(id) {
            const cartItems = getSharedCart();
            const item = cartItems.find(item => Number(item.id ?? item.product_id) === Number(id));
            if (!item) return;
            if (Number(item.qty) > 1) {
                item.qty -= 1;
            } else {
                const nextCart = cartItems.filter(item => Number(item.id ?? item.product_id) !== Number(id));
                window.activeCart = nextCart;
                window.cart = nextCart;
            }
            renderCart();
        }

        function clearCart() {
            window.activeCart = [];
            window.cart = window.activeCart;
            renderCart();
        }

        function renderCart() {
            const cartItems = getSharedCart();
            const tbody = document.getElementById('cart-table-body');
            if (!tbody) return;
            tbody.innerHTML = '';
            syncCartPayload();

            if (cartItems.length === 0) {
                tbody.innerHTML = `<tr id="empty-cart-row"><td colspan="4" class="py-12 text-center text-slate-400 italic">Cart is empty. Click catalog items or scan barcodes to start checkout.</td></tr>`;
                calculateTotals();
                return;
            }

            cartItems.forEach(item => {
                let row = document.createElement('tr');
                row.className = 'hover:bg-slate-50/80 transition';
                row.innerHTML = `
                    <td class="py-2.5 font-bold text-slate-800">${item.name}</td>
                    <td class="py-2.5 text-center font-mono">${item.qty}</td>
                    <td class="py-2.5 text-right font-mono font-bold text-[#056847]">Shs ${(Number(item.price) * Number(item.qty)).toLocaleString()}</td>
                    <td class="py-2.5 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <button type="button" onclick="decrementCartItem(${Number(item.id ?? item.product_id)})" class="bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg px-2 py-1 text-xs font-bold">−</button>
                            <button type="button" onclick="removeFromCart(${Number(item.id ?? item.product_id)})" class="text-red-500 font-bold hover:text-red-700 px-1">Remove</button>
                        </div>
                    </td>
                `;
                tbody.appendChild(row);
            });

            calculateTotals();
        }

        function calculateTotals() {
            const cartItems = getSharedCart();
            let subtotal = cartItems.reduce((sum, item) => sum + (Number(item.price) * Number(item.qty)), 0);
            let discount = parseFloat(document.getElementById('cart-discount').value) || 0;
            let total = Math.max(0, subtotal - discount);

            document.getElementById('cart-subtotal').innerText = 'Shs ' + subtotal.toLocaleString();
            document.getElementById('cart-total').innerText = 'Shs ' + total.toLocaleString();
            syncCartPayload();
        }

        function processCheckout() {
            const cartItems = getSharedCart();
            if (cartItems.length === 0) {
                alert('Cart is empty! Add items before completing sale.');
                return;
            }

            const subtotal = cartItems.reduce((sum, item) => sum + (Number(item.price) * Number(item.qty)), 0);
            const discount = parseFloat(document.getElementById('cart-discount').value) || 0;
            const total = Math.max(0, subtotal - discount);
            const invoiceNumber = 'INV-' + new Date().toISOString().slice(0,10).replace(/-/g, '') + '-' + String(Math.random()).slice(2, 8).toUpperCase();

            const wrapper = document.getElementById('admin-receipt-wrapper');
            const receipt = document.getElementById('thermal-receipt');
            if (receipt && wrapper) {
                const rows = cartItems.map(item => `
                    <div class="thermal-row">
                        <span class="thermal-item-name">${item.name}</span>
                        <span class="thermal-item-total">${(Number(item.price) * Number(item.qty)).toLocaleString()}</span>
                    </div>
                `).join('');

                receipt.innerHTML = `
                    <div class="thermal-header">=== SENSIFLEX OFFICIAL RECEIPT ===</div>
                    <div class="thermal-meta">Receipt ID: #${Date.now()}</div>
                    <div class="thermal-meta">Invoice: ${invoiceNumber}</div>
                    <div class="thermal-meta">Cashier: Admin Terminal</div>
                    <div class="thermal-meta">Date: ${new Date().toLocaleString()}</div>
                    <div class="thermal-rule"></div>
                    ${rows}
                    <div class="thermal-rule"></div>
                    <div class="thermal-row"><span>Subtotal:</span><span>${subtotal.toLocaleString()}</span></div>
                    <div class="thermal-row thermal-discount"><span>Discount:</span><span>-${discount.toLocaleString()}</span></div>
                    <div class="thermal-row thermal-total"><span>Total:</span><span>UGX ${total.toLocaleString()}</span></div>
                    <div class="thermal-rule"></div>
                    <div class="thermal-footer">12 Months Standard Hardware Warranty (CCTV & Security Systems)</div>
                `;
                wrapper.classList.remove('hidden');
            }
        }
    </script>
    <script src="script.js"></script>
</body>
</html>