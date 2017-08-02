<?php
/*
Plugin Name: Media in response
Plugin URI: http://media-in-response.dev.rain.hu/
Description: Media in response
Author: Zsolt Lakatos
Version: 0.5
Author URI: http://djz.hu/
*/

/*  
	Copyright 2008  Zsolt Lakatos  (email : djz@djz.hu)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/


if(!defined('ABSPATH'))
{
  $root = dirname(dirname(dirname(dirname(__FILE__))));
  if (file_exists($root.'/wp-load.php')) 
  {
      // WP 2.6
      require_once($root.'/wp-load.php');
  }
  else 
  {
      // Before 2.6
      require_once($root.'/wp-config.php');
  }
} 

if ( !defined('WP_CONTENT_URL') ) define( 'WP_CONTENT_URL', get_option('siteurl') . '/wp-content');
if ( !defined('WP_CONTENT_DIR') ) define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
/**
 * WordPress 2.6 compatibility :)
 */  
global $wpdb;

/**
 * Some plugin constants
 */  
define('MIR_DIR', WP_CONTENT_URL.'/plugins/media-in-response');
define('MIR_URL', MIR_DIR.'/media-in-response.php');

define('MIR_PICTURE_DIR_NAME', 'mir-pictures');
define('MIR_VIDEO_DIR_NAME', 'mir-videos');
define('MIR_TEMP_NAME', 'temp');
define('MIR_THUMB_NAME', 'thumb');

define('MIR_PICTURE_DIR', WP_CONTENT_DIR . '/' . MIR_PICTURE_DIR_NAME);
define('MIR_VIDEO_DIR', WP_CONTENT_DIR . '/' . MIR_VIDEO_DIR_NAME);

define('MIR_PICTURE_TEMP', MIR_PICTURE_DIR . '/' . MIR_TEMP_NAME);
define('MIR_VIDEO_TEMP', MIR_VIDEO_DIR . '/' . MIR_TEMP_NAME);

define('MIR_PICTURE_URL_DIR', WP_CONTENT_URL.'/' . MIR_PICTURE_DIR_NAME);
define('MIR_VIDEO_URL_DIR', WP_CONTENT_URL . '/' . MIR_VIDEO_DIR_NAME);

define('MIR_PICTURE_URL_TEMP', MIR_PICTURE_URL_DIR . '/' . MIR_TEMP_NAME);
define('MIR_VIDEO_URL_TEMP', MIR_VIDEO_URL_DIR . '/' . MIR_TEMP_NAME);

define('MIR_PICTURE_MAXDIMENSION','450');

define('MIR_VIDEO_TABLE',$wpdb->prefix.'videos');
define('MIR_PICTURE_TABLE',$wpdb->prefix.'comment_pictures');

define('MIR_DEBUG','0');
define('MIR_LOG_FILE', WP_CONTENT_DIR.'/script.log');

$wpdb->mir_videos = MIR_VIDEO_TABLE;
$wpdb->mir_pictures = MIR_PICTURE_TABLE;

/**
 * Handling file upload
 */ 
if (!empty($_FILES))
{ 
  if(!empty($_FILES['mir-picture']))
  { 
    mir_upload_picture();
  }
  elseif ($_FILES['mir-video'])
  {
    mir_upload_video();
  } 
}

/**
 * AJAX functions
 */ 
if(strstr($_SERVER['PHP_SELF'], "media-in-response.php"))
{
  if (isset($_GET['js'])) { mir_get_js(); }
  elseif (!empty($_POST['comment'])) mir_get_form_comment();
  elseif (!empty($_GET['form']) && ($_GET['form'] == 'common')) mir_get_form_common();
  elseif (!empty($_GET['form']) && ($_GET['form'] == 'buttons')) mir_get_form_buttons();
  elseif (!empty($_GET['comment_id']) && ($_GET['show'] == 'media')) mir_show_media($_GET['comment_id']);
}

	/**
	 * Captures a frame from the middle of the movie
	 *
	 * @param string $filename
	 * @param string $thumbnail thumbnail file location	 
	 * @return boolean
	 */ 
