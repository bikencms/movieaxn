<?php
$backlink = "";
// if(isset($_SERVER['HTTP_REFERER']) && $_SERVER['HTTP_REFERER'] != NULL ){
//     $backlink = $_SERVER['HTTP_REFERER'];
// }

// if( strpos($backlink, "moviehqu.com") === false ){
//     //die("403 forbiden"); 
// }
  
$data = NULL;
if( (isset($_REQUEST['p']) && $_REQUEST['p'] != '') || (isset($_REQUEST['pid']) && $_REQUEST['pid'] != '') ){
    $pid = isset($_REQUEST['p'])?$_REQUEST['p']:$_REQUEST['pid'];
    $url = "https://backend.moviehqu.com/outlink/outlink.php?key=123456&post_id=".$pid;
    $data = @file_get_contents($url);
    $data = json_decode($data);
    
}else die("error link");

if($data != NULL && is_object($data)){

    $backlink_r = $data->backlink;
//  $backlink_r = "moviehqu.com";

    if( strpos($backlink, "streamk.tv") !== false ){
        $backlink_r = str_replace("moviehqu.com", "streamk.tv", $backlink_r);
    }
    if( strpos($backlink, "moviehqu.com") !== false ){
        $backlink_r = str_replace("moviehqu.com", "moviehqu.com", $backlink_r);
    }
    $backlink = $backlink_r;

    $link = $data->link;
}else die('error get data');

function is_mobile(){
    if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
        $is_mobile = false;
    } elseif ( strpos( $_SERVER['HTTP_USER_AGENT'], 'Mobile' ) !== false // many mobile devices (all iPhone, iPad, etc.)
        || strpos( $_SERVER['HTTP_USER_AGENT'], 'Android' ) !== false
        || strpos( $_SERVER['HTTP_USER_AGENT'], 'Silk/' ) !== false
        || strpos( $_SERVER['HTTP_USER_AGENT'], 'Kindle' ) !== false
        || strpos( $_SERVER['HTTP_USER_AGENT'], 'BlackBerry' ) !== false
        || strpos( $_SERVER['HTTP_USER_AGENT'], 'Opera Mini' ) !== false
        || strpos( $_SERVER['HTTP_USER_AGENT'], 'Opera Mobi' ) !== false ) {
            $is_mobile = true;
    } else {
        $is_mobile = false;
    }
    return $is_mobile;
}

?>
<html>
<head>
    <title><?php echo $data->title;?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta charset="utf-8">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css">

<!--     <script disable-devtool-auto src='https://cdn.jsdelivr.net/npm/disable-devtool/disable-devtool.min.js'></script>
 -->   
    <!-- Optional theme -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap-theme.min.css">

    <!-- fontawesome -->
    <link rel="stylesheet" href="https://pro.fontawesome.com/releases/v5.10.0/css/all.css" integrity="sha384-AYmEC3Yw5cVb3ZcuHtOA93w35dYTsvhLPVnYs9eStHfGJvOvKxVfELGroGkvsg+p" crossorigin="anonymous"/>

    <!-- Latest compiled and minified JavaScript -->
    <script src=" //code.jquery.com/jquery-latest.min.js"></script>

    <!-- Begin Kiosked -->
