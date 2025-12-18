<?php
/**
 * Shared Navigation Bar for WebSocket Admin Pages
 * Usage: include('navbar.php'); // Set $active_page before including
 */

// Set default if not defined
if (!isset($active_page)) {
    $active_page = '';
}

function isActive($page, $active_page) {
    return $page === $active_page ? 'active' : '';
}
?>

<nav class="navbar navbar-default navbar-fixed-top">
    <div class="container-fluid">
        <div class="navbar-header">
            <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
                <span class="sr-only">Toggle navigation</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>
            <a class="navbar-brand" href="#"><i class="fas fa-tachometer-alt"></i> IT-Dashboard</a>
        </div>
        <div id="navbar" class="navbar-collapse collapse">
            <ul class="nav navbar-nav">
                <li class="<?= isActive('history', $active_page) ?>"><a href="./history.php"><i class="fas fa-history"></i> History</a></li>
                <li class="<?= isActive('best', $active_page) ?>"><a href="./best.php"><i class="fas fa-star"></i> Best</a></li>
                <li class="<?= isActive('youtube', $active_page) ?>"><a href="./youtube.php"><i class="fab fa-youtube"></i> YouTube</a></li>
                <li class="<?= isActive('admin', $active_page) ?>"><a href="./index.php"><i class="fas fa-cog"></i> Adminpanel</a></li>
                <li class="<?= isActive('features', $active_page) ?>"><a href="./features.php"><i class="fas fa-lightbulb"></i> Feature Request</a></li>
                <li class="<?= isActive('julianometer', $active_page) ?>"><a href="./julianometer.php"><i class="fas fa-chart-line"></i> Julian-O-Meter</a></li>
                <li class="<?= isActive('newtoast', $active_page) ?>"><a href="./newtoast.php"><i class="fas fa-bell"></i> Neuer Toast</a></li>
                <li class="<?= isActive('logs', $active_page) ?>"><a href="./logs.php"><i class="fas fa-file-alt"></i> Logs</a></li>
            </ul>
            <ul class="nav navbar-nav navbar-right" style="margin-right: 1%;">
                <?php if (isset($show_ws_status) && $show_ws_status): ?>
                <li>
                    <p class="navbar-text">
                        <span class="ws-status disconnected" id="ws-indicator"></span>
                        <span id="ws-status-text" style="color: #e74c3c;">Nicht verbunden</span>
                    </p>
                </li>
                <?php endif; ?>
                <?php if (isset($show_reload_btn) && $show_reload_btn): ?>
                <li>
                    <p class="navbar-btn">
                        <button class="btn btn-warning" id="reload"><i class="fas fa-sync-alt"></i> Reload All</button>
                    </p>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
