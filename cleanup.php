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

$dirs = array(MIR_VIDEO_TEMP,MYVIDEO_TEMP);

foreach ($dirs as $dir)
{
  if (is_dir($dir)) 
  {
      if ($dh = opendir($dir)) 
      {
          $now = time();
          
          echo "Now:".$now."<br>";
          
          while (($file = readdir($dh)) !== false) 
          {
            if (is_file($dir."/".$file))
            {
              $pos = strrpos($file,'.');
              $hash = substr($file,0,$pos);
            
              $ctime = filectime($dir.'/'.$file);
              echo "filename: $file : hash: ". $hash ." changed: " . $ctime . "<br>\n";
              
              if ($ctime+900 < $now)
              /**
                * Older than 15 minutes
                *
                */
              {
                echo "Delete file: ".$dir."/".$file."<br>";
                
                $thumb_file = $dir."/thumb/".$hash.".jpg"; 
                if (is_file($thumb_file))
                {
                  echo "Delete thumbnail: ".$dir."/".$file."<br>";
                  unlink($thumb_file);
                }
                unlink($dir.'/'.$file);
                
              }
              
            }
            else
            {
              /**
               * Skip              
               */
            }
          }
          closedir($dh);
      }
  }
}
die;
?>