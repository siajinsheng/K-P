<?php
require '../../_base.php';
auth('admin', 'staff');

// Define upload directory
$upload_dir = '../../img/';

// Ensure upload directory exists
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Check if image was uploaded
if (isset($_FILES['image']) && !empty($_FILES['image']['name'])) {
    // Get file information
    $file = $_FILES['image'];
    $filename = $file['name'];
    $tmp_name = $file['tmp_name'];
    $file_error = $file['error'];

    // Check for errors
    if ($file_error !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Upload failed with error code: ' . $file_error]);
        exit;
    }

    // Check file type
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $file_type = mime_content_type($tmp_name);

    if (!in_array($file_type, $allowed_types)) {
        echo json_encode(['success' => false, 'message' => 'Only image files are allowed']);
        exit;
    }

    // Move file to destination
    if (move_uploaded_file($tmp_name, $upload_dir . $filename)) {
        echo json_encode(['success' => true, 'filename' => $filename]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No image uploaded']);
}
