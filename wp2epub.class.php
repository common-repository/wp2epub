<?php

class wp2epub{
	
	private $contener;
	private $context;
	private $log;
	public $okfile=array();
	private $totalsigns=0;
	private $totalcomnumber=0;
	private $totalcomsign=0;
	private $totalposts=0;
	
	function __construct(){
	}
	
	function init($log=true){
		$this->context=$this->proxy_getstreamcontext(trim(get_option('wp2epub_proxypsw')),trim(get_option('wp2epub_proxyurl')));
		$this->log=new wp2epub_log(WP_EPUB_SAVEDIR."wp2epub.log",$log);
	}

	function maker(){
		$this->debug=false;
		global $wpdb;
		
		//$this->p($_POST);
		if(!empty($_POST['do_options'])){
			$this->mem_option('wp2epub_proxypsw',$_POST['do_proxypsw']);
			$this->mem_option('wp2epub_proxyurl',$_POST['do_proxyurl']);
			$this->mem_option('wp2epub_maxtime',$_POST['do_maxtime']);
			if(isset($_POST['do_integration'])) $this->mem_option('wp2epub_integration',$_POST['do_integration']); else $this->mem_option('wp2epub_integration',0);
			if(isset($_POST['do_style'])) $this->mem_option('wp2epub_style',$_POST['do_style']); else $this->mem_option('wp2epub_style',0);
			$this->export_error("Options saved.");
			$this->menu();
		}elseif(isset($_GET['do_testinstall'])){
			$this->install();
			$this->menu();
		}elseif(isset($_GET['do_book'])){
			$this->menu_book($_GET['do_book']);
			exit;			
		}elseif(isset($_POST['do_bookid'])){
			ini_set("display_errors","1");
			//$this->p($_POST);
			
			//Looking for fields
			if(empty($_POST['do_name'])||empty($_POST['do_author'])||empty($_POST['do_title'])){
				//$mores=array();
				$this->export_error("At least, you must fill file name, author name, title!");
				$this->menu_book($_POST['do_book']);
				exit;
			}elseif($_POST['do_bookid']>0&&!empty($_POST['do_remouve'])){
				//Remove
				$query="DELETE FROM ".WP_EPUB_TABLE." WHERE epub_num='".$_POST['do_bookid']."'";
				if($wpdb->query($query)){
					$this->export_error("ePub remouved.");
				}
				$this->menu();
				exit;
			}
			
			//Save
			if(empty($_POST['do_order'])) $e->epub_order=0; else $e->epub_order=$_POST['do_order'];
			if(empty($_POST['do_com'])) $e->epub_com=0; else $e->epub_com=1;
			if(empty($_POST['do_nodates'])) $e->epub_nodates=0; else $e->epub_nodates=1;
			if(empty($_POST['do_french'])) $e->epub_french=0; else $e->epub_french=1;
			if(empty($_POST['do_content'])) $e->epub_content=0; else $e->epub_content=1;
			if(empty($_POST['do_source'])) $e->epub_source=0; else $e->epub_source=1;

			//Cover
			$img="";
			if(!empty($_FILES['do_cover']['name'])){
				$imgcover=$this->preloadImage($_FILES['do_cover']);
				if(!empty($imgcover->tmp)){
					rename($imgcover->tmp,WP_EPUB_SAVEDIR.$imgcover->name);
					$imgcover->tmp=WP_EPUB_SAVEDIR.$imgcover->name;
					$img=serialize($imgcover);
				}
			}

			$e->epub_author=stripslashes($_POST['do_author']);
			$e->epub_title=stripslashes(($_POST['do_title']));
			$e->epub_subtitle=stripslashes(($_POST['do_subtitle']));
			$e->epub_tags=stripslashes($_POST['do_tags']);
			$e->epub_dates=stripslashes($_POST['do_dates']);
			$e->epub_copyright=stripslashes($_POST['do_copyright']);
			$e->epub_colophon=stripslashes($_POST['do_colophon']);
			$e->epub_style=stripslashes($_POST['do_style']);
			$e->epub_intropost=stripslashes($_POST['do_intropost']);
			$e->epub_isbn=stripslashes($_POST['do_isbn']);
			//print_r($e);

			$set="epub_data='".addslashes(serialize($e))."'";
			if(!empty($img)) $set.=",epub_cover='$img'";
			$name=mb_strtolower(str_replace(array(" ","'"),"",$_POST['do_name']));
			if($_POST['do_bookid']>0){
				$query="UPDATE LOW_PRIORITY ".WP_EPUB_TABLE." SET epub_name='".mysql_real_escape_string($name)."',$set WHERE epub_num='".$_POST['do_bookid']."'";
				$wpdb->query($query);
				$this->perform_export($_POST['do_bookid']);
				$this->menu_book($_POST['do_bookid']);
			}else{
				$query="INSERT LOW_PRIORITY INTO ".WP_EPUB_TABLE." SET epub_name='".mysql_real_escape_string($name)."',$set";
				//$query.=" ON DUPLICATE KEY UPDATE $set;";
				$wpdb->query($query);
				if($wpdb->insert_id>0){
					$this->export_error("New book saved.");
					$this->menu_book($wpdb->insert_id);
				}else{
					$e->epub_data=serialize($e);
					$e->epub_name="";
					if(!$this->table_exists(WP_EPUB_TABLE)){
						$msg='<br/><p>Your data base <b>'.WP_EPUB_TABLE.'</b> does not exist! <a href="'.WP_EPUB_PLUGINURL.'&do_testinstall=1">Launch a new install...</a></p>';						
					}else{
						$msg="Impossible to save, duplicate <b>epub file name</b>. You have to use a unique \"epub file name\" for each of tour books. <b>$name</b> allready used.";
						$msg.='<br/><p>Or, other possibility, your data base <b>'.WP_EPUB_TABLE.'</b> is corrupted. Please destroy it with MySQLmanager, then <a href="'.WP_EPUB_PLUGINURL.'&do_testinstall=1">launch a new install...</a>.</p>';
					}
					$this->export_error($msg);
					$this->menu_book(0,$e);
				}
			}
		}else{
			$this->menu();
		}
	}
	
	function mem_option($name,$value=""){
		if(!add_option($name,$value)){
			update_option($name,$value);
		}
	}

	function table_exists($table){
		global $wpdb;
		$query="show tables like '$table'";
		if($wpdb->query($query)==1) return true; else return false;
	}

	function print_error(){
		if(count($this->export_errors)==0) return;
		echo '<div class="updated error">' . __('The following messages were reported:');
		foreach($this->export_errors as $error) {
			echo "<p>{$error}</p>\n";
		}
		echo "</div>";
	}
	
	function newdatabase(){
		global $wpdb;
		if(!$this->table_exists(WP_EPUB_TABLE)){
			$query="CREATE TABLE `".WP_EPUB_TABLE."` (`epub_num` int(11) NOT NULL auto_increment,`epub_name` VARCHAR( 20 ) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,`epub_data` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,`epub_cover` text character set ascii collate ascii_bin, PRIMARY KEY  (`epub_num`),UNIQUE (`epub_name`)) ENGINE = MYISAM;";
			$newtable=$wpdb->query($query);
			print_r($newtable);
			if(!$this->table_exists(WP_EPUB_TABLE)){
				$this->export_error(__('WARNING: Impossible do create database <strong>'.WP_EPUB_TABLE.'</strong>. Contact <a href="mailto:tc@tcrouzet.com">Thierry Crouzet</a>.'));
				return false;
			}
		}
		$this->export_error(__('Database '.WP_EPUB_TABLE.' OK.'));
		return true;
	}
	
	function install(){
		
		$this->init(false);
		
		$phpver=intval(phpversion());		
		if($phpver<5){
			$this->export_error(__('WARNING: Your server run PHP '.phpversion().'. You need PHP 5 or upper to run this plugin in good conditions. You can have bugs, but nothing fatal for your blog. Try!'));
		}

		if(!file_exists(WP_EPUB_SAVEDIR.'index.php')){
			if(!file_exists(WP_EPUB_SAVEDIR)){
				if(@mkdir(WP_EPUB_SAVEDIR)){
					// Give the new dirs the same perms as wp-content.
					$stat = stat( ABSPATH . 'wp-content' );
					$dir_perms = $stat['mode'] & 0000777; // Get the permission bits.
					@chmod(WP_EPUB_SAVEDIR,$dir_perms);
					$this->export_error(__(WP_EPUB_SAVEDIR.' OK'));
				}else{
					$this->export_error(__('WARNING: Your <strong>'.ABSPATH.'wp-content directory</strong> is <strong>NOT</strong> writable! We can not create the backup directory <strong>'.WP_EPUB_SAVEDIR.'</strong>. Use your FTP software to change the file mode.'));
					$this->print_error();
					echo "</div>";
					return false;
				}
			}
			if(!is_writable(WP_EPUB_SAVEDIR)){
				$this->export_error(__('WARNING: Your <strong>'.WP_EPUB_SAVEDIR.'</strong> directory is <strong>NOT</strong> writable! We can not create the <strong>epub</strong> directory. Use your FTP sofware to change the file mode.'));
				$this->print_error();
				echo "</div>";
				return false;
			}
			@touch(WP_EPUB_SAVEDIR."index.php");
		}else{
			$this->export_error(__(WP_EPUB_SAVEDIR.' OK'));
		}
		
		//Post cache
		$cache=WP_EPUB_SAVEDIR."cache/";
		if(!file_exists($cache)){
			if(@mkdir($cache)){
				// Give the new dirs the same perms as wp-content.
				$stat = stat( ABSPATH . 'wp-content' );
				$dir_perms = $stat['mode'] & 0000777; // Get the permission bits.
				@chmod($cache,$dir_perms);
			}
		}else{
			$this->export_error(__($cache.' OK'));
		}
		
		//Protect epub directory
		@unlink(WP_EPUB_SAVEDIR.'.htaccess');
		if(!file_exists(WP_EPUB_SAVEDIR.'.htaccess')){
			$handle=fopen(WP_EPUB_SAVEDIR.".htaccess","w");
			if($handle){
				fwrite($handle,"RewriteEngine on\n");
				fwrite($handle,"RewriteRule ^index\.php$ ".ABSPATH."wp-content/plugins/wp2epub/myphp/list.php?abspath=".WP_EPUB_SAVEDIR."&baseurl=".get_settings('siteurl')."&%{QUERY_STRING} [L]\n");
				fclose($handle);
			}else{
				$this->export_error(__('WARNING: Imposible to create .htaccess in '.WP_EPUB_SAVEDIR));
			}
		}
		
		//Log function
		$iframe=WP_EPUB_SAVEDIR."iframe.php";
		@unlink($iframe);
		if(!file_exists($iframe)){
			$handle=fopen($iframe,"w");
			if($handle){
				fwrite($handle,'<head><META HTTP-EQUIV="Refresh" CONTENT="60"></head><body><pre>'."\n");
				fwrite($handle,'<?php $a=file("wp2epub.log");if(count($a)==0) echo "Display log every minute...";else{$a=array_reverse($a);echo implode($a,"");}?>'."\n");
				fwrite($handle,'</pre></body>');
				fclose($handle);
				$this->export_error(__('.htaccess OK'));
			}else{
				$this->export_error(__('WARNING: Imposible to create iframe.php in '.WP_EPUB_SAVEDIR));
			}
		}

		//The first time create WP_EPUB_TABLE
		if(WP_EPUB_VER<0.17){
			//Drop table
			$query="DROP TABLE `".WP_EPUB_TABLE."`";
			$tmp=$wpdb->query($query);
			$this->export_error(__('Drop table with this version to a better one!!! Sorry!!!'));
			
		}
		
		//Database
		$this->newdatabase();
		
		//Test image import
		$opt=get_option('wp2epub_file_get_contents');
		$opt="";
		if(empty($opt)){
			$img=@file_get_contents("http://www.google.com/intl/fr/about/company/images/company-googlebeta.png",false,$this->context);
			if(empty($img)){
				$this->mem_option('wp2epub_file_get_contents',1);	//Pb
				$this->export_error(__('Images OK'));
				$opt=1;
			}else{
				$this->mem_option('wp2epub_file_get_contents',2);	//Fine
				//$this->export_error(__('WARNING: Importing images looks fine.'));
			}
		}
		if($opt==1){
			$this->export_error(__('WARNING: Your <strong>file_get_contents()</strong> PHP function is limited. You can have problems with images. <a href="'.WP_EPUB_OPTIONS.'">Look to proxy options.</a>'));
		}
	}
	
