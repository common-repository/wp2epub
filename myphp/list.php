<?php

ini_set("display_errors","1");
if(!empty($_GET)) extract($_GET);

/** Load WordPress Bootstrap in order to use classes*/
include_once('../../../../wp-config.php');
include_once('../../../../wp-load.php');
include_once('../../../../wp-includes/wp-db.php');

define('WP_EPUB_URL',$baseurl.'/wp-content/plugins/wp2epub/');
define('WP_EPUB_SAVEDIR_URL',$baseurl.'/wp-content/epub/');
define('WP_EPUB_SAVEDIR',$abspath);
require_once("../wp2epub.class.php");


/** liste file par alpha ou date_desc, date_asc */
function listDirPattern($dirname,$pattern,$sort=""){
	if($dirname[strlen($dirname)-1]!='\\') $dirname.='';
	$handle=opendir($dirname);
	if(!$handle){
		$this->error="Dir does not exist";
		return false;
	}
	$result_array=array();
	$pattern=str_replace(array("\*","\?","#"),array(".*",".","[0-9]+"),preg_quote($pattern));
	while(false!==($file=readdir($handle))){
		if(($file==".")||($file=="..")) continue;
		if(!ereg("^".$pattern."$",$file)) continue;
		if(eregi("date",$sort)){
			$date=filemtime($dirname.$file);
			$result_array[$date]=$file;
		}else{
			$result_array[]=$file;
		}
	}
	if(eregi("date_desc",$sort)){
		krsort($result_array);
	}elseif(eregi("date_asc",$sort)){
		ksort($result_array);
	}elseif(empty($sort))
		sort($result_array);
	else
		arsort($result_array);
	closedir($handle);
	return $result_array;
}

function outHTTP($ad) {
	return ereg_replace('http://','',$ad);
}

function echop($msg){
	echo '<pre>';
	print_r($msg);
	echo '</pre>';
}

class wp2epub_download{

	function __construct(){
	}
	
	function parse(){
		if(preg_match("/last/i",$_REQUEST['epub'])){
			//Last mode
			$exportname="last.epub";
			$filename="last".md5($_REQUEST['epub']);
			$this->is_incache_print($filename.".epub",$exportname);
			//Query
			list($limit,$tags)=explode("|",$_REQUEST['epub']);
			$limit=str_replace("last","",$limit);
			if($limit>0){
				//$this->init(false);
				if(!empty($tags)) $efile->epub_tags=$tags;
				$efile->epub_name=$filename;
				$efile->epub_dates="";
				$efile->epub_order=1;
				$efile->epub_limit=$limit;
				$efile->epub_french=1;
				$efile->epub_content=0;
				$efile->epub_cache=true;
				//$this->p($efile);exit;
			}else{
				$this->notfound();
			}
		}else{
			//Simple post
			$filename=$_REQUEST['epub'].".epub";
			$this->is_incache_print($filename);
			global $wpdb;
			$query="SELECT * FROM ".$wpdb->posts." WHERE ID='".$_REQUEST['epub']."' LIMIT 1";
			$post=$wpdb->get_row($query);
			if($post){
				//$this->init(false);
				$efile->epub_posts[]=$post;
				$efile->epub_cover="";
				$efile->epub_copyright="";
				$efile->epub_colophon="";
				$efile->epub_title=$post->post_title;
				$efile->epub_subtitle="";
				$efile->epub_author=get_the_author_meta("display_name",$post->post_author);
				$efile->epub_name=$post->ID;
				$imgcover->name=$this->findsrc(get_the_post_thumbnail($post->ID));
				$imgcover->tmp=$imgcover->name;
				$type=$this->findfiletype($imgcover->name);
				$imgcover->type=$type->ext;
				$efile->epub_postcover=serialize($imgcover);
				$efile->epub_cover=$efile->epub_postcover;
				$efile->epub_french=1;
				$efile->epub_content=0;
				$efile->epub_cache=true;
				//$this->p($efile);exit;
				$exportname=$post->ID.".epub";
			}else{
				$this->notfound();
			}
		}
		$e=new wp2epub();
		if($e->perform_externalexport($efile)){
			if($this->smartReadFile($e->okfile[0],$exportname)) exit;
		}
		$this->notfound();
	}

	function findsrc($html){
		//$this->p(htmlentities($html));
		preg_match('!src="(.*?)"!i',$html,$matches);
		return $matches[1];
		//$this->p($matches);exit("img");
	}

