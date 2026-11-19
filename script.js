var x = document.getElementById("login");
var y = document.getElementById("register");
var f = document.getElementById("forgot"); // FIX: Reference to the new forgot password form element
var z = document.getElementById("btn");

// 3-Step Password Eye Toggle (Mobile & Desktop):
// Step 1: Initial load (password hidden, normal eye, no slash)
// Step 2: First tap / reveal (password text, eye scales & brightens, no slash)
// Step 3: Second tap / hide (password hidden, eye returns to normal, diagonal orange slash animates across)
function togglePassword(fieldId, iconElement) {
    var passwordInput = document.getElementById(fieldId);
    if (!passwordInput || !iconElement) return;

    var container = iconElement.parentElement;

    if (passwordInput.type === "password") {
        // Step 2: reveal password text, brighten/scale the eye, no slash.
        passwordInput.type = "text";
        iconElement.classList.remove("slashed");
        iconElement.classList.add("eye-revealed");
        iconElement.setAttribute("aria-label", "Hide password");
        if (container) container.classList.remove("has-slash");
    } else {
        // Step 3: hide password text, return eye to normal, animate orange slash across.
        passwordInput.type = "password";
        iconElement.classList.remove("eye-revealed");
        iconElement.classList.add("slashed");
        iconElement.setAttribute("aria-label", "Show password");
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
            alertBox.classList.remove('error', 'success', 'is-visible');
            alertBox.style.display = 'none';
        });
    }

function showAlert(alertId, alertType) {
    var alertBox = document.getElementById(alertId);
    if (!alertBox) return;
    alertBox.classList.remove('error', 'success', 'is-visible');
    alertBox.style.display = 'block';
    alertBox.classList.add(alertType);

    // Re-trigger GPU-friendly transition (opacity and translateY(-4px) -> translateY(0) over 250ms)
    alertBox.style.animation = 'none';
    void alertBox.offsetHeight;
    alertBox.style.animation = '';

    requestAnimationFrame(function () {
        alertBox.classList.add('is-visible');
    });
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
    resetSubmitLoading(x);
    resetSubmitLoading(y);
    if(f) resetSubmitLoading(f);
    setActiveForm(x);
    x.style.left = window.matchMedia('(max-width: 768px)').matches ? "0px" : "32px";
    y.style.left = "400px";
    if(f) f.style.left = "400px";
    z.style.left = "0px";
}

function register(){
    if (!x || !y || !f || !z) return;
    resetSubmitLoading(x);
    resetSubmitLoading(y);
    if(f) resetSubmitLoading(f);
    setActiveForm(y);
    x.style.left = "-400px";
    y.style.left = window.matchMedia('(max-width: 768px)').matches ? "0px" : "32px";
    if(f) f.style.left = "400px";
    z.style.left = window.matchMedia('(max-width: 768px)').matches ? "50%" : "110px";
    y.style.opacity = "1";
    y.style.visibility = "visible";
}

// FIX: Added dedicated function to handle sliding into the Forgot Password view cleanly without overlapping.
function showForgot(event){
    if (!x || !y || !f) return;
    resetSubmitLoading(x);
    resetSubmitLoading(y);
    if(f) resetSubmitLoading(f);
    setActiveForm(f);
    if(event) event.preventDefault();
    x.style.left = "-400px";
    y.style.left = "400px";
    if(f) f.style.left = window.matchMedia('(max-width: 768px)').matches ? "0px" : "32px";
    f.style.opacity = "1";
    f.style.visibility = "visible";
}

// FIX: Add a small submit loading class to each auth form so the button pulses while the server responds.
function setSubmitLoading(form) {
    const button = form.querySelector('.submit-btn');
    if (!button || button.classList.contains('loading')) return false;

    button.classList.add('loading');
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
    button.setAttribute('aria-label', 'Submitting');

    const originalText = button.textContent.trim();
    if (!button.getAttribute('data-original-label')) {
        button.setAttribute('data-original-label', originalText);
    }
    button.innerHTML = '<span class="submit-btn-text">Please wait...</span>';
    return true;
}

