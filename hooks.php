<?php
/**
 * WHMCS Hooks for Referral Lookup Addon
 * 
 * This file contains hooks to enhance the addon's appearance in the WHMCS admin area
 * Performance optimized - only loads when needed
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Hook to inject custom CSS for the addon in admin area
 * Performance optimized - only loads on specific pages
 */
add_hook('AdminHeadOutput', 1, function($vars) {
    // Performance check: Only run on specific admin pages
    if (!isset($_GET['module']) || $_GET['module'] !== 'addonmodules') {
        return '';
    }
    
    // Performance check: Only run for admin users
    if (!isset($_SESSION['adminid']) || empty($_SESSION['adminid'])) {
        return '';
    }
    
    // Performance check: Ensure we're not in a critical context
    if (function_exists('referral_lookup_performance_gate') && !referral_lookup_performance_gate()) {
        return '';
    }
    
    // Performance check: Cache CSS content to avoid file reads
    static $cssContent = null;
    if ($cssContent === null) {
        $cssPath = ROOTDIR . '/modules/addons/referral_lookup/assets/admin-styles.css';
        if (file_exists($cssPath)) {
            $cssContent = file_get_contents($cssPath);
        } else {
            $cssContent = '';
        }
    }
    
    return $cssContent ? '<style>' . $cssContent . '</style>' : '';
});

/**
 * Hook to enhance addon module display
 * Performance optimized - only loads when needed
 */
add_hook('AdminAreaPage', 1, function($vars) {
    // Performance check: Only run on specific admin pages
    if (!isset($_GET['module']) || $_GET['module'] !== 'addonmodules') {
        return '';
    }
    
    // Performance check: Only run for admin users
    if (!isset($_SESSION['adminid']) || empty($_SESSION['adminid'])) {
        return '';
    }
    
    // Performance check: Ensure we're not in a critical context
    if (function_exists('referral_lookup_performance_gate') && !referral_lookup_performance_gate()) {
        return '';
    }
    
    // Performance check: Cache JavaScript to avoid repeated generation
    static $jsContent = null;
    if ($jsContent === null) {
        $jsContent = "
        <script>
        (function() {
            'use strict';
            
            // Performance: Use event delegation instead of multiple event handlers
            document.addEventListener('DOMContentLoaded', function() {
                // Performance: Use more efficient selectors
                var addonCards = document.querySelectorAll('.addon-module-card');
                var processed = false;
                
                for (var i = 0; i < addonCards.length; i++) {
                    var card = addonCards[i];
                    var moduleNameElement = card.querySelector('.module-name');
                    
                    if (moduleNameElement && 
                        (moduleNameElement.textContent.toLowerCase().includes('referral_lookup') || 
                         moduleNameElement.textContent.toLowerCase().includes('referral lookup'))) {
                        
                        // Performance: Set attributes directly instead of using jQuery
                        card.setAttribute('data-module', 'referral_lookup');
                        card.classList.add('cyberin-addon');
                        
                        // Performance: Only enhance author if not already enhanced
                        var authorElement = card.querySelector('.author');
                        if (authorElement && !authorElement.querySelector('img')) {
                            authorElement.innerHTML = '<img src=\"https://media.cyberin.in/file/cyberin/images/logo_big.png\" alt=\"Cyberin\" style=\"height: 20px; width: auto; vertical-align: middle; margin-right: 5px;\"> Cyberin';
                        }
                        
                        processed = true;
                    }
                }
                
                // Performance: Only add hover effects if we found our addon
                if (processed) {
                    var cyberinAddons = document.querySelectorAll('.cyberin-addon');
                    for (var j = 0; j < cyberinAddons.length; j++) {
                        cyberinAddons[j].addEventListener('mouseenter', function() {
                            this.classList.add('addon-hover');
                        });
                        cyberinAddons[j].addEventListener('mouseleave', function() {
                            this.classList.remove('addon-hover');
                        });
                    }
                }
            });
        })();
        </script>";
    }
    
    return $jsContent;
}); 