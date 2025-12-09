<?php
if (isset($_GET["action"]) && $_GET["action"] == "save") {
    file_put_contents('C:/scripts/IT-Dashboard/best.txt', json_encode($_POST) . PHP_EOL, FILE_APPEND | LOCK_EX);
    die();
}

if (isset($_GET["action"]) && $_GET["action"] == "delete") {
    $search = json_encode($_POST) . "\r\n";
    $contents = file_get_contents('C:/scripts/IT-Dashboard/best.txt');
    $contents = str_replace($search, '', $contents);
    file_put_contents('C:/scripts/IT-Dashboard/best.txt', $contents);
    die();
}
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
    <link rel="stylesheet" type="text/css" href="//maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="//cdn.datatables.net/1.10.19/css/dataTables.bootstrap.min.css">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css">
    <link rel="stylesheet" type="text/css" href="./css/style.css">
    <style>
        table {
            table-layout:fixed;
            width:100%;
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
                        <li class="active"><a href="./best.php"><i class="fas fa-star"></i> Best</a></li>
                        <li><a href="./youtube.php"><i class="fab fa-youtube"></i> YouTube</a></li>
                        <li><a href="./index.php"><i class="fas fa-cog"></i> Adminpanel</a></li>
                        <li><a href="./features.php"><i class="fas fa-lightbulb"></i> Feature Request</a></li>
						<li><a href="./julianometer.php"><i class="fas fa-chart-line"></i> Julian-O-Meter</a></li>
                        <li><a href="./newtoast.php"><i class="fas fa-bell"></i> Neuer Toast</a></li>
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
        <div class="col-lg-12">
            <?php
            $best = array();
            $i = 0;

            foreach (file('C:/scripts/IT-Dashboard/best.txt') as $line) {
                $json = json_decode($line);
                array_push($best, $json);
            }

            echo "<table id='ToastTable' class=\"table table-bordered\">";
            echo "<thead>";
            echo "<tr>";
            echo "<th class='col-md-1'>ToastSubject</th>";
            echo "<th class='col-md-3'>ToastPicture</th>";
            echo "<th>JSON</th>";
            echo "<th class='col-md-2'>Aktion</th>";
            echo "</tr>";
            echo "</thead>";
            echo "<tbody>";
            foreach ($best as $line) {
                $i++;
                echo "<tr>";
                if (isset($line->ToastSubject)) {
                    echo '<td id="ToastSubject' . $i . '">' . htmlspecialchars($line->ToastSubject) . '</td>';
                } else {
                    echo '<td id="ToastSubject' . $i . '"></td>';
                }
                if (isset($line->ToastPicture)) {
                    if (strpos($line->ToastPicture, 'mp4') == true || strpos($line->ToastPicture, 'webm') == true) {
                        echo '<td><video controls id="ToastPicture' . $i . '" style="height: 200px;display:block; margin:0 auto;max-width:300px" src="' . htmlspecialchars($line->ToastPicture) . '"></video></td>';
                    } else {
                        echo '<td><img alt="ToastPicture" id="ToastPicture' . $i . '" style="height: 200px;display:block; margin:0 auto;max-width:300px" src="' . htmlspecialchars($line->ToastPicture) . '" /></td>';
                    }
                } else {
                    echo '<td id="ToastPicture' . $i . '"></td>';
                }
                echo '<td><pre contenteditable="true" class="pre-scrollable" id="ToastJSON' . $i . '">'.json_encode($line,JSON_PRETTY_PRINT).'</pre></td>';
                echo '<td><div class="btn-group"><button id="' . $i . '" class="btn btn-primary repeat">Wiederholen</button><button id="' . $i . '" class="btn btn-danger delete">Löschen</button></div></td>';
                echo "</tr>";
            }
            echo "</tbody>";
            echo "</table>";

            ?>
        </div>
    </div>
</div>
<script src="//ajax.googleapis.com/ajax/libs/jquery/2.0.0/jquery.min.js"></script>
<script src="//cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js"></script>
<script src="//cdn.datatables.net/1.10.19/js/dataTables.bootstrap.min.js"></script>
<script>
    $('.repeat').click(function () {
        var id = $(this).attr('id');
        var json = JSON.parse($('#ToastJSON' + id).html());
        $.ajax({
            url: 'api.php',
            type: 'post',
            contentType: 'application/x-www-form-urlencoded',
            success: function (data) {
                //nothing
                console.log(json);
            },
            data: json
        });
    });
    $('#reload').click(function () {
        var json = {
            ToastBody: ("\<script\>location.reload\(\)\<\/script\>"),
            ToastHistory: ("false")
        };
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
    $('.delete').click(function () {
        var id = $(this).attr('id');
        var json = JSON.parse($('#ToastJSON' + id).html());
        $.ajax({
            url: 'best.php?action=delete',
            type: 'post',
            contentType: 'application/x-www-form-urlencoded',
            success: function (data) {
                //nothing
                console.log(JSON.stringify(json));
            },
            data: json
        });
    });
    $('#ToastTable').DataTable({
        "bSort":false
    });
</script>

</body>
</html>
