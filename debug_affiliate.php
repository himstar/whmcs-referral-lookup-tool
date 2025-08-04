<?php
/**
 * Debug Affiliate Claims
 * 
 * This file helps debug affiliate claim issues
 */

// Include WHMCS configuration
require_once '../../../configuration.php';
require_once '../../../init.php';

// Test email
$testEmail = 'Tellyfire051@gmail.com';

echo "<h2>Debugging Affiliate Claims</h2>";
echo "<p><strong>Test Email:</strong> {$testEmail}</p>";

try {
    // Find the client
    $client = Capsule::table('tblclients')
        ->where('email', $testEmail)
        ->first();
    
    if (!$client) {
        echo "<p style='color: red;'>Client not found!</p>";
        exit;
    }
    
    echo "<h3>Client Found:</h3>";
    echo "<p><strong>ID:</strong> {$client->id}</p>";
    echo "<p><strong>Name:</strong> {$client->firstname} {$client->lastname}</p>";
    echo "<p><strong>Email:</strong> {$client->email}</p>";
    
    // Check all affiliate tables
    echo "<h3>Checking All Affiliate Tables:</h3>";
    
    // 1. Check tblaffiliatesaccounts
    echo "<h4>1. tblaffiliatesaccounts:</h4>";
    try {
        $clientServices = Capsule::table('tblhosting')
            ->where('userid', $client->id)
            ->get();
        
        $serviceIds = $clientServices->pluck('id')->toArray();
        echo "<p>Client Services: " . implode(', ', $serviceIds) . "</p>";
        
        if (!empty($serviceIds)) {
            $affiliateClaims = Capsule::table('tblaffiliatesaccounts')
                ->whereIn('relid', $serviceIds)
                ->get();
            
            echo "<p>Found " . $affiliateClaims->count() . " affiliate claims</p>";
            
            foreach ($affiliateClaims as $claim) {
                echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px 0;'>";
                echo "<strong>Claim ID:</strong> {$claim->id}<br>";
                echo "<strong>Affiliate ID:</strong> {$claim->affiliateid}<br>";
                echo "<strong>Service ID:</strong> {$claim->relid}<br>";
                echo "<strong>Last Paid:</strong> " . ($claim->lastpaid ?: 'Never') . "<br>";
                
                // Get affiliate details
                $affiliate = Capsule::table('tblaffiliates')
                    ->where('id', $claim->affiliateid)
                    ->first();
                
                if ($affiliate) {
                    echo "<strong>Affiliate Record:</strong> Found<br>";
                    echo "<strong>Affiliate Client ID:</strong> {$affiliate->clientid}<br>";
                    
                    $affiliateClient = Capsule::table('tblclients')
                        ->where('id', $affiliate->clientid)
                        ->first();
                    
                    if ($affiliateClient) {
                        echo "<strong>Affiliate Name:</strong> {$affiliateClient->firstname} {$affiliateClient->lastname}<br>";
                        echo "<strong>Affiliate Email:</strong> {$affiliateClient->email}<br>";
                    } else {
                        echo "<strong>Affiliate Client:</strong> NOT FOUND<br>";
                    }
                } else {
                    echo "<strong>Affiliate Record:</strong> NOT FOUND<br>";
                }
                echo "</div>";
            }
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
    }
    
    // 2. Check tblaffiliates_referrers
    echo "<h4>2. tblaffiliates_referrers:</h4>";
    try {
        $referrerClaims = Capsule::table('tblaffiliates_referrers')
            ->where('referrer', $client->id)
            ->get();
        
        echo "<p>Found " . $referrerClaims->count() . " referrer claims</p>";
        
        foreach ($referrerClaims as $claim) {
            echo "<div style='border: 1px solid #ddd; padding: 10px; margin: 10px 0;'>";
            echo "<strong>Claim ID:</strong> {$claim->id}<br>";
            echo "<strong>Affiliate ID:</strong> " . ($claim->affiliateid ?? 'N/A') . "<br>";
            echo "<strong>Referrer:</strong> {$claim->referrer}<br>";
            
            if (isset($claim->affiliateid)) {
                $affiliate = Capsule::table('tblaffiliates')
                    ->where('id', $claim->affiliateid)
                    ->first();
                
                if ($affiliate) {
                    $affiliateClient = Capsule::table('tblclients')
                        ->where('id', $affiliate->clientid)
                        ->first();
                    
                    if ($affiliateClient) {
                        echo "<strong>Affiliate Name:</strong> {$affiliateClient->firstname} {$affiliateClient->lastname}<br>";
                        echo "<strong>Affiliate Email:</strong> {$affiliateClient->email}<br>";
                    }
                }
            }
            echo "</div>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
    }
    
    // 3. Check tblaffiliates
    echo "<h4>3. tblaffiliates:</h4>";
    try {
        $affiliateRecords = Capsule::table('tblaffiliates')
            ->where('clientid', $client->id)
            ->get();
        
        echo "<p>Found " . $affiliateRecords->count() . " affiliate records</p>";
        
        foreach ($affiliateRecords as $record) {
            echo "<div style='border: 1px solid #eee; padding: 10px; margin: 10px 0;'>";
            echo "<strong>Affiliate ID:</strong> {$record->id}<br>";
            echo "<strong>Client ID:</strong> {$record->clientid}<br>";
            echo "<strong>Date:</strong> {$record->date}<br>";
            echo "</div>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
    }
    
    // 4. Check tblaffiliateshistory
    echo "<h4>4. tblaffiliateshistory:</h4>";
    try {
        $historyRecords = Capsule::table('tblaffiliateshistory')
            ->where('clientid', $client->id)
            ->get();
        
        echo "<p>Found " . $historyRecords->count() . " history records</p>";
        
        foreach ($historyRecords as $record) {
            echo "<div style='border: 1px solid #eee; padding: 10px; margin: 10px 0;'>";
            echo "<strong>ID:</strong> {$record->id}<br>";
            echo "<strong>Affiliate ID:</strong> " . ($record->affiliateid ?? 'N/A') . "<br>";
            echo "<strong>Client ID:</strong> {$record->clientid}<br>";
            echo "<strong>Date:</strong> {$record->date}<br>";
            echo "</div>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
    }
    
} catch (Exception $e) {
    echo "<h3>Error:</h3>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><em>Debug completed at " . date('Y-m-d H:i:s') . "</em></p>";
?> 