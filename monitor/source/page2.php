<?php
/**
 * Created by PhpStorm.
 * User: victor.lange
 * Date: 05.04.2016
 * Time: 16:12
 */
//
//Helpline
//
require("../../config.php");
$serverName = HELPLINE_SQL_INSTANCE;
$connectionInfo = array("Database" => HELPLINE_SQL_DATABASE, "CharacterSet" => "UTF-8");
$conn = sqlsrv_connect($serverName, $connectionInfo);

if ($conn) {
    if ($result = sqlsrv_query($conn,"
select count(ticket.referencenumber) NewTickets
from (select 'Incident' type, * from increcsystem union all select 'Service Request' type, * from sereresystem) ticket
    inner join (select * from increcsusystem union all select * from sereresusystem) su on ticket.caseid = su.caseid and su.suindex = 1
    left join (select * from increcgeneral union all select * from sereregeneral) general on ticket.caseid = general.caseid
	inner join hlsyscaseassignment assignment ON assignment.caseid = ticket.caseid
where DATEADD(MI, DATEDIFF(MI, GETUTCDATE(), GETDATE()), su.registrationtime) >= convert(date, getdate())
and assignment.caseassignedteamname in ('Infrastructure 3rd Level', 'ITSM Service Desk', 'Workplace Service 2nd Level', 'Infrastructure 2nd Level')
"
    )) {
        $NewTickets = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)["NewTickets"];
    } else {
        echo "<pre>";
        die(print_r(sqlsrv_errors(), true));
    }



    if ($result = sqlsrv_query($conn,"
select count(ticket.referencenumber) ClosedTickets
from (select 'Incident' type, * from increcsystem union all select 'Service Request' type, * from sereresystem) ticket
    inner join (select * from increcsusystem union all select * from sereresusystem) su on ticket.caseid = su.caseid and su.suindex = 1
    left join hlsyslistitem state on ticket.internalstate = state.listitemid
    left join (select * from increcgeneral union all select * from sereregeneral) general on ticket.caseid = general.caseid
	inner join hlsyscaseassignment assignment ON assignment.caseid = ticket.caseid
where DATEADD(MI, DATEDIFF(MI, GETUTCDATE(), GETDATE()), ticket.lastmodified) >= convert(date, getdate())
and assignment.caseassignedteamname in ('Infrastructure 3rd Level', 'ITSM Service Desk', 'Workplace Service 2nd Level', 'Infrastructure 2nd Level')
and state.name in ('SOLVED', 'CLOSED')
"
    )) {
        $ClosedTickets = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)["ClosedTickets"];
    } else {
        echo "<pre>";
        die(print_r(sqlsrv_errors(), true));
    }

    if ($result = sqlsrv_query($conn,"
with allHours ([hour])
as (
    select 0 as [hour]
    union all
    select [hour] + 1
    from allHours
    where [hour] < 24
    )

select [hour], count(t.nummer) [tickets], [team]
from allHours
left join (
    select
	    ticket.referencenumber [nummer]
       ,caseassignedteamname [team]
	   ,case when DATEADD(MI, DATEDIFF(MI, GETUTCDATE(), GETDATE()), ticket.creationtime) >= convert(date, getdate())
	      then datepart(hour, DATEADD(MI, DATEDIFF(MI, GETUTCDATE(), GETDATE()), ticket.creationtime))
	      else 0
	    end [opened]
	   ,case when state.name in ('SOLVED', 'CLOSED') and DATEADD(MI, DATEDIFF(MI, GETUTCDATE(), GETDATE()), ticket.lastmodified) >= convert(date, getdate())
	      then datepart(hour, DATEADD(MI, DATEDIFF(MI, GETUTCDATE(), GETDATE()), ticket.lastmodified))
	      else 25
	    end [closed]
    from (select 'Incident' type, * from increcsystem union all select 'Service Request' type, * from sereresystem) ticket
        inner join (select * from increcsusystem union all select * from sereresusystem) su on ticket.caseid = su.caseid and su.suindex = 1
        left join hlsyslistitem state on ticket.internalstate = state.listitemid
    	inner join hlsyscaseassignment assignment ON assignment.caseid = ticket.caseid
    where
        assignment.caseassignedteamname in ('Infrastructure 3rd Level', 'ITSM Service Desk', 'Workplace Service 2nd Level', 'Infrastructure 2nd Level')
		and ticket.lastmodified > GETDATE() - 365
        and (
    	  state.name not in ('SOLVED', 'CLOSED')
    	  or (
    	    ticket.lastmodified >= convert(date, getdate())
    	    and state.name in ('SOLVED', 'CLOSED')
          )
        )
    ) t on [hour] < t.closed and [hour] >= t.opened
where datepart(hour, getdate()) >= [hour]
group by [team], [hour]
order by [team], [hour]
"
    )) {
        $TicketChart = array();
        $Teams = array();
        while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
            if (end($Teams) != $row['team']) {
                $TicketChart[$row['team']] = array();
                $Teams[] = $row['team'];
            }
            $TicketChart[$row['team']][$row['hour']] = $row['tickets'];
        }
    } else {
        echo "<pre>";
        die(print_r(sqlsrv_errors(), true));
    }



    if ($result = sqlsrv_query($conn, "
select
     ticket.referencenumber referencenumber
	--,ticket.promisedsolutiontime promisedsolutiontime
    --,assignment.caseassignedpersonname assignedagent
	,general.subject subject
	,priority.name priority
	,requester.calnam requester
from (select 'Incident' type, * from increcsystem union all select 'Service Request' type, * from sereresystem) ticket
    inner join (select * from increcsusystem union all select * from sereresusystem) su on ticket.caseid = su.caseid and su.suindex = 1
    left join hlsyslistitem state on ticket.internalstate = state.listitemid
    left join (select * from increccalinf union all select * from sererecalinf) requester on ticket.caseid = requester.caseid
    left join (select * from increcgeneral union all select * from sereregeneral) general on ticket.caseid = general.caseid
    --left join (select * from increcdescdestex union all select * from sereredescdestex) description on ticket.caseid = description.caseid
    inner join hlsyslistitem priority on general.priority = priority.listitemid and priority.name in ('PriorityCritical', 'PriorityHigh')
    --inner join (select * from increccatgencale1 union all select * from sererecatgencale1) category on ticket.caseid = category.caseid
	inner join hlsyscaseassignment assignment ON assignment.caseid = ticket.caseid
	--left join serteageneral team ON team.orgunitid = assignment.caseassignedteamid
where DATEADD(MI, DATEDIFF(MI, GETUTCDATE(), GETDATE()), su.registrationtime) >= convert(date, getdate() - 7)
and assignment.caseassignedteamname in ('Infrastructure 3rd Level', 'ITSM Service Desk', 'Workplace Service 2nd Level', 'Infrastructure 2nd Level')
and state.name not in ('SOLVED', 'CLOSED')
order by referencenumber desc
")) {
        $prioTickets = array();
        while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
            array_push($prioTickets, $row);
        }
    } else {
        echo "<pre>";
        die(print_r(sqlsrv_errors(), true));
    }
} else {
    echo "<pre>";
    die(print_r(sqlsrv_errors(), true));
}
?>
<div class="row">
    <div class="col-lg-6">
        <div class="card dark summary-inline">
            <div class="card-body">
                <div class="content">
                    <div class="title">Heute eröffnete Tickets</div>
                    <div class="title"><?= $NewTickets ?></div>
                </div>
                <div class="clear-both"></div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card dark summary-inline">
            <div class="card-body">
                <div class="content">
                    <div class="title">Heute geschlossene Tickets</div>
                    <div class="title"><?= $ClosedTickets ?></div>
                </div>
                <div class="clear-both"></div>
            </div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <div class="title">Infotisch</div>
                </div>
            </div>
            <div class="card-body no-padding">
                <canvas id="TicketChart" class="chart" width="478" height="260" style="width: 478px; height: 260px;"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card summary-inline">
            <div class="card-header">
                <div class="card-title">
                    <div class="title">Tickets mit Priorität hoch🔥 oder top💥</div>
                </div>
            </div>
            <div class="card-body">
                <table class="<?php echo (count($prioTickets) == 0) ? 'collapse ' : '' ?>table table-responsive">
                    <thead>
                    <tr>
                        <th style="width: 0%;" class="h4"></th>
                        <th style="width: 10%;" class="h4">Ticketnummer</th>
                        <th style="width: 10%;" class="h4">Anforderer</th>
                        <th class="h4">Betreff</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    foreach ($prioTickets as $key => $ticket) {
                        $prio =  ($ticket["priority"] == 'PriorityCritical') ? '💥' : '🔥';
                    ?>
                        <tr class='helplinetable'>
                            <td class='h4 text'><span><?= $prio ?></span></td>
                            <td class='h4 text'><span><?= $ticket["referencenumber"] ?></span></td>
                            <td class='h4 text'><span><?= $ticket["requester"] ?></span></td>
                            <td class='h4 text'><span><?= $ticket["subject"] ?></span></td>
                        </tr>
                    <?php
                    }
                    ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php

