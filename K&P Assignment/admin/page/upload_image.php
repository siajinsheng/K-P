<?php
header('Content-Type: application/json');

$upload_dir = '../uploads/product_images/';

// Ensure upload directory exists
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

try {
    if (!isset($_FILES['image'])) {
        throw new Exception('No file uploaded');
    }

    $file = $_FILES['image'];
    $filename = $file['name'];
    $tmp_name = $file['tmp_name'];

    // Validate file type
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowed_types)) {
        throw new Exception('Invalid file type');
    }

    // Validate file size (5MB max)
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception('File too large');
    }

    // Move uploaded file
    $destination = $upload_dir . $filename;
    if (move_uploaded_file($tmp_name, $destination)) {
        echo json_encode([
            'success' => true, 
            'filename' => $filename
        ]);
    } else {
        throw new Exception('Failed to move uploaded file');
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}