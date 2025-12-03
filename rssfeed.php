<?php
include_once("config.php");

$downloaddir = basename( DOWNLOADS_DIR ) . '/';

// Determine scheme
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
    || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";

// Get host
$host = $_SERVER['HTTP_HOST'];

// Get current script directory path (e.g. /minitube/)
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/';

// Full base URL with scheme, host, and path
$siteUrl = $protocol . $host . $scriptDir;

$files = array_filter(scandir($downloaddir), function ($file) use ($downloaddir) {
    return strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'mp3';
});

usort($files, function($a, $b) use ($downloaddir) {
    return filemtime($downloaddir . $b) - filemtime($downloaddir . $a);
});

header("Content-Type: application/rss+xml; charset=utf-8");

echo "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
echo "<rss version=\"2.0\" xmlns:itunes=\"http://www.itunes.com/dtds/podcast-1.0.dtd\">\n";
echo "<channel>\n";
echo "  <title>_MiniTube</title>\n";
echo "  <link>{$siteUrl}</link>\n";
echo "  <language>hu-hu</language>\n";
echo "  <itunes:author>Mini Tube</itunes:author>\n";
echo "  <itunes:subtitle>MiniTube podcast</itunes:subtitle>\n";
echo "  <itunes:summary>This is an MP3 podcast feed generated in real time from downloaded YouTube videos.</itunes:summary>\n";
echo "  <description>This is an MP3 podcast feed generated in real time from downloaded YouTube videos.</description>\n";
echo "  <itunes:owner>\n";
echo "    <itunes:name>Mini Tube</itunes:name>\n";
echo "    <itunes:email>email@example.com</itunes:email>\n";
echo "  </itunes:owner>\n";
echo "  <itunes:image href='{$siteUrl}cover.jpg' />\n";
echo "  <itunes:category text=\"Technology\" />\n";

foreach ($files as $file) {
    $filePath = $downloaddir . $file;
    $fileUrl = $siteUrl . $downloaddir . rawurlencode($file);
    $fileSize = filesize($filePath);
    $fileMTime = filemtime($filePath);
    $pubDate = date(DATE_RSS, $fileMTime);

    $title = pathinfo($file, PATHINFO_FILENAME);

    echo "  <item>\n";
    echo "    <title>{$title}</title>\n";
    echo "    <itunes:title>{$title}</itunes:title>\n";
    echo "    <enclosure url=\"{$fileUrl}\" length=\"{$fileSize}\" type=\"audio/mpeg\" />\n";
    echo "    <guid>{$fileUrl}</guid>\n";
    echo "    <pubDate>{$pubDate}</pubDate>\n";
    echo "    <itunes:duration>00:00:00</itunes:duration> <!-- Extend with real duration if needed -->\n";
    echo "  </item>\n";
}

echo "</channel>\n";
echo "</rss>\n";
exit;