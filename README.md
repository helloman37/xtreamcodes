[![Buy Me A Coffee](https://img.shields.io/badge/Buy%20Me%20A%20Coffee-%24tysonworlds-ffdd00?style=for-the-badge&logo=buy-me-a-coffee&logoColor=black)](https://cash.app/$tysonworlds)

The app is coming along great. Movies and TV shows now load automatically, so there's no need to add them manually in the panel. I've put a lot of time into this project, so I may not release full source code publicly, but if you'd like a leg up and want to support the work, you can use the Buy Me a Coffee button above.

# Simple IPTV Admin Panel (PHP 7.4–8)
(Keep in mind, player_api is screwed. The app I mention? Works. Will be released once finished. ENJOY!!!!)

About the app at this point: It works on Android TV, and Android phones. I am implimenting some features to make it work great before releasing it here. The full source? hmmm I do not know if I will release that, maybe just a apk. 

I know the answer already, should I release the full source code here? Or... maybe Codecanyon that thing?


> ⚠️ Work in progress – not production-ready yet. Use this as a base to build your own panel.

This project is a lightweight IPTV management panel written in pure PHP 7.4 with MySQL, designed to manage streams you legally obtain (for example Pluto TV and other free OTT sources).

Everything is plain PHP: no frameworks, no Composer, easy to drop on a cheap shared host, VPS, or a local XAMPP stack.

With the Version 7 re-release, an Android TV / mobile app is also being built to work alongside this panel. The idea is that the panel does all the admin work (channels, categories, EPG, users, resellers) while the Android client consumes the data cleanly for end users.

---

## Features (Current)

### 🔐 Admin Area (`/admin`)

Login / logout system for admins.

Dashboard with card-style stats and panels:

- Total channels  
- Online streams  
- Active users  
- Active resellers  
- Stream status overview (online / offline / unchecked, with simple percentages)  
- Quick stats table  
- Recent stream checks list  

Default admin (created by `db.sql`):

- user: `admin`  
- pass: `admin123`  

Change this immediately after installing.

---

### 📺 Channel Management

**File:** `admin/channels.php`

Add, edit, and delete channels.  
Assign channels to categories.

Fields per channel:

- Name  
- Category  
- Stream URL  
- Logo URL  
- EPG ID (tvg-id from M3U)  
- Active toggle  

Each channel row shows the last known status (online, HTTP error codes, or other error text) and the last check time.

Category manager lives in `admin/channels_categories.php`.

You can:

- Add new categories  
- Delete existing categories  

When a category is deleted, channels in that category are not removed; their category is set to `NULL` so they stay in the system.

---

### 📂 M3U Import

**File:** `admin/import_m3u.php`

Supports importing channels from either an uploaded M3U file or a remote M3U URL.

The M3U parser understands standard `#EXTINF` lines and extracts:

- `tvg-id="..."` → stored as `epg_id`  
- `tvg-logo="..."` → stored as `logo_url`  
- `group-title="..."` → mapped to channel categories (auto-creates categories if needed)  
- Channel name (the text after the comma)  
- The next line after `#EXTINF` as the stream URL  

The importer:

- Skips empty or obviously broken entries  
- Avoids exact duplicates (same name and same URL)  

---

### ✅ Stream Checker

**File:** `admin/stream_checker.php`

Provides an individual stream checker for each channel.

Uses cURL with a HEAD request to quickly test the stream URL.

Stores:

- `status` (for example `online`, `http 404`, `error: timeout`, etc.)  
- `last_check` (timestamp of the last probe)  

The dashboard aggregates this into simple online / offline / unchecked stats for a quick health overview.

---

### 🕒 EPG Settings

**File:** `admin/epg.php`

Stores a single global remote EPG URL in XMLTV format. Example:

```text
http://url/xmltv.php?username=USER&password=PASS
```

This global EPG link is intended to be shared between the web panel and the Android app so that both can read the same guide data.

Future versions are planned to expose EPG JSON endpoints for the Android client, allowing the app to pull guide data directly from this panel instead of hard-coding XMLTV URLs.

---

## Notes

This codebase is meant as a starting point for your own IPTV tools. You are responsible for the content you load into it. Only use streams and EPG sources that you have the legal right to use.

The Android app that will accompany this panel is still in development. API endpoints, authentication flows, and EPG output formats may change as the app evolves.
