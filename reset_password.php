<?php
// reset_password.php - Refactored Validation Order & Dynamic Success/Error Alerts
session_start();
require_once 'db.php';

// Initialize response message containers and state trackers
$message      = '';
$message_type = 'error'; // Controls CSS alert theme: 'error' (red) vs 'success' (emerald green)
$should_show_message = false;
$email        = trim($_GET['email'] ?? $_POST['email'] ?? '');

// Message should only appear after a user submits the reset form.
// Avoid showing stale success/error states on a normal page load.
// Comprehensive Regex Policy: Minimum 8 chars, 1 uppercase, 1 lowercase, 1 number, and 1 allowed special symbol
$password_regex = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&#._])[A-Za-z\d@$!%*?&#._]{8,}$/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $should_show_message = true;
    $email            = trim($_POST['email'] ?? '');
    $new_password     = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // STEP 1: Empty Fields Check
    if (empty($email) || empty($new_password) || empty($confirm_password)) {
        $message      = "Error: Please fill in all required password fields.";
        $message_type = 'error';
    }
    // STEP 2: Confirm Password Match Check
    elseif ($new_password !== $confirm_password) {
        $message      = "Error: Passwords do not match. Please ensure both fields match identically.";
        $message_type = 'error';
    }
    // STEP 3: Regex Format Check
    elseif (!preg_match($password_regex, $new_password)) {
        $message      = "Error: Password must be 8+ chars with uppercase, lowercase, number & special symbol (@$!%*?&#._).";
        $message_type = 'error';
    }
    // STEP 4: Strong Password Policy Check
    elseif (strtolower($new_password) === 'password123!' || (strpos($email, '@') !== false && stripos($new_password, explode('@', $email)[0]) !== false)) {
        $message      = "Error: Password is too predictable or contains your email name. Please create a stronger password.";
        $message_type = 'error';
    }
    // STEP 5: Success State & Database Update
    else {
        try {
            $chk = $pdo->prepare("SELECT id FROM users WHERE email = :email");
            $chk->execute([':email' => $email]);
            $user = $chk->fetch();

            if ($user) {
                $hashedPassword = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 17]);
                $upd = $pdo->prepare("UPDATE users SET password = :password WHERE email = :email");
                $upd->execute([':password' => $hashedPassword, ':email' => $email]);

                $message      = "Success: Password updated successfully! Redirecting to login...";
                $message_type = 'success';

                header("Refresh:2; url=index.php?success=registered#login");
                exit();
            } else {
                $message      = "Error: No user account found matching this email address.";
                $message_type = 'error';
            }
        } catch (PDOException $e) {
            $message      = "Database Error: Failed to process password update. Please try again.";
            $message_type = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SENSIFLEX - Reset Password</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body>
    <!-- Mobile layout: hero padding keeps reset cards off physical screen edges. -->
    <div class="hero w-full min-h-screen overflow-x-hidden px-4 sm:px-6 box-border">
        <div class="form-box w-[92%] sm:w-full max-w-md mx-auto box-border" style="min-height: 0;">
            <!-- Brand Logo Header -->
            <div class="logo-container">
                <img src="gallary/logo.png" alt="Sensiflex Logo" class="brand-logo">
            </div>

            <h3 style="text-align: center; margin: 15px 0 5px 0; font-weight: 700; color: #f97316;">Set New Password</h3>
            <p style="text-align: center; font-size: 12px; color: #94a3b8; margin-bottom: 15px;">Create a strong password for your account</p>

            <!-- DYNAMIC ALERT CONTAINER: Shows only after the reset submit button is actually clicked -->
            <?php if ($should_show_message && !empty($message)): ?>
                <div class="alert-box <?php echo $message_type === 'success' ? 'success' : 'error'; ?>" style="display: block; margin-bottom: 15px;">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form action="reset_password.php" method="POST" class="w-full max-w-full min-w-0 space-y-4">
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">

                <!-- New Password Field with Eye Toggle Icon -->
                <div class="password-container w-full max-w-full">
                    <input type="password" name="new_password" class="input-field w-full max-w-full min-w-0 box-border pr-8" id="new-password" placeholder="New Password" required>
                    <img src="gallary/eye.png" alt="Toggle Password" class="eye-icon slashed" onclick="togglePassword('new-password', this)">
                </div>

                <!-- Confirm Password Field with Eye Toggle Icon -->
                <div class="password-container w-full max-w-full">
                    <input type="password" name="confirm_password" class="input-field w-full max-w-full min-w-0 box-border pr-8" id="confirm-password" placeholder="Confirm New Password" required>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="submit-btn w-full max-w-full min-w-0" style="margin-top: 20px;">Update Password &amp; Log In</button>
            </form>

            <div class="remember-row w-full max-w-full" style="justify-content: center; margin-top: 20px;">
                <a href="index.php">← Cancel and Back to Log In</a>
            </div>
        </div>
    </div>
    <script src="script.js"></script>
</body>
</html>