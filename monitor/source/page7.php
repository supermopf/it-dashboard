<?php
/**
 * Microsoft 365 Service Health Status Dashboard
 * User: victor.lange
 * Date: 08.12.2024
 */

require("../../config.php");
require("m365_helper.php");

// Get configured services to monitor
$configured_services = array_filter(array_map('trim', explode(',', M365_SERVICES)));

// Get access token
$access_token = getM365AccessToken();

if (!$access_token) {
    echo '<div class="alert alert-danger" role="alert">';
    echo '<h4><i class="fa fa-exclamation-triangle"></i> Microsoft 365 API-Verbindung fehlgeschlagen</h4>';
    echo '<p>Bitte überprüfen Sie die Konfiguration in config.php:</p>';
    echo '<ul>';
    echo '<li>M365_TENANT_ID</li>';
    echo '<li>M365_CLIENT_ID</li>';
    echo '<li>M365_CLIENT_SECRET</li>';
    echo '</ul>';
    echo '<p class="small">Die Azure AD App benötigt die Permission: <code>ServiceHealth.Read.All</code></p>';
    echo '</div>';
    die();
}

// Fetch service health data
$services = getM365ServiceHealth($access_token);
$issues = getM365CurrentIssues($access_token);

// Filter services based on configuration (if configured, otherwise show all)
if (!empty($configured_services)) {
    $filtered_services = array_filter($services, function($service) use ($configured_services) {
        return in_array($service['service'], $configured_services);
    });
} else {
    // Show all services if no filter is configured
    $filtered_services = $services;
}

// Group issues by service
$issues_by_service = [];
foreach ($issues as $issue) {
    if ($issue['isResolved'] === false) {
        $service_id = $issue['service'];
        if (!isset($issues_by_service[$service_id])) {
            $issues_by_service[$service_id] = [];
        }
        $issues_by_service[$service_id][] = $issue;
    }
}

?>

<div class="row">
    <?php if (empty($filtered_services)): ?>
        <div class="col-lg-12">
            <div class="alert alert-warning">
                <i class="fa fa-info-circle"></i> Keine Services konfiguriert. Bitte M365_SERVICES in config.php setzen.
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($filtered_services as $service): ?>
            <?php 
                $status = $service['status'];
                $service_id = $service['service'];
                $panel_class = getStatusPanelClass($status);
                $status_text = getStatusText($status);
                $service_name = getServiceDisplayName($service_id);
                $icon = getStatusIcon($status);
                $has_issues = isset($issues_by_service[$service_id]) && count($issues_by_service[$service_id]) > 0;
            ?>
            <div class="col-lg-1 col-md-2 col-sm-3 col-xs-4">
                <div class="panel <?php echo $panel_class; ?> m365-service-panel">
                    <div class="panel-heading text-center">
                        <i class="fa <?php echo $icon; ?>"></i>
                    </div>
                    <div class="panel-body text-center">
                        <div class="service-name"><?php echo htmlspecialchars($service_name); ?></div>
                        <?php if ($has_issues): ?>
                            <div class="issue-badge">
                                <i class="fa fa-exclamation-circle"></i> <?php echo count($issues_by_service[$service_id]); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if (!empty($issues_by_service)): ?>
<div class="row">
    <div class="col-lg-12">
        <div class="issues-section-header">
            <i class="fa fa-exclamation-circle"></i> Aktuelle Vorfälle und Beeinträchtigungen
        </div>
        <div class="issues-compact-grid">
            <?php foreach ($issues_by_service as $service_id => $service_issues): ?>
                <?php 
                    $service_name = getServiceDisplayName($service_id);
                    foreach ($service_issues as $issue):
                        $issue_class = 'issue-default';
                        if (stripos($issue['classification'], 'Incident') !== false) {
                            $issue_class = 'issue-danger';
                        } elseif (stripos($issue['classification'], 'Advisory') !== false) {
                            $issue_class = 'issue-warning';
                        }
                        
                        $start_date = new DateTime($issue['startDateTime']);
                        $last_update = new DateTime($issue['lastModifiedDateTime']);
                ?>
                <div class="issue-compact-card <?php echo $issue_class; ?>">
                    <div class="issue-compact-header">
                        <span class="issue-service-name"><?php echo htmlspecialchars($service_name); ?></span>
                        <span class="issue-classification"><?php echo htmlspecialchars($issue['classification']); ?></span>
                    </div>
                    <div class="issue-compact-title">
                        <?php echo htmlspecialchars($issue['title']); ?>
                    </div>
                    <div class="issue-compact-meta">
                        <span><i class="fa fa-clock-o"></i> <?php echo $start_date->format('d.m.Y H:i'); ?></span>
                        <span class="issue-status"><?php echo getStatusText($issue['status']); ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
.m365-service-panel {
    margin-bottom: 8px;
    min-height: 85px;
    transition: all 0.2s ease;
    border: 1px solid rgba(255,255,255,0.08) !important;
    display: flex;
    flex-direction: column;
}

.m365-service-panel:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.4);
}

