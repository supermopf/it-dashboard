<?php
require_once('db_helper.php');
$db = new ToastDB();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 50; // Load only 50 at a time instead of all
$offset = ($page - 1) * $perPage;

$youtubeHistory = $db->getYouTubeHistory($perPage, $offset);
$totalCount = $db->getYouTubeCount();
$totalPages = ceil($totalCount / $perPage);
?>
<!DOCTYPE html>
<html xmlns="">
<head>
    <title>IT Dashboard - YouTube</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href='https://fonts.googleapis.com/css?family=Roboto+Condensed:300,400' rel='stylesheet' type='text/css'>
    <link href='https://fonts.googleapis.com/css?family=Lato:300,400,700,900' rel='stylesheet' type='text/css'>
    <link rel="stylesheet" type="text/css" href="../monitor/lib/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="../monitor/lib/css/bootstrap-slider.min.css">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css">
    <link rel="stylesheet" type="text/css" href="./css/style.css">
</head>
<body>

<?php 
$active_page = 'youtube';
$show_ws_status = true;
$show_reload_btn = true;
include('navbar.php'); 
?>

<div class="container-fluid">
    
    <!-- YouTube Control Panel -->
    <div class="row" style="margin-top: 80px;">
        <div class="col-lg-offset-1 col-lg-10">
            <div class="panel panel-success">
                <div class="panel-heading">
                    <i class="fab fa-youtube"></i> YouTube Steuerung
                </div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-7">
                            <div class="input-group">
                                <input placeholder="Youtube-URL..." type="text" id="yt-message" class="form-control">
                                <span class="input-group-btn">
                                    <button id="yt-btn" class="btn btn-success" type="button"><i class="fas fa-play"></i> Play</button>
                                </span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select id="dashboard-select" class="form-control">
                                <option value="Alle">Alle Dashboards</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <div class="volume-control">
                                <i class="fas fa-volume-up"></i>
                                <input id="volume" data-slider-id='volumeSlider' type="text" data-slider-min="1" data-slider-max="100" data-slider-step="1" data-slider-value="14"/>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- YouTube History Table -->
    <div class="row">
        <div class="col-lg-offset-1 col-lg-10">
            <div class="alert alert-info">
                Zeige <?= count($youtubeHistory) ?> von <?= $totalCount ?> Einträgen (Seite <?= $page ?> von <?= $totalPages ?>)
            </div>
            
            <!-- Pagination Top -->
            <?php if ($totalPages > 1): ?>
            <nav>
                <ul class="pagination">
                    <?php if ($page > 1): ?>
                    <li><a href="?page=<?= $page-1 ?>">&laquo; Zurück</a></li>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page-5); $i <= min($totalPages, $page+5); $i++): ?>
                    <li class="<?= $i == $page ? 'active' : '' ?>">
                        <a href="?page=<?= $i ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                    <li><a href="?page=<?= $page+1 ?>">Weiter &raquo;</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
            
            <div class="panel panel-info">
                <div class="panel-heading">
                    <i class="fab fa-youtube"></i> YouTube History (<?= count($youtubeHistory) ?> Videos)
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover" style="margin-bottom: 0; table-layout: fixed;">
                        <thead>
                            <tr>
                                <th style="width: 120px;">Thumbnail</th>
                                <th style="word-wrap: break-word; overflow-wrap: break-word;">URL</th>
                                <th style="width: 120px;">Aktion</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($youtubeHistory as $video): ?>
                            <tr>
                                <td>
                                    <?php if ($video->video_id): ?>
                                        <a href="<?= htmlspecialchars($video->url) ?>" target="_blank">
                                            <img class='img-responsive' src='https://i.ytimg.com/vi/<?= htmlspecialchars($video->video_id) ?>/hqdefault.jpg' alt="Thumbnail" />
                                        </a>
                                    <?php else: ?>
                                        <a href="<?= htmlspecialchars($video->url) ?>" target="_blank">
                                            <div style="width:120px;height:90px;background:#333;display:flex;align-items:center;justify-content:center;color:#999;font-size:12px;text-align:center;">No Preview</div>
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td style='word-wrap: break-word; overflow-wrap: break-word;'>
                                    <?= htmlspecialchars($video->url) ?>
                                </td>
                                <td>
                                    <button yturl='<?= htmlspecialchars($video->url) ?>' class="btn btn-primary repeat">Wiederholen</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Pagination Bottom -->
            <?php if ($totalPages > 1): ?>
            <nav>
                <ul class="pagination">
                    <?php if ($page > 1): ?>
                    <li><a href="?page=<?= $page-1 ?>">&laquo; Zurück</a></li>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page-5); $i <= min($totalPages, $page+5); $i++): ?>
                    <li class="<?= $i == $page ? 'active' : '' ?>">
                        <a href="?page=<?= $i ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                    <li><a href="?page=<?= $page+1 ?>">Weiter &raquo;</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="../monitor/lib/js/jquery.min.js"></script>