// Ermittle maximale Stunde aus den tatsächlichen Daten
$maxHour = 0;
foreach ($TicketChart as $team => $data) {
    if (!empty($data)) {
        $teamMaxHour = max(array_keys($data));
        if ($teamMaxHour > $maxHour) {
            $maxHour = $teamMaxHour;
        }
    }
}

$ChartLabels = array();
for($i = 0; $i <= $maxHour; $i++){
    $time = strtotime($i . ':00:00');
    array_push($ChartLabels, date("G:i",$time));
}

// Initialisiere alle Teams mit leeren Arrays
$ChartDatasets = array(
    'ITSM Service Desk' => array(),
    'Workplace Service 2nd Level' => array(),
    'Infrastructure 2nd Level' => array(),
    'Infrastructure 3rd Level' => array()
);

foreach ($TicketChart as $team => $data) {
    for($i = 0; $i <= $maxHour; $i++){
        if(isset($data[$i])){
            array_push($ChartDatasets[$team], $data[$i]);
        }else{
            array_push($ChartDatasets[$team], 0);
        }
    }
}

// Fülle fehlende Teams mit Nullen auf
foreach ($ChartDatasets as $team => $data) {
    if (empty($data)) {
        for($i = 0; $i <= $maxHour; $i++){
            array_push($ChartDatasets[$team], 0);
        }
    }
}