	function findfiletype($img){
		$filename=basename($img);
		$i->ext=strtolower(substr(strrchr($filename,"."),1));
		$i->name=str_replace(".".$i->ext,"",$filename);
		switch($i->ext){
			case "gif": $i->type="image/gif"; break;
			case "png": $i->type="image/png"; break;
			case "jpeg":
			case "jpg": $i->type="image/jpeg"; break;
		}
	
		return $i;
	}
	
	function is_incache_print($filename,$exportname=""){
		$cache=WP_EPUB_SAVEDIR."cache/".$filename;
		if(empty($exportname)) $exportname=$filename;
		if($this->smartReadFile($cache,$exportname)) exit;
		return false;
	}

	function notfound(){
		header ("HTTP/1.0 404 Not Found");
		exit;
	}

	function smartReadFile($location,$filename,$mimeType='application/epub+zip'){
		if(!file_exists($location)) return false;
		$size=filesize($location);
		$time=date('r',filemtime($location));
		$fm=@fopen($location,'rb');
		if(!$fm){
			header ("HTTP/1.0 505 Internal server error");
			return true;
		}
		$begin=0;
		$end=$size;
		if(isset($_SERVER['HTTP_RANGE'])){
			if(preg_match('/bytes=\h*(\d+)-(\d*)[\D.*]?/i', $_SERVER['HTTP_RANGE'], $matches)){
				$begin=intval($matches[0]);
				if(!empty($matches[1])) $end=intval($matches[1]);
			}
		}
		if($begin>0||$end<$size)
			header('HTTP/1.0 206 Partial Content');
		else
			header('HTTP/1.0 200 OK');
		header("Content-Type: $mimeType");
		header('Cache-Control: public, must-revalidate, max-age=0');
		header('Pragma: no-cache');
		header('Accept-Ranges: bytes');
		header('Content-Length:'.($end-$begin));
		header("Content-Range: bytes $begin-$end/$size");
		header("Content-Disposition: inline; filename=$filename");
		header("Content-Transfer-Encoding: binary\n");
		header("Last-Modified: $time");
		header('Connection: close');
		$cur=$begin;
		fseek($fm,$begin,0);
		while(!feof($fm)&&$cur<$end&&(connection_status()==0)){
			print fread($fm,min(1024*16,$end-$cur));
			$cur+=1024*16;
		}
		return true;
	}
	
}

if(!empty($epub)){
	//echop($_REQUEST);exit;
	$down=new wp2epub_download();
	$down->parse();
}

$epubs=listDirPattern($abspath,"*.epub");

$file="";
$msg="<ol>";
if(count($epubs)){
	foreach($epubs as $epub){		
		$msg.='<li><a href="http://'.outHTTP(WP_EPUB_SAVEDIR).$epub.'">'.$epub."</a>";
		if(!empty($file)){
			$msg.='<span class="hit">'.substr_count($file,$epub).'</span>';
		}
		$msg.="</li>\n";
	}
}else{
	$msg.='<p>No epub!!!</p>';
}
$msg.='</ol><p>Open the epubs with <a href="http://calibre-ebook.com/">Calibre</a>.</p>';

/**
$file=@file_get_contents($abspath."log.txt");
if(!empty($file)){

	$idsmall=substr_count($file,'idsmall.epub');
	$croisadesmall=substr_count($file,'croisadesmall.epub');
	$cinqth=substr_count($file,'5th.epub');
	$genius=substr_count($file,'genius.epub');
	$peuple=substr_count($file,'peuple.epub');
	$ea=substr_count($file,'ea.epub');
	//	 $epaper=substr_count($file,'epaper.epub');
}**/


$post = new stdClass;
$post->post_author = 1;
//$post->post_name = "";
//$post->guid = get_bloginfo('wpurl') . '/' . $this->page_slug;
$post->post_title ="Epub listing";
$post->post_content=$msg;;
$post->ID = 0;
$post->post_status = 'static';
$post->comment_status = 'closed';
$post->ping_status = 'closed';
$post->comment_count = 0;
$post->post_date = current_time('mysql');
$post->post_date_gmt = current_time('mysql', 1);

$wp_query->posts=array();
$wp_query->post_count = 1;
$wp_query->posts[]=$post;

$template=get_page_template();
load_template($template);
exit;
?>