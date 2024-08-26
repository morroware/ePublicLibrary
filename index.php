<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

function getEbooks($dir, $searchTerm = '', $searchField = 'all', $sortBy = 'title', $sortOrder = 'asc') {
    if (!file_exists($dir) || !is_dir($dir)) {
        return [];
    }

    $result = [];
    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'epub') {
                $relativePath = str_replace($dir . '/', '', $file->getPathname());
                $metadata = getEbookMetadata($file->getPathname());
                
                // Apply search filter
                if (empty($searchTerm) || 
                    ($searchField === 'all' && (
                        stripos($relativePath, $searchTerm) !== false ||
                        stripos($metadata['title'], $searchTerm) !== false ||
                        stripos($metadata['author'], $searchTerm) !== false ||
                        stripos($metadata['published'], $searchTerm) !== false ||
                        stripos($metadata['genre'], $searchTerm) !== false
                    )) ||
                    ($searchField === 'title' && stripos($metadata['title'], $searchTerm) !== false) ||
                    ($searchField === 'author' && stripos($metadata['author'], $searchTerm) !== false) ||
                    ($searchField === 'published' && stripos($metadata['published'], $searchTerm) !== false) ||
                    ($searchField === 'genre' && stripos($metadata['genre'], $searchTerm) !== false)
                ) {
                    $result[] = [
                        'path' => $relativePath,
                        'metadata' => $metadata
                    ];
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error scanning directory $dir: " . $e->getMessage());
    }

    // Sort the results
    usort($result, function($a, $b) use ($sortBy, $sortOrder) {
        $compareResult = strcasecmp($a['metadata'][$sortBy], $b['metadata'][$sortBy]);
        return $sortOrder === 'asc' ? $compareResult : -$compareResult;
    });

    return $result;
}

function getEbookMetadata($filePath) {
    $metadata = [
        'title' => 'Unknown Title',
        'author' => 'Unknown Author',
        'published' => 'Unknown Date',
        'genre' => 'Unknown Genre'
    ];

    $zip = new ZipArchive();
    if ($zip->open($filePath) === TRUE) {
        $content = $zip->getFromName('META-INF/container.xml');
        if ($content) {
            preg_match('/<rootfile.*full-path="([^"]*)".*>/i', $content, $matches);
            if (isset($matches[1])) {
                $opfPath = $matches[1];
                $opfContent = $zip->getFromName($opfPath);
                if ($opfContent) {
                    // Extract title
                    preg_match('/<dc:title.*?>(.*?)<\/dc:title>/is', $opfContent, $titleMatch);
                    if (isset($titleMatch[1])) {
                        $metadata['title'] = trim($titleMatch[1]);
                    }

                    // Extract author
                    preg_match('/<dc:creator.*?>(.*?)<\/dc:creator>/is', $opfContent, $authorMatch);
                    if (isset($authorMatch[1])) {
                        $metadata['author'] = trim($authorMatch[1]);
                    }

                    // Extract publication date
                    preg_match('/<dc:date.*?>(.*?)<\/dc:date>/is', $opfContent, $dateMatch);
                    if (isset($dateMatch[1])) {
                        $metadata['published'] = trim($dateMatch[1]);
                    }

                    // Extract genre
                    preg_match('/<dc:subject.*?>(.*?)<\/dc:subject>/is', $opfContent, $genreMatch);
                    if (isset($genreMatch[1])) {
                        $metadata['genre'] = trim($genreMatch[1]);
                    }
                }
            }
        }
        $zip->close();
    }

    return $metadata;
}

function getAutocompleteSuggestions($dir, $term) {
    $books = getEbooks($dir, $term);
    $suggestions = [];
    foreach ($books as $book) {
        $suggestions[] = $book['metadata']['title'];
        $suggestions[] = $book['metadata']['author'];
        $suggestions[] = $book['metadata']['genre'];
    }
    return array_unique($suggestions);
}

function deleteEbook($filePath) {
    if (file_exists($filePath) && is_file($filePath)) {
        if (unlink($filePath)) {
            return true;
        }
    }
    return false;
}