$ChartLabels = json_encode($ChartLabels);

//$ChartData = json_encode($ChartData);
?>

<script>
var options = {
    scales: {
        xAxes: [{
            ticks: {
                fontSize: 15
            }
        }],
        yAxes: [{
            ticks: {
                min: 0,
                //max: 10,
                fontSize: 15
            }
        }]
    },
    elements: {
        line: {
            tension: 0.1
        }
    },
    borderCapStyle: 'butt'
};

//'Infrastructure 3rd Level', 'ITSM Service Desk', 'Workplace Service 2nd Level', 'Infrastructure 2nd Level'
var data = {
        labels: <?= $ChartLabels ?>,
        datasets: [
            {
                label: 'ITSM Service Desk',
                data: <?php echo json_encode($ChartDatasets['ITSM Service Desk']) ?>,
                spanGaps: true,
                fill: false,
                borderColor: ['rgba(115, 255, 0, 1)'],
                borderWidth: 1
            },
            {
                label: 'Workplace Service 2nd Level',
                data: <?php echo json_encode($ChartDatasets['Workplace Service 2nd Level']) ?>,
                spanGaps: true,
                fill: false,
                borderColor: ['rgba(228, 224, 18, 1)'],
                borderWidth: 1
            },
            {
                label: 'Infrastructure 2nd Level',
                data: <?php echo json_encode($ChartDatasets['Infrastructure 2nd Level']) ?>,
                spanGaps: true,
                fill: false,
                borderColor: ['rgba(247, 142, 5, 1)'],
                borderWidth: 1
            },
            {
                label: 'Infrastructure 3rd Level',
                data: <?php echo json_encode($ChartDatasets['Infrastructure 3rd Level']) ?>,
                spanGaps: true,
                fill: false,
                borderColor: ['rgba(245, 14, 64, 1)'],
                borderWidth: 1
            }
        ]
};
ctx = $('#TicketChart').get(0).getContext('2d');

var d = new Date();
var n = d.getHours();
var TicketChart = new Chart(ctx, {
    type: 'line',
    data: data,
    options: options,
    lineAtIndex: [n]
});
</script>
