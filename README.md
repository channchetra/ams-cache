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
* Optional WebP image optimizer for WordPress uploads with background queueing and offload-safe controls.
* Admin bar purge controls for clearing all cache or only the current page.
* Compatible with the WooCommerce plugin.
* And more...

## Nginx Direct Static Cache

When using the File driver, AMS Cache can write a raw HTML mirror that Nginx may serve before PHP or WordPress runs. Enable it in Advanced settings, review the requirement checks, copy the generated snippet into your Nginx `server` block, run `nginx -t`, then reload Nginx.

## Page Optimization

AMS Cache can optimize cacheable guest HTML before it is saved to File, Redis, Memcached, APCu, or another selected driver. Enable it in the Optimization tab. The pipeline includes HTML/comment cleanup, inline CSS minify, local inline UCSS generation through PurgeCSS, External UCSS for same-site local stylesheet files, native lazy loading for media, first-image LCP priority/preload, Google Fonts preconnect, local JS analysis for readable same-site scripts, and optional JavaScript defer with exclusions.

External UCSS is intentionally conservative. It reads only same-site `.css` files that resolve under the WordPress root, skips SRI/crossorigin/alternate/preload/importing stylesheets, obeys the configured max file size, rewrites relative `url()` assets before inlining, and keeps the original `<link>` if PurgeCSS fails or produces no smaller output.

Preloading warms the homepage first, then same-site links discovered on the homepage with blocking requests, before moving to configured post type, archive, and WooCommerce URLs.

Large sites can preload up to 1000 URLs. AMS Cache queues preload work in small batches so dashboard actions do not have to wait for every URL in one request.

## Vite and Tailwind Admin Console

AMS Cache uses native WordPress admin pages plus a Vite-built admin asset pipeline that bundles React and HeroUI. Tailwind CSS 4.3 is used as the design utility layer while the existing AMS Cache dashboard system remains the visual baseline. The console includes cache, preload, performance, rules, statistics, expert, benchmark, WooCommerce, and about views, with AJAX controls for refresh, preload, homepage purge, image queueing, and full cache clear.

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

The Performance view records recent cache writes and shows what actually happened on each page: bytes before/after, total bytes saved, Local UCSS and External UCSS bytes removed, JS Analysis deferred/analyzed counts, image optimizer status, and feature-level states such as Applied, No change, Disabled, or Failed. Older reports created before the local engines existed may still show Pending engine. It loads five reports first and fetches more only when requested.

All settings forms save in place through authenticated WordPress AJAX requests with nonce and capability checks. The React console writes the same WordPress options used by the cache runtime, so existing option update hooks still run.

The console normalizes legacy controls into switch-style binary toggles or segmented multi-choice controls, keeps dense settings content aligned for faster scanning, and provides live Statistics lists with per-row cache clearing.

`Applied` in a page report means that transform changed cached output. Local UCSS purges inline `<style>` blocks with PurgeCSS. External UCSS purges eligible same-site stylesheet files and inlines the optimized result into cached guest HTML. Both keep a configurable safelist for dynamic classes. JS Analysis runs a local Bun analyzer and defers only readable same-site scripts classified safe. Report sizes use human-readable units and displayed URLs are decoded for easier review.

Built assets are required for the React console. The release script verifies the Vite manifest before packaging.

## Local UCSS, External UCSS, and JS Analysis

AMS Cache can run local UCSS generation, External UCSS generation, and JS analysis from the Optimization tab. Install the tools on your server, then set the executable paths in AMS Cache.

Ubuntu/Debian:

```bash
curl -fsSL https://bun.sh/install | bash
~/.bun/bin/bun add --global purgecss
```

CentOS/RHEL/AlmaLinux:

```bash
curl -fsSL https://bun.sh/install | bash
~/.bun/bin/bun add --global purgecss
```

Verify:

```bash
bun --version
purgecss --version
which bun
which purgecss
```

Windows:

```powershell
where bun
where purgecss
```

On Windows, use the full `bun.exe` path and the full `purgecss.cmd` path in the Optimization tab when PHP cannot see your shell `PATH`.

If PHP `open_basedir` blocks those binaries, create symlinks inside an allowed directory and use those paths in AMS Cache. PHP `shell_exec` must be enabled, and the local optimizer workspace must be writable.

## Image Optimization

AMS Cache can generate WebP variants for WordPress image attachments. Enable Image Optimization in the Performance settings, set quality, and use Queue Images to process the newest 200 JPEG/PNG/WebP attachments. New uploads are optimized automatically when "Optimize images on upload" is enabled.

AMS Cache now uses WebP as the only output and primary upload format. The generated `.webp` file can become the WordPress attachment file for new uploads before offload plugins sync the attachment. Originals stay on disk for backup/compatibility, but the attachment URL can point to WebP after successful conversion.

The image engine uses Bun Image for local WebP generation and tiny loading placeholders. If Bun cannot create WebP, AMS Cache falls back to the native WordPress image editor. Use an absolute Bun binary path in the Performance settings if PHP cannot see `bun` in its `PATH`.

```bash
bun --version
bun run image:check
```

Image placeholders store a short, safe `data:image/...;base64` preview in attachment optimization metadata. When "Image placeholders" is enabled, AMS Cache adds that preview as an inline background while the real WebP image loads.

Safety rules:

* Original uploads are never overwritten.
* Writes are restricted to generated variant files beside existing images inside `wp-content/uploads`.
* GIF, SVG, unknown mime types, and files outside uploads are skipped.
* Background batches are capped to avoid long admin or cron requests.
* HTML rewrite only happens when generated variant metadata exists.
* The Bun optimizer receives only upload-directory source and output paths, validates real paths before writing, and generated placeholder data URLs are length and mime-type restricted before HTML output.

Offload compatibility:

* Default mode is safe for WP Offload Media and Advanced Offload Media because remote/offloaded URL rewriting is disabled.
* If your offload plugin syncs generated `.webp` files to the same remote path, you may enable "Allow offloaded remote URL rewrite".
* Original image files stay intact; new-upload attachment metadata can point to the generated WebP file after successful conversion.

If root can run `purgecss` but PHP cannot, PHP is probably using a smaller web-user `PATH`. Set the absolute path from `which purgecss`, commonly `/usr/local/bin/purgecss`, in the Optimization tab.

Use the UCSS safelist for dynamic classes JavaScript adds after load. Local UCSS currently targets inline page CSS; external stylesheet replacement is intentionally not automatic. If one inline CSS block is malformed, AMS Cache keeps that block raw and still optimizes valid blocks.
