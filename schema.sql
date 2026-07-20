-- ============================================================================
--  down.mr-joep.nl - download tracking system - database schema
-- ============================================================================
--
-- Create the database and a user first (pick your own password):
--
--   CREATE DATABASE downtrack CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
--   CREATE USER 'downtrack'@'localhost' IDENTIFIED BY 'change-me';
--   GRANT SELECT, INSERT, UPDATE, DELETE ON downtrack.* TO 'downtrack'@'localhost';
--   FLUSH PRIVILEGES;
--
-- Then import this file:
--
--   mysql -u downtrack -p downtrack < schema.sql

-- ----------------------------------------------------------------------------
-- downloads: one row per HTTP request (downloads, 404s, bots, scans).
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS downloads (
    id                BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
    requested_at      DATETIME          NOT NULL,
    path              VARCHAR(500)      NOT NULL,
    filename          VARCHAR(255)      NULL,
    file_id           INT UNSIGNED      NULL,
    ip                VARCHAR(45)       NOT NULL,
    user_agent        VARCHAR(500)      NOT NULL DEFAULT '',
    referer           VARCHAR(500)      NOT NULL DEFAULT '',
    method            VARCHAR(10)       NOT NULL DEFAULT 'GET',
    status            SMALLINT UNSIGNED NOT NULL,
    is_download       TINYINT(1)        NOT NULL DEFAULT 0,  -- request resolved to a real file
    counted           TINYINT(1)        NOT NULL DEFAULT 0,  -- counted towards download totals (resumes/segments are not)
    range_request     TINYINT(1)        NOT NULL DEFAULT 0,  -- HTTP Range request (resume / download manager segment)
    is_bot            TINYINT(1)        NOT NULL DEFAULT 0,
    bot_name          VARCHAR(100)      NULL,
    bot_type          VARCHAR(20)       NULL,                -- search/social/seo/ai/monitor/tool/scanner/other
    is_suspicious     TINYINT(1)        NOT NULL DEFAULT 0,
    suspicious_reason VARCHAR(100)      NULL,
    file_size         BIGINT UNSIGNED   NULL,
    bytes_sent        BIGINT UNSIGNED   NOT NULL DEFAULT 0,
    completed         TINYINT(1)        NOT NULL DEFAULT 0,  -- the full file was delivered
    finished_at       DATETIME          NULL,                -- when the transfer ended (any amount of data)
    PRIMARY KEY (id),
    KEY idx_requested  (requested_at),
    KEY idx_ip         (ip, requested_at),
    KEY idx_status     (status, requested_at),
    KEY idx_file       (file_id, requested_at),
    KEY idx_bot        (is_bot, requested_at),
    KEY idx_suspicious (is_suspicious, requested_at),
    KEY idx_download   (is_download, requested_at),
    KEY idx_counted    (counted, requested_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- heartbeats: one row per browser tab that currently has the home page open.
-- Client-side JS pings this every few seconds; rows are not deleted on tab
-- close, they simply age out of the "live" window (see Heartbeat.php) and
-- are reaped by the next ping.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS heartbeats (
    token      VARCHAR(64)  NOT NULL,  -- random id generated client-side per page load
    ip         VARCHAR(45)  NOT NULL,
    user_agent VARCHAR(500) NOT NULL DEFAULT '',
    path       VARCHAR(500) NOT NULL DEFAULT '/',
    first_seen DATETIME     NOT NULL,
    last_seen  DATETIME     NOT NULL,
    PRIMARY KEY (token),
    KEY idx_last_seen (last_seen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- files: one row per file that exists (or existed) in the download directory.
-- Rows are created automatically by serve.php and the panel's disk sync.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS files (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    filename        VARCHAR(255)    NOT NULL,
    size            BIGINT UNSIGNED NOT NULL DEFAULT 0,
    first_seen      DATETIME        NOT NULL,
    uploaded_at     DATETIME        NULL,                -- set when uploaded through the panel
    first_download  DATETIME        NULL,
    last_download   DATETIME        NULL,
    total_downloads INT UNSIGNED    NOT NULL DEFAULT 0,
    missing         TINYINT(1)      NOT NULL DEFAULT 0,  -- no longer present on disk (history is kept)
    PRIMARY KEY (id),
    UNIQUE KEY uq_filename (filename)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- bots: known bot user-agent signatures (case-insensitive substring match).
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bots (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name       VARCHAR(100) NOT NULL,
    ua_pattern VARCHAR(100) NOT NULL,
    type       VARCHAR(20)  NOT NULL DEFAULT 'other',
    enabled    TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pattern (ua_pattern)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- settings: free-form configuration for future features
-- (password protected downloads, signed/expiring links, ...).
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
    name  VARCHAR(100) NOT NULL,
    value TEXT         NOT NULL,
    PRIMARY KEY (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO settings (name, value) VALUES ('schema_version', '1');

-- ----------------------------------------------------------------------------
-- Seed: known bot signatures.
-- ----------------------------------------------------------------------------
INSERT IGNORE INTO bots (name, ua_pattern, type) VALUES
-- Search engines
('Googlebot',        'googlebot',         'search'),
('Google AdsBot',    'adsbot-google',     'search'),
('Bingbot',          'bingbot',           'search'),
('MSNBot',           'msnbot',            'search'),
('DuckDuckBot',      'duckduck',          'search'),
('Yahoo Slurp',      'slurp',             'search'),
('Baiduspider',      'baiduspider',       'search'),
('YandexBot',        'yandex',            'search'),
('SeznamBot',        'seznambot',         'search'),
('Applebot',         'applebot',          'search'),
('Qwantbot',         'qwant',             'search'),
('PetalBot',         'petalbot',          'search'),
-- Social / link previews
('Facebook preview', 'facebookexternalhit', 'social'),
('Facebook Facebot', 'facebot',           'social'),
('Twitterbot',       'twitterbot',        'social'),
('LinkedInBot',      'linkedinbot',       'social'),
('WhatsApp',         'whatsapp',          'social'),
('TelegramBot',      'telegrambot',       'social'),
('Discordbot',       'discordbot',        'social'),
('Slackbot',         'slackbot',          'social'),
('Pinterestbot',     'pinterest',         'social'),
-- SEO crawlers
('AhrefsBot',        'ahrefsbot',         'seo'),
('SemrushBot',       'semrushbot',        'seo'),
('Majestic MJ12bot', 'mj12bot',           'seo'),
('Moz DotBot',       'dotbot',            'seo'),
('BLEXBot',          'blexbot',           'seo'),
('DataForSEO',       'dataforseo',        'seo'),
('Screaming Frog',   'screaming frog',    'seo'),
('Serpstatbot',      'serpstat',          'seo'),
-- AI crawlers
('GPTBot',           'gptbot',            'ai'),
('ChatGPT-User',     'chatgpt-user',      'ai'),
('OAI-SearchBot',    'oai-searchbot',     'ai'),
('ClaudeBot',        'claudebot',         'ai'),
('Claude-User',      'claude-user',       'ai'),
('Anthropic AI',     'anthropic-ai',      'ai'),
('PerplexityBot',    'perplexitybot',     'ai'),
('Bytespider',       'bytespider',        'ai'),
('CCBot',            'ccbot',             'ai'),
('Amazonbot',        'amazonbot',         'ai'),
('Diffbot',          'diffbot',           'ai'),
('Google-Extended',  'google-extended',   'ai'),
('Meta External',    'meta-externalagent','ai'),
-- Uptime monitors
('UptimeRobot',      'uptimerobot',       'monitor'),
('Pingdom',          'pingdom',           'monitor'),
('StatusCake',       'statuscake',        'monitor'),
('Site24x7',         'site24x7',          'monitor'),
('Better Uptime',    'betteruptime',      'monitor'),
-- Command line / library clients
('curl',             'curl/',             'tool'),
('Wget',             'wget/',             'tool'),
('python-requests',  'python-requests',   'tool'),
('Python urllib',    'python-urllib',     'tool'),
('aiohttp',          'aiohttp',           'tool'),
('Go HTTP client',   'go-http-client',    'tool'),
('Java HTTP client', 'java/',             'tool'),
('okhttp',           'okhttp',            'tool'),
('libwww-perl',      'libwww-perl',       'tool'),
('Scrapy',           'scrapy',            'tool'),
('HTTPie',           'httpie',            'tool'),
('PowerShell',       'powershell',        'tool'),
('Headless Chrome',  'headlesschrome',    'tool'),
('PhantomJS',        'phantomjs',         'tool'),
('node-fetch',       'node-fetch',        'tool'),
('axios',            'axios/',            'tool'),
-- Vulnerability scanners
('sqlmap',           'sqlmap',            'scanner'),
('Nikto',            'nikto',             'scanner'),
('Nuclei',           'nuclei',            'scanner'),
('WPScan',           'wpscan',            'scanner'),
('masscan',          'masscan',           'scanner'),
('zgrab',            'zgrab',             'scanner'),
('Nmap NSE',         'nmap scripting engine', 'scanner'),
('gobuster',         'gobuster',          'scanner'),
('DirBuster',        'dirbuster',         'scanner'),
('feroxbuster',      'feroxbuster',       'scanner'),
('ffuf',             'fuzz faster',       'scanner'),
('Acunetix',         'acunetix',          'scanner'),
('Netsparker',       'netsparker',        'scanner'),
('OpenVAS',          'openvas',           'scanner'),
('Qualys',           'qualys',            'scanner'),
('CensysInspect',    'censys',            'scanner'),
('Expanse scanner',  'expanse',           'scanner'),
('Shodan',           'shodan',            'scanner'),
('InternetMeasurement', 'internetmeasurement', 'scanner'),
('Palo Alto scanner','paloaltonetworks',  'scanner');
