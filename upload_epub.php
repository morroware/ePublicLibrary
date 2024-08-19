<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log errors to a file
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/upload_errors.log');

// Directory to save the uploaded files
$targetDir = __DIR__ . "/books/";

// Allowed file extensions
$allowedExtensions = ['epub'];

// Function to save the uploaded files
function saveFiles($baseDir, $uploadedFiles, $allowedExtensions) {
    $feedback = [];
    foreach ($uploadedFiles['name'] as $key => $name) {
        $filePath = $baseDir . '/' . $name;
        $fileExtension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if (is_array($uploadedFiles['name'][$key])) {
            // Recursively save subfolders and files
            if (!is_dir($filePath)) {
                if (mkdir($filePath, 0777, true)) {
                    $feedback[] = ["type" => "success", "message" => "Created directory: $filePath"];
                } else {
                    $error = error_get_last();
                    $feedback[] = ["type" => "error", "message" => "Failed to create directory: $filePath. Error: " . ($error ? $error['message'] : 'Unknown error')];
                }
            }
            $feedback = array_merge($feedback, saveFiles($filePath, [
                'name' => $uploadedFiles['name'][$key],
                'tmp_name' => $uploadedFiles['tmp_name'][$key],
                'size' => $uploadedFiles['size'][$key]
            ], $allowedExtensions));
        } else {
            if (in_array($fileExtension, $allowedExtensions)) {
                if (move_uploaded_file($uploadedFiles['tmp_name'][$key], $filePath)) {
                    $feedback[] = ["type" => "success", "message" => "Uploaded file: $filePath"];
                } else {
                    $error = error_get_last();
                    $feedback[] = ["type" => "error", "message" => "Failed to upload file: $filePath. Error: " . ($error ? $error['message'] : 'Unknown error')];
                    $feedback[] = ["type" => "info", "message" => "Temporary file exists: " . (file_exists($uploadedFiles['tmp_name'][$key]) ? 'Yes' : 'No')];
                    $feedback[] = ["type" => "info", "message" => "Destination writable: " . (is_writable(dirname($filePath)) ? 'Yes' : 'No')];
                    $feedback[] = ["type" => "info", "message" => "File size: " . $uploadedFiles['size'][$key] . " bytes"];
                }
            } else {
                $feedback[] = ["type" => "warning", "message" => "Skipped file (invalid extension): $name"];
            }
        }
    }
    return $feedback;
}

// Main processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ["success" => true, "messages" => []];

    if (!empty($_FILES['file'])) {
        // Ensure the target directory exists
        if (!is_dir($targetDir)) {
            if (mkdir($targetDir, 0777, true)) {
                $response["messages"][] = ["type" => "success", "message" => "Created main directory: $targetDir"];
            } else {
                $error = error_get_last();
                $response["messages"][] = ["type" => "error", "message" => "Failed to create main directory: $targetDir. Error: " . ($error ? $error['message'] : 'Unknown error')];
                $response["success"] = false;
            }
        }

        if ($response["success"]) {
            // Save the files and provide feedback
            $uploadFeedback = saveFiles($targetDir, $_FILES['file'], $allowedExtensions);
            $response["messages"] = array_merge($response["messages"], $uploadFeedback);
            $response["messages"][] = ["type" => "info", "message" => "Upload process completed."];
        }
    } else {
        $response["success"] = false;
        $response["messages"][] = ["type" => "error", "message" => "No files were uploaded."];
    }

    // Send JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload EPUBs - BookShelf</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        gray: {
                            900: '#1a202c',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen transition-colors duration-200">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-4xl font-bold text-purple-600 dark:text-purple-400 mb-8">Upload EPUBs</h1>
        <form id="uploadForm" action="" method="post" enctype="multipart/form-data" class="mb-8">
            <div class="mb-4">
                <label for="file" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Select EPUB file(s) or a folder:</label>
                <input type="file" name="file[]" id="file" multiple accept=".epub" class="block w-full text-sm text-gray-900 dark:text-gray-100 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100 dark:file:bg-purple-900 dark:file:text-purple-300 dark:hover:file:bg-purple-800">
            </div>
            <button type="submit" class="px-4 py-2 bg-purple-500 text-white rounded-lg hover:bg-purple-600 focus:outline-none focus:ring-2 focus:ring-purple-500 transition-colors duration-200">Upload</button>
        </form>
        <div id="feedback" class="mt-4"></div>
    </div>

    <script>
        document.getElementById('uploadForm').onsubmit = function(e) {
            e.preventDefault();
            var formData = new FormData(this);
            var xhr = new XMLHttpRequest();
            xhr.open("POST", this.action, true);
            xhr.onload = function () {
                var feedbackDiv = document.getElementById('feedback');
                feedbackDiv.innerHTML = '';
                try {
                    var response = JSON.parse(xhr.responseText);
                    response.messages.forEach(function(msg) {
                        var messageDiv = document.createElement('div');
                        messageDiv.className = 'mb-2 p-2 rounded ';
                        switch(msg.type) {
                            case 'success':
                                messageDiv.className += 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
                                break;
                            case 'error':
                                messageDiv.className += 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
                                break;
                            case 'warning':
                                messageDiv.className += 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
                                break;
                            default:
                                messageDiv.className += 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
                        }
                        messageDiv.textContent = msg.message;
                        feedbackDiv.appendChild(messageDiv);
                    });
                } catch (e) {
                    feedbackDiv.innerHTML = '<div class="mb-2 p-2 rounded bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Error processing server response.</div>';
                }
            };
            xhr.send(formData);
        };
    </script>
</body>
</html>
