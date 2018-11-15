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
    <link rel="stylesheet" type="text/css" href="../monitor/lib/css/font-awesome.min.css">
    <link rel="stylesheet" type="text/css" href="../monitor/lib/css/animate.min.css">
    <link rel="stylesheet" type="text/css" href="../monitor/lib/css/bootstrap-switch.min.css">
    <link rel="stylesheet" type="text/css" href="../monitor/lib/css/checkbox3.min.css">
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
        <nav class="navbar navbar-default navbar-static-top">
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
                        <li class="active"><a href="./history.php">History</a></li>
                        <li><a href="./best.php">Best</a></li>
                        <li><a href="./youtube.php">YouTube</a></li>
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
    <div class="row">
        <div class="col-lg-12">
            <?php
            $history = array();
            $i = 0;

            foreach (file('C:/scripts/IT-Dashboard/logs.txt') as $line) {
                $json = json_decode($line);
                array_push($history, $json);
            }

            $history = array_reverse($history);

            echo "<table class=\"table table-bordered\">";
            echo "<thead>";
            echo "<tr>";
            echo "<th class='col-md-2'>ToastSubject</th>";
            echo "<th class='col-md-2'>ToastBody</th>";
            echo "<th class='col-md-2'>ToastPicture</th>";
            echo "<th class='col-md-2'>ToastSound</th>";
            echo "<th class='col-md-1'>ToastVolume</th>";
            echo "<th class='col-md-1'>ToastTime</th>";
            echo "<th class='col-md-2'>Aktion</th>";
            echo "</tr>";
            echo "</thead>";
            echo "<tbody>";
            foreach ($history as $line) {
                $i++;
                echo "<tr>";
                if (isset($line->ToastSubject)) {
                    echo '<td id="ToastSubject' . $i . '">' . htmlspecialchars($line->ToastSubject) . '</td>';
                } else {
                    echo '<td id="ToastSubject' . $i . '"></td>';
                }
                if (isset($line->ToastBody)) {
                    echo '<td id="ToastBody' . $i . '">' . htmlspecialchars($line->ToastBody) . '</td>';
                } else {
                    echo '<td id="ToastBody' . $i . '"></td>';
                }
                if (isset($line->ToastPicture)) {
                    if (strpos($line->ToastPicture, 'mp4') == true || strpos($line->ToastPicture, 'webm') == true) {
                        echo '<td><video controls id="ToastPicture' . $i . '" style="height: 150px" src="' . htmlspecialchars($line->ToastPicture) . '"></video>></td>';
                    } else {
                        echo '<td><img id="ToastPicture' . $i . '" style="height: 150px" src="' . htmlspecialchars($line->ToastPicture) . '" /></td>';
                    }
                } else {
                    echo '<td id="ToastPicture' . $i . '"></td>';
                }
                if (isset($line->ToastSound)) {
                    echo '<td id="ToastSound' . $i . '">' . htmlspecialchars($line->ToastSound) . '</td>';
                } else {
                    echo '<td id="ToastSound' . $i . '"></td>';
                }
                if (isset($line->ToastVolume)) {
                    echo '<td id="ToastVolume' . $i . '">' . htmlspecialchars($line->ToastVolume) . '</td>';
                } else {
                    echo '<td id="ToastVolume' . $i . '"></td>';
                }
                if (isset($line->ToastTime)) {
                    echo '<td id="ToastTime' . $i . '">' . htmlspecialchars($line->ToastTime) . '</td>';
                } else {
                    echo '<td id="ToastTime' . $i . '"></td>';
                }
                echo '<td><div class="btn-group"><button id="' . $i . '" class="btn btn-primary repeat">Wiederholen</button><button id="' . $i . '" class="btn btn-danger save">Speichern</button></div></td>';
                echo "</tr>";
            }
            echo "</tbody>";
            echo "</table>";

            ?>
        </div>
    </div>
</div>
<script src="//ajax.googleapis.com/ajax/libs/jquery/2.0.0/jquery.min.js"></script>
<script>
    function htmlDecode(input) {
        var e = document.createElement('div');
        e.innerHTML = input;
        // handle case of empty input
        return e.childNodes.length === 0 ? "" : e.childNodes[0].nodeValue;
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

    $('.repeat').click(function () {
        var id = $(this).attr('id');
        var json = {
            ToastSubject: ($('#ToastSubject' + id).html()),
            ToastBody: (htmlDecode($('#ToastBody' + id).html())),
            ToastPicture: ($('#ToastPicture' + id).attr('src')),
            ToastSound: ($('#ToastSound' + id).html()),
            ToastTime: ($('#ToastTime' + id).html()),
            ToastVolume: ($('#ToastVolume' + id).html())
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
    $('.save').click(function () {
        var id = $(this).attr('id');
        var json = {
            ToastSubject: ($('#ToastSubject' + id).html()),
            ToastBody: (htmlDecode($('#ToastBody' + id).html())),
            ToastPicture: ($('#ToastPicture' + id).attr('src')),
            ToastSound: ($('#ToastSound' + id).html()),
            ToastTime: ($('#ToastTime' + id).html()),
            ToastVolume: ($('#ToastVolume' + id).html())
        };
        clean(json);
        $.ajax({
            url: 'best.php?action=save',
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
