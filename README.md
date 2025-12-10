📥 X (Twitter) Video Downloader API — v1

A simple and powerful API for extracting direct video download links from any public X/Twitter post.

This v1 endpoint supports API keys, multiple methods of authentication, and returns full JSON metadata.

🚀 API Endpoint
Base URL
https://sidman-apis.unaux.com/xvid/v1

Method

GET or POST

🔑 Authentication

v1 requires an API key.

You can pass the key in 3 different ways:

Method 1 — Query Params
?url=TWITTER_URL&api_key=YOUR_API_KEY

Method 2 — HTTP Header
X-API-Key: YOUR_API_KEY

Method 3 — POST Form Data
api_key=YOUR_API_KEY
url=TWITTER_URL

📌 Usage Examples
GET Request
https://sidman-apis.unaux.com/xvid/v1?url=TWITTER_VIDEO_URL&api_key=YOUR_API_KEY

cURL (Windows)
curl "https://sidman-apis.unaux.com/xvid/v1?url=https://x.com/user/status/123&api_key=YOUR_API_KEY"

cURL (Linux / Mac)
curl -G \
  -d "url=https://x.com/user/status/123" \
  -d "api_key=YOUR_API_KEY" \
  "https://sidman-apis.unaux.com/xvid/v1"

✅ Sample Success Response
{
  "status": "success",
  "tweet_id": "1998306622867947850",
  "tweet_url": "https://x.com/Khusshii256131/status/1998306622867947850",
  "video_urls": [
    "https://video.twimg.com/amplify_video/.../video.mp4"
  ],
  "highest_quality": "https://video.twimg.com/amplify_video/.../video.mp4",
  "thumbnail": null,
  "total_variants": 1,
  "extracted_at": "2025-12-10 12:33:03",
  "debug_info": [
    "Tweet ID: 1998306622867947850",
    "Method 1: FxTwitter API",
    "✓ FxTwitter: Found 1 video(s)"
  ],
  "api_info": {
    "api_key_used": "XvApImAs...",
    "rate_limit": "No limit currently",
    "version": "4.1"
  }
}

❌ Error Responses
Missing API key
{
  "status": "error",
  "message": "API key is required"
}

Invalid or missing URL
{
  "status": "error",
  "message": "Tweet URL is required"
}

Video not found
{
  "status": "error",
  "message": "Unable to extract video from the provided Twitter URL"
}

🎯 Features

✔ Requires API key

✔ Multiple authentication methods

✔ Returns highest-quality video

✔ Debug information included

✔ Fast, lightweight JSON API

✔ Works for any public X/Twitter video

✔ Proper rate-limit and version meta

📄 Rate Limit

Currently:

No rate limit (unlimited)

📂 File Structure
/xvid/
 ├── v1             # API v1 folder
 │   └── index.php  # Main v1 API script
 └── README.md

🛠 Self-Hosting

You can self-host this on any PHP server:

unaux.com / InfinityFree

Hostinger

Namecheap

cPanel shared hosting

VPS / Dedicated servers

Upload the /xvid/v1 folder as-is.

📄 License

MIT License

🙌 Credits

API developed by Saieed Rahman
Backend logic optimized for lightweight servers.
