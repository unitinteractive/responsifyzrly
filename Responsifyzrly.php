<?php

/**
 * Responsifyzrly 
 * A library for generating appropriate images for use in responsive designs
 * This library provideds the methods for producing and serving optimized images
 *
 * @version     X.X.X
 * @copyright   2012 Unit Interactive, LLC - UnitInteractive.com
 * @author      R.A. Ray - RobertAdamRay.com
 * @link        https://github.com/unitinteractive/responsifyzrly
 * @license     Dual licensed under the MIT license and GPL license:
 * 				http://opensource.org/licenses/MIT
 * 				http://www.gnu.org/licenses/gpl.html
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

	// properties
	public $cache_file_name;
	public $is_image;
	public $max_width;
	public $quality;
	public $src_name;
	public $src_path;
	public $src_width;
	

	/**
	 * Sets all of the above properties upon instantiation
	 *
	 * @param 	string
	 * 			$orig_path is the server path to the image
	 */
	function __construct($orig_path)
	{
		$this->src_path 	= $orig_path;
		$path_split 		= array_reverse(preg_split('/\\\|\//', $orig_path));
		$this->src_name 	= $path_split[0];
		$this->is_image 	= TRUE;

		// does the image exist?
		if ( ! is_file($this->src_path) OR ! $dimensions = getImageSize($this->src_path))
		{
			$this->is_image = FALSE;

			return;
		}

		// get the image dimensions
		$this->src_width 	= $dimensions[0];
		$this->src_height 	= $dimensions[1];

		// get cookie data
		$cookie = isset($_COOKIE['rspvly']) ? $_COOKIE['rspvly'] : FALSE;

		// if the cookie doesn't exist then we won't resize
		// but we will hedge our bets a bit by serving a medium quality image
		if ( ! $cookie)
		{
			$this->max_width 	= $this->src_width;
			$this->quality 		= self::MED_QUAL;
			
			return;
		}

		// get usable cookie data
		$display_data = $this->_parse_cookie($cookie);

		// set quality
		if ($display_data->bandwidth < self::LOW_BAND)
		{
			$this->quality = self::LOW_QUAL;
		}
		elseif ($display_data->bandwidth < self::MED_BAND)
		{
			$this->quality = self::MED_QUAL;
		}
		else
		{
			$this->quality = self::HIGH_QUAL;
		}

		// max_width is the reported max resolution multiplied by the pixel ratio
		$this->max_width = $display_data->resolution * $display_data->pixel_ratio;

		// do not enlarge the image
		if ($this->max_width > $this->src_width)
		{
			$this->max_width = $this->src_width;
		}

		// set the name of the cache file
		$extension = strtolower(pathinfo($this->src_path, PATHINFO_EXTENSION));

		if ($extension == 'png' OR $extension == 'gif')
		{
			$this->cache_file_name = substr_replace($this->src_name, "-$this->max_width", strrpos($this->src_name, '.'), 0);
		}
		else
		{
			$this->cache_file_name = substr_replace($this->src_name, "-$this->quality-$this->max_width", strrpos($this->src_name, '.'), 0);
		}
	}



	/**
	 * Generates a new images with the appropriate width, height, and quality.
	 *
	 * Adapted from Adaptive Images by Matt Wilcox
	 * Available via the Creative Commons Attribution 3.0 Unported License
	 * https://github.com/MattWilcox/Adaptive-Images
	 * Modified for Responsifyzrly by R.A. Ray
	 *
	 * @return 	string
	 *			$new_image is the data for the generated image to be saved and served
	 */
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
			$intSharpness 	= $this->_find_sharp($this->src_width, $this->max_width);
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

		return $new_image;
	}




	/**
	 * Serves an image to the browser from raw data.
	 *
	 * Adapted from Adaptive Images by Matt Wilcox
	 * Available via the Creative Commons Attribution 3.0 Unported License
	 * https://github.com/MattWilcox/Adaptive-Images
	 * Modified for Responsifyzrly by R.A. Ray
	 *
	 * @param 	string
	 * 			$image_string is the data for the image to be served 
	 */
	public function show_image($image_string) 
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




	/**
	 * Sharpen images function
	 *
	 * From Adaptive Images by Matt Wilcox
	 * Available via the Creative Commons Attribution 3.0 Unported License
	 * https://github.com/MattWilcox/Adaptive-Images
	 *
	 * @return 	string
	 *			$new_image is the data for the generated image to be saved and served
	 */
	private function _find_sharp($intOrig, $intFinal) 
	{
		$intFinal = $intFinal * (750.0 / $intOrig);
		$intA     = 52;
		$intB     = -0.27810650887573124;
		$intC     = .00047337278106508946;
		$intRes   = $intA + $intB * $intFinal + $intC * $intFinal * $intFinal;
		
		return max(round($intRes), 0);
	}



	/**
	 * Parse cookie string into a usable object.
	 *
	 * @param 	string
	 * 			$cookie is the raw cookie string
	 * @return 	object
	 */
	private function _parse_cookie($cookie)
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

		return (object) $cookie;
	}
}