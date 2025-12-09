<!DOCTYPE html>
<html>

<head>
    <title>IT Dashboard - Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Fonts -->
    <link href='https://fonts.googleapis.com/css?family=Roboto+Condensed:300,400' rel='stylesheet' type='text/css'>
    <link href='https://fonts.googleapis.com/css?family=Lato:300,400,700,900' rel='stylesheet' type='text/css'>
    <!-- CSS Libs -->
    <link rel="stylesheet" type="text/css" href="../monitor/lib/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="../monitor/lib/css/animate.min.css">
    <link rel="stylesheet" type="text/css" href="../monitor/lib/css/bootstrap-switch.min.css">
    <link rel="stylesheet" type="text/css" href="../monitor/lib/css/checkbox3.min.css">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css">
    <style>
        body {
            background: #1a1a1a;
            color: #e0e0e0;
            font-family: 'Lato', sans-serif;
        }
        .navbar-default {
            background: #2c3e50;
            border-color: #34495e;
        }
        .navbar-default .navbar-brand,
        .navbar-default .navbar-nav > li > a {
            color: #ecf0f1;
        }
        .navbar-default .navbar-nav > li > a:hover,
        .navbar-default .navbar-nav > li > a:focus {
            background: #34495e;
            color: #fff;
        }
        .navbar-default .navbar-nav > .active > a,
        .navbar-default .navbar-nav > .active > a:hover,
        .navbar-default .navbar-nav > .active > a:focus {
            background: #34495e;
            color: #fff;
        }
        .panel {
            background: #2c2c2c;
            border-color: #3c3c3c;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        }
        .panel-heading {
            background: #34495e !important;
            color: #ecf0f1 !important;
            border-color: #34495e !important;
            font-weight: 600;
        }
        .panel-body {
            background: #2c2c2c;
        }
        .panel-primary > .panel-heading {
            background: #3498db !important;
            border-color: #3498db !important;
        }
        .panel-success > .panel-heading {
            background: #27ae60 !important;
            border-color: #27ae60 !important;
        }
        .panel-warning > .panel-heading {
            background: #f39c12 !important;
            border-color: #f39c12 !important;
        }
        .panel-info > .panel-heading {
            background: #16a085 !important;
            border-color: #16a085 !important;
        }
        .row {
            margin-top: 15px;
        }
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 4px;
            font-weight: 600;
            margin: 4px 0;
            min-width: 80px;
            text-align: center;
        }
        .status-badge.active {
            background: #27ae60;
            color: white;
        }
        .status-badge.inactive {
            background: #7f8c8d;
            color: white;
        }
        .status-badge.page {
            background: #3498db;
            color: white;
        }
        .status-badge.volume {
            background: #9b59b6;
            color: white;
        }
        #console {
            background: #1a1a1a;
            color: #2ecc71;
            border: 1px solid #3c3c3c;
            border-radius: 4px;
            padding: 10px;
            max-height: 400px;
            overflow-y: auto;
            font-size: 12px;
        }
        .table {
            color: #e0e0e0;
        }
        .table > thead > tr > th {
            border-color: #3c3c3c;
            background: #34495e;
            color: #ecf0f1;
        }
        .table > tbody > tr > td {
            border-color: #3c3c3c;
            background: #2c2c2c;
            color: #e0e0e0;
        }
        .table-striped > tbody > tr:nth-of-type(odd) {
            background-color: #252525;
        }
        .table-hover > tbody > tr:hover {
            background-color: #34495e;
        }
        .form-control {
            background: #1a1a1a;
            border-color: #3c3c3c;
            color: #e0e0e0;
        }
        .form-control:focus {
            background: #252525;
            border-color: #3498db;
            color: #e0e0e0;
        }
        .btn-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
            gap: 8px;
            margin-bottom: 15px;
        }
        .btn-grid .btn {
            margin: 0;
        }
        .ws-status {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 5px;
        }
        .ws-status.connected {
            background: #2ecc71;
            box-shadow: 0 0 8px #2ecc71;
        }
        .ws-status.disconnected {
            background: #e74c3c;
        }
        .control-section {
            margin-bottom: 20px;
            padding: 15px;
            background: #252525;
            border-radius: 4px;
            border: 1px solid #3c3c3c;
        }
        .control-section h4 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #3498db;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .volume-control {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .volume-control input[type="range"] {
            flex: 1;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
        }
        .info-item {
            padding: 8px 12px;
            background: #1a1a1a;
            border-radius: 4px;
            border: 1px solid #3c3c3c;
        }
        .info-item strong {
            color: #3498db;
            display: block;
            font-size: 11px;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        /* Toggle Switch Styling */
        .toggle-container {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }
        .toggle-container > label:first-child {
            margin: 0;
            font-weight: 600;
            min-width: 100px;
            color: #ecf0f1;
            cursor: default;
        }
        .switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 30px;
            flex-shrink: 0;
        }
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #4a4a4a;
            transition: .3s;
            border-radius: 30px;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.3);
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 24px;
            width: 24px;
            left: 3px;
            bottom: 3px;
            background-color: #e0e0e0;
            transition: .3s;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        input:checked + .slider {
            background-color: #27ae60;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.2);
        }
        input:focus + .slider {
            box-shadow: 0 0 4px #3498db;
        }
        input:checked + .slider:before {
            transform: translateX(30px);
            background-color: #ffffff;
        }
        .toggle-status {
            font-weight: 600;
            min-width: 40px;
            color: #7f8c8d;
            font-size: 13px;
        }
        .toggle-container:has(input:checked) .toggle-status {
            color: #27ae60;
        }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row" style="margin-top: 0">
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
                        <li><a href="./youtube.php"><i class="fab fa-youtube"></i> YouTube</a></li>
                        <li class="active"><a href="./index.php"><i class="fas fa-cog"></i> Adminpanel</a></li>
                        <li><a href="./features.php"><i class="fas fa-lightbulb"></i> Feature Request</a></li>
						<li><a href="./julianometer.php"><i class="fas fa-chart-line"></i> Julian-O-Meter</a></li>
                        <li><a href="./newtoast.php"><i class="fas fa-bell"></i> Neuer Toast</a></li>
                    </ul>
                    <ul class="nav navbar-nav navbar-right" style="margin-right: 1%;">
                        <li>
                            <p class="navbar-text">
                                <span class="ws-status disconnected" id="ws-indicator"></span>
                                <span id="ws-status-text">Nicht verbunden</span>
                            </p>
                        </li>
                        <li>
                            <p class="navbar-btn">
                                <button class="btn btn-warning" id="reload"><i class="fas fa-sync-alt"></i> Reload All</button>
                            </p>
                        </li>
                    </ul>
                </div><!--/.nav-collapse -->
            </div><!--/.container-fluid -->
        </nav>
    </div>
    
    <!-- Main Controls -->
    <div class="row" style="margin-top: 80px;">
        <div class="col-lg-8">
            <div class="panel panel-info">
                <div class="panel-heading">
                    <i class="fas fa-sliders-h"></i> Dashboard Controls
                </div>
                <div class="panel-body">
                    <!-- Mode Controls -->
                    <div class="control-section">
                        <h4><i class="fas fa-toggle-on"></i> Modi</h4>
                        <div class="toggle-container">
                            <label><i class="fas fa-laugh"></i> FUN Mode</label>
                            <label class="switch">
                                <input type="checkbox" id="fun-toggle">
                                <span class="slider"></span>
                            </label>
                            <span class="toggle-status" id="fun-status">Off</span>
                        </div>
                        <div class="toggle-container">
                            <label><i class="fas fa-music"></i> Radio</label>
                            <label class="switch">
                                <input type="checkbox" id="radio-toggle">
                                <span class="slider"></span>
                            </label>
                            <span class="toggle-status" id="radio-status">Off</span>
                        </div>
                        <div class="toggle-container">
                            <label><i class="fas fa-snowflake"></i> Snow</label>
                            <label class="switch">
                                <input type="checkbox" id="snow-toggle">
                                <span class="slider"></span>
                            </label>
                            <span class="toggle-status" id="snow-status">Off</span>
                        </div>
                        <div class="toggle-container">
                            <label><i class="fas fa-circle-notch"></i> Wheel</label>
                            <label class="switch">
                                <input type="checkbox" id="wheel-toggle">
                                <span class="slider"></span>
                            </label>
                            <span class="toggle-status" id="wheel-status">Off</span>
                        </div>
                    </div>
                    
                    <!-- Wheel Controls -->
                    <div class="control-section" id="wheel-controls" style="display: none;">
                        <h4><i class="fas fa-circle-notch"></i> Glücksrad</h4>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <button class="btn btn-warning btn-lg" id="spin-btn" style="flex: 1;">
                                <i class="fas fa-sync-alt fa-spin" style="display: none;" id="spin-icon"></i>
                                <i class="fas fa-dice" id="dice-icon"></i>
                                <span style="margin-left: 8px;">Glücksrad drehen</span>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Page Controls -->
                    <div class="control-section">
                        <h4><i class="fas fa-file"></i> Seiten Navigation</h4>
                        <div class="btn-grid">
                            <button class="btn btn-primary btn-sm" onclick="sendCommand('!page 1')">Seite 1</button>
                            <button class="btn btn-primary btn-sm" onclick="sendCommand('!page 2')">Seite 2</button>
                            <button class="btn btn-primary btn-sm" onclick="sendCommand('!page 3')">Seite 3</button>
                            <button class="btn btn-primary btn-sm" onclick="sendCommand('!page 4')">Seite 4</button>
                            <button class="btn btn-primary btn-sm" onclick="sendCommand('!page 5')">Seite 5</button>
                            <button class="btn btn-primary btn-sm" onclick="sendCommand('!page 6')">Seite 6</button>
                            <button class="btn btn-primary btn-sm" onclick="sendCommand('!page 7')">Seite 7</button>
                            <button class="btn btn-primary btn-sm" onclick="sendCommand('!page 8')">Seite 8</button>
                            <button class="btn btn-primary btn-sm" onclick="sendCommand('!page 9')">Seite 9</button>
                            <button class="btn btn-primary btn-sm" onclick="sendCommand('!page 10')">Seite 10</button>
                        </div>
                    </div>
                    
                    <!-- Cycle Controls -->
                    <div class="control-section">
                        <h4><i class="fas fa-sync"></i> Cycle Control</h4>
                        <div class="toggle-container">
                            <label><i class="fas fa-sync-alt"></i> Auto Cycle</label>
                            <label class="switch">
                                <input type="checkbox" id="cycle-toggle">
                                <span class="slider"></span>
                            </label>
                            <span class="toggle-status" id="cycle-status">Off</span>
                        </div>
                    </div>
                    
                    <!-- Volume Controls -->
                    <div class="control-section">
                        <h4><i class="fas fa-volume-up"></i> Lautstärke</h4>
                        <div class="volume-control">
                            <label style="min-width: 80px;">Radio:</label>
                            <input type="range" id="radio-volume" min="0" max="100" value="50" class="form-control">
                            <span id="radio-volume-display" style="min-width: 40px;">50%</span>
                        </div>
                        <div class="volume-control" style="margin-top: 10px;">
                            <label style="min-width: 80px;">YouTube:</label>
                            <input type="range" id="yt-volume" min="0" max="100" value="50" class="form-control">
                            <span id="yt-volume-display" style="min-width: 40px;">50%</span>
                        </div>
                    </div>
                    
                    <!-- Manual Command -->
                    <div class="control-section">
                        <h4><i class="fas fa-terminal"></i> Manueller Befehl</h4>
                        <div class="input-group">
                            <span class="input-group-addon" style="background: #34495e; color: #ecf0f1; border-color: #34495e;">
                                <i class="fas fa-chevron-right"></i>
                            </span>
                            <input placeholder="Befehl eingeben..." type="text" id="message" class="form-control">
                            <span class="input-group-btn">
                                <button id="send-btn" class="btn btn-success" type="button"><i class="fas fa-paper-plane"></i> Senden</button>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="panel panel-success">
                <div class="panel-heading">
                    <i class="fas fa-info-circle"></i> System Status
                </div>
                <div class="panel-body">
                    <div class="info-grid">
                        <div class="info-item">
                            <strong>Aktuelle Seite</strong>
                            <span class="status-badge page" id="Page">-</span>
                        </div>
                        <div class="info-item">
                            <strong>FUN Mode</strong>
                            <span class="status-badge" id="FUN">-</span>
                        </div>
                        <div class="info-item">
                            <strong>Radio</strong>
                            <span class="status-badge" id="Radio">-</span>
                        </div>
                        <div class="info-item">
                            <strong>Cycle</strong>
                            <span class="status-badge" id="Cycle">-</span>
                        </div>
                        <div class="info-item">
                            <strong>Snow</strong>
                            <span class="status-badge" id="Snow">-</span>
                        </div>
                        <div class="info-item">
                            <strong>Wheel</strong>
                            <span class="status-badge" id="Wheel">-</span>
                        </div>
                        <div class="info-item">
                            <strong>Cat</strong>
                            <span class="status-badge" id="Cat">-</span>
                        </div>
                        <div class="info-item">
                            <strong>CD</strong>
                            <span class="status-badge" id="CD">-</span>
                        </div>
                        <div class="info-item">
                            <strong>Bus Active</strong>
                            <span class="status-badge" id="BusActive">-</span>
                        </div>
                        <div class="info-item">
                            <strong>Radio Station</strong>
                            <div id="Radiostation" style="margin-top: 4px;">-</div>
                        </div>
                        <div class="info-item">
                            <strong>Radio Volume</strong>
                            <span class="status-badge volume" id="Radiovolume">-</span>
                        </div>
                        <div class="info-item">
                            <strong>YT Volume</strong>
                            <span class="status-badge volume" id="YTvolume">-</span>
                        </div>
                    </div>
                    <div class="info-item" style="margin-top: 10px;">
                        <strong>Song Title</strong>
                        <div id="SongTitle" style="margin-top: 4px; font-size: 12px;">-</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-lg-6">
            <div class="panel panel-warning">
                <div class="panel-heading">
                    <i class="fas fa-terminal"></i> Console Output
                </div>
                <div class="panel-body">
                    <pre id="console"></pre>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <i class="fas fa-users"></i> Verbundene Clients
                    <button class="btn btn-sm btn-default pull-right" id="updatelist" style="margin-top: -5px;">
                        <i class="fas fa-sync-alt"></i> Update
                    </button>
                </div>
                <div class="panel-body">
                    <table class="table table-striped table-hover">
                        <thead>
                        <tr>
                            <th><i class="fas fa-network-wired"></i> IP</th>
                            <th><i class="fas fa-server"></i> Hostname</th>
                            <th><i class="fas fa-desktop"></i> Appname</th>
                        </tr>
                        </thead>
                        <tbody id="clientlist">
                        <tr>
                            <td colspan="3" class="text-center" style="color: #7f8c8d;">
                                <i class="fas fa-spinner fa-spin"></i> Lade Clients...
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>


