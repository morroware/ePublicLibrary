<?php
/**
 * BookShelf - Enhanced Thumbnail Generator
 * Version 3.0 - Fixes for URL encoding, SVG covers, and path matching
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
set_time_limit(600); // 10 minutes for large libraries

// Configuration
$booksDir = __DIR__ . '/books';
$coversDir = __DIR__ . '/books/covers';
$thumbnailWidth = 400;
$thumbnailQuality = 85;

// Check for Imagick (needed for SVG support)
$hasImagick = extension_loaded('imagick');
echo "Imagick extension: " . ($hasImagick ? "✓ Available" : "✗ Not available (SVG covers will be skipped)") . "\n";

// Create covers directory
if (!file_exists($coversDir)) {
    mkdir($coversDir, 0755, true);
    echo "Created covers directory: $coversDir\n";
}

// Create failed log
$failedLog = [];

/**
 * Get all EPUB files recursively
 */
function getEpubFiles($dir) {
    $epubs = [];
    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'epub') {
                $relativePath = str_replace($dir . '/', '', $file->getPathname());
                $epubs[] = [
                    'path' => $file->getPathname(),
                    'relative' => $relativePath
                ];
            }
        }
    } catch (Exception $e) {
        echo "Error scanning directory: " . $e->getMessage() . "\n";
    }
    return $epubs;
}

/**
 * Generate thumbnail filename using MD5 hash
 */
function getThumbnailPath($bookRelativePath, $coversDir) {
    $safeName = md5($bookRelativePath) . '.jpg';
    return $coversDir . '/' . $safeName;
}

/**
 * Build a lookup map of all files in the ZIP (case-insensitive)
 */
function buildFileLookup($zip) {
    $lookup = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $filename = $zip->getNameIndex($i);
        // Store both the lowercase version (for lookup) and actual name
        $lookup[strtolower($filename)] = $filename;
        
        // Also store URL-decoded version
        $decoded = urldecode($filename);
        if ($decoded !== $filename) {
            $lookup[strtolower($decoded)] = $filename;
        }
    }
    return $lookup;
}

/**
 * Find a file in the ZIP with case-insensitive and URL-decoded matching
 */
function findFileInZip($zip, $targetPath, $fileLookup) {
    // Normalize the path
    $targetPath = normalizePath($targetPath);
    
    // Try exact match first
    if ($zip->locateName($targetPath) !== false) {
        return $targetPath;
    }
    
    // Try URL-decoded version
    $decoded = urldecode($targetPath);
    if ($decoded !== $targetPath && $zip->locateName($decoded) !== false) {
        return $decoded;
    }
    
    // Try case-insensitive lookup
    $lower = strtolower($targetPath);
    if (isset($fileLookup[$lower])) {
        return $fileLookup[$lower];
    }
    
    // Try URL-decoded + case-insensitive
    $decodedLower = strtolower($decoded);
    if (isset($fileLookup[$decodedLower])) {
        return $fileLookup[$decodedLower];
    }
    
    return null;
}

/**
 * Extract cover from EPUB with multiple fallback methods
 */
function extractCoverFromEpub($epubPath) {
    global $hasImagick;
    
    $zip = new ZipArchive();
    
    if ($zip->open($epubPath) !== TRUE) {
        return ['success' => false, 'error' => 'Cannot open ZIP'];
    }

    // Build file lookup for case-insensitive matching
    $fileLookup = buildFileLookup($zip);
    
    // Method 1: Find OPF and look for cover metadata
    $coverResult = findCoverViaOPF($zip, $fileLookup);
    
    // Method 2: Look for common cover filenames
    if (!$coverResult) {
        $coverResult = findCoverByFilename($zip, $fileLookup);
    }
    
    // Method 3: Find first image in EPUB
    if (!$coverResult) {
        $coverResult = findFirstImage($zip);
    }
    
    if (!$coverResult) {
        $zip->close();
        return ['success' => false, 'error' => 'No cover found'];
    }

    $coverPath = $coverResult['path'];
    $isSvg = $coverResult['svg'] ?? false;
    
    // Get the image data
    $imageData = $zip->getFromName($coverPath);
    $zip->close();
    
    if (!$imageData) {
        return ['success' => false, 'error' => 'Cannot read image data from: ' . $coverPath];
    }

    // Handle SVG conversion
    if ($isSvg) {
        if (!$hasImagick) {
            return ['success' => false, 'error' => 'SVG cover requires Imagick extension'];
        }
        
        $imageData = convertSvgToPng($imageData);
        if (!$imageData) {
            return ['success' => false, 'error' => 'Failed to convert SVG'];
        }
    }

    return ['success' => true, 'data' => $imageData];
}

