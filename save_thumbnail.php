<?php
/**
 * Save Thumbnail Endpoint
 * Receives cover image data from JavaScript and saves it as a thumbnail
 * Version 2.0 - Uses MD5 hashing for filesystem-safe filenames
 */

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// Get POST data
$bookPath = $_POST['bookPath'] ?? '';
$imageData = $_POST['imageData'] ?? '';

if (empty($bookPath) || empty($imageData)) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

// Validate book path (security)
if (strpos($bookPath, '..') !== false || strpos($bookPath, '/') === 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid book path']);
    exit;
}

// Create covers directory if needed
$coversDir = __DIR__ . '/books/covers';
if (!is_dir($coversDir)) {
    mkdir($coversDir, 0755, true);
}

// Generate thumbnail filename using MD5 hash for filesystem safety
// This eliminates all issues with special characters, spaces, quotes, etc.
$relativePath = str_replace('books/', '', $bookPath);
$safeName = md5($relativePath) . '.jpg';
$thumbnailPath = $coversDir . '/' . $safeName;

// Check if thumbnail already exists
if (file_exists($thumbnailPath)) {
    echo json_encode([
        'success' => true, 
        'message' => 'Thumbnail already exists', 
        'cached' => true,
        'path' => 'books/covers/' . $safeName
    ]);
    exit;
}

// Extract base64 data
if (preg_match('/^data:image\/\w+;base64,(.+)$/', $imageData, $matches)) {
    $imageData = base64_decode($matches[1]);
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid image data format']);
    exit;
}

// Create image from data
$sourceImage = @imagecreatefromstring($imageData);
if (!$sourceImage) {
    echo json_encode(['success' => false, 'error' => 'Cannot create image from data']);
    exit;
}

// Get dimensions
$origWidth = imagesx($sourceImage);
$origHeight = imagesy($sourceImage);

// Calculate thumbnail dimensions (max 400px width)
$maxWidth = 400;
if ($origWidth > $maxWidth) {
    $aspectRatio = $origHeight / $origWidth;
    $newWidth = $maxWidth;
    $newHeight = (int)($maxWidth * $aspectRatio);
} else {
    $newWidth = $origWidth;
    $newHeight = $origHeight;
}

// Create thumbnail
$thumbnail = imagecreatetruecolor($newWidth, $newHeight);
imagealphablending($thumbnail, false);
imagesavealpha($thumbnail, true);

imagecopyresampled(
    $thumbnail, $sourceImage,
    0, 0, 0, 0,
    $newWidth, $newHeight,
    $origWidth, $origHeight
);

// Save as JPEG
$success = imagejpeg($thumbnail, $thumbnailPath, 85);

imagedestroy($sourceImage);
imagedestroy($thumbnail);

if ($success) {
    // Update metadata mapping file
    $metadataPath = $coversDir . '/.metadata.json';
    $metadata = [];
    
    if (file_exists($metadataPath)) {
        $metadata = json_decode(file_get_contents($metadataPath), true) ?: [];
    }
    
    // Store mapping of hash to original path
    $metadata[$safeName] = [
        'original_path' => $relativePath,
        'created' => date('Y-m-d H:i:s'),
        'size' => filesize($thumbnailPath)
    ];
    
    file_put_contents($metadataPath, json_encode($metadata, JSON_PRETTY_PRINT));
    
    $fileSize = round(filesize($thumbnailPath) / 1024, 1);
    echo json_encode([
        'success' => true, 
        'message' => 'Thumbnail saved',
        'size' => $fileSize . ' KB',
        'path' => 'books/covers/' . $safeName,
        'hash' => $safeName
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to save thumbnail']);
}
?>