function mir_generate_thumbnail($filename, $thumbnail)
{
    $movie = new ffmpeg_movie($filename);
    $frameNum = $movie->getFrameCount();
    $frameNum=($frameNum/2);
    
    $frame = $movie->getFrame($frameNum);
    
    if ($frame)
    {    
      $capturedFrame = $frame->toGDImage();
      return imagejpeg($capturedFrame, $thumbnail);
    }
    else
    {
      return false;
    }
}

	/**
	 * Captures a frame from the middle of the movie
	 *
	 * @param string $filename
	 * @param string $newFilename
	 * @param string $maxdimension
	 * @param object $error	 
	 */ 
function mir_resize_image($filename, $newFilename, $maxdimension, &$error)
{
	require_once('imageresizer.inc.php');
	$resizer = new ImageResizer();
	$result = $resizer->openImageFile($filename);
	if ($result === false) $error = sprintf(__('File does not exist: %s','mir'), $filename);
	$resizer->setDimensions($maxdimension, $maxdimension);
	$resizer->resizeNormal();
	switch ($resizer->getOriginalType()) 
  {
		case 'GIF':
			$resizer->outputGIF($newFilename);
		break;

		case 'JPEG':
			$resizer->outputJPEG($newFilename, 80);
		break;

		case 'PNG':
			$resizer->outputPNG($newFilename);
		break;
	}
}

	/**
	 * Handles javascript constants
	 */ 
function mir_get_js()
{
	header("Content-type: text/javascript"); 
?>

var mir_orig_action = '';
var mir_url = '<?php echo MIR_URL ?>';
var mir_site_url = '<?php echo get_option('siteurl'); ?>';
var mir_content_url = '<?php echo WP_CONTENT_URL; ?>';
var mir_swiff_url = '<?php echo WP_CONTENT_URL; ?>/plugins/media-in-response/swf/Swiff.Uploader.swf';

window.addEvent('domready', function() {
	mir_init();
});
<?php
	die;
}

	/**
	 * Provide the upload form, for AJAX
	 */ 
function mir_get_form_common()
{
header("Content-Type: text/html");
?>
	<div id="mir_information">
		<h2><?php _e('To add media to your comment please click the button below, to see the available types'); ?></h2>
		<p class="small"><?php _e('Supported image types: JPG, GIF and PNG.'); ?></p>
		<p class="small"><?php _e('Supported video types: AVI, MPG, MP4, MOV and WMV.'); ?></p>
		<p class="small"><?php echo sprintf(__('Please upload only files smaller than %s.','mir'), ini_get('upload_max_filesize')); ?></p>
	</div>


	<div id="mir_buttons" style="margin:10px 0;">
		<input type="button" id="mir_select_type" value="<?php _e('Add media to this response!'); ?>">
	</div>
<?php
die;
}

	/**
	 * Provide the buttons, for AJAX
	 */ 
function mir_get_form_buttons()
{
header("Content-type: text/plain");
?>
  <div id="mir-picture-message" class="hide">The image has been uploaded</div><br/>
  <div id="mir-video-message" class="hide">The video has been uploaded</div><br/>
  <input type="button" id="mir_upload_picture" value="Upload Image">
  <input type="button" id="mir_upload_video" value="Upload Video"><br/>
  
  <div id="mir-picture" class="hide">	 
  	<div id="mir-picture-status" class="hide">
  		<div>
  			<img src="/de/blog/wp-content/plugins/media-in-response/images/loading.gif" class="progress current-progress" />
  		</div>
  		<div class="current-text"></div>
  	</div>
  	<input id="mir-picture-file" name="comment_picture" type="hidden" value=""/>
  	
  	<div id="mir-picture-preview">
  	 <?php _e('Picture Preview'); ?><br/>
  	 <img id="mir-picture-image" src="<?php echo MIR_DIR; ?>/images/picture_default.jpg" alt="<?php _e('Picture Preview'); ?>">
  	</div>
  </div>

  <div id="mir-video" class="hide">
    <p><?php _e('The video upload progress may take a while, please be patient'); ?></p><br/>	 
  	<div id="mir-video-status" class="hide">
  		<div>
  			<img src="/de/blog/wp-content/plugins/media-in-response/images/loading.gif" class="progress current-progress" />
  		</div>
  	</div>
  	<input id="mir-video-file" type="hidden" name="comment_video" value=""/>
  	
  	<div id="mir-video-preview">
  	 <?php _e('Video Preview'); ?><br/>
  	 <img id="mir-video-image"  src="<?php echo MIR_DIR; ?>/images/video_default.jpg" alt="<?php _e('Video Preview'); ?>">
  	</div>
  </div>
<?php
die;
}
	/**
	 * Handles video upload
	 */ 