/**
 * Convert SVG data to PNG using Imagick
 */
function convertSvgToPng($svgData) {
    try {
        $imagick = new Imagick();
        $imagick->setBackgroundColor(new ImagickPixel('white'));
        $imagick->readImageBlob($svgData);
        $imagick->setImageFormat('png');
        
        // Ensure reasonable size
        $width = $imagick->getImageWidth();
        $height = $imagick->getImageHeight();
        
        if ($width < 100 || $height < 100) {
            // SVG might need scaling
            $imagick->resizeImage(400, 600, Imagick::FILTER_LANCZOS, 1, true);
        }
        
        $pngData = $imagick->getImageBlob();
        $imagick->destroy();
        
        return $pngData;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Method 1: Find cover via OPF metadata
 */
function findCoverViaOPF($zip, $fileLookup) {
    // Read container.xml
    $content = $zip->getFromName('META-INF/container.xml');
    if (!$content) {
        return null;
    }

    // Find OPF path
    if (!preg_match('/<rootfile[^>]+full-path=["\']([^"\']+)["\']/i', $content, $matches)) {
        return null;
    }

    $opfPath = $matches[1];
    $opfDir = dirname($opfPath);
    if ($opfDir === '.') {
        $opfDir = '';
    }
    
    $opfContent = $zip->getFromName($opfPath);
    
    if (!$opfContent) {
        return null;
    }

    // Parse all manifest items into an array for easier lookup
    $manifestItems = [];
    preg_match_all('/<item\s+([^>]+)>/i', $opfContent, $itemMatches);
    
    foreach ($itemMatches[1] as $attrs) {
        $item = [];
        
        if (preg_match('/id=["\']([^"\']+)["\']/i', $attrs, $m)) {
            $item['id'] = $m[1];
        }
        if (preg_match('/href=["\']([^"\']+)["\']/i', $attrs, $m)) {
            $item['href'] = $m[1];
        }
        if (preg_match('/media-type=["\']([^"\']+)["\']/i', $attrs, $m)) {
            $item['media-type'] = $m[1];
        }
        if (preg_match('/properties=["\']([^"\']+)["\']/i', $attrs, $m)) {
            $item['properties'] = $m[1];
        }
        
        if (!empty($item['id'])) {
            $manifestItems[$item['id']] = $item;
        }
    }
    
    // Strategy 1: meta name="cover" content="<id>"
    if (preg_match('/<meta\s+[^>]*name=["\']cover["\'][^>]*content=["\']([^"\']+)["\']/i', $opfContent, $matches) ||
        preg_match('/<meta\s+[^>]*content=["\']([^"\']+)["\'][^>]*name=["\']cover["\']/i', $opfContent, $matches)) {
        
        $coverId = $matches[1];
        
        if (isset($manifestItems[$coverId])) {
            $href = $manifestItems[$coverId]['href'];
            $fullPath = buildFullPath($opfDir, $href);
            $actualPath = findFileInZip($zip, $fullPath, $fileLookup);
            
            if ($actualPath) {
                $isSvg = (stripos($actualPath, '.svg') !== false) || 
                         (isset($manifestItems[$coverId]['media-type']) && 
                          stripos($manifestItems[$coverId]['media-type'], 'svg') !== false);
                return ['path' => $actualPath, 'svg' => $isSvg];
            }
        }
    }
    
    // Strategy 2: properties="cover-image"
    foreach ($manifestItems as $item) {
        if (isset($item['properties']) && stripos($item['properties'], 'cover-image') !== false) {
            $fullPath = buildFullPath($opfDir, $item['href']);
            $actualPath = findFileInZip($zip, $fullPath, $fileLookup);
            
            if ($actualPath) {
                $isSvg = (stripos($actualPath, '.svg') !== false) || 
                         (isset($item['media-type']) && stripos($item['media-type'], 'svg') !== false);
                return ['path' => $actualPath, 'svg' => $isSvg];
            }
        }
    }
    
    // Strategy 3: ID contains "cover"
    foreach ($manifestItems as $id => $item) {
        if (stripos($id, 'cover') !== false && isset($item['media-type']) && 
            stripos($item['media-type'], 'image') !== false) {
            
            $fullPath = buildFullPath($opfDir, $item['href']);
            $actualPath = findFileInZip($zip, $fullPath, $fileLookup);
            
            if ($actualPath) {
                $isSvg = stripos($item['media-type'], 'svg') !== false;
                return ['path' => $actualPath, 'svg' => $isSvg];
            }
        }
    }
    
    // Strategy 4: href contains "cover"
    foreach ($manifestItems as $item) {
        if (isset($item['href']) && stripos($item['href'], 'cover') !== false &&
            isset($item['media-type']) && stripos($item['media-type'], 'image') !== false) {
            
            $fullPath = buildFullPath($opfDir, $item['href']);
            $actualPath = findFileInZip($zip, $fullPath, $fileLookup);
            
            if ($actualPath) {
                $isSvg = stripos($item['media-type'], 'svg') !== false;
                return ['path' => $actualPath, 'svg' => $isSvg];
            }
        }
    }
    
    // Strategy 5: First image item (prefer non-SVG)
    $firstImage = null;
    $firstSvg = null;
    
    foreach ($manifestItems as $item) {
        if (isset($item['media-type']) && stripos($item['media-type'], 'image') !== false) {
            $fullPath = buildFullPath($opfDir, $item['href']);
            $actualPath = findFileInZip($zip, $fullPath, $fileLookup);
            
            if ($actualPath) {
                $isSvg = stripos($item['media-type'], 'svg') !== false;
                
                if (!$isSvg && !$firstImage) {
                    $firstImage = ['path' => $actualPath, 'svg' => false];
                } elseif ($isSvg && !$firstSvg) {
                    $firstSvg = ['path' => $actualPath, 'svg' => true];
                }
            }
        }
    }
    
    return $firstImage ?? $firstSvg;
}

/**
 * Build full path from OPF directory and relative href
 */
function buildFullPath($opfDir, $href) {
    // URL decode the href
    $href = urldecode($href);
    
    if (empty($opfDir)) {
        return $href;
    }
    
    return $opfDir . '/' . $href;
}

/**
 * Method 2: Find cover by common filenames
 */
function findCoverByFilename($zip, $fileLookup) {
    $commonPatterns = [
        'cover.jpg', 'cover.jpeg', 'cover.png', 'cover.gif', 'cover.svg',
        'Cover.jpg', 'Cover.jpeg', 'Cover.png', 'Cover.gif', 'Cover.svg',
        'COVER.jpg', 'COVER.jpeg', 'COVER.png', 'COVER.gif', 'COVER.svg',
        'cover-image.jpg', 'cover-image.jpeg', 'cover-image.png', 'cover-image.svg',
        'titlepage.jpg', 'titlepage.jpeg', 'titlepage.png', 'titlepage.svg'
    ];

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $filename = $zip->getNameIndex($i);
        $basename = basename($filename);
        
        // Check exact match with common names
        if (in_array($basename, $commonPatterns)) {
            $isSvg = stripos($basename, '.svg') !== false;
            return ['path' => $filename, 'svg' => $isSvg];
        }
        
        // Check if filename contains "cover" anywhere in path
        if (stripos($filename, 'cover') !== false && 
            preg_match('/\.(jpg|jpeg|png|gif|svg)$/i', $filename)) {
            $isSvg = stripos($filename, '.svg') !== false;
            return ['path' => $filename, 'svg' => $isSvg];
        }
    }

    return null;
}

/**
 * Method 3: Find first image in EPUB
 */
function findFirstImage($zip) {
    $firstImage = null;
    $firstSvg = null;
    
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $filename = $zip->getNameIndex($i);
        
        if (preg_match('/\.(jpg|jpeg|png|gif)$/i', $filename)) {
            // Skip very small files (likely icons)
            $stat = $zip->statIndex($i);
            if ($stat['size'] > 5000 && !$firstImage) { // At least 5KB
                $firstImage = ['path' => $filename, 'svg' => false];
            }
        } elseif (preg_match('/\.svg$/i', $filename)) {
            $stat = $zip->statIndex($i);
            if ($stat['size'] > 1000 && !$firstSvg) { // At least 1KB for SVG
                $firstSvg = ['path' => $filename, 'svg' => true];
            }
        }
    }

    return $firstImage ?? $firstSvg;
}

