<?php
/**
 * Microsoft 365 Service Health Status Dashboard
 * User: victor.lange
 * Date: 08.12.2025
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

<div class="m365-services">
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
/* M365: Lesbar aus mehreren Metern – Größen aus den Design-Tokens (Fallbacks für die Legacy-Oberfläche) */
.m365-services {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: var(--space-3, 12px);
    margin-bottom: var(--space-5, 24px);
}
.m365-services .m365-service-panel {
    display: flex;
    align-items: center;
    gap: var(--space-3, 12px);
    min-height: 5.5rem;
    margin: 0;
    padding: var(--space-3, 12px) var(--space-4, 16px);
    background: var(--color-surface, #12171d);
    border: 1px solid var(--color-border, rgba(255,255,255,0.08));
    border-left-width: 4px;
    border-radius: var(--radius-md, 12px);
    overflow: hidden;
}
.m365-service-panel .panel-heading {
    flex: none;
    padding: 0;
    border: 0;
    background: none;
    font-size: var(--fs-xl, 30px);
    line-height: 1;
}
.m365-service-panel .panel-heading .icon { width: 1em; height: 1em; }
.m365-service-panel .panel-body {
    flex: 1;
    min-width: 0;
    padding: 0;
    text-align: left;
}
.m365-service-panel .service-name {
    font-size: var(--fs-md, 17px);
    font-weight: var(--fw-semibold, 600);
    line-height: var(--lh-tight, 1.15);
    color: var(--color-text, #e9edf2);
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}
.m365-service-panel .issue-badge {
    margin-top: var(--space-1, 4px);
    font-size: var(--fs-sm, 15px);
    font-weight: var(--fw-semibold, 600);
    color: var(--color-danger, #f0584f);
}
.panel-success.m365-service-panel { border-left-color: var(--color-success, #3fb27f); }
.panel-success.m365-service-panel .panel-heading { color: var(--color-success, #3fb27f); }
.panel-warning.m365-service-panel { border-left-color: var(--color-warning, #e3a535); }
.panel-warning.m365-service-panel .panel-heading { color: var(--color-warning, #e3a535); }
.panel-danger.m365-service-panel { border-left-color: var(--color-danger, #f0584f); }
.panel-danger.m365-service-panel .panel-heading { color: var(--color-danger, #f0584f); }
.panel-default.m365-service-panel { border-left-color: var(--color-info, #5fb3f6); }
.panel-default.m365-service-panel .panel-heading { color: var(--color-info, #5fb3f6); }

.issues-section-header {
    margin-bottom: var(--space-3, 12px);
    font-size: var(--fs-lg, 22px);
    font-weight: var(--fw-semibold, 600);
    color: var(--color-text, #e9edf2);
}
.issues-section-header .icon,
.issues-section-header i { color: var(--color-warning, #e3a535); margin-right: var(--space-2, 8px); }
.issues-compact-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: var(--space-3, 12px);
}
.issues-compact-grid .issue-compact-card {
    padding: var(--space-3, 12px) var(--space-4, 16px);
    background: var(--color-surface, #12171d);
    border: 1px solid var(--color-border, rgba(255,255,255,0.08));
    border-left-width: 4px;
    border-radius: var(--radius-md, 12px);
}
.issue-danger { border-left-color: var(--color-danger, #f0584f); }
.issue-warning { border-left-color: var(--color-warning, #e3a535); }
.issue-default { border-left-color: var(--color-info, #5fb3f6); }
.issue-compact-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: var(--space-3, 12px);
    margin-bottom: var(--space-2, 8px);
}
.issue-service-name {
    font-size: var(--fs-sm, 15px);
    font-weight: var(--fw-semibold, 600);
    color: var(--color-text-secondary, #a3acb9);
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.issue-classification {
    flex: none;
    padding: 0 var(--space-2, 8px);
    border-radius: var(--radius-pill, 999px);
    background: rgba(255,255,255,0.08);
    font-size: var(--fs-xs, 13px);
    font-weight: var(--fw-semibold, 600);
    line-height: 1.6;
}
.issue-compact-title {
    margin-bottom: var(--space-2, 8px);
    font-size: var(--fs-md, 17px);
    font-weight: var(--fw-medium, 500);
    line-height: var(--lh-normal, 1.4);
    color: var(--color-text, #e9edf2);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.issue-compact-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: var(--fs-sm, 15px);
    color: var(--color-text-secondary, #a3acb9);
    font-variant-numeric: tabular-nums;
}
.issue-compact-meta .icon,
.issue-compact-meta i { margin-right: var(--space-1, 4px); }
.issue-status {
    padding: 0 var(--space-2, 8px);
    border-radius: var(--radius-pill, 999px);
    background: rgba(255,255,255,0.06);
    font-weight: var(--fw-semibold, 600);
}
</style>
