<?php
require '../../_base.php';
auth('admin', 'staff');

// Set headers
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="product_upload_template.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// Create output stream
$output = fopen('php://output', 'w');

// Output header row
fputcsv($output, [
    'product_name', 
    'category_id', 
    'product_price', 
    'product_description', 
    'product_status',
    'product_type',
    'stock_S',
    'stock_M',
    'stock_L',
    'stock_XL',
    'stock_XXL'
]);

// Output sample row
fputcsv($output, [
    'Sample T-Shirt',
    'CAT1001', 
    '59.90',
    'This is a sample product description.',
    'Available',
    'Unisex',
    '10',
    '20',
    '15',
    '10',
    '5'
]);

// Output another sample row
fputcsv($output, [
    'Sample Jeans',
    'CAT1003', 
    '99.90',
    'Premium quality jeans.',
    'Available',
    'Man',
    '5',
    '10',
    '15',
    '5',
    '0'
]);

// Close the output stream
fclose($output);
exit;