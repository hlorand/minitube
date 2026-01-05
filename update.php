<?php
include_once __DIR__ . '/config.php';

chdir(__DIR__);

$channels = json_decode(file_get_contents(CHANNELS_FILE), true);
if ( !$channels )
    die("Error loading " . CHANNELS_FILE . " channel list.");

function isShort($id) {
    $ch = curl_init("https://www.youtube.com/shorts/$id");
    curl_setopt_array($ch, [
        CURLOPT_NOBODY => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 3,        // 1 sec total timeout
        CURLOPT_CONNECTTIMEOUT => 3  // 1 sec connect timeout
    ]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code === 200;
}

$videofeed = [];

class YouTubeVideo {
    public $title;
    public $videoid;
    public $publishdate;
    public $description;
    public $category;
    public $channelname;
    public $channelid;
    public $isshort;
}

// Downloads a single channel feed with caching
function fetchWithCache(string $channel) {
    
    if (!is_dir(CACHE_DIR)) 
        mkdir(CACHE_DIR, 0755, true);

    $cacheFile = CACHE_DIR . "/$channel.xml";

    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < CACHE_INVALIDATION_SECONDS) {
        return file_get_contents($cacheFile);
    }

    if( $channel[0] == 'U' )
        $content = @file_get_contents("https://www.youtube.com/feeds/videos.xml?channel_id=$channel");
    else
        $content = @file_get_contents("https://www.youtube.com/feeds/videos.xml?user=$channel");

    if ($content !== false) 
        file_put_contents($cacheFile, $content);

    return $content;
}

foreach ($channels as $category => $channelList) {

	// skip pockettube entries
	if( str_starts_with($category, "ysc_") ) 
		continue;
	
	log_action("Category: " . $category . " - processing " . count($channelList) . " channels");

	// update only select categories
	if( UPDATE_ONLY_CATEGORIES == [] || UPDATE_ONLY_CATEGORIES != [] && in_array($category, UPDATE_ONLY_CATEGORIES) )
	
		foreach ($channelList as $channel) {
			log_action($channel . " - fetching feed");

			$xmlContent = fetchWithCache($channel);
			if ($xmlContent === FALSE) {
				log_action($channel . " - cannot fetch, skipped");
				continue;
			}

			$xml = simplexml_load_string($xmlContent);
			if ($xml === FALSE) {
				log_action($channel . " - cannot parse xml, skipped");
				continue;
			}


			foreach ($xml->entry as $entry) {

				// skip if old
				$publishdate = new DateTime((string)$entry->published);
				$now = new DateTime();
				if ( $publishdate->diff($now)->days > SKIP_OLDER_THAN_DAYS)
					continue;

				$video = new YouTubeVideo();
				$video->videoid = str_replace("yt:video:", "", (string)$entry->id);
				$video->title = (string)$entry->title;
				$video->publishdate = (string)$entry->published;
				$video->description = (string)$entry->children('media', true)->group->description;
				$video->category = $category;
				$video->channelname = (string)$xml->author->name;
				$video->channelid = $channel;
				$video->isshort = isShort($video->videoid);

				$videofeed[] = $video;
			}
		}
}

// remove duplicates by videoid
$videofeed = array_values(array_reduce($videofeed, function ($carry, $item) {
	$carry[$item->videoid] = $item;
	return $carry;
}, []));

// sort by date desc
usort($videofeed, function($a, $b) {
    return strtotime($b->publishdate) - strtotime($a->publishdate);
});

file_put_contents("videofeed.json", json_encode($videofeed) );
log_action("Update completed, " . count($videofeed) . " videos in feed.");