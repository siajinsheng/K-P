<?php
require '../../_base.php';
auth(0, 1); // Only admin and managers can access

// Get JSON data
$jsonData = file_get_contents('php://input');
$data = json_decode($jsonData, true);

if (isset($data['filename'])) {
    $filename = $data['filename'];
    
    // Security: Validate filename to prevent directory traversal attacks
    if (preg_match('/^[a-zA-Z0-9_.-]+$/', $filename) !== 1) {
        echo json_encode(['success' => false, 'message' => 'Invalid filename format']);
        exit;
    }
    
    $filePath = '../uploads/product_images/' . $filename;

    // Verify the file is within the allowed directory
    $realPath = realpath($filePath);
    $uploadDir = realpath('../uploads/product_images/');
    
    if ($realPath === false || strpos($realPath, $uploadDir) !== 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid file path']);
        exit;
    }

    if (file_exists($filePath)) {
        if (unlink($filePath)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete file']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'File not found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Filename not provided']);
}