/**
 * Normalize path (remove ./ and ../)
 */
function normalizePath($path) {
    $path = str_replace('\\', '/', $path);
    
    // Remove leading ./
    $path = preg_replace('#^\./#', '', $path);
    
    // Remove /./
    $path = preg_replace('#/\./#', '/', $path);
    
    // Handle ../
    while (preg_match('#/[^/]+/\.\./#', $path)) {
        $path = preg_replace('#/[^/]+/\.\./#', '/', $path);
    }
    
    // Remove trailing /..
    $path = preg_replace('#/[^/]+/\.\.$#', '', $path);
    
    return $path;
}

/**
 * Create thumbnail from image data
 */
function createThumbnail($imageData, $thumbnailPath, $width, $quality) {
    $sourceImage = @imagecreatefromstring($imageData);
    if (!$sourceImage) {
        return false;
    }

    $origWidth = imagesx($sourceImage);
    $origHeight = imagesy($sourceImage);

    // Calculate new dimensions
    $aspectRatio = $origHeight / $origWidth;
    $newWidth = $width;
    $newHeight = (int)($width * $aspectRatio);

    // Create thumbnail
    $thumbnail = imagecreatetruecolor($newWidth, $newHeight);
    
    // Fill with white background (for transparent images)
    $white = imagecolorallocate($thumbnail, 255, 255, 255);
    imagefill($thumbnail, 0, 0, $white);
    
    // Preserve transparency for PNG
    imagealphablending($thumbnail, true);
    imagesavealpha($thumbnail, true);
    
    // Resize with high quality
    imagecopyresampled(
        $thumbnail, $sourceImage,
        0, 0, 0, 0,
        $newWidth, $newHeight,
        $origWidth, $origHeight
    );

    // Save as JPEG
    $result = imagejpeg($thumbnail, $thumbnailPath, $quality);

    imagedestroy($sourceImage);
    imagedestroy($thumbnail);

    return $result;
}