	function menu() {
		global $wpdb;

		//Versioning
		$ver=get_option('wp2epub_ver');
		if($ver!=WP_EPUB_VER||empty($ver)){
			$this->mem_option('wp2epub_ver',WP_EPUB_VER);
			echo '<p>You are running a new version:'.WP_EPUB_VER.'</p>';
			$this->install();
		}
				
		echo "<div class='wrap'>";
		echo '<h2>' . __('wp2epub') . '</h2>';
		echo 'Convert your blog to ePub by <a href="http://tcrouzet.com">Thierry Crouzet</a> (<a href="http://blog.tcrouzet.com/wp2epub/">plugin page</a>, <a href="http://wordpress.org/extend/plugins/wp2epub/">WordPress page</a>, version: '.WP_EPUB_VER.').';
		echo ' Warning: the process can take several minutes for a big epub. Be patient.<br/><br/>';
		
?><div style="border:1px solid #DDD;padding:10px"><h4>Give me a good reason to improve this plugin...</h4><form action="https://www.paypal.com/cgi-bin/webscr" method="post">
<input type="hidden" name="cmd" value="_s-xclick">
<input type="hidden" name="encrypted" value="-----BEGIN PKCS7-----MIIHHgYJKoZIhvcNAQcEoIIHDzCCBwsCAQExggEwMIIBLAIBADCBlDCBjjELMAkGA1UEBhMCVVMxCzAJBgNVBAgTAkNBMRYwFAYDVQQHEw1Nb3VudGFpbiBWaWV3MRQwEgYDVQQKEwtQYXlQYWwgSW5jLjETMBEGA1UECxQKbGl2ZV9jZXJ0czERMA8GA1UEAxQIbGl2ZV9hcGkxHDAaBgkqhkiG9w0BCQEWDXJlQHBheXBhbC5jb20CAQAwDQYJKoZIhvcNAQEBBQAEgYA/Lzm/DhATC/n4imjbD7p57z3MkO+njmriW93HDgo/mUXCSy/Gq6JCY5fjCf+816RxjMC48Y7j5e7TvxJfRIJRLnfSdwvzDyZ0YqTN3xVB7R8L2+AdPjNqwohMMwfpJiwrKb60JZM3KGPfdoA8awUAuX1tnLQkC1bLcuCalKr/BDELMAkGBSsOAwIaBQAwgZsGCSqGSIb3DQEHATAUBggqhkiG9w0DBwQIVcUMCSMPJKKAeJ1HXSHgR1K9RDuer1bYt/WK3WS2KXp2tTn/sk3HNuj4AtvXbLBJ3pufgRs3+Yt2z98wLya5lXjtMJUR3Xo4S02L+xxdrxq1rXunwbcvGb94HFo7PRCRApkpYG/FblQR9/DXXFc4/8wyfXAID8IX6V576RHQJeUhJKCCA4cwggODMIIC7KADAgECAgEAMA0GCSqGSIb3DQEBBQUAMIGOMQswCQYDVQQGEwJVUzELMAkGA1UECBMCQ0ExFjAUBgNVBAcTDU1vdW50YWluIFZpZXcxFDASBgNVBAoTC1BheVBhbCBJbmMuMRMwEQYDVQQLFApsaXZlX2NlcnRzMREwDwYDVQQDFAhsaXZlX2FwaTEcMBoGCSqGSIb3DQEJARYNcmVAcGF5cGFsLmNvbTAeFw0wNDAyMTMxMDEzMTVaFw0zNTAyMTMxMDEzMTVaMIGOMQswCQYDVQQGEwJVUzELMAkGA1UECBMCQ0ExFjAUBgNVBAcTDU1vdW50YWluIFZpZXcxFDASBgNVBAoTC1BheVBhbCBJbmMuMRMwEQYDVQQLFApsaXZlX2NlcnRzMREwDwYDVQQDFAhsaXZlX2FwaTEcMBoGCSqGSIb3DQEJARYNcmVAcGF5cGFsLmNvbTCBnzANBgkqhkiG9w0BAQEFAAOBjQAwgYkCgYEAwUdO3fxEzEtcnI7ZKZL412XvZPugoni7i7D7prCe0AtaHTc97CYgm7NsAtJyxNLixmhLV8pyIEaiHXWAh8fPKW+R017+EmXrr9EaquPmsVvTywAAE1PMNOKqo2kl4Gxiz9zZqIajOm1fZGWcGS0f5JQ2kBqNbvbg2/Za+GJ/qwUCAwEAAaOB7jCB6zAdBgNVHQ4EFgQUlp98u8ZvF71ZP1LXChvsENZklGswgbsGA1UdIwSBszCBsIAUlp98u8ZvF71ZP1LXChvsENZklGuhgZSkgZEwgY4xCzAJBgNVBAYTAlVTMQswCQYDVQQIEwJDQTEWMBQGA1UEBxMNTW91bnRhaW4gVmlldzEUMBIGA1UEChMLUGF5UGFsIEluYy4xEzARBgNVBAsUCmxpdmVfY2VydHMxETAPBgNVBAMUCGxpdmVfYXBpMRwwGgYJKoZIhvcNAQkBFg1yZUBwYXlwYWwuY29tggEAMAwGA1UdEwQFMAMBAf8wDQYJKoZIhvcNAQEFBQADgYEAgV86VpqAWuXvX6Oro4qJ1tYVIT5DgWpE692Ag422H7yRIr/9j/iKG4Thia/Oflx4TdL+IFJBAyPK9v6zZNZtBgPBynXb048hsP16l2vi0k5Q2JKiPDsEfBhGI+HnxLXEaUWAcVfCsQFvd2A1sxRr67ip5y2wwBelUecP3AjJ+YcxggGaMIIBlgIBATCBlDCBjjELMAkGA1UEBhMCVVMxCzAJBgNVBAgTAkNBMRYwFAYDVQQHEw1Nb3VudGFpbiBWaWV3MRQwEgYDVQQKEwtQYXlQYWwgSW5jLjETMBEGA1UECxQKbGl2ZV9jZXJ0czERMA8GA1UEAxQIbGl2ZV9hcGkxHDAaBgkqhkiG9w0BCQEWDXJlQHBheXBhbC5jb20CAQAwCQYFKw4DAhoFAKBdMBgGCSqGSIb3DQEJAzELBgkqhkiG9w0BBwEwHAYJKoZIhvcNAQkFMQ8XDTEzMDExODA4MzMyMVowIwYJKoZIhvcNAQkEMRYEFHCdZce5niIXXNoXKSxraPeItbNqMA0GCSqGSIb3DQEBAQUABIGAAsi/klp2hKNi8iD09t4JE7Z5z+gAOVsKY78j1Uezz+dKVHBvtAqD4bMz9hxoKpGRjugkmE/mLc3LJcYVS+GmLRtShcrIckFR67DMz93SAD35PTXDCbNhi7Tef5OtfWoJWY7fYCwVJ8UO8YTilyGQV5ccsOW8sZ2vO61qHRbUYBU=-----END PKCS7-----
">
<input type="image" src="https://www.paypalobjects.com/fr_FR/FR/i/btn/btn_donateCC_LG.gif" border="0" name="submit" alt="PayPal - la solution de paiement en ligne la plus simple et la plus s�curis�e !">
<img alt="" border="0" src="https://www.paypalobjects.com/fr_FR/i/scr/pixel.gif" width="1" height="1">
</form></div><?php

		//Maxtime
		if(!get_option('wp2epub_maxtime')) $this->mem_option('wp2epub_maxtime',20);
		
		//Errors
		if(count($this->export_errors)) {
			$this->print_error();
		}
		
		//Books selections
		$query="SELECT * FROM ".WP_EPUB_TABLE." ORDER BY epub_num ASC";
		$efiles=$wpdb->get_results($query);
		if($efiles){
			$c=count($efiles);
			if($c==1) $c="1 epub"; else $c="$c epubs";
			echo '<p><a href="'.get_settings('siteurl')."/".WP_EPUB_DIR.'"/index.php" target="_blank">Index of '.$c.'</a></p>';
			echo '<ol>'; 
			foreach($efiles as $e){
				$data=$this->formatdatas($e->epub_data);
				echo '<li><a href="'.WP_EPUB_PLUGINURL.'&do_book='.$e->epub_num.'">'.$data->epub_title.'</a></li>';
			}
			echo '</ol>';
			echo '<p><br/><a href="'.WP_EPUB_PLUGINURL.'&do_book=0"><b>Create a new book...</a></b></p>'; 				
		}else{
			echo '<p><a href="'.WP_EPUB_PLUGINURL.'&do_book=0"><b>Create your first book...</a></b></p>';
		}
		
		echo '<h2><br/>Settings</h2>';
		echo '<form action="'.WP_EPUB_PLUGINURL.'" method="post">';
		
		echo '<h4>Plugin integration into posts</h4>';
				
		$urltest="http://blog.tcrouzet.com/wp-content/epub/?epub=last10|-noepub,-Lifestream,-Photoblog";
		echo '<p>You can launch epub generation/download from a click on a URL.</p>';
		echo '<p>URL example: <a href="'.$urltest.'">'.$urltest.'</a></p>';
		echo '<p>URL syntax: <b>'.site_url().'/'.WP_EPUB_DIR.'?epub=x</b>, where x can be a post ID or a string such as "last12", to generate an epub dynamically with the last 12 posts.</p>';
		echo '<p>You can restrict epub generation to some tags and categories (or exclude some tags and categories using "-" before them). See example above.';
		echo '<p>The epub is saved in the plugin cache. When you publish a new post, the cache is deleted.</p>';
		$urltest=site_url().'/'.WP_EPUB_DIR.'?epub=last20';
		echo '<p>Test: <a href="'.$urltest.'">'.$urltest.'</a></p>';
		
		echo '<br/><p>You can use the microcode <b>[wp2epub]</b> in your posts. Just type the code in the post text. A link will be display at the top of the post.</p>';
		$style=get_option('wp2epub_style');
		echo 'Microcode button style: <select name="do_style">';
		echo '<option value="0" ';
		if($style==0) echo("selected");
		echo '>No button</option>';
		echo '<option value="1" ';
		if($style==1) echo("selected");
		echo '>Text</option>';
		echo '<option value="2"';
		if($style==2) echo("selected");
		echo '>Picture</option>';
		echo '</select>';
		
		echo '<h4>If you are behind a proxy</h4>';
		echo 'Proxy psw: <input type="text" name="do_proxypsw" value="'.get_option('wp2epub_proxypsw').'"><br/>';
		echo 'Proxy URL: <input type="text" name="do_proxyurl" value="'.get_option('wp2epub_proxyurl').'"><br/>';

		echo '<h4>Miscellaneous</h4>';
		echo 'Max execution time (in minutes): <input type="text" name="do_maxtime" value="'.get_option('wp2epub_maxtime').'"><br/>';
		echo '<p class="submit"><input type="submit" name="do_options" value="Save" / ></p>';
		echo '</form>';
		
		echo '<p><a href="'.WP_EPUB_PLUGINURL.'&do_testinstall=1">Test install...</a></p>';
					
		echo '</div>';
		
	}// end menu()
	
