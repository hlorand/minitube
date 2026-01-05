<?php

set_time_limit(300);

// EDIT THIS file to add your channels, format in README.md
define("CHANNELS_FILE", __DIR__ . '/channels.json');

// list of category names, update all if empty
define('UPDATE_ONLY_CATEGORIES', []);

// skip videos older than this many days
define('SKIP_OLDER_THAN_DAYS', 3);

// hide short videos?
define('HIDE_SHORTS', true);

// ---------------

// automatically generated video feed file by update.php, read by index.php
define("VIDEO_FEED_FILE", __DIR__ . '/videofeed.json');

// directory to store downloaded mp3 files for rss feed
define('DOWNLOADS_DIR', __DIR__ . '/downloads');

// directory to store cached youtube channel feed data
define('CACHE_DIR', __DIR__ . '/cache');
define('CACHE_INVALIDATION_SECONDS', 30 * 60); // 30 min

// action logger function, creates actions.log file and appends messages with timestamp
function log_action(string $message){
    $logFile = __DIR__ . '/actions.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}