<?php
/**
 * Referral Lookup Addon Installation Helper
 * 
 * This file helps with the installation and setup of the addon
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Installation helper function
 */
function referral_lookup_install_helper() {
    // Create necessary directories if they don't exist
    $directories = [
        __DIR__ . '/assets',
        __DIR__ . '/lang',
    ];
    
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
    
    // Set proper file permissions
    $files = [
        __DIR__ . '/assets/admin-styles.css',
        __DIR__ . '/assets/referral-lookup.css',
        __DIR__ . '/assets/referral-lookup.js',
        __DIR__ . '/hooks.php',
    ];
    
    foreach ($files as $file) {
        if (file_exists($file)) {
            chmod($file, 0644);
        }
    }
    
    return true;
}

/**
 * Check if addon is properly installed
 */
function referral_lookup_check_installation() {
    $required_files = [
        __DIR__ . '/referral_lookup.php',
        __DIR__ . '/functions.php',
        __DIR__ . '/assets/referral-lookup.css',
        __DIR__ . '/assets/referral-lookup.js',
    ];
    
    $missing_files = [];
    foreach ($required_files as $file) {
        if (!file_exists($file)) {
            $missing_files[] = basename($file);
        }
    }
    
    if (!empty($missing_files)) {
        return [
            'status' => 'error',
            'message' => 'Missing required files: ' . implode(', ', $missing_files)
        ];
    }
    
    return [
        'status' => 'success',
        'message' => 'Addon installation verified successfully'
    ];
}

// Run installation helper if this file is accessed directly
if (basename($_SERVER['SCRIPT_NAME']) === 'install.php') {
    $result = referral_lookup_install_helper();
    if ($result) {
        echo "✅ Referral Lookup Addon installation helper completed successfully.\n";
        echo "📁 Directories and files have been set up properly.\n";
        echo "🔧 You can now activate the addon from WHMCS Admin → Setup → Addon Modules\n";
    } else {
        echo "❌ Installation helper encountered an error.\n";
    }
}