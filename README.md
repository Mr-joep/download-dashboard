# down.mr-joep.nl — download tracking system

Lightweight, self-hosted download tracker for an nginx download server.
Plain PHP 8 + MariaDB, Bootstrap 5 and Chart.js (both bundled locally),
no Composer, no frameworks, no external APIs.

**Existing download URLs keep working unchanged** (`https://down.mr-joep.nl/file.zip`).
Every request is logged *before* the file is served; the actual transfer is done
by nginx via `X-Accel-Redirect`, so even a 260 GB file never touches PHP memory.

## What it does

- Logs every request: downloads, 404s, unknown files, directory scans, suspicious requests.
- Tracks per file: first/last download, total downloads — the file list syncs
  automatically from disk, no manual importing.
- Detects known bots (Googlebot, Bingbot, AI crawlers, SEO tools, scanners — seeded
  in `schema.sql`) plus suspicious behaviour: directory brute forcing (`/wp-admin`,
  `/.env`, …), vulnerability-scanner user agents, high request rates. Logging only,
  no automatic banning.
- Marks a download **completed** when nginx reports that at least the whole file
  went out (`post_action` → `complete.php`); resumes/range segments are logged but
  not double-counted.
- Bootstrap dark dashboard: overview, live visitors (auto-refresh), recent
  downloads, top downloads, top IPs, bots, 404s, search, Chart.js statistics and
  an upload manager with overwrite confirmation and download-link generator.

## Layout

```
config.php             your configuration (copy of config.example.php)
schema.sql             database schema + seeded bot signatures
nginx-example.conf     nginx server block for production
dev-router.php         router for `php -S` local development
src/                   PHP classes (Database, BotDetector, FileRepository, ...)
public/                web root
├── serve.php          entry point for ALL download traffic
├── complete.php       nginx post_action callback (internal only)
└── panel/             dashboard (password protected)
```

## Deployment (Debian, nginx 1.26, PHP 8.4-FPM, MariaDB)

1. **Copy the app** to the server, e.g. `/home/dow/app` (the download files stay
   where they are, in `/home/dow/public_html`):

   ```
   rsync -a --exclude config.php --exclude storage ./ dow@server:/home/dow/app/
   ```

2. **Create the database** and import the schema:

   ```sql
   CREATE DATABASE downtrack CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'downtrack'@'localhost' IDENTIFIED BY 'your-password';
   GRANT SELECT, INSERT, UPDATE, DELETE ON downtrack.* TO 'downtrack'@'localhost';
   ```
   ```
   mysql -u downtrack -p downtrack < schema.sql
   ```

3. **Configure**: `cp config.example.php config.php` and set the database
   credentials, `files_dir` (`/home/dow/public_html`), a panel password and a
   random `complete_secret`. Keep `serve_method` on `xaccel`.

4. **nginx**: copy `nginx-example.conf` into your site config (merge with your
   existing TLS setup) and reload. The `/_protected/` alias must point at
   `files_dir` and its `post_action /complete.php;` line is what powers the
   completed-yes/no tracking and the live-visitors page.

5. **Permissions**: PHP-FPM needs read access to the files, and write access to
   `files_dir` only if you use the upload page.

6. **Uploads** (optional): browser uploads are limited by
   `client_max_body_size` (nginx) and `upload_max_filesize` / `post_max_size`
   (php.ini). Huge files are better copied with scp/rsync — they appear in the
   panel automatically.

Dashboard: `https://down.mr-joep.nl/panel/`

## Local development (no nginx)

`config.php` in this repo is already set up for local testing: it uses the
`storage/` directory for files and `serve_method => 'php'` (PHP streams the file
itself in 256 KB chunks — dev only).

```
mysql -u root downtrack < schema.sql     # after creating the database
php -S 127.0.0.1:8080 -t public dev-router.php
```

- Download: `http://127.0.0.1:8080/yourfile.zip`
- Panel: `http://127.0.0.1:8080/panel/` (password `dev`)

An `.htaccess` is included for testing under Apache/XAMPP instead; set
`base_path` in config.php to the subdirectory prefix (e.g. `/down/public`).

## How a download flows (production)

1. Client requests `/file.zip`; nginx passes it to `public/serve.php`.
2. serve.php detects bots/suspicious patterns, upserts the `files` row and
   inserts a `downloads` row (`completed = 0`).
3. serve.php replies with `X-Accel-Redirect: /_protected/file.zip?dlid=<id>&tok=<hmac>`.
4. nginx streams the file (sendfile, ranges, resume — PHP is already done).
5. When the transfer ends, nginx's `post_action` calls `complete.php` with the
   byte count; the row gets `finished_at`, `bytes_sent` and `completed = 1` if
   the whole file went out. Without `post_action`, everything still works —
   transfers just show as "Unknown" instead of "Completed".

## Extending (future features)

The spec's future features (password protected downloads, temporary / one-time /
signed / expiring links) all hook into one place: the **access control hook**
marked in `public/serve.php`, right before the request is logged as a download.
The `settings` table is reserved for their configuration. Bot signatures can be
managed in the `bots` table without code changes.

## Notes

- `downloads` stores **all** requests (also 404s and probes), per the spec.
- All timestamps are stored in the timezone from `config.php` by PHP; the
  database's own timezone is never used.
- A download is *counted* once: Range requests that don't start at byte 0
  (resumes, download-manager segments) are logged but don't bump counters.
- The panel is session-protected; uploads are CSRF-protected and PHP file
  extensions are refused.
