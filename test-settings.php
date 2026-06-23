<?php
define('WP_USE_THEMES', false);
require 'C:\Users\MPTC\Local Sites\mptc-cloud\app\public\wp-load.php';

$settings = get_option('scm_option_page_optimization', array());
echo "image_optimization: " . ($settings['image_optimization'] ?? 'not set') . PHP_EOL;
echo "image_optimize_on_upload: " . ($settings['image_optimize_on_upload'] ?? 'not set') . PHP_EOL;
echo "image_formats: " . implode(', ', $settings['image_formats'] ?? ['webp']) . PHP_EOL;
echo "image_batch_size: " . ($settings['image_batch_size'] ?? '?') . PHP_EOL;
echo "image_primary_format: " . ($settings['image_primary_format'] ?? '?') . PHP_EOL;
echo "bun_path: " . ($settings['bun_path'] ?? 'not set') . PHP_EOL;

// Check Bun availability
$bun = $settings['bun_path'] ?? '';
echo "Bun path from settings: '$bun'" . PHP_EOL;
if ($bun) {
    $cmd = $bun . ' --version 2>&1';
    $out = shell_exec($cmd);
    echo "Bun version: " . trim($out ?? 'none') . PHP_EOL;
}

// Check if shell_exec is available
echo "shell_exec available: " . (function_exists('shell_exec') && is_callable('shell_exec') ? 'yes' : 'no') . PHP_EOL;
