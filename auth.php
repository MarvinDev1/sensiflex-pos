<?php
// auth.php - Processes Login, Registration, & Password Resets securely with Role-Based Access Control
session_start();
require_once 'db.php';

// Strict Regex Patterns matching your setup standards
$email_regex    = '/^[\w\-\.]+@([\w\-]+\.)+[\w\-]{2,4}$/';
$password_regex = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- 1. LOGIN HANDLER & ROLE-BASED ROUTING ---
    if (isset($_POST['login_action'])) {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        // Validate empty credentials
        if (empty($email) || empty($password)) {
            header("Location: index.php?error=l_empty_fields#login");
            exit();
        }

        // Validate email format against regex
        if (!preg_match($email_regex, $email)) {
            header("Location: index.php?error=l_invalid_format#login");
            exit();
        }

        try {
            // Query user record securely using PDO prepared statement
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();

            // Verify password hash and authenticate user session
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id']  = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role']     = $user['role'];
                
                // Regenerate session ID to prevent session fixation attacks
                session_regenerate_id(true);

                // Role-Based Access Control (RBAC) Routing:
                // Admins are routed to the administrative control center (dashboard.php),
                // while staff/cashiers are routed to the dedicated POS cashier terminal (staff_dashboard.php).
                if ($user['role'] === 'admin') {
                    header("Location: dashboard.php");
                } else {
                    header("Location: staff_dashboard.php");
                }
                exit();
            } else {
                header("Location: index.php?error=l_invalid_credentials#login");
                exit();
            }
        } catch (PDOException $e) {
            header("Location: index.php?error=database_error#login");
            exit();
        }
    }

    // --- 2. REGISTRATION HANDLER ---
    if (isset($_POST['register_action'])) {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';

        // Validate required form fields
        if (empty($username) || empty($email) || empty($password)) {
            header("Location: index.php?error=r_empty_fields#register");
            exit();
        }

        // Validate email format
        if (!preg_match($email_regex, $email)) {
            header("Location: index.php?error=r_invalid_email#register");
            exit();
        }

        // Validate password strength against strict regex policy
        if (!preg_match($password_regex, $password)) {
            header("Location: index.php?error=r_weak_password#register");
            exit();
        }

        try {
            // Check if email or username already exists in database
            $chk = $pdo->prepare("SELECT id FROM users WHERE email = :email OR username = :username");
            $chk->execute([':email' => $email, ':username' => $username]);
            if ($chk->fetch()) {
                header("Location: index.php?error=r_user_exists#register");
                exit();
            }

            // Secure Bcrypt Password Hashing with high security cost factor (Cost Factor 17)
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 17]);

            // Force all self-registered accounts to the cashier/staff role.
            // Only the installation seed step may create the master admin account.
            $ins = $pdo->prepare("
                INSERT INTO users (username, email, phone, password, role) 
                VALUES (:username, :email, :phone, :password, 'staff')
            ");
            $ins->execute([
                ':username' => $username,
                ':email'    => $email,
                ':phone'    => $phone,
                ':password' => $hashedPassword
            ]);

            // Redirect the registration success response to the login card so the success banner appears cleanly on the login form
            header("Location: index.php?success=registered#login");
            exit();

        } catch (PDOException $e) {
            header("Location: index.php?error=database_error#register");
            exit();
        }
    }

    // --- 3. FORGOT PASSWORD HANDLER (DIRECT RESET FOR LOCAL DEV) ---
    if (isset($_POST['forgot_action'])) {
        $email = trim($_POST['email'] ?? '');

        // Validate email format
        if (empty($email) || !preg_match($email_regex, $email)) {
            header("Location: index.php?error=f_invalid_email#forgot");
            exit();
        }

        try {
            // Verify if email exists in users table
            $chk = $pdo->prepare("SELECT id FROM users WHERE email = :email");
            $chk->execute([':email' => $email]);
            if ($chk->fetch()) {
                // Directly open the reset password screen for this verified email
                header("Location: reset_password.php?email=" . urlencode($email));
                exit();
            } else {
                header("Location: index.php?error=f_not_found#forgot");
                exit();
            }
        } catch (PDOException $e) {
            header("Location: index.php?error=database_error#forgot");
            exit();
        }
    }
}
?>