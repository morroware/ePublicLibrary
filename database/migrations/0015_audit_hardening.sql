-- 0015 — Audit-pass indexes for hot paths.
--
-- Adds composite / single-column indexes that earlier migrations missed:
--   - book_tags(book_id, tag_id): "tags for this book" on book.php.
--   - reading_progress(percentage): stats dashboard, Continue Reading rail.
--   - collection_books(collection_id, added_at): date-sorted shelf views.
--   - reviews(updated_at): admin "recently edited" filters.
--
-- All four indexes are pure additions, no data migration. Safe to re-run
-- only via the runner (schema_migrations records the checksum).

ALTER TABLE `book_tags`
    ADD KEY `idx_book_tags_book` (`book_id`, `tag_id`);

ALTER TABLE `reading_progress`
    ADD KEY `idx_progress_percentage` (`percentage`);

ALTER TABLE `collection_books`
    ADD KEY `idx_collection_books_added` (`collection_id`, `added_at`);

ALTER TABLE `reviews`
    ADD KEY `idx_reviews_updated` (`updated_at`);
