<?php
/**
 * Twitter/X Video Downloader API - Production Ready
 * Version: 4.0 - Working Implementation
 * Uses reliable extraction methods with proper fallbacks
 */

header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

class TwitterVideoExtractor {
    
    private $debugInfo = [];
    private $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36';
    
    public function extractVideo($tweetUrl) {
        $tweetId = $this->extractTweetId($tweetUrl);
        if (!$tweetId) {
            return ['status' => 'error', 'message' => 'Invalid Twitter/X URL format'];
        }
        
        $this->debugInfo[] = "Tweet ID: $tweetId";
        
        // Method 1: FxTwitter API (most reliable)
        $result = $this->tryFxTwitter($tweetId, $tweetUrl);
        if ($result['success']) {
            return $this->formatResponse($tweetId, $tweetUrl, $result['data']);
        }
        
        // Method 2: VxTwitter API
        $result = $this->tryVxTwitter($tweetId, $tweetUrl);
        if ($result['success']) {
            return $this->formatResponse($tweetId, $tweetUrl, $result['data']);
        }
        
        // Method 3: Direct syndication endpoint
        $result = $this->trySyndicationAPI($tweetId);
        if ($result['success']) {
            return $this->formatResponse($tweetId, $tweetUrl, $result['data']);
        }
        
        // Method 4: Twitter API v1.1 (public endpoint)
        $result = $this->tryTwitterAPIv1($tweetId);
        if ($result['success']) {
            return $this->formatResponse($tweetId, $tweetUrl, $result['data']);
        }
        
        // Method 5: Web scraping with enhanced patterns
        $result = $this->tryWebScraping($tweetUrl);
        if ($result['success']) {
            return $this->formatResponse($tweetId, $tweetUrl, $result['data']);
        }
        
        // Method 6: Third-party services
        $result = $this->tryThirdPartyServices($tweetId);
        if ($result['success']) {
            return $this->formatResponse($tweetId, $tweetUrl, $result['data']);
        }
        
        return [
            'status' => 'error',
            'message' => 'Could not extract video. The tweet may not contain video or is protected.',
            'tweet_id' => $tweetId,
            'debug_info' => $this->debugInfo
        ];
    }
    
    private function extractTweetId($url) {
        $patterns = [
            '/(?:twitter\.com|x\.com)\/(?:\w+)\/status\/(\d+)/i',
            '/(?:twitter\.com|x\.com)\/i\/web\/status\/(\d+)/i',
            '/^(\d+)$/'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }
        return null;
    }
    
    private function tryFxTwitter($tweetId, $tweetUrl) {
        $this->debugInfo[] = "Method 1: FxTwitter API";
        
        // FxTwitter provides direct video links
        $fxUrl = str_replace(['twitter.com', 'x.com'], 'api.fxtwitter.com', $tweetUrl);
        
        $response = $this->makeRequest($fxUrl, [
            'Accept: application/json',
            'User-Agent: ' . $this->userAgent
        ]);
        
        if ($response['success']) {
            $data = json_decode($response['content'], true);
            
            if ($data && isset($data['tweet']['media']['videos'])) {
                $videos = [];
                foreach ($data['tweet']['media']['videos'] as $video) {
                    if (isset($video['url'])) {
                        $videos[] = $video['url'];
                    }
                }
                
                if (!empty($videos)) {
                    $thumbnail = $data['tweet']['media']['photos'][0]['url'] ?? null;
                    $this->debugInfo[] = "✓ FxTwitter: Found " . count($videos) . " video(s)";
                    return ['success' => true, 'data' => ['videos' => $videos, 'thumbnail' => $thumbnail]];
                }
            }
        }
        
        $this->debugInfo[] = "✗ FxTwitter: Failed";
        return ['success' => false];
    }
    
    private function tryVxTwitter($tweetId, $tweetUrl) {
        $this->debugInfo[] = "Method 2: VxTwitter API";
        
        // VxTwitter is another reliable service
        $vxUrl = str_replace(['twitter.com', 'x.com'], 'api.vxtwitter.com', $tweetUrl);
        
        $response = $this->makeRequest($vxUrl, [
            'Accept: application/json',
            'User-Agent: ' . $this->userAgent
        ]);
        
        if ($response['success']) {
            $data = json_decode($response['content'], true);
            
            if ($data && isset($data['media_extended'])) {
                $videos = [];
                foreach ($data['media_extended'] as $media) {
                    if (isset($media['type']) && $media['type'] === 'video' && isset($media['url'])) {
                        $videos[] = $media['url'];
                    }
                }
                
                if (!empty($videos)) {
                    $thumbnail = $data['media_extended'][0]['thumbnail_url'] ?? null;
                    $this->debugInfo[] = "✓ VxTwitter: Found " . count($videos) . " video(s)";
                    return ['success' => true, 'data' => ['videos' => $videos, 'thumbnail' => $thumbnail]];
                }
            }
        }
        
        $this->debugInfo[] = "✗ VxTwitter: Failed";
        return ['success' => false];
    }
    
