<?php
$METANAME_API_ENDPOINT = 'https://metaname.nz/api/1.1';
include ('Metaname.php');
$account_reference = getenv ("account_reference");
$api_key = getenv ("api_key");
$metaname = new Metaname ($account_reference, $api_key);
?>
