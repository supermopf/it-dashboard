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
    <link rel="stylesheet" type="text/css" href="../monitor/lib/css/font-awesome.min.css">
    <link rel="stylesheet" type="text/css" href="../monitor/lib/css/animate.min.css">
    <link rel="stylesheet" type="text/css" href="../monitor/lib/css/bootstrap-switch.min.css">
    <link rel="stylesheet" type="text/css" href="../monitor/lib/css/checkbox3.min.css">
    <link rel="stylesheet" type="text/css" href="../monitor/lib/css/bootstrap-slider.min.css">
    <!-- CSS App -->
    <!--	<link rel="stylesheet" type="text/css" href="../css/style.css">-->
    <!--	<link rel="stylesheet" type="text/css" href="../css/themes/flat-blue.css">-->
    <style>
        .row {
            margin-top: 25px;
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
                    <a class="navbar-brand" href="#">IT-Dashboard</a>
                </div>
                <div id="navbar" class="navbar-collapse collapse">
                    <ul class="nav navbar-nav">
                        <li><a href="./history.php">History</a></li>
                        <li><a href="./best.php">Best</a></li>
                        <li class="active"><a href="./youtube.php">YouTube</a></li>
                        <li><a href="./index.php">Adminpanel</a></li>
                        <li><a href="./features.php">Feature Request</a></li>
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
        <div class="col-lg-offset-1 col-lg-8">
            <div class="input-group">
                <input placeholder="Youtube-URL..." type="text" id="yt-message" class="form-control">
                <span class="input-group-btn">
                    <button id="yt-btn" class="btn btn-success" type="button">Play</button>
                </span>
            </div>
        </div>
        <div class="col-lg-3 form-group">
            <input id="volume" data-slider-id='volumeSlider' type="text" data-slider-min="1" data-slider-max="100" data-slider-step="1" data-slider-value="14"/>
        </div>
    </div>
    <div class="row">
        <div class="col-lg-offset-1 col-lg-10">
            <table class="table table-bordered">
                <thead>
                <tr>
                    <td style="width: 15%;">Thumbnail</td>
                    <td style="width: 40%;">Titel</td>
                    <td style="width: 35%;">URL</td>
                    <td style="width: 10%;">Aktion</td>
                </tr>
                </thead>
                <tbody>
                <?php
                $history = array_unique(array_reverse(file('C:/scripts/IT-Dashboard/ytlog.txt')));
                foreach ($history as $line) {
                    parse_str($line, $query);
                    reset($query);
                    $url = key($query);
                    $result = get_youtube_details($query[$url], "title");
                    echo "<tr>";
                    echo "<td>";
                    echo "<a href='" . $line . "'><img class='img-responsive' src='https://i.ytimg.com/vi/" . $query[$url] . "/hqdefault.jpg' /></a>";
                    echo "</td>";
                    echo "<td>";
                    echo $result;
                    echo "</td>";
                    echo "<td>";
                    echo "$line";
                    echo "</td>";
                    echo "<td>";
                    if($result != "Video nicht abspielbar"){
                        echo "<div class=\"btn-group\"><button yturl='".$line."' class=\"btn btn-primary repeat\">Wiederholen</button></div>";
                    }
                    echo "</td>";
                    echo "</tr>";
                }
                ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>


<?php

function get_youtube_details($ref, $detail)
{
    if (!isset($GLOBALS['youtube_details'][$ref])) {
        $json = @file_get_contents('https://www.youtube.com/oembed?url=http://www.youtube.com/watch?v=' . urlencode($ref) . '&format=json');
        if($json === false)
        {
            return "Video nicht abspielbar";
        }
        $GLOBALS['youtube_details'][$ref] = json_decode($json, true); //parse the JSON into an array
    }

    return $GLOBALS['youtube_details'][$ref][$detail]; //return the requested video detail
}

?>
<script src="../monitor/lib/js/jquery.min.js"></script>
<script src="../monitor/lib/js/bootstrap-slider.min.js"></script>
<script>
    var sliding = false;
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
            ToastBody: ("\<script\>location.reload\(\)\<\/script\>")
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
        var wsUri = "wss://it-dashboard.cbr.de:8999";
        websocket = new WebSocket(wsUri);

        websocket.onopen = function (ev) { // connection is open
            msg = {
                message: '!reg [Adminpanel]'
            };
            websocket.send(JSON.stringify(msg));
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