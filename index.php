<?php
// index.php - Clean Frontend Template. Translates URL parameters from auth.php.
session_start();

$login_message    = '';
$login_type       = '';
$register_message = '';
$register_type    = '';
$forgot_message   = '';
$forgot_type      = '';
$active_form      = 'login';

// Translate URL Error Codes
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'l_empty_fields':
            $login_message = "Please fill in all login fields."; $login_type = 'error'; break;
        case 'l_invalid_format':
            $login_message = "Please enter a valid email address format."; $login_type = 'error'; break;
        case 'l_invalid_credentials':
            $login_message = "Invalid email or password."; $login_type = 'error'; break;
        case 'r_empty_fields':
            $register_message = "All required fields must be filled out."; $register_type = 'error'; break;
        case 'r_invalid_email':
            $register_message = "Please provide a valid registration email."; $register_type = 'error'; break;
        case 'r_weak_password':
            $register_message = "Password must be 8+ chars with uppercase, lowercase, number & special symbol."; $register_type = 'error'; break;
        case 'r_user_exists':
            $register_message = "An account with this email or username already exists."; $register_type = 'error'; break;
        case 'f_invalid_email':
            $forgot_message = "Please provide a valid email address."; $forgot_type = 'error'; break;
        case 'f_not_found':
            $forgot_message = "No account found matching this email."; $forgot_type = 'error'; break;
        case 'database_error':
            $login_message = $register_message = $forgot_message = "A database error occurred. Try again.";
            $login_type = $register_type = $forgot_type = 'error';
            break;
    }

    if (strpos($_GET['error'], 'f_') === 0) {
        $active_form = 'forgot';
    } elseif (strpos($_GET['error'], 'r_') === 0) {
        $active_form = 'register';
    }
}

// Translate URL Success Codes
if (isset($_GET['success'])) {
    if ($_GET['success'] === 'registered') {
        $login_message = "Registration successful! You can now log in.";
        $login_type = 'success';
        $active_form = 'login';
    } elseif ($_GET['success'] === 'reset_sent') {
        $forgot_message = "Password reset instructions sent to your email.";
        $forgot_type = 'success';
        $active_form = 'forgot';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, viewport-fit=cover">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <title>SENSIFLEX - Login & Register</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="max-w-full overflow-x-hidden">
    <div class="hero w-full min-h-screen px-4 sm:px-6 box-border overflow-x-hidden">
        <div class="form-box w-[92%] sm:w-full max-w-md mx-auto p-5 sm:p-8 box-border">
            <div class="logo-container">
                <img src="gallary/logo.png" alt="Sensiflex Logo" class="brand-logo">
            </div>

            <div class="btn-box">
                <div id="btn"></div>
                <button type="button" class="toggle-btn" onclick="clearAuthAlerts(); login();">Log In</button>
                <button type="button" class="toggle-btn" onclick="clearAuthAlerts(); register();">Register</button>
            </div>
            
            <form id="login" class="input-group w-full max-w-full box-border<?php echo $active_form === 'login' ? ' active' : ''; ?>" action="auth.php" method="POST" autocomplete="off">
                <input type="hidden" name="login_action" value="1">
                <div id="login-alert" class="alert-box my-3 w-full box-border <?php echo $login_type === 'success' ? 'success' : ($login_type === 'error' ? 'error' : ''); ?>" style="display: <?php echo !empty($login_message) ? 'block' : 'none'; ?>;">
                <?php echo htmlspecialchars($login_message); ?>
                </div>
                <input type="email" name="email" class="input-field w-full max-w-full min-w-0 box-border" placeholder="Email" required>
                <div class="password-container w-full max-w-full min-w-0 box-border">
                    <input type="password" name="password" class="input-field w-full max-w-full min-w-0 box-border pr-8" id="login-password" placeholder="Password" required>
                    <img src="gallary/eye.png" alt="Toggle Password" class="eye-icon" onclick="togglePassword('login-password', this)">
                </div>
                
                <div class="remember-row flex flex-wrap gap-2 justify-between items-center w-full max-w-full min-w-0 box-border text-xs sm:text-sm">
                    <div class="checkbox-container box-border">
                        <input type="checkbox" class="check-box box-border">
                        <span>Remember Password</span>
                    </div>
                    <a href="#" class="min-w-0" onclick="clearAuthAlerts(); showForgot(event);">Forgot Password?</a>
                </div>
                
                <button type="submit" class="submit-btn w-full max-w-full min-w-0 box-border">Log in</button>
            </form>

            <form id="register" class="input-group w-full max-w-full box-border<?php echo $active_form === 'register' ? ' active' : ''; ?>" action="auth.php" method="POST" autocomplete="off">
                <input type="hidden" name="register_action" value="1">
                <div id="register-alert" class="alert-box my-3 w-full box-border <?php echo $register_type === 'success' ? 'success' : ($register_type === 'error' ? 'error' : ''); ?>" style="display: <?php echo !empty($register_message) ? 'block' : 'none'; ?>;">
                <?php echo htmlspecialchars($register_message); ?>
                </div>
                <input type="text" name="username" class="input-field w-full max-w-full min-w-0 box-border" placeholder="Username" required>
                <input type="email" name="email" class="input-field w-full max-w-full min-w-0 box-border" placeholder="Email Address" required>
                <input type="tel" name="phone" class="input-field w-full max-w-full min-w-0 box-border" placeholder="Phone Number" required>

                <div class="password-container w-full max-w-full min-w-0 box-border">
                    <input type="password" name="password" class="input-field w-full max-w-full min-w-0 box-border pr-8" id="register-password" placeholder="Create Password" required>
                    <img src="gallary/eye.png" alt="Toggle Password" class="eye-icon" onclick="togglePassword('register-password', this)">
                </div>

                <div class="remember-row flex flex-wrap gap-2 justify-between items-center w-full max-w-full min-w-0 box-border text-xs sm:text-sm">
                    <div class="checkbox-container box-border">
                        <input type="checkbox" class="check-box box-border" required>
                        <span>I agree to security terms</span>
                    </div>
                </div>

                <button type="submit" class="submit-btn w-full max-w-full min-w-0 box-border">Register</button>
            </form>

            <form id="forgot" class="input-group w-full max-w-full box-border<?php echo $active_form === 'forgot' ? ' active' : ''; ?>" action="auth.php" method="POST" autocomplete="off">
                <input type="hidden" name="forgot_action" value="1">
                <div id="forgot-alert" class="alert-box my-3 w-full box-border <?php echo $forgot_type === 'success' ? 'success' : ($forgot_type === 'error' ? 'error' : ''); ?>" style="display: <?php echo !empty($forgot_message) ? 'block' : 'none'; ?>;">
                    <?php echo htmlspecialchars($forgot_message); ?>
                </div>

                <input type="email" name="email" class="input-field w-full max-w-full min-w-0 box-border" placeholder="Enter your registered email" autocomplete="off" required>

                <div class="remember-row flex flex-wrap gap-2 justify-between items-center w-full max-w-full min-w-0 box-border text-xs sm:text-sm">
                    <a href="#" class="min-w-0 ml-auto" onclick="clearAuthAlerts(); login(); return false;">Back to Log In</a>
                </div>

                <button type="submit" class="submit-btn w-full max-w-full min-w-0 box-border">Reset Password</button>
            </form>
        </div>
    </div>
    <script src="script.js"></script>
</body>
</html>