/**
 * BookShelf - Professional JavaScript
 * Version 7.1 - Enhanced E-Reader with Special Characters Support
 */

// ============================================================================
// GLOBAL VARIABLES
// ============================================================================

let book;
let rendition;
let currentLocation = null;
let toc = [];
let bookmarks = [];
let currentBookPath = '';

const reader = document.getElementById('reader');
const themeToggle = document.getElementById('theme-toggle');
const searchInput = document.getElementById('searchInput');
const searchForm = document.getElementById('searchForm');
const sortBy = document.getElementById('sortBy');
const sortOrder = document.getElementById('sortOrder');

// ============================================================================
// URL ENCODING UTILITIES
// ============================================================================

/**
 * Properly encode a file path for use with EPUB.js
 * Handles special characters, spaces, quotes, etc.
 */
function encodeEpubPath(path) {
    // Split the path into directory and filename
    const parts = path.split('/');
    
    // Encode each part separately, but preserve the forward slashes
    const encodedParts = parts.map((part, index) => {
        // For all parts, encode them properly
        // encodeURIComponent handles: spaces, quotes, apostrophes, special chars
        return encodeURIComponent(part);
    });
    
    return encodedParts.join('/');
}

// ============================================================================
// READER SETTINGS (persisted to localStorage)
// ============================================================================

const ReaderSettings = {
    STORAGE_KEY: 'bookshelf-reader-settings',
    
    defaults: {
        fontSize: 100,        // percentage
        fontFamily: 'serif',  // serif, sans-serif, mono
        lineHeight: 1.6,
        theme: 'auto',        // auto, light, dark, sepia
        margins: 'normal'     // compact, normal, wide
    },
    
    current: null,
    
    init() {
        this.current = { ...this.defaults, ...this.load() };
    },
    
    load() {
        try {
            const saved = localStorage.getItem(this.STORAGE_KEY);
            return saved ? JSON.parse(saved) : {};
        } catch (e) {
            return {};
        }
    },
    
    save() {
        try {
            localStorage.setItem(this.STORAGE_KEY, JSON.stringify(this.current));
        } catch (e) {
            console.warn('Could not save reader settings:', e);
        }
    },
    
    set(key, value) {
        this.current[key] = value;
        this.save();
        this.apply();
    },
    
    get(key) {
        return this.current[key];
    },
    
    apply() {
        if (!rendition) return;
        
        const settings = this.current;
        const fontSize = settings.fontSize / 100;
        
        const fontFamilies = {
            'serif': 'Georgia, "Times New Roman", serif',
            'sans-serif': '"DM Sans", -apple-system, BlinkMacSystemFont, sans-serif',
            'mono': '"JetBrains Mono", "Fira Code", monospace'
        };
        
        const margins = {
            'compact': '2%',
            'normal': '5%',
            'wide': '10%'
        };
        
        // Apply theme
        let bgColor, textColor;
        const themeMode = settings.theme === 'auto' 
            ? (ThemeManager.isDarkMode() ? 'dark' : 'light')
            : settings.theme;
            
        switch (themeMode) {
            case 'dark':
                bgColor = '#0f0e0d';
                textColor = '#e7e5e4';
                break;
            case 'sepia':
                bgColor = '#f4ecd8';
                textColor = '#5c4b37';
                break;
            default: // light
                bgColor = '#fdfcfb';
                textColor = '#1c1917';
        }
        
        rendition.themes.default({
            body: {
                background: bgColor + ' !important',
                color: textColor + ' !important',
                'font-family': fontFamilies[settings.fontFamily] + ' !important',
                'font-size': `${fontSize}rem !important`,
                'line-height': settings.lineHeight + ' !important',
                'padding-left': margins[settings.margins] + ' !important',
                'padding-right': margins[settings.margins] + ' !important'
            },
            'p, div, span, li': {
                'font-size': 'inherit !important',
                'line-height': 'inherit !important'
            },
            'h1, h2, h3, h4, h5, h6': {
                color: textColor + ' !important'
            },
            'a': {
                color: '#c2703c !important'
            }
        });
        
        // Update reader background
        const viewer = document.getElementById('epub-viewer');
        if (viewer) {
            viewer.style.background = bgColor;
        }
    }
};

// ============================================================================
// BOOKMARKS & READING PROGRESS
// ============================================================================

const ReadingProgress = {
    STORAGE_KEY: 'bookshelf-reading-progress',
    
    save(bookPath, cfi) {
        try {
            const progress = this.loadAll();
            progress[bookPath] = {
                cfi: cfi,
                timestamp: Date.now()
            };
            localStorage.setItem(this.STORAGE_KEY, JSON.stringify(progress));
        } catch (e) {
            console.warn('Could not save reading progress:', e);
        }
    },
    
    load(bookPath) {
        const progress = this.loadAll();
        return progress[bookPath] || null;
    },
    
    loadAll() {
        try {
            const saved = localStorage.getItem(this.STORAGE_KEY);
            return saved ? JSON.parse(saved) : {};
        } catch (e) {
            return {};
        }
    }
};

