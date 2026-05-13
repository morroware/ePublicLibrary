-- Denormalized review_count and avg_rating on books.
-- Kept fresh by AFTER INSERT/UPDATE/DELETE triggers on reviews.
-- Cheaper than aggregating at read time for the library and book-detail views.

ALTER TABLE `books`
    ADD COLUMN `review_count` INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN `avg_rating`   DECIMAL(3,2) NOT NULL DEFAULT 0.00,
    ADD KEY `idx_books_rating` (`avg_rating` DESC, `review_count` DESC);

-- Backfill from any existing reviews (no-op on a fresh install).
UPDATE `books` b
LEFT JOIN (
    SELECT book_id,
           COUNT(*) AS rc,
           COALESCE(AVG(rating), 0) AS ar
    FROM `reviews`
    WHERE `status` = 'published'
    GROUP BY book_id
) r ON r.book_id = b.id
SET b.review_count = COALESCE(r.rc, 0),
    b.avg_rating   = COALESCE(r.ar, 0);

DROP TRIGGER IF EXISTS `reviews_after_insert`;
DROP TRIGGER IF EXISTS `reviews_after_update`;
DROP TRIGGER IF EXISTS `reviews_after_delete`;

CREATE TRIGGER `reviews_after_insert` AFTER INSERT ON `reviews`
FOR EACH ROW
UPDATE `books`
SET review_count = (SELECT COUNT(*)                FROM `reviews` WHERE book_id = NEW.book_id AND status = 'published'),
    avg_rating   = (SELECT COALESCE(AVG(rating),0) FROM `reviews` WHERE book_id = NEW.book_id AND status = 'published')
WHERE id = NEW.book_id;

CREATE TRIGGER `reviews_after_update` AFTER UPDATE ON `reviews`
FOR EACH ROW
UPDATE `books`
SET review_count = (SELECT COUNT(*)                FROM `reviews` WHERE book_id = NEW.book_id AND status = 'published'),
    avg_rating   = (SELECT COALESCE(AVG(rating),0) FROM `reviews` WHERE book_id = NEW.book_id AND status = 'published')
WHERE id = NEW.book_id;

CREATE TRIGGER `reviews_after_delete` AFTER DELETE ON `reviews`
FOR EACH ROW
UPDATE `books`
SET review_count = (SELECT COUNT(*)                FROM `reviews` WHERE book_id = OLD.book_id AND status = 'published'),
    avg_rating   = (SELECT COALESCE(AVG(rating),0) FROM `reviews` WHERE book_id = OLD.book_id AND status = 'published')
WHERE id = OLD.book_id;