    private function trySyndicationAPI($tweetId) {
        $this->debugInfo[] = "Method 3: Syndication API";
        
        $url = "https://cdn.syndication.twimg.com/tweet-result?id=$tweetId&lang=en&token=a";
        
        $response = $this->makeRequest($url, [
            'Accept: application/json',
            'Origin: https://platform.twitter.com',
            'Referer: https://platform.twitter.com/'
        ]);
        
        if ($response['success']) {
            $data = json_decode($response['content'], true);
            
            if ($data && isset($data['mediaDetails'])) {
                $videos = [];
                $thumbnail = null;
                
                foreach ($data['mediaDetails'] as $media) {
                    if (isset($media['video_info']['variants'])) {
                        foreach ($media['video_info']['variants'] as $variant) {
                            if (isset($variant['url']) && strpos($variant['url'], '.mp4') !== false) {
                                $videos[] = $variant['url'];
                            }
                        }
                    }
                    
                    if (!$thumbnail && isset($media['media_url_https'])) {
                        $thumbnail = $media['media_url_https'];
                    }
                }
                
                if (!empty($videos)) {
                    $this->debugInfo[] = "✓ Syndication: Found " . count($videos) . " video(s)";
                    return ['success' => true, 'data' => ['videos' => $videos, 'thumbnail' => $thumbnail]];
                }
            }
        }
        
        $this->debugInfo[] = "✗ Syndication: Failed";
        return ['success' => false];
    }
    
    private function tryTwitterAPIv1($tweetId) {
        $this->debugInfo[] = "Method 4: Twitter API v1.1";
        
        // Using tweet.json endpoint (sometimes publicly accessible)
        $url = "https://api.twitter.com/1.1/statuses/show.json?id=$tweetId&include_entities=true&tweet_mode=extended";
        
        $response = $this->makeRequest($url, [
            'Accept: application/json',
            'User-Agent: ' . $this->userAgent
        ]);
        
        if ($response['success']) {
            $data = json_decode($response['content'], true);
            
            if ($data) {
                $extracted = $this->extractMediaFromTweetData($data);
                if (!empty($extracted['videos'])) {
                    $this->debugInfo[] = "✓ API v1.1: Found " . count($extracted['videos']) . " video(s)";
                    return ['success' => true, 'data' => $extracted];
                }
            }
        }
        
        $this->debugInfo[] = "✗ API v1.1: Failed";
        return ['success' => false];
    }
    
    private function tryWebScraping($tweetUrl) {
        $this->debugInfo[] = "Method 5: Web Scraping";
        
        $urls = [
            $tweetUrl,
            str_replace('twitter.com', 'x.com', $tweetUrl),
            str_replace('x.com', 'twitter.com', $tweetUrl)
        ];
        
        foreach ($urls as $url) {
            $response = $this->makeRequest($url, [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.9',
                'Sec-Fetch-Dest: document',
                'Sec-Fetch-Mode: navigate',
                'Upgrade-Insecure-Requests: 1'
            ]);
            
            if ($response['success']) {
                $extracted = $this->extractFromHTML($response['content']);
                if (!empty($extracted['videos'])) {
                    $this->debugInfo[] = "✓ Web Scraping: Found " . count($extracted['videos']) . " video(s)";
                    return ['success' => true, 'data' => $extracted];
                }
            }
        }
        
        $this->debugInfo[] = "✗ Web Scraping: Failed";
        return ['success' => false];
    }
    
    private function tryThirdPartyServices($tweetId) {
        $this->debugInfo[] = "Method 6: Third-party services";
        
        // Try various public API services
        $services = [
            "https://twitsave.com/info?url=https://twitter.com/i/status/$tweetId",
            "https://ssstwitter.com/info?url=https://twitter.com/i/status/$tweetId"
        ];
        
        foreach ($services as $serviceUrl) {
            $response = $this->makeRequest($serviceUrl, [
                'Accept: application/json, text/html',
                'User-Agent: ' . $this->userAgent
            ]);
            
            if ($response['success']) {
                // Try to extract video URLs from response
                $extracted = $this->extractFromHTML($response['content']);
                if (!empty($extracted['videos'])) {
                    $this->debugInfo[] = "✓ Third-party: Found " . count($extracted['videos']) . " video(s)";
                    return ['success' => true, 'data' => $extracted];
                }
            }
        }
        
        $this->debugInfo[] = "✗ Third-party: Failed";
        return ['success' => false];
    }
    
