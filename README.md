[![Buy Me A Coffee](https://img.shields.io/badge/Buy%20Me%20A%20Coffee-%24tysonworlds-ffdd00?style=for-the-badge&logo=buy-me-a-coffee&logoColor=black)](https://cash.app/$tysonworlds)
😁

# Simple IPTV Admin Panel (PHP 7.4–8.x)

> ⚠️ Work in progress – dev-friendly base, not a turnkey production panel.

Pure PHP + MySQL IPTV panel + companion Android app. No frameworks. No Composer. Shared-hosting friendly.

**Xtream-style Player API is working/fixed** and the Android client consumes it cleanly.

---

## Android App Download (Released)

The Android TV / Android phone app is **released for download**.

I’ve put a lot into this project. While the APK is public, I’m keeping the **full app source private**.

---

## What’s New (Recent Work)

This repo has been upgraded heavily versus the early “basic panel” version:

### ✅ Web Installer (Styled Wizard)
- **Auto-redirects to `/install/`** if not installed.
- **Step-by-step wizard** with Next/Back (server-rendered; doesn’t break if JS fails).
- Writes directly to **`config.php`** (no manual edits required).
- Sets:
  - DB host/name/user/pass
  - `base_url`
  - Admin username/password (shown on Finish)
  - Optional **PayPal** + **CashApp** fields (not required)
- Runs schema + migrations cleanly and creates an `installed.lock`.

### ✅ Endpoint / URL Fixes
- Dashboard endpoint references fixed:
  - `/get.php` (not `/panel/get.php`)
  - `/xmltv.php` (not `/panel/xmltv.php`)

### ✅ Reseller Credits Display (Header)
Resellers can now always see exactly how many credits they have:

- The top header “Credits” label is now a **live badge**:
  - `Credits: 123`
  - Indicator dot is **green** when credits > 0
  - Indicator dot is **red** when credits = 0
- Implemented by injecting the reseller’s credit balance into the existing HTML topbar template.

> Note: Admins don’t “have” credits — this display is intended for reseller-facing pages.

### ✅ Fail Videos (System → Fail Videos)
Admins can now set custom videos that play when a user fails authentication or is blocked — instead of returning plain text like **“Invalid credentials”**.

- Admin page: **System → Fail Videos**
- Supported formats (any URL ending with):
  - `.mp4`
  - `.m3u8`
  - `.ts`
- Enforced across common entrypoints:
  - `get.php`
  - Stream endpoints (e.g. `/live/...`, `/movie/...`, `/series/...`)
  - Segment-based streaming can use **`.ts`** fail videos for best compatibility

How it works:
- When a request fails (invalid login / expired / banned / limit reached), the server returns a **302 redirect** to your configured fail video URL.
- If no fail video is configured, the system falls back to the original behavior (plain error response).

---

## Features (Current)

### 🔐 Admin Area (`/admin`)
- Admin login/logout
- Dashboard stats:
  - Total channels
  - Online/offline/unchecked health stats
  - Active users/resellers
  - Recent stream checks

> Default admin from early SQL files may exist in older installs. **Change it immediately.**

### 🧾 Abuse Controls (Ban System)
- Ban by **IP** and/or **username/account** for abuse.
- Enforced across:
  - `player_api.php`
  - `get.php`
  - `xmltv.php`
  - streaming endpoints (`/live`, `/seg`, etc.)
- Admin UI: **Abuse Bans** page with quick add/remove.

### 🧾 Telemetry + Audit Logs (Abuse Visibility)
- Request logging table (`request_logs`) for API + stream hits.
- Logs:
  - username (when present), IP, UA, device_id
  - endpoint/action
  - result reason (`auth_fail`, `banned_ip`, `rate_limited`, `max_connections`, etc.)
  - response time
- Admin UI: **System → Telemetry**
  - Top IPs / top failures
  - Suspicious accounts (many IPs in a short window)
  - Quick actions (ban IP/user)

### 💳 Billing Reports
New **Billing → Reports** page:
- **Monthly revenue grid** (up to last 12 months)
- “Up for renewal” sections (users nearing expiry / renewal windows)

### 📺 Channel Management
- Add / edit / delete channels
- Categories
- Fields:
  - Name, Category, Stream URL, Logo URL, EPG ID (tvg-id), Active toggle
- Stream status + last check timestamp per channel

### 📂 M3U Import
- Import from uploaded M3U or remote URL
- Parses: tvg-id, tvg-logo, group-title, name, URL
- Skips broken entries; supports duplicate-friendly workflows

### ✅ Stream Checker
- Fast cURL probe (HEAD)
- Stores `status` + `last_check`

### 🕒 EPG System
- Multiple EPG sources (add/enable/disable/delete)
- Import + cache guide data for fast access
- Protected XMLTV endpoint:
```text
http://yourdomain.com/xmltv.php?username=USER&password=PASS
```
- Admin-wrapped import page: `/admin/epg_import.php`

---

## Install (Web Wizard)

1) Upload files to your web root  
2) Visit your domain → it redirects to `/install/` automatically  
3) Enter DB credentials + base URL  
4) (Optional) enter PayPal/CashApp fields  
5) Finish → installer prints **admin username + password**  
6) Login at `/admin`

**After install:** delete `/install/` or block it via web server rules (recommended).

---

## Rewrites (Xtream-style URLs)

If you want `/live/...` and `/seg/...` to work, enable the provided Apache/Nginx rewrite rules.

---

## Cron (Recommended)

```bash
*/10 * * * * php /path/to/scripts/stream_probe.php --limit=400 >/dev/null 2>&1
0 */6 * * * php /path/to/scripts/epg_import.php --flush=0 >/dev/null 2>&1
```

---

## Notes on Fail Videos (Compatibility Tips)

- **`.m3u8`** is the best choice for most IPTV apps (especially for Live-style playback).
- **`.mp4`** works well for many apps but not all “live” players.
- **`.ts`** is the safest for **segment endpoints** because those requests often expect raw transport stream bytes.

---

## Legal

This project is a starting point for IPTV tools. Only load streams/EPG data you have the legal right to use (e.g., free/OTT sources like Pluto TV).

---

## Support / Updates

If this saves you time and you want to support the work (especially the Android client), use the button at the top.
