<div id="picture-box">
    <div class="row">
        <div class="col-lg-2">
            <img class="img-responsive" src="./grafana/24.png?nocache=<?php echo time(); ?>"/>
        </div>
        <div class="col-lg-2">
            <img class="img-responsive" src="./grafana/25.png?nocache=<?php echo time(); ?>"/>
        </div>
        <div class="col-lg-2">
            <img class="img-responsive" src="./grafana/33.png?nocache=<?php echo time(); ?>"/>
        </div>
        <div class="col-lg-2">
            <img class="img-responsive" src="./grafana/22.png?nocache=<?php echo time(); ?>"/>
        </div>
        <div class="col-lg-2">
            <img class="img-responsive" src="./grafana/34.png?nocache=<?php echo time(); ?>"/>
        </div>
        <div class="col-lg-2">
            <img class="img-responsive" src="./grafana/35.png?nocache=<?php echo time(); ?>"/>
        </div>
    </div>
    <div class="row">
        <div class="col-lg-6">
            <img class="img-responsive" src="./grafana/37.png?nocache=<?php echo time(); ?>"/>
        </div>
        <div class="col-lg-6">
            <img class="img-responsive" src="./grafana/36.png?nocache=<?php echo time(); ?>"/>
        </div>
    </div>
    <div class="row">
        <div class="col-lg-3">
            <img class="img-responsive" src="./grafana/iops.png?nocache=<?php echo time(); ?>"/>
        </div>
        <div class="col-lg-3">
            <img class="img-responsive" src="./grafana/throughput.png?nocache=<?php echo time(); ?>"/>
        </div>
        <div class="col-lg-3">
            <img class="img-responsive" src="./grafana/readlat.png?nocache=<?php echo time(); ?>"/>
        </div>
        <div class="col-lg-3">
            <img class="img-responsive" src="./grafana/writelat.png?nocache=<?php echo time(); ?>"/>
        </div>
    </div>
</div>

<script>
    $(document).ready(function () {
        $(".img-responsive").on("error", function () {
            //$(this).attr('src', './images/nopicture.png');
            $("#picture-box").html('<h1 style="text-align: center">Grafana down ¯\\_(ツ)_/¯</h1>');
        });
    });
</script>