	function menu_book($bookid,$inpute="") {
		global $wpdb;
		$e="";
		if($bookid>0){
			$query="SELECT * FROM ".WP_EPUB_TABLE." WHERE epub_num=$bookid";
			$e=$wpdb->get_row($query);
			//$this->p($e);
		}elseif(!empty($inpute)){
			$e=$inpute;
		}
		
		//Errors
		$this->print_error();
		
		//did we just do a backup?  If so, let's report the status
		if(count($this->okfile)>0){
			echo '<div class="updated"><p>' . __('ePub generation successful') . '!</p>';
			echo '<p>' . __('Your ePub backup file has been saved on the server at '.date('H:i \t\h\e Y-m-d').'. If you would like to download it now, right click and select "Save As"</p>');
			foreach($this->okfile as $file){
				$filename=str_replace(WP_EPUB_SAVEDIR,"",$file);
				echo '<p><a href="'.get_settings('siteurl').'/'.WP_EPUB_DIR."/$filename\">$filename</a> : " . sprintf(__('%s bytes'), filesize($file))."</p>";
			}
			echo '</div>';
		}		
		
		echo '<form action="'.WP_EPUB_PLUGINURL.'" enctype="multipart/form-data" method="post">';
		echo '<input type="hidden" name="MAX_FILE_SIZE" value="'.$this->return_bytes(ini_get('upload_max_filesize')).'"/>';
		echo '<input type="hidden" name="do_bookid" value="'.$bookid.'">';
		echo '<table style="border:none;border-collapse:collapse;width:100%">';
		$this->form_line($e,"#DDD");				
		echo '</table>';
		echo '</form>';
		
		echo '<iframe src="" id="hidden_div" style="display:none;width:95%;height:150px;border:1px solid #DDD;"></iframe>';
		
		echo '<h3>Help!</h3><ol>';
		echo '<li>ePub file name must be unique, no space.</li>';
		echo '<li>Author must be filled.</li>';
		echo '<li>Title is the name of the book. Example: The Da Vinci Code :-)</li>';
		echo '<li>Sub title only if you want.</li>';
		echo '<li>List tags or categories separated by comas. Insert a - before a tag or a category to exclude it. Example: "politics,-USA" will include all the posts with the tag politics but not those with USA even if they have the tag politics.</li>';
		echo '<li>You can filter by date. The dates must be separated by comas. Examples: "2010" or "2008,2009" or "2010:05" or "2010:05:30". You can also exclude dates with -. Example: "2010,-2010:01".</li>';
		echo '<li>Check to publish the comments.</li>';
		echo '<li>With no dates checked, there is no date for the posts.</li>';
		echo '<li>French will print dates in French.</li>';
		echo '<li>Content will build a complete table of contents with years and months. If not, just the post titles.</li>';
		echo '<li>Source will print the permalink at the end of the post.</li>';
		echo '<li>You can have a post placed at the beginning of the book. Insert the post number ID.</li>';
		echo '<li>The copyright page is the first page after the cover. You can use HTML and fields: [title], [subtitle], [blog], [author], [blogurl], [year], [today], [signs], [comments], [commentsigns], [posts], [pages].</li>';		
		echo '<li>The colophon page is the last page of the book.</li>';
		echo '<li>You can upload a picture for your cover.</li>';
		echo '<li>If you want a new style sheet, copy the original <b>'.$this->style_dir().'style.css</b> in the same directory (with a new name). Now, make changes. It will appear in the list.</li>';
		echo '<li>By default posts will be published chronologically starting with the oldest. You have 4 sort modes.</li>';
		echo '<li>The first time, click on <b>Save</b> to save your book settings. Then click on <b>Save & generate</b> to build the epub file.</li>';
		echo '<li>Wait. It can take a long time. The ePub is building. To debug, click on <b>Show log</b> before launching the generation to follow what is happening.</li>';
		echo '<li>On Windows, Mac OS or Linux, download ePubs and edit them on <a href="http://code.google.com/p/sigil/">Sigil</a>, view them on <a href="http://calibre-ebook.com/">Calibre</a>, a tablet or a reader (Kindle via Calibre).</li>';
		echo '<li>Validate your ePub with <a href="http://threepress.org/document/epub-validate/">epubChecker</a>, <a href="http://code.google.com/p/epubcheck/">ePubCheck</a> or <a href="http://code.google.com/p/flightcrew/">FlightCrew</a>. Warning: you can have problems in your posts.</li>';
		echo '<li>Unzip the zip file and you can open the htm file with a word processor and convert it to PDF or other formats.</li>';
		echo '</ol>';
		echo '<h3>Tips</h3><ol>';
		echo '<li>Tag the posts you do not want to export with a tag, "noepub" for example, and create a filter on it "-noepub".</li>';
		echo '<li>If the generation bugs because of a memory overflow, export year by year.</li>';
		echo '</ol>';
				
		echo '<script type="text/javascript">';
		//echo "\n";
		echo "function show() {var div=document.getElementById('hidden_div');div.style.display='';div.src='".get_settings('siteurl')."/".WP_EPUB_DIR."/iframe.php';}\n";
		echo '</script>'."\n";;
	}// end menu_book()

	function form_line($e="",$bak){
		if(!empty($e)){
			//print_r($e->epub_data);
			$efile=$this->formatdatas($e->epub_data);
			if(!empty($e->epub_name)){
				$file=WP_EPUB_SAVEDIR.$e->epub_name.'.epub';
				$testfile=file_exists($file);
				$book='Book #'.$e->epub_num;
				$imgcover=unserialize($e->epub_cover);
				//$this->p($imgcover);
				if(empty($imgcover->name)){
					$testimg=false;
				}else{
					$fileimg=WP_EPUB_SAVEDIR.$imgcover->name;
					$testimg=file_exists($fileimg);
				}
				$e->epub_num="_".$e->epub_num;
				$button="Save & generate";
			}else{
				//Modification after input bug
				$testfile=false;
				$book="New Book";
				$testimg=false;
				$e->epub_num="";
				$button="Save";
			}
		}else{
			$testfile=false;
			$book="New Book";
			$testimg=false;
			$e->epub_num="";
			$efile->epub_copyright="<h1>[title]</h1>\n<h2>[blog]</h2>\n<h3>[author]</h3>\n<p>&copy; <a href=\"[blogurl]\">[author]</a> - [year]</p>";
			$efile->epub_colophon="<p><br /><br /><br />printed the [today] by wp2epub</p><br/><br/>Total signs: [signs]<br/>Book pages: [pages]";
			$button="Save";
		}
		$td=' style="width:95%"';
		
		echo '<tr style="background:'.$bak.'"><td colspan="4" style="width:100%;padding-top:15px;padding-bottom:10px"><b>'.$book.'</b>';
		if($testfile){
			echo ' <a href="'.get_settings('siteurl').'/'.WP_EPUB_DIR.'/'.$e->epub_name.'.epub">'.$e->epub_name.'.epub</a>';
			$filemhtl=WP_EPUB_SAVEDIR.$efile->epub_name.'.zip';
			if($filemhtl) echo ' <a href="'.get_settings('siteurl').'/'.WP_EPUB_DIR.'/'.$e->epub_name.'.zip">'.$e->epub_name.'.zip</a>';
		}
		echo '</td></tr><tr style="background:'.$bak.'">';
		echo '<td>ePub file name*:<br/><input type="text" name="do_name" value="'.$e->epub_name.'" '.$td.'></td>';
		echo '<td>Author*:<br/><input type="text" name="do_author" value="'.$efile->epub_author.'" '.$td.'></td>';
		echo '<td>Title*:<br/><input type="text" name="do_title" value="'.$efile->epub_title.'"'.$td.'></td>';
		echo '<td>Sub title:<br/><input type="text" name="do_subtitle" value="'.$efile->epub_subtitle.'"'.$td.'></td>';
		echo '</tr><tr style="background:'.$bak.'">';
		echo '<td>Tags (ex: "politics,-USA"):<br/><input type="text" name="do_tags" value="'.$efile->epub_tags.'"'.$td.'></td>';
		echo '<td>Dates (ex: "2010,-2010:01"):<br/><input type="text" name="do_dates" value="'.$efile->epub_dates.'"'.$td.'></td>';
		echo '<td colspan="2">';
		echo ' Comments <input type="checkbox" name="do_com" value="1" ';
		if($efile->epub_com==1) echo('checked');
		echo'/>';
		echo ' No dates <input type="checkbox" name="do_nodates" value="1" ';
		if($efile->epub_nodates==1) echo('checked');
		echo'/>';
		echo ' French <input type="checkbox" name="do_french" value="1" ';
		if($efile->epub_french==1) echo('checked');
		echo'/>';
		echo ' Content <input type="checkbox" name="do_content" value="1" ';
		if($efile->epub_content==1) echo('checked');
		echo'/>';
		echo ' Source <input type="checkbox" name="do_source" value="1" ';
		if($efile->epub_source==1) echo('checked');
		echo'/>';
		echo ' Introduction post number:<input type="text" name="do_intropost" value="'.$efile->epub_intropost.'" style="width:50px">';
		echo ' ISBN:<input type="text" name="do_isbn" value="'.$efile->epub_isbn.'" style="width:140px">';
		echo '</td>';
		echo '</tr><tr style="background:'.$bak.'">';
		echo '<td>Copyright page:<br/><textarea rows="5" name="do_copyright" '.$td.'>'.$efile->epub_copyright.'</textarea></td>';
		echo '<td>Colophon page:<br/><textarea rows="5" name="do_colophon" '.$td.'>'.$efile->epub_colophon.'</textarea></td>';
		
		//Cover
		echo '<td valin="top">';
		echo 'Cover PNG/JPG, max size '.ini_get('upload_max_filesize').':<br/>';
		echo '<input type="file" name="do_cover" id="do_cover'.$e->epub_num.'"><br/>';
		if($testimg){
			echo '<a href="'.get_settings('siteurl').'/'.WP_EPUB_DIR.'/'.$imgcover->name.'" target="_blanc">'.$imgcover->name.'</a><br/>';
		}
		
		echo 'Styles: <select name="do_style">';
		$styles=$this->listDirPattern($this->style_dir(),"*.css");
		foreach($styles as $style){
			echo '<option value="'.$style.'"';
			if($style==$efile->epub_style) echo("selected");
			echo '>'.$style.'</option>';
		}
		echo '</select>';

		echo ' Sort: <select name="do_order">';
		echo '<option value="0" ';
		if($efile->epub_order==0) echo("selected");
		echo '>Chronological</option>';
		echo '<option value="1"';
		if($efile->epub_order==1) echo("selected");
		echo '>Reverse Chronological</option>';
		echo '<option value="2"';
		if($efile->epub_order==2) echo("selected");
		echo '>Alphabetical</option>';
		echo '<option value="3"';
		if($efile->epub_order==3) echo("selected");
		echo '>Reverse Alphabetical</option>';
		echo '</select>';
		
		echo '</td>';
		echo '<td>';
		if($testimg) echo '<img src="'.get_settings('siteurl').'/'.WP_EPUB_DIR.'/'.$imgcover->name.'" style="width:100px"/>';
		echo '</td>';
		echo '</tr>';
		
		echo '<tr style="background:'.$bak.'"><td colspan="4" style="width:100%;padding-bottom:15px">';
		echo ' <input type="submit" name="do_base" value="'.$button.'" /> ';
		if(!empty($e->epub_num)) echo '<input type="submit" name="do_remouve" value="Remouve" />';
		echo ' <a href="javascript:void(0)" onclick="show()";>Show log</a> | <a href="'.WP_EPUB_PLUGINURL.'">Book list/Settings</a>';
		echo '</td></tr>';
	}
	
