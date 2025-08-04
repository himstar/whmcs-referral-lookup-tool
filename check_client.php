<?php
/**
 * Quick Client Check Script
 * 
 * This script helps you quickly check if a specific client exists in your WHMCS database
 * and verify their referral status.
 * 
 * Usage: php check_client.php
 * 
 * Note: Update the database configuration below before running
 */

// Database configuration - update these values
$db_host = 'localhost';
$db_name = 'whmcs-dev'; // Update to your database name
$db_user = 'root';      // Update to your database user
$db_pass = '';          // Update to your database password

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "🔍 WHMCS Client Check Tool\n";
    echo "==========================\n\n";
    
    // Check for the specific client
    $clientEmail = 'pradeep@softradix.com';
    
    echo "Searching for client: $clientEmail\n";
    echo "--------------------------------\n";
    
    // Check if client exists
    $stmt = $pdo->prepare("SELECT * FROM tblclients WHERE email = ?");
    $stmt->execute([$clientEmail]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($client) {
        echo "✅ CLIENT FOUND!\n";
        echo "ID: " . $client['id'] . "\n";
        echo "Name: " . $client['firstname'] . " " . $client['lastname'] . "\n";
        echo "Email: " . $client['email'] . "\n";
        echo "Company: " . ($client['companyname'] ?: 'N/A') . "\n";
        echo "Created: " . $client['datecreated'] . "\n";
        echo "Status: " . $client['status'] . "\n";
        
        // Check if referrer_id column exists
        $stmt = $pdo->query("SHOW COLUMNS FROM tblclients LIKE 'referrer_id'");
        $referrerColumn = $stmt->fetch();
        
        if ($referrerColumn) {
            echo "\n📋 Referrer ID Column: EXISTS\n";
            echo "Referrer ID Value: " . ($client['referrer_id'] ?: 'NULL') . "\n";
            
            if ($client['referrer_id']) {
                // Find referrer details
                $stmt = $pdo->prepare("SELECT id, firstname, lastname, email FROM tblclients WHERE id = ?");
                $stmt->execute([$client['referrer_id']]);
                $referrer = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($referrer) {
                    echo "Referrer: " . $referrer['firstname'] . " " . $referrer['lastname'] . " (" . $referrer['email'] . ")\n";
                }
            }
        } else {
            echo "\n📋 Referrer ID Column: DOES NOT EXIST\n";
        }
        
        // Check affiliate accounts table for referral relationships
        echo "\n🔍 Checking Affiliate Accounts Table:\n";
        
        try {
            // Get client's services
            $stmt = $pdo->prepare("SELECT id FROM tblhosting WHERE userid = ?");
            $stmt->execute([$client['id']]);
            $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($services)) {
                $serviceIds = array_column($services, 'id');
                echo "- Client has " . count($serviceIds) . " services: " . implode(', ', $serviceIds) . "\n";
                
                // Check if any services are in affiliate accounts
                $placeholders = str_repeat('?,', count($serviceIds) - 1) . '?';
                $stmt = $pdo->prepare("SELECT * FROM tblaffiliatesaccounts WHERE relid IN ($placeholders)");
                $stmt->execute($serviceIds);
                $affiliateClaims = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($affiliateClaims)) {
                    echo "✅ REFERRAL FOUND!\n";
                    foreach ($affiliateClaims as $claim) {
                        echo "- Service ID: " . $claim['relid'] . " is referred by Affiliate ID: " . $claim['affiliateid'] . "\n";
                        
                        // Get affiliate details
                        $stmt = $pdo->prepare("SELECT * FROM tblaffiliates WHERE id = ?");
                        $stmt->execute([$claim['affiliateid']]);
                        $affiliate = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($affiliate) {
                            // Get affiliate client details
                            $stmt = $pdo->prepare("SELECT id, firstname, lastname, email FROM tblclients WHERE id = ?");
                            $stmt->execute([$affiliate['clientid']]);
                            $affiliateClient = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($affiliateClient) {
                                echo "  Referrer: " . $affiliateClient['firstname'] . " " . $affiliateClient['lastname'] . " (" . $affiliateClient['email'] . ")\n";
                                echo "  Last Paid: " . ($claim['lastpaid'] ?: 'Never') . "\n";
                            }
                        }
                    }
                } else {
                    echo "❌ No referral claims found in affiliate accounts table\n";
                }
            } else {
                echo "- Client has no services/hosting accounts\n";
            }
        } catch (Exception $e) {
            echo "- Error checking affiliate accounts: " . $e->getMessage() . "\n";
        }
        
        // Check if client is an affiliate
        echo "\n🔍 Checking if client is an affiliate:\n";
        try {
            $stmt = $pdo->prepare("SELECT * FROM tblaffiliates WHERE clientid = ?");
            $stmt->execute([$client['id']]);
            $affiliateRecord = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($affiliateRecord) {
                echo "✅ Client is registered as an affiliate!\n";
                echo "- Affiliate ID: " . $affiliateRecord['id'] . "\n";
                echo "- Signup Date: " . $affiliateRecord['date'] . "\n";
                
                // Count referrals made by this affiliate
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tblaffiliatesaccounts WHERE affiliateid = ?");
                $stmt->execute([$affiliateRecord['id']]);
                $referralCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                echo "- Total Referrals Made: " . $referralCount . "\n";
            } else {
                echo "❌ Client is not registered as an affiliate\n";
            }
        } catch (Exception $e) {
            echo "- Error checking affiliate status: " . $e->getMessage() . "\n";
        }
        
        echo "\n💡 ANALYSIS:\n";
        if (!empty($affiliateClaims)) {
            echo "✅ Client has referral claims in affiliate accounts table\n";
            echo "✅ This confirms the referral relationship exists\n";
            echo "✅ Safe to credit the affiliate for this referral\n";
        } else {
            echo "❌ No referral claims found in affiliate accounts table\n";
            echo "⚠️  This client appears to be a direct registration\n";
            echo "⚠️  Manual verification required before crediting affiliate\n";
        }
        
    } else {
        echo "❌ CLIENT NOT FOUND!\n";
        echo "\nPossible reasons:\n";
        echo "1. Email address is incorrect\n";
        echo "2. Client is in a different database\n";
        echo "3. Client was added after database export\n";
        echo "4. Database name is different\n";
        
        // Show some sample clients
        echo "\n📋 Sample clients in database:\n";
        $stmt = $pdo->query("SELECT id, firstname, lastname, email FROM tblclients LIMIT 5");
        $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($clients as $sampleClient) {
            echo "- " . $sampleClient['firstname'] . " " . $sampleClient['lastname'] . " (" . $sampleClient['email'] . ")\n";
        }
    }
    
    echo "\n🔧 Database Info:\n";
    echo "Database: $db_name\n";
    echo "Total Clients: " . $pdo->query("SELECT COUNT(*) FROM tblclients")->fetchColumn() . "\n";
    
} catch (Exception $e) {
    echo "❌ Database Error: " . $e->getMessage() . "\n";
    echo "\nPlease check your database configuration:\n";
    echo "- Host: $db_host\n";
    echo "- Database: $db_name\n";
    echo "- User: $db_user\n";
}
?> 