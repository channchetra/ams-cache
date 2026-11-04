<?php
/*
 * This file is part of the Shieldon Simple Cache package.
 *
 * (c) Terry L. <contact@terryl.in>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Shieldon\SimpleCache\Driver;

use Shieldon\SimpleCache\CacheProvider;
use Shieldon\SimpleCache\Exception\CacheException;
use Redis as RedisServer;
use Exception;
use function array_keys;
use function extension_loaded;
use function function_exists;
use function unserialize;
use function serialize;
use function is_bool;
use function is_array;
use function gzcompress;
use function gzuncompress;
use function in_array;
use function strlen;
use function strpos;
use function substr;
use function max;
use function min;

/**
 * A cache driver class provided by Redis database.
 */
class Redis extends CacheProvider
{
    protected $type = 'redis';

    /**
     * The Redis instance.
     *
     * @var \Redis|null
     */
    protected $redis = null;

    /**
     * Compress payload before storing.
     *
     * @var bool
     */
    protected $compress = true;

    /**
     * Minimum payload bytes before compression.
     *
     * @var int
     */
    protected $compressThreshold = 1024;

    /**
     * Compression level.
     *
     * @var int
     */
    protected $compressLevel = 6;

    /**
     * Constructor.
     *
     * @param array $setting The settings.
     *
     * @throws CacheException
     */
    public function __construct(array $setting = [])
    {
        $config = [
            'host' => '127.0.0.1',
            'port' => 6379,
            'user' => null,
            'pass' => null,
            'database' => null,
            'compress' => true,
            'compress_threshold' => 1024,
            'compress_level' => 6,

            // If the UNIX socket is set, host, port, user and pass will be ignored.
            'unix_socket' => '',
        ];

        foreach (array_keys($config) as $key) {
            if (isset($setting[$key])) {
                $config[$key] = $setting[$key];
            }
        }

        $this->compress = !in_array($config['compress'], [false, 0, '0', 'no', 'off'], true) &&
            function_exists('gzcompress') &&
            function_exists('gzuncompress');
        $this->compressThreshold = (int) $config['compress_threshold'];
        $this->compressLevel = max(1, min(9, (int) $config['compress_level']));

        $this->connect($config);
    }

    /**
     * Connect to Redis server.
     *
     * @param array $config The settings.
     *
     * @return void
     *
     * @throws CacheException
     */
    protected function connect(array $config): void
    {
        if (extension_loaded('redis')) {
            try {
                $this->redis = new RedisServer();

                if (!empty($config['unix_socket'])) {
                    // @codeCoverageIgnoreStart
                    $this->redis->connect($config['unix_socket']);
                    // @codeCoverageIgnoreEnd
                } else {
                    $this->redis->connect($config['host'], $config['port']);
                    $this->auth($config);
                }

                if ($config['database'] !== null && $config['database'] !== '') {
                    $this->redis->select((int) $config['database']);
                }

            // @codeCoverageIgnoreStart
            } catch (Exception $e) {
                throw new CacheException($e->getMessage());
            }
            // @codeCoverageIgnoreEnd
            return;
        }

        // @codeCoverageIgnoreStart
        throw new CacheException(
            'PHP Redis extension is not installed on your system.'
        );
        // @codeCoverageIgnoreEnd
    }

    /**
     * Redis authentication.
     *
     * @param array $config The user / pass data.
     * @return void
     * @codeCoverageIgnore
     */
    protected function auth(array $config = []): void
    {
        if (!empty($config['pass'])) {
            if ($this->getVersion() >= 6 && !empty($config['user'])) {
                $this->redis->auth([
                    $config['user'],
                    $config['pass'],
                ]);
                return;
            }

            $this->redis->auth($config['pass']);
        }
    }

    /**
     * Get Redis version number.
     */
    public function getVersion(): int
    {
        $info = $this->redis->info();

        return (int) $info['redis_version'];
    }

    /**
     * Fetch a cache by an extended Cache Driver.
     *
     * @param string $key The key of a cache.
     *
     * @return array
     */
    protected function doGet(string $key): array
    {
        $content = $this->redis->get($this->getKeyName($key));

        if (empty($content)) {
            return [];
        }

        if (strpos($content, 'scgz:') === 0) {
            if (!function_exists('gzuncompress')) {
                return [];
            }

            $content = gzuncompress(substr($content, 5));

            if (false === $content) {
                return [];
            }
        }

        $data = unserialize($content);

        return is_array($data) ? $data : [];
    }

    /**
     * Set a cache by an extended Cache Driver.
     *
     * @param string $key       The key of a cache.
     * @param mixed  $value     The value of a cache. (serialized)
     * @param int    $ttl       The time to live for a cache.
     * @param int    $timestamp The time to store a cache.
     *
     * @return bool
     */
    protected function doSet(string $key, $value, int $ttl, int $timestamp): bool
    {
        $contents = [
            'timestamp' => $timestamp,
            'ttl'       => $ttl,
            'value'     => $value,
        ];

        if (empty($ttl)) {
            $ttl = null;
        }

        $payload = serialize($contents);

        if ($this->compress && strlen($payload) >= $this->compressThreshold) {
            $compressed = gzcompress($payload, $this->compressLevel);

            if (false !== $compressed) {
                $payload = 'scgz:' . $compressed;
            }
        }

        $result = $this->redis->set(
            $this->getKeyName($key),
            $payload,
            $ttl
        );

        return $result;
    }

    /**
     * Delete a cache by an extended Cache Driver.
     *
     * @param string $key The key of a cache.
     *
     * @return bool
     */
    protected function doDelete(string $key): bool
    {
        return $this->redis->del($this->getKeyName($key)) >= 0;
    }

    /**
     * Delete all caches by an extended Cache Driver.
     *
     * @return bool
     */
    protected function doClear(): bool
    {
        $keys = $this->redis->keys('sc:*');

        if (!empty($keys)) {
            foreach ($keys as $key) {
                $this->redis->del($key);
            }
        }

        return true;
    }

    /**
     * Check if the cache exists or not.
     *
     * @param string $key The key of a cache.
     *
     * @return bool
     */
    protected function doHas(string $key): bool
    {
        $exist = $this->redis->exists($this->getKeyName($key));

        // This function took a single argument and returned TRUE or FALSE in phpredis versions < 4.0.0.

        // @codeCoverageIgnoreStart
        if (is_bool($exist)) {
            return $exist;
        }

        return $exist > 0;
        // @codeCoverageIgnoreEnd
    }

    /**
     * Get the key name of a cache.
     *
     * @param string $key The key of a cache.
     *
     * @return string
     */
    private function getKeyName(string $key): string
    {
        return 'sc:' . $key;
    }
}
