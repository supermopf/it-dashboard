/**
 * Created by victor.lange on 05.04.2016.
 */
var Page_AJAX;

$(document).ready(function () {
    $.ajaxSetup({cache: false});
});
//Vars
//Anzahl der Seiten
var PageCount = $("#navigation li").length;
var Cycle = false;
var FUN = false;


function LoadPage(pagenumber, src) {
    //Loading Animation
    $("#ajax").html('<h1 class="text-center"><i class="fa fa-cog fa-spin fa-3x fa-fw margin-bottom"></i></h1>');
    //Seite laden
    Page_AJAX = $.ajax({
        async: true,
        type: "GET",
        data: {src: src},
        url: "./ajax/page" + pagenumber + ".php",
        success: function (result) {
            //active entfernen
            $("#navigation>li.active").removeClass("active");
            //Ein neuen active setzen
            $("#navigation li:nth-child(" + pagenumber + ")").addClass("active");
            $("#ajax").html(result);
            $('#ajax').animateCss("bounceInUp");
        }
    });
}