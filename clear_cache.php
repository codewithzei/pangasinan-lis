<?php
/**
 * Clear PHP Opcode Cache
 * Run this file in your browser to clear PHP's opcode cache
 */

echo "<!DOCTYPE html>";
echo "<html><head><title>Clear Cache</title>";
echo "<style>body{font-family:Arial,sans-serif;max-width:600px;margin:50px auto;padding:20px;}.success{color:green;}.error{color:red;}.info{color:blue;}</style>";
echo "</head><body>";
echo "<h2>Cache Clearing Utility</h2>";

// Clear OPcache if available
if (function_exists('opcache_reset')) {
    if (opcache_reset()) {
        echo "<p class='success'>✓ OPcache cleared successfully!</p>";
    } else {
        echo "<p class='error'>✗ Failed to clear OPcache.</p>";
    }
} else {
    echo "<p class='info'>ℹ OPcache is not enabled.</p>";
}

// Clear realpath cache
clearstatcache(true);
echo "<p class='success'>✓ Realpath cache cleared successfully!</p>";

// Display OPcache status
if (function_exists('opcache_get_status')) {
    $status = opcache_get_status(false);
    if ($status) {
        echo "<hr>";
        echo "<h3>OPcache Status</h3>";
        echo "<p><strong>Enabled:</strong> " . ($status['opcache_enabled'] ? 'Yes' : 'No') . "</p>";
        echo "<p><strong>Cache Full:</strong> " . ($status['cache_full'] ? 'Yes' : 'No') . "</p>";
        echo "<p><strong>Restart Pending:</strong> " . ($status['restart_pending'] ? 'Yes' : 'No') . "</p>";
    }
}

echo "<hr>";
echo "<p><strong>Cache clearing complete!</strong></p>";
echo "<p><a href='javascript:history.back()'>← Go Back</a></p>";
echo "</body></html>";