// Handle delete request
if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['bookPath'])) {
    $bookPath = 'books/' . $_POST['bookPath'];
    $result = deleteEbook($bookPath);
    header('Content-Type: application/json');
    echo json_encode(['success' => $result]);
    exit;
}

$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
$searchField = isset($_GET['searchField']) ? $_GET['searchField'] : 'all';
$sortBy = isset($_GET['sortBy']) ? $_GET['sortBy'] : 'title';
$sortOrder = isset($_GET['sortOrder']) ? $_GET['sortOrder'] : 'asc';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$itemsPerPage = 12;

if (isset($_GET['autocomplete'])) {
    $suggestions = getAutocompleteSuggestions('books', $_GET['autocomplete']);
    header('Content-Type: application/json');
    echo json_encode($suggestions);
    exit;
}

$books = getEbooks('books', $searchTerm, $searchField, $sortBy, $sortOrder);

$totalBooks = count($books);
$totalPages = max(1, ceil($totalBooks / $itemsPerPage));
$page = min($page, $totalPages);

$paginatedBooks = array_slice($books, ($page - 1) * $itemsPerPage, $itemsPerPage);

$paginationRange = 2;
$startPage = max($page - $paginationRange, 1);
$endPage = min($page + $paginationRange, $totalPages);
?>

<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Collection</title>
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.5/jszip.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/epubjs@0.3.93/dist/epub.min.js"></script>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="favicon.ico" type="image/x-icon">
</head>
<body class="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen transition-colors duration-200">
    <header class="bg-white dark:bg-gray-800 shadow-md mb-4 py-4">
        <div class="container mx-auto px-4">
            <div class="flex justify-between items-center mb-4">
                <h1 class="text-2xl md:text-3xl font-bold text-purple-600 dark:text-purple-400">Book Collection</h1>
                <button id="theme-toggle" class="p-2 rounded-full bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors duration-200">
                    <svg class="w-6 h-6 text-gray-800 dark:text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
                    </svg>
                </button>
            </div>
            
            <form id="searchForm" action="" method="get" class="mb-4">
                <div class="flex flex-col gap-2">
                    <input type="text" name="search" id="searchInput" value="<?= htmlspecialchars($searchTerm) ?>" placeholder="Search ebooks..." class="w-full px-4 py-2 rounded-lg bg-gray-100 dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-purple-500 transition-colors duration-200">
                    <div class="flex gap-2">
                        <select name="searchField" class="flex-grow px-4 py-2 bg-gray-100 dark:bg-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 transition-colors duration-200">
                            <option value="all" <?= $searchField === 'all' ? 'selected' : '' ?>>All Fields</option>
                            <option value="title" <?= $searchField === 'title' ? 'selected' : '' ?>>Title</option>
                            <option value="author" <?= $searchField === 'author' ? 'selected' : '' ?>>Author</option>
                            <option value="published" <?= $searchField === 'published' ? 'selected' : '' ?>>Published Date</option>
                            <option value="genre" <?= $searchField === 'genre' ? 'selected' : '' ?>>Genre</option>
                        </select>
                        <button type="submit" class="px-4 py-2 bg-purple-500 text-white rounded-lg hover:bg-purple-600 focus:outline-none focus:ring-2 focus:ring-purple-500 transition-colors duration-200">
                            Search
                        </button>
                    </div>
                </div>
            </form>
            
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2">
                <div class="flex items-center gap-2 w-full sm:w-auto">
                    <select id="sortBy" class="flex-grow sm:flex-grow-0 px-4 py-2 bg-gray-100 dark:bg-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 transition-colors duration-200">
                        <option value="title" <?= $sortBy === 'title' ? 'selected' : '' ?>>Sort by Title</option>
                        <option value="author" <?= $sortBy === 'author' ? 'selected' : '' ?>>Sort by Author</option>
                        <option value="published" <?= $sortBy === 'published' ? 'selected' : '' ?>>Sort by Published Date</option>
                        <option value="genre" <?= $sortBy === 'genre' ? 'selected' : '' ?>>Sort by Genre</option>
                    </select>
                    <select id="sortOrder" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 transition-colors duration-200">
                        <option value="asc" <?= $sortOrder === 'asc' ? 'selected' : '' ?>>Ascending</option>
                        <option value="desc" <?= $sortOrder === 'desc' ? 'selected' : '' ?>>Descending</option>
                    </select>
                </div>
                <a href="upload_epub.php" class="w-full sm:w-auto px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-500 transition-colors duration-200 text-center">
                    Upload EPUBs
                </a>
            </div>
        </div>
    </header>