	function export_error($err) {
		if(count($this->export_errors) < 20) {
			$this->export_errors[] = $err;
		} elseif(count($this->export_errors) == 20) {
			$this->export_errors[] = __('Subsequent errors have been omitted from this log.');
		}
	}

	/** lise file par alpha ou date_desc, date_asc */
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

	/************************
	 * real code start here *
	 ************************/

	//Post integration
	function post_integration($content){
	}

	function perform_export($bookid=""){
		global $wpdb;
		$this->init();
		set_time_limit(get_option('wp2epub_maxtime')*60);
		if($this->return_bytes(ini_get('memory_limit'))<$this->return_bytes("256M")){
			ini_set("memory_limit","512M");
		}
		if($bookid>0){
			$query="SELECT * FROM ".WP_EPUB_TABLE." WHERE epub_num='$bookid' LIMIT 1";
			$e=$wpdb->get_row($query);
			//$this->p($e);
			if($e){
				$efile=$this->formatdatas($e->epub_data);
				$efile->epub_num=$e->epub_num;
				$efile->epub_name=$e->epub_name;
				$efile->epub_cover=$e->epub_cover;
				//print_r($efile);
				if($this->make_file($efile)) $this->make_htm_word($efile);
			}else{
				$this->export_error(__('No epub file denifined!'));
			}
		}else{
			$this->export_error(__('No epub file denifined!'));
		}
	}
	
	//To use with other plugins
	function perform_externalexport($efile){
		$this->init();
		return $this->make_file($efile);
	}
	
	function formatdatas($e){
		//echo("<hr>$e<br>");
		$efile=unserialize($e);
		//print_r($efile);
		return $efile; 
	}
	
	//
	function var_substitution($copy,$efile){
		$copy=str_replace("[title]",$efile->epub_title,$copy);
		$copy=str_replace("[subtitle]",$efile->epub_subtitle,$copy);
		$copy=str_replace("[blog]",$this->outHTTP(get_settings('siteurl')),$copy);
		$copy=str_replace("[blogurl]",get_settings('siteurl'),$copy);
		$copy=str_replace("[author]",$efile->epub_author,$copy);
		$copy=str_replace("[year]",date("Y"),$copy);
		$copy=str_replace("[today]",date("Y-n-j"),$copy);
		$copy=str_replace("[signs]",$this->totalsigns,$copy);
		$copy=str_replace("[comments]",$this->totalcomnumber,$copy);
		$copy=str_replace("[commentsigns]",$this->totalcomsign,$copy);
		$copy=str_replace("[posts]",$this->totalposts,$copy);
		$pages=intval($this->totalsigns/2000);
		$copy=str_replace("[pages]",$pages,$copy);
		return $copy;
	}
	
	function format_post($post,$efile,$normal=true){
		global $wpdb;
		$this->totalsigns+=strlen(strip_tags($post->post_title.$post->post_content));
		$this->contener->set("");
		$contener_tmp="";
		
		$title=$this->clean_title($post->post_title);
		$this->log->e(" PostID:".$post->ID." ".$title);
		$this->title->set($title);
		$contener_tmp='<h1 class="main">'.$title."</h1>\n";
		unset($title);
		
		$tmpstp=strtotime($post->post_date);
		if($normal){
			$this->year[$this->contener->index()]=date("Y",$tmpstp);
			$this->month[$this->contener->index()]=$this->traducdate(date("F",$tmpstp),$efile->epub_french);
		}
		if(!$efile->epub_nodates&&$normal){
			$contener_tmp.='<div class="soustitre">';
			$contener_tmp.=$this->traducdate(date("l j F Y",$tmpstp),$efile->epub_french);
			$contener_tmp.="</div>\n";
		}
		$contener_tmp.=$this->format_text($post->post_content);
		unset($this->fout);
		if(empty($contener_tmp)) continue;
		
		//Source link
		if($efile->epub_source==1){
			//print_r($post);exit;
			$contener_tmp.="\n<p><a href=\"".str_replace("http://www.tcrouzet.com/wordpress","http://blog.tcrouzet.com",$post->guid)."\">Source...</a></p>";
		}
		
		//$this->log->en($contener_tmp);
		
		//Comments
		if($efile->epub_com==1){
			$wherecom="comment_post_ID='$post->ID'";
			$query="SELECT * FROM $wpdb->comments WHERE $wherecom ORDER BY comment_date ASC";
			$comments=$wpdb->get_results($query);
			if($comments){
				$comnumber=0;
				$msg="";
				foreach($comments as $key => $c){
					$this->log->e(".");					
					if(eregi("This comment was originally posted",$c->comment_content)) continue;
					if(eregi("Topsy.com",$c->comment_author)) continue;						
					$comnumber++;
					$msg.="\n<h4>".strip_tags($c->comment_author)."</h4>\n".$this->format_text(strip_tags($c->comment_content));
				}
				$contener_tmp.="\n<hr /><h4>".$comnumber." commentaires</h4>\n".$msg;
				$this->totalcomnumber+=$comnumber;
				$this->totalcomsign+=mb_strlen($msg);
				unset($msg,$comnumber,$c,$key);
			}
			unset($wherecomn,$query,$comments);
		}
		$this->contener->add($contener_tmp);
		unset($contener_tmp);
	}

	function make_file($efile){
		global $wpdb;
		
		require_once("simplehtmldom/simple_html_dom.php");
		
		$this->imgindex=1;
		$this->imgtab=array();
		$this->contener=new wp2epub_mysqlmem("contener");
		$this->year=array();
		$this->month=array();
		$this->title=new wp2epub_mysqlmem("title");
		$this->totalsigns=0;
		
		$this->ncom=0;		//Comments overall number
			
		//Cover
		if(!empty($efile->epub_cover)){
			$imgcover=unserialize($efile->epub_cover);
			$this->cover=$this->cover_make($imgcover);
		}elseif(!empty($efile->epub_title)){
			$this->cover='<div class="first">';
			$this->cover.='<h1>'.$efile->epub_title."</h1>\n";
			$this->cover.='<h4>'.$efile->epub_subtitle.'</h4>';
			$this->cover.='<h3>'.$efile->epub_author."</h3>\n";
			$this->cover.="</div>\n";
			$this->cover=$this->ops_maker($this->cover,$efile);
		}else{
			$this->cover="";
		}
		
		//Copyright page
		$this->copyright=$this->var_substitution($efile->epub_copyright,$efile);
		if(!empty($this->copyright)) $this->copyright='<div class="first">'.$this->var_substitution($efile->epub_copyright,$efile)."</div>\n";
		
		//Introduction post
		if($efile->epub_intropost>0){
			$post=$this->get_one_post($efile->epub_intropost);
			if($post){
				$this->format_post($post,$efile,false);
				$this->new_page();
				unset($post);
			}
		}
		
		//Post by post
		if(count($efile->epub_posts)){
			//For external use only
			foreach($efile->epub_posts as $post){
				//$this->p($post->ID);
				$this->format_post($post,$efile);
				$this->new_page();
			}			
		}else{
			$query=$this->make_query($efile);
			$key=0;
			$post=$wpdb->get_row($query,OBJECT,$key);
			while($post){
				$key++;
				$this->log->en("");
				$this->log->e($key,true);
				$this->format_post($post,$efile);
				$this->new_page();
				$post=$wpdb->get_row($query,OBJECT,$key);
			}
			if($key==0){
				$this->export_complete = false;
				$this->export_error(__('No post find! '.$query));
				return;
			}else{
				$this->totalposts=$key;
			}
		}
		
		//Colophon
		$this->colophon=$this->var_substitution($efile->epub_colophon,$efile);
		if(!empty($this->colophon)) $this->colophon='<div class="last">'.$this->var_substitution($efile->epub_colophon,$efile)."</div>\n";
		
		if($this->debug) exit;
		
		$this->epub_title=$efile->epub_title;
		$this->epub_author=$efile->epub_author;
		$this->epub_french=$efile->epub_french;
		$this->okfile[]=$this->make_epub($efile);
		$this->log->en("\r\n*** make_file end ***");
		return true;
	}

