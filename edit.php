<?php

require "vendor/autoload.php";
require "config.php";
if (isset($_POST['filling'])){
    $query = isset($_POST['query']) ? $_POST['query'] : "";
	$_store_loc = explode("/", $query)[0];
    if ($_store_loc) {
        $store_loc = str_pad($_store_loc, 6, "0", STR_PAD_LEFT);
        if (!is_dir(PATH2CSV)) throw new Exception("ERROR: tetris csv file path isn't exists!");
        $csv_file = fopen(PATH2CSV . CSV_FNAME, "r+");
        $row_exists = FALSE;
        $csv2save = array();
        while(($row = fgetcsv($csv_file)) !== FALSE) {
            if (!$row_exists && $row && $row[0] == $store_loc) {
            	$csv2save[] = [$store_loc, $_POST['filling']];
            	$row_exists = TRUE;
            } elseif ($row && array_key_exists(1, $row) && $row[0] != $store_loc) {
            	$csv2save[] = $row;
            }
        }
        rewind($csv_file);
        if (!$row_exists)
        	$csv2save[] = [$store_loc, $_POST['filling']];
        foreach ($csv2save as $row) {
        	fputcsv($csv_file, $row);
        }
        fclose($csv_file);
        $response = "Filling of storage " . $store_loc . " updated.";
	} else $response = "Filling of storage " . $store_loc . " NOT updated.";

} else {
	$postFields = array(
		'identifier' => 'guid',
		'guid' => isset($_POST['guid']) ? $_POST['guid'] : ''
	);
	if (isset($_POST['stock'])){
        $dt = new DateTime("now", new DateTimeZone('America/New_York'));
        $dt->setTimestamp(time());
		$postFields['stock'] = $_POST['stock'];
		$postFields['vdate'] = (string)$dt->format('Y-m-d H:i:s');
        unset($dt);
	} else {
		$postFields['storagelocation'] = isset($_POST['storagelocation']) ? $_POST['storagelocation'] : '';
	}
	// echo var_dump($postFields);
	// exit();
	$curl = curl_init();
	// if (!isset($_POST['guid']) || isset($_POST['stock'])){
	// 	throw new Exception("Recived unexpected post data!");
	// 	exit();
	// }

	// echo $_POST['guid'];
	// echo $_POST['stock'];
	// exit();
	curl_setopt_array($curl, array(
		CURLOPT_URL => "https://api.suredone.com/v1/editor/items/edit",
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING => "",
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_TIMEOUT => 30,
		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST => "POST",
		CURLOPT_SSL_VERIFYPEER => false,
		CURLOPT_SSL_VERIFYHOST => false,
		CURLOPT_POSTFIELDS => $postFields,
		CURLOPT_HTTPHEADER => array(
			"content-type: multipart/form-data",
			"x-auth-token: $token",
			"x-auth-user: $user"
		),
	));
	$response = curl_exec($curl);
	$err = curl_error($curl);
	curl_close($curl);
}
	// header('Content-Type: application/json');
if ($err)
	echo "cURL Error #:" . $err;
else
	echo $response;
?>