const BookmarkManager = {
    STORAGE_KEY: 'bookshelf-bookmarks',
    
    loadAll() {
        try {
            const saved = localStorage.getItem(this.STORAGE_KEY);
            return saved ? JSON.parse(saved) : {};
        } catch (e) {
            return {};
        }
    },
    
    saveAll(data) {
        try {
            localStorage.setItem(this.STORAGE_KEY, JSON.stringify(data));
        } catch (e) {
            console.warn('Could not save bookmarks:', e);
        }
    },
    
    getForBook(bookPath) {
        const all = this.loadAll();
        return all[bookPath] || [];
    },
    
    add(bookPath, cfi, text) {
        const all = this.loadAll();
        if (!all[bookPath]) all[bookPath] = [];
        
        // Check if already bookmarked
        if (all[bookPath].some(b => b.cfi === cfi)) {
            return false;
        }
        
        all[bookPath].push({
            cfi: cfi,
            text: text.substring(0, 100) + (text.length > 100 ? '...' : ''),
            timestamp: Date.now()
        });
        
        this.saveAll(all);
        return true;
    },
    
    remove(bookPath, cfi) {
        const all = this.loadAll();
        if (all[bookPath]) {
            all[bookPath] = all[bookPath].filter(b => b.cfi !== cfi);
            this.saveAll(all);
        }
    },
    
    isBookmarked(bookPath, cfi) {
        const bookmarks = this.getForBook(bookPath);
        return bookmarks.some(b => b.cfi === cfi);
    }
};

// ============================================================================
// THEME MANAGEMENT
// ============================================================================

const ThemeManager = {
    STORAGE_KEY: 'bookshelf-theme',
    DARK_CLASS: 'dark',
    
    init() {
        const savedTheme = this.getSavedTheme();

        // Default to dark mode on first visit, otherwise use saved preference
        if (savedTheme === null || savedTheme === 'dark') {
            this.setDarkMode(true, false);
        } else {
            this.setDarkMode(false, false);
        }
    },
    
    toggle() {
        const isDark = document.documentElement.classList.contains(this.DARK_CLASS);
        this.setDarkMode(!isDark, true);
    },
    
    setDarkMode(enabled, animate = true) {
        const html = document.documentElement;
        
        if (animate) {
            html.style.transition = 'background-color 0.3s ease, color 0.3s ease';
            setTimeout(() => html.style.transition = '', 300);
        }
        
        if (enabled) {
            html.classList.add(this.DARK_CLASS);
            this.saveTheme('dark');
        } else {
            html.classList.remove(this.DARK_CLASS);
            this.saveTheme('light');
        }
        
        // Update reader theme if open
        if (ReaderSettings.current && ReaderSettings.current.theme === 'auto') {
            ReaderSettings.apply();
        }
        
        window.dispatchEvent(new CustomEvent('themechange', { 
            detail: { theme: enabled ? 'dark' : 'light' } 
        }));
    },
    
    saveTheme(theme) {
        try {
            localStorage.setItem(this.STORAGE_KEY, theme);
        } catch (e) {
            console.warn('Could not save theme preference:', e);
        }
    },
    
    getSavedTheme() {
        try {
            return localStorage.getItem(this.STORAGE_KEY);
        } catch (e) {
            return null;
        }
    },
    
    isDarkMode() {
        return document.documentElement.classList.contains(this.DARK_CLASS);
    }
};

// Initialize settings
ReaderSettings.init();
ThemeManager.init();

if (themeToggle) {
    themeToggle.addEventListener('click', () => ThemeManager.toggle());
}

// ============================================================================
// ENHANCED EPUB READER
// ============================================================================