	//+++
	function make_query($efile){
		global $wpdb;

		$ids=array();
		$exclude=array();
		$names=array();
		$tags=trim($efile->epub_tags);
		$tags=trim($tags,",");
		$tags=trim($tags);
		$tags=explode(",",$tags);
		if(count($tags)){
			$q="";
			foreach($tags as $tag){
				$originaltag=trim($tag);
				$tag=trim($tag,"-");
				if($originaltag!=$tag) $exclude[]=$tag;
				if(empty($tag)) continue;
				if(!empty($q)) $q.=" OR ";
				$q.="name='".addslashes(trim($tag))."'";
			}
			//echo($q."<br>");
			if(!empty($q)){
				$query="SELECT term_taxonomy_id,name FROM ".$wpdb->terms.",".$wpdb->term_taxonomy." WHERE ".$wpdb->terms.".term_id=".$wpdb->term_taxonomy.".term_id AND ($q)";
				//echo($query."<br>");
				$term_taxonomy_id=$wpdb->get_results($query);
				foreach($term_taxonomy_id as $id){
					$ids[]=$id->term_taxonomy_id;
					$names[$id->term_taxonomy_id]=$id->name;
				}
				//print_r($ids);
				//print_r($names);
			}
		}
		
		$total=count($ids);
		$termouts=array();
		$neototal=$total;
		$qterms="";
		if($total>0){
			$qterms="";
			$first=true;
			foreach($ids as $id){
				if(!$first) $qterms.=" OR ";
				if(in_array($names[$id],$exclude,true)) {$neototal--;$termouts[]=$id;}
				$qterms.="term_taxonomy_id='".$id."'";
				$first=false;
			}
		}
		
		//print_r($exclude);print_r($ids);print_r($termouts);echo("$total $neototal<br>");
		if($total==1&&$neototal==1){
			$query="SELECT * FROM ".$wpdb->term_relationships.",".$wpdb->posts." WHERE object_id=ID AND ($qterms) AND ";
		}elseif($total>0&&$neototal>0){
			$query="SELECT *,COUNT(DISTINCT term_taxonomy_id) as termsnbr FROM ".$wpdb->term_relationships.",".$wpdb->posts." WHERE object_id=ID AND ($qterms) AND ";
		}elseif($total>0&&$neototal==0){
			$query="SELECT *,GROUP_CONCAT(DISTINCT term_taxonomy_id SEPARATOR ',') AS termsids FROM ".$wpdb->term_relationships.",".$wpdb->posts." WHERE object_id=ID AND ";
		}else{
			$query="SELECT * FROM ".$wpdb->posts." WHERE ";
		}
		$query.="post_status='publish' AND post_type='post'";

		//$query.=" AND ID='16298'";			//test on a single post		
		
		//Dates
		if(!empty($efile->epub_dates)){
			$dates=explode(",",$efile->epub_dates);
			$dquery="";
			foreach($dates as $date){
				list($y,$m,$d)=explode(":",$date);
				if(empty($y)) continue;
				$ny=trim($y,"-");
				if($ny!=$y) {$like="NOT LIKE";$op=" AND ";} else {$like="LIKE";$op=" OR ";}
				$y=$ny;
				$dd=$y."-";
				if(!empty($m)){
					$dd.=$m."-";
					if(!empty($d)) $dd.=$d;
				}
				$dd.="%";
				if(empty($dquery)) $dquery.=" ("; else $dquery.=$op;
				$dquery.="post_date ".$like." '$dd'";
			}
			if(!empty($dquery)){
				$query.=" AND ".$dquery.")";
			}
		}
		
		if($total==1&&$neototal==1){
		}elseif($total>0&&$neototal==0){
			$tmp="";
			foreach($termouts as $term){
				//$query.=" AND term_taxonomy_id<>$term";
				if(!empty($tmp)) $tmp.=" AND ";
				$tmp.="NOT FIND_IN_SET('$term',termsids)";
			}
			//$query.=" GROUP BY ID";
			$query.=" GROUP BY ID HAVING $tmp";
		}elseif($total>0){
			$qg="";
			if($neototal>0) $qg="termsnbr=$neototal";
			foreach($termouts as $term){
				if(!empty($qg)) $qg.=" AND ";
				$qg.="term_taxonomy_id<>$term";
			}
			$query.=" GROUP BY ID";
			if(!empty($qg)) $query.=" HAVING $qg";			 
		}
		
		//Sort order
		switch($efile->epub_order){
			case "0": $query.=" ORDER BY post_date_gmt ASC"; break;
	     	case "1": $query.=" ORDER BY post_date_gmt DESC"; break;
	     	case "2": $query.=" ORDER BY post_title ASC"; break;
	     	case "3": $query.=" ORDER BY post_title DESC"; break;
		}

		if(!empty($efile->epub_limit)){
			$query.=" LIMIT ".$efile->epub_limit;
			$query="($query) ORDER BY post_date_gmt ASC";
		}
		
		$this->log->en($query);
		unset($ids,$exclude,$names,$tags,$qg,$tmp,$dquery,$y,$ny,$qterms,$first,$originaltag);
		return $query;		
	}
	
	function get_one_post($id){
		global $wpdb;
		//$query="SELECT * FROM ".$wpdb->term_relationships.",".$wpdb->posts." WHERE ID='$id'";
		$query="SELECT * FROM ".$wpdb->posts." WHERE ID='$id'";
		return $wpdb->get_row($query);
	}
	
	function style_dir(){
		return ABSPATH."wp-content/plugins/wp2epub/css/";
	}

	function style_path($efile){
		return $this->style_dir().$efile->epub_style;
	}

	function make_style($efile){
		return @file_get_contents($this->style_path($efile));
	}
	
	//Epub
	function make_epub($efile){

		//Prepare zip archive
		$output_name=$efile->epub_name.".epub";
		if(isset($efile->epub_cache))
			$this->export_file=WP_EPUB_SAVEDIR."cache/".$output_name;
		else
			$this->export_file=WP_EPUB_SAVEDIR.$output_name;
		$epub=new wp2epub_zip($this->export_file);
		
		//Start of the archive, no compress
		$epub->add_content('application/epub+zip','mimetype',false);
	
		//META-INF
		$epub->add_content($this->make_container(),'META-INF/container.xml');

		//Cover +++
		$imgcover="";
		if(!empty($efile->epub_postcover)){
			$imgcover=unserialize($efile->epub_postcover);
			$epub->add_content(@file_get_contents($imgcover->name),'OEBPS/images/imgcover.'.$imgcover->type);
		}elseif(!empty($efile->epub_cover)){
			$imgcover=unserialize($efile->epub_cover);
			$epub->add_content(@file_get_contents(WP_EPUB_SAVEDIR.$imgcover->name),'OEBPS/images/imgcover.'.$imgcover->type);
		}else{
			$imgcover="";
		}
		if(!empty($this->cover)) $epub->add_content($this->cover,'OEBPS/text/cover.xhtml');
		
		//Copyright
		if(!empty($this->copyright)) $epub->add_content($this->ops_maker($this->copyright,$efile),'OEBPS/text/copyright.xhtml');
		
		//Images
		foreach($this->imgtab as $key => $value){
			$img=@file_get_contents($this->localise_file($value),false,$this->context);
			$ext=$this->imgxtension($value);
			if(empty($img)){
				$this->log->en("\r\n".WP_PLUGIN_DIR."images/v.png");
				$img=@file_get_contents(WP_PLUGIN_DIR."images/v.png");
				$ext="png";
			}
			$epub->add_content($img,'OEBPS/images/'.$key.'.'.$ext);
			unset($img);
		}
		
		//OEBPS
		$epub->add_content($this->ncx_maker($efile),'OEBPS/toc.ncx');					//Navigation, table of contents
		$epub->add_content($this->opf_maker($efile,$imgcover),'OEBPS/content.opf');		//Files listing
		foreach($this->contener->indexs() as $key){
			$ops=$this->contener->get($key);
			//$this->log->en($ops);
			$epub->add_content($this->ops_maker($ops,$efile),'OEBPS/text/book'.str_pad($key,4,"0",STR_PAD_LEFT).'.xhtml');
			unset($ops);
		}
		
		//Table of content
		if(!empty($this->toc_html)) $epub->add_content($this->ops_maker($this->toc_html,$efile),'OEBPS/text/toc.xhtml');
		
		//Colophon
		if(!empty($this->colophon)) $epub->add_content($this->ops_maker($this->colophon,$efile),'OEBPS/text/colophon.xhtml');
		
		//CSS
		$epub->add_content($this->make_style($efile),'OEBPS/styles/main.css');
		
		//finish it up and download
		if(isset($efile->epub_directopen)){
			//Direct output
			$output_file=$epub->output();
			$output_mime='application/epub+zip';
			$output_name=$efile->epub_name;
			$output_name.=".epub";
			header('Content-Type: application/x-download');
			header('Content-Length: '. strlen($output_file));
			header('Content-Disposition: attachment; filename="' . $output_name . '"');
			header('Content-Transfer-Encoding: binary');
			echo $output_file;
		}else{
			//File
			if($epub->save()){
				$this->export_complete=true;
				return $this->export_file;
			}else{
				$this->export_file="";
				$this->export_error(__('Could not save '.$this->export_file));
				return false;
			}
		}
	}

	//Word
	function make_htm_word($efile){

		//Prepare zip archive
		$output_name=$efile->epub_name.".zip";
		$this->export_file=WP_EPUB_SAVEDIR.$output_name;
		//return $this->okfile[]=$this->export_file;
		$epub=new wp2epub_zip($this->export_file);
		
		$doc=new wp2epub_diskmem(); 
				
		$msg="";
		$msg.='<html xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns:m="http://schemas.microsoft.com/office/2004/12/omml" xmlns="http://www.w3.org/TR/REC-html40">';
		$msg.="\n\n<head>\n";
	    $msg.='<meta http-equiv="Content-Type" content="application/xhtml+xml; charset=utf-8" />';
		$msg.="\n";
	    $msg.='<title>'.stripslashes($efile->epub_title)."</title>\n";
	    $msg.="<style>\n<!--\n";
	    $msg.=str_replace(";width:100%}","}",$this->make_style($efile));
	    $msg.="\n-->\n</style>";
		$msg.="\n";
	    $msg.="</head>\n";
	    $msg.="<body>\n";
	    
	    $doc->add($msg);
		
		foreach($this->contener->indexs() as $key){
			$ops=$this->contener->get($key);

			//Pictures integration
			//echo htmlentities($ops);
			preg_match_all('!<img(.*?)src="../images/(.*?)" />!i',$ops,$matches);
			if(!empty($matches[2][0])){
				foreach($matches[2] as $picture){
					//echo $picture;
					$i=$this->findfiletype($picture);
					$img=@file_get_contents($this->localise_file($this->imgtab[$i->name]),false,$this->context);
					if(!empty($img)){
						$epub->add_content($img,$efile->epub_name.'/'.$picture);					
					}
					unset($img);
				}
			}
			$msg=str_replace("../images/",$efile->epub_name.'/',$ops);
			//Formatage word htm
			$msg=mb_eregi_replace("&nbsp;"," ",$msg);
			$msg=mb_eregi_replace("&copy;","�",$msg);
			$msg=str_replace('<div class="pi">','<p class=MsoNormal>',$msg);
			$msg=str_replace('</div>','</p>',$msg);
			$msg=preg_replace('/<div class="(.*)">/i',"<p>",$msg);
			$msg=str_replace('<br/><br/>','</p><p class=MsoNormal>',$msg);			
			$doc->add($msg);
		}

		$doc->add("\n</body>\n</html>");	
		
		//zip
		$epub->add_file($doc->file(),$efile->epub_name.".htm");
		if(!$epub->save()){
			$this->export_file="";
			$this->export_error(__('Could not save '.$this->export_file));
			return false;
		}else{
			$this->log->en("*** make_htm_word ***");
			$this->export_complete=true;
			$this->okfile[]=$this->export_file;
			return true;
		}
	}
	
