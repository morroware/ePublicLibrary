<?php
/**
 * BookShelf - Professional Ebook Reader
 * Main Index Page - Version 7.1 (Special Characters Fixed)
 */

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
                        'metadata' => $metadata,
                        'thumbnail' => getThumbnailPath($relativePath)
                    ];
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error scanning directory $dir: " . $e->getMessage());
    }

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
                    preg_match('/<dc:title.*?>(.*?)<\/dc:title>/is', $opfContent, $titleMatch);
                    if (isset($titleMatch[1])) {
                        $metadata['title'] = trim($titleMatch[1]);
                    }

                    preg_match('/<dc:creator.*?>(.*?)<\/dc:creator>/is', $opfContent, $authorMatch);
                    if (isset($authorMatch[1])) {
                        $metadata['author'] = trim($authorMatch[1]);
                    }

                    preg_match('/<dc:date.*?>(.*?)<\/dc:date>/is', $opfContent, $dateMatch);
                    if (isset($dateMatch[1])) {
                        $metadata['published'] = trim($dateMatch[1]);
                    }

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

function getThumbnailPath($bookRelativePath) {
    // Use MD5 hash for unique, safe filenames
    $safeName = md5($bookRelativePath) . '.jpg';
    $thumbnailPath = 'books/covers/' . $safeName;
    
    if (file_exists($thumbnailPath)) {
        return $thumbnailPath;
    }
    
    // Fallback to old naming scheme for backwards compatibility
    $oldSafeName = str_replace(['/', '\\'], '_', $bookRelativePath);
    $oldSafeName = preg_replace('/\.epub$/i', '.jpg', $oldSafeName);
    $oldThumbnailPath = 'books/covers/' . $oldSafeName;
    
    if (file_exists($oldThumbnailPath)) {
        return $oldThumbnailPath;
    }
    
    return null;
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

$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
$searchField = isset($_GET['searchField']) ? $_GET['searchField'] : 'all';
$sortBy = isset($_GET['sortBy']) ? $_GET['sortBy'] : 'title';
$sortOrder = isset($_GET['sortOrder']) ? $_GET['sortOrder'] : 'asc';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$itemsPerPage = 25;

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>BookShelf - Your Digital Library</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.5/jszip.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/epubjs@0.3.93/dist/epub.min.js"></script>
    
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    
    <!-- Header -->
    <header>
        <div class="header-container">
            
            <!-- Logo and Theme Toggle -->
            <div class="header-top">
                <div class="logo-container">
                    <div class="logo-icon">B</div>
                    <div class="logo-text">
                        <h1>BookShelf</h1>
                        <p>Your Digital Library</p>
                    </div>
                </div>
                
                <button id="theme-toggle" aria-label="Toggle theme">
                    <div class="toggle-slider">
                        <svg class="w-3.5 h-3.5 text-amber-500 dark:hidden" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd"></path>
                        </svg>
                        <svg class="w-3.5 h-3.5 text-white hidden dark:block" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path>
                        </svg>
                    </div>
                </button>
            </div>
            
            <!-- Search Form -->
            <form id="searchForm" action="" method="get" class="search-container">
                <div class="search-form-inner">
                    <div class="search-input-wrapper">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                        <input type="text" 
                               name="search" 
                               id="searchInput" 
                               value="<?= htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8') ?>" 
                               placeholder="Search books by title, author, or genre..."
                               autocomplete="off">
                    </div>
                    
                    <div class="search-controls">
                        <select name="searchField" class="search-select">
                            <option value="all" <?= $searchField === 'all' ? 'selected' : '' ?>>All Fields</option>
                            <option value="title" <?= $searchField === 'title' ? 'selected' : '' ?>>Title</option>
                            <option value="author" <?= $searchField === 'author' ? 'selected' : '' ?>>Author</option>
                            <option value="published" <?= $searchField === 'published' ? 'selected' : '' ?>>Published</option>
                            <option value="genre" <?= $searchField === 'genre' ? 'selected' : '' ?>>Genre</option>
                        </select>
                        <button type="submit" class="search-button">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                            Search
                        </button>
                    </div>
                </div>
            </form>
            
            <!-- Controls Bar -->
            <div class="controls-bar">
                <div class="sort-controls">
                    <select id="sortBy">
                        <option value="title" <?= $sortBy === 'title' ? 'selected' : '' ?>>Title</option>
                        <option value="author" <?= $sortBy === 'author' ? 'selected' : '' ?>>Author</option>
                        <option value="published" <?= $sortBy === 'published' ? 'selected' : '' ?>>Published</option>
                        <option value="genre" <?= $sortBy === 'genre' ? 'selected' : '' ?>>Genre</option>
                    </select>
                    
                    <select id="sortOrder">
                        <option value="asc" <?= $sortOrder === 'asc' ? 'selected' : '' ?>>A → Z</option>
                        <option value="desc" <?= $sortOrder === 'desc' ? 'selected' : '' ?>>Z → A</option>
                    </select>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main>
        <div class="content-wrapper">
            
            <!-- Section Header -->
            <div class="section-header">
                <h2 class="section-title">
                    <?= empty($searchTerm) ? 'Your Library' : 'Search Results' ?>
                </h2>
                <?php if (!empty($books)): ?>
                    <span class="book-count">
                        <?= $totalBooks ?> book<?= $totalBooks !== 1 ? 's' : '' ?>
                    </span>
                <?php endif; ?>
            </div>
            
            <?php if (empty($books)): ?>
                <!-- Empty State -->
                <div class="empty-state">
                    <div class="empty-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                        </svg>
                    </div>
                    <h3 class="empty-title">
                        <?= empty($searchTerm) ? 'No books yet' : 'No books found' ?>
                    </h3>
                    <p class="empty-description">
                        <?= empty($searchTerm) ? 'Start building your digital library by uploading EPUB files.' : 'Try adjusting your search terms or filters.' ?>
                    </p>
                    <?php if (empty($searchTerm)): ?>
                        <a href="upload_epub.php" class="empty-action">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            Add Your First Book
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- Book Grid -->
                <div class="book-grid">
                    <?php foreach ($paginatedBooks as $book): 
                        // Properly encode paths for different contexts
                        $bookPathUrl = 'books/' . rawurlencode($book['path']);
                        $bookPathJs = 'books/' . $book['path'];
                        $bookPathData = htmlspecialchars($bookPathJs, ENT_QUOTES, 'UTF-8');
                        $thumbnailUrl = $book['thumbnail'] ? htmlspecialchars($book['thumbnail'], ENT_QUOTES, 'UTF-8') : '';
                    ?>
                        <div class="book-card" onclick='openReader(<?= json_encode($bookPathJs, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                            <div class="book-cover-wrapper">
                                <div class="book-cover" 
                                     data-book="<?= $bookPathData ?>"
                                     <?php if ($book['thumbnail']): ?>
                                     data-thumbnail="<?= $thumbnailUrl ?>"
                                     style="background-image: url('<?= $thumbnailUrl ?>');"
                                     <?php endif; ?>>
                                    <?php if (!$book['thumbnail']): ?>
                                    <div class="book-cover-loading">
                                        <svg class="w-10 h-10 animate-spin text-white opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="book-overlay">
                                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                </div>
                            </div>
                            
                            <div class="book-info">
                                <h3 class="book-title" title="<?= htmlspecialchars($book['metadata']['title'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($book['metadata']['title'], ENT_QUOTES, 'UTF-8') ?>
                                </h3>
                                <p class="book-author">
                                    <?= htmlspecialchars($book['metadata']['author'], ENT_QUOTES, 'UTF-8') ?>
                                </p>
                                <div class="book-meta">
                                    <a href="<?= $bookPathUrl ?>" 
                                       class="book-download" 
                                       download="<?= htmlspecialchars(basename($book['path']), ENT_QUOTES, 'UTF-8') ?>"
                                       onclick="event.stopPropagation();">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                        </svg>
                                        Download
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <nav class="pagination" aria-label="Pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=1&search=<?= urlencode($searchTerm) ?>&searchField=<?= urlencode($searchField) ?>&sortBy=<?= urlencode($sortBy) ?>&sortOrder=<?= urlencode($sortOrder) ?>">First</a>
                        <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($searchTerm) ?>&searchField=<?= urlencode($searchField) ?>&sortBy=<?= urlencode($sortBy) ?>&sortOrder=<?= urlencode($sortOrder) ?>">Prev</a>
                    <?php endif; ?>

                    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span><?= $i ?></span>
                        <?php else: ?>
                            <a href="?page=<?= $i ?>&search=<?= urlencode($searchTerm) ?>&searchField=<?= urlencode($searchField) ?>&sortBy=<?= urlencode($sortBy) ?>&sortOrder=<?= urlencode($sortOrder) ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($searchTerm) ?>&searchField=<?= urlencode($searchField) ?>&sortBy=<?= urlencode($sortBy) ?>&sortOrder=<?= urlencode($sortOrder) ?>">Next</a>
                        <a href="?page=<?= $totalPages ?>&search=<?= urlencode($searchTerm) ?>&searchField=<?= urlencode($searchField) ?>&sortBy=<?= urlencode($sortBy) ?>&sortOrder=<?= urlencode($sortOrder) ?>">Last</a>
                    <?php endif; ?>
                </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>

    <!-- EPUB Reader Container -->
    <div id="reader">
        <div id="epub-viewer"></div>
    </div>

    <script src="script.js"></script>
</body>
</html>