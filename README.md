# Simple IPTV Admin Panel (PHP 7.4)  

> ⚠️ Work in progress – not production-ready yet. Use as a base to build your own panel.

This project is a lightweight IPTV management panel written in **pure PHP 7.4** + **MySQL**, designed to manage streams you legally obtain (e.g. Pluto TV, etc.).  

Everything is plain PHP – no frameworks, no composer, easy to drop on a cheap shared host or XAMPP stack.

---

## Features (Current)

### 🔐 Admin Area (`/admin`)

- Login / logout system for admins  
- Clean dashboard with card-style stats and panels:
  - Total channels
  - Online streams
  - Active users
  - Active resellers
  - Stream status overview (online / offline / unchecked + percentages)
  - Quick stats table
  - Recent stream checks list

> Default admin (created by `db.sql`):  
> **user:** `admin` / **pass:** `admin123`  
> Change this immediately.

---

### 📺 Channel Management

**File:** `admin/channels.php`

- Add / edit / delete channels
- Assign channels to categories
- Fields:
  - Name
  - Category
  - Stream URL
  - Logo URL
  - EPG ID (`tvg-id` from M3U)
  - Active toggle
- Shows:
  - Status (`online`, HTTP errors, etc.)
  - Last check time
- Category manager (`admin/channels_categories.php`):
  - Add categories
  - Delete categories (channels are automatically set to `NULL` category)

---

### 📂 M3U Import

**File:** `admin/import_m3u.php`

Supports importing channels from:

- **Uploaded M3U file**, or  
- **Remote M3U URL**

The parser understands:

- `#EXTINF` lines
- `tvg-id="..."` → stored as `epg_id`
- `tvg-logo="..."` → stored as `logo_url`
- `group-title="..."` → mapped to **categories** (auto-creates categories)
- Channel name (after the comma)
- Next line as stream URL

Other details:

- Skips empty/broken entries
- Avoids exact duplicates (same name + same URL)
- Creates categories if `group-title` doesn’t exist yet

---

### ✅ Stream Checker

**File:** `admin/stream_checker.php`

- Individual checker for each channel
- Uses cURL (HEAD request) to test the stream URL
- Stores:
  - `status` (e.g. `online`, `http 404`, `error: ...`)
  - `last_check` (timestamp)
- Dashboard aggregates these into **online / offline / unchecked** stats

---

### 🕒 EPG Settings

**File:** `admin/epg.php`

- Stores a **global remote EPG URL** (XMLTV style), e.g.:

  ```text
  http://url/xmltv.php?username=USER&password=PASS
