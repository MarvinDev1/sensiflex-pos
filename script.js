var x = document.getElementById("login");
var y = document.getElementById("register");
var f = document.getElementById("forgot"); // FIX: Reference to the new forgot password form element
var z = document.getElementById("btn");

// FIX: Keep the password toggle available on any page that uses the same eye icon pattern, including reset_password.php.
function togglePassword(fieldId, iconElement) {
    var passwordInput = document.getElementById(fieldId);
    if (!passwordInput || !iconElement) return;

    var container = iconElement.parentElement;
    if (passwordInput.type === "password") {
        passwordInput.type = "text";
        iconElement.classList.remove("slashed");
        if (container) container.classList.remove("has-slash");
    } else {
        passwordInput.type = "password";
        iconElement.classList.add("slashed");
        if (container) container.classList.add("has-slash");
    }
}

function setActiveForm(activeForm) {
    [x, y, f].forEach(function (form) {
        if (!form) return;
        form.classList.toggle("active", form === activeForm);
    });
}

// FIX: Guard the shared auth-card logic so pages without the login/register/forgot forms (like reset_password.php) do not throw runtime errors during initialization.
if (!x || !y || !f) {
    console.info('Auth card not present on this page; skipping dynamic form initialization.');
} else {
    function clearAuthAlerts() {
        document.querySelectorAll('.alert-box').forEach(function (alertBox) {
            alertBox.classList.remove('error', 'success');
            alertBox.style.display = 'none';
        });
    }

function showAlert(alertId, alertType) {
    var alertBox = document.getElementById(alertId);
    if (!alertBox) return;
    alertBox.classList.remove('error', 'success');
    alertBox.classList.add(alertType);
    alertBox.style.display = 'block';
}

function syncAuthAlertsFromUrl() {
    var urlParams = new URLSearchParams(window.location.search);
    var error = urlParams.get('error');
    var success = urlParams.get('success');
    var hash = window.location.hash.replace('#', '');
    var effectiveForm = 'login';

    if (hash === 'register') {
        effectiveForm = 'register';
    } else if (hash === 'forgot') {
        effectiveForm = 'forgot';
    } else if (error && error.startsWith('f_')) {
        effectiveForm = 'forgot';
    } else if (error && error.startsWith('r_')) {
        effectiveForm = 'register';
    }

    clearAuthAlerts();

    // FIX: Show backend-triggered alerts only when a real auth action has returned a query-string response.
    // This prevents stale error/success banners from remaining visible after unrelated tab switches or reload cycles.
    if (success === 'registered') {
        showAlert('login-alert', 'success');
        return 'login';
    }

    if (success === 'reset_sent') {
        showAlert('forgot-alert', 'success');
        return 'forgot';
    }

    if (error === 'database_error') {
        if (effectiveForm === 'register') {
            showAlert('register-alert', 'error');
            return 'register';
        }

        if (effectiveForm === 'forgot') {
            showAlert('forgot-alert', 'error');
            return 'forgot';
        }

        showAlert('login-alert', 'error');
        return 'login';
    }

    if (error === 'l_empty_fields' || error === 'l_invalid_format' || error === 'l_invalid_credentials') {
        showAlert('login-alert', 'error');
        return 'login';
    }

    if (error === 'r_empty_fields' || error === 'r_invalid_email' || error === 'r_weak_password' || error === 'r_user_exists') {
        showAlert('register-alert', 'error');
        return 'register';
    }

    if (error === 'f_invalid_email' || error === 'f_not_found') {
        showAlert('forgot-alert', 'error');
        return 'forgot';
    }

    return effectiveForm;
}

// FIX: Match the active form against the card's responsive inner padding.
function login(){
    if (!x || !y || !f || !z) return;
    setActiveForm(x);
    x.style.left = window.matchMedia('(max-width: 768px)').matches ? "20px" : "25px";
    y.style.left = "400px";
    if(f) f.style.left = "400px";
    z.style.left = "0px";
}

function register(){
    if (!x || !y || !f || !z) return;
    setActiveForm(y);
    x.style.left = "-400px";
    y.style.left = window.matchMedia('(max-width: 768px)').matches ? "20px" : "25px";
    if(f) f.style.left = "400px";
    z.style.left = window.matchMedia('(max-width: 768px)').matches ? "50%" : "110px";
    y.style.opacity = "1";
    y.style.visibility = "visible";
}

// FIX: Added dedicated function to handle sliding into the Forgot Password view cleanly without overlapping.
function showForgot(event){
    if (!x || !y || !f) return;
    setActiveForm(f);
    if(event) event.preventDefault();
    x.style.left = "-400px";
    y.style.left = "400px";
    if(f) f.style.left = window.matchMedia('(max-width: 768px)').matches ? "20px" : "25px";
    f.style.opacity = "1";
    f.style.visibility = "visible";
}

// FIX: Add a small submit loading class to each auth form so the button pulses while the server responds.
function setSubmitLoading(form) {
    const button = form.querySelector('.submit-btn');
    if (!button) return;
    button.classList.add('loading');
    button.disabled = true;
    button.textContent = 'Please wait...';
}

document.addEventListener('submit', function(event) {
    const form = event.target;
    if (!form || !form.classList.contains('input-group')) return;
    setSubmitLoading(form);
});

// FIX: Automatically detect URL error or success parameters on page load to display the correct active form without overlapping elements.
// This also covers redirects back from PHP after a failed reset or registration request on page reload.
    window.addEventListener("DOMContentLoaded", function() {
        const triggerView = syncAuthAlertsFromUrl();

        if (triggerView === 'register') {
            register();
        } else if (triggerView === 'forgot') {
            showForgot();
        } else {
            login();
        }
    });
}

