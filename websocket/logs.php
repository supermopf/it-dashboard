<?php
/**
 * WebSocket Server Log Viewer
 */
require(getenv('DASHBOARD_CONFIG') ?: __DIR__ . '/../config.php');
require_once(__DIR__ . '/../lib/security.php');
require_admin_access();
$logFile = __DIR__ . '/logs/websocket.log';
$lines = isset($_GET['lines']) ? max(1, min(5000, (int)$_GET['lines'])) : 500;
$autoRefresh = isset($_GET['refresh']) ? (int)$_GET['refresh'] : 0;

// Read log file
$logContent = '';
if (file_exists($logFile)) {
    // Nur das Dateiende lesen statt der kompletten Datei (bis 10 MB) pro Aufruf (AUDIT X17).
    $fileSize = filesize($logFile);
    $fh = fopen($logFile, 'rb');
    $chunk = (int)min($fileSize, max(65536, $lines * 400));
    fseek($fh, -$chunk, SEEK_END);
    $tail = (string)fread($fh, $chunk);
    fclose($fh);
    $fileContent = explode("\n", rtrim($tail, "\n"));
    if ($chunk < $fileSize) {
        array_shift($fileContent); // erste, evtl. abgeschnittene Zeile
    }
    $totalLines = $chunk < $fileSize ? count($fileContent) . '+' : count($fileContent);
    $logLines = array_slice($fileContent, -$lines);
    $logContent = implode("\n", $logLines);
    
    $fileSize = filesize($logFile);
    $fileSizeMB = round($fileSize / 1024 / 1024, 2);
} else {
    $logContent = "Log-Datei nicht gefunden.";
    $totalLines = 0;
    $fileSizeMB = 0;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>IT Dashboard - WebSocket Logs</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href='https://fonts.googleapis.com/css?family=Roboto+Condensed:300,400' rel='stylesheet' type='text/css'>
    <link href='https://fonts.googleapis.com/css?family=Lato:300,400,700,900' rel='stylesheet' type='text/css'>
    <link rel="stylesheet" type="text/css" href="../monitor/lib/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css">
    <link rel="stylesheet" type="text/css" href="./css/style.css">
    <?php if ($autoRefresh > 0): ?>
    <meta http-equiv="refresh" content="<?= $autoRefresh ?>">
    <?php endif; ?>
    <style>
        .log-container {
            background: #1e1e1e;
            color: #d4d4d4;
            font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
            font-size: 13px;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
            max-height: calc(100vh - 300px);
            overflow-y: auto;
        }
        .log-line {
            margin: 2px 0;
            line-height: 1.5;
        }
        .log-debug {
            color: #4fc3f7;
        }
        .log-error {
            color: #f44336;
        }
        .log-warning {
            color: #ff9800;
        }
        .log-normal {
            color: #4caf50;
        }
        .controls {
            margin: 20px 0;
        }
        .controls .btn {
            margin-right: 10px;
        }
        .stats {
            margin: 15px 0;
            padding: 10px;
            background: #f5f5f5;
            border-radius: 4px;
        }
        .stats span {
            margin-right: 20px;
        }
    </style>
</head>
<body>

<?php 
$active_page = 'logs';
include('navbar.php'); 
?>

<div class="container-fluid">
    <div class="row" style="margin-top: 80px;">
                    </ul>
                </div>
            </div>
        </nav>
    </div>
    
    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <i class="fas fa-file-alt"></i> WebSocket Server Logs
                </div>
                <div class="panel-body">
                    <div class="stats">
                        <span><strong>Log-Datei:</strong> <?= basename($logFile) ?></span>
                        <span><strong>Größe:</strong> <?= $fileSizeMB ?> MB</span>
                        <span><strong>Zeilen gesamt:</strong> <?= number_format($totalLines) ?></span>
                        <span><strong>Angezeigt:</strong> <?= min($lines, $totalLines) ?> Zeilen</span>
                        <?php if ($autoRefresh > 0): ?>
                        <span class="label label-success">Auto-Refresh: <?= $autoRefresh ?>s</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="controls">
                        <div class="btn-group">
                            <a href="?lines=100" class="btn btn-default <?= $lines == 100 ? 'active' : '' ?>">100 Zeilen</a>
                            <a href="?lines=500" class="btn btn-default <?= $lines == 500 ? 'active' : '' ?>">500 Zeilen</a>
                            <a href="?lines=1000" class="btn btn-default <?= $lines == 1000 ? 'active' : '' ?>">1000 Zeilen</a>
                            <a href="?lines=5000" class="btn btn-default <?= $lines == 5000 ? 'active' : '' ?>">5000 Zeilen</a>
                        </div>
                        
                        <div class="btn-group" style="margin-left: 20px;">
                            <a href="?lines=<?= $lines ?>" class="btn btn-default <?= $autoRefresh == 0 ? 'active' : '' ?>">Kein Refresh</a>
                            <a href="?lines=<?= $lines ?>&refresh=2" class="btn btn-default <?= $autoRefresh == 2 ? 'active' : '' ?>">2s</a>
                            <a href="?lines=<?= $lines ?>&refresh=5" class="btn btn-default <?= $autoRefresh == 5 ? 'active' : '' ?>">5s</a>
                            <a href="?lines=<?= $lines ?>&refresh=10" class="btn btn-default <?= $autoRefresh == 10 ? 'active' : '' ?>">10s</a>
                        </div>
                        
                        <div class="btn-group pull-right">
                            <button class="btn btn-primary" onclick="location.reload()"><i class="fas fa-sync"></i> Aktualisieren</button>
                            <button class="btn btn-success" onclick="scrollToBottom()"><i class="fas fa-arrow-down"></i> Nach unten</button>
                            <form method="post" style="display:inline" onsubmit="return confirm('Wirklich alle Logs löschen?')"><input type="hidden" name="clear" value="1"><button type="submit" class="btn btn-danger"><i class="fas fa-trash"></i> Logs löschen</button></form>
                        </div>
                    </div>
                    
                    <div class="log-container" id="logContainer">
                        <?php
                        // Löschen nur per POST von derselben Seite (kein CSRF per Link/GET mehr)
                        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear'])) {
                            file_put_contents($logFile, '');
                            echo '<div class="alert alert-success">Logs gelöscht!</div>';
                            echo '<meta http-equiv="refresh" content="1;url=logs.php">';
                        } else {
                            // Colorize log output
                            $lines = explode("\n", $logContent);
                            foreach ($lines as $line) {
                                if (empty(trim($line))) continue;
                                
                                $class = 'log-normal';
                                if (strpos($line, '[DEBUG]') !== false) {
                                    $class = 'log-debug';
                                } elseif (strpos($line, 'Error') !== false || strpos($line, 'Failed') !== false) {
                                    $class = 'log-error';
                                } elseif (strpos($line, 'Warning') !== false) {
                                    $class = 'log-warning';
                                }
                                
                                echo '<div class="log-line ' . $class . '">' . htmlspecialchars($line) . '</div>';
                            }
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="../monitor/lib/js/jquery.min.js"></script>
<script src="../monitor/lib/js/bootstrap.min.js"></script>
<script>
    function scrollToBottom() {
        var container = document.getElementById('logContainer');
        container.scrollTop = container.scrollHeight;
    }
    
    // Auto-scroll to bottom on page load
    $(document).ready(function() {
        scrollToBottom();
    });
</script>
</body>
</html>
