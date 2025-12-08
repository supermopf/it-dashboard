<?php
$url = $_REQUEST["src"];
$videoID = null; // Variable initialisieren

// 1. Prüfe auf das YouTube Shorts-Format: https://www.youtube.com/shorts/VIDEO_ID
// Der reguläre Ausdruck extrahiert die 11-stellige ID direkt aus dem Pfad.
if (preg_match('/youtube\.com\/shorts\/([a-zA-Z0-9_-]{11})/i', $url, $matches)) {
    $videoID = $matches[1];
}
// 2. Fallback auf das Standard-Format (watch?v=VIDEO_ID) und youtu.be/VIDEO_ID
else {
    // Versucht die ID über den Query-Parameter 'v' zu bekommen (Standard-URL)
    parse_str(parse_url($url, PHP_URL_QUERY), $yturl);
    
    if (isset($yturl['v'])) {
        $videoID = $yturl['v'];
    } 
    // Zusätzlicher Check für die Kurzform-URL youtu.be/VIDEO_ID
    else {
        $path = parse_url($url, PHP_URL_PATH);
        // Entfernt den führenden Schrägstrich, um die ID zu erhalten
        $potential_id = trim($path, '/');

        // Prüfen, ob es eine gültig aussehende 11-stellige YouTube ID ist
        if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $potential_id)) {
             $videoID = $potential_id;
        }
    }
}

if ($videoID) {
    //echo "Gefundene Video-ID: " . $videoID;
} else {
    echo "Keine gültige YouTube-Video-ID gefunden.";
}

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
        return '<?php echo $videoID; ?>';
    }
    function getStartSeconds() {
        return '<?php if(isset($yturl['t'])){echo $yturl['t'];}else{echo 0;}; ?>';
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
            playerVars: {start: getStartSeconds(),autoplay: 1, controls: 1, showinfo: 0, rel: 0, showsearch: 0, iv_load_policy: 3},
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
        YTRunning = false;
        LoadPage(CurrentPage);
    }


    function stopVideo() {
        player.stopVideo();
    }
</script>