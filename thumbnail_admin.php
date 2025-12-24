<?php
/**
 * BookShelf - Thumbnail Admin Panel
 * Simple interface to manage cover thumbnails
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

$coversDir = __DIR__ . '/books/covers';
$action = $_GET['action'] ?? 'view';

// Handle actions
if ($action === 'generate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Redirect to generate_thumbnails.php with output buffering
    header('Location: generate_thumbnails.php');
    exit;
}

if ($action === 'clear' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $deleted = 0;
    if (is_dir($coversDir)) {
        $files = glob($coversDir . '/*.jpg');
        foreach ($files as $file) {
            if (unlink($file)) {
                $deleted++;
            }
        }
    }
    $message = "Deleted $deleted thumbnail(s)";
}

// Get statistics
$totalThumbnails = 0;
$totalSize = 0;

if (is_dir($coversDir)) {
    $files = glob($coversDir . '/*.jpg');
    $totalThumbnails = count($files);
    foreach ($files as $file) {
        $totalSize += filesize($file);
    }
}

$totalSizeMB = round($totalSize / 1024 / 1024, 2);

// Get book count
$booksDir = __DIR__ . '/books';
$bookCount = 0;
if (is_dir($booksDir)) {
    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($booksDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'epub') {
                $bookCount++;
            }
        }
    } catch (Exception $e) {
        // Ignore errors
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thumbnail Admin - BookShelf</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-2xl mx-auto">
            
            <!-- Header -->
            <div class="mb-8">
                <a href="index.php" class="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400 hover:text-purple-600 dark:hover:text-purple-400 mb-4">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Back to Library
                </a>
                <h1 class="text-3xl font-bold mb-2" style="background: linear-gradient(135deg, #9333ea, #7e22ce); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                    Thumbnail Manager
                </h1>
                <p class="text-gray-600 dark:text-gray-400">Manage book cover thumbnails for faster loading</p>
            </div>

            <?php if (isset($message)): ?>
            <div class="mb-6 p-4 bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300 rounded-xl border border-green-200 dark:border-green-800">
                <?= htmlspecialchars($message) ?>
            </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
                <div class="bg-white dark:bg-gray-800 rounded-xl p-6 border border-gray-200 dark:border-gray-700">
                    <div class="text-sm text-gray-600 dark:text-gray-400 mb-1">Total Books</div>
                    <div class="text-3xl font-bold text-gray-900 dark:text-white"><?= $bookCount ?></div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 rounded-xl p-6 border border-gray-200 dark:border-gray-700">
                    <div class="text-sm text-gray-600 dark:text-gray-400 mb-1">Thumbnails</div>
                    <div class="text-3xl font-bold text-purple-600 dark:text-purple-400"><?= $totalThumbnails ?></div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 rounded-xl p-6 border border-gray-200 dark:border-gray-700">
                    <div class="text-sm text-gray-600 dark:text-gray-400 mb-1">Cache Size</div>
                    <div class="text-3xl font-bold text-gray-900 dark:text-white"><?= $totalSizeMB ?> MB</div>
                </div>
            </div>

            <!-- Actions -->
            <div class="bg-white dark:bg-gray-800 rounded-xl p-6 border border-gray-200 dark:border-gray-700 mb-6">
                <h2 class="text-xl font-bold mb-4 text-gray-900 dark:text-white">Actions</h2>
                
                <div class="space-y-4">
                    <!-- Generate Thumbnails -->
                    <div class="flex items-start gap-4 p-4 bg-purple-50 dark:bg-purple-900/20 rounded-lg border border-purple-200 dark:border-purple-800">
                        <svg class="w-6 h-6 text-purple-600 dark:text-purple-400 flex-shrink-0 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        <div class="flex-1">
                            <h3 class="font-semibold text-gray-900 dark:text-white mb-1">Generate Thumbnails</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">Create thumbnails for all books that don't have them yet. This will significantly speed up your library.</p>
                            <form action="?action=generate" method="post">
                                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg font-medium text-sm transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                    </svg>
                                    Generate Now
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Clear Cache -->
                    <div class="flex items-start gap-4 p-4 bg-red-50 dark:bg-red-900/20 rounded-lg border border-red-200 dark:border-red-800">
                        <svg class="w-6 h-6 text-red-600 dark:text-red-400 flex-shrink-0 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                        <div class="flex-1">
                            <h3 class="font-semibold text-gray-900 dark:text-white mb-1">Clear All Thumbnails</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">Delete all cached thumbnails. Use this if covers are outdated or corrupted.</p>
                            <form action="?action=clear" method="post" onsubmit="return confirm('Are you sure you want to delete all thumbnails?');">
                                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium text-sm transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                    Clear Cache
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Info -->
            <div class="bg-blue-50 dark:bg-blue-900/20 rounded-xl p-6 border border-blue-200 dark:border-blue-800">
                <div class="flex gap-3">
                    <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div class="text-sm text-blue-900 dark:text-blue-200">
                        <p class="font-semibold mb-2">How Thumbnails Work</p>
                        <ul class="space-y-1 text-blue-800 dark:text-blue-300">
                            <li>• <strong>Automatic Generation:</strong> Thumbnails are created automatically as you browse your library</li>
                            <li>• <strong>First View:</strong> When you view a book for the first time, the cover is extracted and cached</li>
                            <li>• <strong>Instant After:</strong> All subsequent views load the cached thumbnail instantly</li>
                            <li>• <strong>Standard Ebooks:</strong> Works with SVG and all other cover formats</li>
                            <li>• <strong>Manual Option:</strong> You can still pre-generate thumbnails using the button above</li>
                            <li>• Thumbnails are stored in <code class="bg-blue-100 dark:bg-blue-900 px-1 rounded">books/covers/</code></li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="bg-green-50 dark:bg-green-900/20 rounded-xl p-6 border border-green-200 dark:border-green-800 mt-6">
                <div class="flex gap-3">
                    <svg class="w-5 h-5 text-green-600 dark:text-green-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div class="text-sm text-green-900 dark:text-green-200">
                        <p class="font-semibold mb-1">✨ Just Browse Your Library!</p>
                        <p class="text-green-800 dark:text-green-300">Thumbnails will be created automatically as you view books. No action needed!</p>
                    </div>
                </div>
            </div>

        </div>
    </div>
</body>
</html>