// JavScript for the live camera scanner and product lookup on the dashboard page
// script.js - Client-Side POS Logic, Barcode Scanner, Theme Toggling, & Cart State Management

let activeCart = [];
let cameraActive = false;

// Mobile Sidebar Toggle Function for small screens
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    if (!sidebar) return;
    sidebar.classList.toggle('-translate-x-full');
    if (overlay) {
        overlay.classList.toggle('hidden');
    }
}

function toggleMobileSidebar() {
    toggleSidebar();
}

function getSharedScannerContext() {
    const input = document.getElementById('sku-input') || document.getElementById('barcode-input');
    const viewport = document.getElementById('interactive');
    const btn = document.getElementById('cam-toggle-btn');
    const feedback = document.getElementById('lookup-feedback') || document.getElementById('barcode-feedback');
    return { input, viewport, btn, feedback };
}

function bindSidebarCloseButtons() {
    document.querySelectorAll('.sidebar-close-btn').forEach(function(button) {
        button.addEventListener('click', toggleSidebar);
    });
}

function addProductFromUnifiedSearch(product) {
    if (typeof window.addToCartFromCatalog === 'function' && Array.isArray(window.posProducts) && window.posProducts.some(item => Number(item.id) === Number(product.product_id))) {
        window.addToCartFromCatalog(product.product_id, product.name, product.price, product.stock);
        return;
    }

    if (typeof addToCart === 'function') {
        addToCart(product);
        return;
    }
}

