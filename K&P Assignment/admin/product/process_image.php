<?php
require '../../_base.php';
auth('admin', 'staff');

// Define allowed operations
$allowed_operations = ['rotate_left', 'rotate_right', 'flip_horizontal', 'flip_vertical'];

// Get JSON data
$jsonData = file_get_contents('php://input');
$data = json_decode($jsonData, true);

// Validate input
if (!isset($data['filename']) || !isset($data['operation']) || !in_array($data['operation'], $allowed_operations)) {
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid request parameters'
    ]);
    exit;
}

$filename = $data['filename'];
$operation = $data['operation'];

// Security: Validate filename to prevent directory traversal attacks
if (preg_match('/^[a-zA-Z0-9_.-]+$/', $filename) !== 1) {
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid filename format'
    ]);
    exit;
}

// Construct file paths
$filePath = '../../img/' . $filename;

// Verify the file is within the allowed directory
$realPath = realpath($filePath);
$uploadDir = realpath('../../img/');

if ($realPath === false || strpos($realPath, $uploadDir) !== 0) {
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid file path'
    ]);
    exit;
}

if (!file_exists($filePath)) {
    echo json_encode([
        'success' => false, 
        'message' => 'File not found'
    ]);
    exit;
}

try {
    // Use SimpleImage for image manipulation
    require_once '../../lib/SimpleImage.php';
    $image = new SimpleImage();
    $image->fromFile($filePath);

    // Apply the requested operation
    switch ($operation) {
        case 'rotate_left':
            $image->rotate(-90);
            break;
        case 'rotate_right':
            $image->rotate(90);
            break;
        case 'flip_horizontal':
            $image->flip('x');
            break;
        case 'flip_vertical':
            $image->flip('y');
            break;
    }

    // Save the processed image
    $image->toFile($filePath, null, 85);

    // Return success response with timestamp to prevent browser caching
    echo json_encode([
        'success' => true, 
        'message' => 'Image processed successfully',
        'updated_url' => $filename . '?t=' . time()
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error processing image: ' . $e->getMessage()
    ]);
}