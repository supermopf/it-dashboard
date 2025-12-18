<?php
require_once('db_helper.php');
$db = new ToastDB();

// Handle actions
if (isset($_GET["action"]) && $_GET["action"] == "save") {
    $db->addFavorite($_POST);
    die('OK');
}

if (isset($_GET["action"]) && $_GET["action"] == "delete" && isset($_POST['id'])) {
    $db->deleteFavorite($_POST['id']);
    die('OK');
}

$favorites = $db->getFavorites();
?>
<!DOCTYPE html>
<html>
<head>
    <title>IT Dashboard - Best</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href='https://fonts.googleapis.com/css?family=Roboto+Condensed:300,400' rel='stylesheet' type='text/css'>
    <link href='https://fonts.googleapis.com/css?family=Lato:300,400,700,900' rel='stylesheet' type='text/css'>
    <link rel="stylesheet" type="text/css" href="../monitor/lib/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css">
    <link rel="stylesheet" type="text/css" href="./css/style.css">
    <style>
        table { table-layout:fixed; width:100%; }
    </style>
</head>
<body>

<?php 
$active_page = 'best';
$show_reload_btn = true;
include('navbar.php'); 
?>

<div class="container-fluid">
    <div class="row" style="margin-top: 80px;">
        </nav>
    </div>
    
    <div class="row" style="margin-top: 5%;">
        <div class="col-lg-12">
            <div class="alert alert-success">
                <strong>Favoriten:</strong> <?= count($favorites) ?> gespeicherte Toasts
            </div>
            
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
                <?php foreach ($favorites as $row): 
                    $json = json_decode($row->raw_json);
                ?>
                    <tr data-row-id="<?= $row->id ?>">
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
                        <td><pre class="pre-scrollable" contenteditable="true" data-id="<?= $row->id ?>"><?= json_encode($json, JSON_PRETTY_PRINT) ?></pre></td>
                        <td>
                            <div class="btn-group">
                                <button class="btn btn-primary repeat" data-id="<?= $row->id ?>">Wiederholen</button>
                                <button class="btn btn-danger delete" data-id="<?= $row->id ?>">Löschen</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
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
    
    $('.delete').click(function () {
        var id = $(this).data('id');
        var row = $('tr[data-row-id="' + id + '"]');
        
        if (confirm('Wirklich löschen?')) {
            $.ajax({
                url: 'best_new.php?action=delete',
                type: 'post',
                data: { id: id },
                success: function (data) {
                    row.fadeOut(300, function() { $(this).remove(); });
                }
            });
        }
    });
</script>
</body>
</html>
