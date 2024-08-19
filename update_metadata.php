<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

function updateEbookMetadata($filePath, $newMetadata) {
    $log = "Attempting to update metadata for file: $filePath\n";
    
    if (!file_exists($filePath)) {
        $log .= "Error: File not found: $filePath\n";
        return ['success' => false, 'message' => "File not found: $filePath", 'log' => $log];
    }

    $log .= "File exists. Checking permissions...\n";
    $currentPerms = fileperms($filePath);
    $log .= "Current file permissions: " . decoct($currentPerms & 0777) . "\n";

    if (!is_writable($filePath)) {
        $log .= "Error: File is not writable\n";
        return ['success' => false, 'message' => "File is not writable", 'log' => $log];
    }

    $log .= "File is writable. Proceeding with update...\n";

    if (!class_exists('ZipArchive')) {
        $log .= "Error: ZipArchive class not found. PHP ZIP extension may not be installed.\n";
        return ['success' => false, 'message' => "ZipArchive class not found", 'log' => $log];
    }

    $zip = new ZipArchive();
    $openResult = $zip->open($filePath);
    if ($openResult !== TRUE) {
        $log .= "Error: Failed to open EPUB file: $filePath. ZipArchive error code: $openResult\n";
        return ['success' => false, 'message' => "Failed to open EPUB file", 'log' => $log];
    }

    $log .= "Successfully opened EPUB file\n";

    $content = $zip->getFromName('META-INF/container.xml');
    if (!$content) {
        $zip->close();
        $log .= "Error: Failed to read container.xml\n";
        return ['success' => false, 'message' => "Failed to read container.xml", 'log' => $log];
    }

    $log .= "Successfully read container.xml\n";

    if (!preg_match('/<rootfile.*full-path="([^"]*)".*>/i', $content, $matches)) {
        $zip->close();
        $log .= "Error: Failed to find OPF file path in container.xml\n";
        return ['success' => false, 'message' => "Failed to find OPF file path", 'log' => $log];
    }

    $opfPath = $matches[1];
    $log .= "Found OPF file path: $opfPath\n";

    $opfContent = $zip->getFromName($opfPath);
    if (!$opfContent) {
        $zip->close();
        $log .= "Error: Failed to read OPF file: $opfPath\n";
        return ['success' => false, 'message' => "Failed to read OPF file", 'log' => $log];
    }

    $log .= "Successfully read OPF file\n";

    // Update metadata
    $updates = [
        'title' => ['pattern' => '/<dc:title.*?>(.*?)<\/dc:title>/is', 'replacement' => "<dc:title>{$newMetadata['title']}</dc:title>"],
        'author' => ['pattern' => '/<dc:creator.*?>(.*?)<\/dc:creator>/is', 'replacement' => "<dc:creator>{$newMetadata['author']}</dc:creator>"],
        'published' => ['pattern' => '/<dc:date.*?>(.*?)<\/dc:date>/is', 'replacement' => "<dc:date>{$newMetadata['published']}</dc:date>"],
        'genre' => ['pattern' => '/<dc:subject.*?>(.*?)<\/dc:subject>/is', 'replacement' => "<dc:subject>{$newMetadata['genre']}</dc:subject>"]
    ];

    foreach ($updates as $field => $update) {
        $count = 0;
        $opfContent = preg_replace($update['pattern'], $update['replacement'], $opfContent, -1, $count);
        $log .= "Updated $field: $count occurrences\n";
        
        if ($count === 0 && $field === 'genre') {
            // If genre doesn't exist, add it
            $opfContent = preg_replace('/<\/metadata>/is', "{$update['replacement']}\n</metadata>", $opfContent, -1, $count);
            $log .= "Added new genre element\n";
        }
    }

    if ($zip->addFromString($opfPath, $opfContent) === false) {
        $zip->close();
        $log .= "Error: Failed to write updated OPF content\n";
        return ['success' => false, 'message' => "Failed to write updated OPF content", 'log' => $log];
    }

    $log .= "Successfully wrote updated OPF content\n";

    // Check available disk space
    $freeSpace = disk_free_space(dirname($filePath));
    $log .= "Available disk space before closing: $freeSpace bytes\n";

    // Try to close the file
    $closeResult = $zip->close();
    if ($closeResult === false) {
        $error = error_get_last();
        $log .= "Error: Failed to close EPUB file after writing. PHP Error: " . print_r($error, true) . "\n";
        return ['success' => false, 'message' => "Failed to close EPUB file after writing", 'log' => $log];
    }

    $log .= "Successfully closed EPUB file\n";

    // Check file size after closing
    $newSize = filesize($filePath);
    $log .= "New file size: $newSize bytes\n";

    // Check available disk space again
    $freeSpace = disk_free_space(dirname($filePath));
    $log .= "Available disk space after closing: $freeSpace bytes\n";

    return ['success' => true, 'message' => "Metadata updated successfully", 'log' => $log];
}

// Capture all output
ob_start();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $bookPath = $_POST['bookPath'];
        $newMetadata = [
            'title' => $_POST['title'],
            'author' => $_POST['author'],
            'published' => $_POST['published'],
            'genre' => $_POST['genre']
        ];

        $result = updateEbookMetadata('books/' . $bookPath, $newMetadata);

        // Include any output in the result
        $result['output'] = ob_get_contents();
        ob_end_clean();

        header('Content-Type: application/json');
        echo json_encode($result);
    } else {
        throw new Exception('Method not allowed');
    }
} catch (Exception $e) {
    $output = ob_get_contents();
    ob_end_clean();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'output' => $output,
        'trace' => $e->getTraceAsString()
    ]);
}
