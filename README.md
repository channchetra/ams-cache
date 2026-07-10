# AMS Cache - WordPress Cache Plugin

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
* Vite-built React admin console using HeroUI components and Tailwind CSS 4.3 design tokens, with live status, settings views, requirement checks, and one-click actions.
* Optional Redis and File cache compression.
* Optional Nginx direct static cache for the File driver.
* Optional page optimization before cache storage: HTML minify, inline/external UCSS generation, JS analysis, lazy media, LCP image priority, Google Fonts preconnect, and guarded JavaScript defer.
* Admin bar purge controls for clearing all cache or only the current page.
* Compatible with the WooCommerce plugin.
* And more...

## Modular Architecture

As of v3.0.8, the plugin's helper functions have been split from a single monolithic `helpers.php` (179 KB) into focused modules, each loaded via `helpers.php` bootstrap:

| Module | Purpose |
|--------|---------|
| `cache-core.php` | Dir hash, upload dir, cache key, URI normalization, cacheable checks |
| `cache-nginx.php` | Nginx static cache write/delete/clear |
| `cache-nginx-config.php` | Nginx requirements check + config snippet generation |
| `cache-purger.php` | Cache statistics + purge operations |
| `page-optimizer.php` | HTML/CSS minification, lazy loading, UCSS, media optimization, reports |
| `cache-preload.php` | Preload queue, homepage crawling, scheduled warming |
| `cache-utils.php` | Driver factory, benchmark footer JS, shell exec, config defaults |

## Nginx Direct Static Cache

When using the File driver, AMS Cache can write a raw HTML mirror that Nginx may serve before PHP or WordPress runs. Enable it in Advanced settings, review the requirement checks, copy the generated snippet into your Nginx `server` block, run `nginx -t`, then reload Nginx.

## Page Optimization

AMS Cache can optimize cacheable guest HTML before it is saved to File, Redis, Memcached, APCu, or another selected driver. Enable it in the Optimization tab. The pipeline includes HTML/comment cleanup, inline CSS minify, conservative PHP UCSS for local styles, hashed CSS assets for eligible same-site stylesheets, native lazy loading for media, first-image LCP priority/preload, Google Fonts preconnect, bounded PHP JS analysis, and optional safe JavaScript defer with exclusions.

External UCSS is intentionally conservative. It reads only same-site `.css` files that resolve under the WordPress root, skips SRI/crossorigin/alternate/preload/importing stylesheets, obeys the configured max file size, rewrites relative `url()` assets before publishing a content-addressed CSS file, and keeps the original `<link>` when the PHP engine produces no smaller output.

## Preloading

Preloading warms the homepage first (with a blocking request), then same-site links discovered on the homepage, before moving to configured post type, archive, and WooCommerce URLs. Enable Preload in thedashboard to start queueing immediately, or use the "Run Preload" button to trigger warming on demand.

Large sites can preload up to 1000 URLs. AMS Cache queues preload work in small batches so dashboard actions do not have to wait for every URL in one request. Remaining batches are processed via WordPress cron.

## Vite and Tailwind Admin Console

AMS Cache uses native WordPress admin pages plus a Vite-built admin asset pipeline that bundles React and HeroUI. Tailwind CSS 4.3 is used as the design utility layer while the existing AMS Cache dashboard system remains the visual baseline. The console includes cache, preload, performance, rules, statistics, expert, benchmark, WooCommerce, and about views, with AJAX controls for refresh, preload, homepage purge, and full cache clear.

Development build:

```bash
bun install
bun run build
```

Release build:

```powershell
bun run build:release
```

One-command version bump and release:

```powershell
bun run release
bun run release:minor
powershell -ExecutionPolicy Bypass -File .\bin\release.ps1 -Version 3.1.0 -Note "Release dashboard polish."
```

`bin/release.ps1` defaults to a patch bump. It syncs `cache-master.php`, `SCM_PLUGIN_VERSION`, `README.txt` stable tag, `package.json`, React about-page labels, and `CHANGELOG.md`, then calls `bin/build-release.ps1`. The release script runs `bun install --frozen-lockfile`, builds Vite assets into `inc/assets/build`, verifies the Vite manifest, then creates the WordPress zip in `dist/`. Pass `-SkipBunBuild` only when built assets already exist.

The Performance view records recent cache writes and shows what actually happened on each page: bytes before/after, total bytes saved, Local UCSS and External UCSS bytes removed, JS Analysis deferred/analyzed counts, and feature-level states such as Applied, No change, Disabled, or Failed. Older reports created before the local engines existed may still show Pending engine. It loads five reports first and fetches more only when requested.

All settings forms save in place through authenticated WordPress AJAX requests with nonce and capability checks. The React console writes the same WordPress options used by the cache runtime, so existing option update hooks still run.

The console normalizes legacy controls into switch-style binary toggles or segmented multi-choice controls, keeps dense settings content aligned for faster scanning, and provides live Statistics lists with per-row cache clearing.

`Applied` in a page report means that transform changed cached output. Local UCSS uses conservative PHP selector matching and preserves dynamic/unsupported rules. External UCSS publishes optimized hashed CSS assets. Both keep a configurable safelist for dynamic classes. JS Analysis uses bounded PHP heuristics and defers only readable same-site scripts classified safe. Report sizes use human-readable units and displayed URLs are decoded for easier review.

Built assets are required for the React console. The release script verifies the Vite manifest before packaging.

## Local UCSS, External UCSS, and JS Analysis

AMS Cache uses built-in PHP engines for Local UCSS, External UCSS, and JS Analysis. Production does not require Bun, Node, PurgeCSS, Composer, or `shell_exec`. The legacy executable fields remain only so older saved settings and dashboards continue to load.

Bun and the frontend package toolchain are build-machine dependencies only. Run `bun install --frozen-lockfile` and `bun run build` before packaging the React console.

Windows:

```powershell
where bun
where purgecss
```

On Windows, the optional legacy Bun/PurgeCSS fields can be left empty. Optimization workspaces and runtime config are stored outside public uploads when the host provides a private temp directory.

The preloader uses loopback HTTP requests to warm cached pages. On local development environments with self-signed SSL certificates, SSL verification is automatically disabled for preload requests. This behavior can be overridden via the `scm_preload_sslverify` filter.
