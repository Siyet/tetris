<?php
require "vendor/autoload.php";
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
require "config.php";

$curl  = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_URL =>  "https://api.suredone.com/v1/bulk/exports/?type=items&mode=include&fields=vdate,stock,storagelocation",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "GET",
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPHEADER => array(
        "x-auth-token: $token",
        "x-auth-user: $user"
    ),
));
$_response = curl_exec($curl);
$err = curl_error($curl);
if($err){
    echo "cURL Error #:" . $err;
    exit();
}
$_response = json_decode($_response, true);
sleep(2);
if (!is_dir(PATH2CSV)) throw new Exception("ERROR: tetris csv file path isn't exists!");
$volumes_csv_file = fopen(PATH2CSV . CSV_FNAME, "r+");
$volumes = array();
while(($row = fgetcsv($volumes_csv_file)) !== FALSE) {
    if ($row && array_key_exists(1, $row)) {
        $volumes[] = $row;
    }
}
fclose($volumes_csv_file);
unset($row,$volumes_csv_file);
$volumes = json_encode($volumes);
sleep(2);
curl_setopt_array($curl, array(
    CURLOPT_URL =>  "https://api.suredone.com/v1/bulk/exports/".$_response["export_file"],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "GET",
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPHEADER => array(
        "x-auth-token: $token",
        "x-auth-user: $user"
    ),
));
$_response = curl_exec($curl);
$err = curl_error($curl);
if($err){
    echo "cURL Error #:" . $err;
    exit();
}
$_response = json_decode($_response, true);
curl_close($curl);


$vdates_csv_file = fopen($_response["url"], "r");
$vdates = array();
$storlocs = array();
$wrong_storlocs = 0;
$wrong_storlocs_keys = [];
$listings_total = 0;
$start_at = date("Y-m-d H:i:s");
$is_header = true;
while(($row = fgetcsv($vdates_csv_file)) !== FALSE) {
    if ($is_header){
        $is_header = false;
        continue;
    }
    if ($row && array_key_exists(0, $row) && $row[0]) {
        $vdates[] = [$row[0], $row[1]];
    }
    if ($row && array_key_exists(2, $row)){
        $listings_total += 1;
        if ($row[0] && $start_at > $row[0]) $start_at = $row[0];
        $is_wrong_storloc = FALSE;
        foreach (preg_split("/[, ]+/", $row[2]) as $storloc) {
            if (!preg_match("/^([0-9]{6}(\/[0-9]{1,2})?)|([0-9][A-z][0-9][A-z]?)$/", $storloc)) {
                if ($storloc){
                    if (!in_array($storloc, $wrong_storlocs_keys))
                        $wrong_storlocs_keys[] = $storloc;
                    
                    $is_wrong_storloc = TRUE;
                }
                
                continue; 
            }
            $storloc_idx = "_".explode("/", $storloc)[0];
            if (array_key_exists($storloc_idx, $storlocs)){
                $storlocs[$storloc_idx]["listings"] += 1;
                if ($storlocs[$storloc_idx]["status"] !== "inprogress" 
                    && (
                        ($row[0] && $storlocs[$storloc_idx]["status"] !== "completed") 
                        || (!$row[0] && $storlocs[$storloc_idx]["status"] !== "todo")
                    )
                ){
                    $storlocs[$storloc_idx]["status"] = "inprogress";
                }
            } else {
                $storlocs[$storloc_idx] = [
                    "listings" => 1,
                    "status" => $row[0] ? "completed" : "todo"
                ];
            }
        } unset($storloc_idx, $storloc);
        if ($is_wrong_storloc) $wrong_storlocs += 1;
        unset($is_wrong_storloc);
    }
}
fclose($vdates_csv_file);
unset($row,$vdates_csv_file,$_response,$is_header);
sort($vdates);
$vdates = json_encode($vdates);
$storlocs = json_encode($storlocs);
$wrong_storlocs = json_encode($wrong_storlocs);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" type="text/css" href="css/grafs.css?rev=<?=time()?>">
    <link rel="stylesheet" href="css/style.css?rev=<?=time()?>">
    <script src="//ajax.googleapis.com/ajax/libs/jquery/2.1.4/jquery.min.js"></script>
    <script type="text/javascript" src="js/grafs.js"></script>
    <script type="text/javascript" src="js/fi.js"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.22.2/moment.min.js"></script>
    <style>
    #volumes div {
        padding: 12px 20px;
        margin: 4px 8px;
        float: left;
        font-family: monospace;
        color: white;
        font-size: 1.2em;
        font-weight: bold;
    }
    ul {
        font-family: monospace;
        font-size:1.1em;
        color:#444;
    }
    ul ul {
        font-size: 1em;
    }
    </style>
    <title>Document</title>
</head>

<body>
<div id="chart" style="height: 300px;"></div>
<div id="chart-legend"></div>
<div id="volumes"></div>
<div style="clear:both"></div>

<ul style="float:right; margin-right:16px">
    <li>
        Boxes:
        <ul>
            <li> - in progress: <big id="sl-inprogress"></big></li>
            <li> - done: <big id="sl-done"></big></li>
            <li> - total: <big id="sl-total"></big></li>
        </ul>
    </li>
