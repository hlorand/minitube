<?php
include_once __DIR__ . '/config.php';

chdir(__DIR__);

$channels = json_decode(file_get_contents(CHANNELS_FILE), true);
if ( !$channels )
    die("Error loading " . CHANNELS_FILE . " channel list.");

$videofeed = [];

class YouTubeVideo {
    public $title;
    public $videoId;
    public $publishdate;
    public $description;
    public $category;
    public $channelname;
    public $channelid;
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
	log_action("Category: " . $category . " - processing " . count($channelList) . " channels");

	// update only select categories
	if( UPDATE_ONLY_CATEGORIES == [] || UPDATE_ONLY_CATEGORIES != [] && in_array($category, UPDATE_ONLY_CATEGORIES) )
	
	// skip pockettube entries
	if( !str_starts_with($category, "ysc_") )

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
				$video->videoId = str_replace("yt:video:", "", (string)$entry->id);
				$video->title = (string)$entry->title;
				$video->publishdate = (string)$entry->published;
				$video->description = (string)$entry->children('media', true)->group->description;
				$video->category = $category;
				$video->channelname = (string)$xml->author->name;
				$video->channelid = $channel;

				$videofeed[] = $video;
			}
		}
}

// remove duplicates by videoId
$videofeed = array_values(array_reduce($videofeed, function ($carry, $item) {
	$carry[$item->videoId] = $item;
	return $carry;
}, []));

// sort by date desc
usort($videofeed, function($a, $b) {
    return strtotime($b->publishdate) - strtotime($a->publishdate);
});

file_put_contents("videofeed.json", json_encode($videofeed) );
log_action("Update completed, " . count($videofeed) . " videos in feed.");