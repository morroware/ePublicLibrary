<?php
/**
 * Dictionary proxy.
 *
 *   GET /api/dictionary.php?word=...
 *     → returns [{ word, phonetic, meanings: [{partOfSpeech, definitions: [{definition, example?}]}] }]
 *
 * Why proxy? Two reasons:
 *   1. Keeps the CSP tight — the reader fetches same-origin only.
 *   2. Lets us cache responses on disk and swap providers later without
 *      touching the JS.
 *
 * Upstream: dictionaryapi.dev (free, no key). Cached per-word for 30 days.
 *
 * Falls back gracefully if the upstream is unreachable or the host has
 * outbound network restricted.
 */

define('APP_BOOTED', true);
require __DIR__ . '/../includes/bootstrap.php';

$word = trim(strtolower((string) ($_GET['word'] ?? '')));
$word = preg_replace('/[^a-z\-\']/i', '', $word);
if ($word === '' || mb_strlen($word) > 60) {
    json_error('Invalid word', 400);
}

// Rate limit per-IP to deter abuse
$bucket = 'dict:ip:' . (client_ip() ?: 'unknown');
if (!rate_limit_check($bucket, 60, 60)) {
    json_error('Slow down, please.', 429);
}
rate_limit_hit($bucket, 60);

// Disk cache
$cacheDir = storage_path('cache_path') . '/dictionary';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0750, true);
}
$cacheFile = $cacheDir . '/' . substr($word, 0, 2) . '/' . $word . '.json';
if (is_file($cacheFile) && time() - filemtime($cacheFile) < 60 * 60 * 24 * 30) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, max-age=86400');
    echo file_get_contents($cacheFile);
    exit;
}

// Upstream fetch
$url = 'https://api.dictionaryapi.dev/api/v2/entries/en/' . rawurlencode($word);
$context = stream_context_create([
    'http' => [
        'timeout' => 4,
        'user_agent' => 'ePublicLibrary/1.2 (+https://github.com/morroware/ePublicLibrary)',
        'ignore_errors' => true,
    ],
]);
$raw = @file_get_contents($url, false, $context);
if ($raw === false) {
    json_error('Dictionary lookup unavailable.', 503);
}

$decoded = json_decode($raw, true);
if (!is_array($decoded) || isset($decoded['title'])) {
    // The API returns { title: "No Definitions Found", ... } on miss
    json_response([]);
}

// Slim down: word, phonetic, meanings (partOfSpeech, definitions[].definition + example)
$out = [];
foreach ($decoded as $entry) {
    $phonetic = $entry['phonetic'] ?? '';
    if ($phonetic === '' && !empty($entry['phonetics'])) {
        foreach ($entry['phonetics'] as $p) {
            if (!empty($p['text'])) { $phonetic = $p['text']; break; }
        }
    }
    $meanings = [];
    foreach ($entry['meanings'] ?? [] as $m) {
        $defs = [];
        foreach (($m['definitions'] ?? []) as $d) {
            $defs[] = array_filter([
                'definition' => $d['definition'] ?? null,
                'example'    => $d['example']    ?? null,
            ]);
            if (count($defs) >= 3) break;  // Trim — UI shows top 3
        }
        $meanings[] = [
            'partOfSpeech' => $m['partOfSpeech'] ?? '',
            'definitions'  => $defs,
        ];
    }
    $out[] = [
        'word'     => $entry['word'] ?? $word,
        'phonetic' => $phonetic,
        'meanings' => $meanings,
    ];
}

// Cache
$shardDir = dirname($cacheFile);
if (!is_dir($shardDir)) @mkdir($shardDir, 0750, true);
@file_put_contents($cacheFile, json_encode($out, JSON_UNESCAPED_UNICODE));

json_response($out);