function openReader(bookPath) {
    currentBookPath = bookPath;
    
    console.log('Opening book:', bookPath);
    
    // Create enhanced reader UI if not exists
    createReaderUI();
    
    reader.style.display = 'flex';
    reader.classList.add('reader-open');
    document.body.style.overflow = 'hidden';

    // Properly encode the URI for EPUB.js
    // CRITICAL: This handles spaces, quotes, apostrophes, special characters
    const encodedPath = encodeEpubPath(bookPath);
    console.log('Encoded path for EPUB.js:', encodedPath);

    try {
        book = ePub(encodedPath);
        
        book.ready.then(() => {
            console.log('Book loaded successfully');
            
            rendition = book.renderTo("epub-viewer", {
                width: '100%',
                height: '100%',
                spread: 'auto',
                minSpreadWidth: 1000,
                flow: 'paginated'
            });
            
            // Generate locations for progress tracking
            book.locations.generate(1600).then(() => {
                console.log('Locations generated:', book.locations.length());
                // Update progress now that locations are ready
                if (currentLocation) {
                    updateProgressBar(currentLocation);
                }
            }).catch(err => {
                console.warn('Failed to generate locations:', err);
            });
            
            // Load TOC
            book.loaded.navigation.then(nav => {
                toc = nav.toc;
                renderTOC(toc);
            }).catch(err => {
                console.warn('Failed to load navigation:', err);
            });
            
            // Load bookmarks for this book
            bookmarks = BookmarkManager.getForBook(bookPath);
            renderBookmarks();
            
            // Check for saved reading position
            const savedProgress = ReadingProgress.load(bookPath);
            if (savedProgress && savedProgress.cfi) {
                rendition.display(savedProgress.cfi).catch(() => {
                    rendition.display();
                });
            } else {
                rendition.display();
            }
            
            // Apply settings
            ReaderSettings.apply();
            
            // Set up location tracking
            rendition.on('relocated', (location) => {
                currentLocation = location;
                updateProgressBar(location);
                updatePageInfo(location);
                ReadingProgress.save(bookPath, location.start.cfi);
                updateBookmarkButton();
            });
            
            // Click hotspots for navigation
            rendition.on('rendered', (section) => {
                setupHotspots();
            });
            
            // Handle link clicks
            rendition.on('click', (e) => {
                // Don't interfere with actual links
                if (e.target.tagName === 'A') return;
            });
            
        }).catch(err => {
            console.error('Error loading book:', err);
            showNotification('Failed to load book: ' + err.message, 'error');
            closeReader();
        });
    } catch (err) {
        console.error('Error creating ePub object:', err);
        showNotification('Failed to open book: ' + err.message, 'error');
        closeReader();
    }

    document.addEventListener("keydown", handleKeyDown);
}

