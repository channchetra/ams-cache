# Third-Party Libraries

KH Speed Engine vendors the following MIT-licensed libraries in this directory. They
were copied from the Composer packages (`vendor/`) so that KH Speed Engine can
maintain and patch them in-repo instead of relying on `composer install` at
runtime.

## Shieldon Simple Cache 1.3.4

- License: MIT
- Source: https://github.com/terrylinooo/simple-cache
- Location: `inc/lib/simple-cache/`
- License text: `inc/lib/simple-cache/LICENSE`

The local `File` and `Redis` drivers include the compression and TTL fixes that
were previously maintained as copies in `patches/`. Those patch files duplicated
the driver sources and have been removed now that the fixed drivers live here.

## PSR Simple Cache 1.0.1

- License: MIT
- Source: https://github.com/php-fig/simple-cache
- Location: `inc/lib/psr-simple-cache/`
- License text: `inc/lib/psr-simple-cache/LICENSE.md`

## Loading

`inc/lib/autoload.php` registers a PSR-4 style autoloader for the
`Shieldon\SimpleCache\` and `Psr\SimpleCache\` namespaces. The original
namespaces are preserved, so plugin code and tests are unaffected.

## Updating

When upgrading either library, copy the new `src/` contents over the matching
subdirectory, keep the license file, re-apply any AMS-specific driver fixes, and
update the version numbers above.
