# Changelog

## [2.7.2] - 2026-05-15

### Added
- Add exact Local UCSS saved-byte and JS Analysis deferred/analyzed summary cards on the Performance page.

### Changed
- Replace admin table components with list-based blocks matching the Latest Page Report design.

## [2.7.1] - 2026-05-15

### Fixed
- Keep malformed inline CSS blocks raw when PurgeCSS cannot parse them, while still optimizing valid blocks on the same page.
- Surface useful PurgeCSS parse messages instead of only the first stack-trace file path.

## [2.7.0] - 2026-05-15

### Added
- Real Local UCSS Generation for inline page CSS through PurgeCSS, with configurable safelist support.
- Real JS Analysis engine through local Node.js that defers only readable same-site scripts classified safe.
- Writable local optimizer workspace requirement check and runtime feature reporting for applied, no change, or failed jobs.

## [2.6.6] - 2026-05-15

### Changed
- Show optimization report sizes with human-readable units instead of raw bytes.
- Decode displayed optimization report URLs for easier reading.
- Humanize old stored report byte strings at render time, so existing reports improve before recache.

## [2.6.5] - 2026-05-15

### Changed
- Load only five recent page optimization reports by default, with click-to-load pagination in five-row batches.
- Explain that Local UCSS Generation and JS Analysis remain requirement checks until their engines produce changed cached HTML.
- Convert guest visibility rows into explicit read-only badges and normalize switch labels to `Enable`, `Disable`, `Yes`, and `No`.

### Fixed
- Tighten Statistics setting card layout so its switch and save button no longer stretch awkwardly.

## [2.6.4] - 2026-05-15

### Changed
- Rebuild checkbox settings into consistent switch controls across Preload, Performance, WooCommerce, and remaining console tabs.
- Improve Advanced driver connection controls, including disabled TCP / Unix Socket states.
- Replace the old Statistics split view with a searchable live table and per-row clear-cache actions.

### Fixed
- Re-run settings enhancement across hidden and newly opened console tabs so Rules, Statistics, Expert Mode, Benchmark, and WooCommerce no longer keep legacy controls.

## [2.6.3] - 2026-05-15

### Fixed
- Restore AJAX settings saves by reading the literal form `action` attribute instead of the form field named `action`.

### Changed
- Replace binary Enable/Disable and Yes/No radios with switch-style controls.
- Refresh settings and dashboard tables with cleaner neutral styling and remove leftover settings-row backgrounds and separators.

## [2.6.2] - 2026-05-15

### Changed

- Radio groups are rebuilt into clean segmented controls instead of styling malformed legacy label markup in place.
- Settings spacing, advanced driver field alignment, TTL example layout, and long requirement output wrapping are tightened.

### Fixed

- Segmented controls no longer apply flex layout to whole settings cells, which had pulled help text, inputs, and example tables into one row.

## [2.6.1] - 2026-05-15

### Added

- AJAX settings saves across all Vue console settings views.
- Modern settings form styling with section cards, segmented radio groups, refined field surfaces, and per-form save status.

### Changed

- Existing Settings API forms now submit without a full admin-page reload while keeping their native nonce, capability, and update-hook behavior.

## [2.6.0] - 2026-05-15

### Added

- Unified Vue admin console covering cache, preload, performance, advanced, rules, statistics, expert, benchmark, WooCommerce, and about views.
- Page optimization reports that record bytes before/after, saved bytes, and feature-level result states for recent cached pages.
- Performance workspace with requirement checks, recent page reports, and detailed latest-page feature diagnostics.

### Changed

- Existing WordPress admin pages now resolve into one Vue console while preserving native Settings API saves.
- Admin styling now uses compact design tokens, modern surfaces, side navigation, and status pills.

## [2.5.1] - 2026-05-15

### Added

- Batched preload queue with dashboard totals for queued, processed, and remaining URLs.
- Homepage priority fallback from enabled post types when the homepage crawler cannot find enough internal links.

### Changed

- Dashboard Run Preload and Purge Homepage actions now start queue work instead of blocking the full AJAX request.
- Preload limit now supports up to 1000 URLs, dispatched in smaller batches.
- Priority URL count now reflects planned homepage-priority URLs instead of only the previous run count.