	function make_container(){
		$msg='<?xml version="1.0"?>
<container version="1.0" xmlns="urn:oasis:names:tc:opendocument:xmlns:container">
  <rootfiles>
    <rootfile full-path="OEBPS/content.opf" media-type="application/oebps-package+xml" />
  </rootfiles>
</container>';
		return $msg;	
	}

	///Meta and all files listing
	function opf_maker($efile,$imgcover){
		$opf='<?xml version="1.0"?>';
		$opf.="\n";
		$opf.='<package xmlns="http://www.idpf.org/2007/opf" unique-identifier="BookID" version="2.0">';
		$opf.="\n";
	
		$opf.='<metadata xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:opf="http://www.idpf.org/2007/opf">';
		$opf.="\n";
		$opf.="<dc:title>".stripslashes($efile->epub_title)."</dc:title>\n";
		if($efile->epub_french==1){
	    	$opf.="<dc:language>fr</dc:language>\n";
		}else{
			$opf.="<dc:language>en</dc:language>\n";
		}
		if(empty($efile->epub_isbn)) $efile->epub_isbn="wp2epub-".rand(100,10000);
		$opf.='<dc:identifier id="BookID" opf:scheme="ISBN">'.$efile->epub_isbn."</dc:identifier>\n";
	    $opf.='<dc:creator opf:role="aut">'.stripslashes($this->epub_author)."</dc:creator>\n";
	    $opf.='<dc:publisher>'.stripslashes($this->epub_author)."</dc:publisher>\n";
	    //$opf.='<dc:genre>'.stripslashes("blog")."</dc:genre>\n";
	    if(!empty($imgcover->tmp)){
	    	$opf.='<meta name="cover" content="cover-image"/>';    	
			$opf.="\n";
		}
		$opf.="</metadata>\n";
	 
		$opf.="<manifest>\n";
	    $opf.='<item id="ncx" href="toc.ncx" media-type="application/x-dtbncx+xml"/>';
		$opf.="\n";
	    $opf.='<item id="stylesheet" href="styles/main.css" media-type="text/css"/>';
		$opf.="\n";
		
		//Images
		if(!empty($imgcover->tmp)){
			//print_r($imgcover);
			//echo $imgcover->type;
			$texten=$this->findfiletype($imgcover->name);
	    	$opf.='<item id="cover-image" href="images/imgcover.'.$imgcover->type.'" media-type="'.$texten->type.'"/>';
			$opf.="\n";
		}
	    foreach($this->imgtab as $key => $value){
			$texten=$this->findfiletype($value);
	    	$opf.='<item id="image.'.$key.'" href="images/'.$key.'.'.$this->imgxtension($value).'" media-type="'.$texten->type.'"/>';
			$opf.="\n";
		}
		
		//Content
		if(!empty($this->toc_html)){
    		$opf.='<item id="toc" href="text/toc.xhtml" media-type="application/xhtml+xml"/>';
			$opf.="\n";
		}

		//Text
		if(!empty($this->cover)) $opf.='<item id="cover" href="text/cover.xhtml" media-type="application/xhtml+xml"/>'."\n";
	    if(!empty($this->copyright))$opf.='<item id="copyright" href="text/copyright.xhtml" media-type="application/xhtml+xml"/>'."\n";
	    foreach($this->contener->indexs() as $key){
			$opf.='<item id="book'.str_pad($key,4,"0",STR_PAD_LEFT).'" href="text/book'.str_pad($key,4,"0",STR_PAD_LEFT).'.xhtml" media-type="application/xhtml+xml"/>'."\n";
		}
	    if(!empty($this->colophon)) $opf.='<item id="colophon" href="text/colophon.xhtml" media-type="application/xhtml+xml"/>'."\n";
	    $opf.="</manifest>\n";
	 
		$opf.="<spine toc=\"ncx\">\n";
		if(!empty($this->cover)) $opf.="<itemref idref=\"cover\"/>\n";
	    if(!empty($this->copyright)) $opf.="<itemref idref=\"copyright\"/>\n";
		foreach($this->contener->indexs() as $key){
			$opf.='<itemref idref="book'.str_pad($key,4,"0",STR_PAD_LEFT).'"/>'."\n";
		}
		if(!empty($this->toc_html)) $opf.="<itemref idref=\"toc\"/>\n";
	    if(!empty($this->colophon)) $opf.="<itemref idref=\"colophon\"/>\n";
		$opf.="</spine>\n";
		
		$opf.="<guide>\n";
		if(!empty($this->cover)) $opf.="<reference  href=\"text/cover.xhtml\" type=\"cover\" title=\"Cover\"/>\n";
		if(!empty($this->copyright)) $opf.="<reference  href=\"text/copyright.xhtml\" type=\"copyright-page\" title=\"Copyright\"/>\n";
		$opf.="<reference  href=\"text/book0001.xhtml\" type=\"text\" title=\"Text\"/>\n";
		if(!empty($this->toc_html)) $opf.="<reference  href=\"text/toc.xhtml\" type=\"toc\" title=\"Content\"/>\n";
		if(!empty($this->colophon)) $opf.="<reference  href=\"text/colophon.xhtml\" type=\"colophon\" title=\"Colophon\"/>\n";
		$opf.="</guide>";		
	 
		$opf.='</package>';
		return $opf;
	}

	//Table of contents
	function ncx_maker($efile){
		//print_r($efile);
		$msg='<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE ncx PUBLIC "-//NISO//DTD ncx 2005-1//EN" "http://www.daisy.org/z3986/2005/ncx-2005-1.dtd">
<ncx version="2005-1" xml:lang="en" xmlns="http://www.daisy.org/z3986/2005/ncx/">
<head>
    <meta name="dtb:uid" content="'.$efile->epub_isbn.'"/>';
    if($efile->epub_content==0){
    	$msg.="\n".'<meta name="dtb:depth" content="1"/>'."\n";
    }else{
    	$msg.="\n".'<meta name="dtb:depth" content="2"/>'."\n";
    }	
    $msg.='<meta name="dtb:totalPageCount" content="0"/>
    <meta name="dtb:maxPageNumber" content="0"/>
</head>
 
<docTitle>
    <text>'.stripslashes($efile->epub_title).'</text>
</docTitle>
 
<docAuthor>
    <text>'.stripslashes($efile->epub_author).'</text>
</docAuthor>
 
<navMap>';

    	$this->toc_html='<h2 class="main">Content</h2>';
    	$ordi=1;
   
    	if(!empty($this->cover)){
			$this->toc_html.="<p><a href=\"cover.xhtml\">Cover</a></p>\n";
	    	$msg.='<navPoint class="chapter" id="cover" playOrder="'.$ordi.'">'."\n";
			$msg.='<navLabel><text>Cover</text></navLabel>'."\n";
			$msg.='<content src="text/cover.xhtml"/>'."\n";
			$msg.="</navPoint>\n";
			$ordi++;
    	} 

		if(!empty($this->copyright)){
			$this->toc_html.="<p><a href=\"copyright.xhtml\">Copyright</a></p>\n";
			$msg.='<navPoint class="chapter" id="copyright" playOrder="'.$ordi.'">'."\n";
			$msg.='<navLabel><text>Copyright</text></navLabel>'."\n";
			$msg.='<content src="text/copyright.xhtml"/>'."\n";
			$msg.="</navPoint>\n";
			$ordi++;
		} 
		
		$max=count($this->contener->indexs());
		$year="";
		$month="";
		$first=true;
	
		foreach($this->contener->indexs() as $key){
			
			//if($ordi>10) continue;

			$h3=$this->title->get($key);
			if(empty($h3)) continue;
			
			$id='book'.str_pad($key,4,"0",STR_PAD_LEFT);
			
			if($first){
				$this->toc_html.="<p><a href=\"$id.xhtml\">Start</a></p>\n";
				$first=false;
			}

			if($efile->epub_content==1&&isset($this->year[$key])&&$year!=$this->year[$key]){
				//New year
				if(!empty($year)) $msg.="   </navPoint>\n</navPoint>\n";
				$year=$this->year[$key];
				$month="";
				$this->toc_html.='<h3><a href="'.$id.'.xhtml">'.$year.'</a></h3>'."\n";
				$msg.='<navPoint class="h1" id="'.$id.'_y" playOrder="'.$ordi.'">'."\n";
				$msg.='<navLabel><text>'.$year.'</text></navLabel>'."\n";
				$msg.='<content src="text/'.$id.'.xhtml"/>'."\n";
				//$ordi++;
			}

			if($efile->epub_content==1&&isset($this->month[$key])&&$month!=$this->month[$key]){
				//New month
				if(!empty($month)) $msg.="   </navPoint>\n";
				$month=$this->month[$key];
				$this->toc_html.='<h4><a href="'.$id.'.xhtml">'.$month.'</a></h4>'."\n";
				$msg.='   <navPoint class="h2" id="'.$id.'_m" playOrder="'.$ordi.'">'."\n";
				$msg.='   <navLabel><text>'.$month.'</text></navLabel>'."\n";
				$msg.='   <content src="text/'.$id.'.xhtml"/>'."\n";
				//$ordi++;
			}
			
			$this->toc_html.='<p><a href="'.$id.'.xhtml">'.str_replace("&nbsp;"," ",$h3).'</a></p>'."\n";
			$msg.='      <navPoint class="chapter" id="'.$id.'" playOrder="'.$ordi.'">'."\n";
			$msg.='      <navLabel><text>'.str_replace("&nbsp;"," ",$h3).'</text></navLabel>'."\n";
			$msg.='      <content src="text/'.$id.'.xhtml"/>'."\n";
			$msg.="      </navPoint>\n"; 
			$ordi++;
			
		}
		if(!empty($month)) $msg.="   </navPoint>\n";
		if(!empty($year)) $msg.="</navPoint>\n";

		$msg.='<navPoint class="chapter" id="toc" playOrder="'.$ordi.'">'."\n";
		$msg.='<navLabel><text>Content</text></navLabel>'."\n";
		$msg.='<content src="text/toc.xhtml"/>'."\n";
		$msg.="</navPoint>\n";
		$ordi++;
		
		if(!empty($this->colophon)){
			$this->toc_html.="<p><a href=\"colophon.xhtml\">Colophon</a></p>\n";
			$msg.='<navPoint class="chapter" id="colophon" playOrder="'.$ordi.'">'."\n";
			$msg.='<navLabel><text>Colophon</text></navLabel>'."\n";
			$msg.='<content src="text/colophon.xhtml"/>'."\n";
			$msg.="</navPoint>\n";
		}

		$msg.='</navMap></ncx>';
		return $msg;
	}