<script type="text/javascript">window.__ITGS_started = Date.now();</script>
<script type="text/javascript" async="async" src="//scripts.kiosked.com/loader/kiosked-loader.js?site=17622"></script>
<!-- End Kiosked -->
 
    <script type="text/javascript">
        var uri=window.location.toString();if(uri.indexOf("?pid=","?pid=")>0){var clean_uri=uri.substring(0,uri.indexOf("?pid="));window.history.replaceState({},document.title,clean_uri);window.history.pushState({},document.title,clean_uri)}
    </script>
    <script type="text/javascript">
        //<![CDATA[
        $(document).ready(function(){            
            var w = $("#video_content").width();
            if( w > 500 ){
                w = w*0.8;
                $("#div_content_button").css("width",w);
            }else $("#div_content_button").css("width","auto");

            var h = w/1.7;
            if( h < 260 ){
                h = 260;
            }
            $("#video_player").attr("style","width:"+w+"px !important;height:"+h+"px !important;");
            $(window).resize(function(){
                var wr = $("#video_content").width();
                if( wr > 500 ){
                    wr = wr*0.8;
                    $("#div_content_button").css("width",wr);
                }else $("#div_content_button").css("width","auto");

                var hr = wr/1.7; 
                if( hr < 260 ){
                    hr = 260;
                }
                $("#video_player").attr("style","width:"+wr+"px !important;height:"+hr+"px !important;");
            });
        });
        function playback(ele,link){
            $(".btn-play").each(function(){
                $(this).removeClass("active");
            });
            $(ele).addClass("active"); 
            $("#video_player").attr("src",link+'?pid=<?php echo $pid; ?>');
            //$("#video_player").attr("src",link+'?ref=moviehqu.com&pid=<?php echo $pid; ?>');

            var ifrm_html = $("#playVideoUl").html(); 
            $("#video_player").remove();
            $("#playVideoUl").html(ifrm_html);
        }
        //]]>
    </script>
    <style type="text/css">
        body{
            background-color: #131722;
            color: #c9c9c9;
        }
        .btn-play{
            padding: 15px 15px;
            text-align: left;
            font-size: 12px;
            min-width: 140px;
            border-radius: 40px;
            position: relative;
            background-color: #fff;
            color: #000;
            text-align: center;
            margin-right: 10px;
            margin-top: 10px;
        }
        .btn-back{
            background-color: transparent;
            /*border: 1px solid #fff;*/
            color: #fff;
            padding: 12px 20px;
            font-size: 18px;
            font-weight: 700;
            margin-top: 10px;
        }
        .active{
            background-color: #b6bbca;
            color: #000;
        }
        #ads_box_S{
            float: left;
            text-align: center;
            background-color: #1c222e;
            width: 100%;
        }
        .ads_box_pc{
            margin: 30px auto;
            /*height: 200px;*/
        text-align: center;
                /*background-color: #ddd;*/  
        }
        #box_back_btn_mobile{
            display: none;
        }
        #row_contain_link{
            text-align: center;
        }
        button.btn.btn-play {
            order: 2;
        }

        button.btn.btn-play.btn-hqplus {
            order: 1;
        }
        button.btn.btn-play.btn-asia {
            display: none;
        }
        .flex-btn-hq { 
            display: flex;
              justify-content: center;
            flex-direction: row;
        }
       /* .row.ads-middle {
		    margin-top: 350px;
		}*/
        /*.ads-left,.ads-right{
            margin-top: 350px;
        }*/
		/*.ads-right2{
		    margin-top: 240px;
		}*/
		/*.ads-top {
		    margin-top: 40px;
		}
        .ads-mobile {
            margin-top: 20px;
            margin-left: 10px;
            margin-right: 10px;
        }
        .ads-mobile-center.second {
            margin-top: 60px;
        }
         .ads-mobile-center.first {
            margin-top: 60px;
        }*/
        /*@media(min-width: 1200px){
            .row.ads-dicace { 
                width: 912px !important;
                text-align: center;
                margin: 0 auto;
            }
        }*/
         @media(max-width: 992px){
            .widget.block-15.widget_block{
                display: none;
            }
            .video-full{
                    padding-left: 0 !important;
            }
            #playVideoUl iframe{
                width: 100% !important;
            }

         }
        @media(max-width: 1000px){
            .ads_box_pc{
                overflow: hidden;
                /*height: 550px;*/
            } 
            #video_content{
                padding: 0 !important;
            }
            .btn-back{
                padding: 5px 5px;
                font-size: 12px;
            }
            .btn-play{
                padding: 5px 5px;
                min-width: 80px;
                margin-right: 2px;
            }
            #box_back_btn_pc{
                display: none;
            }
            #box_back_btn_mobile{
                display: block;
            }
            #row_contain_link{
                float: left;
                width: 100%;
            }
            /*.row.ads-middle {
			    margin-top: 300px;
                margin-bottom: 60px;
			}	*/
            
        }
        #form_search .btn-default{
            background: #fff;

        }
     /*   .ads-top {
		    margin-top: 140px;
		}
        .ads-mid-left, .ads-mid-right {
		    margin-top: 280px;
		}*/
		.ads-mid-left, .ads-mid-right {
		    margin-top: 350px;
		}
		.ads-right{
			margin-left: 8px;
		}
         .ads-left{
         	margin-right: 8px;
         }
    .form-video{
    	display: none;
    }

    </style>
		