## [2.5.0] - 2026-05-15

### Added

- NPM-less Vue 3 dashboard loaded from CDN inside native WordPress admin screens.
- Live dashboard status cards for cache health, page optimization, server requirements, and preload progress.
- Dashboard AJAX actions for refresh, run preload, purge homepage, and clear all cache.
- Cache footprint and optimization requirement summaries on the main AMS Cache page.

### Changed

- AMS Cache now opens to a dedicated Dashboard page, with Settings, Expert Mode, Statistics, and About as secondary pages.
- Admin styling refreshed for a more compact, interactive cache console.

## [2.4.3] - 2026-05-14

### Changed

- Clear Cache is now hidden from the frontend admin bar; Purge Current Page remains available there.

## [2.4.2] - 2026-05-14

### Changed

- AMS Cache admin bar controls now load on frontend for logged-in users.
- Homepage-discovered preload URLs now run first with blocking requests before the rest of the preload queue.
- Cache clear and preload setting changes now immediately warm homepage-discovered URLs when preload is enabled.

## [2.4.1] - 2026-05-14

### Added

- Purge Current Page admin bar action for Administrators and Editors.

### Changed

- Critical homepage preload now runs blocking after cache clear so Homepage stats repopulate immediately.
- Node.js and PurgeCSS checks now use common web-user PATH locations and fail command-not-found output correctly.

## [2.4.0] - 2026-05-14

### Added

- Local UCSS Generation and JS Analysis options with Node.js, PurgeCSS, and `shell_exec` requirement checks.
- Homepage-first preload crawling that extracts same-site links from homepage HTML before falling back to configured post/archive URL lists.
- Scoped homepage/archive purge for published or deleted posts.

### Changed

- Cache serving is now guest-only in both Normal Mode and Expert Mode.
- Cache identity is normalized before key generation.
- Statistics now de-duplicate rows that point to the same URI.
- Frontend cache ignores query-string requests, static asset paths, auth-cookie requests, REST, admin, login, and XML-RPC paths.

## [2.3.0] - 2026-05-14

### Added

- Page Optimization settings tab.
- Cache-time HTML optimization before File, Redis, Memcached, APCu, and other driver storage.
- HTML minification, safe comment removal, and inline CSS minification.
- Native lazy loading for images and iframes with keyword exclusions.
- First-image LCP priority/preload controls.
- Google Fonts preconnect hints.
- Optional JavaScript defer with exclusion list.
- Requirement checks for page optimization.

### Changed

- Updating page optimization settings now clears cache and starts configured preload/critical warmup.

## [2.2.0] - 2026-05-13

### Added

- Redis database selector for DB0 through DB15.
- Redis database selection support in bundled Shieldon Simple Cache Redis driver.
- Redis HTML payload compression options.
- File driver compression options.
- Maximum cache entries option that prunes oldest known cache rows.
- Cache key prefix option for shared Redis, Memcached, APC, APCu, WinCache, MongoDB, and MySQL stores.
- Cache preload option that warms selected homepage, post type, archive, and WooCommerce URLs.
- Immediate critical preload for homepage and archive URLs after cache clear or published post save.
- Nginx direct static cache option for File driver with server requirement checks and generated Nginx snippet.
- Custom post type and custom post type archive cache support.

### Changed

- Expert Mode snippet now explains that runtime settings are read from `config.json`.
- Expert Mode snippet now emits normalized paths for Windows/local development.
- Frontend benchmark JavaScript no longer depends on jQuery.
- Shared-store cache clearing deletes known AMS Cache keys instead of flushing entire shared stores.
- Plugin branding changed to AMS Cache.
- WordPress compatibility metadata tested up to 6.9.4.
- Preload loopback timeout increased for more reliable non-blocking warm requests.
- Cache statistics now keep URI metadata while remaining compatible with older size-only stat files.

### Fixed

- Redis advanced settings remain editable when Redis is the active driver.
- Redis, Memcached, and MongoDB advanced field values now render correctly.
- Driver status view now marks the selected active driver.
- Single excluded URL entries persist correctly.
