<?php
/**
 * Referral Lookup Addon - Helper Functions
 * 
 * Core business logic and helper functions for the Referral Lookup Tool
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

/**
 * Search clients function - Enhanced and fixed
 */
function searchClients($term)
{
    try {
        $term = '%' . $term . '%';
        
        // Get basic client information
        $clients = Capsule::table('tblclients as c')
            ->select([
                'c.id',
                'c.firstname',
                'c.lastname', 
                'c.companyname',
                'c.email',
                'c.datecreated',
                'c.status'
            ])
            ->where(function($query) use ($term) {
                $query->where('c.firstname', 'like', $term)
                      ->orWhere('c.lastname', 'like', $term)
                      ->orWhere('c.email', 'like', $term)
                      ->orWhere('c.companyname', 'like', $term);
            })
            ->orderBy('c.id', 'desc')
            ->limit(50)
            ->get();
        
        $results = [];
        
        foreach ($clients as $client) {
            $hasReferrer = false;
            $referrerName = null;
            $referrerEmail = null;
            $isAffiliate = false;
            $searchMatchType = 'client';
            $matchedDomain = null;
            
            // Check if client has a referrer through affiliate accounts
            try {
                // Get client's services
                $clientServices = Capsule::table('tblhosting')
                    ->where('userid', $client->id)
                    ->get();
                
                $serviceIds = $clientServices->pluck('id')->toArray();
                
                if (!empty($serviceIds)) {
                    // Check if any services are in affiliate accounts
                    $affiliateClaim = Capsule::table('tblaffiliatesaccounts')
                        ->whereIn('relid', $serviceIds)
                        ->first();
                    
                    if ($affiliateClaim) {
                        // Get affiliate details
                        $affiliate = Capsule::table('tblaffiliates')
                            ->where('id', $affiliateClaim->affiliateid)
                            ->first();
                        
                        if ($affiliate) {
                            $referrer = Capsule::table('tblclients')
                                ->where('id', $affiliate->clientid)
                                ->first();
                            
                            if ($referrer) {
                                $hasReferrer = true;
                                $referrerName = trim($referrer->firstname . ' ' . $referrer->lastname);
                                $referrerEmail = $referrer->email;
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                // Error checking affiliate accounts
            }
            
            // Check if client is an affiliate
            try {
                $affiliateRecord = Capsule::table('tblaffiliates')
                    ->where('clientid', $client->id)
                    ->first();
                
                if ($affiliateRecord) {
                    $isAffiliate = true;
                }
            } catch (Exception $e) {
                // Error checking affiliate status
            }
            
            $results[] = [
                'id' => $client->id,
                'name' => trim($client->firstname . ' ' . $client->lastname),
                'email' => $client->email,
                'company' => $client->companyname,
                'created' => date('M j, Y', strtotime($client->datecreated)),
                'status' => $client->status,
                'has_referrer' => $hasReferrer ? 'Yes' : 'No',
                'referrer_name' => $referrerName,
                'referrer_email' => $referrerEmail,
                'is_affiliate' => $isAffiliate,
                'search_match_type' => $searchMatchType,
                'matched_domain' => $matchedDomain
            ];
        }
        
        return ['status' => 'success', 'data' => $results];
        
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => 'Search failed: ' . $e->getMessage()];
    }
}

/**
 * Search specifically for affiliate domains and referral domains
 */
function searchAffiliateDomains($term)
{
    try {
        $term = trim($term);
        if (strlen($term) < 2) {
            return ['status' => 'error', 'message' => 'Search term must be at least 2 characters'];
        }
        
        $searchTerm = '%' . $term . '%';
        
        // Search for domains specifically
        $domains = Capsule::table('tblclients as c')
            ->leftJoin('tbldomains as d', 'c.id', '=', 'd.userid')
            ->select([
                'c.id',
                'c.firstname',
                'c.lastname',
                'c.companyname',
                'c.email',
                'c.status',
                'c.datecreated',
                'd.domain',
                'd.registrationdate'
            ])
            ->where(function($query) use ($searchTerm) {
                $query->where('d.domain', 'like', $searchTerm)
                      ->orWhere(Capsule::raw('SUBSTRING_INDEX(d.domain, ".", 1)'), 'like', $searchTerm);
            })
            ->whereIn('c.status', ['Active', 'Inactive', 'Closed'])
            ->whereNotNull('d.domain')
            ->orderBy('c.id', 'desc')
            ->orderBy('d.registrationdate', 'desc')
            ->limit(50)
            ->get();
        
        $result = [];
        foreach ($domains as $data) {
            $result[] = [
                'client_id' => $data->id,
                'client_name' => trim($data->firstname . ' ' . $data->lastname),
                'client_email' => $data->email,
                'domain' => $data->domain,
                'registration_date' => date('M j, Y', strtotime($data->registrationdate)),
                'has_referrer' => false, // We'll add referral functionality later
                'referrer_name' => null,
                'referrer_email' => null,
                'is_affiliate' => false,
                'status' => $data->status
            ];
        }
        
        return ['status' => 'success', 'data' => $result];
        
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => 'Domain search failed: ' . $e->getMessage()];
    }
}

/**
 * Get detailed referral information
 */
function getReferralDetails($clientId, $adminId, $adminName, $adminIp, $enableLogs)
{
    try {
        // Get client details
        $client = Capsule::table('tblclients')
            ->where('id', $clientId)
            ->first();
        
        if (!$client) {
            return ['status' => 'error', 'message' => 'Client not found'];
        }
        
        // Check if client has a referrer
        $hasReferrer = false;
        $referrerInfo = null;
        
        try {
            // Get client's services
            $clientServices = Capsule::table('tblhosting')
                ->where('userid', $clientId)
                ->get();
            
            $serviceIds = $clientServices->pluck('id')->toArray();
            
            if (!empty($serviceIds)) {
                // Check if any services are in affiliate accounts
                $affiliateClaim = Capsule::table('tblaffiliatesaccounts')
                    ->whereIn('relid', $serviceIds)
                    ->first();
                
                if ($affiliateClaim) {
                    // Get affiliate details
                    $affiliate = Capsule::table('tblaffiliates')
                        ->where('id', $affiliateClaim->affiliateid)
                        ->first();
                    
                    if ($affiliate) {
                        $referrer = Capsule::table('tblclients')
                            ->where('id', $affiliate->clientid)
                            ->first();
                        
                        if ($referrer) {
                            $hasReferrer = true;
                            $referrerInfo = [
                                'id' => $referrer->id,
                                'name' => trim($referrer->firstname . ' ' . $referrer->lastname),
                                'email' => $referrer->email,
                                'affiliate_id' => $affiliate->id,
                                'service_id' => $affiliateClaim->relid,
                                'last_paid' => $affiliateClaim->lastpaid
                            ];
                        }
                    }
                }
            }
        } catch (Exception $e) {
            // Error checking affiliate accounts
        }
        
        // Check if client is an affiliate
        $isAffiliate = false;
        $affiliateStats = null;
        
        try {
            $affiliateRecord = Capsule::table('tblaffiliates')
                ->where('clientid', $clientId)
                ->first();
            
            if ($affiliateRecord) {
                $isAffiliate = true;
                
                // Get affiliate statistics
                $totalReferrals = Capsule::table('tblaffiliatesaccounts')
                    ->where('affiliateid', $affiliateRecord->id)
                    ->count();
                
                $totalCommissions = Capsule::table('tblaffiliateshistory')
                    ->where('affiliateid', $affiliateRecord->id)
                    ->sum('amount');
                
                $affiliateStats = [
                    'total_referrals' => $totalReferrals,
                    'total_commissions' => $totalCommissions,
                    'signup_date' => $affiliateRecord->date
                ];
            }
        } catch (Exception $e) {
            // Error checking affiliate status
        }
        
        // Get basic statistics
        $totalServices = Capsule::table('tblhosting')
            ->where('userid', $clientId)
            ->count();
        
        $totalInvoices = Capsule::table('tblinvoices')
            ->where('userid', $clientId)
            ->count();
        
        // Log the lookup if enabled
        if ($enableLogs) {
            logReferralLookup($adminId, $adminName, $clientId, 'view_details', null, $adminIp);
        }
        
        return [
            'status' => 'success',
            'client' => [
                'id' => $client->id,
                'name' => trim($client->firstname . ' ' . $client->lastname),
                'email' => $client->email,
                'company' => $client->companyname,
                'created' => date('M j, Y', strtotime($client->datecreated)),
                'status' => $client->status
            ],
            'referral_info' => [
                'has_referrer' => $hasReferrer,
                'referrer' => $referrerInfo,
                'is_affiliate' => $isAffiliate,
                'affiliate_stats' => $affiliateStats
            ],
            'statistics' => [
                'total_services' => $totalServices,
                'total_invoices' => $totalInvoices
            ]
        ];
        
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => 'Failed to get referral details: ' . $e->getMessage()];
    }
}

/**
 * Get referral tree (multi-level) - Updated for actual database structure
 */
function getReferralTree($client_id, $level = 0, $max_level = 3)
{
    try {
        if ($level > $max_level) return [];
        
        $client_id = (int)$client_id;
        
        // First, get all services for this client
        $clientServices = Capsule::table('tblhosting')
            ->where('userid', $client_id)
            ->pluck('id')
            ->toArray();
        
        if (empty($clientServices)) {
            return [];
        }
        
        // Find all clients who were referred by this client (through affiliate accounts)
        $referrals = [];
        
        try {
            // Get affiliate ID for this client
            $affiliate = Capsule::table('tblaffiliates')
                ->where('clientid', $client_id)
                ->first();
            
            if ($affiliate) {
                // Find all services that were referred by this affiliate
                $referredServices = Capsule::table('tblaffiliatesaccounts')
                    ->where('affiliateid', $affiliate->id)
                    ->pluck('relid')
                    ->toArray();
                
                if (!empty($referredServices)) {
                    // Get client details for these services
                    $referredClients = Capsule::table('tblhosting')
                        ->whereIn('id', $referredServices)
                        ->get();
                    
                    foreach ($referredClients as $service) {
                        $client = Capsule::table('tblclients')
                            ->where('id', $service->userid)
                            ->first();
                        
                        if ($client) {
                            $referrals[] = [
                                'id' => $client->id,
                                'name' => trim($client->firstname . ' ' . $client->lastname),
                                'email' => $client->email,
                                'created' => date('M j, Y', strtotime($client->datecreated)),
                                'level' => $level + 1,
                                'children' => getReferralTree($client->id, $level + 1, $max_level)
                            ];
                        }
                    }
                }
            }
        } catch (Exception $e) {
            // If affiliate system is not set up, return empty
            error_log('Referral tree error: ' . $e->getMessage());
        }
        
        return $referrals;
        
    } catch (Exception $e) {
        error_log('Referral tree error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Log referral lookup activity
 */
function logReferralLookup($admin_id, $admin_name, $client_id, $action, $search_term, $ip_address)
{
    try {
        Capsule::table('mod_referral_lookup_logs')->insert([
            'admin_id' => (int)$admin_id,
            'admin_name' => $admin_name,
            'client_id' => (int)$client_id,
            'action' => $action,
            'search_term' => $search_term,
            'ip_address' => $ip_address,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } catch (Exception $e) {
        // Log error silently, don't break the main functionality
        error_log('Referral Lookup Log Error: ' . $e->getMessage());
    }
}

/**
 * Generate main HTML interface
 */
function generateHTML($modulelink, $version, $results_per_page)
{
    // Get CSS and JS content
    $cssContent = file_get_contents(__DIR__ . '/assets/referral-lookup.css');
    $jsContent = file_get_contents(__DIR__ . '/assets/referral-lookup.js');
    
    // Check database state
    $clientCount = Capsule::table('tblclients')->count();
    $affiliateCount = 0;
    try {
        $affiliateCount = Capsule::table('tblaffiliates')->count();
    } catch (Exception $e) {
        // Affiliate table might not exist
    }
    
    $html = <<<HTML
<style>{$cssContent}</style>

<div class="container-fluid px-4 py-3">
    <div class="row">
        <div class="col-12">
            <!-- Header Section -->
            <div class="referral-lookup-container">
                
                <div class="referral-lookup-content">
                    <!-- Database Status Section -->
                    <div class="stats-row">
                        <div class="row">
                            <div class="col-lg-4 col-md-6">
                                <div class="stat-item">
                                    <div class="stat-number">{$clientCount}</div>
                                    <div class="stat-label">Total Clients</div>
                                    <i class="fas fa-users fa-2x text-primary opacity-25 mt-2"></i>
                                </div>
                            </div>
                            <div class="col-lg-4 col-md-6">
                                <div class="stat-item">
                                    <div class="stat-number">{$affiliateCount}</div>
                                    <div class="stat-label">Total Affiliates</div>
                                    <i class="fas fa-star fa-2x text-success opacity-25 mt-2"></i>
                                </div>
                            </div>
                            <div class="col-lg-4 col-md-6">
                                <div class="stat-item">
                                    <div class="stat-number">Active</div>
                                    <div class="stat-label">Referral System</div>
                                    <i class="fas fa-link fa-2x text-info opacity-25 mt-2"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Search Section -->
                    <div class="search-section">
                        <div class="search-header">
                            <h5>
                                <i class="fas fa-search text-primary mr-2"></i> Search & Conflict Checker
                            </h5>
                        </div>
                        <div class="search-form">
                    <form id="searchForm">
                        <div class="row g-3 align-items-end">
                            <div class="col-lg-8 col-md-7">
                                <div class="form-group mb-0">
                                    <label for="clientSearch" class="form-label fw-semibold text-dark mb-2">
                                        <i class="fas fa-user text-muted me-1"></i> Search by Name, Email, or Company
                                    </label>
                                    <input type="text" 
                                           class="form-control form-control-lg" 
                                           id="clientSearch" 
                                           placeholder="Enter name, email, or company name..." 
                                           autocomplete="off">
                                    <div class="form-text mt-1">
                                        <i class="fas fa-info-circle text-muted me-1"></i> Minimum 2 characters required for search
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4 col-md-5">
                                <div class="mt-4">
                                    <button type="button" id="searchBtn" class="btn btn-primary">
                                        <i class="fas fa-search me-2"></i> Search
                                    </button>
                                    <button type="button" id="checkConflictBtn" class="btn btn-danger" 
                                            title="Check for referral conflicts (requires valid email)" disabled>
                                        <i class="fas fa-exclamation-triangle me-2"></i> Check Conflicts
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Loading Section -->
            <div id="loadingSpinner" class="text-center py-5" style="display: none;">
                <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                    <span class="sr-only">Loading...</span>
                </div>
                <p class="text-muted fs-5">Searching...</p>
            </div>

            <!-- Results Section -->
            <div id="searchResults" class="search-section" style="display: none;">
                <div class="search-header">
                    <h5>
                        <i class="fas fa-list text-primary mr-2"></i> <span id="resultsTitle">Search Results</span>
                    </h5>
                </div>
                <div id="resultsList"></div>
            </div>

            <!-- No Results Section -->
            <div id="noResults" class="search-section" style="display: none;">
                <div class="text-center py-5">
                    <i class="fas fa-search fa-3x text-muted mb-4 opacity-50"></i>
                    <h4 class="text-muted mb-2">No Results Found</h4>
                    <p class="text-muted mb-0">No clients found matching your search criteria.</p>
                </div>
            </div>
        </div>
    </div>
</div>
        </div>
    </div>
</div>

<!-- Referral Details Modal -->
<div class="modal fade" id="referralModal" tabindex="-1" role="dialog" aria-labelledby="referralModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white border-0">
                <h5 class="modal-title font-weight-bold" id="referralModalLabel">
                    <i class="fas fa-user-friends mr-2"></i> Referral Details
                </h5>
            </div>
            <div class="modal-body p-4" id="referralModalBody">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary mb-3" role="status">
                        <span class="sr-only">Loading...</span>
                    </div>
                    <p class="text-muted mb-0">Loading referral details...</p>
                </div>
            </div>
            <div class="modal-footer border-0 py-3">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="fas fa-times mr-2"></i> Close
                </button>
                <button type="button" class="btn btn-info" id="viewReferralTree">
                    <i class="fas fa-sitemap mr-2"></i> View Referral Tree
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Footer Section -->
<div class="container-fluid px-4 py-3 mt-4">
    <div class="row">
        <div class="col-12">
            <div class="referral-lookup-container">
                <div class="text-center py-3">
                    <div class="d-flex align-items-center justify-content-center mb-2">
                        <a href="https://cyberin.in" target="_blank" class="text-decoration-none font-weight-bold" style="display: block;">  <img src="https://media.cyberin.in/file/cyberin/images/logo_big.png" alt="Cyberin Logo" class="cyberin-logo footer-logo mr-2"></a>
                        <p class="text-muted mt-1 fs-7">
                            <i class="fas fa-shield-alt text-primary mr-1"></i>
                            Referral Lookup Tool v{$version}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    var moduleLink = '{$modulelink}';
</script>
<script>{$jsContent}</script>
HTML;
    
    return $html;
}

/**
 * Check for referral conflicts - verify if a client was referred by multiple people
 */
function checkReferralConflicts($clientEmail)
{
    try {
        $clientEmail = trim($clientEmail);
        if (empty($clientEmail)) {
            return ['status' => 'error', 'message' => 'Email is required'];
        }
        
        // First, find the client
        $client = Capsule::table('tblclients')
            ->where('email', $clientEmail)
            ->first();
        
        if (!$client) {
            return [
                'status' => 'not_found', 
                'message' => "Client with email '{$clientEmail}' not found in database",
                'suggestions' => [
                    'Check if the email is correct',
                    'Client might be in a different database',
                    'Client might have been added after database export'
                ]
            ];
        }
        
        $result = [
            'status' => 'success',
            'client' => [
                'id' => $client->id,
                'name' => trim($client->firstname . ' ' . $client->lastname),
                'email' => $client->email,
                'company' => $client->companyname,
                'created' => date('M j, Y', strtotime($client->datecreated)),
                'status' => $client->status
            ],
            'referral_analysis' => [
                'has_referrer_id_column' => false,
                'referrer_id_value' => null,
                'potential_referrers' => [],
                'affiliate_claims' => [],
                'conflict_detected' => false
            ]
        ];
        
        // Check if referrer_id column exists in tblclients
        try {
            $columns = Capsule::select('SHOW COLUMNS FROM tblclients LIKE "referrer_id"');
            if (count($columns) > 0) {
                $result['referral_analysis']['has_referrer_id_column'] = true;
                
                // Get the referrer_id value
                $referrerId = $client->referrer_id ?? null;
                $result['referral_analysis']['referrer_id_value'] = $referrerId;
                
                if ($referrerId) {
                    // Find the referrer details
                    $referrer = Capsule::table('tblclients')
                        ->where('id', $referrerId)
                        ->first();
                    
                    if ($referrer) {
                        $result['referral_analysis']['potential_referrers'][] = [
                            'id' => $referrer->id,
                            'name' => trim($referrer->firstname . ' ' . $referrer->lastname),
                            'email' => $referrer->email,
                            'source' => 'referrer_id column',
                            'is_affiliate' => false
                        ];
                    }
                }
            }
        } catch (Exception $e) {
            // Column doesn't exist
        }
        
        // Check affiliate accounts table for referral relationships
        try {
            // Get all services/hosting accounts for this client
            $clientServices = Capsule::table('tblhosting')
                ->where('userid', $client->id)
                ->get();
            
            $serviceIds = $clientServices->pluck('id')->toArray();
            
            if (!empty($serviceIds)) {
                // Check if any of these services are in affiliate accounts
                $affiliateClaims = Capsule::table('tblaffiliatesaccounts')
                    ->whereIn('relid', $serviceIds)
                    ->get();
                
                foreach ($affiliateClaims as $claim) {
                    // Get affiliate details
                    $affiliate = Capsule::table('tblaffiliates')
                        ->where('id', $claim->affiliateid)
                        ->first();
                    
                    if ($affiliate) {
                        // Get affiliate client details
                        $affiliateClient = Capsule::table('tblclients')
                            ->where('id', $affiliate->clientid)
                            ->first();
                        
                        if ($affiliateClient) {
                            $affiliateName = trim($affiliateClient->firstname . ' ' . $affiliateClient->lastname);
                            $affiliateEmail = $affiliateClient->email;
                            
                            // Only add if we have valid affiliate information
                            if (!empty($affiliateName) && !empty($affiliateEmail)) {
                                $result['referral_analysis']['affiliate_claims'][] = [
                                    'table' => 'tblaffiliatesaccounts',
                                    'claim_id' => $claim->id,
                                    'affiliate_id' => $claim->affiliateid,
                                    'service_id' => $claim->relid,
                                    'affiliate_name' => $affiliateName,
                                    'affiliate_email' => $affiliateEmail,
                                    'last_paid' => $claim->lastpaid,
                                    'source' => 'affiliate accounts table'
                                ];
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            // Table might not exist or other error
            error_log('Error checking affiliate accounts: ' . $e->getMessage());
        }
        
        // Check other affiliate tables for any additional claims
        $affiliateTables = [
            'tblaffiliates_referrers' => 'referrer',
            'tblaffiliates' => 'clientid',
            'tblaffiliateshistory' => 'clientid'
        ];
        
        foreach ($affiliateTables as $table => $column) {
            try {
                $affiliateClaims = Capsule::table($table)
                    ->where($column, $client->id)
                    ->get();
                
                foreach ($affiliateClaims as $claim) {
                    // Get affiliate details based on table structure
                    $affiliateId = null;
                    $affiliateName = 'Unknown';
                    $affiliateEmail = 'Unknown';
                    
                    if ($table === 'tblaffiliates_referrers') {
                        // This table stores referrer relationships
                        $affiliateId = $claim->affiliateid ?? $claim->id;
                        if ($affiliateId) {
                            $affiliate = Capsule::table('tblaffiliates')
                                ->where('id', $affiliateId)
                                ->first();
                            if ($affiliate) {
                                $affiliateClient = Capsule::table('tblclients')
                                    ->where('id', $affiliate->clientid)
                                    ->first();
                                if ($affiliateClient) {
                                    $affiliateName = trim($affiliateClient->firstname . ' ' . $affiliateClient->lastname);
                                    $affiliateEmail = $affiliateClient->email;
                                }
                            }
                        }
                    } elseif ($table === 'tblaffiliates') {
                        // This table stores affiliate records
                        $affiliateId = $claim->id;
                        $affiliateClient = Capsule::table('tblclients')
                            ->where('id', $claim->clientid)
                            ->first();
                        if ($affiliateClient) {
                            $affiliateName = trim($affiliateClient->firstname . ' ' . $affiliateClient->lastname);
                            $affiliateEmail = $affiliateClient->email;
                        }
                    } elseif ($table === 'tblaffiliateshistory') {
                        // This table stores affiliate history
                        $affiliateId = $claim->affiliateid ?? $claim->id;
                        if ($affiliateId) {
                            $affiliate = Capsule::table('tblaffiliates')
                                ->where('id', $affiliateId)
                                ->first();
                            if ($affiliate) {
                                $affiliateClient = Capsule::table('tblclients')
                                    ->where('id', $affiliate->clientid)
                                    ->first();
                                if ($affiliateClient) {
                                    $affiliateName = trim($affiliateClient->firstname . ' ' . $affiliateClient->lastname);
                                    $affiliateEmail = $affiliateClient->email;
                                }
                            }
                        }
                    }
                    
                    // Only add if we have valid affiliate information
                    if ($affiliateName !== 'Unknown' && $affiliateEmail !== 'Unknown') {
                        $result['referral_analysis']['affiliate_claims'][] = [
                            'table' => $table,
                            'claim_id' => $claim->id ?? 'N/A',
                            'affiliate_id' => $affiliateId,
                            'affiliate_name' => $affiliateName,
                            'affiliate_email' => $affiliateEmail,
                            'source' => $table . ' table'
                        ];
                    }
                }
            } catch (Exception $e) {
                // Table might not exist
                error_log("Error checking {$table}: " . $e->getMessage());
            }
        }
        
        // Enhanced conflict analysis with detailed affiliate information
        $allReferrers = [];
        
        // Add potential referrers from referrer_id column
        foreach ($result['referral_analysis']['potential_referrers'] as $referrer) {
            $allReferrers[] = [
                'type' => 'Database Referrer',
                'name' => $referrer['name'],
                'email' => $referrer['email'],
                'source' => $referrer['source'],
                'details' => "Client ID: #{$referrer['id']}",
                'priority' => 1
            ];
        }
        
        // Add affiliate claims with detailed information
        foreach ($result['referral_analysis']['affiliate_claims'] as $claim) {
            $affiliateDetails = "Affiliate ID: #{$claim['affiliate_id']}";
            if (isset($claim['service_id'])) {
                $affiliateDetails .= " | Service ID: #{$claim['service_id']}";
            }
            if (isset($claim['last_paid'])) {
                $affiliateDetails .= " | Last Paid: " . ($claim['last_paid'] ?: 'Never');
            }
            
            $allReferrers[] = [
                'type' => 'Affiliate Claim',
                'name' => $claim['affiliate_name'] ?? 'Unknown',
                'email' => $claim['affiliate_email'] ?? 'Unknown',
                'source' => $claim['source'],
                'details' => $affiliateDetails,
                'table' => $claim['table'],
                'claim_id' => $claim['claim_id'],
                'priority' => 2
            ];
        }
        
        // Check for additional referral sources
        $additionalSources = [];
        
        // Check custom fields for referral information
        try {
            $customFields = Capsule::table('tblcustomfields')
                ->where('type', 'client')
                ->where('fieldname', 'like', '%refer%')
                ->orWhere('fieldname', 'like', '%affiliate%')
                ->get();
            
            foreach ($customFields as $field) {
                $fieldValue = Capsule::table('tblcustomfieldsvalues')
                    ->where('fieldid', $field->id)
                    ->where('relid', $client->id)
                    ->value('value');
                
                if ($fieldValue) {
                    $additionalSources[] = [
                        'type' => 'Custom Field',
                        'field_name' => $field->fieldname,
                        'value' => $fieldValue,
                        'source' => 'Custom field: ' . $field->fieldname
                    ];
                }
            }
        } catch (Exception $e) {
            // Custom fields might not exist
        }
        
        // Check for any notes or comments mentioning referrals
        try {
            $notes = Capsule::table('tblticketreplies')
                ->where('userid', $client->id)
                ->where('message', 'like', '%refer%')
                ->orWhere('message', 'like', '%affiliate%')
                ->get();
            
            if ($notes->count() > 0) {
                $additionalSources[] = [
                    'type' => 'Support Notes',
                    'count' => $notes->count(),
                    'source' => 'Support tickets mentioning referrals'
                ];
            }
        } catch (Exception $e) {
            // Tickets might not exist
        }
        
        // Determine conflict status and create detailed analysis
        $totalReferrers = count($allReferrers);
        $uniqueAffiliates = collect($allReferrers)->pluck('email')->unique()->count();
        
        if ($totalReferrers > 1) {
            $result['referral_analysis']['conflict_detected'] = true;
            $result['referral_analysis']['conflict_message'] = "Multiple referral claims detected! Found {$totalReferrers} claims from {$uniqueAffiliates} unique affiliates.";
            $result['referral_analysis']['conflict_severity'] = $uniqueAffiliates > 2 ? 'High' : 'Medium';
        } elseif ($totalReferrers == 1) {
            $result['referral_analysis']['conflict_detected'] = false;
            $result['referral_analysis']['conflict_message'] = "Single referral claim found. No conflicts detected.";
            $result['referral_analysis']['conflict_severity'] = 'None';
        } else {
            $result['referral_analysis']['conflict_detected'] = false;
            $result['referral_analysis']['conflict_message'] = "No referral claims found. Client appears to be a direct registration.";
            $result['referral_analysis']['conflict_severity'] = 'None';
        }
        
        // Add comprehensive referral analysis
        $result['referral_analysis']['all_referrers'] = $allReferrers;
        $result['referral_analysis']['additional_sources'] = $additionalSources;
        $result['referral_analysis']['analysis_summary'] = [
            'total_claims' => $totalReferrers,
            'unique_affiliates' => $uniqueAffiliates,
            'database_referrers' => count($result['referral_analysis']['potential_referrers']),
            'affiliate_claims' => count($result['referral_analysis']['affiliate_claims']),
            'additional_sources' => count($additionalSources)
        ];
        
        return $result;
        
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => 'Failed to check referral conflicts: ' . $e->getMessage()];
    }
}