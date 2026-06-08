<?php
// api/upload.php
header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Method not allowed.");
    }
    
    if (!isset($_FILES['file'])) {
        throw new Exception("No file uploaded.");
    }
    
    $file = $_FILES['file'];
    
    // Check for PHP upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new Exception("File exceeds maximum allowed size.");
            case UPLOAD_ERR_PARTIAL:
                throw new Exception("File was only partially uploaded.");
            case UPLOAD_ERR_NO_FILE:
                throw new Exception("No file was uploaded.");
            default:
                throw new Exception("Unknown upload error.");
        }
    }
    
    // Check file size (5MB limit)
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception("File exceeds 5MB size limit.");
    }
    
    // Verify it is an image
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif'
    ];
    
    if (!array_key_exists($mimeType, $allowedTypes)) {
        throw new Exception("Invalid file type. Only JPG, PNG, WEBP, and GIF are allowed.");
    }
    
    $ext = $allowedTypes[$mimeType];
    
    // Ensure target folder exists
    $uploadDir = __DIR__ . '/uploads';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Create unique filename
    $filename = uniqid('prod_', true) . '.' . $ext;
    $targetPath = $uploadDir . '/' . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception("Failed to save uploaded file.");
    }
    
    // Return relative path from root
    $relativePath = 'api/uploads/' . $filename;
    
    echo json_encode([
        'success' => true,
        'message' => 'Image uploaded successfully.',
        'image_url' => $relativePath
    ]);
    exit;
    
} catch (Exception $e) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}
