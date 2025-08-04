<?php
/**
 * Performance Monitoring for Referral Lookup Addon
 * 
 * This file contains performance checks and optimizations
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Performance check - ensure we're not in a critical context
 */
function referral_lookup_performance_check() {
    // Don't run during cron jobs
    if (defined('WHMCS_CRON') || php_sapi_name() === 'cli') {
        return false;
    }
    
    // Don't run during API calls unless specifically needed
    if (isset($_GET['api']) || strpos($_SERVER['REQUEST_URI'], '/api/') !== false) {
        return false;
    }
    
    // Don't run during AJAX calls unless for our addon
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        // Only allow for our addon's AJAX calls
        if (!isset($_POST['action']) || !in_array($_POST['action'], [
            'search_clients', 
            'get_referral_details', 
            'check_referral_conflicts', 
            'get_referral_tree'
        ])) {
            return false;
        }
    }
    
    return true;
}

/**
 * Memory usage check
 */
function referral_lookup_memory_check() {
    $memoryLimit = ini_get('memory_limit');
    $memoryUsage = memory_get_usage(true);
    
    // Convert memory limit to bytes
    $limitBytes = return_bytes($memoryLimit);
    
    // If we're using more than 80% of memory, don't load heavy features
    if ($limitBytes > 0 && ($memoryUsage / $limitBytes) > 0.8) {
        return false;
    }
    
    return true;
}

/**
 * Helper function to convert memory string to bytes
 */
function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int)$val;
    
    switch($last) {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }
    
    return $val;
}

/**
 * Database query performance check
 */
function referral_lookup_db_performance_check() {
    // Check if we're in a heavy database operation
    if (isset($GLOBALS['db_query_count']) && $GLOBALS['db_query_count'] > 100) {
        return false;
    }
    
    return true;
}

/**
 * Load time check
 */
function referral_lookup_loadtime_check() {
    // If page has been loading for more than 5 seconds, skip non-essential features
    if (isset($_SERVER['REQUEST_TIME_FLOAT'])) {
        $loadTime = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
        if ($loadTime > 5.0) {
            return false;
        }
    }
    
    return true;
}

/**
 * Main performance gate - call this before any addon operations
 */
function referral_lookup_performance_gate() {
    return referral_lookup_performance_check() && 
           referral_lookup_memory_check() && 
           referral_lookup_db_performance_check() && 
           referral_lookup_loadtime_check();
}

/**
 * Performance logging (only if detailed logging is enabled)
 */
function referral_lookup_performance_log($operation, $startTime = null) {
    static $enableLogs = null;
    
    if ($enableLogs === null) {
        // Get addon settings
        $addonSettings = get_query_vals('tbladdonmodules', 'setting,value', ['module' => 'referral_lookup']);
        $enableLogs = false;
        
        foreach ($addonSettings as $setting) {
            if ($setting['setting'] === 'enable_detailed_logs' && $setting['value'] === 'on') {
                $enableLogs = true;
                break;
            }
        }
    }
    
    if (!$enableLogs) {
        return;
    }
    
    $endTime = microtime(true);
    $duration = $startTime ? ($endTime - $startTime) * 1000 : 0; // Convert to milliseconds
    
    $logData = [
        'operation' => $operation,
        'duration_ms' => round($duration, 2),
        'memory_usage' => memory_get_usage(true),
        'peak_memory' => memory_get_peak_usage(true),
        'timestamp' => date('Y-m-d H:i:s'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ];
    
    // Log to file for debugging
    $logFile = __DIR__ . '/logs/performance.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    file_put_contents($logFile, json_encode($logData) . "\n", FILE_APPEND | LOCK_EX);
} 