function createReaderUI() {
    // Check if already created
    if (document.getElementById('reader-header')) return;
    
    // Clear existing reader content and rebuild with proper structure
    reader.innerHTML = `
        <!-- Reader Header -->
        <div id="reader-header" class="reader-header">
            <div class="reader-header-left">
                <button id="reader-close-btn" class="reader-icon-btn" title="Close (Esc)">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                </button>
                <div id="reader-book-title" class="reader-book-title"></div>
            </div>
            <div class="reader-header-center">
                <div id="reader-page-info" class="reader-page-info"></div>
            </div>
            <div class="reader-header-right">
                <button id="reader-toc-btn" class="reader-icon-btn" title="Table of Contents (T)">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
                <button id="reader-bookmark-btn" class="reader-icon-btn" title="Bookmark (B)">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
                    </svg>
                </button>
                <button id="reader-settings-btn" class="reader-icon-btn" title="Settings (S)">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="3"/>
                        <path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/>
                    </svg>
                </button>
            </div>
        </div>
        
        <!-- Main Reading Area Container -->
        <div id="reader-main" class="reader-main">
            <!-- EPUB Viewer -->
            <div id="epub-viewer"></div>
            
            <!-- Navigation Hotspots (inside main area) -->
            <div id="hotspot-left" class="reader-hotspot reader-hotspot-left">
                <div class="hotspot-indicator">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M15 18l-6-6 6-6"/>
                    </svg>
                </div>
            </div>
            <div id="hotspot-right" class="reader-hotspot reader-hotspot-right">
                <div class="hotspot-indicator">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 18l6-6-6-6"/>
                    </svg>
                </div>
            </div>
        </div>
        
        <!-- Progress Bar (above bottom controls) -->
        <div id="reader-progress-container" class="reader-progress-container">
            <div id="reader-progress-bar" class="reader-progress-bar"></div>
        </div>
        
        <!-- Bottom Controls -->
        <div id="reader-controls" class="reader-bottom-controls">
            <button id="reader-prev-btn" class="reader-nav-btn">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M15 18l-6-6 6-6"/>
                </svg>
                <span>Previous</span>
            </button>
            <div class="reader-progress-text">
                <span id="reader-percent">0%</span>
            </div>
            <button id="reader-next-btn" class="reader-nav-btn">
                <span>Next</span>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 18l6-6-6-6"/>
                </svg>
            </button>
        </div>
        
        <!-- TOC Panel -->
        <div id="reader-toc-panel" class="reader-panel reader-panel-hidden">
            <div class="reader-panel-header">
                <h3>Contents</h3>
                <button id="reader-toc-close" class="reader-panel-close">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 6L6 18M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="reader-panel-tabs">
                <button class="reader-panel-tab active" data-tab="toc">Chapters</button>
                <button class="reader-panel-tab" data-tab="bookmarks">Bookmarks</button>
            </div>
            <div id="reader-toc-content" class="reader-panel-content">
                <ul id="reader-toc-list" class="reader-toc-list"></ul>
            </div>
            <div id="reader-bookmarks-content" class="reader-panel-content" style="display: none;">
                <ul id="reader-bookmarks-list" class="reader-toc-list"></ul>
            </div>
        </div>
        
        <!-- Settings Panel -->
        <div id="reader-settings-panel" class="reader-panel reader-panel-hidden">
            <div class="reader-panel-header">
                <h3>Reading Settings</h3>
                <button id="reader-settings-close" class="reader-panel-close">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 6L6 18M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="reader-panel-content reader-settings-content">
                <!-- Font Size -->
                <div class="reader-setting-group">
                    <label class="reader-setting-label">Font Size</label>
                    <div class="reader-setting-control">
                        <button id="font-decrease" class="reader-setting-btn">A−</button>
                        <span id="font-size-value" class="reader-setting-value">100%</span>
                        <button id="font-increase" class="reader-setting-btn">A+</button>
                    </div>
                </div>
                
                <!-- Font Family -->
                <div class="reader-setting-group">
                    <label class="reader-setting-label">Font</label>
                    <div class="reader-font-options">
                        <button class="reader-font-btn active" data-font="serif">Serif</button>
                        <button class="reader-font-btn" data-font="sans-serif">Sans</button>
                        <button class="reader-font-btn" data-font="mono">Mono</button>
                    </div>
                </div>
                
                <!-- Line Height -->
                <div class="reader-setting-group">
                    <label class="reader-setting-label">Line Spacing</label>
                    <div class="reader-setting-control">
                        <button id="line-decrease" class="reader-setting-btn">−</button>
                        <span id="line-height-value" class="reader-setting-value">1.6</span>
                        <button id="line-increase" class="reader-setting-btn">+</button>
                    </div>
                </div>
                
                <!-- Theme -->
                <div class="reader-setting-group">
                    <label class="reader-setting-label">Theme</label>
                    <div class="reader-theme-options">
                        <button class="reader-theme-btn reader-theme-auto active" data-theme="auto" title="Auto">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="5"/>
                                <path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/>
                            </svg>
                        </button>
                        <button class="reader-theme-btn reader-theme-light" data-theme="light" title="Light"></button>
                        <button class="reader-theme-btn reader-theme-sepia" data-theme="sepia" title="Sepia"></button>
                        <button class="reader-theme-btn reader-theme-dark" data-theme="dark" title="Dark"></button>
                    </div>
                </div>
                
                <!-- Margins -->
                <div class="reader-setting-group">
                    <label class="reader-setting-label">Margins</label>
                    <div class="reader-margin-options">
                        <button class="reader-margin-btn" data-margin="compact">Compact</button>
                        <button class="reader-margin-btn active" data-margin="normal">Normal</button>
                        <button class="reader-margin-btn" data-margin="wide">Wide</button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Panel Overlay -->
        <div id="reader-overlay" class="reader-overlay"></div>
    `;
    
    // Setup event listeners
    setupReaderEventListeners();
}