function resetSubmitLoading(buttonOrForm) {
    if (!buttonOrForm) return;
    const button = buttonOrForm.classList && buttonOrForm.classList.contains('submit-btn')
        ? buttonOrForm
        : buttonOrForm.querySelector('.submit-btn');
    if (!button) return;

    button.classList.remove('loading');
    button.disabled = false;
    button.removeAttribute('aria-busy');

    const originalText = button.getAttribute('data-original-label');
    if (originalText) {
        button.innerHTML = '<span class="submit-btn-text">' + originalText + '</span>';
        button.setAttribute('aria-label', originalText);
    }
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

// FIX: Improve Quagga loading and camera permission handling so mobile scanning works over HTTPS and cleanly falls back to manual SKU entry.
let quaggaLoadPromise = null;

function ensureQuaggaLoaded() {
    if (typeof window.Quagga !== 'undefined') {
        return Promise.resolve(window.Quagga);
    }

    if (!quaggaLoadPromise) {
        quaggaLoadPromise = new Promise(function(resolve, reject) {
            const fallbackScript = document.createElement('script');
            fallbackScript.src = 'https://cdn.jsdelivr.net/npm/quagga@0.12.1/dist/quagga.min.js';
            fallbackScript.async = true;
            fallbackScript.onload = function() {
                if (typeof window.Quagga !== 'undefined') resolve(window.Quagga);
                else reject(new Error('QuaggaJS did not initialize.'));
            };
            fallbackScript.onerror = function() {
                reject(new Error('QuaggaJS could not be downloaded.'));
            };
            document.head.appendChild(fallbackScript);
        });
    }

    return quaggaLoadPromise;
}

function resetCameraScannerUI(viewport, btn) {
    stopBarcodeDetectorFallback();
    cameraActive = false;
    if (viewport) viewport.classList.add('hidden');
    if (btn) btn.textContent = 'Start Live Camera Scanner';
}

function useManualBarcodeEntry(input, feedback) {
    if (input) {
        input.focus();
        input.placeholder = 'Type or scan the barcode/SKU manually';
    }
    if (feedback) {
        feedback.textContent = 'Camera scanner unavailable. You can enter the barcode or SKU manually.';
        feedback.className = 'mt-3 text-sm text-amber-700';
    }
}

// Live Camera Viewfinder Toggle & Scanner Initialization
async function toggleCameraScanner() {
    const { viewport, btn, input, feedback } = getSharedScannerContext();
    if (!viewport || !btn) return;

    if (cameraActive) {
        if (typeof window.Quagga !== 'undefined') {
            try { window.Quagga.stop(); } catch (error) { console.warn(error); }
        }
        resetCameraScannerUI(viewport, btn);
        return;
    }

    const isLocal = location.hostname === 'localhost' || location.hostname === '127.0.0.1';
    if (location.protocol !== 'https:' && !isLocal) {
        alert('For phone camera scanning, open this site over HTTPS. You can still type or scan the barcode manually.');
        useManualBarcodeEntry(input, feedback);
        return;
    }

    let Quagga;
    try {
        Quagga = await ensureQuaggaLoaded();
    } catch (error) {
        console.warn(error);
        alert('Camera scanner library could not load. Please enter the barcode or SKU manually.');
        useManualBarcodeEntry(input, feedback);
        resetCameraScannerUI(viewport, btn);
        return;
    }

    if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function') {
        alert('Camera access is not available in this browser. Please use a supported browser or type the SKU manually.');
        useManualBarcodeEntry(input, feedback);
        resetCameraScannerUI(viewport, btn);
        return;
    }

    viewport.classList.remove('hidden');
    btn.textContent = 'Stop Camera Scanner';
    cameraActive = true;

    Quagga.init({
        inputStream: {
            name: 'Live',
            type: 'LiveStream',
            target: viewport,
            constraints: window.matchMedia('(max-width: 768px)').matches ? {
                facingMode: 'environment',
                width: { ideal: 640, max: 960 },
                height: { ideal: 480, max: 720 }
            } : {
                facingMode: 'environment',
                width: { ideal: 1280 },
                height: { ideal: 720 }
            }
        },
        decoder: {
            readers: ['code_128_reader', 'ean_reader', 'ean_8_reader', 'upc_reader', 'upc_e_reader', 'code_39_reader']
        },
        locate: true
    }, function(error) {
        if (error) {
            console.error(error);
            let message = 'Camera initialization failed. Allow camera permission in your browser and try again.';
            if (error.name === 'NotAllowedError') {
                message = 'Camera permission was denied. Allow camera access in your phone browser settings, then try again.';
            } else if (error.name === 'NotFoundError') {
                message = 'No camera was found on this device. You can type or scan the SKU manually.';
            }
            alert(message);
            useManualBarcodeEntry(input, feedback);
            resetCameraScannerUI(viewport, btn);
            return;
        }

        Quagga.start();
    });

    Quagga.onDetected(function(result) {
        const code = result && result.codeResult ? result.codeResult.code : '';
        if (!code) return;
        if (input) input.value = code;
        toggleCameraScanner();
        if (typeof lookupProduct === 'function') lookupProduct();
    });
}

