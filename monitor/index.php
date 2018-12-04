<!DOCTYPE html>
<html>

<head>
    <title>IT Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="Pragma" content="no-cache">
    <meta charset="utf-8"/>
    <!-- Fonts -->
    <link href='//fonts.googleapis.com/css?family=Roboto+Condensed:300,400' rel='stylesheet' type='text/css'>
    <link href='//fonts.googleapis.com/css?family=Lato:300,400,700,900' rel='stylesheet' type='text/css'>
    <!-- Favicon -->
    <link rel="shortcut icon" href="./img/favicon.ico" type="image/x-ico; charset=binary"/>
    <link rel="icon" href="./img/favicon.ico" type="image/x-ico; charset=binary"/>
    <!-- CSS Libs -->
    <link rel="stylesheet" type="text/css" href="lib/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="lib/css/font-awesome.min.css">
    <link rel="stylesheet" type="text/css" href="lib/css/animate.min.css">
    <link rel="stylesheet" type="text/css" href="lib/css/bootstrap-switch.min.css">
    <link rel="stylesheet" type="text/css" href="lib/css/checkbox3.min.css">
    <!-- CSS App -->
    <link rel="stylesheet" type="text/css" href="css/style.css?random=<?php echo uniqid(); ?>">
    <link rel="stylesheet" type="text/css" href="css/themes/flat-blue.css?random=<?php echo uniqid(); ?>">
</head>

<?php
$ua = htmlentities($_SERVER['HTTP_USER_AGENT'], ENT_QUOTES, 'UTF-8');
if (preg_match('~MSIE|Internet Explorer~i', $ua) || (strpos($ua, 'Trident/7.0; rv:11.0') !== false)) {
    echo "<div class='row'>";
    echo "<div class='col-lg-2 col-lg-offset-5'>";
    echo "<img src='./img/ie.png' class='img-responsive'/>";
    echo "<div class='panel panel-danger'>";
    echo "<div class='panel-heading'>Browser nicht unterstützt</div>";
    echo "<div class='panel-body'>Bitte nutze einen richtigen Browser...</div>";
    echo "</div>";
    echo "</div>";
    echo "</div>";
    die();
}
?>