<div class="container mx-auto px-4">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-4 sm:p-6 mb-8">
            <h2 class="text-xl sm:text-2xl font-semibold mb-4 text-purple-600 dark:text-purple-400">Ebooks</h2>
            <?php if (empty($books)): ?>
                <p class="text-gray-600 dark:text-gray-400 italic">No ebooks found. Please make sure the 'books' folder exists and contains EPUB files.</p>
            <?php else: ?>
 <ul class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-4">
    <?php foreach ($paginatedBooks as $book): ?>
        <li class="bg-gray-100 dark:bg-gray-700 p-4 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors duration-200">
            <div class="book-cover" data-book="books/<?= htmlspecialchars($book['path']) ?>">
                <div class="w-full h-full flex items-center justify-center text-gray-500 dark:text-gray-400">Loading...</div>
            </div>
            <div class="flex flex-col items-start justify-between mt-2">
                <a href="#" class="flex items-start flex-1 text-purple-600 dark:text-purple-400 hover:text-purple-800 dark:hover:text-purple-300 text-sm font-semibold truncate w-full open-reader" 
                   data-book-path="<?= htmlspecialchars($book['path'], ENT_QUOTES, 'UTF-8') ?>"
                   title="<?= htmlspecialchars($book['metadata']['title'], ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($book['metadata']['title']) ?>
                </a>
                <p class="text-sm text-gray-600 dark:text-gray-400"><?= htmlspecialchars($book['metadata']['author']) ?></p>
                <p class="text-xs text-gray-500 dark:text-gray-500"><?= htmlspecialchars($book['metadata']['published']) ?></p>
                <p class="text-xs text-gray-500 dark:text-gray-500">Genre: <?= htmlspecialchars($book['metadata']['genre']) ?></p>
                <div class="flex items-center mt-1">
                    <a href="books/<?= htmlspecialchars($book['path']) ?>" class="text-purple-600 dark:text-purple-400 hover:text-purple-800 dark:hover:text-purple-300" download title="Download">
                        ⬇️
                    </a>
                    <a href="#" class="text-purple-600 dark:text-purple-400 hover:text-purple-800 dark:hover:text-purple-300 ml-2 edit-metadata" 
                       data-book-path="<?= htmlspecialchars($book['path'], ENT_QUOTES, 'UTF-8') ?>"
                       data-book-title="<?= htmlspecialchars($book['metadata']['title'], ENT_QUOTES, 'UTF-8') ?>"
                       data-book-author="<?= htmlspecialchars($book['metadata']['author'], ENT_QUOTES, 'UTF-8') ?>"
                       data-book-published="<?= htmlspecialchars($book['metadata']['published'], ENT_QUOTES, 'UTF-8') ?>"
                       data-book-genre="<?= htmlspecialchars($book['metadata']['genre'], ENT_QUOTES, 'UTF-8') ?>"
                       title="Edit Metadata">
                        ✏️
                    </a>
                    <a href="#" class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 ml-2 delete-book" 
                       data-book-path="<?= htmlspecialchars($book['path'], ENT_QUOTES, 'UTF-8') ?>"
                       data-book-title="<?= htmlspecialchars($book['metadata']['title'], ENT_QUOTES, 'UTF-8') ?>"
                       title="Delete">
                        🗑️
                    </a>
                </div>
            </div>
        </li>
    <?php endforeach; ?>