function setupReaderEventListeners() {
    // Close button
    document.getElementById('reader-close-btn').addEventListener('click', closeReader);
    
    // Navigation
    document.getElementById('reader-prev-btn').addEventListener('click', prevPage);
    document.getElementById('reader-next-btn').addEventListener('click', nextPage);
    
    // Hotspots
    document.getElementById('hotspot-left').addEventListener('click', prevPage);
    document.getElementById('hotspot-right').addEventListener('click', nextPage);
    
    // TOC Panel
    document.getElementById('reader-toc-btn').addEventListener('click', toggleTOCPanel);
    document.getElementById('reader-toc-close').addEventListener('click', closePanels);
    
    // Bookmark
    document.getElementById('reader-bookmark-btn').addEventListener('click', toggleBookmark);
    
    // Settings Panel
    document.getElementById('reader-settings-btn').addEventListener('click', toggleSettingsPanel);
    document.getElementById('reader-settings-close').addEventListener('click', closePanels);
    
    // Overlay
    document.getElementById('reader-overlay').addEventListener('click', closePanels);
    
    // Panel tabs
    document.querySelectorAll('.reader-panel-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.reader-panel-tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            
            const tabName = tab.dataset.tab;
            document.getElementById('reader-toc-content').style.display = tabName === 'toc' ? 'block' : 'none';
            document.getElementById('reader-bookmarks-content').style.display = tabName === 'bookmarks' ? 'block' : 'none';
        });
    });
    
    // Font size controls
    document.getElementById('font-decrease').addEventListener('click', () => {
        const current = ReaderSettings.get('fontSize');
        if (current > 60) {
            ReaderSettings.set('fontSize', current - 10);
            document.getElementById('font-size-value').textContent = ReaderSettings.get('fontSize') + '%';
        }
    });
    
    document.getElementById('font-increase').addEventListener('click', () => {
        const current = ReaderSettings.get('fontSize');
        if (current < 200) {
            ReaderSettings.set('fontSize', current + 10);
            document.getElementById('font-size-value').textContent = ReaderSettings.get('fontSize') + '%';
        }
    });
    
    // Line height controls
    document.getElementById('line-decrease').addEventListener('click', () => {
        const current = ReaderSettings.get('lineHeight');
        if (current > 1.2) {
            ReaderSettings.set('lineHeight', Math.round((current - 0.2) * 10) / 10);
            document.getElementById('line-height-value').textContent = ReaderSettings.get('lineHeight');
        }
    });
    
    document.getElementById('line-increase').addEventListener('click', () => {
        const current = ReaderSettings.get('lineHeight');
        if (current < 2.4) {
            ReaderSettings.set('lineHeight', Math.round((current + 0.2) * 10) / 10);
            document.getElementById('line-height-value').textContent = ReaderSettings.get('lineHeight');
        }
    });
    
    // Font family buttons
    document.querySelectorAll('.reader-font-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.reader-font-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            ReaderSettings.set('fontFamily', btn.dataset.font);
        });
    });
    
    // Theme buttons
    document.querySelectorAll('.reader-theme-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.reader-theme-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            ReaderSettings.set('theme', btn.dataset.theme);
        });
    });
    
    // Margin buttons
    document.querySelectorAll('.reader-margin-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.reader-margin-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            ReaderSettings.set('margins', btn.dataset.margin);
        });
    });
    
    // Initialize settings display
    updateSettingsDisplay();
    
    // Progress bar click to seek
    document.getElementById('reader-progress-container').addEventListener('click', (e) => {
        if (!book || !rendition) return;
        const rect = e.currentTarget.getBoundingClientRect();
        const percent = (e.clientX - rect.left) / rect.width;
        const cfi = book.locations.cfiFromPercentage(percent);
        rendition.display(cfi);
    });
}

function updateSettingsDisplay() {
    const settings = ReaderSettings.current;
    
    document.getElementById('font-size-value').textContent = settings.fontSize + '%';
    document.getElementById('line-height-value').textContent = settings.lineHeight;
    
    // Update active states
    document.querySelectorAll('.reader-font-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.font === settings.fontFamily);
    });
    
    document.querySelectorAll('.reader-theme-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.theme === settings.theme);
    });
    
    document.querySelectorAll('.reader-margin-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.margin === settings.margins);
    });
}

function setupHotspots() {
    // Hotspots are now handled via dedicated elements outside the iframe
}

function renderTOC(items, container = null) {
    const list = container || document.getElementById('reader-toc-list');
    list.innerHTML = '';
    
    items.forEach(item => {
        const li = document.createElement('li');
        li.className = 'reader-toc-item';
        
        const link = document.createElement('a');
        link.href = '#';
        link.textContent = item.label;
        link.addEventListener('click', (e) => {
            e.preventDefault();
            rendition.display(item.href);
            closePanels();
        });
        
        li.appendChild(link);
        
        if (item.subitems && item.subitems.length > 0) {
            const subList = document.createElement('ul');
            subList.className = 'reader-toc-sublist';
            renderTOC(item.subitems, subList);
            li.appendChild(subList);
        }
        
        list.appendChild(li);
    });
}

function renderBookmarks() {
    const list = document.getElementById('reader-bookmarks-list');
    if (!list) return;
    
    const bookmarks = BookmarkManager.getForBook(currentBookPath);
    list.innerHTML = '';
    
    if (bookmarks.length === 0) {
        list.innerHTML = '<li class="reader-toc-item reader-toc-empty">No bookmarks yet</li>';
        return;
    }
    
    bookmarks.forEach(bookmark => {
        const li = document.createElement('li');
        li.className = 'reader-toc-item reader-bookmark-item';
        
        const link = document.createElement('a');
        link.href = '#';
        link.innerHTML = `
            <span class="bookmark-text">${bookmark.text}</span>
            <span class="bookmark-date">${new Date(bookmark.timestamp).toLocaleDateString()}</span>
        `;
        link.addEventListener('click', (e) => {
            e.preventDefault();
            rendition.display(bookmark.cfi);
            closePanels();
        });
        
        const deleteBtn = document.createElement('button');
        deleteBtn.className = 'bookmark-delete';
        deleteBtn.innerHTML = `
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 6L6 18M6 6l12 12"/>
            </svg>
        `;
        deleteBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            BookmarkManager.remove(currentBookPath, bookmark.cfi);
            renderBookmarks();
            updateBookmarkButton();
        });
        
        li.appendChild(link);
        li.appendChild(deleteBtn);
        list.appendChild(li);
    });
}

