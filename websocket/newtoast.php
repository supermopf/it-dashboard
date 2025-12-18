<?php
/**
 * Created by PhpStorm.
 * User: victor.lange
 * Date: 15.01.2019
 * Time: 08:56
 */
?>
<!DOCTYPE html>
<html>
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
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css">
    <link rel="stylesheet" type="text/css" href="./css/style.css">
</head>
<body>

<?php 
$active_page = 'newtoast';
$show_reload_btn = true;
include('navbar.php'); 
?>

<div class="container-fluid">
    <div class="row" style="margin-top: 80px;">
        <form class="form-horizontal" action="#">
            <fieldset>
                <?php
                $properties = array(
                    "ToastSubject",
                    "ToastBody",
                    "ToastPicture",
                    "ToastColor",
                    "ToastTextColor",
                    "ToastSound",
                    "ToastTime",
                    "ToastVolume",
                    "ToastHistory",
                    "ToastVideoNoRepeat",
                    "Target"
                );

                foreach($properties as $item){
                    echo '<div class="form-group">';
                        echo '<label class="col-md-4 control-label" for="textinput">'.$item.'</label>  ';
                        echo '<div class="col-md-4">';
                            if ($item === 'Target') {
                                echo '<select id="'.$item.'" name="'.$item.'" class="form-control">';
                                echo '<option value="Alle">Alle Dashboards</option>';
                                echo '</select>';
                            } else {
                                echo '<input id="'.$item.'" name="'.$item.'" type="text" placeholder="'.$item.'" class="form-control input-md">';
                            }
                        echo '</div>';
                    echo '</div>';
                } ?>

                <div class="form-group">
                    <label class="col-md-4 control-label" for="singlebutton"></label>
                    <div class="col-md-4">
                        <button id="sendtoast" class="btn btn-success">Senden</button>
                    </div>
                </div>
            </fieldset>
        </form>
    </div>
</div>
<script src="../monitor/lib/js/jquery.min.js"></script>
<script src="../monitor/lib/js/bootstrap.min.js"></script>
<script type="text/javascript" src="../config.js.php"></script>
<script>
    var websocket;
    var reconnectAttempts = 0;
    var maxReconnectAttempts = 5;
    var reconnectDelay = 1000;
    var reconnectTimer = null;
    var intentionalClose = false;
    var isConnecting = false;
    
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
        
        websocket = new WebSocket(DASHBOARD_CONFIG.WEBSOCKET_URL);
        
        websocket.onopen = function(ev) {
            console.log('WebSocket verbunden');
            reconnectAttempts = 0;
            reconnectDelay = 1000;
            isConnecting = false;
            
            // Register and request dashboard list
            var msg = { message: '!reg [NewToast]' };
            websocket.send(JSON.stringify(msg));
            
            msg = { message: '!dashboardlist' };
            websocket.send(JSON.stringify(msg));
        };
        
        websocket.onerror = function(ev) {
            console.log('WebSocket Error');
            isConnecting = false;
        };
        
        websocket.onclose = function(ev) {
            console.log('WebSocket geschlossen');
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
        
        websocket.onmessage = function(ev) {
            var msg = JSON.parse(ev.data);
            
            if (msg.type === 'auth') {
                // Re-register and request dashboard list
                var authMsg = { message: '!reg [NewToast]' };
                websocket.send(JSON.stringify(authMsg));
                
                var listMsg = { message: '!dashboardlist' };
                websocket.send(JSON.stringify(listMsg));
            }
            
            if (msg.type === 'dashboardlist') {
                // Update dashboard dropdown
                var select = $('#Target');
                // Clear all except "Alle"
                select.find('option:not([value="Alle"])').remove();
                
                // Add each dashboard
                if (msg.dashboards && msg.dashboards.length > 0) {
                    msg.dashboards.forEach(function(dashboard) {
                        select.append($('<option></option>').attr('value', dashboard).text(dashboard));
                    });
                }
                
                // Restore last selection from localStorage
                var lastTarget = localStorage.getItem('lastToastTarget');
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
    
    $(document).ready(function() {
        // Initialize WebSocket connection
        connectWebSocket();
    });
    
    function clean(obj) {
        var propNames = Object.getOwnPropertyNames(obj);
        for (var i = 0; i < propNames.length; i++) {
            var propName = propNames[i];
            if (obj[propName] === null || obj[propName] === undefined || obj[propName] === "") {
                delete obj[propName];
            }
        }
    }
    $('#sendtoast').click(function () {
        var json = {
            <?php
                $jsarray = "";
                foreach($properties as $item){
                    $jsarray .= $item.': ($("#'.$item.'").val()),';
                }
                echo rtrim($jsarray,",");
            ?>};


        clean(json);
        
        // Save last selection to localStorage
        if (json.Target) {
            localStorage.setItem('lastToastTarget', json.Target);
        }
        
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
</script>
</body>
</html>
