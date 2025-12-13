[![Buy Me A Coffee](https://img.shields.io/badge/Buy%20Me%20A%20Coffee-%24tysonworlds-ffdd00?style=for-the-badge&logo=buy-me-a-coffee&logoColor=black)](https://cash.app/$tysonworlds)

# Simple IPTV Admin Panel (PHP 7.4–8.x)

> ⚠️ Work in progress – not production-ready. Use at your own risk.

This project is a lightweight IPTV management panel written in pure PHP + MySQL. It’s designed to run on cheap shared hosting, VPS, or local stacks (XAMPP/LAMP). No frameworks. No Composer. Just drop it in and build.

**Player API is working/fixed** and the Android client consumes it cleanly.

---

## About the Android App

The Android TV / mobile app is coming along strong: **Movies + TV Shows load automatically** (no manual panel entry needed). I’ve put a lot into this, so I may not publish the full app source publicly.

If you want a leg up and want to support development, use the button above.

- Android TV ✅  
- Android phones ✅  
- More polish + features in progress ✅  

---

## Features (Current)

### 🔐 Admin Area (`/admin`)
- Admin login/logout
- Dashboard with stats + panels:
  - Total channels
  - Online streams
  - Active users
  - Active resellers
  - Stream health breakdown (online/offline/unchecked)
  - Recent stream checks
  - Quick stats table

Default admin (from `db.sql`):
- **user:** `admin`
- **pass:** `admin123`

Change it immediately.

---

### 📺 Channel Management
**File:** `admin/channels.php`

- Add / edit / delete channels
- Assign channels to categories
- Fields per channel:
  - Name
  - Category
  - Stream URL
  - Logo URL
  - EPG ID (tvg-id)
  - Active toggle
- Shows last known status (online / http code / error) + last check time

Category manager:
- `admin/channels_categories.php`
- Deleting a category will **not delete channels** — it sets category to `NULL`

---

### 📂 M3U Import
**File:** `admin/import_m3u.php`

Imports from:
- Uploaded M3U file
- Remote M3U URL

Parses:
- `tvg-id="..."` → `epg_id`
- `tvg-logo="..."` → `logo_url`
- `group-title="..."` → category auto-map (auto-creates missing categories)
- Channel name + stream URL

Importer behavior:
- Skips broken/empty entries
- Avoids exact duplicates (same name + same URL)

---

### ✅ Stream Checker
**File:** `admin/stream_checker.php`

- HEAD probe via cURL
- Stores:
  - `status` (online / http 404 / timeout / etc.)
  - `last_check`

Dashboard aggregates simple health stats for quick overview.

---

### 🕒 EPG
- Manage EPG sources in the admin area
- Import pulls XMLTV → stores guide data for fast access
- Intended for both the panel + Android client

Example XMLTV URL format:
```text
http://yourdomain.com/xmltv.php?username=USER&password=PASS
```

---

## Install
1) Upload files to your web root  
2) Import `db.sql` into MySQL  
3) Configure DB credentials in your config file  
4) Login at `/admin` and change default admin password  
5) Add channels or import an M3U  
6) Add an EPG source + run EPG import

---

## Legal
This project is meant as a starting point for your own IPTV tools. Only load streams and guide data you have the legal right to use (e.g., free/OTT sources like Pluto TV).

---

## Roadmap
- More admin tooling (packages/bouquets, reseller controls)
- Improved EPG endpoints for the Android app
- Performance and caching upgrades
- UI polish + workflow improvements

---

## Support / Updates
If you use this and want to support the work (and the Android client), hit the button up top.