// Reliable page-specific Quagga serial scanning logic with HTTPS, camera permission, form validation, and manual fallback handling
let serialScannerActive = false;
let serialScannerDetected = false;

async function ensureSerialQuaggaLoaded() {
    if (typeof window.Quagga !== 'undefined') return window.Quagga;

    return new Promise(function(resolve, reject) {
        const fallbackScript = document.createElement('script');
        fallbackScript.src = 'https://cdn.jsdelivr.net/npm/quagga@0.12.1/dist/quagga.min.js';
        fallbackScript.async = true;
        fallbackScript.onload = function() {
            typeof window.Quagga !== 'undefined'
                ? resolve(window.Quagga)
                : reject(new Error('QuaggaJS did not initialize'));
        };
        fallbackScript.onerror = function() {
            reject(new Error('Unable to load QuaggaJS'));
        };
        document.head.appendChild(fallbackScript);
    });
}

function setSerialScannerFeedback(message, isError) {
    const feedback = document.getElementById('serial-scanner-feedback');
    if (!feedback) return;
    feedback.textContent = message;
    feedback.className = 'mt-2 text-xs font-medium ' + (isError ? 'text-red-600' : 'text-emerald-700');
}

function resetSerialScannerUI() {
    const viewport = document.getElementById('serial-scanner-viewport');
    const button = document.getElementById('serial-scanner-btn');
    serialScannerActive = false;
    serialScannerDetected = false;
    if (viewport) {
        viewport.classList.add('hidden');
        viewport.classList.remove('flex');
    }
    if (button) {
        button.innerHTML = '<span aria-hidden="true">📷</span><span>Scan Serial Code</span>';
        button.setAttribute('aria-expanded', 'false');
    }
}

