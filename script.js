let book;
let rendition;
const reader = document.getElementById('reader');
const themeToggle = document.getElementById('theme-toggle');

// Theme toggle functionality
function toggleTheme() {
    document.documentElement.classList.toggle('dark');
    localStorage.setItem('theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
    applyThemeToReader();
}

themeToggle.addEventListener('click', toggleTheme);

// Check for saved theme preference or prefer-color-scheme
const savedTheme = localStorage.getItem('theme');
const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

if (savedTheme === 'dark' || (savedTheme === null && prefersDark)) {
    document.documentElement.classList.add('dark');
} else {
    document.documentElement.classList.remove('dark');
}
function openEditModal(bookPath, title, author, published, genre) {
    document.getElementById('bookPath').value = bookPath;
    document.getElementById('title').value = title;
    document.getElementById('author').value = author;
    document.getElementById('published').value = published;
    document.getElementById('genre').value = genre;
    document.getElementById('editModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}

function submitEditForm() {
    const form = document.getElementById('editForm');
    const formData = new FormData(form);

    fetch('update_metadata.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Metadata updated successfully');
            closeEditModal();
            // Reload the page to reflect changes
            location.reload();
        } else {
            alert('Error updating metadata: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while updating metadata');
    });
}

function openReader(bookPath) {
    reader.style.display = 'flex';

    book = ePub(bookPath);
    book.ready.then(() => {
        rendition = book.renderTo("epub-viewer", {
            width: '100%',
            height: '100%',
            spread: "always"
        });
        rendition.display().catch(err => {
            console.error('Error rendering page:', err);
        });

        // Apply current theme to reader
        applyThemeToReader();
    }).catch(err => {
        console.error('Error loading book:', err);
    });

    document.addEventListener("keyup", handleKeyPress);
}

function closeReader() {
    reader.style.display = 'none';
    if (book) {
        book.destroy();
    }
    document.removeEventListener("keyup", handleKeyPress);
}

function handleKeyPress(e) {
    if (e.key === "ArrowRight") {
        nextPage();
    }
    if (e.key === "ArrowLeft") {
        prevPage();
    }
    if (e.key === "Escape") {
        closeReader();
    }
}

function nextPage() {
    if (rendition) {
        rendition.next();
    }
}

function prevPage() {
    if (rendition) {
        rendition.prev();
    }
}

function applyThemeToReader() {
    if (rendition) {
        const theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
        rendition.themes.register(theme, theme === 'dark' ? darkTheme : lightTheme);
        rendition.themes.select(theme);
    }
}

const lightTheme = {
    body: {
        background: '#ffffff',
        color: '#000000'
    }
};

const darkTheme = {
    body: {
        background: '#1a202c',
        color: '#e2e8f0'
    }
};

let touchStartX = 0;
let touchEndX = 0;

reader.addEventListener('touchstart', handleTouchStart);
reader.addEventListener('touchend', handleTouchEnd);

function handleTouchStart(e) {
    touchStartX = e.changedTouches[0].screenX;
}

function handleTouchEnd(e) {
    touchEndX = e.changedTouches[0].screenX;
    handleSwipe();
}

function handleSwipe() {
    if (touchStartX - touchEndX > 50) {
        nextPage();
    }
    if (touchEndX - touchStartX > 50) {
        prevPage();
    }
}

// Lazy loading implementation
function lazyLoadBookCovers() {
    const bookCovers = document.querySelectorAll('.book-cover');
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                loadBookCover(entry.target);
                observer.unobserve(entry.target);
            }
        });
    }, {
        rootMargin: '100px 0px',
        threshold: 0.1
    });

    bookCovers.forEach(cover => {
        observer.observe(cover);
    });
}

function loadBookCover(coverElement) {
    const bookPath = coverElement.dataset.book;
    const book = ePub(bookPath);

    book.loaded.metadata.then(() => {
        return book.coverUrl();
    }).then(coverUrl => {
        if (coverUrl) {
            coverElement.style.backgroundImage = `url(${coverUrl})`;
            coverElement.innerHTML = '';
        } else {
            return book.spine.items[0].load(book.load.bind(book));
        }
    }).then(content => {
        if (content) {
            const parser = new DOMParser();
            const doc = parser.parseFromString(content, "text/html");
            const images = doc.getElementsByTagName('img');
            if (images.length > 0) {
                const firstImageSrc = images[0].src;
                coverElement.style.backgroundImage = `url(${firstImageSrc})`;
                coverElement.innerHTML = '';
            } else {
                throw new Error('No image on first page');
            }
        }
    }).catch(err => {
        coverElement.style.backgroundImage = 'none';
        coverElement.innerHTML = '<div class="w-full h-full flex items-center justify-center bg-gray-300 dark:bg-gray-700 text-gray-600 dark:text-gray-400">No Cover</div>';
    });
}

// Autocomplete functionality
const searchInput = document.getElementById('searchInput');
const searchForm = document.getElementById('searchForm');

