<?php
// logout.php - Secure Logout Confirmation & Session Destruction
session_start();

// Security Check: If user is not logged in, redirect straight to login
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Handle actual logout execution when confirmation is confirmed via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_logout'])) {
    // Unset all session variables
    $_SESSION = array();

    // Destroy the session cookie if it exists
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    // Destroy the session entirely
    session_destroy();

    // Redirect back to login page
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SENSIFLEX - Logout Confirmation</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen max-w-full overflow-x-hidden flex items-center justify-center font-sans antialiased px-4 py-5 box-border">

    <!-- Logout Confirmation Dialog Box -->
    <div class="form-box w-[92%] sm:w-full max-w-md mx-auto box-border" style="min-height: 380px; text-align: center; display: flex; flex-direction: column; justify-content: center;">
        
        <!-- Brand Logo Header -->
        <div class="logo-container" style="margin-bottom: 15px;">
            <img src="gallary/logo.png" alt="Sensiflex Logo" class="brand-logo" style="width: 150px; margin: 0 auto;">
        </div>

        <h3 style="font-weight: 800; font-size: 20px; color: #ffffff; margin-bottom: 8px;">Sign Out Confirmation</h3>
        <p style="font-size: 13px; color: #94a3b8; margin-bottom: 25px;">Are you sure you want to log out of the SENSIFLEX Control Center?</p>

        <!-- Logout Form Actions -->
        <form action="logout.php" method="POST" class="w-full max-w-full box-border space-y-3">
            <input type="hidden" name="confirm_logout" value="1">
            
            <button type="submit" class="submit-btn" style="background: linear-gradient(to right, #dc2626, #ef4444); margin-bottom: 10px;">
                Yes, Log Out
            </button>
            
            <a href="dashboard.php" style="display: block; width: 100%; padding: 12px 30px; background: rgba(255, 255, 255, 0.08); border-radius: 30px; color: #ffffff; font-weight: 600; font-size: 14px; text-decoration: none; transition: background 0.2s;">
                Cancel & Return
            </a>
        </form>

    </div>

</body>
</html>