<?php

/**
 * Responsifyzrly - a (mostly) standalone library for generating appropriate images for use in responsive designs
 *
 * @author      R.A. Ray <robert.adam.ray@gmail.com>
 * @copyright   2012 Unit Interactive, LLC
 * @link        http://Responsifyzrly.com
 * @license     http://Responsifyzrly.com/license
 * @version     0.1
 *
 * MIT LICENSE
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to
 * the following conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
 * LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
 * OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
 * WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

class Responsifyzrly {

	// quality of JPGs in percent
	const LOW_QUAL 	= 50;
	const MED_QUAL 	= 65;
	const HIGH_QUAL = 80;

	// bandwidth response times in milliseconds
	const LOW_BAND 	= 50;
	const MED_BAND	= 300;

	// how long to keep the images in the user's browser cache in seconds
	const BROWSER_CACHE = 604800; // 7 days

	public $is_image;
	public $max_width;
	public $quality;
	public $src_path;
	public $src_name;
	public $src_width;

	function __construct($orig_path)
	{
		$this->src_path = $orig_path;

		$path_split 		= preg_split('/\\\|\//', $orig_path);
		$path_split 		= array_reverse($path_split);
		$this->src_name 	= $path_split[0];
		$this->is_image 	= TRUE;

		// does the image exist?
		if ( ! is_file($this->src_path) OR ! $dimensions = getImageSize($this->src_path))
		{
			$this->is_image = FALSE;

			return;
		}

		// get the image width
		$this->src_width 	= $dimensions[0];
		$this->src_height 	= $dimensions[1];

		// get cookie data
		$cookie = isset($_COOKIE['rspvly']) ? $_COOKIE['rspvly'] : FALSE;

		if ( ! $cookie)
		{
			$this->max_width 	= $this->src_width;
			$this->quality 		= self::MED_QUAL;
			
			return;
		}

		// get usable cookie data
		$data = $this->parse_cookie($cookie);

		// set quality
		if ($data['bandwidth'] < self::LOW_BAND)
		{
			$this->quality = self::LOW_QUAL;
		}
		elseif ($data['bandwidth'] < self::MED_BAND)
		{
			$this->quality = self::MED_QUAL;
		}
		else
		{
			$this->quality = self::HIGH_QUAL;
		}

		// max_width is the reported max resolution multiplied by the pixel ratio
		$this->max_width = $data['resolution'] * $data['pixel_ratio'];

		// do not enlarge the image
		if ($this->max_width > $this->src_width)
		{
			$this->max_width = $this->src_width;
		}
	}





	public function get_cache_file_name()
	{
		$extension = strtolower(pathinfo($this->src_path, PATHINFO_EXTENSION));

		if ($extension == 'png' OR $extension == 'gif')
		{
			return substr_replace($this->src_name, "-$this->max_width", strrpos($this->src_name, '.'), 0);
		}
		else
		{
			return substr_replace($this->src_name, "-$this->quality-$this->max_width", strrpos($this->src_name, '.'), 0);
		}
	}




	/* generates the given cache file for the given source file with the given resolution */
	public function generate_cache_image()
	{
		$handle 		= fopen($this->src_path, 'r');
		$image_string 	= fread($handle, filesize($this->src_path));

		fclose($handle);

		$src 		= imagecreatefromstring($image_string);
		$extension 	= strtolower(pathinfo($this->src_path, PATHINFO_EXTENSION));

		// Do we need to downscale the image?
		if ($this->src_width > $this->max_width)  
		{
			$ratio      = $this->src_height/$this->src_width;
			$new_height = ceil($this->max_width * $ratio);

			// re-sized image
			$dst = ImageCreateTrueColor($this->max_width, $new_height); 

			if ($extension == 'png')
			{
				imagealphablending($dst, false);
				
				imagesavealpha($dst,true);
				
				$transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
				
				imagefilledrectangle($dst, 0, 0, $this->max_width, $new_height, $transparent);
			}

			// do the resize in memory
			ImageCopyResampled($dst, $src, 0, 0, 0, 0, $this->max_width, $new_height, $this->src_width, $this->src_height);

			ImageDestroy($src);

			// sharpen the image
			$intSharpness 	= $this->find_sharp($this->src_width, $this->max_width);
		    $arrMatrix 		= 	array(
							        array(-1, -2, -1),
							        array(-2, $intSharpness + 12, -2),
							        array(-1, -2, -1)
							    );

		    imageconvolution($dst, $arrMatrix, $intSharpness, 0);
		}
		else
		{
			$dst = $src;
		}

		// use output buffering to capture outputted image stream
		ob_start();
		
		switch ($extension) 
		{
			case 'png':
			
				ImagePng($dst);
			
				break;
			
			case 'gif':
			
				ImageGif($dst);
			
				break;
			
			default:
			
				ImageJpeg($dst, NULL, $this->quality);
			
				break;
		}

		$new_image = ob_get_clean(); 

		ImageDestroy($dst);

		//$this->cache_image = $new_image;

		return $new_image;
	}




	function show_image($image_string) 
	{
		$extension 	= strtolower(pathinfo($this->src_path, PATHINFO_EXTENSION));

		if (in_array($extension, array('png', 'gif', 'jpeg'))) 
		{
			header("Content-Type: image/".$extension);
		} 
		else 
		{
			header("Content-Type: image/jpeg");
		}

		header("Cache-Control: private, max-age=".self::BROWSER_CACHE);
		header('Expires: '.gmdate('D, d M Y H:i:s', time() + self::BROWSER_CACHE).' GMT');
		header('Content-Length: '.strlen($image_string));

		echo $image_string;
		
		exit();
	}




	private function find_sharp($intOrig, $intFinal) 
	{
		$intFinal = $intFinal * (750.0 / $intOrig);
		$intA     = 52;
		$intB     = -0.27810650887573124;
		$intC     = .00047337278106508946;
		$intRes   = $intA + $intB * $intFinal + $intC * $intFinal * $intFinal;
		
		return max(round($intRes), 0);
	}




	private function parse_cookie($cookie)
	{
		$cookie = trim($cookie, '&');
		$cookie = explode('&', $cookie);

		if (count($cookie))
		{
			foreach ($cookie as $key => $value)
			{
				$tmp = explode('=', $value);

				$cookie[$tmp[0]] = $tmp[1];

				unset($cookie[$key]);
			}
		}

		return $cookie;
	}
}