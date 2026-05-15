=== AMS Cache ===

Contributors: terrylin
Tags: cache, redis, mongodb, memcached, apc, apcu
Requires at least: 5.8
Tested up to: 6.9.4
Stable tag: 2.7.2
Requires PHP: 7.1.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl.html

== Description ==

AMS Cache is an extremely lightweight, high-performance cache plugin that speeds up your WordPress sites on the fly. The core of AMS Cache is driven by Shieldon Simple Cache, a PSR-16 simple cache library.

Core library:

- [terrylinooo/simple-cache](https://github.com/terrylinooo/simple-cache) (Simple Cache library)

First release date: October, 1, 2020

== Features ==

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

== Nginx Direct Static Cache ==

When using the File driver, AMS Cache can write a raw HTML mirror that Nginx may serve before PHP or WordPress runs. Enable it in Advanced settings, review the requirement checks, copy the generated snippet into your Nginx server block, run nginx -t, then reload Nginx.

== Page Optimization ==

AMS Cache can optimize cacheable guest HTML before it is saved to File, Redis, Memcached, APCu, or another selected driver. Enable it in the Optimization tab. The pipeline includes HTML/comment cleanup, inline CSS minify, local inline UCSS generation through PurgeCSS, native lazy loading for media, first-image LCP priority/preload, Google Fonts preconnect, local JS analysis for readable same-site scripts, and optional JavaScript defer with exclusions.

Large sites can preload up to 1000 URLs. AMS Cache queues preload work in small batches so dashboard actions do not have to wait for every URL in one request.

== NPM-less Admin Console ==

The AMS Cache admin console uses native WordPress admin screens and Vue 3 from CDN. No Node build, bundler, or compiled admin assets are required. The console covers cache, preload, performance, advanced, rules, statistics, expert, benchmark, WooCommerce, and about views, with AJAX controls for refresh, preload, homepage purge, and full cache clear.

The Performance view records recent cache writes and shows what actually happened on each page: bytes before/after, total bytes saved, Local UCSS bytes removed, JS Analysis deferred/analyzed counts, and feature-level states such as Applied, No change, Disabled, or Failed. Older reports created before the local engines existed may still show Pending engine.

All settings forms save in place through AJAX while still posting to WordPress options.php, so native Settings API validation, nonces, and option update hooks remain in use.

If your server cannot reach the default CDN, override the Vue URL with the scm_vue_cdn_url filter.

== Local UCSS and JS Analysis ==

AMS Cache can run local UCSS generation and JS analysis from the Optimization tab. The requirement pass checks PHP shell_exec, Node.js, PurgeCSS CLI, and a writable optimizer workspace. Install and configure the tools on your server, then set the executable paths in AMS Cache.

Ubuntu/Debian:

`curl -fsSL https://deb.nodesource.com/setup_24.x | sudo -E bash -`
`sudo apt-get install -y nodejs`
`sudo npm install -g purgecss`

CentOS/RHEL/AlmaLinux:

`curl -fsSL https://rpm.nodesource.com/setup_24.x | sudo bash -`
`sudo yum install -y nodejs`
`sudo npm install -g purgecss`

Verify:

`node --version`
`purgecss --version`
`which node`
`which purgecss`

Windows:

`where node`
`where purgecss`

On Windows, use the full `node.exe` path and the full `purgecss.cmd` path in the Optimization tab when PHP cannot see your shell `PATH`.

If PHP open_basedir blocks those binaries, create symlinks inside an allowed directory and use those paths in AMS Cache.

If root can run purgecss but PHP cannot, PHP is probably using a smaller web-user PATH. Set the absolute path from `which purgecss`, commonly `/usr/local/bin/purgecss`, in the Optimization tab.

Use the UCSS safelist for dynamic classes JavaScript adds after load. Local UCSS currently targets inline page CSS; external stylesheet replacement is intentionally not automatic. If one inline CSS block is malformed, AMS Cache keeps that block raw and still optimizes valid blocks.

Notice:

Before you install and use this plugin, please read the following notices carefully:

- Logged-in users will not trigger the caching process.
- A debug message will be appended to the end of the page's source code: `<!-- This page is cached by the AMS Cache plugin. //-->`. This is intended for debugging purposes only, confirming that the page is being cached. This message can be disabled in the Settings page.

If you encounter issues with your website when using AMS Cache, list all plugins installed on your website when reporting the problem.

== Installation ==

- Upload the plugin files to the /wp-content/plugins/ams-cache directory, or install the plugin directly through the WordPress plugins screen.
- Activate the plugin via the 'Plugins' screen in WordPress.
- Navigate to the AMS Cache menu in the Plugins section and configure your options.

== Frequently Asked Questions ==

= Should I disable other cache plugins? =

AMS Cache caches entire webpages into static HTML files. Therefore, it is recommended to disable other similar cache plugins that perform the same function. However, it can function alongside object cache plugins without issue.

= How can I determine whether the caching is working or not? =

Be aware that logged-in users will not trigger the caching process. To verify caching, use incognito mode or a different browser to revisit the same webpage, and then you can check the cache status in one of the following ways:

- View the source code of the webpage and check the debug message as shown in screenshot (L).
- Enable the benchmark information in the footer section as shown in screenshot (K), where you can see the cache status.

If there is no debug message in the source code, or if the cache status consistently displays 'No', there might be a plugin conflict with AMS Cache that prevents it from working correctly.

== Screenshots ==

1. Setting page - Basic.
2. Setting page - Basic (Bottom).
3. Setting page - Advanced.
4. Setting page - Perferences.
5. Setting page - Benchmark.
6. Setting page - WooCommerce.
7. Setting page - Exclusion.
8. Setting page - Expert mode.
9. Setting page - Cache statistics.
10. Setting page - About author.
11. Front page - Benchmark (footer text)
12. Debug message (Normal mode)
13. Debug message (Expert mode)

== Translation ==

Japanese (ja_JP) by [Colocal](https://colocal.com).

== Changelog ==

= 2.7.0 (5/15/2026) =

* Add real Local UCSS Generation for inline page CSS using PurgeCSS with configurable safelist support.
* Add real JS Analysis engine using local Node.js to defer only readable same-site scripts classified safe.
* Add writable optimizer workspace checks and runtime report states for applied, no change, or failed engine jobs.

= 2.6.6 (5/15/2026) =

* Show optimization report sizes with human-readable units instead of raw bytes.
* Decode displayed report URLs for easier reading.
* Humanize old stored report byte strings at render time so existing reports improve without waiting for recache.

= 2.6.5 (5/15/2026) =

* Load only five recent page optimization reports by default and add click-to-load pagination in five-row batches.
* Clarify that Local UCSS Generation and JS Analysis are still requirement checks until their engines write changed cached HTML.
* Make guest visibility rows explicit read-only badges, improve Statistics setting layout, and normalize switch labels to `Enable`, `Disable`, `Yes`, and `No`.

= 2.6.4 (5/15/2026) =

* Rebuild checkbox options into consistent switch controls for Preload, Performance, WooCommerce, and other settings tabs.
* Improve Advanced driver connection choices, including readable disabled TCP / Unix Socket controls.
* Replace legacy Statistics layout with a searchable live table and per-row clear-cache actions.
* Broaden settings form enhancement so Rules, Statistics, Expert Mode, Benchmark, and WooCommerce forms receive modern controls after tab changes.

= 2.6.3 (5/15/2026) =

* Fix AJAX settings saves by using the form action attribute instead of the shadowed `action` form field.
* Replace binary Enable/Disable and Yes/No radio groups with switch-style controls.
* Refresh settings and dashboard tables with cleaner table styling and remove the leftover settings-row fill and separators.

= 2.6.2 (5/15/2026) =

* Rebuild radio groups into clean segmented controls instead of styling malformed legacy labels in place.
* Fix segmented-control layout so help text, inputs, and TTL examples no longer collapse into one horizontal row.
* Tighten settings padding, align advanced driver fields, improve TTL example layout, and wrap long requirement output cleanly.

= 2.6.1 (5/15/2026) =

* Make all console settings forms save through AJAX without reloading the admin page.
* Redesign settings forms with modern section cards, segmented radio groups, refined inputs, compact status feedback, and consistent console styling.
* Keep WordPress Settings API forms under the hood for nonce handling, capability checks, and existing option hooks.

= 2.6.0 (5/15/2026) =

* Rebuild AMS Cache admin as one Vue console with side navigation across cache, preload, performance, advanced, rules, statistics, expert, benchmark, WooCommerce, and about views.
* Add recent page optimization reports with bytes before/after and feature-level Applied, No change, Disabled, or Pending engine states.
* Restyle the admin with compact design tokens, modern surfaces, status pills, and report tables inspired by current Vue dashboard patterns.
* Keep native WordPress Settings API forms underneath the Vue shell so existing settings continue to save normally.

= 2.5.1 (5/15/2026) =

* Change dashboard preload actions to queue batched background work instead of blocking the whole AJAX request.
* Add homepage priority fallback from enabled post types when homepage crawling cannot find enough internal links.
* Show preload queue total, processed, and remaining counts on the dashboard.
* Allow preload limits up to 1000 URLs while dispatching in smaller batches.

= 2.5.0 (5/15/2026) =

* Add NPM-less Vue 3 dashboard loaded from CDN inside native WordPress admin screens.
* Add live status cards for cache health, optimization, server requirements, and preload progress.
* Add dashboard AJAX actions for refresh, run preload, purge homepage, and clear all cache.
* Refresh admin styling for a compact interactive cache console.

= 2.4.3 (5/14/2026) =

* Hide Clear Cache from the frontend admin bar; keep only Purge Current Page there.

= 2.4.2 (5/14/2026) =

* Load AMS Cache admin bar controls on frontend for logged-in users.
* Make homepage-discovered preload URLs run first with blocking requests before the rest of the preload queue.
* Warm homepage-discovered URLs immediately after cache clear and preload setting changes.

= 2.4.1 (5/14/2026) =

* Make critical homepage preload blocking after cache clear so Homepage stats repopulate immediately.
* Add Purge Current Page admin bar action for Administrators and Editors.
* Improve Node.js and PurgeCSS checks by using common web-user PATH locations and failing command-not-found output correctly.

= 2.4.0 (5/14/2026) =

* Enforce guest-only cache serving in Normal Mode and Expert Mode.
* Add stricter cacheable document checks to avoid caching query URLs, static assets, and logged-in requests.
* Normalize cache keys and de-duplicate stats rows for the same URI.
* Add homepage-first preload crawling for same-site homepage links.
* Add Local UCSS Generation and JS Analysis options with Node.js, PurgeCSS, and shell_exec requirement checks.
* Add scoped homepage/archive purge when posts are published or deleted.

= 2.3.0 (5/14/2026) =

* Add page optimization settings before cache storage.
* Add HTML/comment cleanup, inline CSS minify, lazy media, first-image LCP priority/preload, Google Fonts preconnect, and guarded JavaScript defer.
* Clear and warm cache when page optimization settings change.

= 1.0.0 (10/01/2020) =

* First release on WordPress plugin directory.

= 1.2.0 (10/06/2020) =

* Add "Expert Mode".

= 1.2.1 (10/07/2020) =

* Improve debug message.
* New setting option for Expert Mode.
* Add warning message if a user use a plugin having conflicts with AMS Cache.

= 1.2.2 (10/07/2020) =

* Improve debug message - Add SQL query numbers.

= 1.3.0 (10/08/2020)

* Add setting option - Visibility of cache for logged-in users.
* Add setting option - Archive pages: category, tag, date and author.
* Add setting option - Homepage.
* Fix some small issues.

= 1.4.0 (10/09/2020) =

* Add setting page - Cache statistics.
* Improve code - Prevent conflicts with others plugins.

= 1.4.1 (10/16/2020) =

* Add feature - Automatic installation of Expert Mode code. (Removed in later versions)

= 1.5.1 (10/17/2020) =

* Add setting page - Benchmark settings.
* Add feature - Benchmark information in widget or footer area.
* Fix some small issues.

= 1.5.2 (10/17/2020) =

* Add a Clear Cache button on admin bar.

= 2.0.0 (10/27/2020) =

* Support to WooCommerce plugin.
* Add setting pages - WooCommerce, Exclusion,  Advanced settings.
* Add an option - HTML debug comment.
* Improve cache statistic page.
* Update translation strings for zh_TW, zh_CN.
* Fix issues.

= 2.0.1 (10/27/2020) =

* Fix SQLite driver error after performing a new installation.

= 2.0.3 (10/31/2020) =

* Support to WP-CLI

= 2.1.0 (11/15/2020) =

* Add options - Now, you can use unix socket or TCP in Redis, MongoDB and Memcaced Drivers.
* Update core library to 1.3.1
* Add unit tests and run the tests before releasing new updates.
* Fix issues.

= 2.1.1 (10/31/2021) =

* Fix type hint.
* Driver will fall back to File driver if current driver is unavailable.
* Test up to PHP 8.0
* Test up to WordPress 5.8.1

= 2.1.2 (8/28/2022) =

* Fix a type hint issue that occurs a PHP 8 fatal error.
* Test up to PHP 8.0
* Test up to WordPress 6.0.1
* Fix coding style to fit the WordPress coding standard.

= 2.1.3 (5/29/2023) =

* Test up to PHP 8.2.5
* Test up to WordPress 6.2.2
* Add Japanese translation.

= 2.2.0 (5/13/2026) =

* Add an option to disable cache expiring mechanism.
* Add Redis database selector for DB0 through DB15.
* Add cache key prefix option for shared cache stores.
* Add cache preload option to warm selected pages after cache clears.
* Add custom post type and custom post type archive cache support.
* Improve Expert Mode snippet and runtime config sync.
* Fix advanced driver field rendering and selected driver status.
* Fix single excluded URL entry persistence.