function mir_upload_video()
{
	$file = $_FILES['mir-video']['tmp_name'];
	$orig_name = strtolower($_FILES['mir-video']['name']);
	$error = false;
	$size = false;
 
	if ($_FILES['mir-video']['error'] == 1)
	{
    $error = sprintf(__('Please upload only files smaller than %s.','mir'), ini_get('upload_max_filesize'));
	}

	if ($error)
	{
		$result['result'] = 'failed';
		$result['error'] = $error;
	}
	else
	{
		$result['result'] = 'success';
		
		$pos = strrpos($orig_name,'.');
		$ext = substr($orig_name,$pos+1,strlen($orig_name)-$pos);
		$result['ext'] = $ext;
		$result['size'] = "Uploaded a video";
		$hash = substr(md5(time()),10,6);
		$newFilename = $hash.".".$ext;

    $target = MIR_VIDEO_TEMP.'/'.$newFilename;
		$ret = move_uploaded_file($file,$target);
		
		$src = '';
		
		if ($ret)
		{
		  $thumbnail = '/' . MIR_THUMB_NAME . '/'.$hash.'.jpg';
		
		  if (class_exists('ffmpeg_movie'))
		  {
		    mir_generate_thumbnail($target,MIR_VIDEO_TEMP.$thumbnail);
		    
		    if(is_file(MIR_VIDEO_TEMP.'/'.$thumbnail))
		    {
          $src = MIR_VIDEO_URL_TEMP.$thumbnail;
        }
		  }
    }
		  
         
	 $result['filename'] = $newFilename;
	 $result['src'] = $src;
                 
  }
  
  if (!headers_sent() )
  {
  	header('Content-type: application/json');
  }
  
  echo json_encode($result);
  die;
}

	/**
	 * Handles picture upload
	 */ 
function mir_upload_picture()
{
	$file = $_FILES['mir-picture']['tmp_name'];
	$error = false;
	$size = false;
 
	if ($_FILES['mir-picture']['error'] == 1)
	{
		$error = sprintf(__('Please upload only files smaller than %s.','mir'), ini_get('upload_max_filesize'));
	}
	
	if (empty($error) && !($size = @getimagesize($file) ) )
	{
		$error = __('Please upload only images, no other files are supported.','mir');
	}
	if (empty($error) && !in_array($size[2], array(1, 2, 3, 7, 8) ) )
	{
		$error = __('Please upload only supported type images.','mir');
	}
	if (empty($error) && (($size[0] < 25) || ($size[1] < 25)))
	{
	   $error = sprintf(__('Please upload an image bigger than %s.','mir'), "25px");
	}
	
	$addr = gethostbyaddr($_SERVER['REMOTE_ADDR']);
  
	if ($error)
	{
		$result['result'] = 'failed';
		$result['error'] = $error;
	}
	else
	{
		$result['result'] = 'success';
		
		$mime = $size['mime'];
		$pos = strpos($mime,'/');
		$ext = substr($mime,$pos+1,strlen($mime)-$pos);
		$result['ext'] = $ext;
		if ($ext == 'jpeg') $ext = 'jpg';
		
		$result['size'] = sprintf(__('Uploaded an image %s with %dpx/%dpx.','mir'), $size['mime'],$size[0],$size[1]);
		
		$hash = substr(md5(time()),10,6);
		$newFilename = $hash.".".$ext;

	  $ret = move_uploaded_file($file,MIR_PICTURE_TEMP.'/'.$newFilename);
		
		if($ret)
		{
		  $result['filename'] = $newFilename;
		  $result['src'] = MIR_PICTURE_URL_TEMP.'/'.$newFilename;
		  
 		  $newSmallFilename = MIR_THUMB_NAME . '/' . $hash . '.' . $ext;
 		  mir_resize_image(MIR_PICTURE_TEMP.'/'.$newFilename, MIR_PICTURE_TEMP.'/'.$newSmallFilename, MIR_PICTURE_MAXDIMENSION, &$error);
    }
	}
	
  if (!headers_sent() )
  {
  	header('Content-type: application/json');
  }
  
  echo json_encode($result);
  die;
}

	/**
	 * AJAX error handler
	 */ 