<script src="../monitor/lib/js/bootstrap-slider.min.js"></script>
<script type="text/javascript" src="../config.js.php"></script>
<script>
    var websocket;
    var sliding = false;
    var reconnectAttempts = 0;
    var maxReconnectAttempts = 5;
    var reconnectDelay = 1000;
    var reconnectTimer = null;
    var intentionalClose = false;
    var isConnecting = false;
    
    function loadDashboards() {
        // Request dashboard list via WebSocket
        if (websocket && websocket.readyState === WebSocket.OPEN) {
            var msg = { message: '!dashboardlist' };
            websocket.send(JSON.stringify(msg));
        }
    }
    
    function updateWSStatus(connected) {
        var indicator = document.getElementById('ws-indicator');
        var statusText = document.getElementById('ws-status-text');
        if (connected) {
            indicator.className = 'ws-status connected';
            statusText.textContent = 'Verbunden';
            statusText.style.color = '#2ecc71';
        } else {
            indicator.className = 'ws-status disconnected';
            statusText.textContent = 'Nicht verbunden';
            statusText.style.color = '#e74c3c';
        }
    }
    
    function connectWebSocket() {
        // Prevent parallel connection attempts
        if (isConnecting) {
            console.log("Connection bereits im Gange, überspringe...");
            return;
        }
        
        isConnecting = true;
        
        // Clear any pending reconnect timer
        if (reconnectTimer) {
            clearTimeout(reconnectTimer);
            reconnectTimer = null;
        }
        
        // Close existing websocket if it exists
        if (websocket) {
            try {
                websocket.onclose = null;
                websocket.onerror = null;
                websocket.onmessage = null;
                websocket.onopen = null;
                websocket.close();
            } catch (e) {
                // Ignore close errors
            }
        }
        
        var wsUri = DASHBOARD_CONFIG.WEBSOCKET_URL;
        websocket = new WebSocket(wsUri);

        websocket.onopen = function (ev) {
            updateWSStatus(true);
            reconnectAttempts = 0;
            reconnectDelay = 1000;
            isConnecting = false;
            
            // Register and request dashboard list
            var msg = {
                message: '!reg [YouTube]'
            };
            websocket.send(JSON.stringify(msg));
            
            msg = {
                message: '!dashboardlist'
            };
            websocket.send(JSON.stringify(msg));
        };
        
        websocket.onerror = function (ev) {
            updateWSStatus(false);
            isConnecting = false;
        };
        
        websocket.onclose = function (ev) {
            updateWSStatus(false);
            isConnecting = false;
            
            if (intentionalClose) {
                return;
            }
            
            if (reconnectAttempts < maxReconnectAttempts) {
                reconnectAttempts++;
                var delay = reconnectDelay * Math.pow(2, reconnectAttempts - 1);
                
                console.log("Reconnecting in " + (delay / 1000) + "s... (Attempt " + reconnectAttempts + "/" + maxReconnectAttempts + ")");
                
                reconnectTimer = setTimeout(function() {
                    console.log("Attempting to reconnect...");
                    connectWebSocket();
                }, delay);
            } else {
                console.log("Max reconnect attempts reached.");
            }
        };

        websocket.onmessage = function (ev) {
            var msg = JSON.parse(ev.data);
            var type = msg.type;
            var message = msg.message;

            if (type === 'auth') {
                // Re-register and request dashboard list
                var authMsg = {
                    message: '!reg [YouTube]'
                };
                websocket.send(JSON.stringify(authMsg));
                
                var listMsg = {
                    message: '!dashboardlist'
                };
                websocket.send(JSON.stringify(listMsg));
            }
            if(type === 'update'){
                if (!sliding) {
                    $('#volume').slider('setValue', msg.YTvolume * 100)
                }
            }
            if (type === 'dashboardlist') {
                // Update dashboard dropdown
                var select = $('#dashboard-select');
                // Clear all except "Alle"
                select.find('option:not([value="Alle"])').remove();
                
                // Add each dashboard
                if (msg.dashboards && msg.dashboards.length > 0) {
                    msg.dashboards.forEach(function(dashboard) {
                        select.append($('<option></option>').attr('value', dashboard).text(dashboard));
                    });
                }
                
                // Restore last selection from localStorage
                var lastTarget = localStorage.getItem('lastDashboardTarget');
                if (lastTarget) {
                    select.val(lastTarget);
                }
            }
        };
    }
    
    // Clean disconnect on page unload
    window.addEventListener('beforeunload', function() {
        intentionalClose = true;
        if (websocket) {
            websocket.close();
        }
    });
    
    $('#reload').click(function () {
        $.ajax({
            url: 'api.php',
            type: 'post',
            data: {
                ToastBody: "<script>location.reload()<\/script>",
                ToastHistory: "false"
            }
        });
    });
    
    $('#yt-btn').click(function () {
        var mymessage = $('#yt-message').val();
        $('#yt-message').val("");
        var target = $('#dashboard-select').val();
        
        // Save last selection to localStorage
        localStorage.setItem('lastDashboardTarget', target);
        
        var msg = {
            message: '!video ' + mymessage + '|' + target
        };
        websocket.send(JSON.stringify(msg));
    });

    $(document).ready(function () {
        $("#volume").slider();
        $('#volume').on("click touchstart slideStop", function (sliderValue) {
            sliding = false;
            var volume = sliderValue.value / 100;
            var msg = {
                message: '!var YTvolume ' + volume
            };
            websocket.send(JSON.stringify(msg));
        });

        $('#volume').on("slideStart", function (sliderValue) {
            sliding = true;
        });
        
        // Initialize WebSocket connection
        connectWebSocket();
        
        $('.repeat').click(function () {
            yturl = $(this).attr('yturl');
            var target = $('#dashboard-select').val();
            
            // Save last selection to localStorage
            localStorage.setItem('lastDashboardTarget', target);
            
            var msg = {
                message: '!video ' + yturl.replace(/(\r\n\t|\n|\r\t)/gm,"") + '|' + target
            };
            websocket.send(JSON.stringify(msg));
        });
    });
</script>
</body>
</html>
