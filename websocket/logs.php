<?php
/**
 * WebSocket Server Log Viewer
 */
$logFile = __DIR__ . '/logs/websocket.log';
$lines = isset($_GET['lines']) ? (int)$_GET['lines'] : 500;
$autoRefresh = isset($_GET['refresh']) ? (int)$_GET['refresh'] : 0;

// Read log file
$logContent = '';
if (file_exists($logFile)) {
    $fileContent = file($logFile);
    $totalLines = count($fileContent);
    
    // Get last X lines
    $logLines = array_slice($fileContent, -$lines);
    $logContent = implode('', $logLines);
    
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
                            <a href="?clear=1" class="btn btn-danger" onclick="return confirm('Wirklich alle Logs löschen?')"><i class="fas fa-trash"></i> Logs löschen</a>
                        </div>
                    </div>
                    
                    <div class="log-container" id="logContainer">
                        <?php
                        if (isset($_GET['clear'])) {
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