function toggleBookmark() {
    if (!currentLocation) return;
    
    const cfi = currentLocation.start.cfi;
    
    if (BookmarkManager.isBookmarked(currentBookPath, cfi)) {
        BookmarkManager.remove(currentBookPath, cfi);
        showNotification('Bookmark removed');
    } else {
        // Get some text from current page
        const range = rendition.getRange(cfi);
        const text = range ? range.toString().substring(0, 100) : 'Bookmarked page';
        BookmarkManager.add(currentBookPath, cfi, text || 'Bookmarked page');
        showNotification('Bookmark added');
    }
    
    renderBookmarks();
    updateBookmarkButton();
}

function updateBookmarkButton() {
    const btn = document.getElementById('reader-bookmark-btn');
    if (!btn || !currentLocation) return;
    
    const isBookmarked = BookmarkManager.isBookmarked(currentBookPath, currentLocation.start.cfi);
    btn.classList.toggle('active', isBookmarked);
    
    if (isBookmarked) {
        btn.innerHTML = `
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2">
                <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
            </svg>
        `;
    } else {
        btn.innerHTML = `
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
            </svg>
        `;
    }
}

function updateProgressBar(location) {
    if (!location) return;
    
    const progressBar = document.getElementById('reader-progress-bar');
    const percentText = document.getElementById('reader-percent');
    
    // Check if locations are generated
    if (book && book.locations && book.locations.length()) {
        const percent = book.locations.percentageFromCfi(location.start.cfi);
        
        if (progressBar && percent !== undefined && !isNaN(percent)) {
            progressBar.style.width = (percent * 100) + '%';
        }
        if (percentText && percent !== undefined && !isNaN(percent)) {
            percentText.textContent = Math.round(percent * 100) + '%';
        }
    } else {
        // Locations not ready yet, try to estimate from spine position
        if (book && book.spine && location.start.index !== undefined) {
            const totalSections = book.spine.length;
            const currentIndex = location.start.index;
            const estimatedPercent = totalSections > 0 ? (currentIndex / totalSections) : 0;
            
            if (progressBar) {
                progressBar.style.width = (estimatedPercent * 100) + '%';
            }
            if (percentText) {
                percentText.textContent = Math.round(estimatedPercent * 100) + '%';
            }
        }
    }
}

function updatePageInfo(location) {
    const pageInfo = document.getElementById('reader-page-info');
    const bookTitle = document.getElementById('reader-book-title');
    
    if (bookTitle && book.packaging && book.packaging.metadata) {
        bookTitle.textContent = book.packaging.metadata.title || 'Unknown Title';
    }
    
    if (pageInfo && location) {
        // Try to get chapter title
        const currentSection = book.spine.get(location.start.cfi);
        if (currentSection) {
            const chapter = toc.find(item => currentSection.href.includes(item.href.split('#')[0]));
            pageInfo.textContent = chapter ? chapter.label : '';
        }
    }
}

function toggleTOCPanel() {
    const panel = document.getElementById('reader-toc-panel');
    const overlay = document.getElementById('reader-overlay');
    const settingsPanel = document.getElementById('reader-settings-panel');
    
    settingsPanel.classList.add('reader-panel-hidden');
    panel.classList.toggle('reader-panel-hidden');
    overlay.classList.toggle('active', !panel.classList.contains('reader-panel-hidden'));
}

function toggleSettingsPanel() {
    const panel = document.getElementById('reader-settings-panel');
    const overlay = document.getElementById('reader-overlay');
    const tocPanel = document.getElementById('reader-toc-panel');
    
    tocPanel.classList.add('reader-panel-hidden');
    panel.classList.toggle('reader-panel-hidden');
    overlay.classList.toggle('active', !panel.classList.contains('reader-panel-hidden'));
}

function closePanels() {
    document.getElementById('reader-toc-panel').classList.add('reader-panel-hidden');
    document.getElementById('reader-settings-panel').classList.add('reader-panel-hidden');
    document.getElementById('reader-overlay').classList.remove('active');
}

function closeReader() {
    // Save final position
    if (currentLocation && currentBookPath) {
        ReadingProgress.save(currentBookPath, currentLocation.start.cfi);
    }
    
    reader.style.display = 'none';
    reader.classList.remove('reader-open');
    document.body.style.overflow = '';
    
    if (book) {
        book.destroy();
        book = null;
        rendition = null;
    }
    
    currentLocation = null;
    currentBookPath = '';
    toc = [];
    
    closePanels();
    document.removeEventListener("keydown", handleKeyDown);
}