    private function extractMediaFromTweetData($tweet) {
        $videos = [];
        $thumbnail = null;
        
        // Check extended_entities (primary location for media)
        if (isset($tweet['extended_entities']['media'])) {
            foreach ($tweet['extended_entities']['media'] as $media) {
                if ($media['type'] === 'video' || $media['type'] === 'animated_gif') {
                    if (isset($media['video_info']['variants'])) {
                        foreach ($media['video_info']['variants'] as $variant) {
                            if ($variant['content_type'] === 'video/mp4') {
                                $videos[] = $variant['url'];
                            }
                        }
                    }
                    if (!$thumbnail && isset($media['media_url_https'])) {
                        $thumbnail = $media['media_url_https'];
                    }
                }
            }
        }
        
        // Check entities as fallback
        if (empty($videos) && isset($tweet['entities']['media'])) {
            foreach ($tweet['entities']['media'] as $media) {
                if (isset($media['video_info']['variants'])) {
                    foreach ($media['video_info']['variants'] as $variant) {
                        if (isset($variant['url']) && strpos($variant['url'], '.mp4') !== false) {
                            $videos[] = $variant['url'];
                        }
                    }
                }
            }
        }
        
        return ['videos' => array_unique($videos), 'thumbnail' => $thumbnail];
    }
    
    private function extractFromHTML($html) {
        $videos = [];
        $thumbnail = null;
        
        // Method 1: Extract from JSON-LD structured data
        if (preg_match_all('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $matches)) {
            foreach ($matches[1] as $json) {
                $data = json_decode($json, true);
                if ($data) {
                    $found = $this->findVideosInArray($data);
                    $videos = array_merge($videos, $found['videos']);
                    if (!$thumbnail && $found['thumbnail']) {
                        $thumbnail = $found['thumbnail'];
                    }
                }
            }
        }
        
        // Method 2: Extract from inline JavaScript objects
        $patterns = [
            '/video_info["\']?\s*:\s*\{[^}]*variants["\']?\s*:\s*\[([^\]]+)\]/is',
            '/variants["\']?\s*:\s*(\[\s*\{[^\]]+\}\s*\])/is',
            '/"contentUrl"\s*:\s*"([^"]*\.mp4[^"]*)"/i',
            '/"url"\s*:\s*"(https:\/\/video\.twimg\.com[^"]*\.mp4[^"]*)"/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                foreach ($matches[1] as $match) {
                    // Try to parse as JSON
                    $data = json_decode($match, true);
                    if (is_array($data)) {
                        foreach ($data as $item) {
                            if (isset($item['url']) && $this->isValidVideoUrl($item['url'])) {
                                $videos[] = $item['url'];
                            }
                        }
                    } else if ($this->isValidVideoUrl($match)) {
                        $videos[] = $match;
                    }
                }
            }
        }
        
        // Method 3: Direct URL extraction with strict patterns
        $urlPatterns = [
            '/https:\/\/video\.twimg\.com\/ext_tw_video\/\d+\/pu\/vid\/\d+x\d+\/[\w-]+\.mp4(?:\?[^"\s]*)?/i',
            '/https:\/\/video\.twimg\.com\/ext_tw_video\/\d+\/pu\/pl\/[\w-]+\.m3u8(?:\?[^"\s]*)?/i',
            '/https:\/\/video\.twimg\.com\/amplify_video\/\d+\/vid\/\d+x\d+\/[\w-]+\.mp4(?:\?[^"\s]*)?/i',
            '/https:\/\/video\.twimg\.com\/tweet_video\/[\w-]+\.mp4(?:\?[^"\s]*)?/i'
        ];
        
