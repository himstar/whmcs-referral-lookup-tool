<?php
/**
 * WHMCS Referral Lookup Addon
 * 
 * A powerful and professional admin tool for WHMCS to search, analyze, 
 * and manage customer referral relationships with comprehensive conflict 
 * detection and affiliate tracking.
 * 
 * @author Cyberin <info@cyberin.in>
 * @website https://cyberin.in
 * @version 1.2.0
 * @license MIT
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

// Include helper functions
require_once __DIR__ . '/functions.php';

// Include performance monitoring
if (file_exists(__DIR__ . '/performance.php')) {
    require_once __DIR__ . '/performance.php';
}

// Include hooks for admin area enhancements - only in admin context and when performance allows
if (defined('WHMCS') && 
    isset($_SESSION['adminid']) && 
    !empty($_SESSION['adminid']) && 
    function_exists('referral_lookup_performance_gate') && 
    referral_lookup_performance_gate()) {
    if (file_exists(__DIR__ . '/hooks.php')) {
        require_once __DIR__ . '/hooks.php';
    }
}

/**
 * Addon configuration
 */
function referral_lookup_config()
{
    return [
        'name' => 'Referral Lookup Tool',
        'description' => '🔍 Professional admin tool to search, analyze, and manage customer referral relationships with comprehensive conflict detection and affiliate tracking.',
        'version' => '1.2.0',
        'author' => '<img src="https://media.cyberin.in/file/cyberin/images/logo_big.png" alt="Cyberin" style="max-width: 120px; vertical-align: middle; margin-right: 5px;"/>',
        'website' => 'https://cyberin.in',
        'language' => 'english',
        'support' => 'https://cyberin.in/support',
        'tags' => ['referral', 'affiliate', 'lookup', 'conflict-detection', 'cyberin'],
        'compatibility' => ['8.0', '8.1', '8.2', '8.3'],
        'fields' => [
            'enable_detailed_logs' => [
                'FriendlyName' => 'Enable Detailed Logging',
                'Type' => 'yesno',
                'Description' => 'Log all referral lookups for audit purposes and compliance',
                'Default' => 'no',
            ],
            'results_per_page' => [
                'FriendlyName' => 'Results Per Page',
                'Type' => 'text',
                'Size' => '5',
                'Default' => '20',
                'Description' => 'Number of search results to display per page (10-100)',
            ],
            'auto_refresh' => [
                'FriendlyName' => 'Auto Refresh Data',
                'Type' => 'yesno',
                'Description' => 'Automatically refresh data every 30 seconds',
                'Default' => 'no',
            ],
        ]
    ];
}

/**
 * Addon activation
 */
function referral_lookup_activate()
{
    try {
        // Check if table already exists
        if (!Capsule::schema()->hasTable('mod_referral_lookup_logs')) {
            // Create log table for audit trail using Capsule
            Capsule::schema()->create('mod_referral_lookup_logs', function ($table) {
                $table->increments('id');
                $table->integer('admin_id');
                $table->string('admin_name', 100);
                $table->integer('client_id');
                $table->string('action', 50);
                $table->string('search_term', 255)->nullable();
                $table->string('ip_address', 45);
                $table->timestamp('timestamp')->useCurrent();
                
                $table->index('admin_id');
                $table->index('client_id');
                $table->index('timestamp');
            });
        }
        
        return [
            'status' => 'success',
            'description' => 'Referral Lookup addon activated successfully. Access it from Addons menu.',
        ];
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'description' => 'Failed to activate addon: ' . $e->getMessage(),
        ];
    }
}

/**
 * Addon deactivation
 */
function referral_lookup_deactivate()
{
    return [
        'status' => 'success',
        'description' => 'Referral Lookup addon deactivated. Log table preserved.',
    ];
}

/**
 * Main addon output
 */
function referral_lookup_output($vars)
{
    $modulelink = $vars['modulelink'];
    $version = $vars['version'];
    $enable_logs = $vars['enable_detailed_logs'];
    $results_per_page = (int)$vars['results_per_page'] ?: 20;
    
    // Get current admin info
    $admin_id = $_SESSION['adminid'];
    $admin_name = $_SESSION['adminusername'];
    $admin_ip = $_SERVER['REMOTE_ADDR'];
    
    // Handle AJAX requests
    if (isset($_POST['action'])) {
        header('Content-Type: application/json');
        
        // Debug logging
        error_log('Referral Lookup AJAX Request: ' . $_POST['action'] . ' - ' . json_encode($_POST));
        
        try {
            switch ($_POST['action']) {
                case 'search_clients':
                    $result = searchClients($_POST['term']);
                    error_log('Search result: ' . json_encode($result));
                    echo json_encode($result);
                    exit;
                case 'search_affiliate_domains':
                    echo json_encode(searchAffiliateDomains($_POST['term']));
                    exit;
                case 'get_referral_details':
                    echo json_encode(getReferralDetails($_POST['client_id'], $admin_id, $admin_name, $admin_ip, $enable_logs));
                    exit;
                case 'get_referral_tree':
                    echo json_encode(getReferralTree($_POST['client_id']));
                    exit;
                case 'check_referral_conflicts':
                    echo json_encode(checkReferralConflicts($_POST['client_email']));
                    exit;
                default:
                    echo json_encode(['status' => 'error', 'message' => 'Invalid action: ' . $_POST['action']]);
                    exit;
            }
        } catch (Exception $e) {
            error_log('Referral Lookup Error: ' . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
            exit;
        }
    }
    
    // Main HTML output
    echo generateHTML($modulelink, $version, $results_per_page);
}

// Functions are now included from functions.php

// generateHTML function is now included from functions.php