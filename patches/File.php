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
use DirectoryIterator;
use function chmod;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function function_exists;
use function gzcompress;
use function gzuncompress;
use function in_array;
use function is_array;
use function is_file;
use function max;
use function min;
use function rtrim;
use function serialize;
use function str_replace;
use function strlen;
use function strpos;
use function substr;
use function unlink;
use function unserialize;

/**
 * A cache driver class provided by local file system.
 */
class File extends CacheProvider
{
    protected $type = 'file';

    /**
     * The absolute path of the storage's directory.
     * It must be writable.
     *
     * @var string
     */
    protected $storage = '/tmp/simple-cache';

    /**
     * Compress payload before storing.
     *
     * @var bool
     */
    protected $compress = false;

    /**
     * Minimum payload bytes before compression.
     *
     * @var int
     */
    protected $compressThreshold = 4096;

    /**
     * Compression level.
     *
     * @var int
     */
    protected $compressLevel = 1;

    /**
     * Constructor.
     *
     * @param array $setting The settings.
     *
     * @throws CacheException
     */
    public function __construct(array $setting = [])
    {
        if (isset($setting['storage'])) {
            $this->storage = rtrim($setting['storage'], '/');
        }

        if (isset($setting['compress'])) {
            $this->compress = !in_array($setting['compress'], [false, 0, '0', 'no', 'off'], true) &&
                function_exists('gzcompress') &&
                function_exists('gzuncompress');
        }

        if (isset($setting['compress_threshold'])) {
            $this->compressThreshold = max(0, (int) $setting['compress_threshold']);
        }

        if (isset($setting['compress_level'])) {
            $this->compressLevel = max(1, min(9, (int) $setting['compress_level']));
        }

        $this->assertDirectoryWritable($this->storage);
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
        $filePath = $this->getFilePath($key);

        if (!is_file($filePath)) {
            return [];
        }

        $content = file_get_contents($filePath);

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

        $filePath = $this->getFilePath($key);
        $payload = serialize($contents);

        if ($this->compress && strlen($payload) >= $this->compressThreshold) {
            $compressed = gzcompress($payload, $this->compressLevel);

            if (false !== $compressed) {
                $payload = 'scgz:' . $compressed;
            }
        }
        
        if (file_put_contents($filePath, $payload)) {
            chmod($filePath, 0640);
            return true;
        }

        // @codeCoverageIgnoreStart
        return false;
        // @codeCoverageIgnoreEnd
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
        $filePath = $this->getFilePath($key);

        return is_file($filePath) ? unlink($filePath) : false;
    }

    /**
     * Delete all caches by an extended Cache Driver.
     *
     * @return bool
     */
    protected function doClear(): bool
    {
        $directory = new DirectoryIterator($this->storage);

        foreach ($directory as $file) {
            if ($file->isFile() && $file->getExtension() === 'cache') {
                unlink($file->getRealPath());
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
        return file_exists($this->getFilePath($key));
    }

    /**
     * Fetch all cache items.
     *
     * @return attay
     */
    protected function getAll(): array
    {
        $directory = new DirectoryIterator($this->storage);
        $list = [];

        foreach ($directory as $file) {
            $ext = $file->getExtension();

            if ($file->isFile() && $ext === 'cache') {
                $key = str_replace('.' . $ext, '', $file->getFilename());
                $value = $this->doGet($key);

                $list[$key] = $value;
            }
        }
        return $list;
    }

    /**
     * Get the path of a cache file.
     *
     * @param string $key The key of a cache.
     *
     * @return string
     */
    private function getFilePath(string $key): string
    {
        return $this->storage . '/' . $key . '.cache';
    }
}
