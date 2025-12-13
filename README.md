[![Buy Me A Coffee](https://img.shields.io/badge/Buy%20Me%20A%20Coffee-%24tysonworlds-ffdd00?style=for-the-badge&logo=buy-me-a-coffee&logoColor=black)](https://cash.app/$tysonworlds)

# Simple IPTV Admin Panel (PHP 7.4–8.x)

> ⚠️ Work in progress – use at your own risk. This is a dev-friendly base, not a turnkey “production panel”.

Pure PHP + MySQL IPTV panel + a companion Android app. No frameworks. No Composer. Easy to drop on shared hosting or a VPS.

**Player API is working/fixed** (Xtream-style) and the Android client consumes it cleanly.

---

## Android App Download (Released)

The Android TV / Android phone app is **released for download**.

- **Download APK (latest):** *(add your direct link here — GitHub Releases recommended)*
- **Changelog / versions:** *(optional)*

I’ve put a lot into this project. I may release the APK publicly but **keep full app source private**.

---

## What’s New / What We Changed

This repo has been upgraded heavily versus the early “basic panel” version:

### ✅ Player API / Xtream Compatibility
- Fixed `player_api.php` responses and compatibility improvements.
- Playlist endpoint support via `get.php`.
- Xtream-style routes supported (with rewrites): `/live/...` and `/seg/...` (Apache/Nginx examples included).

### 🔒 Security, Anti-Share, Limits
- **Hard max_connections enforcement** (new streams denied when limit is reached).
- **Per-session stream token rotation** (sharing a playlist URL won’t keep segments working).
- **Device binding support** (optional lock / device tracking for accounts).
- Rate limiting added (to protect login / API / playlist pulls).
- **CSRF protection** added to admin actions (like EPG source changes).
- Audit logging for key actions (auth fails, limit hits, admin actions).

### 🗓️ EPG System (Real Pipeline)
- EPG source management in admin (add / enable / disable / delete).
- EPG import pipeline (XMLTV ingest → stored/cached for faster use).
- `/xmltv.php` protected (username/password) + caching.
- **Admin-wrapped EPG import page** (`/admin/epg_import.php`) with proper layout (no “plain white page”).

### 📡 Stream Health / Probe Tools
- Stream probe script + admin UI wrapper.
- `/admin/stream_probe.php` is linked under **System** in the admin menu and matches the dashboard look.

### 🧹 Bulk Delete Fix (FK-safe)
- Fixed bulk delete to avoid `TRUNCATE channels` errors with foreign keys (`package_channels → channels`).
- Child tables cleared first; parent table uses safe delete + auto-increment reset.

### 🧭 Admin UI Improvements
- Sidebar changed to **categorized accordion**:
  - Categories stay pinned.
  - Submenus expand/collapse per section.
  - Active page auto-opens the right group.
- Admin pages styled consistently (dashboard-like wrapper for tools pages).

---

## Features (Current)

### 🔐 Admin Area (`/admin`)
- Admin login/logout
- Dashboard stats:
  - Total channels
  - Online/offline/unchecked health stats
  - Active users/resellers
  - Recent stream checks

Default admin (from `db.sql`):
- **user:** `admin`
- **pass:** `admin123`

Change it immediately.

### 📺 Channel Management
- Add / edit / delete channels
- Categories (create/delete)
- Fields:
  - Name, Category, Stream URL, Logo URL, EPG ID (tvg-id), Active toggle
- Stream status + last check timestamp per channel

### 📂 M3U Import
- Import from uploaded M3U or remote M3U URL
- Parses: tvg-id, tvg-logo, group-title, name, URL
- Skips broken entries; avoids exact duplicates

### ✅ Stream Checker
- Fast cURL probe (HEAD)
- Stores `status` + `last_check`

### 🕒 EPG
- Manage multiple EPG sources
- Import + cache guide data for fast access
- Protected XMLTV endpoint:
```text
http://yourdomain.com/xmltv.php?username=USER&password=PASS
```

---

## Install

1) Upload files to your web root  
2) Import `db.sql` into MySQL  
3) Set DB credentials in your config file  
4) Login at `/admin` and change default admin password  
5) Add channels (or import an M3U)  
6) Add an EPG source and run import  

### Rewrites (important for Xtream-style URLs)
If you want `/live/...` and `/seg/...` to work, enable the provided Apache/Nginx rewrite rules.

---

## Cron (Recommended)

Run probes + EPG import on a schedule (examples):

```bash
*/10 * * * * php /path/to/scripts/stream_probe.php --limit=400 >/dev/null 2>&1
0 */6 * * * php /path/to/scripts/epg_import.php --flush=0 >/dev/null 2>&1
```

---

## Legal

This project is a starting point for IPTV tools. Only load streams/EPG data you have the legal right to use (e.g., free/OTT sources like Pluto TV).

---

## Support / Updates

If this saves you time and you want to support the work (especially the Android client), use the button at the top.
