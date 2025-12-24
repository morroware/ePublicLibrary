<?php
/**
 * BookShelf - Update Metadata Script
 * 
 * Handles AJAX requests to update EPUB metadata.
 * Modifies the OPF file inside the EPUB archive with new metadata values.
 * 
 * @author BookShelf Team
 * @version 2.0
 */

// Enable comprehensive error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * Update EPUB metadata by modifying the OPF file inside the EPUB archive
 * 
 * @param string $filePath Path to the EPUB file
 * @param array $newMetadata Associative array with new metadata values
 * @return array Result array with success status, message, and debug log
 */
function updateEbookMetadata($filePath, $newMetadata) {
    $log = "=== Metadata Update Process Started ===\n";
    $log .= "Timestamp: " . date('Y-m-d H:i:s') . "\n";
    $log .= "File path: $filePath\n\n";
    
    // Check if file exists
    if (!file_exists($filePath)) {
        $log .= "ERROR: File not found at path: $filePath\n";
        return [
            'success' => false, 
            'message' => "File not found: $filePath", 
            'log' => $log
        ];
    }
    $log .= "✓ File exists\n";

    // Check file permissions
    $currentPerms = fileperms($filePath);
    $log .= "Current file permissions: " . decoct($currentPerms & 0777) . "\n";

    if (!is_writable($filePath)) {
        $log .= "ERROR: File is not writable\n";
        return [
            'success' => false, 
            'message' => "File is not writable. Please check file permissions.", 
            'log' => $log
        ];
    }
    $log .= "✓ File is writable\n\n";

    // Check if ZipArchive class is available
    if (!class_exists('ZipArchive')) {
        $log .= "ERROR: ZipArchive class not found. PHP ZIP extension may not be installed.\n";
        return [
            'success' => false, 
            'message' => "ZipArchive extension not available. Please install php-zip.", 
            'log' => $log
        ];
    }
    $log .= "✓ ZipArchive class available\n";

    // Open the EPUB file (which is a ZIP archive)
    $zip = new ZipArchive();
    $openResult = $zip->open($filePath);
    
    if ($openResult !== TRUE) {
        $errorMessages = [
            ZipArchive::ER_EXISTS => 'File already exists',
            ZipArchive::ER_INCONS => 'Zip archive inconsistent',
            ZipArchive::ER_INVAL => 'Invalid argument',
            ZipArchive::ER_MEMORY => 'Malloc failure',
            ZipArchive::ER_NOENT => 'No such file',
            ZipArchive::ER_NOZIP => 'Not a zip archive',
            ZipArchive::ER_OPEN => 'Can\'t open file',
            ZipArchive::ER_READ => 'Read error',
            ZipArchive::ER_SEEK => 'Seek error'
        ];
        
        $errorMsg = isset($errorMessages[$openResult]) ? $errorMessages[$openResult] : "Unknown error code: $openResult";
        $log .= "ERROR: Failed to open EPUB file. Error: $errorMsg\n";
        
        return [
            'success' => false, 
            'message' => "Failed to open EPUB file: $errorMsg", 
            'log' => $log
        ];
    }
    $log .= "✓ Successfully opened EPUB file\n\n";

    // Read the container.xml to find the OPF file location
    $log .= "--- Reading container.xml ---\n";
    $content = $zip->getFromName('META-INF/container.xml');
    
    if (!$content) {
        $zip->close();
        $log .= "ERROR: Failed to read META-INF/container.xml\n";
        return [
            'success' => false, 
            'message' => "Failed to read container.xml. This may not be a valid EPUB file.", 
            'log' => $log
        ];
    }
    $log .= "✓ Successfully read container.xml\n";

    // Extract OPF file path from container.xml
    if (!preg_match('/<rootfile.*full-path="([^"]*)".*>/i', $content, $matches)) {
        $zip->close();
        $log .= "ERROR: Failed to find OPF file path in container.xml\n";
        return [
            'success' => false, 
            'message' => "Failed to parse container.xml. Invalid EPUB structure.", 
            'log' => $log
        ];
    }

    $opfPath = $matches[1];
    $log .= "✓ Found OPF file path: $opfPath\n\n";

    // Read the OPF file content
    $log .= "--- Reading OPF file ---\n";
    $opfContent = $zip->getFromName($opfPath);
    
    if (!$opfContent) {
        $zip->close();
        $log .= "ERROR: Failed to read OPF file at: $opfPath\n";
        return [
            'success' => false, 
            'message' => "Failed to read OPF file: $opfPath", 
            'log' => $log
        ];
    }
    $log .= "✓ Successfully read OPF file\n";
    $log .= "Original OPF content length: " . strlen($opfContent) . " bytes\n\n";

    // Update metadata fields
    $log .= "--- Updating Metadata ---\n";
    $updates = [
        'title' => [
            'pattern' => '/<dc:title.*?>(.*?)<\/dc:title>/is', 
            'replacement' => "<dc:title>" . htmlspecialchars($newMetadata['title'], ENT_XML1, 'UTF-8') . "</dc:title>"
        ],
        'author' => [
            'pattern' => '/<dc:creator.*?>(.*?)<\/dc:creator>/is', 
            'replacement' => "<dc:creator>" . htmlspecialchars($newMetadata['author'], ENT_XML1, 'UTF-8') . "</dc:creator>"
        ],
        'published' => [
            'pattern' => '/<dc:date.*?>(.*?)<\/dc:date>/is', 
            'replacement' => "<dc:date>" . htmlspecialchars($newMetadata['published'], ENT_XML1, 'UTF-8') . "</dc:date>"
        ],
        'genre' => [
            'pattern' => '/<dc:subject.*?>(.*?)<\/dc:subject>/is', 
            'replacement' => "<dc:subject>" . htmlspecialchars($newMetadata['genre'], ENT_XML1, 'UTF-8') . "</dc:subject>"
        ]
    ];

    // Apply each metadata update
    foreach ($updates as $field => $update) {
        $count = 0;
        $opfContent = preg_replace($update['pattern'], $update['replacement'], $opfContent, -1, $count);
        
        if ($count > 0) {
            $log .= "✓ Updated $field: $count occurrence(s) replaced\n";
        } else {
            $log .= "⚠ $field: No existing element found\n";
            
            // If genre doesn't exist, add it to the metadata section
            if ($field === 'genre') {
                $metadataEndCount = 0;
                $opfContent = preg_replace(
                    '/<\/metadata>/is', 
                    "  {$update['replacement']}\n</metadata>", 
                    $opfContent, 
                    -1, 
                    $metadataEndCount
                );
                
                if ($metadataEndCount > 0) {
                    $log .= "✓ Added new genre element to metadata\n";
                } else {
                    $log .= "⚠ Could not add genre element - metadata tag not found\n";
                }
            }
        }
    }
    
    $log .= "\nUpdated OPF content length: " . strlen($opfContent) . " bytes\n\n";

    // Write updated OPF content back to the EPUB
    $log .= "--- Writing Updated Content ---\n";
    if ($zip->addFromString($opfPath, $opfContent) === false) {
        $zip->close();
        $log .= "ERROR: Failed to write updated OPF content back to EPUB\n";
        return [
            'success' => false, 
            'message' => "Failed to write updated metadata to EPUB file.", 
            'log' => $log
        ];
    }
    $log .= "✓ Successfully wrote updated OPF content\n";

    // Check available disk space
    $freeSpace = disk_free_space(dirname($filePath));
    $log .= "Available disk space: " . number_format($freeSpace / 1024 / 1024, 2) . " MB\n\n";

    // Close the ZIP archive to save changes
    $log .= "--- Finalizing Changes ---\n";
    $closeResult = $zip->close();
    
    if ($closeResult === false) {
        $error = error_get_last();
        $log .= "ERROR: Failed to close EPUB file after writing.\n";
        if ($error) {
            $log .= "PHP Error: " . print_r($error, true) . "\n";
        }
        return [
            'success' => false, 
            'message' => "Failed to save changes to EPUB file.", 
            'log' => $log
        ];
    }
    $log .= "✓ Successfully closed EPUB file\n";

    // Verify the file after closing
    $newSize = filesize($filePath);
    $log .= "Updated file size: " . number_format($newSize / 1024, 2) . " KB\n";

    // Check available disk space again
    $freeSpace = disk_free_space(dirname($filePath));
    $log .= "Available disk space after update: " . number_format($freeSpace / 1024 / 1024, 2) . " MB\n\n";

    $log .= "=== Metadata Update Completed Successfully ===\n";

    return [
        'success' => true, 
        'message' => "Metadata updated successfully", 
        'log' => $log
    ];
}