/* Dark panels with accent colors */
.m365-service-panel .panel-heading {
    padding: 5px;
    font-size: 18px;
    background-color: rgba(40, 40, 40, 0.9) !important;
    border-bottom: 1px solid rgba(255,255,255,0.1) !important;
    min-height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.m365-service-panel .panel-body {
    background-color: rgba(30, 30, 30, 0.95) !important;
    padding: 0 !important;
    color: #e0e0e0 !important;
    display: flex;
    flex-direction: column;
    flex: 1;
    min-height: 0;
}

.m365-service-panel .service-name {
    font-size: 9px;
    font-weight: 600;
    margin: 0;
    padding: 4px 3px;
    color: #ffffff !important;
    line-height: 1.1;
    background-color: rgba(30, 30, 30, 0.95) !important;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    flex: 1;
}

.m365-service-panel .status-text {
    font-size: 8px;
    font-weight: 500;
    margin: 2px 0 0 0;
    color: #b0b0b0 !important;
    display: none;
}

.m365-service-panel .issue-badge {
    margin: 0;
    padding: 3px;
    font-size: 8px;
    color: #ff6b6b !important;
    font-weight: bold;
    background-color: rgba(40, 40, 40, 0.95) !important;
    flex-shrink: 0;
}

/* Success - Green accent */
.panel-success.m365-service-panel {
    border-left: 3px solid #2ECC71 !important;
}
.panel-success.m365-service-panel .panel-heading {
    color: #2ECC71 !important;
}

/* Warning - Orange accent */
.panel-warning.m365-service-panel {
    border-left: 3px solid #F1C40F !important;
}
.panel-warning.m365-service-panel .panel-heading {
    color: #F1C40F !important;
}

/* Danger - Red accent */
.panel-danger.m365-service-panel {
    border-left: 3px solid #E74C3C !important;
}
.panel-danger.m365-service-panel .panel-heading {
    color: #E74C3C !important;
}

/* Default - Blue accent */
.panel-default.m365-service-panel {
    border-left: 3px solid #337ab7 !important;
}
.panel-default.m365-service-panel .panel-heading {
    color: #337ab7 !important;
}

/* Issue panels - dark theme */
.m365-issue-panel {
    margin-bottom: 15px;
    background-color: rgba(40, 40, 40, 0.8) !important;
    border: 1px solid rgba(255,255,255,0.1) !important;
}

.m365-issue-panel .panel-heading {
    background-color: rgba(50, 50, 50, 0.9) !important;
    color: #fff !important;
    border-color: rgba(255,255,255,0.1) !important;
}

.m365-issue-panel .panel-body {
    background-color: rgba(30, 30, 30, 0.9) !important;
    color: #e0e0e0 !important;
}

.service-issue-header {
    margin-top: 20px;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid rgba(255,255,255,0.2);
    color: #fff !important;
}

.issue-description {
    font-size: 13px;
    line-height: 1.6;
    max-height: 200px;
    overflow-y: auto;
    color: #d0d0d0 !important;
}

.issue-meta {
    font-size: 12px;
    background-color: rgba(50, 50, 50, 0.6);
    color: #d0d0d0 !important;
    padding: 10px;
    border-radius: 4px;
    border: 1px solid rgba(255,255,255,0.1);
}

.issue-meta p {
    margin-bottom: 8px;
    color: #d0d0d0 !important;
}

.issue-meta strong {
    display: inline-block;
    width: 120px;
    color: #ffffff !important;
}

/* Compact Issues Grid - TV optimized */
.issues-section-header {
    font-size: 16px;
    font-weight: 700;
    color: #fff !important;
    margin-bottom: 15px;
    padding: 10px 0;
    border-bottom: 2px solid rgba(255,255,255,0.2);
}

.issues-section-header i {
    color: #F1C40F;
    margin-right: 8px;
}

.issues-compact-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 10px;
}

.issue-compact-card {
    background-color: rgba(40, 40, 40, 0.95) !important;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 4px;
    padding: 10px;
    transition: all 0.2s ease;
}

.issue-compact-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.4);
}

.issue-danger {
    border-left: 3px solid #E74C3C !important;
}

.issue-warning {
    border-left: 3px solid #F1C40F !important;
}

.issue-default {
    border-left: 3px solid #3498DB !important;
}

.issue-compact-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 6px;
    padding-bottom: 6px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.issue-service-name {
    font-size: 11px;
    font-weight: 700;
    color: #fff !important;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.issue-classification {
    font-size: 9px;
    padding: 2px 6px;
    background-color: rgba(255,255,255,0.1);
    border-radius: 3px;
    color: #fff !important;
    font-weight: 600;
}

.issue-compact-title {
    font-size: 12px;
    font-weight: 600;
    color: #e0e0e0 !important;
    margin-bottom: 6px;
    line-height: 1.3;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.issue-compact-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 10px;
    color: #b0b0b0 !important;
}

.issue-compact-meta i {
    margin-right: 4px;
}

.issue-status {
    padding: 2px 6px;
    background-color: rgba(255,255,255,0.05);
    border-radius: 3px;
    font-weight: 600;
}

/* Badge styling */
.badge-danger {
    background-color: #d9534f;
    color: #fff !important;
}

/* Panel primary styling */
.panel-primary > .panel-heading {
    background-color: rgba(51, 122, 183, 0.2) !important;
    border-color: #337ab7 !important;
    color: #fff !important;
}
</style>
