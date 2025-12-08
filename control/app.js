var sliding = false;
var ImageMatch = new Array ();

$("#busdiv").hide();

$(document).ready(function () {

    dropdown = $('#radiostations');
    dropdown.empty();
    dropdown.prop('selectedIndex', 0);

    url = DASHBOARD_CONFIG.DASHBOARD_BASE_URL + '/websocket/streams.php';

    // Populate dropdown with list of provinces
    $.getJSON(url, function (data) {
        $.each(data, function (key, entry) {
            ImageMatch[entry["RadioURL"]] = entry["RadioImage"];
            dropdown.append($('<option></option>').attr('value', entry["RadioURL"]).text(entry["RadioName"]));
        })
    });


    $(document).bind('touchmove', false);
    $(".checkbox").bootstrapSwitch();
    $('#radio_checkbox').bootstrapSwitch('state');
    $("#funmode_checkbox").bootstrapSwitch('state', false, true);
    //create a new WebSocket object.
    var wsUri = DASHBOARD_CONFIG.WEBSOCKET_URL;
    websocket = new WebSocket(wsUri);

    websocket.onopen = function (ev) { // connection is open
        var msg = {
            message: '!reg [Control]'
        };
        websocket.send(JSON.stringify(msg));
    };
	
    $('#spinbtn').bind("click touchstart", function () { //use clicks message send button
        //prepare json data
        var msg = {
            message: '!spin'
        };
        //convert and send data to server
        websocket.send(JSON.stringify(msg));
    });


    $('#page1').bind("click touchstart", function () { //use clicks message send button
        //prepare json data
        var msg = {
            message: '!fpage 1'
        };
        //convert and send data to server
        websocket.send(JSON.stringify(msg));
    });
    $('#page2').bind("click touchstart", function () { //use clicks message send button
        //prepare json data
        var msg = {
            message: '!fpage 2'
        };
        //convert and send data to server
        websocket.send(JSON.stringify(msg));
    });
    $('#page3').bind("click touchstart", function () { //use clicks message send button
        //prepare json data
        var msg = {
            message: '!fpage 3'
        };
        //convert and send data to server
        websocket.send(JSON.stringify(msg));
    });
    $('#page4').bind("click touchstart", function () { //use clicks message send button
        //prepare json data
        var msg = {
            message: '!fpage 4'
        };
        //convert and send data to server
        websocket.send(JSON.stringify(msg));
    });
    $('#page5').bind("click touchstart", function () { //use clicks message send button
        //prepare json data
        var msg = {
            message: '!fpage 5'
        };
        //convert and send data to server
        websocket.send(JSON.stringify(msg));
    });
    $('#page6').bind("click touchstart", function () { //use clicks message send button
        //prepare json data
        var msg = {
            message: '!fpage 6'
        };
        //convert and send data to server
        websocket.send(JSON.stringify(msg));
    });
    $('#page7').bind("click touchstart", function () { //use clicks message send button
        //prepare json data
        var msg = {
            message: '!fpage 7'
        };
        //convert and send data to server
        websocket.send(JSON.stringify(msg));
    });
    $('#page8').bind("click touchstart", function () { //use clicks message send button
        //prepare json data
        var msg = {
            message: '!fpage 8'
        };
        //convert and send data to server
        websocket.send(JSON.stringify(msg));
    });
    $('#page9').bind("click touchstart", function () { //use clicks message send button
        //prepare json data
        var msg = {
            message: '!fpage 9'
        };
        //convert and send data to server
        websocket.send(JSON.stringify(msg));
    });
    $('#page10').bind("click touchstart", function () { //use clicks message send button
        //prepare json data
        var msg = {
            message: '!fpage 10'
        };
        //convert and send data to server
        websocket.send(JSON.stringify(msg));
    });
    $('#page11').bind("click touchstart", function () { //use clicks message send button
        //prepare json data
        var msg = {
            message: '!fpage 11'
        };
        //convert and send data to server
        websocket.send(JSON.stringify(msg));
    });
    $('#page12').bind("click touchstart", function () { //use clicks message send button
        //prepare json data
        var msg = {
            message: '!fpage 12'
        };
        //convert and send data to server
        websocket.send(JSON.stringify(msg));
    });
    $('#titlebtn').bind("click touchstart", function () { //use clicks message send button
        //prepare json data
        var msg = {
            message: '!songtitle now'
        };
        //convert and send data to server
        websocket.send(JSON.stringify(msg));
    });

    $("#volume").slider({
        scale: 'logarithmic',
        step: 1,
        min: 1,
        max: 100,
        tooltip: "hide"
    });
    $('#volume').on("click touchstart slideStop", function (sliderValue) {
        sliding = false;
        var volume = sliderValue.value / 100;
        var msg = {
            message: '!var Radiovolume ' + volume
        };
        websocket.send(JSON.stringify(msg));
    });

    $('#volume').on("slideStart", function (sliderValue) {
        sliding = true;
    });
	
	$('#wheel_checkbox').on('switchChange.bootstrapSwitch', function () {
        if ($('#wheel_checkbox').bootstrapSwitch('state')) {
            var msg = {
                message: '!var Wheel True'
            };
        } else {
            var msg = {
                message: '!var Wheel False'
            };
        }
        //convert and send data to server
        console.log(msg);
        websocket.send(JSON.stringify(msg));
    });


    $('#cycle_checkbox').on('switchChange.bootstrapSwitch', function () {
        if ($('#cycle_checkbox').bootstrapSwitch('state')) {
            var msg = {
                message: '!var Cycle True'
            };
        } else {
            var msg = {
                message: '!var Cycle False'
            };
        }
        //convert and send data to server
        console.log(msg);
        websocket.send(JSON.stringify(msg));
    });

    $('#radio_checkbox').on('switchChange.bootstrapSwitch', function () {
        if ($('#radio_checkbox').bootstrapSwitch('state')) {
            var msg = {
                message: '!var Radio True'
            };
        } else {
            var msg = {
                message: '!var Radio False'
            };
        }
        //convert and send data to server
        console.log(msg);
        websocket.send(JSON.stringify(msg));
    });

    $('#radiostations').on('change', function (e) {
        var valueSelected = this.value;
        var msg = {
            message: '!var Radiostation ' + valueSelected
        };
        websocket.send(JSON.stringify(msg));
        var msg = {
            message: '!var RadioStationIcon ' + ImageMatch[valueSelected]
        };
        websocket.send(JSON.stringify(msg));
    });

    $('#funmode_checkbox').on('switchChange.bootstrapSwitch', function () {
        if ($('#funmode_checkbox').bootstrapSwitch('state')) {
            var msg = {
                message: '!var FUN True'
            };
        } else {
            var msg = {
                message: '!var FUN False'
            };
        }
        //convert and send data to server
        console.log(msg);
        websocket.send(JSON.stringify(msg));
    });

    $('#stop').bind("click touchstart", function () {
        var msg = {
            message: '!bus start'
        };
        websocket.send(JSON.stringify(msg));
    });

    $('#stop').on("contextmenu", function(evt) {
        evt.preventDefault();
        var msg = {
            message: '!bus end'
        };
        websocket.send(JSON.stringify(msg));
    });

    $('.progress').on("contextmenu", function(evt) {
        evt.preventDefault();
        var msg = {
            message: '!fpage 1'
        };
        websocket.send(JSON.stringify(msg));
        var msg = {
            message: '!var Cat True'
        };
        websocket.send(JSON.stringify(msg));
    });


    //#### Message received from server?
    websocket.onmessage = function (ev) {
        var msg = JSON.parse(ev.data); //PHP sends Json data
        var type = msg.type; //message type
        var message = msg.message; //message text

        if (type === 'command') {
            var command = message.substring(0, message.indexOf(' '));
            //console.log("Befehl erhalten: "+message);
            switch (command) {
                case "!page":
                    message = message.replace("!page ", "");
                    $("#content-container>div>div>button.btn-primary").removeClass("btn-primary");
                    $("#page" + message).addClass("btn-primary");
                    break;
                case "!bus":
                    if (message.replace("!bus ", "") === "middle") {
                        $('#busdiv').show();
                        var now = new Date();
                        var hour = now.getHours();
                        if (hour >= 10 && hour < 12) {
                            $('#busdiv').html('<img id="bus" src="./img/broetchen.png">');
                        } else {
                            $('#busdiv').html('<img id="bus" class="flip" src="./img/bus.gif">');
                        }
                        $("#bus").animate({left: '90%'}, 8500, function () {
                            if (hour >= 10 && hour < 12) {
                                var audio = new Audio('./res/hupe.wav');
                                audio.play();
                            }
                            $("#bus").toggleClass("flip").delay(3000);
                            $("#bus").animate({left: '0%'}, 8500, function () {
                                //Websocket
                                var msg = {
                                    message: '!bus end'
                                };
                                websocket.send(JSON.stringify(msg));
                            });
                        });
                    }
                    break;
                default:
                    console.log("Befehl nicht gefunden: " + message);
            }
        }
        if (type === 'update') {
            $("#content-container>div>div>button.btn-primary").removeClass("btn-primary");
            $("#page" + msg.Page).addClass("btn-primary");

            if (JSON.parse($("#funmode_checkbox").bootstrapSwitch('state')) !== JSON.parse(msg.FUN)) {
                $("#funmode_checkbox").bootstrapSwitch('state', JSON.parse(msg.FUN), true);
            }
            if (JSON.parse($("#cycle_checkbox").bootstrapSwitch('state')) !== JSON.parse(msg.Cycle)) {
                $("#cycle_checkbox").bootstrapSwitch('state', JSON.parse(msg.Cycle), true);
            }
            if (JSON.parse($("#wheel_checkbox").bootstrapSwitch('state')) !== JSON.parse(msg.Wheel)) {
                $("#wheel_checkbox").bootstrapSwitch('state', JSON.parse(msg.Cycle), true);
            }
            if (JSON.parse($("#radio_checkbox").bootstrapSwitch('state')) !== JSON.parse(msg.Radio)) {
                $("#radio_checkbox").bootstrapSwitch('state', JSON.parse(msg.Radio), true);
            }
            $('#timer').attr('data-transitiongoal', msg.CD);
            $('#timer').progressbar();

            $('#radiostations option[value="' + msg.Radiostation + '"]').prop('selected', true);
            if (!sliding) {
                $('#volume').slider('setValue', msg.Radiovolume * 100)
            }


        }
        if (type === 'console') {
            console.log(message);
        }
        if (type === 'auth') {
            msg = {
                message: '!reg [Control]'
            };
            websocket.send(JSON.stringify(msg));
        }
    };

    websocket.onerror = function (ev) {
        console.log("Error Occurred - " + ev.data);
        location.reload();
    };
    websocket.onclose = function (ev) {
        console.log("Connection Closed");
        location.reload();
    };


});