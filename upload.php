<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log errors to a file
ini_set('log_errors', 1);
ini_set('error_log', '/path/to/error.log');  // Replace with an actual path

// Output PHP configuration
echo "<h3>PHP Configuration:</h3>";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "post_max_size: " . ini_get('post_max_size') . "<br>";
echo "max_file_uploads: " . ini_get('max_file_uploads') . "<br>";

// Directory to save the uploaded files
$targetDir = __DIR__ . "/books/";
echo "Target Directory: " . $targetDir . "<br>";

// Allowed file extensions
$allowedExtensions = ['epub', 'pdf', 'cbz'];

// Function to save the uploaded files
function saveFiles($baseDir, $uploadedFiles, $allowedExtensions) {
    $feedback = "";
    foreach ($uploadedFiles['name'] as $key => $name) {
        $filePath = $baseDir . '/' . $name;
        $fileExtension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if (is_array($uploadedFiles['name'][$key])) {
            // Recursively save subfolders and files
            if (!is_dir($filePath)) {
                if (mkdir($filePath, 0777, true)) {
                    $feedback .= "Created directory: $filePath<br>";
                } else {
                    $error = error_get_last();
                    $feedback .= "Failed to create directory: $filePath. Error: " . ($error ? $error['message'] : 'Unknown error') . "<br>";
                }
            }
            $feedback .= saveFiles($filePath, [
                'name' => $uploadedFiles['name'][$key],
                'tmp_name' => $uploadedFiles['tmp_name'][$key]
            ], $allowedExtensions);
        } else {
            if (in_array($fileExtension, $allowedExtensions)) {
                if (move_uploaded_file($uploadedFiles['tmp_name'][$key], $filePath)) {
                    $feedback .= "Uploaded file: $filePath<br>";
                } else {
                    $error = error_get_last();
                    $feedback .= "Failed to upload file: $filePath. Error: " . ($error ? $error['message'] : 'Unknown error') . "<br>";
                    $feedback .= "Temporary file exists: " . (file_exists($uploadedFiles['tmp_name'][$key]) ? 'Yes' : 'No') . "<br>";
                    $feedback .= "Destination writable: " . (is_writable(dirname($filePath)) ? 'Yes' : 'No') . "<br>";
                    $feedback .= "File size: " . $uploadedFiles['size'][$key] . " bytes<br>";
                }
            } else {
                $feedback .= "Skipped file (invalid extension): $name<br>";
            }
        }
    }
    return $feedback;
}

// Main processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h3>POST Data:</h3>";
    var_dump($_POST);

    echo "<h3>Files Data:</h3>";
    var_dump($_FILES);

    if (!empty($_FILES['file'])) {
        // Ensure the target directory exists
        if (!is_dir($targetDir)) {
            if (mkdir($targetDir, 0777, true)) {
                echo "Created main directory: $targetDir<br>";
            } else {
                $error = error_get_last();
                echo "Failed to create main directory: $targetDir. Error: " . ($error ? $error['message'] : 'Unknown error') . "<br>";
                exit;
            }
        } else {
            echo "Main directory already exists: $targetDir<br>";
        }

        // Check directory permissions
        echo "Directory writable: " . (is_writable($targetDir) ? 'Yes' : 'No') . "<br>";
        echo "Directory permissions: " . substr(sprintf('%o', fileperms($targetDir)), -4) . "<br>";

        // Save the files and provide feedback
        $uploadFeedback = saveFiles($targetDir, $_FILES['file'], $allowedExtensions);
        echo $uploadFeedback;
        echo "<p>Upload process completed.</p>";
    } else {
        echo "<p>No files were uploaded.</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Folders</title>
</head>
<body>
    <h2>Upload Folders</h2>
    <form id="uploadForm" action="" method="post" enctype="multipart/form-data">
        <label for="file">Select a folder:</label>
        <input type="file" name="file[]" id="file" webkitdirectory directory multiple required>
        <button type="submit">Uploadz</button>
    </form>
    <div id="feedback"></div>
    <script>
        document.getElementById('uploadForm').onsubmit = function(e) {
            e.preventDefault();
            var formData = new FormData(this);
            var xhr = new XMLHttpRequest();
            xhr.open("POST", this.action, true);
            xhr.onload = function () {
                document.getElementById('feedback').innerHTML = xhr.responseText;
            };
            xhr.send(formData);
        };
    </script>
</body>
</html>
