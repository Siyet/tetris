<?php
require "vendor/autoload.php";
// ini_set('error_reporting', E_ALL);
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
require "config.php";

$goods = array();
$resp_json = NULL;
$filling = "0";
if (isset($_GET['query'])) {

    $query = $_GET['query'];
    $response = array();
    $goods = array();
    $curl  = curl_init();
    curl_setopt_array($curl, array(
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
    function getItemsByPage($page=1) {
        global $query;
        global $response;
        global $curl;
        global $goods;
        curl_setopt_array($curl, array(
            CURLOPT_URL =>  API_URL . "storagelocation:" . urlencode($query) . "/?page=" . (string)$page
        ));
        $_response = curl_exec($curl);
        $err = curl_error($curl);

        if($err){
            echo "cURL Error #:" . $err;
            exit();
        }
        $_response = json_decode($_response, true);
        foreach ($_response as $key => $row) {
            if ($key == "all" || $key == "type" || $key == "time")
                $response[$key] = $row;
            else {
                $goods[] = $row;
                // $storagelocation = implode( " ", array_map(
                //     function ($item) { return preg_match("/[0-9]{5}/", $item) ? "0" + $item : $item; },
                //     preg_split("/[\s,]+/", $row['storagelocation'])
                // ) );
                $response[] = array(
                    "storagelocation" => $row["storagelocation"],
                    "stock" => $row["stock"],
                    "title" => $row["title"],
                    "guid"  => $row["guid"],
                    "vdate" => $row["vdate"],
                    "expiration" => $row["expiration"],
                    "media" => $row["media"]
                );
            }
        } unset($key, $row, $storagelocation);
        if ((int)$response["all"] > count($goods)){
            getItemsByPage($page += 1);
        }
        return;
    }
    getItemsByPage();
    curl_close($curl);
    $response = json_encode($response);
    if (isset($_GET['printing']) && $goods){
        $html = "
            <html>
                <head>
                    <style>
                        @page {
                            size: 288px 96px;
                            margin: 0;
                            padding: 0;
                        }
                        html {
                            font-family: 'Tw Cen MT';
                        }
                        img {
                            height: 90px;
                            width: 90px;
                            padding: 3px;
                            float: left;
                            display: inline-block;
                        }
                        html, body, .container {
                            margin: 0px;
                            padding: 0px;
                            border:none;
                        }
                        .container {
                            width: 288px;
                        }
                        .content {
                            width: 186px;
                            padding: 0;
                            float: right;
                            display: inline-block;
                        }
                        .sku {
                            font-size: 13px;
                            padding: 0;
                            margin: 0;
                            font-weight: bold;
                        }
                        .title {
                            font-size: 11px;
                            padding: 0;
                            margin: 0;
                            line-height:60%;
                        }
                        .expiration {
                            position:absolute;
                            font-size: 12px;
                            padding: 0;
                            left: 98px;
                            bottom: 8px;
                        }
                        .sku, .title, .expiration {
                            width: 100%;
                        }
                    </style>
                </head>
                <body>
                    <script type='text/javascript'> try { this.print(); } catch (e) { window.onload = window.print; } </script>";
        foreach ($goods as $product) {
            $vtime = $product["vdate"] ? strtotime($product["vdate"] . " +3 months") : NULL;
            if ($product["stock"] != 0 && $product["stock"] != "0" && $vtime > time()){
            // if ($product["stock"] != 0 && $product["stock"] != "0"){
                $chl = array(
                    '0' => $product['sku'],
                    '1' => $product['title'],
                    '2' => $product['storagelocation'],
                    '3' => $product['expiration']
                );
                // $image1 = QRcode::svg(json_encode($chl), $product['sku'], false, QR_ECLEVEL_L, 90);
                // echo $image1;
                $qr = new \Endroid\QrCode\QrCode(json_encode([
                    '0' => $product['sku'],
                    '1' => $product['storagelocation'],
                    '2' => $product['expiration']
                ]));
                $qr->setSize(90);
                $qr->setMargin(0);
                $qr->setEncoding('UTF-8');
                $qr->setWriterByName('png');
                $image = $qr->writeDataUri();

                // $a = preg_split("/\s*;\s*/", $image);
                // array_splice($a, 1, 0, "charset=binary");
                // $image = implode(";", $a);
                $expiration = isset($chl['3']) && !empty($chl['3']) ? $chl['3'] : 'N/A';
                for ($i=0; $i < (int)$product["stock"]; $i++){
                    $html .= "
                        <div class='container'>
                            <img src='".$image."'>
                            <div class='content'>
                                <h1 class='sku'>{$chl['0']}</h1>
                                <div class='title'>{$chl['1']}</div>
                                <div class='expiration'>Expiration: <b>{$expiration}</b></div>
                            </div>
                            <div style='clear:both; height:0'></div>
                        </div>";
                }
            }
        }
        $html .= "</body></html>";
        // echo $html;
        // exit();
        $options = new \Dompdf\Options();
        $options->setIsRemoteEnabled(true);
        $options->setIsHtml5ParserEnabled(true);
        $dompdf = new \Dompdf\Dompdf($options);
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => FALSE,
                'verify_peer_name' => FALSE,
                'allow_self_signed'=> TRUE
            ]
        ]);
        $dompdf->setHttpContext($context);
        $dompdf->loadHtml($html);
        // Render the HTML as PDF
        $dompdf->render();
        // Output the generated PDF to Browser
        $dompdf->stream("document.pdf", array("Attachment" => false));
        //  $dompdf->stream(urlencode($query).".pdf", array("Attachment" => 1));
    } else {
        $_store_loc = explode("/", $query)[0];
        if ($_store_loc) {
            $store_loc = str_pad($_store_loc, 6, "0", STR_PAD_LEFT);
            if (!is_dir(PATH2CSV)) throw new Exception("ERROR: tetris csv file path isn't exists!");
            $csv_file = fopen(PATH2CSV . CSV_FNAME, "r");
            while(($row = fgetcsv($csv_file)) !== FALSE) {
                if ($row && $row[0] == $store_loc) $filling = $row[1];
            }
            fclose($csv_file);
        }
    }
}
 ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" href="css/kube.min.css">
    <link rel="stylesheet" href="css/kube.theme.min.css">
    <link rel="stylesheet" href="css/kube-addons.min.css">
    <link rel="stylesheet" href="css/style.css">

    <title>tetris</title>
