<?php
$url = $_REQUEST["src"];
parse_str(parse_url($url, PHP_URL_QUERY), $yturl);

?>
<div class="row">
    <div class="col-lg-10 col-lg-offset-1">
        <div class="embed-responsive embed-responsive-16by9">
            <div id='player'></div>
        </div>
    </div>
</div>

<script>
    YTRunning = true;
    $(document).ready(function () {
        console.log("ready!");
        loadPlayer();
    });

    function getArtistId() {
        return '<?php echo $yturl['v']; ?>';
    }

    function loadPlayer() {
        if (typeof(YT) == 'undefined' || typeof(YT.Player) == 'undefined') {

            var tag = document.createElement('script');
            tag.src = "https://www.youtube.com/iframe_api";
            var firstScriptTag = document.getElementsByTagName('script')[0];
            firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);

            window.onYouTubePlayerAPIReady = function () {
                onYouTubePlayer();
            };

        } else {

            onYouTubePlayer();

        }
    }

    var player;

    function onYouTubePlayer() {
        player = new YT.Player('player', {
            height: '450',
            width: '880',
            videoId: getArtistId(),
            playerVars: {autoplay: 1, controls: 1, showinfo: 0, rel: 0, showsearch: 0, iv_load_policy: 3},
            events: {
                'onStateChange': onPlayerStateChange,
                'onReady': onPlayerReady,
                'onError': catchError
            }
        });
    }

    function onPlayerStateChange(event) {
        console.log(event.data);
        if (event.data == YT.PlayerState.ENDED) {
            YTRunning = false;
            LoadPage(CurrentPage);
        }
    }

    function onPlayerReady(event) {
        player.setVolume(volume * 100);
        event.target.playVideo();
    }


    function catchError(event) {
        if (event.data == 100) {
            //DONE
            var msg = {
                message: "!var YT False"
            };
            //convert and send data to server
            websocket.send(JSON.stringify(msg));
            console.log("Video existiert nicht mehr");
        }
    }


    function stopVideo() {
        player.stopVideo();
    }
</script>