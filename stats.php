<?php
/**
 * Reading stats dashboard.
 *
 * Authed-only. Pulls aggregations from ReadingSessionRepository plus
 * reading_progress / reviews to give the user a summary of how they read.
 */

define('APP_BOOTED', true);
require __DIR__ . '/includes/bootstrap.php';

require_auth();
$user = current_user();
$uid  = (int) $user['id'];

$totals     = ReadingSessionRepository::totals($uid);
$daily      = ReadingSessionRepository::dailyTotals($uid, 30);
$topBooks   = ReadingSessionRepository::topBooks($uid, 5);
$topGenres  = ReadingSessionRepository::topGenres($uid, 5);
$recent     = ReadingSessionRepository::recentSessions($uid, 10);

// Reviews this user has written (for the "Reviews posted" counter).
$reviewStmt = db()->prepare("SELECT COUNT(*) FROM reviews WHERE user_id = ?");
$reviewStmt->execute([$uid]);
$reviewCount = (int) $reviewStmt->fetchColumn();

// Highlights count
$hlStmt = db()->prepare("SELECT COUNT(*) FROM highlights WHERE user_id = ?");
$hlStmt->execute([$uid]);
$highlightCount = (int) $hlStmt->fetchColumn();

render('library/stats', [
    'pageTitle'      => 'Your reading stats',
    'pageClass'      => 'stats-page',
    'totals'         => $totals,
    'daily'          => $daily,
    'topBooks'       => $topBooks,
    'topGenres'      => $topGenres,
    'recent'         => $recent,
    'reviewCount'    => $reviewCount,
    'highlightCount' => $highlightCount,
], 'app');