function handleKeyDown(e) {
    // Don't handle if panels are open
    const tocPanel = document.getElementById('reader-toc-panel');
    const settingsPanel = document.getElementById('reader-settings-panel');
    
    if (tocPanel && !tocPanel.classList.contains('reader-panel-hidden')) {
        if (e.key === 'Escape') {
            closePanels();
            e.preventDefault();
        }
        return;
    }
    
    if (settingsPanel && !settingsPanel.classList.contains('reader-panel-hidden')) {
        if (e.key === 'Escape') {
            closePanels();
            e.preventDefault();
        }
        return;
    }
    
    switch(e.key) {
        case 'ArrowRight':
        case ' ':
            nextPage();
            e.preventDefault();
            break;
        case 'ArrowLeft':
            prevPage();
            e.preventDefault();
            break;
        case 'Escape':
            closeReader();
            e.preventDefault();
            break;
        case 't':
        case 'T':
            toggleTOCPanel();
            e.preventDefault();
            break;
        case 'b':
        case 'B':
            toggleBookmark();
            e.preventDefault();
            break;
        case 's':
        case 'S':
            toggleSettingsPanel();
            e.preventDefault();
            break;
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

// ============================================================================
// TOUCH GESTURES
// ============================================================================

let touchStartX = 0;
let touchStartY = 0;
let touchEndX = 0;
let touchEndY = 0;
const SWIPE_THRESHOLD = 50;

if (reader) {
    reader.addEventListener('touchstart', (e) => {
        touchStartX = e.changedTouches[0].screenX;
        touchStartY = e.changedTouches[0].screenY;
    }, { passive: true });

    reader.addEventListener('touchend', (e) => {
        touchEndX = e.changedTouches[0].screenX;
        touchEndY = e.changedTouches[0].screenY;
        handleSwipe();
    }, { passive: true });
}

function handleSwipe() {
    const deltaX = touchStartX - touchEndX;
    const deltaY = touchStartY - touchEndY;
    
    // Only handle horizontal swipes
    if (Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaX) > SWIPE_THRESHOLD) {
        if (deltaX > 0) {
            nextPage();
        } else {
            prevPage();
        }
    }
}

// ============================================================================
// LAZY LOADING BOOK COVERS
// ============================================================================

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
        threshold: 0.01
    });

    bookCovers.forEach(cover => observer.observe(cover));
}

function loadBookCover(coverElement) {
    const thumbnail = coverElement.dataset.thumbnail;
    if (thumbnail) {
        coverElement.classList.add('loaded');
        return;
    }
    
    const bookPath = coverElement.dataset.book;
    
    // Encode the path properly for EPUB.js
    const encodedPath = encodeEpubPath(bookPath);
    const tempBook = ePub(encodedPath);

    tempBook.loaded.metadata.then(() => {
        return tempBook.coverUrl();
    }).then(coverUrl => {
        if (coverUrl) {
            const img = new Image();
            img.onload = () => {
                coverElement.style.backgroundImage = `url(${coverUrl})`;
                coverElement.innerHTML = '';
                coverElement.classList.add('loaded');
                saveThumbnailFromUrl(coverUrl, bookPath);
            };
            img.onerror = () => {
                throw new Error('Cover image failed to load');
            };
            img.src = coverUrl;
        } else {
            return tempBook.spine.items[0].load(tempBook.load.bind(tempBook));
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
                coverElement.classList.add('loaded');
                saveThumbnailFromUrl(firstImageSrc, bookPath);
            } else {
                throw new Error('No images found');
            }
        }
    }).catch(() => {
        coverElement.style.backgroundImage = 'none';
        coverElement.innerHTML = `
            <div class="book-cover-loading" style="background: linear-gradient(145deg, #c2703c 0%, #8b4a22 100%);">
                <svg class="w-10 h-10 text-white opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                </svg>
            </div>
        `;
    });
}

function saveThumbnailFromUrl(imageUrl, bookPath) {
    if (imageUrl.includes('books/covers/')) {
        return;
    }
    
    const img = new Image();
    img.crossOrigin = 'Anonymous';
    
    img.onload = function() {
        try {
            const canvas = document.createElement('canvas');
            canvas.width = img.width;
            canvas.height = img.height;
            
            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0);
            
            const imageData = canvas.toDataURL('image/jpeg', 0.85);
            
            fetch('save_thumbnail.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `bookPath=${encodeURIComponent(bookPath)}&imageData=${encodeURIComponent(imageData)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log(`✅ Thumbnail saved: ${bookPath}`);
                }
            })
            .catch(err => {
                console.warn('Thumbnail save error:', err);
            });
        } catch (e) {
            console.warn('Could not save thumbnail:', e.message);
        }
    };
    
    img.src = imageUrl;
}

