<?php
// ini_set('display_errors', '1');
// ini_set('display_startup_errors', '1');
// error_reporting(E_ALL);

// header("Access-Control-Allow-Origin: https://dicecake.com");
if( isset($_REQUEST['key']) && $_REQUEST['key'] == '123456' && isset($_REQUEST['post_id']) && intval($_REQUEST['post_id']) > 0 ){
	$folder = __DIR__."/json/";
	$fn = $folder."post_".$_REQUEST['post_id'].".json";
	 // var_dump($fn);
	if(file_exists($fn)){
		$file = $fn;
		$json = file_get_contents($file);
		$datas = json_decode($json);
		if (count((array)$datas) == 0) { 
		  // Delete file empty
			
		 	 unlink($file);
	  		
		} 

	} 
	if( !file_exists($fn) || ( file_exists($fn) && filesize($fn) > 0 )  ){
		$servername = "localhost";
		$username = "peekle";
		$password = "gbR3IVfFtJyP5HFv";
#		$password = "drWzeIHB7r98zqxd";
		$db = "peekle";
		$con = mysqli_connect($servername, $username, $password, $db);
		if (mysqli_connect_errno()) {
		  echo "Failed to connect to MySQL: " . mysqli_connect_error();
		  exit();  
		}
		mysqli_set_charset( $con , "utf8" );

		// include(__DIR__.'/../wp-load.php');
		// global $wpdb;
		$post_id = $_REQUEST['post_id'];
		$q = "SELECT p.post_title,pm.meta_value, p.post_type, p.post_name FROM `wp_posts` as p INNER JOIN wp_postmeta as pm ON pm.post_id = p.ID and pm.meta_key IN ('_episode_url_link','_movie_url_link') WHERE p.ID =".$post_id;
		// $rs = $wpdb->get_results($q);
		$rs = mysqli_query( $con , $q );
		
		if( mysqli_num_rows($rs) > 0 ){
			$r = mysqli_fetch_object($rs);
			$link = $r->meta_value;
			preg_match_all('#(<Part \d>|)\bhttps?:\/\/[^\s()<>]+(?:\([\w\d]+\)|([^\s!"$%&()*+,\-./:;<=>?@[\]^`{|}~]|\/|[^\s!"\]$%&()*+<></]))#', $link, $matches);


			if( count($matches[0]) > 0 ){
				$arr_sort = array();
		        foreach($matches[0] as $key => $value) {
		        	if( !isset($arr_sort[$value]) ){
			            if( strpos($value, "videojs.vidground.com") !== false ){
			                $arr_sort[ $value ] = 1;
			            }else if( strpos($value, "short.ink") !== false ){
			                $arr_sort[ $value ] = 2;
			            }else if( strpos($value, "asianembed") !== false || strpos($value, "dembed1.com") !== false || strpos($value, "youtu") !== false || strpos($value, "naver") !== false ){
			                $arr_sort[ $value ] = 3;
			            }else $arr_sort[ $value ] = 4;
		        	}
		        }
		        asort($arr_sort);
		        $links = array();
		        foreach ($arr_sort as $k => $v) {
		        	$links[] = $k;
		        }


		        $title = $r->post_title;
				$dt = json_encode(
					array(
						"title" => $title,
						"link"  => $links,
						"backlink" => "https://moviehqu.com/".$r->post_type."/".$r->post_name
					)
				);
				  
				write_file_new_url( $dt , $fn );
				echo $dt;
			}else die("empty link");
		}else die("empty link");
		mysqli_free_result($rs);
		mysqli_close($con);
	}else{
		$dt = @file_get_contents($fn);
		$arr_dt = json_decode($dt);
		$arr_link = $arr_dt->link;
		$arr_sort = array();
		foreach ($arr_link as $k => $value) {
			if( !isset($arr_sort[$value]) ){
	            if( strpos($value, "videojs.vidground.com") !== false ){
	                $arr_sort[ $value ] = 1;
	            }else if( strpos($value, "short.ink") !== false ){
	                $arr_sort[ $value ] = 2;
	            }else if( strpos($value, "asianembed") !== false || strpos($value, "dembed1.com") !== false || strpos($value, "youtu") !== false || strpos($value, "naver") !== false ){
	                $arr_sort[ $value ] = 3;
	          	}else $arr_sort[ $value ] = 4;
	      	}
		}

		asort($arr_sort);
        $links = array();
        foreach ($arr_sort as $k => $v) {
        	$links[] = $k;
        }

        $arr_dt->link = $links;
        $dt = json_encode($arr_dt);

		echo $dt;
	}
}else die("403 forbiden");

function write_file_new_url($data,$link){
	$myfile = fopen($link, "w+") or die("Unable to open file!");
    $txt = $data;
    fwrite($myfile, $txt);
    fclose($myfile);
}
?>