<body class="flat-blue">
<!--    <div>-->
<!--        <audio id="radiostream" src="https://ndr-ndr1niedersachsen-hannover.cast.addradio.de/ndr/ndr1niedersachsen/hannover/mp3/128/stream.mp3"></audio>-->
<!--    </div>-->
<div class="app-container">
    <div class="row content-container">
        <nav class="navbar navbar-default navbar-fixed-top navbar-top">
            <div class="container-fluid">
                <div class="navbar-header">
                    <button type="button" class="navbar-expand-toggle">
                        <i class="fa fa-bars icon"></i>
                    </button>
                    <ol class="breadcrumb navbar-breadcrumb">
                        <li class="active">IT-Dashboard</li>
                        <li id="Pageheader"></li>
                        <li>Seitenwechsel in: <span id="page-timer"></span></li>
                    </ol>
                </div>
                <ul class="nav navbar-nav navbar-right">
                    <button type="button" class="navbar-right-expand-toggle pull-right visible-xs">
                        <i class="fa fa-times icon"></i>
                    </button>
                    <li class="dropdown profile">
                        <a href="#">
                            <div id="clock" class="navbar-brand"></div>
                        </a>
                    </li>
                </ul>
                <div id="cat">
                </div>
            </div>
            <div id="loadingbar"></div>
        </nav>
        <div class="side-menu sidebar-inverse">
            <nav class="navbar navbar-default" role="navigation">
                <div class="side-menu-container">
                    <div class="navbar-header">
                        <a class="navbar-brand" href="#">
                            <div class="icon fa fa-tachometer"></div>
                            <div class="title">IT Dashboard</div>
                        </a>
                    </div>
                    <ul id="navigation" class="nav navbar-nav">
                        <li class="active">
                            <a href="javascript:ButtonPage(1);">
                                <span class="icon fa fa-cloud"></span><span class="title">Temperatur</span>
                            </a>
                        </li>
                        <li>
                            <a href="javascript:ButtonPage(2);">
                                <span class="icon fa fa-life-ring"></span><span class="title">helpLine</span>
                            </a>
                        </li>
                        <li>
                            <a href="javascript:ButtonPage(3);">
                                <span class="icon fa fa-globe"></span><span class="title">Netzwerk</span>
                            </a>
                        </li>
                        <li>
                            <a href="javascript:ButtonPage(4);">
                                <span class="icon fa fa-bar-chart"></span><span class="title">Performance</span>
                            </a>
                        </li>
                        <li>
                            <a href="javascript:ButtonPage(5);">
                                <span class="icon fa fa-bar-chart"></span><span class="title">VMware</span>
                            </a>
                        </li>
                        <li>
                            <a href="javascript:ButtonPage(6);">
                                <span class="icon fa fa-bell"></span><span class="title">SCOM</span>
                            </a>
                        </li>
                        <li>
                            <a href="javascript:ButtonPage(7);">
                                <span class="icon fa fa-hdd-o"></span><span class="title">NetApp</span>
                            </a>
                        </li>
                        <li>
                            <a href="javascript:ButtonPage(8);">
                                <span class="icon fa fa-save"></span><span class="title">Backup</span>
                            </a>
                        </li>
                        <li>
                            <a href="javascript:ButtonPage(9);">
                                <span class="icon fa fa-code-fork"></span><span class="title">
                                    🚍400

                                </span>
                            </a>
                        </li>
                        <li>
                            <a href="javascript:ButtonPage(10);">
                                <span class="icon fa fa-wrench"></span><span class="title">Updates</span>
                            </a>
                        </li>
                        <li>
                            <a href="javascript:ButtonPage(11);">
                                <span class="icon fa fa-exclamation-triangle"></span><span class="title">DERDACK</span>
                            </a>
                        </li>
                        <li>
                            <a href="javascript:ButtonPage(12);">
                                <span class="icon fa fa-puzzle-piece"></span><span class="title">Projekte</span>
                            </a>
                        </li>
                    </ul>
                </div>
                <!-- /.navbar-collapse -->
            </nav>
        </div>
        <!-- Modal -->
        <div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
            <div class="modal-dialog" role="document" style="width: 50%;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title h1 text-center" id="myModalLabel">
                            Modal title
                        </h4>
                    </div>
                    <div class="modal-body">
                        <div id="modalimage"></div>
                        <div id="modalbodytext" class="h2" style="word-wrap: break-word;"></div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Main Content -->
        <div class="container-fluid">
            <div class="side-body padding-top">
                <!--Load AJAX-->
                <div id="ajax">
                    <h1 class="text-center"><i class="fa fa-refresh fa-spin fa-3x fa-fw margin-bottom"></i></h1>
                </div>
            </div>
        </div>
    </div>
    <div id="toast"><div id="toastimg"></div><div id="toastdesc"></div></div>
    <div id="buscontainer"><img id="bus" class="flip" src="./img/bus.gif"></div>
    <div>
        <!-- Javascript Libs -->
        <script type="text/javascript" src="lib/js/jquery.min.js"></script>
        <script type="text/javascript" src="lib/js/bootstrap.min.js"></script>
        <script type="text/javascript" src="lib/js/bootstrap-switch.min.js"></script>
        <script type="text/javascript" src="lib/js/jquery.matchHeight-min.js"></script>
        <script type="text/javascript" src="lib/js/Chart.bundle.js"></script>

        <!-- Javascript -->
        <script type="text/javascript" src="js/app.js?random=<?php echo uniqid(); ?>"></script>

        <script src='./js/snowfall.min.jquery.js'></script>
    </div>
</div>
</body>
</html>
