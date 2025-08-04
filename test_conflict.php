<?php
/**
 * Test Enhanced Conflict Detection
 * 
 * This file helps test the enhanced conflict detection logic
 * with your production database data
 */

// Include WHMCS configuration
require_once '../../../configuration.php';
require_once '../../../init.php';

// Include the addon functions
require_once 'functions.php';

// Test email from your production database
$testEmail = 'Tellyfire051@gmail.com';

echo "<h2>Testing Enhanced Conflict Detection</h2>";
echo "<p><strong>Test Email:</strong> {$testEmail}</p>";

try {
    // Test the enhanced conflict detection
    $result = checkReferralConflicts($testEmail);
    
    echo "<h3>Results:</h3>";
    echo "<pre>";
    print_r($result);
    echo "</pre>";
    
    if ($result['status'] === 'success') {
        $analysis = $result['referral_analysis'];
        
        echo "<h3>Analysis Summary:</h3>";
        echo "<ul>";
        echo "<li><strong>Total Claims:</strong> " . ($analysis['analysis_summary']['total_claims'] ?? 0) . "</li>";
        echo "<li><strong>Unique Affiliates:</strong> " . ($analysis['analysis_summary']['unique_affiliates'] ?? 0) . "</li>";
        echo "<li><strong>Conflict Detected:</strong> " . ($analysis['conflict_detected'] ? 'Yes' : 'No') . "</li>";
        echo "<li><strong>Conflict Severity:</strong> " . ($analysis['conflict_severity'] ?? 'None') . "</li>";
        echo "</ul>";
        
        if (!empty($analysis['all_referrers'])) {
            echo "<h3>All Referrers:</h3>";
            foreach ($analysis['all_referrers'] as $referrer) {
                echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px 0;'>";
                echo "<strong>Type:</strong> " . $referrer['type'] . "<br>";
                echo "<strong>Name:</strong> " . $referrer['name'] . "<br>";
                echo "<strong>Email:</strong> " . $referrer['email'] . "<br>";
                echo "<strong>Source:</strong> " . $referrer['source'] . "<br>";
                echo "<strong>Details:</strong> " . $referrer['details'] . "<br>";
                echo "<strong>Priority:</strong> " . $referrer['priority'] . "<br>";
                echo "</div>";
            }
        }
        
        if (!empty($analysis['additional_sources'])) {
            echo "<h3>Additional Sources:</h3>";
            foreach ($analysis['additional_sources'] as $source) {
                echo "<div style='border: 1px solid #ddd; padding: 5px; margin: 5px 0;'>";
                echo "<strong>Type:</strong> " . $source['type'] . "<br>";
                echo "<strong>Source:</strong> " . $source['source'] . "<br>";
                if (isset($source['value'])) {
                    echo "<strong>Value:</strong> " . $source['value'] . "<br>";
                }
                if (isset($source['count'])) {
                    echo "<strong>Count:</strong> " . $source['count'] . "<br>";
                }
                echo "</div>";
            }
        }
    }
    
} catch (Exception $e) {
    echo "<h3>Error:</h3>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><em>Test completed at " . date('Y-m-d H:i:s') . "</em></p>";
?> 