// ============================================================================
// MAIN REQUEST HANDLER
// ============================================================================

// Capture all output for debugging
ob_start();

try {
    // Only accept POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method. Only POST is allowed.');
    }

    // Validate required POST parameters
    if (!isset($_POST['bookPath']) || !isset($_POST['title']) || 
        !isset($_POST['author']) || !isset($_POST['published']) || 
        !isset($_POST['genre'])) {
        throw new Exception('Missing required parameters.');
    }

    // Get and sanitize input
    $bookPath = $_POST['bookPath'];
    $newMetadata = [
        'title' => $_POST['title'],
        'author' => $_POST['author'],
        'published' => $_POST['published'],
        'genre' => $_POST['genre']
    ];

    // Security: Prevent path traversal attacks
    if (strpos($bookPath, '..') !== false || strpos($bookPath, '/') === 0) {
        throw new Exception('Invalid book path. Path traversal not allowed.');
    }

    // Update the metadata
    $result = updateEbookMetadata('books/' . $bookPath, $newMetadata);

    // Capture any output that occurred during processing
    $result['output'] = ob_get_contents();
    ob_end_clean();

    // Send JSON response
    header('Content-Type: application/json');
    echo json_encode($result);

} catch (Exception $e) {
    // Capture output on error
    $output = ob_get_contents();
    ob_end_clean();
    
    // Send error response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'output' => $output,
        'trace' => $e->getTraceAsString()
    ]);
}
?>