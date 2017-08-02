<?php
class ImageResizer {
	var $gdinfo;
	var $originalimage;
	var $resizedimage;
	var $orig_width;
	var $orig_height;
	var $maxWidth = 0;
	var $maxHeight = 0;
	var $originaltype;
	var $bgCol = null;
	
	function ImageResizer() 
  {
		$this->gdinfo = gd_info();
	}
	
	function openImageFile($file) 
  {
		if (!file_exists($file)) 
    {
			trigger_error(sprintf(__('File does not exist: %s','mir'), $file), E_USER_ERROR);
			return false;
		}

		$imginfo = getimagesize($file);
		
		switch ($imginfo[2]) 
    {
			case 1: // GIF
				$image = imagecreatefromgif($file) or trigger_error(sprintf(__('This file is not in GIF format: %s.','mir'), $file), E_USER_ERROR);
				$this->originaltype = 'GIF';
				break;

			case 2: // JPEG
				$image = imagecreatefromjpeg($file) or trigger_error(sprintf(__('This file is not in JPG format: %s.','mir'), $file), E_USER_ERROR);
				$this->originaltype = 'JPEG';
				break;

			case 3: // PNG
				$image = imagecreatefrompng($file) or trigger_error(sprintf(__('This file is not in PNG format: %s.','mir'), $file), E_USER_ERROR);
				$this->originaltype = 'PNG';
				break;
			
			default:			
				trigger_error(sprintf(__("Can't determinate image type: %s (%s , %s)",'mir'), $file,$imginfo[2],$imginfo['mime']), E_USER_ERROR);
		}
		
		$this->originalimage = $image;
		$this->resizedimage = $image;
		$this->orig_width = $imginfo[0];
		$this->orig_height = $imginfo[1];
	}
		
	function outputJPEG($file=null, $quality=80) 
  {
		if ($file === null) header('Content-type: image/jpeg');
		imagejpeg($this->resizedimage, $file, $quality);
	}

	function outputGIF($file=null) 
  {
		if ($file === null) header('Content-type: image/gif');
		imagegif($this->resizedimage, $file);
	}

	function outputPNG($file=null) 
  {
		if ($file === null) header('Content-type: image/png');
		imagepng($this->resizedimage, $file);
	}
	
	function setDimensions($w, $h) 
  {
		$this->maxWidth = $w;
		$this->maxHeight = $h;
	}
	
	function factor($enlarge=false, $max=false) 
  {
		if ($this->orig_width <> $this->maxWidth) $factorX = $this->maxWidth / $this->orig_width;
		else $factorX = 1;
		if ($this->orig_height <> $this->maxHeight) $factorY = $this->maxHeight / $this->orig_height;
		else $factorY = 1;
		if ($max) $factor = max($factorX, $factorY);
		else $factor = min($factorX, $factorY);
		if ($factor > 1 && !$enlarge) return 1;
		else return $factor;
	}

	function resizeNormal($enlarge=false) 
  {
		$factor = $this->factor($enlarge, false);
		$rX = floor($this->orig_width * $factor);
		$rY = floor($this->orig_height * $factor);
		$this->resizedimage = imagecreatetruecolor($rX, $rY);
		imagecopyresampled($this->resizedimage, $this->originalimage, 0, 0, 0, 0, $rX, $rY, $this->orig_width, $this->orig_height);
	}

	function resizeCrop($enlarge=false) 
  {
		$factor = $this->factor($enlarge, true);
		$rX = floor($this->orig_width * $factor);
		$qX = $rX;
		$rY = floor($this->orig_height * $factor);
		$qY = $rY;
		
		$cropX = 0;
		$cropY = 0;
		if ($rX > $this->maxWidth) 
    {
			$cropX = ($this->maxWidth - $rX) / 2;
			$rX = $this->maxWidth;
		}
    elseif ($rY > $this->maxHeight) 
    {
			$cropY = ($this->maxHeight - $rY) / 2;
			$rY = $this->maxHeight;
		}

		$this->resizedimage = imagecreatetruecolor($rX, $rY);
		imagecopyresampled($this->resizedimage, $this->originalimage, $cropX, $cropY, 0, 0, $qX, $qY, $this->orig_width, $this->orig_height);
	}
	
	function getOriginalType() 
  {
		return $this->originaltype;
	}
}
?>