<!-- Prevent Google Translate pop-ups -->
<meta name="google" content="notranslate">

</head>
<body>
    <div class="container" id="div_contain">
        <div class="row">
            <div class="col-md-12 video-full" align="center" style="margin-bottom:10px;">
                <div class="row">
                    <!-- form -->
                    <div class="col-md-12 form-video">
                        <form action="return false;" id="form_search" style="margin: 20px auto 40px auto; max-width:700px;">
                          <div class="input-group" bis_skin_checked="1">
                            <input type="text" name="url" id="form_in_url" class="form-control" placeholder="https://...." style="padding-left:15px;;height: 50px;border-radius: 50px;font-size: 20px;">
                            <div class="input-group-btn" bis_skin_checked="1">
                              <button class="btn btn-default" type="submit" style="margin-left: 10px;height: 48px;border-radius: 35px;font-size: 20px;">
                                Generate
                              </button>
                            </div>
                          </div> 
                          
                        </form>
                    </div>
                    <script type="text/javascript">
                        jQuery(document).ready(function(){
                            jQuery("#form_search").on("submit",function(){ 
                                var link = jQuery("#form_in_url").val();
                                console.log(link);
                                if( link.indexOf("https://") < 0 ){
                                    alert("wrong link");
                                    return false;
                                }
                                window.open( "https://justlink.tv/single_link.php?url="+window.btoa(link) , '_self');
                                return false;
                            });
                        });
                    </script>
                    <!-- end form -->
                    <div class="col-md-12" id="video_content"> 
                        <div id="playVideoUl">
                            <?php
                            $first_link = $link[0];
                            if( strpos($first_link, ">") !== false ){
                                $exp_l = explode(">",$first_link);
                                $first_link = $exp_l[1];
                            }
                        
                            ?> 
                            <!-- <iframe src="<?php echo $first_link;?>?ref=moviehqu.com" class="test" width="100%" height="430" frameborder="0" scrolling="no" type="application/x-shockwave-flash" allowscriptaccess="always" allowfullscreen="true" name="video_player" id="video_player"></iframe> -->
                            <iframe src="<?php echo $first_link;?>?ref=moviehqu.com" class="test" width="100%" height="430" frameborder="0" scrolling="no" type="application/x-shockwave-flash" allowscriptaccess="always" allowfullscreen="true" name="video_player" id="video_player"></iframe>
                        </div> 
                    </div>
                </div> 
                <div class="row" style="margin-top:10px;">
                    <div class="" style="padding:0" id="div_content_button">
                        <div class="col-md-2" style="text-align:left;" id="box_back_btn_pc">
                            
                            <?php if( $backlink != "" ){
                                ?>
                            <a href="<?php echo $backlink;?>"><button class="btn btn-back"><i class="fas fa-arrow-left"></i> 돌아가기</button></a>
                                <?php 
                            }?>
                        </div>
                        <div class="col-md-10" id="row_contain_link">
                            <div style="float:left;" id="box_back_btn_mobile">
                                <a href="<?php echo $backlink;?>"><button class="btn btn-back"><i class="fas fa-arrow-left"></i> 돌아가기</button></a>
                            </div>
                            <div class="flex-btn-hq">
                            <?php
                            $arr_check = array();
                            $num_link = 3;
                            foreach ($link as $k => $l) {
                                $l = str_replace("\r","",$l);
                                $l = str_replace("'","",$l);
                                $l = str_replace('"','',$l);
                                $part = "";
                                if( strpos($l, ">") !== false ){
                                    $exp_l = explode(">",$l);
                                    $part = str_replace("<", "", $exp_l[0]);
                                    $l = $exp_l[1];
                                    $num_link++;
                                }
                                $l_n = str_replace("https://","",$l);
                                $l_n = explode("/",$l_n);
                                $l_n = $l_n[0];
                                $l_n = explode(".",$l_n);
                                $name = "";
                                if( count($l_n) > 2 ){
                                    $name = $l_n[1];
                                }else $name = $l_n[0];

                                if( strpos($backlink , "episode") !== false ){
                                    if($name != "youtube" && ( in_array($name,$arr_check) || count($arr_check) == $num_link) ){
                                        continue;
                                    }else $arr_check[] = $name;
                                }else if($k > $num_link) break;
                                
                                $name = ucfirst($name);
                                if( $name == 'Vidground' ) $name = "HQ Plus";
                                if( $name == 'Short' ) $name = "Hydrax";
                                if( $name == 'Asianembed' || $name == 'Dembed1' || $name == 'Asianplay' ) $name = "K-Vid";
                                if( $part != "" ){
                                    $name .= " - ".$part;
                                }
                                ?>
                                 <?php
                                        

                                        if (strpos($l, "https://videojs.vidground.com") !== false) {
                                           ?>
                                            <button class="btn btn-play btn-hqplus <?php echo $k==0?'active':''; ?>" onclick="playback(this,'<?php echo $l;?>')"> <i class="fas fa-play"></i> <?php echo $name;?></button>
                                           <?php
                                        } else if(strpos($l, "https://asianhdplay.pro") !== false){
                                            $count_asia = strlen($l);
                                            if ($count_asia > 80) {
                                         ?>
                                            <button class="btn btn-play btn-asia-show <?php echo $k==0?'active':''; ?>" onclick="playback(this,'<?php echo $l;?>')"> <i class="fas fa-play"></i> <?php echo $name;?></button>
                                        <?php  } ?>
                                            
                                             <button class="btn btn-play btn-asia <?php echo $k==0?'active':''; ?>" onclick="playback(this,'<?php echo $l;?>')"> <i class="fas fa-play"></i> <?php echo $name;?></button>
                                        <?php }else {
                                           ?>
                                           <button class="btn btn-play <?php echo $k==0?'active':''; ?>" onclick="playback(this,'<?php echo $l;?>')"> <i class="fas fa-play"></i> <?php echo $name;?></button>
                                           <?php
                                        }
                                    ?>
                               <!--  <button class="btn btn-play <?php //echo $k==0?'active':''; ?>" onclick="playback(this,'<?php //echo $l;?>')"> <i class="fas fa-play"></i> <?php //echo $name;?></button> -->
                                <?php
                            }
                            ?>
                              </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