	function ops_head(){
		$msg='<?xml version="1.0" encoding="UTF-8" standalone="no"?>';
		$msg.="\n";
		$msg.='<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">';
		$msg.="\n";
		$msg.='<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">';	
		$msg.="\n";
		$msg.="<head>\n";
		$msg.='<meta http-equiv="Content-Type" content="application/xhtml+xml; charset=utf-8" />';
		$msg.="\n";
		return $msg;
	}

	function ops_maker($main,$efile){
		$msg=$this->ops_head();
	    $msg.='  <title>'.stripslashes($efile->epub_title)."</title>\n";
	    $msg.='  <link rel="stylesheet" href="../styles/main.css" type="text/css" />';
		$msg.="\n";
	    $msg.="</head>\n";
	    $msg.="<body>\n";
	    $main=$msg.$main."\n</body>\n</html>";
	    //$this->log->en($main);
		return $main;
	}

	function cover_make($imgcover){
		$msg=$this->ops_head();		
		$msg.='<title>Cover</title>
    <link rel="stylesheet" type="text/css" href="../styles/main.css" />
</head>
<body>
  	<div></div>
	<p><img id="cover-image" src="../images/imgcover.'.$imgcover->type.'" alt="bookcover" /></p>
</body>
</html>';
		return $msg;
	}
	
	function new_page(){
		$page=$this->contener->get();		
		$page=trim($page);
		$page=trim($page,"\n");
		if(empty($page)){
			$this->contener->reset();
			unset($page);
			return;
		}else{
			$this->contener->update($page);
			unset($page);			
		}
	}

	function format_text($msg){

		unset($this->fout);
		
		//Delete PHP code
		$msg=preg_replace("/<\?php(.*)\?>/i","",$msg);

		$msg=str_replace("<!--more-->","",$msg);
		$msg=preg_replace("/<a href=\"(.*?)\"><img(.*?)\/>(.*?)<\/a>/i","<img$2/>", $msg);
		//echo($msg);
		//Delete object
		$msg=preg_replace("/<object (.*?)>(.*?)<\/object>/i", "", $msg);
		
		//Mark lines
		$msg=mb_eregi_replace("\r","",$msg);
		$msg=mb_eregi_replace("\n\n","\n",$msg);
		$msg=mb_eregi_replace("\n","<br/>",$msg);
		
		$msg=str_replace("--","—",$msg);
		$msg=str_replace("	","",$msg);
		//echo($msg);
		//$this->log->en($msg);
		
		if(false&&class_exists(DOMDocument)){
			$x = new DOMDocument;
			$x->loadHTML($msg);
			$msg=$x->saveXML();
			echo($msg);
		}elseif(class_exists(tidy)){
			$tidy = new Tidy();
			$msg= @$tidy->repairString($msg,false,"utf8");
		}else{
			$msg="<body>".$msg."</body>";
		}
		
		$msg=str_replace("<br></li>","</li>",$msg);
		
		//$this->log->en($msg);
		$this->fout="";
		$this->inp=false;
		$this->inli=false;
		$this->inem=false;
		$this->instrong=false;
		$parser=new simple_html_dom();
		//$this->p(strlen($msg));
		$parser->load($msg);
		unset($msg);
		//$this->log->e(" load");
		foreach($parser->find('body') as $body) {break;}
		//$this->log->e(" tree");
		$this->html_tree($body);
		if($this->inp) $this->fout.="</div>"; 
		
		//$this->fout=str_replace("</p>","",$this->fout);		
		
		//Final HTML cleaning
		$this->fout=str_replace('<div class="pi"> ','<div class="pi">',$this->fout);
		$this->fout=str_replace('<div class="pi"></div>','',$this->fout);
		$this->fout=str_replace('<li></li>','',$this->fout);
		$this->fout=str_replace('epub://','http://',$this->fout);
		
		$parser->clear();
		unset($parser);
		return $this->fout;
	}
	
	function clean_texte($msg){
		$msg=html_entity_decode($msg,null,'UTF-8');
		$msg=str_replace(array("=","&","#","<"),array("%3D","%26","%23",""),$msg);
		$msg=str_replace("%26quot;",'"',$msg);
		$msg=str_replace("%26nbsp;","&nbsp;",$msg);
		$msg=str_replace("%26%23233;","�?",$msg);
		$msg=str_replace("%26%23224;","�",$msg);
		$msg=str_replace("%26%23201;","�",$msg);
		$msg=str_replace("%26%23192;","�",$msg);
		$msg=str_replace("<","&lt;",$msg);
		return $msg;
	}
	
	function clean_title($msg){
		$msg=$this->clean_texte($msg);
		$msg=str_replace("%26","et",$msg);
		return $msg; 
	}
	
	function end_para($stillin=true){
		if($this->inp) $this->fout.="</div>\n";
		if($stillin) $this->inp=true; else $this->inp=false;
	}

	function html_tree($node){		
		$child=true;
		$closediv=false;
		$closed=true;
		
		if($indebug) echo("$node->tag<br>");
		
		$tag=strtolower(trim($node->tag));
		if($tag=="text"||empty($tag)){
			if(!$this->inp){
				$this->fout.='<div class="pi">';
				$this->inp=true;
				$closed=false;
				//$tag=p;
			}
			$this->fout.=$this->clean_texte($node->innertext);
			$child=false;
		}elseif($tag=="body"||$tag=="div"||$tag=="object"||$tag=="unknown"||$tag=="embed"||$tag=="iframe"||$tag=="font"||$tag=="param"||$tag=="strike"||$tag=="noscript"){
			$closed=false;
		}elseif($tag=="span"){
		}elseif($tag=="a"){
			$this->fout.="<".$tag;
			foreach($node->attr as $key=>$value){
				if($key=="name") $this->fout.=" id=\"$value\"";
				if($key=="href") $this->fout.=" href=\"".trim(str_replace(array("=","&","#"),array("%3D","%26","%23"),$value))."\"";	//Problem here	
			}
			$this->fout.=">";
		}elseif($tag=="br"){
			if($this->inp&&!$this->inli){
				if($this->inem)
					$this->fout.='</em></div>'."\n".'<div class="pi"><em>';
				elseif($this->instrong)
					$this->fout.='</strong></div>'."\n".'<div class="pi"><strong>';
				else
					$this->fout.='</div>'."\n".'<div class="pi">';
			}else
				$this->fout.='<br/>';
		}elseif($tag=="p"){
			if($this->inp) $this->fout.="</div>\n";
			$this->fout.='<div class="pi">';
			$this->inp=true;
		}elseif($tag=="blockquote"){
			if($this->inp) $this->fout.="</div>\n";
			$this->fout.='<div class="blockquote">';
			$this->inp=true;
		}elseif($tag=="h1"){
			$this->end_para();
			$this->fout.='<h1 class="main">';
		}elseif($tag=="h2"){
			$this->end_para();
			$this->fout.='<h2 class="main">';
		}elseif($tag=="h3"){
			$this->end_para();
			$this->fout.='<h3 class="main">';
		}elseif($tag=="h4"){
			$this->end_para();
			$this->fout.='<h4 class="main">';
		}elseif($tag=="ol"||$tag=="ul"){
			$this->end_para();
			$this->fout.="<".$tag.">";
		}elseif($tag=="li"){
			//$this->end_para();
			$this->fout.="<".$tag.">";
			$this->inp=true;
			$this->inli=true;
		}elseif($tag=="img"){
			$closed=false;
			foreach($node->attr as $key=>$value){
				if($key=="src") $url=$value;
			}
			if(!empty($url)){
				$this->imgtab[$this->imgindex]=$url;
				//$this->fout.='<div><img class="main" alt="image" src="../images/'.$this->imgindex.'.'.$this->imgxtension($url).'" /></div>';
				$this->fout.='<img class="main" alt="image" src="../images/'.$this->imgindex.'.'.$this->imgxtension($url).'" />';
				$this->imgindex++;
			}
		}elseif($tag=="em"){
			$this->fout.="<em>";
			$this->inem=true;
		}elseif($tag=="strong"){
			$this->fout.="<strong>";
			$this->instrong=true;
		}else{
			$this->fout.="<".$tag.">";
		}
			
		//Explore the children
		if($child && count($node->nodes)>0){
			foreach($node->nodes as $child){
				$this->html_tree($child);
			}
		}
		    
		if(!$closed){
		}elseif($closediv){
			$this->fout.="</div>\n";
		}elseif($span){
			$this->fout.="</span>";
		}elseif($tag=="h1"||$tag=="h2"||$tag=="h3"||$tag=="h4"||$tag=="ol"||$tag=="ul"){
			$this->fout.="</".$tag.">";
			$this->inp=false;
		}elseif($tag=="li"){	
			$this->fout.="</li>\n";
			$this->inli=false;
		}elseif($tag=="p"||$tag=="blockquote"){	
			$this->fout.="</div>\n";
			$this->inp=false;
		}elseif($tag=="em"){	
			$this->fout.="</".$tag.">";
			$this->inem=false;
		}elseif($tag=="strong"){	
			$this->fout.="</".$tag.">";
			$this->instrong=false;
		}elseif($tag!="text"&&!empty($tag)&&$tag!="span"&&$tag!="body"&&$tag!="br"&&$tag!="div"&&!eregi(":",$tag)){
			$this->fout.="</".$tag.">";
		}
	}

	function imgxtension($url){
		$info=parse_url($url);
		$info=pathinfo($info['path']);
		return $info['extension'];
	}

	function traducDate($content,$french=0){
		if($french==0) return $content;
		$dateUS=array('January','February','March','April','May','June','July','August','September','October','November','December','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday');
		$dateFR=array('janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre','lundi','mardi','mercredi','jeudi','vendredi','samedi','dimanche');
		return str_replace($dateUS,$dateFR,$content);
	}
	
