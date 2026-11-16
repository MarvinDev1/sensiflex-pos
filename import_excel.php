<?php
// import_excel.php - Batch Import Products from CSV/Excel into SENSIFLEX
session_start();
require_once 'db.php';

// Security Check: Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Redirect back to the right dashboard based on active role
$redirect_page = ($_SESSION['role'] === 'admin') ? 'inventory.php' : 'staff_dashboard.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file']['tmp_name'];

    if (empty($file)) {
        header("Location: $redirect_page?error=empty_file");
        exit();
    }

    try {
        $handle = fopen($file, "r");
        $row_count = 0;

        $pdo->beginTransaction();

        // Fixed SQL: All 5 database columns matched with 5 correct placeholders
        $stmt = $pdo->prepare("
            INSERT INTO products (barcode, product_name, category, price, stock_qty) 
            VALUES (:barcode, :name, :category, :price, :stock_qty)
        ");

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $row_count++;
            // Skip the header row
            if ($row_count === 1) continue;

            // Correct Column Mapping for 6 columns: [ID, Barcode, Name, Category, Price, Stock]
            if (count($data) >= 6) {
                $id        = trim($data[0]); // Skipped (auto-incremented)
                $barcode   = trim($data[1]);
                $name      = trim($data[2]);
                $category  = trim($data[3]);
                $price     = floatval($data[4]);
                $stock_qty = intval($data[5]);
            } elseif (count($data) === 5) {
                // Fallback if imported from a custom CSV without Product ID
                $barcode   = trim($data[0]);
                $name      = trim($data[1]);
                $category  = trim($data[2]);
                $price     = floatval($data[3]);
                $stock_qty = intval($data[4]);
            } else {
                continue;
            }

            if (!empty($name)) {
                $stmt->execute([
                    ':barcode'   => $barcode,
                    ':name'      => $name,
                    ':category'  => $category ?: 'General Hardware',
                    ':price'     => $price,
                    ':stock_qty' => $stock_qty
                ]);
            }
        }

        fclose($handle);
        $pdo->commit();

        header("Location: $redirect_page?success=import_complete");
        exit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die("Import Failed: " . $e->getMessage());
    }
} else {
    header("Location: $redirect_page");
    exit();
}