        foreach ($urlPatterns as $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                foreach ($matches[0] as $url) {
                    $url = html_entity_decode($url);
                    $url = str_replace(['\\/', '\\u002F', '\\/'], '/', $url);
                    if ($this->isValidVideoUrl($url)) {
                        $videos[] = $url;
                    }
                }
            }
        }
        
        // Extract thumbnail
        if (preg_match('/https:\/\/pbs\.twimg\.com\/(?:media|ext_tw_video_thumb)\/[\w-]+\.(?:jpg|png)(?::large)?/i', $html, $match)) {
            $thumbnail = $match[0];
        }
        
        return [
            'videos' => array_unique(array_filter($videos)),
            'thumbnail' => $thumbnail
        ];
    }
    
    private function findVideosInArray($data, &$videos = [], &$thumbnail = null) {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if ($key === 'contentUrl' && is_string($value) && $this->isValidVideoUrl($value)) {
                    $videos[] = $value;
                }
                if ($key === 'thumbnailUrl' && is_string($value) && !$thumbnail) {
                    $thumbnail = $value;
                }
                if (is_array($value) || is_object($value)) {
                    $this->findVideosInArray($value, $videos, $thumbnail);
                }
                if (is_string($value) && $this->isValidVideoUrl($value)) {
                    $videos[] = $value;
                }
            }
        }
        
        return ['videos' => array_unique($videos), 'thumbnail' => $thumbnail];
    }
    
    private function isValidVideoUrl($url) {
        if (!is_string($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        // Must contain video extension
        if (strpos($url, '.mp4') === false && strpos($url, '.m3u8') === false) {
            return false;
        }
        
        // Must be from trusted domains
        $validDomains = ['twimg.com', 'twitter.com', 'x.com'];
        $isValidDomain = false;
        foreach ($validDomains as $domain) {
            if (strpos($url, $domain) !== false) {
                $isValidDomain = true;
                break;
            }
        }
        if (!$isValidDomain) {
            return false;
        }
        
        // Exclude promotional/UI videos
        $excludePatterns = [
            '/inapp[_-]/i', '/radar[_-]promo/i', '/grok[_-]/i',
            '/promo[_-]/i', '/sticky\/videos/i', '/onboarding/i',
            '/welcome[_-]/i', '/intro[_-]/i', '/tutorial/i',
            '/placeholder/i', '/sample[_-]/i', '/demo[_-]/i',
            '/ui[_-]video/i', '/app[_-]promo/i'
        ];
        
        foreach ($excludePatterns as $pattern) {
            if (preg_match($pattern, $url)) {
                return false;
            }
        }
        
        return true;
    }
    
    private function makeRequest($url, $headers = [], $userAgent = null) {
        if (!$userAgent) {
            $userAgent = $this->userAgent;
        }
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_USERAGENT => $userAgent,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_ENCODING => 'gzip, deflate',
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1
        ]);
        
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        return [
            'success' => ($content !== false && empty($error) && $httpCode >= 200 && $httpCode < 400),
            'content' => $content,
            'http_code' => $httpCode
        ];
    }
    
    private function formatResponse($tweetId, $tweetUrl, $data) {
        $videos = array_unique($data['videos']);
        
        if (empty($videos)) {
            return [
                'status' => 'error',
                'message' => 'No valid video URLs extracted',
                'tweet_id' => $tweetId,
                'debug_info' => $this->debugInfo
            ];
        }
        
        // Sort by quality (resolution-based)
        usort($videos, function($a, $b) {
            preg_match('/\/(\d+)x(\d+)\//', $a, $ma);
            preg_match('/\/(\d+)x(\d+)\//', $b, $mb);
            
            $qa = isset($ma[1]) ? (int)$ma[1] * (int)$ma[2] : 0;
            $qb = isset($mb[1]) ? (int)$mb[1] * (int)$mb[2] : 0;
            
            // Fallback: check bitrate indicators
            if ($qa === 0) preg_match('/(\d+)p?\.mp4/', $a, $m) && $qa = (int)$m[1] * 1000;
            if ($qb === 0) preg_match('/(\d+)p?\.mp4/', $b, $m) && $qb = (int)$m[1] * 1000;
            
            return $qb - $qa;
        });
        
        return [
            'status' => 'success',
            'tweet_id' => $tweetId,
            'tweet_url' => $tweetUrl,
            'video_urls' => array_values($videos),
            'highest_quality' => $videos[0],
            'thumbnail' => $data['thumbnail'],
            'total_variants' => count($videos),
            'extracted_at' => date('Y-m-d H:i:s'),
            'debug_info' => $this->debugInfo
        ];
    }
}

// ============================================================================
// MAIN EXECUTION
// ============================================================================

try {
    // Validate input
    if (!isset($_GET['url']) || empty(trim($_GET['url']))) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Missing required parameter: url',
            'usage' => 'GET /?url={TWITTER_URL}',
            'examples' => [
                'https://twitter.com/username/status/123456789',
                'https://x.com/username/status/123456789'
            ]
        ], JSON_PRETTY_PRINT);
        exit;
    }
    
    $tweetUrl = trim($_GET['url']);
    
    // Validate URL format
    if (!preg_match('/(twitter\.com|x\.com)/i', $tweetUrl)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid URL. Must be a Twitter or X URL.',
            'provided_url' => $tweetUrl
        ], JSON_PRETTY_PRINT);
        exit;
    }
    
    // Extract video
    $extractor = new TwitterVideoExtractor();
    $result = $extractor->extractVideo($tweetUrl);
    
    // Set appropriate HTTP status code
    http_response_code($result['status'] === 'success' ? 200 : 404);
    
    // Return JSON response
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Internal server error',
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}
?>