/**
 * Generate thumbnail for a book
 */
function generateThumbnail($epubPath, $thumbnailPath, $width, $quality) {
    $extractResult = extractCoverFromEpub($epubPath);
    
    if (!$extractResult['success']) {
        return ['success' => false, 'error' => $extractResult['error']];
    }

    if (createThumbnail($extractResult['data'], $thumbnailPath, $width, $quality)) {
        return ['success' => true];
    }

    return ['success' => false, 'error' => 'Failed to create thumbnail'];
}

/**
 * Update metadata mapping file
 */
function updateMetadataMapping($coversDir, $safeName, $relativePath, $size) {
    $metadataPath = $coversDir . '/.metadata.json';
    $metadata = [];
    
    if (file_exists($metadataPath)) {
        $metadata = json_decode(file_get_contents($metadataPath), true) ?: [];
    }
    
    // Store mapping of hash to original path
    $metadata[$safeName] = [
        'original_path' => $relativePath,
        'created' => date('Y-m-d H:i:s'),
        'size' => $size
    ];
    
    file_put_contents($metadataPath, json_encode($metadata, JSON_PRETTY_PRINT));
}

// Main execution
echo "=== BookShelf Enhanced Thumbnail Generator v3.0 ===\n\n";
echo "Scanning for EPUB files...\n";

$epubs = getEpubFiles($booksDir);
$totalBooks = count($epubs);

echo "Found $totalBooks EPUB files\n\n";

if ($totalBooks === 0) {
    echo "No EPUB files found in $booksDir\n";
    exit;
}

$generated = 0;
$skipped = 0;
$failed = 0;

foreach ($epubs as $index => $epub) {
    $num = $index + 1;
    $thumbnailPath = getThumbnailPath($epub['relative'], $coversDir);
    $safeName = basename($thumbnailPath);
    
    // Skip if thumbnail already exists
    if (file_exists($thumbnailPath)) {
        echo "[$num/$totalBooks] ⏭️  Skipped (exists): {$epub['relative']}\n";
        $skipped++;
        continue;
    }

    echo "[$num/$totalBooks] 🔄 Processing: {$epub['relative']}\n";
    
    $result = generateThumbnail($epub['path'], $thumbnailPath, $thumbnailWidth, $thumbnailQuality);
    
    if ($result['success']) {
        $fileSize = round(filesize($thumbnailPath) / 1024, 1);
        
        // Update metadata mapping
        updateMetadataMapping($coversDir, $safeName, $epub['relative'], filesize($thumbnailPath));
        
        echo "[$num/$totalBooks] ✅ Generated ($fileSize KB): {$epub['relative']}\n";
        $generated++;
    } else {
        echo "[$num/$totalBooks] ❌ Failed ({$result['error']}): {$epub['relative']}\n";
        $failedLog[] = [
            'file' => $epub['relative'],
            'error' => $result['error']
        ];
        $failed++;
    }
}

echo "\n=== Summary ===\n";
echo "Total books: $totalBooks\n";
echo "Generated: $generated\n";
echo "Skipped: $skipped\n";
echo "Failed: $failed\n";

if ($failed > 0) {
    echo "\n=== Failed Books ===\n";
    $failedLogPath = $coversDir . '/failed.log';
    $logContent = "Failed Thumbnail Generation Log\n";
    $logContent .= "Generated: " . date('Y-m-d H:i:s') . "\n\n";
    
    foreach ($failedLog as $item) {
        $logContent .= "{$item['file']}\n  Error: {$item['error']}\n\n";
        echo "  - {$item['file']} ({$item['error']})\n";
    }
    
    file_put_contents($failedLogPath, $logContent);
    echo "\nFailed books logged to: $failedLogPath\n";
}

echo "\nDone! Thumbnails saved to: $coversDir\n";
?>