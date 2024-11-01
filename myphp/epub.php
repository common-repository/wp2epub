<?php

function logTexteBrut($msg){
	global $abspath;
	$handle=fopen($abspath."log.txt","a");
	if($handle) {
		fwrite($handle,$msg."\n");
		fclose($handle);
		return true;
	}
	exit("Gros bug log");
}

if(!empty($_POST)) extract($_POST);
if(!empty($_GET)) extract($_GET);
$file=$epub.".epub";

//echo("$file<br>");echo("$abspath<br>");exit;
//print_r($_SERVER);exit;

$msg=date("Y/m/d H:s")."\n";
$msg.=$file."\n";
$msg.=$_SERVER['REMOTE_ADDR']."\n";
$msg.=$_SERVER['HTTP_USER_AGENT']."\n";
$msg.="\n";
logTexteBrut($msg);

?>