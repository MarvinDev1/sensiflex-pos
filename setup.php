<?php
// setup.php - SENSIFLEX One-Time Provisioning Installer

define('LOCK_FILE', __DIR__ . '/installed.lock');

// 1. Check if installation lock exists
if (file_exists(LOCK_FILE)) {
    die("
    <div style='font-family: Arial, sans-serif; padding: 30px; background: #121826; color: #f87171; border-radius: 10px; max-width: 500px; margin: 50px auto; text-align: center; border: 1px solid #ef4444;'>
        <h2 style='margin-top:0;'>SECURITY LOCK ACTIVE</h2>
        <p style='color:#e2e8f0;'>SENSIFLEX has already been installed and provisioned on this server.</p>
        <p style='font-size:12px; color:#94a3b8;'>To re-run installer, manually remove <code>installed.lock</code> from the project root directory.</p>
    </div>
    ");
}

$error = '';
$success = '';

// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'sensiflex_db';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // 2. Input Validation & Regex Checks
    if (empty($username) || empty($email) || empty($password)) {
        $error = "All required fields must be filled out.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please provide a valid email address.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $password)) {
        $error = "Password must be at least 8 characters long and include uppercase, lowercase, a number, and a special character.";
    } else {
        try {
            // 3. Connect to Database via PDO
            $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);

            // 4. Check if Admin already exists
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' OR email = :email");
            $checkStmt->execute([':email' => $email]);
            if ($checkStmt->fetch()) {
                $error = "An administrator account or email already exists in the system.";
            } else {
                // 5. Secure Bcrypt Hashing Loop (Cost Factor 17)
                $options = ['cost' => 17];
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT, $options);

                // 6. Insert Master Admin into Unified Table
                $insertStmt = $pdo->prepare("
                    INSERT INTO users (username, email, phone, password, role) 
                    VALUES (:username, :email, :phone, :password, 'admin')
                ");
                
                $insertStmt->execute([
                    ':username' => $username,
                    ':email'    => $email,
                    ':phone'    => $phone,
                    ':password' => $hashedPassword
                ]);

                // 7. Write Installation Lock File
                file_put_contents(LOCK_FILE, "SENSIFLEX installed successfully on " . date('Y-m-d H:i:s'));

                $success = "Master Admin Account Provisioned Successfully! <br><a href='index.php' style='color:#f97316; font-weight:bold;'>Proceed to Login</a>";
            }
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SENSIFLEX - One-Time Installer</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="max-w-full overflow-x-hidden">
    <div class="hero w-full min-h-screen overflow-x-hidden px-4 sm:px-6 box-border">
        <div class="form-box w-[92%] sm:w-full max-w-md mx-auto box-border" style="min-height: auto; padding-bottom: 30px;">
            <div class="logo-container">
                <img src="gallary/logo.png" alt="Sensiflex Logo" class="brand-logo">
            </div>
            
            <h3 style="text-align: center; margin: 15px 0; color: #f97316; font-size: 18px;">Master Admin Installer</h3>

            <?php if (!empty($error)): ?>
                <div class="alert-box error" style="display:block;"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert-box success" style="display:block;"><?php echo $success; ?></div>
            <?php else: ?>
                <form action="setup.php" method="POST" style="position: relative; top: 0; width: 100%;">
                    <input type="text" name="username" class="input-field" placeholder="Admin Username" required>
                    <input type="email" name="email" class="input-field" placeholder="Admin Email" required>
                    <input type="tel" name="phone" class="input-field" placeholder="Phone Number">
                    
                    <div class="password-container">
                        <input type="password" name="password" id="setup-pass" class="input-field" placeholder="Master Password" required>
                        <img src="gallary/eye.png" alt="Toggle Password" class="eye-icon slashed" onclick="togglePassword('setup-pass', this)">
                    </div>

                    <div class="password-container">
                        <input type="password" name="confirm_password" id="setup-confirm" class="input-field" placeholder="Confirm Password" required>
                    </div>

                    <button type="submit" class="submit-btn">Provision System & Lock</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <script src="script.js"></script>
</body>
</html>