async function toggleSerialScanner() {
    const viewport = document.getElementById('serial-scanner-viewport');
    const button = document.getElementById('serial-scanner-btn');
    const input = document.getElementById('serial-number-input');
    if (!viewport || !button || !input) return;

    if (serialScannerActive) {
        if (typeof window.Quagga !== 'undefined') {
            try { window.Quagga.stop(); } catch (error) { console.warn(error); }
        }
        resetSerialScannerUI();
        setSerialScannerFeedback('Serial scanner stopped.', false);
        return;
    }

    const isLocal = location.hostname === 'localhost' || location.hostname === '127.0.0.1';
    if (location.protocol !== 'https:' && !isLocal) {
        setSerialScannerFeedback('Camera scanning requires HTTPS. Type or use a handheld scanner instead.', true);
        input.focus();
        return;
    }

    let Quagga;
    try {
        Quagga = await ensureSerialQuaggaLoaded();
    } catch (error) {
        console.warn(error);
        setSerialScannerFeedback('Scanner library could not load. Type or use a handheld scanner instead.', true);
        input.focus();
        return;
    }

    if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function') {
        setSerialScannerFeedback('Camera access is unavailable in this browser. Type or use a handheld scanner instead.', true);
        input.focus();
        return;
    }

    viewport.classList.remove('hidden');
    viewport.classList.add('flex');
    button.innerHTML = '<span aria-hidden="true">■</span><span>Stop Scanner</span>';
    button.setAttribute('aria-expanded', 'true');
    serialScannerActive = true;
    serialScannerDetected = false;
    setSerialScannerFeedback('Requesting camera permission...', false);

    Quagga.init({
        inputStream: {
            name: 'SerialLive',
            type: 'LiveStream',
            target: viewport,
            constraints: window.matchMedia('(max-width: 768px)').matches ? {
                facingMode: 'environment',
                width: { ideal: 640, max: 960 },
                height: { ideal: 480, max: 720 }
            } : {
                facingMode: 'environment',
                width: { ideal: 1280 },
                height: { ideal: 720 }
            }
        },
        locator: { patchSize: 'medium', halfSample: true },
        decoder: {
            readers: ['code_128_reader', 'code_39_reader', 'ean_reader', 'ean_8_reader', 'upc_reader', 'upc_e_reader']
        },
        locate: true
    }, function(error) {
        if (error) {
            console.error(error);
            const denied = error.name === 'NotAllowedError' || /permission/i.test(error.message || '');
            setSerialScannerFeedback(denied
                ? 'Camera permission denied. Allow it in browser settings, then try again.'
                : 'Could not start the camera. Check device permissions and try again.', true);
            try { Quagga.stop(); } catch (stopError) { console.warn(stopError); }
            resetSerialScannerUI();
            input.focus();
            return;
        }

        setSerialScannerFeedback('Camera ready. Hold the barcode inside the frame.', false);
        Quagga.start();
    });

    Quagga.onDetected(function(result) {
        if (!serialScannerActive || serialScannerDetected) return;
        const code = result && result.codeResult ? String(result.codeResult.code || '').trim() : '';
        if (!code) return;

        serialScannerDetected = true;
        input.value = code;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
        input.setCustomValidity('');
        input.reportValidity();
        setSerialScannerFeedback('Serial detected: ' + code, false);

        try { Quagga.stop(); } catch (error) { console.warn(error); }
        resetSerialScannerUI();
        input.focus();
    });
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
    document.getElementById('cart-disdiscount').textContent = '-UGX ' + discount.toLocaleString();
    document.getElementById('cart-total').textContent = 'UGX ' + total.toLocaleString();
}

// Prepare JSON payload for checkout form submission
function prepareCheckout() {
    document.getElementById('cart-items-json').value = JSON.stringify(activeCart);
}

// Step 1 (Initial load): Password fields begin hidden with the normal eye icon and no slash.
function initPasswordEyeIcons() {
    document.querySelectorAll('.eye-icon').forEach(function (iconElement) {
        iconElement.classList.remove('slashed', 'eye-revealed');
        iconElement.setAttribute('aria-label', 'Show password');

        if (iconElement.parentElement) {
            iconElement.parentElement.classList.remove('has-slash');
        }
    });
}
initPasswordEyeIcons();
window.addEventListener('DOMContentLoaded', initPasswordEyeIcons);

let barcodeDetectorLoop = null;

async function startBarcodeDetectorFallback(viewport, input, feedback, onDetected) {
    if (!('BarcodeDetector' in window)) return false;

    try {
        const detector = new window.BarcodeDetector({
            formats: ['qr_code', 'code_128', 'code_39', 'ean_13', 'ean_8', 'upc_a', 'upc_e', 'codabar', 'itf']
        });

        const detectLoop = async function() {
            if (!cameraActive && !serialScannerActive) return;
            const video = viewport ? viewport.querySelector('video') : null;
            if (!video || video.readyState < 2) {
                barcodeDetectorLoop = window.requestAnimationFrame(detectLoop);
                return;
            }

            try {
                const codes = await detector.detect(video);
                if (codes && codes.length) {
                    const value = String(codes[0].rawValue || '').trim();
                    if (value) {
                        if (input) {
                            input.value = value;
                            input.dispatchEvent(new Event('input', { bubbles: true }));
                            input.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                        if (feedback) feedback.textContent = 'Code detected: ' + value;
                        if (typeof onDetected === 'function') onDetected(value);
                        return;
                    }
                }
            } catch (error) {
                console.warn('BarcodeDetector fallback failed:', error);
            }

            barcodeDetectorLoop = window.requestAnimationFrame(detectLoop);
        };

        barcodeDetectorLoop = window.requestAnimationFrame(detectLoop);
        return true;
    } catch (error) {
        console.warn('BarcodeDetector unavailable:', error);
        return false;
    }
}

function stopBarcodeDetectorFallback() {
    if (barcodeDetectorLoop) {
        window.cancelAnimationFrame(barcodeDetectorLoop);
        barcodeDetectorLoop = null;
    }
}