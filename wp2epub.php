<?php

/*
Plugin Name: wp2epub
Plugin URI: http://blog.tcrouzet.com/wp2epub/
Description: wp2epub generate epub files, ready to publish, for iPad, iPhone and other readers. Just choose the tags, categories or dates to export. It's done. You are now a bloguer and a writer. wp2epub also export in html, and then you can open with a wordprocessor to convert into PDF or other formats. A good way to backup your blog.
Author: Thierry Crouzet
Version: 0.65
Author URI: http://blog.tcrouzet.com/
*/

// CHANGE THIS IF YOU WANT TO USE A DIFFERENT BACKUP LOCATION
define('WP_EPUB_DIR','wp-content/epub');
define('WP_EPUB_TABLE',$wpdb->prefix.'wp2epub');
define('WP_EPUB_SAVEDIR', ABSPATH.WP_EPUB_DIR.'/');
define('WP_EPUB_PLUGINURL',get_admin_url().'/tools.php?page=wp2epub');
define('WP_EPUB_OPTIONS',WP_EPUB_PLUGINURL.'&do_option=1');
define('WP_EPUB_IMAGES',plugins_url('images/',__FILE__));
define('WP_EPUB_VER',0.65);

add_action('admin_menu','wp2epub_admin_menu');
function wp2epub_admin_menu() {
	// Add a new submenu under tools
	add_management_page('wp2epub','wp2epub','edit_themes', basename(__FILE__), 'wp2epub_menu');
}

add_filter('plugin_action_links_'.plugin_basename(__FILE__),"wp2epub_addConfigureLink", 10, 2);
function wp2epub_addConfigureLink($links) { 
	$link = '<a href="tools.php?page=wp2epub.php">' . __('Settings') . '</a>';
	array_unshift( $links, $link ); 
	return $links;
}

function wp2epub_menu(){
	require_once("wp2epub.class.php");
	$epub=new wp2epub();
	$epub->maker();
}

add_action('save_post','wp2epub_cleancache');
function wp2epub_cleancache(){
	$dir=WP_EPUB_SAVEDIR."cache/";
	$handle=opendir($dir);
	if(!$handle) return false;
	while(false!==($obj=readdir($handle))){
		if($obj=='.'||$obj=='..') continue;
		@unlink($dir."/".$obj);
	}
	closedir($handle);
}

//Exemple d'appel [wp2epub text=0]
add_shortcode('wp2epub','wp2epub_button');
function wp2epub_button($atts){
	extract(shortcode_atts(array("text" => 1),$atts));
	$style=get_option('wp2epub_style');
	global $post;
	if($style==1){
		echo '<a href="/'.WP_EPUB_DIR.'/?epub='.$post->ID.'" class="w2epub">epub</a>';
	}elseif($style==2){
		echo '<a href="/'.WP_EPUB_DIR.'/?epub='.$post->ID.'" class="w2epub"><img src="'.WP_EPUB_IMAGES.'epub1.png" style="width:44px;height:20px;margin-bottom:0" title="Download epub"/></a>';
	}
}

function wp2epub_external($efile){
	require_once("wp2epub.class.php");
	$epub=new wp2epub();
	$epub->perform_externalexport($efile);
}

?>