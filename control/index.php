<!DOCTYPE html>
<html manifest="cache.txt">

<head>
    <title>IT Dashboard</title>
    <meta name="HandheldFriendly" content="true"/>
    <meta name="apple-mobile-web-app-capable" content="yes"/>
    <meta name="apple-mobile-web-app-status-bar-style" content="black"/>
    <meta name="apple-mobile-web-app-title" content="Dashboard App">
    <meta name="viewport" content="initial-scale=1 maximum-scale=1 user-scalable=0 minimal-ui"/>
    <link rel="shortcut icon" href="../monitor/img/favicon.ico" type="image/x-ico; charset=binary"/>
    <link rel="icon" href="../monitor/img/favicon.ico" type="image/x-ico; charset=binary"/>
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
    <link rel="stylesheet" type="text/css" href="../monitor/css/style.css">
    <link rel="stylesheet" type="text/css" href="../monitor/css/themes/flat-blue.css">
    <link rel="stylesheet" type="text/css" href="./style.css">
</head>
<body>
<div id="content-container" class="container-fluid fill-height">
    <div class="row">
        <div class="progress">
            <div id="timer" class="progress-bar" role="progressbar" data-transitiongoal="0" aria-valuemin="0"
                 aria-valuemax="30"></div>
        </div>
    </div>
    <div class="row">
        <div class="col-sm-3">
            <button id="page1" class="btn btn-xl btn-block"><span class="icon fa fa-cloud"></span>Wetter</button>
        </div>
        <div class="col-sm-3">
            <button id="page2" class="btn btn-xl btn-block"><span class="icon fa fa-life-ring"></span>HelpLine</button>
        </div>
        <div class="col-sm-3">
            <button id="page3" class="btn btn-xl btn-block"><span class="icon fa fa-globe"></span>Netzwerk</button>
        </div>
        <div class="col-sm-3">
            <button id="page4" class="btn btn-xl btn-block"><span class="icon fa fa-bar-chart"></span><span
                        class="title">Performance</span></button>
        </div>
    </div>
    <div class="row">
        <div class="col-sm-3">
            <button id="page5" class="btn btn-xl btn-block"><span class="icon fa fa-bar-chart"></span><span
                        class="title">VMware</span></button>
        </div>
        <div class="col-sm-3">
            <button id="page6" class="btn btn-xl btn-block"><span class="icon fa fa-bell"></span><span class="title">SCOM</span>
            </button>
        </div>
        <div class="col-sm-3">
            <button id="page7" class="btn btn-xl btn-block"><span class="icon fa fa-hdd-o"></span><span class="title">NetApp</span>
            </button>
        </div>
        <div class="col-sm-3">
            <button id="page8" class="btn btn-xl btn-block"><span class="icon fa fa-save"></span><span class="title">Backup</span>
            </button>
        </div>
    </div>
    <div class="row">

        <div class="col-sm-3">
            <button id="page9" class="btn btn-xl btn-block"><span class="icon fa fa-code-fork"></span><span
                        class="title">M3</span></button>
        </div>
        <div class="col-sm-3">
            <button id="page10" class="btn btn-xl btn-block"><span class="icon fa fa-wrench"></span><span class="title">Updates</span>
            </button>
        </div>
        <div class="col-sm-3">
            <button id="page11" class="btn btn-xl btn-block"><span class="icon fa fa-exclamation-triangle"></span><span
                        class="title">DERDACK</span></button>
        </div>
        <div class="col-sm-3">
            <button id="page12" class="btn btn-xl btn-block"><span class="icon fa fa-puzzle-piece"></span><span
                        class="title">Projektplan</span></button>
        </div>
    </div>
    <div class="row">
        <div class="col-sm-4 form-group">
            <label class="col-sm-6 control-label vcenter" for="funmode_checkbox">FUN-Mode</label>
            <input class="col-sm-6 checkbox" type="checkbox" id="funmode_checkbox" data-toggle="toggle">
        </div>

        <div class="col-sm-6 form-group">
            <select id="radiostations" class="form-control"></select>
        </div>
    </div>
    <div class="row">
        <div class="col-sm-4 form-group">
            <label class="col-sm-6 control-label vcenter" for="cycle_checkbox">Seitenwechsel</label>
            <input class="col-sm-6 checkbox" id="cycle_checkbox" type="checkbox" data-toggle="toggle">
        </div>
        <div class="col-sm-4 form-group">
            <input id="volume" data-slider-id='volumeSlider' type="text" data-slider-min="1" data-slider-max="100"
                   data-slider-step="1" data-slider-value="14"/>
        </div>
    </div>
    <div class="row">
        <div id="secret" class="col-sm-4">
            <label class="col-sm-6 control-label vcenter" for="radio_checkbox">Radio</label>
            <input class="col-sm-6 checkbox" id="radio_checkbox" type="checkbox" data-toggle="toggle">
        </div>
        <div class="col-sm-4">

        </div>
    </div>
    <div id="busdiv">
        <img id="bus" class="flip" src="./img/bus.gif">
    </div>
    <div id="stopdiv">
        <img id="stop" class="img-responsive" src="./img/stop.png">
    </div>
</div>
</body>


<script src="../monitor/lib/js/jquery.min.js"></script>
<script src="../monitor/js/bootstrap-progressbar.min.js"></script>
<script src="../monitor/lib/js/bootstrap-switch.min.js"></script>
<script src="../monitor/lib/js/bootstrap-slider.min.js"></script>
<script src="./app.js?random=<?php echo uniqid(); ?>"></script>