function renderUnifiedSearchResults() {
    const input = document.getElementById('barcode-input');
    const results = document.getElementById('barcode-search-results');
    if (!input || !results) return;

    const query = (input.value || '').trim().toLowerCase();
    const products = Array.isArray(window.posProducts) ? window.posProducts : (Array.isArray(window.staffProducts) ? window.staffProducts : []);

    if (!query) {
        results.classList.add('hidden');
        results.innerHTML = '';
        return;
    }

    const matches = products.filter(item => {
        const name = String(item.product_name || item.name || '').toLowerCase();
        const category = String(item.category || '').toLowerCase();
        const id = String(item.id || '').toLowerCase();
        return name.includes(query) || category.includes(query) || id.includes(query);
    }).slice(0, 6);

    if (!matches.length) {
        results.classList.remove('hidden');
        results.innerHTML = '<div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-3 py-3 text-xs text-slate-500">No matching products found.</div>';
        return;
    }

    results.classList.remove('hidden');
    results.innerHTML = matches.map(item => {
        const productId = Number(item.id);
        const productName = String(item.product_name || item.name || '');
        const category = item.category || 'General';
        const price = Number(item.price || 0);
        const stock = Number(item.stock_qty || 0);
        return `
            <div class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5">
                <div class="min-w-0">
                    <div class="font-bold text-slate-900 text-xs truncate">${productName}</div>
                    <div class="text-[10px] text-slate-500">${category} • Shs ${price.toLocaleString()} • ${stock} in stock</div>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" data-product-id="${productId}" class="unified-search-remove bg-slate-200 hover:bg-slate-300 text-slate-700 text-[10px] font-bold px-2 py-1.5 rounded-lg shadow-xs transition">
                        −
                    </button>
                    <button type="button" data-product-id="${productId}" data-product-name="${productName.replace(/'/g, "\\'")}" data-product-price="${price}" data-product-stock="${stock}" class="unified-search-add bg-[#f97316] hover:bg-orange-600 text-white text-[10px] font-bold px-2.5 py-1.5 rounded-lg shadow-xs transition">
                        + Add
                    </button>
                </div>
            </div>
        `;
    }).join('');

    results.querySelectorAll('.unified-search-remove').forEach(function(button) {
        button.addEventListener('click', function() {
            const productId = Number(this.dataset.productId);
            const index = activeCart.findIndex(item => Number(item.product_id) === productId);
            if (index >= 0) {
                if (activeCart[index].qty > 1) {
                    activeCart[index].qty -= 1;
                } else {
                    activeCart.splice(index, 1);
                }
                renderCart();
            }
            input.value = '';
            renderUnifiedSearchResults();
        });
    });

    results.querySelectorAll('.unified-search-add').forEach(function(button) {
        button.addEventListener('click', function() {
            const product = {
                product_id: Number(this.dataset.productId),
                name: this.dataset.productName,
                price: Number(this.dataset.productPrice),
                qty: 1,
                serial_id: null,
                serial_number: 'N/A',
                stock: Number(this.dataset.productStock)
            };

            addProductFromUnifiedSearch(product);
            input.value = '';
            renderUnifiedSearchResults();
        });
    });
}

function handleUnifiedBarcodeInput(event) {
    if (!event || !event.target || event.target.id !== 'barcode-input') return;

    if (event.type === 'input') {
        renderUnifiedSearchResults();
        return;
    }

    if (event.key === 'Enter') {
        event.preventDefault();
        const value = (event.target.value || '').trim();
        if (!value) return;
        if (typeof lookupProduct === 'function') {
            lookupProduct();
        } else {
            renderUnifiedSearchResults();
        }
    }
}

function bindUnifiedBarcodeInput() {
    const input = document.getElementById('barcode-input');
    if (!input) return;

    input.addEventListener('input', handleUnifiedBarcodeInput);
    input.addEventListener('keydown', handleUnifiedBarcodeInput);
}

window.addEventListener('DOMContentLoaded', function() {
    bindSidebarCloseButtons();
    bindUnifiedBarcodeInput();
});

function printReceipt() {
    const receipt = document.getElementById('thermal-receipt');
    if (!receipt) return;

    const printWindow = window.open('', '_blank', 'width=420,height=900');
    if (!printWindow) {
        window.print();
        return;
    }

    printWindow.document.write(`<!DOCTYPE html><html><head><title>Receipt</title><style>
        body { margin: 0; background: #fff; font-family: 'Courier New', Courier, monospace; color: #111827; }
        .receipt { width: 100%; max-width: 360px; margin: 0 auto; padding: 18px 16px; box-sizing: border-box; }
        .receipt-header { text-align: center; font-weight: 700; font-size: 12px; letter-spacing: 0.04em; }
        .receipt-meta, .receipt-row, .receipt-footer { font-size: 11px; line-height: 1.6; }
        .receipt-rule { border-top: 1px dashed #111827; margin: 10px 0; }
        .receipt-row { display: flex; justify-content: space-between; gap: 8px; }
        .receipt-total { font-weight: 700; }
        .receipt-discount { color: #056847; }
        .receipt-footer { text-align: center; margin-top: 12px; }
    </style></head><body><div class="receipt">${receipt.innerHTML}</div></body></html>`);
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 250);
}

