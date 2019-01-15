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
                    <a class="navbar-brand" href="#">IT-Dashboard</a>
                </div>
                <div id="navbar" class="navbar-collapse collapse">
                    <ul class="nav navbar-nav">
                        <li><a href="./history.php">History</a></li>
                        <li><a href="./best.php">Best</a></li>
                        <li><a href="./youtube.php">YouTube</a></li>
                        <li class="active"><a href="./index.php">Adminpanel</a></li>
                        <li><a href="./features.php">Feature Request</a></li>
                        <li><a href="./newtoast.php">Neuer Toast</a></li>
                    </ul>
                    <ul class="nav navbar-nav navbar-right" style="margin-right: 1%;">
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
    <div class="row" style="margin-top: 5%;">
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
                    YTvolume: <span id="YTvolume"></span><br/>
                    SongTitle: <span id="SongTitle"></span><br/>
                    RadioStationIcon: <span id="RadioStationIcon"></span><br/>
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
        var wsUri = "wss://it-dashboard.cbr.de:8999";
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
                $("#SongTitle").html(msg.SongTitle);
                $("#RadioStationIcon").html(msg.RadioStationIcon);
                $("#YTvolume").html(msg.YTvolume);
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