searchInput.addEventListener('input', debounce(function() {
    const term = this.value;
    if (term.length > 2) {
        fetch(`index.php?autocomplete=${encodeURIComponent(term)}`)
            .then(response => response.json())
            .then(suggestions => {
                showAutocompleteSuggestions(suggestions);
            });
    } else {
        hideAutocompleteSuggestions();
    }
}, 300));

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function showAutocompleteSuggestions(suggestions) {
    let suggestionsContainer = document.getElementById('autocomplete-suggestions');
    if (!suggestionsContainer) {
        suggestionsContainer = document.createElement('ul');
        suggestionsContainer.id = 'autocomplete-suggestions';
        suggestionsContainer.className = 'absolute z-10 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 w-full max-w-md mt-1 rounded-lg shadow-lg';
        searchInput.parentNode.appendChild(suggestionsContainer);
    }

    suggestionsContainer.innerHTML = '';
    suggestions.forEach(suggestion => {
        const li = document.createElement('li');
        li.textContent = suggestion;
        li.className = 'px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer';
        li.addEventListener('click', () => {
            searchInput.value = suggestion;
            hideAutocompleteSuggestions();
            searchForm.submit();
        });
        suggestionsContainer.appendChild(li);
    });

    suggestionsContainer.style.display = 'block';
}

function hideAutocompleteSuggestions() {
    const suggestionsContainer = document.getElementById('autocomplete-suggestions');
    if (suggestionsContainer) {
        suggestionsContainer.style.display = 'none';
    }
}

document.addEventListener('click', function(e) {
    if (e.target !== searchInput) {
        hideAutocompleteSuggestions();
    }
});

// Sorting functionality
const sortBy = document.getElementById('sortBy');
const sortOrder = document.getElementById('sortOrder');

[sortBy, sortOrder].forEach(element => {
    element.addEventListener('change', function() {
        const searchParams = new URLSearchParams(window.location.search);
        searchParams.set('sortBy', sortBy.value);
        searchParams.set('sortOrder', sortOrder.value);
        window.location.search = searchParams.toString();
    });
});

// Metadata editing functionality
function openEditModal(bookPath, title, author, published, genre) {
    document.getElementById('bookPath').value = bookPath;
    document.getElementById('title').value = title;
    document.getElementById('author').value = author;
    document.getElementById('published').value = published;
    document.getElementById('genre').value = genre;
    document.getElementById('editModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}

function submitEditForm() {
    const form = document.getElementById('editForm');
    const formData = new FormData(form);

    fetch('update_metadata.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        console.log('Server response:', data);
        if (data.success) {
            alert('Metadata updated successfully');
            closeEditModal();
            // Reload the page to reflect changes
            location.reload();
        } else {
            let errorMessage = 'Error updating metadata: ' + data.message + '\n\n';
            if (data.log) {
                errorMessage += 'Log:\n' + data.log + '\n\n';
            }
            if (data.output) {
                errorMessage += 'Output:\n' + data.output + '\n\n';
            }
            if (data.trace) {
                errorMessage += 'Stack Trace:\n' + data.trace;
            }
            console.error('Metadata update error:', errorMessage);
            
            // Create a pre-formatted text area to display the error message
            const errorDisplay = document.createElement('textarea');
            errorDisplay.value = errorMessage;
            errorDisplay.style.width = '100%';
            errorDisplay.style.height = '300px';
            errorDisplay.style.padding = '10px';
            errorDisplay.style.marginTop = '10px';
            errorDisplay.readOnly = true;

            // Show the error in a modal or append it to the page
            const errorModal = document.createElement('div');
            errorModal.style.position = 'fixed';
            errorModal.style.top = '10%';
            errorModal.style.left = '10%';
            errorModal.style.right = '10%';
            errorModal.style.backgroundColor = 'white';
            errorModal.style.padding = '20px';
            errorModal.style.border = '1px solid black';
            errorModal.style.zIndex = '1000';

            const closeButton = document.createElement('button');
            closeButton.textContent = 'Close';
            closeButton.onclick = function() { document.body.removeChild(errorModal); };

            errorModal.appendChild(document.createTextNode('Error Details:'));
            errorModal.appendChild(errorDisplay);
            errorModal.appendChild(closeButton);

            document.body.appendChild(errorModal);
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        alert('An error occurred while updating metadata: ' + error.message);
    });
}
document.addEventListener('DOMContentLoaded', function() {
    const sortOptions = document.querySelector('.flex.items-center.gap-2');
    const sortToggle = document.createElement('button');
    sortToggle.textContent = 'Sort Options';
    sortToggle.className = 'px-4 py-2 bg-purple-500 text-white rounded-lg hover:bg-purple-600 focus:outline-none focus:ring-2 focus:ring-purple-500 transition-colors duration-200 sm:hidden';
    sortToggle.onclick = function() {
        sortOptions.classList.toggle('hidden');
    };
    sortOptions.parentNode.insertBefore(sortToggle, sortOptions);

    function checkWidth() {
        if (window.innerWidth < 640) {  // 'sm' breakpoint in Tailwind
            sortOptions.classList.add('hidden');
            sortToggle.classList.remove('hidden');
        } else {
            sortOptions.classList.remove('hidden');
            sortToggle.classList.add('hidden');
        }
    }

    window.addEventListener('resize', checkWidth);
    checkWidth();  // Initial check
});
// Initialize lazy loading when the DOM is fully loaded
document.addEventListener('DOMContentLoaded', lazyLoadBookCovers);