<script src="//ajax.googleapis.com/ajax/libs/jquery/2.0.0/jquery.min.js"></script>
<script type="text/javascript" src="../config.js.php"></script>

<script language="javascript" type="text/javascript">
    var websocket;
    
    function clean(obj) {
        var propNames = Object.getOwnPropertyNames(obj);
        for (var i = 0; i < propNames.length; i++) {
            var propName = propNames[i];
            if (obj[propName] === null || obj[propName] === undefined || obj[propName] === "") {
                delete obj[propName];
            }
        }
    }
    
    function sendCommand(cmd) {
        if (websocket && websocket.readyState === WebSocket.OPEN) {
            var msg = { message: cmd };
            websocket.send(JSON.stringify(msg));
            LogToConsole("→ Gesendet: " + cmd);
        } else {
            alert("WebSocket nicht verbunden!");
        }
    }
    
    function formatTimestamp() {
        var now = new Date();
        return '[' + now.toLocaleTimeString('de-DE') + '] ';
    }
    
    function LogToConsole(msg) {
        var console = document.getElementById("console");
        console.innerHTML += formatTimestamp() + msg + "\n";
        console.scrollTop = console.scrollHeight;
    }

    function ListClients() {
        document.getElementById("clientlist").innerHTML = '<tr><td colspan="3" class="text-center" style="color: #7f8c8d;"><i class="fas fa-spinner fa-spin"></i> Lade...</td></tr>';
        var msg = { message: "!clientlist" };
        websocket.send(JSON.stringify(msg));
    }
    
    function updateStatusBadge(elementId, value) {
        var el = document.getElementById(elementId);
        el.textContent = value;
        if (value == 'True' || value == true || value == 1) {
            el.className = 'status-badge active';
        } else if (value == 'False' || value == false || value == 0) {
            el.className = 'status-badge inactive';
        } else if (elementId == 'Page') {
            el.className = 'status-badge page';
        } else if (elementId == 'Radiovolume' || elementId == 'YTvolume') {
            el.className = 'status-badge volume';
            el.textContent = Math.round(value * 100) + '%';
        } else {
            el.className = 'status-badge';
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

    $(document).ready(function () {
        // Reload button
        $('#reload').click(function () {
            var json = {
                ToastBody: ("\<script\>location.reload\(\)\<\/script\>"),
                ToastHistory: ("false")
            };
            clean(json);
            $.ajax({
                url: 'api.php',
                type: 'post',
                contentType: 'application/x-www-form-urlencoded',
                success: function (data) {
                    LogToConsole("✓ Reload-Befehl gesendet");
                },
                data: json
            });
        });
        
        // Volume sliders
        $('#radio-volume').on('input', function() {
            var vol = $(this).val();
            $('#radio-volume-display').text(vol + '%');
        });
        
        $('#radio-volume').on('change', function() {
            var vol = $(this).val() / 100;
            sendCommand('!var Radiovolume ' + vol);
        });
        
        $('#yt-volume').on('input', function() {
            var vol = $(this).val();
            $('#yt-volume-display').text(vol + '%');
        });
        
        $('#yt-volume').on('change', function() {
            var vol = $(this).val() / 100;
            sendCommand('!var YTvolume ' + vol);
        });

        // WebSocket connection
        var wsUri = DASHBOARD_CONFIG.WEBSOCKET_URL;
        websocket = new WebSocket(wsUri);

        websocket.onopen = function (ev) {
            LogToConsole("✓ WebSocket verbunden!");
            updateWSStatus(true);
            msg = { message: '!reg [Adminpanel]' };
            websocket.send(JSON.stringify(msg));
            ListClients();
            // Request initial status update
            setTimeout(function() {
                sendCommand('!status');
            }, 500);
        };

        websocket.onerror = function (ev) {
            LogToConsole("✗ WebSocket Fehler!");
            updateWSStatus(false);
        };
        
        websocket.onclose = function (ev) {
            LogToConsole("✗ WebSocket Verbindung geschlossen");
            updateWSStatus(false);
            // Reconnect attempt after 5 seconds
            setTimeout(function() {
                LogToConsole("→ Versuche Reconnect...");
                websocket = new WebSocket(wsUri);
            }, 5000);
        };

        // Button handlers
        $('#updatelist').click(function () {
            ListClients();
        });
        
        $('#send-btn').click(function () {
            var mymessage = $('#message').val();
            if (mymessage.trim() === '') return;
            $('#message').val("");
            sendCommand(mymessage);
        });
        
        $('#message').keypress(function(e) {
            if (e.which == 13) { // Enter key
                $('#send-btn').click();
            }
        });

        // Toggle switches - prevent loops by checking if user initiated
        $('#fun-toggle').change(function(e) {
            if (!$(this).data('updating')) {
                var isChecked = $(this).is(':checked');
                sendCommand('!var FUN ' + (isChecked ? 'True' : 'False'));
            }
        });
        
        $('#radio-toggle').change(function(e) {
            if (!$(this).data('updating')) {
                var isChecked = $(this).is(':checked');
                sendCommand('!var Radio ' + (isChecked ? 'True' : 'False'));
            }
        });
        
        $('#snow-toggle').change(function(e) {
            if (!$(this).data('updating')) {
                var isChecked = $(this).is(':checked');
                sendCommand('!var Snow ' + (isChecked ? 'True' : 'False'));
            }
        });
        
        $('#wheel-toggle').change(function(e) {
            if (!$(this).data('updating')) {
                var isChecked = $(this).is(':checked');
                sendCommand('!var Wheel ' + (isChecked ? 'True' : 'False'));
            }
        });
        
        $('#cycle-toggle').change(function(e) {
            if (!$(this).data('updating')) {
                var isChecked = $(this).is(':checked');
                sendCommand('!var Cycle ' + (isChecked ? 'True' : 'False'));
            }
        });
        
        // Spin button for wheel
        $('#spin-btn').click(function() {
            sendCommand('!spin');
            // Show spinning animation
            $('#dice-icon').hide();
            $('#spin-icon').show();
            // Reset after 3 seconds
            setTimeout(function() {
                $('#spin-icon').hide();
                $('#dice-icon').show();
            }, 3000);
        });

        // WebSocket message handler
        websocket.onmessage = function (ev) {
            var msg = JSON.parse(ev.data);
            var type = msg.type;
            var message = msg.message;

            if (type == 'update') {
                updateStatusBadge('Page', msg.Page);
                updateStatusBadge('FUN', msg.FUN);
                updateStatusBadge('Cat', msg.Cat);
                updateStatusBadge('Snow', msg.Snow);
                updateStatusBadge('BusActive', msg.BusActive);
                updateStatusBadge('Wheel', msg.Wheel);
                updateStatusBadge('Cycle', msg.Cycle);
                updateStatusBadge('CD', msg.CD);
                updateStatusBadge('Radio', msg.Radio);
                updateStatusBadge('Radiovolume', msg.Radiovolume);
                updateStatusBadge('YTvolume', msg.YTvolume);
                
                // Update toggle switches - set data flag to prevent triggering change event
                var updateToggle = function(selector, value) {
                    var toggle = $(selector);
                    // Check for various true values: true, 'true', 'True', 1
                    var isOn = value === true || value === 'true' || value === 'True' || value === 1 || value === '1';
                    toggle.data('updating', true);
                    toggle.prop('checked', isOn);
                    toggle.data('updating', false);
                    return isOn;
                };
                
                var funOn = updateToggle('#fun-toggle', msg.FUN);
                var radioOn = updateToggle('#radio-toggle', msg.Radio);
                var snowOn = updateToggle('#snow-toggle', msg.Snow);
                var wheelOn = updateToggle('#wheel-toggle', msg.Wheel);
                var cycleOn = updateToggle('#cycle-toggle', msg.Cycle);
                
                // Update status labels
                $('#fun-status').text(funOn ? 'On' : 'Off');
                $('#radio-status').text(radioOn ? 'On' : 'Off');
                $('#snow-status').text(snowOn ? 'On' : 'Off');
                $('#wheel-status').text(wheelOn ? 'On' : 'Off');
                $('#cycle-status').text(cycleOn ? 'On' : 'Off');
                
                // Show/hide wheel controls based on wheel status
                if (wheelOn) {
                    $('#wheel-controls').slideDown();
                } else {
                    $('#wheel-controls').slideUp();
                }
                
                $('#Radiostation').html(msg.Radiostation || '-');
                $('#SongTitle').html(msg.SongTitle || '-');
                
                // Update volume sliders
                if (msg.Radiovolume !== undefined) {
                    var radioVol = Math.round(msg.Radiovolume * 100);
                    $('#radio-volume').val(radioVol);
                    $('#radio-volume-display').text(radioVol + '%');
                }
                if (msg.YTvolume !== undefined) {
                    var ytVol = Math.round(msg.YTvolume * 100);
                    $('#yt-volume').val(ytVol);
                    $('#yt-volume-display').text(ytVol + '%');
                }
            }
            
            if (type == 'clientlist') {
                if (document.getElementById("clientlist").innerHTML.includes('Lade...')) {
                    document.getElementById("clientlist").innerHTML = "";
                }
                document.getElementById("clientlist").innerHTML += message + "\n";
            }
            
            if (type == 'console') {
                LogToConsole("← " + message);
            }
            
            if (type == 'auth') {
                msg = { message: '!reg [Adminpanel]' };
                websocket.send(JSON.stringify(msg));
            }
        };

        // Auto-update client list every 30 seconds
        window.setInterval(function () {
            if (websocket && websocket.readyState === WebSocket.OPEN) {
                ListClients();
            }
        }, 30000);
    });
</script>