function clearQueue() {
    if (typeof activeCart !== 'undefined' && Array.isArray(activeCart)) {
        activeCart.length = 0;
    }

    if (typeof cart !== 'undefined' && Array.isArray(cart)) {
        cart.length = 0;
    }

    const cartJson = document.getElementById('cart-items-json');
    if (cartJson) {
        cartJson.value = '[]';
    }

    const cartTable = document.getElementById('cart-table-body');
    if (cartTable) {
        cartTable.innerHTML = '<tr><td colspan="6" class="py-6 text-center text-slate-500">Cart is empty. Scan items to begin checkout.</td></tr>';
    }

    const subtotal = document.getElementById('cart-subtotal');
    const total = document.getElementById('cart-total');
    const discount = document.getElementById('cart-discount');
    if (subtotal) subtotal.textContent = 'UGX 0.00';
    if (total) total.textContent = 'UGX 0.00';
    if (discount) discount.value = '0';

    const receiptWrapper = document.getElementById('admin-receipt-wrapper');
    if (receiptWrapper) {
        receiptWrapper.classList.add('hidden');
    }

    const receipt = document.getElementById('thermal-receipt');
    if (receipt) {
        receipt.innerHTML = '';
    }

    const input = document.getElementById('barcode-input');
    if (input) {
        input.value = '';
        input.focus();
    }

    const searchResults = document.getElementById('barcode-search-results');
    if (searchResults) {
        searchResults.classList.add('hidden');
        searchResults.innerHTML = '';
    }

    if (typeof renderCart === 'function') {
        renderCart();
    }
}

function clearReceiptAndCart() {
    clearQueue();
}

// Live Camera Viewfinder Toggle & Scanner Initialization
function toggleCameraScanner() {
    const { viewport, btn, input } = getSharedScannerContext();
    if (!viewport || !btn) return;

    if (!cameraActive) {
        viewport.classList.remove('hidden');
        btn.textContent = 'Stop Camera Scanner';
        cameraActive = true;

        if (!window.Quagga) {
            console.warn('QuaggaJS is not loaded on this page.');
            alert('Camera scanner is unavailable because QuaggaJS is not loaded.');
            cameraActive = false;
            btn.textContent = 'Start Live Camera Scanner';
            viewport.classList.add('hidden');
            return;
        }

        Quagga.init({
            inputStream: {
                name: 'Live',
                type: 'LiveStream',
                target: viewport,
                constraints: {
                    facingMode: 'environment'
                }
            },
            decoder: {
                readers: ['code_128_reader', 'ean_reader', 'upc_reader', 'qr_code_reader']
            }
        }, function(err) {
            if (err) {
                console.error(err);
                alert('Camera initialization failed. Please ensure camera permissions are allowed.');
                cameraActive = false;
                btn.textContent = 'Start Live Camera Scanner';
                viewport.classList.add('hidden');
                return;
            }
            Quagga.start();
        });

        Quagga.onDetected(function(result) {
            const code = result && result.codeResult ? result.codeResult.code : '';
            if (!code) return;
            if (input) input.value = code;
            toggleCameraScanner();
            lookupProduct();
        });

    } else {
        if (window.Quagga) {
            Quagga.stop();
        }
        viewport.classList.add('hidden');
        btn.textContent = 'Start Live Camera Scanner';
        cameraActive = false;
    }
}

