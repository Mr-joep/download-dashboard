# Project: down.mr-joep.nl Download Tracking System

## Goal

Create a lightweight PHP + MariaDB download tracking system for the existing download server.

The current download links must remain exactly the same.

Example:

https://down.mr-joep.nl/file.zip

No download.php links or changed URLs.

The system must be self-hosted and use only:

- PHP 8.4
- MariaDB
- Nginx
- HTML
- CSS
- Vanilla JavaScript
- Bootstrap (local copy)

No Composer.
No Laravel.
No frameworks.
No external APIs.

---

# Server

OS:
Debian

Webserver:
Nginx 1.26

PHP:
8.4.23

Database:
MariaDB 11.8

Current file location:

/home/dow/public_html/

---

# Main Features

## Download Tracking

Every request must be logged before the file is served.

Log:

- Timestamp
- Requested filename
- IP Address
- Download completed (Yes/No)

If the file does not exist, log the request as a 404.

---

## File Database

The system automatically keeps a list of files.

For every file store:

- Filename
- First download
- Last download
- Total downloads

The file list should automatically update when new files appear.

No manual importing.

---

## Request Logging

Every request should be stored.

Including:

Successful downloads

404 requests

Unknown files

Directory scanning attempts

Suspicious requests

---

# Bot Detection

Detect and identify:

Known bots

- Googlebot
- Bingbot
- DuckDuckBot
- Other known crawlers

Suspicious bots

Directory brute forcing

Examples:

/admin

/wp-admin

/wp-login.php

/.env

/config.php

/etc/passwd

Common vulnerability scanners

High request rate from one IP

The system should only log these events.

No automatic banning.

---

# Dashboard

Simple Bootstrap dashboard.

Pages:

## Dashboard

Show:

Total downloads

Downloads today

Downloads this week

Downloads this month

Total files

Total requests

404 requests

Bot requests

Recent activity

---

## Live Visitors

Show currently active downloads.

Refresh automatically.

---

## Recent Downloads

Newest downloads first.

Show:

Time

Filename

IP

Status

---

## Top Downloads

Most downloaded files.

Show:

Filename

Download count

First download

Last download

---

## Top IPs

Most active IP addresses.

Show:

IP

Download count

Last seen

---

## Countries

If country lookup is available through server configuration it may be added later.

Not required for version 1.

---

## Bots

List detected bots.

Include:

IP

Bot name

User Agent

Requested URL

Time

---

## 404 Requests

Show every missing file request.

Useful for finding broken links and scanners.

---

## Search

Search for:

Filename

IP

Date

---

## Statistics

Charts using Chart.js.

Graphs:

Downloads per day

Downloads per month

Top downloaded files

Top IPs

404 requests over time

Bot activity

---

# Upload Manager

Provide a simple upload page.

Features:

Upload files

Store metadata

Generate download link (downlaod/filename)

Display download link

Overwrite existing file confirmation

---

# Future Features (dont make them now!)

The code should be written so these features can be added later.

- Password protected downloads
- Temporary download links
- One-time download links
- Signed URLs
- Expiring download links

---

# Database

Tables:

downloads

Stores every request.

files

Stores file statistics.

bots

Known bot signatures.

settings

Future configuration.

---

# Performance

Expected usage

Files:
~20

Largest file:
Up to 260 GB

Downloads/day:
2–50

Concurrent users:
1–20

The system must not load large files into PHP memory.

Downloads must be handled by Nginx after PHP logs the request.

Use X-Accel-Redirect whenever possible.

---

# Design

Simple.

Fast.

Dark mode.

Bootstrap.

Responsive.

No unnecessary animations.

---

# Coding Style

Plain PHP.

Object-oriented where useful.

Minimal dependencies.

Readable code.

Easy to modify.

No Composer.

No external libraries except Bootstrap and Chart.js stored locally.

---

# Success Criteria

The project is considered complete when:

✓ Existing download URLs continue working.

✓ Every request is logged.

✓ Downloads are counted.

✓ 404 requests are logged.

✓ Bots are detected.

✓ Scanner activity is visible.

✓ Dashboard displays all statistics.

✓ Upload page works.

✓ Large files download efficiently.

✓ Everything runs locally on the server.