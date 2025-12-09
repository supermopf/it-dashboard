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
<div class="container-fluid">
    <div class="row" style="margin-top: 5%">
        <nav class="navbar navbar-default navbar-fixed-top">
            <div class="container-fluid">
                <div class="navbar-header">
                    <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar">
                        <span class="sr-only">Toggle navigation</span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                    </button>
                    <a class="navbar-brand" href="#"><i class="fas fa-tachometer-alt"></i> IT-Dashboard</a>
                </div>
                <div id="navbar" class="navbar-collapse collapse">
                    <ul class="nav navbar-nav">
                        <li><a href="./history.php"><i class="fas fa-history"></i> History</a></li>
                        <li><a href="./best.php"><i class="fas fa-star"></i> Best</a></li>
                        <li class="active"><a href="./youtube.php"><i class="fab fa-youtube"></i> YouTube</a></li>
                        <li><a href="./index.php"><i class="fas fa-cog"></i> Adminpanel</a></li>
                        <li><a href="./features.php"><i class="fas fa-lightbulb"></i> Feature Request</a></li>
                        <li><a href="./julianometer.php"><i class="fas fa-chart-line"></i> Julian-O-Meter</a></li>
                        <li><a href="./newtoast.php"><i class="fas fa-bell"></i> Neuer Toast</a></li>
                    </ul>
                    <ul class="nav navbar-nav navbar-right" style="margin-right: 1%;">
                        <li>
                            <p class="navbar-text">
                                <span id="ws-indicator" class="ws-status disconnected"></span>
                                <span id="ws-status-text" style="color: #e74c3c;">Nicht verbunden</span>
                            </p>
                        </li>
                        <li><p class="navbar-btn"><button class="btn btn-default" id="reload">Reload</button></p></li>
                    </ul>
                </div>
            </div>
        </nav>
    </div>
    
    <!-- YouTube Control Panel -->
    <div class="row">
        <div class="col-lg-offset-1 col-lg-10">
            <div class="panel panel-success">
                <div class="panel-heading">
                    <i class="fab fa-youtube"></i> YouTube Steuerung
                </div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-9">
                            <div class="input-group">
                                <input placeholder="Youtube-URL..." type="text" id="yt-message" class="form-control">
                                <span class="input-group-btn">
                                    <button id="yt-btn" class="btn btn-success" type="button"><i class="fas fa-play"></i> Play</button>
                                </span>
                            </div>
                        </div>
                        <div class="col-md-3">
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
    var sliding = false;
    
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
        var msg = {
            message: '!video ' + mymessage
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
        
        var wsUri = DASHBOARD_CONFIG.WEBSOCKET_URL;
        websocket = new WebSocket(wsUri);

        websocket.onopen = function (ev) {
            updateWSStatus(true);
            msg = {
                message: '!reg [YouTube]'
            };
            websocket.send(JSON.stringify(msg));
        };
        
        websocket.onerror = function (ev) {
            updateWSStatus(false);
        };
        
        websocket.onclose = function (ev) {
            updateWSStatus(false);
        };
        
        $('.repeat').click(function () {
            yturl = $(this).attr('yturl');
            var msg = {
                message: '!video ' + yturl.replace(/(\r\n\t|\n|\r\t)/gm,"")
            };
            websocket.send(JSON.stringify(msg));
        });

        websocket.onmessage = function (ev) {
            var msg = JSON.parse(ev.data);
            var type = msg.type;
            var message = msg.message;

            if (type === 'auth') {
                msg = {
                    message: '!reg [YouTube]'
                };
                websocket.send(JSON.stringify(msg));
            }
            if(type === 'update'){
                if (!sliding) {
                    $('#volume').slider('setValue', msg.YTvolume * 100)
                }
            }
        };
    });
</script>
</body>
</html>