// ============================================================================
// AUTOCOMPLETE
// ============================================================================

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func.apply(this, args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

if (searchInput) {
    searchInput.addEventListener('input', debounce(function() {
        const term = this.value.trim();
        
        if (term.length > 2) {
            fetch(`index.php?autocomplete=${encodeURIComponent(term)}`)
                .then(response => response.json())
                .then(suggestions => showAutocompleteSuggestions(suggestions))
                .catch(error => console.error('Autocomplete error:', error));
        } else {
            hideAutocompleteSuggestions();
        }
    }, 300));
}

function showAutocompleteSuggestions(suggestions) {
    let container = document.getElementById('autocomplete-suggestions');
    let backdrop = document.getElementById('autocomplete-backdrop');
    
    if (!container) {
        container = document.createElement('ul');
        container.id = 'autocomplete-suggestions';
        
        if (window.innerWidth < 768) {
            backdrop = document.createElement('div');
            backdrop.id = 'autocomplete-backdrop';
            backdrop.addEventListener('click', hideAutocompleteSuggestions);
            document.body.appendChild(backdrop);
            document.body.appendChild(container);
        } else {
            searchInput.parentNode.style.position = 'relative';
            searchInput.parentNode.appendChild(container);
        }
    }

    container.innerHTML = '';
    
    if (suggestions.length === 0) {
        hideAutocompleteSuggestions();
        return;
    }
    
    suggestions.slice(0, 8).forEach(suggestion => {
        const li = document.createElement('li');
        li.textContent = suggestion;
        
        li.addEventListener('click', () => {
            searchInput.value = suggestion;
            hideAutocompleteSuggestions();
            searchForm.submit();
        });
        
        container.appendChild(li);
    });

    container.style.display = 'block';
    
    if (backdrop && window.innerWidth < 768) {
        backdrop.style.display = 'block';
        setTimeout(() => backdrop.classList.add('active'), 10);
    }
}

function hideAutocompleteSuggestions() {
    const container = document.getElementById('autocomplete-suggestions');
    const backdrop = document.getElementById('autocomplete-backdrop');
    
    if (container) {
        container.style.display = 'none';
    }
    
    if (backdrop) {
        backdrop.classList.remove('active');
        backdrop.style.display = 'none';
    }
}

document.addEventListener('click', (e) => {
    if (e.target !== searchInput && !e.target.closest('#autocomplete-suggestions')) {
        hideAutocompleteSuggestions();
    }
});

// ============================================================================
// SORTING
// ============================================================================

if (sortBy && sortOrder) {
    [sortBy, sortOrder].forEach(element => {
        element.addEventListener('change', function() {
            const params = new URLSearchParams(window.location.search);
            params.set('sortBy', sortBy.value);
            params.set('sortOrder', sortOrder.value);
            window.location.search = params.toString();
        });
    });
}

// ============================================================================
// NOTIFICATIONS
// ============================================================================

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    
    const bgColor = type === 'error' ? '#dc2626' : '#0d7377';
    const isMobile = window.innerWidth < 640;
    
    notification.style.cssText = `
        position: fixed;
        ${isMobile ? 'bottom: 5rem; left: 1rem; right: 1rem;' : 'bottom: 2rem; left: 50%; transform: translateX(-50%); max-width: 300px;'}
        padding: 0.875rem 1.25rem;
        background: ${bgColor};
        color: white;
        border-radius: 10px;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
        z-index: 9999;
        font-size: 0.875rem;
        font-weight: 500;
        text-align: center;
        animation: notificationIn 0.3s ease;
    `;
    
    notification.textContent = message;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'notificationOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 2000);
}

// ============================================================================
// INITIALIZATION
// ============================================================================

document.addEventListener('DOMContentLoaded', () => {
    lazyLoadBookCovers();
    
    console.log('%c📚 BookShelf v7.1', 'color: #c2703c; font-size: 18px; font-weight: bold;');
    console.log('%cSpecial Characters Support Enabled', 'color: #0d7377; font-weight: bold;');
});

// Add animations
const style = document.createElement('style');
style.textContent = `
    @keyframes notificationIn {
        from {
            opacity: 0;
            transform: translateX(-50%) translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }
    }
    
    @keyframes notificationOut {
        from {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }
        to {
            opacity: 0;
            transform: translateX(-50%) translateY(20px);
        }
    }
    
    @media (max-width: 640px) {
        @keyframes notificationIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes notificationOut {
            from {
                opacity: 1;
                transform: translateY(0);
            }
            to {
                opacity: 0;
                transform: translateY(20px);
            }
        }
    }
`;
document.head.appendChild(style);