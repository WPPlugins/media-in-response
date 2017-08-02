<?php
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

global $wpdb;

$videos = $wpdb->get_results("SELECT * FROM ".$wpdb->mir_videos." WHERE video_processed = '0'");

foreach ($videos as $video) 
{

	$ffmpeg				= "/usr/local/bin/ffmpeg";
	$FileName			= $video->video_filename;
	$flvName			=  $video->video_hash.'.flv';

	$dir = str_replace('//', '/', MIR_VIDEO_DIR);

	$videoPath = $dir.'/'.$FileName;
	$flvPath = $dir.'/'.$flvName;

	if (is_file($videoPath)) 
  {
		$ffmpegObj = new ffmpeg_movie($videoPath);

    /**
     * If this is a valid movie
     */         
		if ($ffmpegObj) 
    {
			$srcFPS = $ffmpegObj->getFrameRate();
			$srcAB = intval($ffmpegObj->getAudioBitRate()/1000);
			$srcAR = $ffmpegObj->getAudioSampleRate();

			$cmd = $ffmpeg . " -i " . $videoPath . " -ar 44100 -ab 64000 -acodec libmp3lame -f flv -deinterlace -nr 500 -s 320x240 -aspect 4:3 -r ".$srcFPS." -b 270k -me_range ".$srcFPS." -i_qfactor 0.71 -g 500 " . $flvPath . " 2>&1";

      /**
       * Here is the shell command execute
       */             
			$handle = @popen($cmd, "r");
			pclose($handle);
	
			if (is_file($flvPath) && filesize($flvPath) > 0) 
      {
				@unlink($videoPath);
				$ID = $video->video_id;
				$wpdb->query("UPDATE ".$wpdb->mir_videos." SET video_processed = '1', video_filename = '".$flvName."', video_public='1' WHERE video_id = '".$ID."'");
			}
		}
	} 
  else 
  {
    $wpdb->query("DELETE FROM ".$wpdb->mir_videos." WHERE video_id = '".intval($ID)."' LIMIT 1");
  }
}
?>