# AMS Cache: Persistent Expert Mode Runtime

Use a persistent private directory for AMS Cache Expert Mode. Do not rely on `/tmp`; Docker restarts and host cleanup can remove it.

## cPanel

Create the directory outside `public_html`:

```bash
mkdir -p /home/CPUSER/ams-cache-runtime
chown -R CPUSER:CPUSER /home/CPUSER/ams-cache-runtime
chmod 700 /home/CPUSER/ams-cache-runtime
```

Replace `CPUSER` with the real cPanel account and PHP user.

## Docker

Mount persistent storage outside the web root:

```yaml
volumes:
  ams_cache_runtime:

services:
  wordpress:
    volumes:
      - ams_cache_runtime:/var/lib/ams-cache-runtime
```

Ensure the PHP/WordPress user owns the mounted directory and it has mode `700`.

## `wp-config.php`

Add this before the AMS Cache Expert Mode block. Use the path for the current environment:

```php
if ( ! defined( 'WP_TEMP_DIR' ) ) {
    define( 'WP_TEMP_DIR', '/var/lib/ams-cache-runtime' );
}
```

cPanel example:

```php
define( 'WP_TEMP_DIR', '/home/CPUSER/ams-cache-runtime' );
```

## Regenerate Expert Mode

1. Open AMS Cache settings.
2. Save Expert Mode again.
3. Replace the old generated `runtime_dir` value in `wp-config.php` with the newly generated value.
4. Do not copy another site's hash or keep the old `/tmp/...` path.

The generated path normally includes the site's cache subdirectory, for example:

```php
'runtime_dir' => '/var/lib/ams-cache-runtime/ams-cache/1_<site-hash>',
```

## Permissions

The runtime directory must be private. After Expert Mode creates its files:

```bash
find /var/lib/ams-cache-runtime -type d -exec chmod 700 {} \;
find /var/lib/ams-cache-runtime -type f -exec chmod 600 {} \;
```

Use the cPanel path instead when applicable. Never use `777`, and never place this directory under `public_html` or the Docker web root.

## Verify

1. Purge the homepage cache.
2. Load it once as a guest; this request warms the cache.
3. Load it a second time; it should show the Expert Mode marker.
4. Restart the Docker container and repeat the test.

If the second request works after restart, the persistent runtime setup is correct.