</ul>
                <div class="flex justify-center mt-6 flex-wrap">
                    <?php if ($page > 1): ?>
                        <a href="?page=1&search=<?= urlencode($searchTerm) ?>&searchField=<?= urlencode($searchField) ?>&sortBy=<?= urlencode($sortBy) ?>&sortOrder=<?= urlencode($sortOrder) ?>" class="px-3 py-1 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-purple-500 hover:text-white dark:hover:bg-purple-500 dark:hover:text-white transition-colors duration-200 m-1">First</a>
                        <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($searchTerm) ?>&searchField=<?= urlencode($searchField) ?>&sortBy=<?= urlencode($sortBy) ?>&sortOrder=<?= urlencode($sortOrder) ?>" class="px-3 py-1 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-purple-500 hover:text-white dark:hover:bg-purple-500 dark:hover:text-white transition-colors duration-200 m-1">Prev</a>
                    <?php endif; ?>

                    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="px-3 py-1 bg-purple-500 text-white rounded m-1"><?= $i ?></span>
                        <?php else: ?>
                            <a href="?page=<?= $i ?>&search=<?= urlencode($searchTerm) ?>&searchField=<?= urlencode($searchField) ?>&sortBy=<?= urlencode($sortBy) ?>&sortOrder=<?= urlencode($sortOrder) ?>" class="px-3 py-1 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-purple-500 hover:text-white dark:hover:bg-purple-500 dark:hover:text-white transition-colors duration-200 m-1"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($searchTerm) ?>&searchField=<?= urlencode($searchField) ?>&sortBy=<?= urlencode($sortBy) ?>&sortOrder=<?= urlencode($sortOrder) ?>" class="px-3 py-1 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-purple-500 hover:text-white dark:hover:bg-purple-500 dark:hover:text-white transition-colors duration-200 m-1">Next</a>
                        <a href="?page=<?= $totalPages ?>&search=<?= urlencode($searchTerm) ?>&searchField=<?= urlencode($searchField) ?>&sortBy=<?= urlencode($sortBy) ?>&sortOrder=<?= urlencode($sortOrder) ?>" class="px-3 py-1 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-purple-500 hover:text-white dark:hover:bg-purple-500 dark:hover:text-white transition-colors duration-200 m-1">Last</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div id="reader" class="bg-white dark:bg-gray-900">
        <div id="epub-viewer"></div>
        <div id="reader-controls" class="bg-gray-100 dark:bg-gray-800">
            <button onclick="prevPage()" class="reader-btn bg-purple-500 text-white hover:bg-purple-600 dark:bg-purple-600 dark:hover:bg-purple-700">Previous</button>
            <button onclick="nextPage()" class="reader-btn bg-purple-500 text-white hover:bg-purple-600 dark:bg-purple-600 dark:hover:bg-purple-700">Next</button>
            <button onclick="closeReader()" class="reader-btn bg-red-500 text-white hover:bg-red-600 dark:bg-red-600 dark:hover:bg-red-700">Close</button>
        </div>
    </div>

    <div id="editModal" class="fixed z-10 inset-0 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100" id="modal-title">
                        Edit Book Metadata
                    </h3>
                    <div class="mt-2">
                        <form id="editForm">
                            <input type="hidden" id="bookPath" name="bookPath">
                            <div class="mb-4">
                                <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Title</label>
                                <input type="text" name="title" id="title" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            </div>
                            <div class="mb-4">
                                <label for="author" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Author</label>
                                <input type="text" name="author" id="author" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            </div>
                            <div class="mb-4">
                                <label for="published" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Published Date</label>
                                <input type="text" name="published" id="published" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            </div>
                            <div class="mb-4">
                                <label for="genre" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Genre</label>
                                <input type="text" name="genre" id="genre" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            </div>
                        </form>
                    </div>
                </div>
                <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-purple-600 text-base font-medium text-white hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 sm:ml-3 sm:w-auto sm:text-sm" onclick="submitEditForm()">
                        Save
                    </button>
                    <button type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm dark:bg-gray-600 dark:text-gray-100 dark:border-gray-500 dark:hover:bg-gray-700" onclick="closeEditModal()">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>
