<?php
require_once('db_helper.php');
$db = new ToastDB();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 50; // Load only 50 at a time instead of all
$offset = ($page - 1) * $perPage;

$history = $db->getHistory($perPage, $offset);
$totalCount = $db->getHistoryCount();
$totalPages = ceil($totalCount / $perPage);
?>
<!DOCTYPE html>
<html>
<head>
    <title>IT Dashboard - History</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href='https://fonts.googleapis.com/css?family=Roboto+Condensed:300,400' rel='stylesheet' type='text/css'>
    <link href='https://fonts.googleapis.com/css?family=Lato:300,400,700,900' rel='stylesheet' type='text/css'>
    <link rel="stylesheet" type="text/css" href="../monitor/lib/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css">
    <link rel="stylesheet" type="text/css" href="./css/style.css">
    <style>
        table { table-layout:fixed; width:100%; }
        .pagination { margin: 20px 0; }
    </style>
</head>
<body>

<?php 
$active_page = 'history';
$show_reload_btn = true;
include('navbar.php'); 
?>

<div class="container-fluid">
    <div class="row" style="margin-top: 80px;">
    
    <div class="row" style="margin-top: 5%;">
        <div class="col-lg-12">
            <div class="alert alert-info">
                Zeige <?= count($history) ?> von <?= $totalCount ?> Einträgen (Seite <?= $page ?> von <?= $totalPages ?>)
            </div>
            
            <!-- Pagination Top -->
            <?php if ($totalPages > 1): ?>
            <nav>
                <ul class="pagination">
                    <?php if ($page > 1): ?>
                    <li><a href="?page=<?= $page-1 ?>">&laquo; Zurück</a></li>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page-5); $i <= min($totalPages, $page+5); $i++): ?>
                    <li class="<?= $i == $page ? 'active' : '' ?>">
                        <a href="?page=<?= $i ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                    <li><a href="?page=<?= $page+1 ?>">Weiter &raquo;</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
            
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th class='col-md-1'>ToastSubject</th>
                        <th class='col-md-3'>ToastPicture</th>
                        <th>JSON</th>
                        <th class='col-md-2'>Aktion</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($history as $row): 
                    $json = json_decode($row->raw_json);
                ?>
                    <tr>
                        <td><?= htmlspecialchars($row->toast_subject ?? '') ?></td>
                        <td>
                            <?php if ($row->toast_picture): ?>
                                <?php if (strpos($row->toast_picture, 'mp4') !== false || strpos($row->toast_picture, 'webm') !== false): ?>
                                    <video controls style="height: 200px;display:block; margin:0 auto;max-width:300px" src="<?= htmlspecialchars($row->toast_picture) ?>"></video>
                                <?php else: ?>
                                    <img alt="ToastPicture" style="height: 200px;display:block; margin:0 auto;max-width:300px" src="<?= htmlspecialchars($row->toast_picture) ?>" />
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td><pre class="pre-scrollable" data-id="<?= $row->id ?>"><?= json_encode($json, JSON_PRETTY_PRINT) ?></pre></td>
                        <td>
                            <div class="btn-group">
                                <button class="btn btn-primary repeat" data-id="<?= $row->id ?>">Wiederholen</button>
                                <button class="btn btn-danger save" data-id="<?= $row->id ?>">Speichern</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Pagination Bottom -->
            <?php if ($totalPages > 1): ?>
            <nav>
                <ul class="pagination">
                    <?php if ($page > 1): ?>
                    <li><a href="?page=<?= $page-1 ?>">&laquo; Zurück</a></li>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page-5); $i <= min($totalPages, $page+5); $i++): ?>
                    <li class="<?= $i == $page ? 'active' : '' ?>">
                        <a href="?page=<?= $i ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                    <li><a href="?page=<?= $page+1 ?>">Weiter &raquo;</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="//ajax.googleapis.com/ajax/libs/jquery/2.0.0/jquery.min.js"></script>
<script>
    $('.repeat').click(function () {
        var id = $(this).data('id');
        var json = JSON.parse($('pre[data-id="' + id + '"]').text());
        $.ajax({
            url: 'api.php',
            type: 'post',
            data: json,
            success: function (data) {
                alert('Toast wiederholt!');
            }
        });
    });
    
    $('#reload').click(function () {
        $.ajax({
            url: 'api.php',
            type: 'post',
            data: {
                ToastBody: "<script>location.reload()<\/script>",
                ToastHistory: "false"
            }
        });
    });
    
    $('.save').click(function () {
        var id = $(this).data('id');
        var json = JSON.parse($('pre[data-id="' + id + '"]').text());
        $.ajax({
            url: 'best_new.php?action=save',
            type: 'post',
            data: json,
            success: function (data) {
                alert('Zu Favoriten hinzugefügt!');
            }
        });
    });
</script>
</body>
</html>
