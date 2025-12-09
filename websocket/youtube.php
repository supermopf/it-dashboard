<!DOCTYPE html>
<html xmlns="">
<head>
    <title>IT Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Fonts -->
    <link href='https://fonts.googleapis.com/css?family=Roboto+Condensed:300,400' rel='stylesheet' type='text/css'>
    <link href='https://fonts.googleapis.com/css?family=Lato:300,400,700,900' rel='stylesheet' type='text/css'>
    <!-- CSS Libs -->
    <link rel="stylesheet" type="text/css" href="../monitor/lib/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="../monitor/lib/css/animate.min.css">
    <link rel="stylesheet" type="text/css" href="../monitor/lib/css/bootstrap-switch.min.css">
    <link rel="stylesheet" type="text/css" href="../monitor/lib/css/checkbox3.min.css">
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
                    <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
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
                        <li>
                            <p class="navbar-btn">
                                <button class="btn btn-default" id="reload">Reload</button>
                            </p>
                        </li>
                    </ul>
                </div><!--/.nav-collapse -->
            </div><!--/.container-fluid -->
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
            <div class="panel panel-info">
                <div class="panel-heading">
                    <i class="fab fa-youtube"></i> YouTube History
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
                <?php
                $history = array_unique(array_reverse(file('C:/scripts/IT-Dashboard/ytlog.txt')));
                foreach ($history as $line) {
                    // Extract video ID from different YouTube URL formats
                    $videoID = null;
                    
                    // 1. Check for YouTube Shorts format: https://www.youtube.com/shorts/VIDEO_ID
                    if (preg_match('/youtube\.com\/shorts\/([a-zA-Z0-9_-]{11})/i', $line, $matches)) {
                        $videoID = $matches[1];
                    }
                    // 2. Check for standard format (watch?v=VIDEO_ID)
                    else {
                        parse_str(parse_url($line, PHP_URL_QUERY), $yturl);
                        if (isset($yturl['v'])) {
                            // Remove any trailing underscores or whitespace from video ID
                            $videoID = trim($yturl['v'], "_ \t\n\r\0\x0B");
                        }
                        // 3. Check for short URL format youtu.be/VIDEO_ID
                        else {
                            $path = parse_url($line, PHP_URL_PATH);
                            $potential_id = trim($path, '/');
                            if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $potential_id)) {
                                $videoID = $potential_id;
                            }
                        }
                    }
                    
                    echo "<tr>";
                    echo "<td>";
                    if ($videoID) {
                        echo "<a href='" . trim($line) . "'><img class='img-responsive' src='https://i.ytimg.com/vi/" . $videoID . "/hqdefault.jpg' /></a>";
                    } else {
                        echo "<a href='" . trim($line) . "'><img class='img-responsive' src='https://via.placeholder.com/120x90?text=No+Preview' /></a>";
                    }
                    echo "</td>";
                    echo "<td style='word-wrap: break-word; overflow-wrap: break-word;'>";
                    echo trim($line);
                    echo "</td>";
                    echo "<td>";
                        echo "<div class=\"btn-group\"><button yturl='".trim($line)."' class=\"btn btn-primary repeat\">Wiederholen</button></div>";
                    echo "</td>";
                    echo "</tr>";
                }
                ?>
                </tbody>
            </table>
        </div>
            </div>
        </div>
    </div>
</div>
</body>
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
    
    function clean(obj) {
        var propNames = Object.getOwnPropertyNames(obj);
        for (var i = 0; i < propNames.length; i++) {
            var propName = propNames[i];
            if (obj[propName] === null || obj[propName] === undefined || obj[propName] === "") {
                delete obj[propName];
            }
        }
    }
    $('#reload').click(function () {
        var id = $(this).attr('id');
        var json = {
            ToastSubject: ($('#ToastSubject' + id).html()),
            ToastBody: ("\<script\>location.reload\(\)\<\/script\>"),
            ToastHistory: ("false")
        };
        clean(json);
        $.ajax({
            url: 'api.php',
            type: 'post',
            contentType: 'application/x-www-form-urlencoded',
            success: function (data) {
                //nothing
                console.log(JSON.stringify(json));
            },
            data: json
        });
    });
    $('#yt-btn').click(function () { //use clicks message send button
        var mymessage = $('#yt-message').val(); //get message text
        $('#yt-message').val("");
        //prepare json data
        var msg = {
            message: '!video ' + mymessage
        };
        //convert and send data to server
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
        //create a new WebSocket object.
        var wsUri = DASHBOARD_CONFIG.WEBSOCKET_URL;
        websocket = new WebSocket(wsUri);

        websocket.onopen = function (ev) { // connection is open
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


        //#### Message received from server?
        websocket.onmessage = function (ev) {
            var msg = JSON.parse(ev.data); //PHP sends Json data
            var type = msg.type; //message type
            var message = msg.message; //message text

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