</ul>

<ul style="float:right; margin-right:16px">
    <li>
        Listings count:
        <ul>
            <li> - done: <big id="listings-done"></big></li>
            <li> - total: <big><?php echo $listings_total; ?></big></li>
        </ul>
    </li>
</ul>
<ul style="float:right; margin-right:16px">
    <li>
        Start at: <big id="start-at"></big>
    </li>
    <li>
        Estimate end:
        <ul>
            <li> - by listings: <big id="end-by-listings"></big></li>
            <li> - by boxes: <big id="end-by-boxes"></big></li>
            <!-- <li> - avg: <big id="end-avg"></big></li> -->
        </ul>
    </li>
</ul>
<ul style="float:right; margin-right:16px">
    <li>
        Listings with wrong `storagelocation`: <big><?php echo $wrong_storlocs ? $wrong_storlocs : "[]"; ?></big>
    </li>
    <li>
        Wrong keyword in storagelocations: <big><?php echo count($wrong_storlocs_keys); ?></big> <a href="#" onclick="$('.hidden').css('display', 'list-item'); $(this).hide();"><i>show list</i></a>
        <ul>
            <?php foreach ($wrong_storlocs_keys as $key) { ?>
            <li class="hidden" style="display:none;"><a target="_blank" href="http://nationalsalesltd.com/suredone/?query=storagelocation%3A<?php echo $key; ?>"><?php echo $key; ?></a></li>
            <?php } ?>
        </ul>
    </li>
</ul>

<!-- JS -->
<script>
function getDatesList(startDate, endDate) {
    endDate = new Date(endDate);
 let retVal = [];
 let current = new Date(startDate);
 while (current <= endDate) {
  _cur = new Date(current);
  let month=_cur.getMonth() +1
  if ((month+"").length < 2) month = "0" + month
  let day=_cur.getDate()
  if ((day+"").length < 2) day = "0" + day
  retVal.push(`${_cur.getFullYear()}-${month}-${day}`);
  current.setDate(current.getDate() + 1);
 }
 return retVal;
}
let start_at = moment("<?php echo $start_at; ?>")
$('#start-at').text(start_at.format("LL"))
// let start_at_ms = parseInt(moment().format('x')) - parseInt(start_at.format('x'))

let vdates = <?php echo $vdates ? $vdates : "[]"; ?>;
$('#listings-done').text(vdates.length)
let storlocs = <?php echo $storlocs ? $storlocs : "[]"; ?>;
let sl_total = Object.values(storlocs).length
let sl_done = Object.values(storlocs).reduce((acc,el)=>acc + (el.status === "completed" ? 1 : 0), 0)
$('#sl-total').text(sl_total)
$('#sl-done').text(sl_done)
$('#sl-inprogress').text(Object.values(storlocs).reduce((acc,el)=>acc + (el.status === "inprogress" ? 1 : 0), 0))
// console.log(start_at_ms * Object.values(storlocs).length / Object.values(storlocs).reduce((acc,el)=>acc + (el.status === "completed" ? 1 : 0), 0) + parseInt(start_at.format('x')))
let start_at_as_x = parseInt(start_at.format('x'))
let now_at_as_x = parseInt(moment().format('x'))
$('#end-by-boxes').text( moment(
        ( ( now_at_as_x - start_at_as_x ) * sl_total / sl_done ) + start_at_as_x
    ).format('LL') )
$('#end-by-listings').text( moment(
        ( ( now_at_as_x - start_at_as_x ) * <?php echo $listings_total; ?> / vdates.length ) + start_at_as_x
    ).format('LL') )
$('#end-avg').text(moment())
let volumes = <?php echo $volumes ? $volumes : "[]"; ?>;
console.log(storlocs)
volumes.forEach(vol=>{
    if(vol && vol[0]){
        let qq = parseInt(vol[1])
        let color = "grey"
        if (qq === 100) color = "#FF0201"
        else if (qq >=75) color ="#FA6690"
        else if (qq >= 50) color = "#F5A623"
        else if (qq >= 25) color = "#00BA79"
        $('#volumes').append(`<div style="background-color: ${color};">${vol[0]}</div>`)
    }
})
let labels = getDatesList(vdates[0][0], vdates[vdates.length-1][0])
let listings = labels.map(el=>0)
let goods = labels.map(el=>0)
let wrong_keys = <?php $wrong_storlocs_keys; ?>

vdates.forEach(el => {
    if (el && el[0] && el[0].length >= 10){
        let date = el[0].substr(0,10)
        let idx = labels.indexOf(date)
        if (idx !== -1){
            listings[idx] = listings[idx] + 1
            if (el[1]) goods[idx] = goods[idx] + parseInt(el[1])
        }
    }
})
// console.log(labels)
let data = {
    labels: labels,
    data: [
        {
            group: 'Listings',
            data: listings
        },
        {
            group: 'Goods',
            data: goods
        }
    ]
};

var options = {
    stroke: {
        width: 1
    },
    legend: {
        target: '#chart-legend'
    }
};

var line = new Grafs.Line('#chart', data, options);
</script>
</body>
</html>