	//Supprime http:// d'un url
	function outHTTP($ad) {
		return ereg_replace('http://','',$ad);
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

	function abspath($url){
		if(!eregi("http://",$url))
			return get_settings('siteurl').$url;
		else
			return $url;		
	}

	function localise_file($url){
		if(!eregi("http://",$url)) return ABSPATH.$url;
		$url=$this->abspath($url);
		$url=str_replace(get_settings('siteurl'),ABSPATH,$url);
		return $url;
	}
	
	function preloadImage($imgfile){
		if(!isset($imgfile['size'])){
			$this->error="Image vide";
			return false;
		}
		$this->error=$imgfile['error'];
		if($this->error==2){
			$this->export_error="HTML max size overflow!";
			return false;
		}
		if($this->error>0) return false;

		$img->name=$imgfile['name'];
		$img->tmp=$imgfile['tmp_name'];
		$it=explode('/',$imgfile['type']);
		$img->type=str_replace(array("pjpeg","jpeg"),"jpg",$it[1]);
		$img->size=$imgfile['size'];

		return $img;
	}

	function display_filesize($filesize){
		if(is_numeric($filesize)){
			$decr = 1024; $step = 0;
			$prefix = array('Byte','KB','MB','GB','TB','PB');
	       
			while(($filesize / $decr) > 0.9){
	        	$filesize = $filesize / $decr;
	        	$step++;
	    	}
	    	return round($filesize,2).' '.$prefix[$step];
	    }else{
	    	return 'NaN';
	    }
	}

    function return_bytes($val){
        if(empty($val))return 0;
        $val = trim($val);
        preg_match('#([0-9]+)[\s]*([a-z]+)#i', $val, $matches);
        $last = '';
        if(isset($matches[2])){
            $last = $matches[2];
        }

        if(isset($matches[1])){
            $val = (int) $matches[1];
        }

        switch (strtolower($last)){
            case 'g':
            case 'gb':
                $val *= 1024;
            case 'm':
            case 'mb':
                $val *= 1024;
            case 'k':
            case 'kb':
                $val *= 1024;
        }
		return (int) $val;
	}

	//function to enable a proxy in fileget contents by generating a stream_context
	function proxy_getstreamcontext($psw="",$proxy=""){
		if(empty($psw)||empty($proxy)) return null;
		$auth = base64_encode('loginonproxy:'.$psw);
		$proxy = array('http' => array('proxy' => $proxy,'request_fulluri' => true,'header' => 'Proxy-Authorization: Basic $auth'));
		return stream_context_create($proxy);
	}

	function p($msg){
		echo("<pre>");
		print_r($msg);
		echo("</pre>");
	}
}

//Load process
class wp2epub_log {
	
	private $handle=false;
	
	function __construct($logfile,$status=true){
		if(!$status) return;
		@unlink($logfile);
		$this->handle=@fopen($logfile,"w");
	}
	
	function e($msg,$t=false){
		if($this->handle){
			if($t) $msg=date("H:i")." $msg";
			fwrite($this->handle,$this->noAccent($msg));
		}
	}

	function en($msg,$t=false){
		if($this->handle){
			if($t) $msg=date("H:i")." $msg";
			fwrite($this->handle,$this->noAccent($msg)."\r\n");
		}
	}
	
	function noAccent($msg, $ascii = false){
		if($ascii)
			$str = htmlentities($msg); 
		else
			$str = htmlentities($msg, ENT_QUOTES, "UTF-8");
			
		// cf: http://www.w3.org/TR/REC-html40/sgml/entities.html
  		$str = preg_replace('/&([a-zA-Z])(uml|acute|grave|circ|tilde|cedil|slash|ring);/', '$1',$str);
  		$str=str_replace(array("&#039;","&rsquo;"),"'",$str);
  		return html_entity_decode($str);
		
	}
	
}

//Using file as large string
class wp2epub_diskmem{
	
	private $file;
	private $handle;

	function __construct(){
		$this->file=tempnam(sys_get_temp_dir(), "var");
		$this->handle=@fopen($this->file,"a");
	}
	
	function add($val=""){
		if(!$this->handle) return false;
		fwrite($this->handle,$val);
	}
	
	function file(){
		fclose($this->handle);
		return $this->file;
	}
	
}

//Using MYSQL as large array
class wp2epub_mysqlmem{
	
	private $base;
	private $index=-1;
	private $ids=array();
	private $useoldfield=false;

	function __construct($tab){
		global $wpdb;
		$this->base="wp_epub_tmp_".$tab;
		$q="CREATE TEMPORARY TABLE `$this->base` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,`val` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL) ENGINE = MYISAM";
		$wpdb->query($q);
	}

	function index(){
		return $this->index;
	}
	
	function indexs(){
		return $this->ids; 
	}

	function set($val=""){
		global $wpdb;
		if($this->useoldfield){
			$this->useoldfield=false;
			$q="UPDATE LOW_PRIORITY ".$this->base." SET val='".mysql_real_escape_string($val)."' WHERE id='$this->index'";
			$wpdb->query($q);
		}else{
			//$q="INSERT LOW_PRIORITY INTO ".$this->base." SET val='".mysql_real_escape_string($val)."'";
			//$q=array("val" => "'".$val."'");
			$q=array("val" => $val);
			$wpdb->insert($this->base,$q); 
			//$this->p($q);
			//$wpdb->query($q);
			$this->index=$wpdb->insert_id;
			$this->ids[]=$this->index;
			//$this->p($this->index);
		}
	}

	function add($val=""){
		global $wpdb;
		$q="UPDATE LOW_PRIORITY ".$this->base." SET val=CONCAT(val,'".mysql_real_escape_string($val)."') WHERE id='$this->index'";
		$wpdb->query($q);
	}

	function update($val=""){
		global $wpdb;
		$q="UPDATE LOW_PRIORITY ".$this->base." SET val='".mysql_real_escape_string($val)."' WHERE id='$this->index'";
		$wpdb->query($q);
	}
	
	function reset(){
		$this->useoldfield=true;
	}
	
	function get($id=""){
		global $wpdb;
		if(empty($id)) $id=$this->index;
		$q="SELECT val FROM ".$this->base." WHERE id='$id'";
		$v=$wpdb->get_row($q);
		return $v->val;
	}
	
	function p($msg){
		echo("<pre>");
		print_r($msg);
		echo("</pre>");
	}
}

//Zip optimisation
class wp2epub_zip{
	
	private $zip;
	private $tstamp;
	private $mode;
	private $zfile;
	private $zipmode=null;	//Compression on/off
	private $zipfile="";
	public $error="";

	function __construct($zipfile="",$mode=3){
		$this->mode=$mode;
		$this->zipfile=$zipfile;
		@unlink($zipfile);
		switch($this->mode){
     	case "1":
     		$this->tstamp=time();
			require_once("zipcreate/zipcreate.class.php");
			$this->zip=new ZipCreate();
			break;
     	case "2":
			if (class_exists('ZipArchive')){
				$this->zip=new ZipArchive();
				// Prepare File
				$this->zfile=tempnam(sys_get_temp_dir(), "zip");
				$this->zip->open($this->zfile,ZipArchive::OVERWRITE);
			}else{
				$this->error="Non class";
			}
     		break;
     	case "3":
     		//http://www.phpconcept.net/pclzip
     		require_once('pclzip-2-8-2/pclzip.lib.php');
 			$this->zip= new PclZip($this->zipfile);
 			$this->zipmode=true;
     		break;
		}
	}

	//From content (zipmode=false for just storing witout compress)
	function add_content($content,$filename,$zipmode=true){
		switch($this->mode){
     	case "1":
     		if(!$zipmode) $this->zip->ztype='store';
			$this->zip->add_file($content,$filename,$this->tstamp);
     		if(!$zipmode) $this->zip->ztype='gzip';
			break;
     	case "2":
			$this->zip->addFromString($filename,$content);
			break;
     	case "3":
	     	$a=array(PCLZIP_ATT_FILE_NAME=>$filename,PCLZIP_ATT_FILE_CONTENT=>$content);
     		if($zipmode){
				$list=$this->zip->add(array($a));
     		}else{
	     		$list=$this->zip->add(array($a),PCLZIP_OPT_NO_COMPRESSION);
     		}
			if($list==0) die("ERROR1:'".$this->zip->errorInfo(true)."'");
     		break;
		}
	}

	//From file
	function add_file($filepath,$filename){
		switch($this->mode){
     	case "1":
			$content=readfile($filepath);
			$this->zip->add_file($content,$filename,$this->tstamp);
   			break;
     	case "2":
			$this->zip->addFile($filepath,$filename);
			break;
     	case "3":
			global $wp2epub_CallBack_filename;
			$wp2epub_CallBack_filename=$filename;
			$list=$this->zip->add($filepath,PCLZIP_OPT_REMOVE_ALL_PATH,PCLZIP_CB_PRE_ADD,'wp2epub_CallBack');
			if($list==0) die("ERROR2:'".$this->zip->errorInfo(true)."'");
			unset($wp2epub_CallBack_filename);
     		break;
		}
	}
		
	function renameFile($p_event, &$p_header){
		print_r($p_header);
		return 1;
	}
		
	function output(){
		switch($this->mode){
     	case "1":
			return $this->zip->build_zip();
     	case "2":
     		$this->zip->close();
			$out=readfile($this->zfile);
			unlink($this->zfile);
			return $out;  
     	case "3":
			$out=readfile($this->zfile);
			unlink($this->zfile);
			return $out;  
		}
	}
	
	function save(){
		switch($this->mode){
     	case "1":
			$output=$this->zip->build_zip();
			if(!$this->saveFile($this->zipfile,$output)){
				$this->error='Could not save '.$this->zipfile;
				return false;
			}
			return true;
     	case "2":
			// Close and send to users
			$this->zip->close();
			//$this->delfile($filename);
			if(!copy($this->zfile,$this->zipfile)){
				$this->error='Could not save '.$this->zipfile;
				unlink($this->zfile);
				return false;
			}
			return true;
     	case "3":
     		return true;
     		break;
		}
	}

	function saveFile($name,$msg){
		$this->delfile($name);
		$fp=@fopen($name,'w');
		if(!empty($fp)) {
			@fputs($fp,$msg);
			@fclose($fp);
			return true;
		}else
			return false;
	}

	function delfile($img) {
		if(@file_exists($img)){
			if(@unlink($img)) return true;
			$this->error="Impossible to unlink $img (1)";
			if(!@chmod($img,0777)){
				$this->error="Impossible to cmod $img";
				return false;
			}
			if(@unlink($img)) return true;
			$this->error="Impossible to unlink $img (2)";
			return false;
		}else{
			$this->error="File $img do not exist";
			return false;
		}
	}

	function p($msg){
		echo("<pre>");
		print_r($msg);
		echo("</pre>");
	}
}

function wp2epub_CallBack($p_event, &$p_header){
	global $wp2epub_CallBack_filename;
	$p_header['stored_filename']=$wp2epub_CallBack_filename;
	return 1;
}
?>