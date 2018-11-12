<!DOCTYPE html>
<html>

<head>
    <title>IT Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Fonts -->
    <link href='http://fonts.googleapis.com/css?family=Roboto+Condensed:300,400' rel='stylesheet' type='text/css'>
    <link href='http://fonts.googleapis.com/css?family=Lato:300,400,700,900' rel='stylesheet' type='text/css'>
    <!-- CSS Libs -->
    <link rel="stylesheet" type="text/css" href="../monitor/lib/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="../monitor/lib/css/font-awesome.min.css">
    <link rel="stylesheet" type="text/css" href="../monitor/lib/css/animate.min.css">
    <link rel="stylesheet" type="text/css" href="../monitor/lib/css/bootstrap-switch.min.css">
    <link rel="stylesheet" type="text/css" href="../monitor/lib/css/checkbox3.min.css">
    <!-- CSS App -->
    <!--	<link rel="stylesheet" type="text/css" href="../css/style.css">-->
    <!--	<link rel="stylesheet" type="text/css" href="../css/themes/flat-blue.css">-->
    <style>
        .row {
            margin-top: 15px;
        }
    </style>
</head>
<body>


<!--<buttton onclick=""></buttton>-->
<!---->
<!--<input type="text" name="message" id="message" />-->
<!--<button id="send-btn">Senden</button>-->
<div class="container-fluid">
    <div class="row">
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading">
                    Adminpanel
                </div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-5">
                            <div class="btn-group">
                                <button class="btn btn-success" id="fun-on-btn">FUN Mode on</button>
                                <button class="btn btn-danger" id="fun-off-btn">FUN Mode off</button>
                            </div>
                        </div>
                        <div class="col-md-offset-4 col-md-3">
                            <div class="btn-group pull-right">
                                <button class="btn btn-success" id="radio-on-btn">Radio on</button>
                                <button class="btn btn-danger" id="radio-off-btn">Radio off</button>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="input-group">
                                <input placeholder="Youtube-URL..." type="text" id="yt-message" class="form-control">
                                <span class="input-group-btn">
									<button id="yt-btn" class="btn btn-success" type="button">Play</button>
								</span>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="input-group">
                                <input placeholder="Manueller Befehl..." type="text" id="message" class="form-control">
                                <span class="input-group-btn">
									<button id="send-btn" class="btn btn-default" type="button">Senden</button>
								</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        <div class="col-md-6">
            <div class="panel panel-primary">
                <div class="panel-heading">
                    Variablen
                </div>
                <div class="panel-body">
                    Page: <span id="Page"></span><br/>
                    FUN: <span id="FUN"></span><br/>
                    Cat: <span id="Cat"></span><br/>
                    Snow: <span id="Snow"></span><br/>
                    BusActive: <span id="BusActive"></span><br/>
                    Cycle: <span id="Cycle"></span><br/>
                    CD: <span id="CD"></span><br/>
                    Radio: <span id="Radio"></span><br/>
                    Radiostation: <span id="Radiostation"></span><br/>
                    Radiovolume: <span id="Radiovolume"></span><br/>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-lg-6">
            <div class="panel panel-default">
                <div class="panel-heading">
                    Console
                </div>
                <div class="panel-body">
                    <pre id="console"></pre>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <button class="btn btn-info" id="updatelist">Update</button>
                </div>
                <div class="panel-body">
                    <table class="table">
                        <thead>
                        <tr>
                            <th>IP</th>
                            <th>Hostname</th>
                            <th>Appname</th>
                        </tr>
                        </thead>
                        <tbody id="clientlist">

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

<script language="javascript" type="text/javascript">

    function LogToConsole(msg) {
        document.getElementById("console").innerHTML += msg + "\n";
    }

    function ListClients() {
        document.getElementById("clientlist").innerHTML = "";
        //prepare json data
        var msg = {
            message: "!clientlist"
        };
        //convert and send data to server
        websocket.send(JSON.stringify(msg));
    }

    $(document).ready(function () {
        //create a new WebSocket object.
        var wsUri = "ws://it-dashboard.cbr.de:9000";
        websocket = new WebSocket(wsUri);

        websocket.onopen = function (ev) { // connection is open
            LogToConsole("Verbunden!");
            msg = {
                message: '!reg [Adminpanel]'
            };
            websocket.send(JSON.stringify(msg));
            ListClients();
        };

        $('#updatelist').click(function () { //use clicks message send button
            ListClients();
        });
        $('#send-btn').click(function () { //use clicks message send button
            var mymessage = $('#message').val(); //get message text
            $('#message').val("");
            //prepare json data
            var msg = {
                message: mymessage
            };
            //convert and send data to server
            websocket.send(JSON.stringify(msg));
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


        //FUN


        $('#fun-on-btn').click(function () {
            var msg = {
                message: '!var FUN True'
            };
            websocket.send(JSON.stringify(msg));
        });
        $('#fun-off-btn').click(function () {
            var msg = {
                message: '!var FUN False'
            };
            websocket.send(JSON.stringify(msg));
        });


        //Radio

        $('#radio-on-btn').click(function () {
            var msg = {
                message: '!var Radio True'
            };
            websocket.send(JSON.stringify(msg));
        });
        $('#radio-off-btn').click(function () {
            var msg = {
                message: '!var Radio False'
            };
            websocket.send(JSON.stringify(msg));
        });


        //#### Message received from server?
        websocket.onmessage = function (ev) {
            var msg = JSON.parse(ev.data); //PHP sends Json data
            var type = msg.type; //message type
            var message = msg.message; //message text

            console.log(type + ": " + message);

            if (type == 'update') {
                $("#Page").html(msg.Page);
                $("#FUN").html(msg.FUN);
                $("#Cat").html(msg.Cat);
                $("#Snow").html(msg.Snow);
                $("#BusActive").html(msg.BusActive);
                $("#Cycle").html(msg.Cycle);
                $("#CD").html(msg.CD);
                $("#Radio").html(msg.Radio);
                $("#Radiostation").html(msg.Radiostation);
                $("#Radiovolume").html(msg.Radiovolume);
            }
            if (type == 'clientlist') {
                document.getElementById("clientlist").innerHTML += message + "\n";
//			    $("#clientlist").textContent += message;
                //console.log(message);
            }
            if (type == 'console') {
                document.getElementById("console").innerHTML += message + "\n";
//			    $("#clientlist").textContent += message;
                //console.log(message);
            }
            if (type == 'auth') {
                msg = {
                    message: '!reg [Adminpanel]'
                };
                websocket.send(JSON.stringify(msg));
            }
        };

        websocket.onerror = function (ev) {
            LogToConsole("Error Occurred - " + ev.data);
        };
        websocket.onclose = function (ev) {
            LogToConsole("Connection Closed");
        };

        window.setInterval(function () {
//            ListClients();
        }, 5000);

    });
</script>