</head>

<body>
    <div class="page">
        <main class="main">
            <span class="loc_num">Enter location number:</span>
            <div class="enter_number">
                <input type="text" id="query_field">
                <button class="button" onClick = "getdetails(event)">Find it</button>
            </div>
            <span>You can enter the box number (prefix 03), column number. You will be shown what is on this shelf.</span>
<?php if ($query) { ?>
            <span class="found">The filling of the storage location:
                <button style="margin-left:16px" onClick="setFilling(event, 0)" class="button <?php echo !$filling || $filling=='0' ? '' : 'is-secondary' ; ?>">0%</button>
                <button onClick="setFilling(event, 25)" class="button <?php echo $filling && $filling=='25' ? '' : 'is-secondary' ; ?>">25%</button>
                <button onClick="setFilling(event, 50)" class="button <?php echo $filling && $filling=='50' ? '' : 'is-secondary' ; ?>">50%</button>
                <button onClick="setFilling(event, 75)" class="button <?php echo $filling && $filling=='75' ? '' : 'is-secondary' ; ?>">75%</button>
                <button onClick="setFilling(event, 100)" class="button <?php echo $filling && $filling=='100' ? '' : 'is-secondary' ; ?>">100%</button>
            </span>
<?php }; ?>
            <span id="founding_stats" class="found" style="display:none;">Found <b id="resp_all">0</b> items at location <b class="query_val"></b></span>

            <div class="find_sub_loc" style="display:none;"></div>
            <div id="founded_items"> </div>

            <div class="labels">
                <span>Label`s ready to print: <b class="count">0</b></span>
            </div>

            <span>if all items are checked and the quantity is correct, click the button below:</span>

            <div class="print">
                <button id="btnPrint" class="button" onClick="printLabels()">Print labels</button>
                <span>Attention! <br>
                    The total number of items in this box will be printed.</span>
            </div>
        </main>
    </div>

    <script src="js/jquery-3.3.1.js "></script>
    <script src="js/kube.min.js "></script>
    <script src="js/kube-addons.min.js "></script>
    <script src="js/script.js "></script>
    <script>
        function checkVDate(prod_vdate) {
            vdate = new Date(Date.parse(prod_vdate));
            vdate.setMonth(vdate.getMonth() + 3);
            return prod_vdate && vdate.getTime() > new Date().getTime()
        }
        let find_resuts = <?php echo $response ? $response : "[]"; ?>;
        // console.log(find_resuts);
        let locations_groups = [];
        let main = $("#founded_items");
        let query_str = window.location.search ? window.location.search.split("=")[1] : "";
        $("#query_field").val(query_str);
        for (let key in find_resuts){
            if (key != "type" && key != "all" && key != "time"){
                let storagelocation = find_resuts[key]["storagelocation"].split(/[\s,]+/);
                storagelocation.forEach(el=>{
                    if (el.indexOf(query_str) !== -1){
                        if (!(el in locations_groups)) locations_groups[el] = [];
                        locations_groups[el].push(find_resuts[key]);
                    }
                });
            }
        }
        if (find_resuts) {
            $('#founding_stats').css("display", "block");
            $('#resp_all').text(find_resuts.all);
        }
        if (query_str) $('.query_val').text(query_str);
        let find_sub_loc = $('.find_sub_loc');
        let can_print = true;
        for (let key in locations_groups) {
            if (key != query_str)
                find_sub_loc.append("<a href='#' onClick='setStext(\""+key+"\")' style='display:inline-block;'>"+key+"</a>");
            let found_item = $(`<div class="found_item"><h2>`+key+`</h2><table class="is-responsive"></table></div>`);
            main.append(found_item);
            let table = found_item.find("table");
            locations_groups[key].forEach(prod=>{
                let tr2append = `<tr class="is-middle"><td style="width:120px"><img class="thumbnail" src="`+prod.media+`" style="width:80px;"></td>
                        <td>` + prod.title + `<br/><code>`+prod.guid+`</code>`;
                if (prod.expiration) tr2append+=`<small>Expiration: `+prod.expiration+`</small>`
                else if (prod.title.indexOf("(x)") !== -1) tr2append+=`<br/><small>Expiration: N/A</small>`;
                tr2append+=`</td>
                        <td><input type="number" value="`+prod.stock+`"><input type="hidden" value="`+prod.guid+`"></td>
                        <td><button class="button" onClick="updateStock(event)"`;
                // if (prod.guid !== "NS010149") tr2append += `disabled="disabled"`;
                tr2append += `><b>↻</b></button></td>
                        <td>`;

                if (checkVDate(prod.vdate)) tr2append += `<div class="green"><div class="white"></div></div>`;
                else can_print = false;
                tr2append += `</td><td>`;
                let storagelocation = prod["storagelocation"].split(/[\s,]+/);
                storagelocation.forEach(el=>{
                    if (el !== key){
                        tr2append += `<a href="#" style="display:block" onClick="setStext('`+el+`')">`+el+`</a>`;
                    }
                });
                tr2append += `<input style="display:none;width:200px" type="text" name="storagelocation" value="`+prod.storagelocation.replace(/"/g, '\\"')+`"></td><td><button class="edit" onClick="editStorageLocation(event)">✎</button><button class="edit" style="display:none;" onClick="saveStorageLocation(event, '`+prod.guid+`')">✔</button></td></tr>`;
                table.append(tr2append);
            })
        }
        if (can_print) $("#btnPrint").attr("disabled", null);
        if (find_sub_loc.html())
            find_sub_loc.css("display", "block")
                        .prepend(`<span>System find sub-locations in main location</span>`);
        getSum();
        $('input[type=number]').on('change', () => getSum() );
        let td_edit_location = null;
        let init_storage_location = null;
        function editStorageLocation(ev) {
            if (td_edit_location) {
                td_edit_location.find("a").css("display","block");
                td_edit_location.find("input[name='storagelocation']").css("display","none").val(init_storage_location)
                td_edit_location.next().find('button.edit').css("display", "inline-flex").next().css("display","none");
            }
            let btn_edit = $(ev.target).css("display","none");
            td_edit_location = btn_edit.next().css("display", "inline-flex").parent().prev()
            td_edit_location.find("a").css("display","none");
            init_storage_location = td_edit_location.find("input[name='storagelocation']").css("display","block").val();
        }
        $(document).mouseup(function (e){ // событие клика по веб-документу
            var container = $("input[name='storagelocation']").parents('tr'); // тут указываем ID элемента
            if (!container.is(e.target) // если клик был не по нашему блоку
                && container.has(e.target).length === 0 && td_edit_location) { // и не по его дочерним элементам
                td_edit_location.find("a").css("display","block");
                td_edit_location.find("input[name='storagelocation']").css("display","none").val(init_storage_location)
                td_edit_location.next().find('button.edit').css("display", "inline-flex").next().css("display","none");
            }
        });
        function saveStorageLocation(ev, prod_guid) {
            $.ajax({
                type: "POST",
                url: "edit.php",
                data: {storagelocation: td_edit_location.find('input[name="storagelocation"]').val(), guid: prod_guid}
            }).done(function( result ) {
                console.log(result)
                window.location.href = window.location.protocol + "//" + window.location.host + "/" + window.location.search
            });
        }
        function getSum() {
            let sum = 0;
            for (let key in find_resuts){
                if (key != "type" && key != "all" && key != "time"){
                    if (find_resuts[key].vdate && checkVDate(find_resuts[key].vdate)) sum += parseInt(find_resuts[key].stock);
                }
            }
            $('.count').html(sum);
        }
        $(window).on('load', function() {
            getSum();
        });
        $('input[type=number]').on('change', function() {
            getSum();
        });
        let popup = null;
        $('img.thumbnail').hover((el)=>{
            // hover in
            popup = $(`<img>`)
                .css("position","fixed")
                .css("top","10%")
                .css("left","200px")
                .css("max-width","80%")
                .css("max-height","80%")
                .attr("src", $(el.target).attr("src"))
                .appendTo($('body'))
        }, ()=>{
            //hover out
            popup.remove();
        });
        function setStext(text) {
            if (text) {
                $('#query_field').val(text);
                query_str = text;
            }
            getdetails();
        }
        function printLabels() {
            window.location.href = window.location.protocol + "//" + window.location.host + "/" + window.location.search + "&printing=true";
        }
        function setFilling(ev, count) {
            let target_btn = $(ev.target);
            target_btn.parent().find("button").addClass("is-secondary")
            target_btn.removeClass("is-secondary")
            $.ajax({
                type: "POST",
                url: "edit.php",
                data: {filling: count.toString(), query: query_str}
            }).done(function( result ) {
                // console.log(result)
            })
        }
        function updateStock(ev) {
            let btn = $(ev.target);
            btn.attr("disabled", "disabled");
            let stock = btn.parents("td").prev().find('input[type="number"]').val();
            let guid = btn.parents("td").prev().find('input[type="hidden"]').val();
            $.ajax({
                type: "POST",
                url: "edit.php",
                data: {stock: stock, guid: guid}
            }).done(function( result ) {
                window.location.href = window.location.protocol + "//" + window.location.host + "/" + window.location.search
                btn.attr("disabled", null);
                btn.parents("td").next().html(`<div class="green"><div class="white"></div></div>`);
                let can_print = true;
                for (let key in locations_groups){
                    locations_groups[key].forEach(prod=>{
                        if (prod.guid == guid){
                            let now = new Date();
                            prod.vdate = now.getFullYear().toString() + "-"
                                       + now.getMonth().toString().padStart(2,"0") + "-"
                                       + now.getDate().toString().padStart(2,"0") + " "
                                       + now.getHours().toString().padStart(2,"0") + ":"
                                       + now.getMinutes().toString().padStart(2,"0") + ":"
                                       + now.getSeconds().toString().padStart(2,"0");
                        }
                        if (!prod.vdate) can_print = false;
                    })
                }
                getSum();
                if (can_print) $("#btnPrint").attr("disabled", null);
                else $("#btnPrint").attr("disabled", "disabled");
            });
        }
        function getdetails(ev=null){

            let button = ev ? $(ev.target) : $(".enter_number > button");
            button.attr("disabled", "disabled").text("searching...");
            window.location.href=window.location.protocol + "//" + window.location.host + "/?query=" + $('#query_field').val();
            // let query_str = $('#query_field').val();

            // $.ajax({
            //     type: "POST",
            //     url: "adapter.php",
            //     data: {query: query_str}
            // }).done(function( result ) {


            // });
        }
    </script>
</body>
</html>