function mir_ajax_error($m) 
{
  header('HTTP/1.0 406 Not Acceptable'); 
  die($m); 
}

	/**
	 * AJAX comment handling	 
	 */ 
function mir_get_form_comment()
{
  global $wpdb, $user_identity, $user_email, $user_url, $user_ID;
  
  foreach($_POST as $k => $v) $_POST[$k] = trim(urldecode($v));

  /**
   * extract & alias POST variables
   */
   extract($_POST, EXTR_PREFIX_ALL, '');

  /**
   * get the post comment_status
   */
  $post_status = $wpdb->get_var("SELECT comment_status FROM {$wpdb->posts} WHERE ID = '".$wpdb->escape($_comment_post_ID)."' LIMIT 1;");
  if ( empty($post_status) ) // make sure the post exists
    mir_ajax_error(__("That post doesn't even exist!",'mir'));
  if ( $post_status == 'closed' ) // and the post is not closed for comments
    mir_ajax_error(__("Sorry, comments are closed.",'mir'));

  /**
   * Get user information
   */     
  get_currentuserinfo();
  
  if ( $user_ID )
  {
    $_author = addslashes($user_identity); // name
    $_email = addslashes($user_email); // email
    $_url = addslashes($user_url); // url
  }

  if ( $_comment == '' ) mir_ajax_error(__('Please fill in the comment field.','mir'));

  /**
   * Get user information
   */
  if($wpdb->get_var("
  SELECT comment_ID FROM {$wpdb->comments}
  WHERE comment_post_ID = '".$wpdb->escape($_comment_post_ID)."'
    AND ( comment_author = '".$wpdb->escape($_author)."'
  ".($_email? " OR comment_author_email = '".$wpdb->escape($_email)."'" : "")."
  ) AND comment_content = '".$wpdb->escape($_comment)."'
  LIMIT 1;"))
    mir_ajax_error(__('You already said that before.','mir'));

  /**
   * Insert
   */
  $comment_ID = wp_new_comment(array(
    'comment_post_ID' => $_comment_post_ID,
    'comment_author' => $_author,
    'comment_author_email' => $_email,
    'comment_author_url' => $_url,
    'comment_content' => $_comment,
    'comment_type' => '',
    'user_ID' => $user_ID
  ));

  /**
   * Cookie handling
   */     
  if ( !$user_ID ) 
  { // remember cookie
    setcookie('comment_author_' . COOKIEHASH, $_author, time() + 30000000, COOKIEPATH, COOKIE_DOMAIN);
    setcookie('comment_author_email_' . COOKIEHASH, $_email, time() + 30000000, COOKIEPATH, COOKIE_DOMAIN);
    setcookie('comment_author_url_' . COOKIEHASH, $_url, time() + 30000000, COOKIEPATH, COOKIE_DOMAIN);
  }

  $picture_pattern = "/^([a-f0-9]{6})\.(jpg|gif|png)/i";
  $video_pattern = "/^([a-f0-9]{6})\.(wmv|mov|avi|3gp|mpg)/i";
  
  if (!empty($_POST['comment_picture']) && preg_match($picture_pattern,$_POST['comment_picture']))
  {
    $mir_image = $_POST['comment_picture'];
    
    $pos = strrpos($mir_image,'.');
    $hash = substr($mir_image,0,$pos);
    $ext = substr($mir_image,$pos+1,3);
  /**
   *  Only 3 characters, because we did the jpeg->jpg type conversion before
   */

 		$newSmallFilename = MIR_THUMB_NAME . '/' . $hash . '.' . $ext;
 		
 		rename(MIR_PICTURE_TEMP.'/'.$mir_image,MIR_PICTURE_DIR.'/'.$mir_image);
 		mir_resize_image(MIR_PICTURE_DIR.'/'.$mir_image, MIR_PICTURE_DIR.'/'.$newSmallFilename, MIR_PICTURE_MAXDIMENSION, &$error);
  
    $data = array('picture_filename'=>$mir_image,'picture_owner'=>$user_ID,'picture_attached'=>$comment_ID,'picture_public'=>'1','picture_created'=>date('Y-m-d H:i:s'));
    $ret = $wpdb->insert(MIR_PICTURE_TABLE,$data);
  }
  if (!empty($_POST['comment_video']) && preg_match($video_pattern,$_POST['comment_video']))
  {
    $mir_video = $_POST['comment_video'];
    
    $pos = strrpos($mir_video,'.');
    $hash = substr($mir_video,0,$pos);
    $ext = substr($mir_video,$pos+1,3);
  
    $thumb = '/' . MIR_THUMB_NAME . '/'.$hash.'.jpg';
  
    $ret = @rename(MIR_VIDEO_TEMP.'/'.$mir_video,MIR_VIDEO_DIR.'/'.$mir_video);
    
    if($ret)
    {
      @rename(MIR_VIDEO_TEMP.'/'.$thumb,MIR_VIDEO_DIR.'/'.$thumb);
      $data = array('video_hash'=>$hash,'video_filename'=>$mir_video,'video_owner'=>$user_ID,'video_attached'=>$comment_ID,'video_type'=>'comment','video_public'=>'1','video_created'=>date('Y-m-d H:i:s'));
            
      $ret = $wpdb->insert(MIR_VIDEO_TABLE,$data);  
     }
  }
}

function mir_show_media($comment_ID) 
{
  global $wpdb;

  if (!is_numeric($comment_ID)) mir_ajax_error(__("Don't hack, brother...",'mir'));

  $videoSearch = $wpdb->get_results("SELECT * FROM ".$wpdb->mir_videos." WHERE video_attached = '".intval($comment_ID)."' AND video_type = 'comment' AND video_public = '1' AND video_processed = '1'");

  $pictureSearch = $wpdb->get_results("SELECT * FROM ".$wpdb->mir_pictures." WHERE picture_attached = '".$comment_ID."' AND picture_public = '1'");    
  
  if ($videoSearch || $pictureSearch)
  {
    echo $comment_ID."|";
?>
<span><?php _e('Attached media'); ?></span><br/>
<?php
    
    if ($videoSearch)
    {
    //XXX
      $video = MIR_VIDEO_URL_DIR."/".$videoSearch[0]->video_filename;
      $image = MIR_VIDEO_URL_DIR.'/'.MIR_THUMB_NAME.'/'.$videoSearch[0]->video_hash.".jpg";
      ?>
        <embed src="<?php echo WP_CONTENT_URL; ?>/plugins/media-in-response/swf/player.swf" width="400" height="320" allowscriptaccess="always" allowfullscreen="true" flashvars="height=320&width=400&file=<?php echo $video; ?>&image=<?php echo $image; ?>&backcolor=0xCAD5DC&frontcolor=0x535353&lightcolor=0x000000&showdigits=false&usefullscreen=false&thumbsinplaylist=false" />
      <?php
    }

    if ($pictureSearch)
    {
      $picture = $pictureSearch[0];
      
      $rows = $picture%3;
      
?>      <dl class="gallery-item">
        <dt class="gallery-icon">
          <a href="<?php echo MIR_PICTURE_URL_DIR . '/' . $picture->picture_filename; ?>" target="_blank">
            <img class="attachment-thumbnail" src="<?php echo MIR_PICTURE_URL_DIR . '/' . MIR_THUMB_NAME . '/' . $picture->picture_filename; ?>">
          </a>
        </dt>
      </dl>
      <br style="clear: both;"/><?php
    }
    die;
  }
}

function mir_head()
{
?>
<link href="<?php echo WP_CONTENT_URL; ?>/plugins/media-in-response/css/mir.css" type="text/css" rel="stylesheet"/>
<script type="text/javascript" src="<?php echo WP_CONTENT_URL; ?>/plugins/media-in-response/js/mootools.js"></script>
<script type="text/javascript" src="<?php echo WP_CONTENT_URL; ?>/plugins/media-in-response/js/mir_core.js"></script>
<script type="text/javascript" src="<?php echo WP_CONTENT_URL; ?>/plugins/media-in-response/js/Swiff.Uploader.js"></script>
<script type="text/javascript" src="<?php echo WP_CONTENT_URL; ?>/plugins/media-in-response/js/Fx.ProgressBar.js"></script>
<script type="text/javascript" src="<?php echo WP_CONTENT_URL; ?>/plugins/media-in-response/js/FancyUpload2.js"></script>
<script type="text/javascript" src="<?php echo WP_CONTENT_URL; ?>/plugins/media-in-response/media-in-response.php?js"></script>
<?php
}

function mir_activate()
{
	if(@is_file(ABSPATH.'/wp-admin/upgrade-functions.php')) 
  {
		require_once(ABSPATH.'/wp-admin/upgrade-functions.php');
	}
  elseif(@is_file(ABSPATH.'/wp-admin/includes/upgrade.php')) 
  {
		require_once(ABSPATH.'/wp-admin/includes/upgrade.php');
	}
  else
  {
		die(__('We have problem finding your \'/wp-admin/upgrade-functions.php\' and \'/wp-admin/includes/upgrade.php\'','mir'));
	}

  $create_table = array();
  
	$create_table['videos'] = "CREATE TABLE $wpdb->mir_videos (".
									"video_id bigint(20) NOT NULL auto_increment,".
									"video_hash varchar(6) character set utf8 NOT NULL default '',".
									"video_owner bigint(20) default '-1',".
									"video_created datetime default NULL,".
									"video_public int(2) default '0',".
									"video_type enum('post','profile','comment') default 'comment',".
									"video_processed int(2) default '0',".
									"video_attached bigint(20) default NULL,".
									"video_filename varchar(255) default NULL,".
									"PRIMARY KEY (video_hash),".
									"UNIQUE KEY video_hash (video_hash),".
                  "UNIQUE KEY video_id (video_id) )";

	$create_table['pictures'] = "CREATE TABLE $wpdb->mir_pictures (".
									"picture_id bigint(20) NOT NULL auto_increment,".
									"picture_owner bigint(20) default '-1',".
									"picture_created datetime default NULL,".
									"picture_public int(2) default '0',".
									"picture_attached bigint(20) default NULL,".
									"picture_filename varchar(255) NOT NULL default '',".
									"PRIMARY KEY (picture_filename),".
									"UNIQUE KEY picture_filename (picture_filename),".
                  "UNIQUE KEY picture_id (picture_id) )";
  
  maybe_create_table($wpdb->mir_videos, $create_table['videos']);
  maybe_create_table($wpdb->mir_pictures, $create_table['pictures']);
  
  /**
   * Database is ready, let's create directories
   */     

define('MIR_PICTURE_DIR', WP_CONTENT_DIR . '/' . MIR_PICTURE_DIR_NAME);
define('MIR_VIDEO_DIR', WP_CONTENT_DIR . '/' . MIR_VIDEO_DIR_NAME);

define('MIR_PICTURE_TEMP', MIR_PICTURE_DIR . '/' . MIR_TEMP_NAME);
define('MIR_VIDEO_TEMP', MIR_VIDEO_DIR . '/' . MIR_TEMP_NAME);

  $mir_dirs = array(
    WP_CONTENT_DIR . '/' . MIR_VIDEO_DIR_NAME,
    WP_CONTENT_DIR . '/' . MIR_VIDEO_DIR_NAME . '/' . MIR_TEMP_NAME,
    WP_CONTENT_DIR . '/' . MIR_VIDEO_DIR_NAME . '/' . MIR_THUMB_NAME,
    WP_CONTENT_DIR . '/' . MIR_VIDEO_DIR_NAME . '/' . MIR_TEMP_NAME . '/' . MIR_THUMB_NAME,
    WP_CONTENT_DIR . '/' . MIR_PICTURE_DIR_NAME,
    WP_CONTENT_DIR . '/' . MIR_PICTURE_DIR_NAME . '/' . MIR_TEMP_NAME,
    WP_CONTENT_DIR . '/' . MIR_PICTURE_DIR_NAME . '/' . MIR_THUMB_NAME,
    WP_CONTENT_DIR . '/' . MIR_PICTURE_DIR_NAME . '/' . MIR_TEMP_NAME . '/' . MIR_THUMB_NAME     
  );
  
  foreach ($mir_dirs as $dir)
  {
    if (!empty($dir))
    {
      if (!is_dir($dir)) mkdir($dir);
    }
  }
}

function mir_textdomain() 
{
	load_plugin_textdomain('mir', WP_CONTENT_URL.'/plugins/media-in-response/lang');
}

add_action('init', 'mir_textdomain');
add_action('wp_head','mir_head');
add_action('activate_media-in-response/media-in-response.php', 'mir_activate');

?>