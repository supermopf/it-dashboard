# IT Dashboard - AI Coding Agent Guide

## Project Overview
This is a PHP-based IT infrastructure monitoring dashboard with real-time WebSocket communication, cycling page views, and REST API for toast notifications. The system displays IT metrics (temperature, helpdesk, performance, backups, etc.) and includes fun features like memes, radio streaming, and YouTube integration.

## Architecture

### Core Components
- **`/monitor`**: Main dashboard display with auto-cycling pages (primary UI)
- **`/applications`**: Separate application-specific views (PDM, Mobimedia, ERP)
- **`/control`**: Tablet-based remote control interface for dashboard operations
- **`/websocket`**: WebSocket server (`server.php`) and REST API (`api.php`)

### Data Flow
1. **Page Rendering**: `monitor/source/pageN.php` → cached by `rendercache.php` → served from `monitor/cache/`
2. **Real-time Updates**: WebSocket server (`websocket/server.php`) broadcasts to all connected clients
3. **External Data**: PowerShell scripts populate SQL Server databases → PHP pages query for display

### WebSocket Architecture
- Server runs on port 9000 via `php websocket/server.php`
- Handles page switching, radio control, toast notifications, and special modes (FUN mode, wheel, etc.)
- REST API at `/websocket/api.php` accepts POST requests for toast messages
- WebSocket commands sent from `control/` interface or monitor's JavaScript

## Configuration

**Critical**: Copy `config.template.php` to `config.php` and configure:
- SQL Server instances (DASHBOARD_SQL_INSTANCE, SCCM_SQL_INSTANCE, etc.)
- LDAP credentials for Active Directory
- API keys (openweathermap)
- WebSocket URL and domain settings
- Default radio station URL

**Note**: `config.php` is gitignored - never commit credentials.

## Database Patterns

All database connections use `sqlsrv_*` functions (Microsoft SQL Server):
```php
$conn = sqlsrv_connect(DASHBOARD_SQL_INSTANCE, $Default_Connection);
$result = sqlsrv_query($conn, $query);
while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) { ... }
```

Data is queried in `monitor/source/pageN.php` files and rendered with Chart.js for visualization.

## Development Workflows

### Adding New Dashboard Pages
1. Create `monitor/source/pageX.php` (query data, render HTML/charts)
2. Add page reference to `monitor/rendercache.php` array
3. Update `monitor/index.php` navigation with new menu item
4. Add button handler in `monitor/js/app.js` (`ButtonPage(X)`)
5. Run caching: `php monitor/rendercache.php` (setup as cron job in production)

### Testing WebSocket Changes
1. Start server: `php websocket/server.php`
2. Open browser to `monitor/index.php` or `control/index.php`
3. Watch terminal for debug logs (`LogDebug()` and `LogNormal()` output)
4. Debug level controlled by `DebugLogLevel` constant (1=important, 3=verbose)

### Toast Notification API
POST to `/websocket/api.php`:
```php
ToastSubject, ToastBody, ToastPicture (URL), ToastColor (#FA2A00), 
ToastTime (ms), ToastVolume (0-1), ToastHistory (bool)
```

## Caching System

**Important**: Production uses cached pages for performance
- Source files: `monitor/source/pageN.php` (edit these)
- Cached files: `monitor/cache/pageN.php` (auto-generated, don't edit)
- Regenerate cache: `php monitor/rendercache.php` (set up as cronjob)
- Debug mode: `LoadPage(N, src, debug=true)` loads from source directly

## Special Features

### FUN Mode
Activated via WebSocket command - overlays memes and visual effects on all pages. Check `monitor/js/app.js` for `FUN` variable handling.

### Radio Integration
- Streaming managed by WebSocket server
- Station list in database, icons cached in `websocket/radioicons/`
- Volume control and song title display via WebSocket messages

### Remote Control
`control/index.php` provides tablet interface to:
- Switch pages manually
- Control radio/YouTube
- Toggle FUN mode, wheel, snow effects
- Adjust cycle timing (default 30 seconds)

## Code Conventions

- **PHP Style**: Mixed procedural + minimal OOP, defined constants in config
- **JavaScript**: Global variables (`websocket`, `Page_AJAX`, etc.) in `monitor/js/app.js`
- **AJAX Pattern**: Pages load via `LoadPage(N)` or `ButtonPage(N)` functions
- **CSS Themes**: Located in `monitor/css/themes/` (flat-blue.css, darkmode.css, etc.)
- **Bootstrap 3**: UI framework throughout (panels, buttons, grids)

## File Naming Patterns

- `pageN.php`: Numbered dashboard pages (1-12+)
- `pageFUN.php`, `pageWHEEL.php`, `pageYT.php`: Special mode pages
- Image cache: `websocket/imgcache/<md5>.<extension>` (PHP files contain image data)

## Dependencies

- PHP with `sqlsrv` extension (Microsoft SQL Server)
- WebSocket library (custom implementation in `websocket/server.php`)
- Chart.js for data visualization
- Bootstrap 3 + Font Awesome 5
- jQuery for AJAX and DOM manipulation

## Common Gotchas

- WebSocket server must be running for dashboard to function
- Page changes won't persist without cache regeneration
- SQL Server connection strings vary (DASHBOARD_SQL_INSTANCE vs SCCM_SQL_INSTANCE)
- Image cache uses `.php` extension for security (served via PHP)