// Barcode/QR Code & SKU Product Lookup (AJAX)
function lookupProduct() {
    const { input, feedback } = getSharedScannerContext();
    const sku = (input ? input.value : '').trim();

    if (!sku) {
        if (feedback) {
            feedback.textContent = 'Please enter a valid SKU or scan barcode.';
            feedback.className = 'mt-3 text-sm text-red-400';
        }
        return;
    }

    fetch(`staff_dashboard.php?action=lookup_product&sku=${encodeURIComponent(sku)}`)
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                feedback.textContent = data.message;
                feedback.className = 'mt-3 text-sm text-red-400';
                return;
            }

            feedback.textContent = `Found: ${data.product.product_name} (Stock: ${data.product.stock_qty}). Use the + Add button to place it in the cart.`;
            feedback.className = 'mt-3 text-sm text-emerald-400';

            const productList = Array.isArray(window.staffProducts) ? window.staffProducts : [];
            if (!productList.some(item => Number(item.id) === Number(data.product.id))) {
                productList.push(data.product);
            }
            window.staffProducts = productList;
            renderUnifiedSearchResults();
        })
        .catch(error => {
            console.error('Error:', error);
            feedback.textContent = 'Network error during product lookup.';
            feedback.className = 'mt-3 text-sm text-red-400';
        });
}

// Add item to active cart array state
function addToCart(product) {
    let existing = activeCart.find(item => item.product_id === product.product_id && item.serial_id === product.serial_id);
    if (existing) {
        existing.qty += 1;
    } else {
        activeCart.push(product);
    }
    renderCart();
}

function syncCartDataPayload() {
    const cartPayload = document.getElementById('cart-items-json');
    if (cartPayload) {
        cartPayload.value = JSON.stringify(activeCart);
    }
}

function decrementFromCart(index) {
    const item = activeCart[index];
    if (!item) return;
    if (item.qty > 1) {
        item.qty -= 1;
    } else {
        activeCart.splice(index, 1);
    }
    renderCart();
}

// Remove item from cart state
function removeFromCart(index) {
    activeCart.splice(index, 1);
    renderCart();
}

// Automated Bulk Discount Engine & Cart Rendering
function renderCart() {
    const tbody = document.getElementById('cart-table-body');
    tbody.innerHTML = '';
    syncCartDataPayload();

    if (activeCart.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="py-6 text-center opacity-60">Cart is empty. Scan items to begin checkout.</td></tr>';
        document.getElementById('cart-subtotal').textContent = 'UGX 0.00';
        document.getElementById('cart-discount').textContent = 'UGX 0.00';
        document.getElementById('cart-total').textContent = 'UGX 0.00';
        syncCartDataPayload();
        return;
    }

    let subtotal = 0;
    activeCart.forEach((item, index) => {
        let itemTotal = item.price * item.qty;
        subtotal += itemTotal;

        let tr = document.createElement('tr');
        tr.innerHTML = `
            <td class="py-3 px-4">${item.name}</td>
            <td class="py-3 px-4 text-amber-400 font-mono text-xs">${item.serial_number}</td>
            <td class="py-3 px-4">UGX ${item.price.toLocaleString()}</td>
            <td class="py-3 px-4">${item.qty}</td>
            <td class="py-3 px-4 font-bold">UGX ${itemTotal.toLocaleString()}</td>
            <td class="py-3 px-4">
                <div class="flex items-center gap-2">
                    <button type="button" onclick="decrementFromCart(${index})" class="bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg px-2 py-1 text-xs font-bold">−</button>
                    <button type="button" onclick="removeFromCart(${index})" class="text-red-400 hover:text-red-300 text-xs font-bold">Remove</button>
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });

    // Automated Bulk Discount Calculation (5% if subtotal exceeds 100,000 UGX)
    let discount = subtotal > 100000 ? subtotal * 0.05 : 0;
    let total = subtotal - discount;

    document.getElementById('cart-subtotal').textContent = 'UGX ' + subtotal.toLocaleString();
    document.getElementById('cart-discount').textContent = '-UGX ' + discount.toLocaleString();
    document.getElementById('cart-total').textContent = 'UGX ' + total.toLocaleString();
}

// Prepare JSON payload for checkout form submission
function prepareCheckout() {
    document.getElementById('cart-items-json').value = JSON.stringify(activeCart);
}

// FIX: Initialize the orange slash for password fields that start hidden on every authentication view.
document.querySelectorAll('.eye-icon.slashed').forEach(function (iconElement) {
    if (iconElement.parentElement) {
        iconElement.parentElement.classList.add('has-slash');
    }
});