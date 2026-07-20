# dow.mr-joep.nl — download tracking system

Lightweight, self-hosted download tracker for an nginx download server.
Plain PHP 8 + MariaDB, Bootstrap 5 and Chart.js (both bundled locally),
no Composer, no frameworks, no external APIs.

**Existing download URLs keep working unchanged** (`https://dow.mr-joep.nl/file.zip`).
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
  downloads, top downloads (click "Downloads"/"Requests" to sort), top IPs,
  bots, 404s, search, Chart.js statistics, an upload manager with overwrite
  confirmation and download-link generator, and a Storage tab (rename,
  delete, permanently purge a deleted file's history). **No login** — anyone
  who can reach `/panel/` can use it.

## Layout as deployed on this server

```
/home/dow/public_html/
├── config.php            live configuration (db creds, files_dir, secrets)
├── config.example.php    documents every config.php option
├── schema.sql             database schema + seeded bot signatures
├── nginx-example.conf     original upstream example (kept for reference only —
│                          the live nginx config differs, see below)
├── dev-router.php         router for `php -S` local development
├── src/                   PHP classes (Database, BotDetector, FileRepository, ...)
├── public/                nginx docroot
│   ├── serve.php          entry point for ALL download traffic
│   ├── complete.php       nginx post_action callback (internal only)
│   └── panel/             dashboard (no login)
└── files/                 files_dir — the actual downloadable files live here
```

`config.php`, `schema.sql`, `src/`, and `.git` sit inside `public_html` but are
**never web-reachable**: nginx's `root` points at `public_html/public`, not
`public_html` itself, and `public/`'s catch-all (`location /` → `serve.php`)
never falls through to serving arbitrary static files, so those paths return
serve.php's own 404 regardless of what's actually on disk there.

To add a new download: drop the file into `/home/dow/public_html/files/` (or
upload it through the panel). It appears in the dashboard automatically, and
`https://dow.mr-joep.nl/<filename>` starts working immediately — no config
changes, no restart.

## nginx

The live vhost is `/etc/nginx/sites-available/dow.mr-joep.nl.conf`, generated
from `nginx-example.conf` but adapted for this Virtualmin-managed server:
root points at `public_html/public`, `/_protected/` (the X-Accel-Redirect
target) is aliased to `public_html/files/`, and the existing SSL cert paths /
webmail / admin subdomain redirects Virtualmin manages were preserved. A
backup of the pre-deploy config is at `/root/dow.mr-joep.nl.conf.bak`.

`/phpmyadmin/` has its own `root /home/dow/public_html;` location block,
since the phpMyAdmin install lives at `public_html/phpmyadmin/`, outside the
app's `public/` docroot. If Virtualmin's "PHP script execution mode" toggle
is changed again in the UI, it may re-append its own generic `location ~
"\.php(/|$)"` block at the bottom of this file — that block has always been
broken (no `SCRIPT_FILENAME`, no `fastcgi_split_path_info`) and is redundant
with the explicit per-path blocks already here; delete it if it reappears.

Traffic normally arrives via Cloudflare (Zero Trust in front of `/panel/*`);
`/etc/nginx/conf.d/cloudflare-realip.conf` maps Cloudflare's `CF-Connecting-IP`
header back onto `$remote_addr` for connections that actually originate from
Cloudflare's published ranges, so IPs logged by the app (Top IPs, bot-rate
detection, etc.) are the real visitor IP, not Cloudflare's edge IP. The
origin's real IP is still directly reachable though (bypassing both
Cloudflare and Access) — see `/home/dow/questions.md`.

Reload after editing:

```
nginx -t && systemctl reload nginx
```

## How a download flows (production)

1. Client requests `/file.zip`; nginx passes it to `public/serve.php`.
2. serve.php detects bots/suspicious patterns, upserts the `files` row and
   inserts a `downloads` row (`completed = 0`).
3. serve.php replies with `X-Accel-Redirect: /_protected/file.zip?dlid=<id>&tok=<hmac>`.
4. nginx streams the file (sendfile, ranges, resume — PHP is already done).
5. When the transfer ends, nginx's `post_action` calls `complete.php`, which
   marks the row `finished_at` / `bytes_sent` / `completed = 1` if the whole
   file went out.

**Note on step 5**, since it differs from a naive reading of `nginx-example.conf`:
nginx's `post_action` subrequest only ever sees the *original* client request
(`GET /file.zip`, no query string) — not the `dlid`/`tok` appended to the
X-Accel-Redirect target in step 3, and not any response header serve.php sets
either (X-Accel-Redirect discards the whole original response once the
internal redirect happens). So `complete.php` can't use the signed id at all;
instead it matches the open `downloads` row by **path + IP** (both of which
nginx *does* preserve for `post_action`), picking the most recent unfinished
one. Unambiguous at this app's scale (~20 files, a handful of downloads/day).
See `RequestLogger::finalizeByPathIp()`.

## Local development (no nginx)

For local testing, copy `config.example.php` to `config.php` and set
`files_dir` to a local `storage/` directory and `serve_method` to `'php'`
(PHP streams the file itself in 256 KB chunks — dev only, not what's running
in production here).

```
mysql -u root downtrack < schema.sql     # after creating the database
php -S 127.0.0.1:8080 -t public dev-router.php
```

- Download: `http://127.0.0.1:8080/yourfile.zip`
- Panel: `http://127.0.0.1:8080/panel/`

An `.htaccess` is included for testing under Apache/XAMPP instead; set
`base_path` in config.php to the subdirectory prefix (e.g. `/down/public`).

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
- Uploads are CSRF-protected and PHP file extensions are refused, but the
  panel itself has no authentication — see "What it does" above.
