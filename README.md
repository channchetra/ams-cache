# AMS Cache - WordPress Cache Plugin

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)

![WordPress Cache Plugin](./inc/assets/images/banner-772x250.png)

AMS Cache is an extremely lightweight, high-performance cache plugin that speeds up your WordPress sites on the fly. The core of AMS Cache is driven by Shieldon [Simple Cache](https://github.com/terrylinooo/simple-cache), a PSR-16 simple cache library.


## Requirement

* PHP version > 7.1.0
* WordPress version > 4.7
* Tested up to WordPress 6.9.4
* Tested up to PHP 8.2.5

## Features

* Extremely lightweight and high-performance.
* Support up to 10 cache drivers such as File, Redis, Memcache, Memcached, APC, APCu, WinCache, MySQL, SQLite, and MongoDB.
* Provide detailed cache statistics, easy to manage.
* NPM-less Vue 3 admin console loaded from CDN with live status, settings views, requirement checks, and one-click actions.
* Optional Redis and File cache compression.
* Optional Nginx direct static cache for the File driver.
* Optional page optimization before cache storage: HTML minify, inline CSS minify, local inline UCSS generation, JS analysis, lazy media, LCP image priority, Google Fonts preconnect, and guarded JavaScript defer.
* Admin bar purge controls for clearing all cache or only the current page.
* Compatible with the WooCommerce plugin.
* And more...

## Nginx Direct Static Cache

When using the File driver, AMS Cache can write a raw HTML mirror that Nginx may serve before PHP or WordPress runs. Enable it in Advanced settings, review the requirement checks, copy the generated snippet into your Nginx `server` block, run `nginx -t`, then reload Nginx.

## Page Optimization

AMS Cache can optimize cacheable guest HTML before it is saved to File, Redis, Memcached, APCu, or another selected driver. Enable it in the Optimization tab. The pipeline includes HTML/comment cleanup, inline CSS minify, local inline UCSS generation through PurgeCSS, native lazy loading for media, first-image LCP priority/preload, Google Fonts preconnect, local JS analysis for readable same-site scripts, and optional JavaScript defer with exclusions.

Preloading warms the homepage first, then same-site links discovered on the homepage with blocking requests, before moving to configured post type, archive, and WooCommerce URLs.

Large sites can preload up to 1000 URLs. AMS Cache queues preload work in small batches so dashboard actions do not have to wait for every URL in one request.

## NPM-less Admin Console

AMS Cache uses native WordPress admin pages plus Vue 3 from CDN for one unified admin console. There is no Node build, bundler, or generated admin asset pipeline. The console includes cache, preload, performance, advanced, rules, statistics, expert, benchmark, WooCommerce, and about views, with AJAX controls for refresh, preload, homepage purge, and full cache clear.

The Performance view records recent cache writes and shows what actually happened on each page: bytes before/after, total bytes saved, Local UCSS bytes removed, JS Analysis deferred/analyzed counts, and feature-level states such as Applied, No change, Disabled, or Failed. Older reports created before the local engines existed may still show Pending engine. It loads five reports first and fetches more only when requested.

All settings forms save in place through AJAX while still posting to WordPress `options.php`, so native Settings API validation, nonces, and option update hooks remain in use.

The console normalizes legacy controls into switch-style binary toggles or segmented multi-choice controls, keeps dense settings content aligned for faster scanning, and provides a live Statistics table with per-row cache clearing.

`Applied` in a page report means that transform changed cached output. Local UCSS currently purges inline `<style>` blocks with PurgeCSS and keeps a configurable safelist for dynamic classes. JS Analysis runs a local Node.js analyzer and defers only readable same-site scripts classified safe. Report sizes use human-readable units and displayed URLs are decoded for easier review.

If your server cannot reach the default CDN, override the Vue URL with the `scm_vue_cdn_url` filter.

## Local UCSS and JS Analysis

AMS Cache can run local UCSS generation and JS analysis from the Optimization tab. Install the tools on your server, then set the executable paths in AMS Cache.

Ubuntu/Debian:

```bash
curl -fsSL https://deb.nodesource.com/setup_24.x | sudo -E bash -
sudo apt-get install -y nodejs
sudo npm install -g purgecss
```

CentOS/RHEL/AlmaLinux:

```bash
curl -fsSL https://rpm.nodesource.com/setup_24.x | sudo bash -
sudo yum install -y nodejs
sudo npm install -g purgecss
```

Verify:

```bash
node --version
purgecss --version
which node
which purgecss
```

Windows:

```powershell
where node
where purgecss
```

On Windows, use the full `node.exe` path and the full `purgecss.cmd` path in the Optimization tab when PHP cannot see your shell `PATH`.

If PHP `open_basedir` blocks those binaries, create symlinks inside an allowed directory and use those paths in AMS Cache. PHP `shell_exec` must be enabled, and the local optimizer workspace must be writable.

If root can run `purgecss` but PHP cannot, PHP is probably using a smaller web-user `PATH`. Set the absolute path from `which purgecss`, commonly `/usr/local/bin/purgecss`, in the Optimization tab.

Use the UCSS safelist for dynamic classes JavaScript adds after load. Local UCSS currently targets inline page CSS; external stylesheet replacement is intentionally not automatic. If one inline CSS block is malformed, AMS Cache keeps that block raw and still optimizes valid blocks.

