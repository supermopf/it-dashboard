var websocket;
var wsUri = DASHBOARD_CONFIG.WEBSOCKET_URL;
var SnowActive = false;
var catstart = true;
var radio_running = false;
var radio_station = "https://ndr-ndr1niedersachsen-hannover.cast.addradio.de/ndr/ndr1niedersachsen/hannover/mp3/128/stream.mp3";
var radio = new Audio(radio_station);
var volume;
var YTvolume;
var Page_AJAX;
var Freeze = false;
var PageCount = $("#navigation li").length;
var Cycle = false;
var FUN = false;
var CurrentPage = 0;
var rnd = 1;
var YTRunning = false;
var SongTitle = "";
var ClosingTimeout;



function CyclePages() {
    if (i === 0) {
        $('#slider1').show();
        i++;
    } else {
        if (PageCount > 1) {
            $('#slider' + i).hide();
            if (i >= PageCount) {
                i = 1;
            }
            else {
                i++;
            }
            $('#slider' + i).show();
            $('#slider' + i).animateCss('bounceInRight');
        }
    }
    $('#CurrentPage').html(i);
}

function LoadPage(pagenumber, src, debug=false) {
    rnd = Math.floor((Math.random() * 5) + 1);
    if(debug){
        folder = 'source'
    }else{
        folder = 'cache'
    }
    if (!Freeze || debug) {
        if (!YTRunning) {
            //Seite laden
            Page_AJAX = $.ajax({
                async: true,
                type: "GET",
                data: {src: src},
                url: "./"+ folder +"/page" + pagenumber + ".php",
                success: function (result) {
                    //active entfernen
                    $("#navigation>li.active").removeClass("active");

                    if (pagenumber != "FUN" && pagenumber != "YT" && pagenumber != "RELOAD" && pagenumber != "URGENT") {
                        CurrentPage = pagenumber;
                        //Ein neuen active setzen
                        $("#navigation li:nth-child(" + pagenumber + ")").addClass("active");
                        $('#Pageheader').html($("#navigation li:nth-child(" + pagenumber + ")>a>.title").html());
                        $('#Pageheader').animateCss("fadeIn");
                    }
                    $("#ajax").html(result);
                    $('#ajax').animateCss("bounceInUp");
                }
            });
        }
    }
}


function getURLParameter(name) {
    var value = decodeURIComponent((RegExp(name + '=' + '(.+?)(&|$)').exec(location.search) || [, ""])[1]);
    return (value !== 'null') ? value : false;
}

$(function () {
    $(".navbar-expand-toggle").click(function () {
        $(".app-container").toggleClass("expanded");
        return $(".navbar-expand-toggle").toggleClass("fa-rotate-90");
    });
    return $(".navbar-right-expand-toggle").click(function () {
        $(".navbar-right").toggleClass("expanded");
        return $(".navbar-right-expand-toggle").toggleClass("fa-rotate-90");
    });
});
$(function () {
    return $('.toggle-checkbox').bootstrapSwitch({
        size: "small"
    });
});
$(function () {
    return $('.match-height').matchHeight();
});
$(function () {
    return $(".side-menu .nav .dropdown").on('show.bs.collapse', function () {
        return $(".side-menu .nav .dropdown .collapse").collapse('hide');
    });
});

function startTime() {
    var today = new Date();
    var h = today.getHours();
    var m = today.getMinutes();
    var s = today.getSeconds();
    m = checkTime(m);
    s = checkTime(s);
    document.getElementById('clock').innerHTML =
        h + ":" + m + ":" + s;
    var t = setTimeout(startTime, 500);
}

function checkTime(i) {
    if (i < 10) {
        i = "0" + i
    }
    // add zero in front of numbers < 10
    return i;
}

function ButtonPage(page) {
    var msg = {
        message: "!fpage " + page
    };
    //convert and send data to server
    websocket.send(JSON.stringify(msg));
}


