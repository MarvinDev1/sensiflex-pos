<?php
// export_excel.php - Export SENSIFLEX Inventory Catalog to Excel (.csv)
session_start();
require_once 'db.php';

// Security Check: Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

try {
    // Fetch all inventory items including barcode
    $stmt = $pdo->query("SELECT id, barcode, product_name, category, price, stock_qty FROM products ORDER BY id ASC");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Set headers to force CSV file download for Excel
    $filename = "sensiflex_inventory_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    // Open PHP output stream
    $output = fopen('php://output', 'w');

    // Write CSV Column Headers (Added Barcode column)
    fputcsv($output, ['Product ID', 'Barcode', 'Product Name', 'Category', 'Unit Price (UGX)', 'Stock Quantity']);

    // Write product rows
    foreach ($products as $row) {
        fputcsv($output, [
            $row['id'],
            $row['barcode'],
            $row['product_name'],
            $row['category'],
            $row['price'],
            $row['stock_qty']
        ]);
    }

    fclose($output);
    exit();

} catch (PDOException $e) {
    die("Export Error: " . $e->getMessage());
}