<?php
require("../config.php");
$conn = sqlsrv_connect(DASHBOARD_SQL_INSTANCE, $Default_Connection);

if ($conn) {
    if(isset($_POST["user"]) && $_POST["user"] != ""){
       $sql = "INSERT INTO [IT-Dashboard].[dbo].[IT-Dashboard_Julianometer]([Description],[User],[Timestamp]) VALUES ('".$_POST["description"]."','".$_POST["user"]."',GETDATE())";
	   if(sqlsrv_query($conn, $sql)){
			echo "OK";
        }else {
            echo "<pre>";
            die(print_r(sqlsrv_errors(), true));
        }
    }
    if ($result = sqlsrv_query($conn, "SELECT *  FROM [IT-Dashboard].[dbo].[IT-Dashboard_Julianometer] ORDER BY [ID] DESC")) {
        $features = array();
        while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
            array_push($features, $row);
        }
    } else {
        echo "<pre>";
        die(print_r(sqlsrv_errors(), true));
    }
} else {
    echo "<pre>";
    die(print_r(sqlsrv_errors(), true));
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
                        <li><a href="./youtube.php">YouTube</a></li>
                        <li><a href="./index.php">Adminpanel</a></li>
                        <li><a href="./features.php">Feature Request</a></li>
                        <li class="active"><a href="./julianometer.php">Julian-O-Meter</a></li>
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
        <div class="col-lg-10 col-lg-offset-1">
            <button id="opennew" class="btn btn-primary">Neue Buchung</button>
            <!-- Modal -->
            <div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                            <h4 class="modal-title" id="myModalLabel">Neue Buchung</h4>
                        </div>
                        <div class="modal-body">
						    <div class="form-group">
								<input class="form-control" type="text" id="user" placeholder="Wer hat das Kontingent verbaucht?"/>
                            </div>
                            <div class="form-group">
                                <textarea class="form-control" rows="5" id="description" placeholder="Wieso wurde das Kontingent verbaucht?"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-dismiss="modal">Abbrechen</button>
                            <button type="button" id="newfeature" class="btn btn-primary">Speichern</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-lg-10 col-lg-offset-1">
            <table class="table table-bordered table-hover table-striped">
                <thead>
                <tr>
                    <th style="width: 10%">Datum</th>
                    <th>Grund</th>
                    <th style="width: 10%">Verbraucher</th>
                </tr>
                </thead>
                <tbody>
                <?php
                    foreach ($features as $feature) {
						$newDate = $feature["Timestamp"]->format('d.m.Y');
                        echo "<tr>";
                            echo "<td>".$newDate."</td>";
                            echo "<td>".$feature["Description"]."</td>";
                            echo "<td>".$feature["User"]."</td>";
                        echo "</tr>";
                    }
                ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script src="../monitor/lib/js/jquery.min.js"></script>
<script src="../monitor/lib/js/bootstrap.min.js"></script>
<script>
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
    $("#opennew").click(function() {
        $('#myModal').modal({backdrop: 'static', keyboard: false})
    });
    $("#newfeature").click(function() {
        $("#feature").submit();
        json = {
            user: $('#user').val(),
			description: $('#description').val()
        };
        $.ajax({
            url: 'julianometer.php',
            type: 'post',
            contentType: 'application/x-www-form-urlencoded',
            success: function (data) {
                location.reload();
            },
            data: json
        });
    });
</script>
</body>
</html>