$(document).ready(function () {
    startTime();
    $.ajaxSetup({cache: false});
    websocket = new WebSocket(wsUri);

    websocket.onopen = function (ev) {
        console.log("Verbunden!");
        var msg = {
            message: '!repeat'
        };
        websocket.send(JSON.stringify(msg));

        var name = getURLParameter('Name');

        msg = {
            message: '!reg [Dashboard]' + name
        };
        websocket.send(JSON.stringify(msg));
    };


    websocket.onmessage = function (ev) {
        var msg = JSON.parse(ev.data); //PHP sends Json data
        var type = msg.type; //message type
        var message = msg.message; //message text

        if (type == 'command') {
            var command = message.substring(0, message.indexOf(' '));
            // console.log("Befehl erhalten: "+message);
            switch (command) {
                case "!video":
                    if (getURLParameter('Name') == "Main") {
                        message = message.replace("!video ", "");
                        LoadPage("YT", message);
                    }
                    break;
                case "!bus":
                    if (getURLParameter('Name') == "Main") {
                        message = message.replace("!bus ", "");
                        switch (message) {
                            case "start":
                                if (BusActive === "false") {
                                    BusActive = true;
                                    console.log("BUS START");
                                    msg = {
                                        message: '!var BusActive True'
                                    };
                                    websocket.send(JSON.stringify(msg));

                                    var now = new Date();
                                    var hour = now.getHours();
                                    if (hour >= 10 && hour < 12) {
                                        $('#buscontainer').html('<img id="bus" src="./img/broetchen.png">');
                                    } else {
                                        $('#buscontainer').html('<img id="bus" class="flip" src="./img/bus.gif">');
                                    }

                                    $('#buscontainer').show();
                                    $("#bus").animate({left: '100%'}, 8500, function () {
                                        $("#bus").toggleClass("flip");
                                        //Websocket
                                        msg = {
                                            message: '!bus middle'
                                        };
                                        websocket.send(JSON.stringify(msg));
                                    });
                                } else {
                                    console.log("BUS IS BUSY");
                                }
                                break;
                            case "middle":

                                break;
                            case "end":
                                $("#bus").animate({left: '0%'}, 8500, function () {
                                    $("#bus").addClass("flip");
                                    $('#buscontainer').hide();
                                    msg = {
                                        message: '!var BusActive False'
                                    };
                                    websocket.send(JSON.stringify(msg));
                                });
                                break;
                        }
                    }
                    break;
                case "!page":
                    if (typeof Page_AJAX !== 'undefined') {
                        console.log("Killed AJAX");
                        Page_AJAX.abort();
                    }
                    message = message.replace("!page ", "");
                    if (message === "FUN") {
                        if (getURLParameter('Name') === "Main") {
                            LoadPage(message);
                        }
                    } else {
                        LoadPage(message);
                    }
                    break;
                case "!songtitle":
                    show_toast();
                    break;
                case "!urgent":
                    var audio;
                    if (getURLParameter('Name') === "Main") {
                        message = JSON.parse(message.replace("!urgent ", ""));
                        if (message.ToastColor !== undefined && message.ToastColor !== "") {
                            $(".modal-header").css('background-color', message.ToastColor);
                        } else {
                            $(".modal-header").css('background-color', '#FA2A00');
                        }
                        if (message.ToastTextColor !== undefined && message.ToastTextColor !== "") {
                            $(".modal-header").css('color', message.ToastTextColor);
                        } else {
                            $(".modal-header").css('color', '#fff');
                        }
                        if (message.ToastSubject !== undefined && message.ToastSubject !== "") {
                            $(".modal-title").html(message.ToastSubject);
                        } else {
                            $(".modal-title").html("");
                        }
                        if (message.ToastBody !== undefined && message.ToastBody !== "") {
                            $("#modalbodytext").html(message.ToastBody);
                        } else {
                            $("#modalbodytext").html("");
                        }

                        if (message.ToastPicture !== undefined && message.ToastPicture !== "") {
                            if ((message.ToastPicture).includes('mp4') || (message.ToastPicture).includes('webm')) {
                                if(message.ToastVideoNoRepeat !== undefined && message.ToastVideoNoRepeat == "true"){
                                    $("#modalimage").html('<div align="center" class="embed-responsive embed-responsive-16by9"><video onplay="clearTimeout(ClosingTimeout)" onended="VideoOnEnded(ClosingTimeout)" autoplay class="embed-responsive-item" src="' + message.ToastPicture + '?nocache=' + Math.floor(Math.random() * 100) + '"></video></div>');
                                }else{
                                    $("#modalimage").html('<div align="center" class="embed-responsive embed-responsive-16by9"><video loop autoplay class="embed-responsive-item" src="' + message.ToastPicture + '?nocache=' + Math.floor(Math.random() * 100) + '"></video></div>');
                                }


                            } else {
                                $("#modalimage").html('<img src="' + message.ToastPicture + '?nocache=' + Math.floor(Math.random() * 100) + '" class="img-responsive" style="max-height: calc(100vh - 200px)" alt="">');
                            }
                        } else {
                            $("#modalimage").html("");
                        }

                        if (message.ToastSound !== undefined && message.ToastSound !== "") {
                            audio = new Audio(message.ToastSound);
                        } else {
                            audio = new Audio('./res/alert.mp3');
                        }

                        if (message.ToastVolume !== undefined && message.ToastVolume !== "") {
                            audio.volume = message.ToastVolume;
                        } else {
                            audio.volume = 0.5;
                        }
                        audio.play();

                        $('#myModal').modal('show');

                        if (message.ToastTime !== undefined && message.ToastTime !== "") {
                            ClosingTimeout = setTimeout(function () {
                                $('#myModal').modal('hide');
                                $("#modalimage").html('');
                            }, message.ToastTime);
                        } else {
                            ClosingTimeout = setTimeout(function () {
                                $('#myModal').modal('hide');
                                $("#modalimage").html('');
                            }, 30000);
                        }
                    }
                    break;
                default:
                    console.log("Befehl nicht gefunden: " + message);
            }
        }
        if (type === 'update') {
            $('#loadingbar').width((msg.CD / 30 * 100) + '%');
            $('#cat').width((msg.CD / 30 * 100 + 5) + '%');
            if (msg.Cat === "true") {
                if (msg.Page == 9) {
                    $('#cat').html('<img id="busimg" src="./img/bus.gif">');
                } else {
                    switch (rnd) {
                        case 1:
                            if (catstart && getURLParameter('Name') === "Main") {
                                $('#cat').html('<img id="catimg" src="./img/cat.gif">');
                                catstart = false;
                                var catsound = new Audio('./res/meow.mp3');
                                catsound.load();
                                catsound.volume = 0.1;
                                catsound.play();
                            }

                            break;
                        case 2:
                            if (catstart && getURLParameter('Name') === "Main") {
                                $('#cat').html('<img id="dogimg" src="./img/dog.gif">');
                                catstart = false;
                                var dogsound = new Audio('./res/bark.mp3');
                                dogsound.load();
                                dogsound.volume = 0.4;
                                dogsound.play();
                            }
                            break;
                        case 3:
                            if (catstart && getURLParameter('Name') === "Main") {
                                $('#cat').html('<img id="dinoimg" src="./img/dino.gif">');
                                catstart = false;
                                var dinosound = new Audio('./res/bark.mp3');
                                dinosound.load();
                                dinosound.volume = 0.4;
                                //dinosound.play();
                            }
                            break;
                        case 4:
                            if (catstart && getURLParameter('Name') === "Main") {
                                $('#cat').html('<img id="dinoimg2" src="./img/dino2.gif">');
                                catstart = false;
                                var dinosound = new Audio('./res/bark.mp3');
                                dinosound.load();
                                dinosound.volume = 0.4;
                                //dinosound.play();
                            }
                            break;
                        case 5:
                            if (catstart && getURLParameter('Name') === "Main") {
                                $('#cat').html('<img id="dkimg" src="./img/dk.gif">');
                                catstart = false;
                                var dksound = new Audio('./res/bark.mp3');
                                dksound.load();
                                dksound.volume = 0.4;
                                //dinosound.play();
                            }
                            break;
                    }
                }
            } else {
                $('#cat').html("");
                catstart = true;
            }

            volume = msg.Radiovolume;
            YTvolume = msg.YTvolume;
            if (YTRunning) {
                player.setVolume(YTvolume * 100)
            }

            if (msg.SongTitle !== SongTitle && radio_running) {
                SongTitle = msg.SongTitle;
                launch_toast(msg.RadioStationIcon,msg.SongTitle)
            }

            if (msg.Radio === "true" && YTRunning === false && getURLParameter('Name') === "Main") {
                radio.volume = msg.Radiovolume;


                if (msg.Radiostation !== radio_station) {
                    radio_station = msg.Radiostation;
                    radio.setAttribute("src", msg.Radiostation);
                    radio.load();
                    radio.play();
                }
                if (radio_running === false) {
                    radio_running = true;
                    radio.setAttribute("src", msg.Radiostation);
                    radio.load();
                    radio.play();
                }
            } else {
                radio_running = false;
                radio.setAttribute("src", "");
            }

            BusActive = msg.BusActive;

            if (msg.Snow === "true") {
                if (SnowActive === false) {
                    console.log("Snow Enabled");
                    $(document).snowfall({round: true, minSize: 5, maxSize: 8, flakeCount: 150}); // add rounded
                    SnowActive = true;
                }
            } else {
                if (SnowActive === true) {
                    console.log("Snow Disabled");
                    $(document).snowfall('clear');
                    SnowActive = false;
                }
            }


            if (CurrentPage === "6" && (msg.CD % 10) === 0) {
                CyclePages();
            }
            if (msg.Cycle === "true") {
                $("#page-timer").html(msg.CD);
            } else {
                $("#page-timer").html("Deaktiviert");
            }
        }
        if (type === 'console') {
            console.log(message);
        }
        if (type === 'auth') {
            var name = getURLParameter('Name');

            msg = {
                message: '!reg [Dashboard]' + name
            };
            websocket.send(JSON.stringify(msg));
        }


    };

    websocket.onerror = function (ev) {
        console.log("Error Occurred - " + ev.data)
    };
    websocket.onclose = function (ev) {
        console.log("Connection Closed");
        window.location.reload();

    };
});

$.fn.extend({
    animateCss: function (animationName, callback) {
        var animationEnd = 'webkitAnimationEnd mozAnimationEnd MSAnimationEnd oanimationend animationend';
        this.addClass('animated ' + animationName).one(animationEnd, function () {
            $(this).removeClass('animated ' + animationName);
            if (callback) {
                callback();
            }
        });
        return this;
    }
});

function launch_toast(imgurl,description) {
    var x = document.getElementById("toast");
    x.className = "show";
    if(imgurl !== undefined) {
        $('#toastimg').html("<img src='" + imgurl + "'>");
    }else{
        $('#toastimg').html("");
    }
    $('#toastdesc').html(description);
    setTimeout(function(){ x.className = x.className.replace("show", "");}, 12000);
}
function show_toast(){
    var x = document.getElementById("toast");
    x.className = "show";
    setTimeout(function(){ x.className = x.className.replace("show", "");}, 12000);
}

function VideoOnEnded() {
    $('#myModal').modal('hide');
}