<!-- 영상아래 메시지지
<center>
현재 HQ Plus 링크에서 재생 오류가 발생하고 있습니다. Hydrax 링크를 이용해 주세요.
</center>
-->
        <div class="row" style="margin-top:20px;"></div>
    </div>
    
    
     <div id="ads_box_S">
                
                <div class="container">
                    <div class="row ads-kokoa">
                       <div class="col-md-1 col-sm-1 col-12"></div>
                        <div class="col-md-10 col-sm-10 col-12">
                            <center style="margin-bottom: 15px">
                                <div class="ads-top">
                                    <div class="home-section home-movie-section-aside-header">
                
                                    </div>
                                   
                                </div>
                                <div class="row ads-middle">
                                        <div class="col-md-1 col-sm-1 col-12"></div>
                                        <div class="col-md-5 col-sm-5 col-12 ads-left" style="margin-bottom: 10px">
                                            <div class="ads-box-child"></div>
                                         
                                        </div>
                                        <div class="col-md-5 col-sm-5 col-12 ads-right" style="margin-bottom: 10px">
                                            <div class="single-tv-show-ads"></div>
                                           
                                        </div>
                                        <div class="col-md-1 col-sm-1 col-12"></div>
                                </div> 
                                
                                <div class="ads-bottom">
                                    <div class="home-section home-ads-bt-feature">
                                        <div></div>
                                       
                                    </div> 
                                   
                                </div>
                            </center>

                            
                        </div>
                        
                        <div class="col-md-1 col-sm-1 col-12">
                            
                        </div>

                    </div>
                </div>
         </div>


        
