# it-dashboard
![Dashboard Example](/example/weather.png?raw=true "Weather Page")

This is a Dashboard written in PHP.

You want to monitor your infrastructure and also share memes with your colleagues? 
Then this may be the right thing for you!

This application cycles through multiple PHP-pages and provides an REST-API for
important alerts or fun stuff.

The pages are easy to replace, so you can show your own performance data or whatever.

Most of the data in my pages are collected via a PowerShell Script,
which saves the data in a Database.

Features
========

* REST-API for ToastMessages
* Replaceable PHP-Pages
* Remote-Control Page for Tablets
* YouTube Support
* Internet Radio with Streaminfo
* Fun Mode (Every Page is filled with memes)
* Fun Stuff (e.g. A Bus driving through every screen)
* Caching
* WebSocket
* Adminpanel


Server
=======

To use the application, you need a Websocket.

The WebSocket is started by running: `/websocket/server.php`



REST-API
========

The API is reachable under: `/websocket/api.php`

|Parameter|Method|Required|String|Default|Description|
|--- |--- |--- |--- |--- |--- |
|ToastSubject|POST|No|String||Titele of Toast|
|ToastBody|POST|No|String||Text of Toast|
|ToastPicture|POST|No|String||Picture/Video of Toast|
|ToastColor|POST|No|String|#FA2A00|Color of Toast|
|ToastTextColor|POST|No|String|#FFFFFF|Color of Heading|
|ToastSound|POST|No|String|Win XP Error|URL of Sound|
|ToastTime|POST|No|Integer|30000|Time until Toast hides in ms|
|ToastVolume|POST|No|Float|0.5|Volume from 0 to 1|
|ToastHistory|POST|No|Boolean|true|If true, the Toast is saved in the History|
|ToastVideoNoRepeat|POST|No|Boolean|false|If true, the Toast is hidden, when the Video finished|

Caching
=======

To use the caching, you need to set up a cronjob which executes `/monitor/rendercache.php`

This Script gets all pages in the `/monitor/source` folder and saves them in `/monitor/cache`  
