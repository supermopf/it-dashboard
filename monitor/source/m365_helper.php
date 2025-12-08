<?php
/**
 * Microsoft 365 Service Health Helper Functions
 * User: victor.lange
 * Date: 08.12.2024
 */

/**
 * Get Microsoft Graph API Access Token
 * @return string|null Access token or null on failure
 */
function getM365AccessToken() {
    $tenant_id = M365_TENANT_ID;
    $client_id = M365_CLIENT_ID;
    $client_secret = M365_CLIENT_SECRET;
    
    if (empty($tenant_id) || empty($client_id) || empty($client_secret)) {
        return null;
    }
    
    $token_url = "https://login.microsoftonline.com/$tenant_id/oauth2/v2.0/token";
    
    $post_data = [
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'scope' => 'https://graph.microsoft.com/.default',
        'grant_type' => 'client_credentials'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $token_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $token_data = json_decode($response, true);
        return $token_data['access_token'] ?? null;
    }
    
    return null;
}

/**
 * Get M365 Service Health Status
 * @param string $access_token
 * @return array Array of service health data
 */
function getM365ServiceHealth($access_token) {
    if (empty($access_token)) {
        return [];
    }
    
    $api_url = "https://graph.microsoft.com/v1.0/admin/serviceAnnouncement/healthOverviews";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $access_token",
        "Content-Type: application/json"
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $data = json_decode($response, true);
        return $data['value'] ?? [];
    }
    
    return [];
}

/**
 * Get current issues for M365 services
 * @param string $access_token
 * @return array Array of current issues
 */
function getM365CurrentIssues($access_token) {
    if (empty($access_token)) {
        return [];
    }
    
    $api_url = "https://graph.microsoft.com/v1.0/admin/serviceAnnouncement/issues";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $access_token",
        "Content-Type: application/json"
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $data = json_decode($response, true);
        return $data['value'] ?? [];
    }
    
    return [];
}

/**
 * Get Bootstrap panel class based on service status
 * @param string $status
 * @return string Bootstrap panel class
 */
function getStatusPanelClass($status) {
    switch (strtolower($status)) {
        case 'servicedegradation':
        case 'serviceinterruption':
            return 'panel-danger';
        case 'restoringservice':
        case 'extendedrecovery':
            return 'panel-warning';
        case 'serviceoperational':
            return 'panel-success';
        case 'investigatingsuspendedservice':
            return 'panel-warning';
        default:
            return 'panel-default';
    }
}

/**
 * Get human-readable status text (German)
 * @param string $status
 * @return string
 */
function getStatusText($status) {
    switch (strtolower($status)) {
        case 'servicedegradation':
            return 'Beeinträchtigt';
        case 'serviceinterruption':
            return 'Störung';
        case 'restoringservice':
            return 'Wiederherstellung';
        case 'extendedrecovery':
            return 'Erweiterte Wiederherstellung';
        case 'serviceoperational':
            return 'Betriebsbereit';
        case 'investigatingsuspendedservice':
            return 'Wird untersucht';
        case 'falsepositive':
            return 'Fehlalarm';
        case 'pir':
        case 'postincidentreportpublished':
            return 'Bericht veröffentlicht';
        default:
            return ucfirst($status);
    }
}

/**
 * Get friendly service name (German)
 * @param string $service
 * @return string
 */
function getServiceDisplayName($service) {
    $names = [
        'Exchange' => 'Exchange Online',
        'SharePoint' => 'SharePoint Online',
        'MicrosoftTeams' => 'Microsoft Teams',
        'skypeforbusiness' => 'Skype for Business',
        'OneDriveForBusiness' => 'OneDrive for Business',
        'AzureActiveDirectory' => 'Azure Active Directory',
        'DynamicsCRM' => 'Dynamics 365',
        'MicrosoftIntune' => 'Microsoft Intune',
        'PowerBIcom' => 'Power BI',
        'Planner' => 'Microsoft Planner',
        'Yammer' => 'Yammer',
        'Forms' => 'Microsoft Forms',
        'SwayEnterprise' => 'Sway',
        'MicrosoftFlow' => 'Power Automate',
        'PowerApps' => 'Power Apps',
        'OfficeontheWeb' => 'Office Online',
        'MobileDeviceManagement' => 'Mobile Device Management'
    ];
    
    return $names[$service] ?? $service;
}

/**
 * Get status icon for service
 * @param string $status
 * @return string Font Awesome icon class
 */
function getStatusIcon($status) {
    switch (strtolower($status)) {
        case 'servicedegradation':
            return 'fa-exclamation-triangle';
        case 'serviceinterruption':
            return 'fa-times-circle';
        case 'restoringservice':
        case 'extendedrecovery':
            return 'fa-wrench';
        case 'serviceoperational':
            return 'fa-check-circle';
        case 'investigatingsuspendedservice':
            return 'fa-search';
        default:
            return 'fa-question-circle';
    }
}