<script type="text/javascript">
    setInterval(function(){
        $(document).ready(function() {
           
            //top
            if ($('.ads-top').find('.kskdDiv.kskdCls').attr('data-kiosked-state') === 'destroyed') {
              console.log('Have Attribute destroyed top');
                $('.ads-top').css('margin', '10px');
                $('.ads-top').css('height', '0');
            } else {
                console.log('No Attribute destroyed top');
                 $('.ads-top').css('marginTop', '40px');
            }

            //right
            if ($('.ads-right').find('.kskdDiv.kskdCls').attr('data-kiosked-state') === 'destroyed') {
              console.log('Have Attribute destroyed right');
                $('.ads-right').css('marginTop', '10px');
                $('.ads-right').css('height', '0 ');
                

            } else {
                console.log('No Attribute destroyed right');
                $('.ads-right').css('margin-top', '320px');
                var windowWidth = $(window).width();
                if (windowWidth < 1016) {
                    $('.ads-right').css('margin-top', '290px');

                }
                if (windowWidth >= 768 && windowWidth <= 998) {
                    $('.ads-right').css('margin-top', '250px');

                }
                if (windowWidth >= 576 && windowWidth <= 767) {
                    $('.ads-right').css('max-width', '300px');
                    $('.ads-right').css('height', 'auto');
                    console.log(windowWidth);
                    $('.ads-right').css('margin-top', '250px');

                }
                
                if (windowWidth < 575) {
                    $('.ads-right').css('margin-top', '310px');

                }
                
                 
            }

            //left
            if ($('.ads-left').find('.kskdDiv.kskdCls').attr('data-kiosked-state') === 'destroyed') {
                
                console.log('Have Attribute destroyed left');
                $('.ads-left').css('margin-top', '0');
                $('.ads-left').css('height', '0');
 
            } else { 

                console.log('No Attribute destroyed left');
                $('.ads-left').css('margin-top', '320px');

                var windowWidth = $(window).width();
                if (windowWidth < 1016) {
                    $('.ads-left').css('margin-top', '290px');

                }
                if (windowWidth >= 768 && windowWidth <= 998) {
                    $('.ads-left').css('margin-top', '250px');

                }
                if (windowWidth >= 576 && windowWidth <= 767) {
                    $('.ads-left').css('max-width', '300px');
                    $('.ads-left').css('height', 'auto');
                    console.log(windowWidth);
                     $('.ads-left').css('margin-top', '250px');
                }
                
                if (windowWidth < 575) {
                    $('.ads-left').css('margin-top', '310px');

                }
                

                 
            }


           
        });
    },2000);   

   </script>

<script type="text/javascript">
    $(".btn-play.btn-hqplus").trigger('click');
</script>

<script disable-devtool-auto src='https://cdn.jsdelivr.net/npm/disable-devtool/disable-devtool.min.js'></script>
</body>

</html>
 
