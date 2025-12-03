<?php
include_once __DIR__ . '/config.php';

// create download folder for mp3 files if not exists
if (!is_dir(DOWNLOADS_DIR)) {
    mkdir(DOWNLOADS_DIR, 0755, true);
}

// get yt-dlp from official github repo overwrite current yt-dlp binary if exists (update)
function downloadYtDlp($path) {
    $ytDlpUrl = "https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp";
    file_put_contents($path, file_get_contents($ytDlpUrl));
    chmod($path, 0755);
    log_action("yt-dlp downloaded/updated.");
}

// get version tag of latest yt-dlp release from github api
function getYtDlpVersion() {
    $versionInfo = file_get_contents("https://api.github.com/repos/yt-dlp/yt-dlp/releases/latest", false, stream_context_create([
        'http' => [
            'header' => "User-Agent: MiniTube-App"
        ]
    ]));
    $data = json_decode($versionInfo, true);
    return $data['tag_name'] ?? null;
}

// download or update yt-dlp if not exists or outdated
$needDownload = false;
if (!file_exists(__DIR__ . '/yt-dlp')) {
    $needDownload = true;
    log_action("yt-dlp not found, will download.");
} else {
    // get current version from yt-dlp-version.txt
    $currentVersion = trim(file_get_contents(__DIR__ . '/yt-dlp-version.txt'));
    $latestVersion = getYtDlpVersion();
    if ($currentVersion !== $latestVersion) {
        $needDownload = true;
        log_action("yt-dlp version outdated (current: $currentVersion, latest: $latestVersion), will download new version.");
    }

}
if ($needDownload) {
    downloadYtDlp(__DIR__ . '/yt-dlp');
    file_put_contents(__DIR__ . '/yt-dlp-version.txt', getYtDlpVersion());
}

// Handle download request via GET parameter
if (isset($_GET['video'])) {
    $youtubeUrl = trim($_GET['video']);
    // validate URL format with built in and regex (for youtube domain)
    if (filter_var($youtubeUrl, FILTER_VALIDATE_URL) && preg_match('/^(https?:\/\/)?(www\.)?youtube\.com\/watch\?v=.+$/', $youtubeUrl) ) {
        $escapedUrl = escapeshellarg($youtubeUrl);
        $command = "nohup python3.10 ./yt-dlp -x --audio-format mp3 --audio-quality 9 --postprocessor-args \"-ac 1 -b:a 16k\" -o \"" . DOWNLOADS_DIR . "/%(title)s.%(ext)s\" $escapedUrl > yt-dlp.log 2>&1 &";
        exec($command);
        log_action("Download: $youtubeUrl Command: $command");
        echo "Download started asynchronously for the given URL.";
    } else {
        echo "Invalid URL provided.";
    }
}