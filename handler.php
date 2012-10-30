<?php
	include('Responsifyzrly.php');

	$base_dir			= realpath('./images');
	$img 				= new Responsifyzrly($base_dir.'/'.$_GET['img']);
	$cache_dir 			= $base_dir.'/cache/';
	$cache_image_loc 	= $cache_dir.$img->cache_file_name;

	// does the cache image already exist?
	if ( ! is_file($cache_image_loc))
	{
		// create the cache image
		$cache_image = $img->generate_cache_image();

		// does the directory exist?
		if (is_dir($cache_dir) || mkdir($cache_dir, 0755, true)) 
		{ 
			// save the cache image
			file_put_contents($cache_image_loc, $cache_image);
		}

		// check again if it really doesn't exist to protect against race conditions
		elseif (is_dir($cache_dir)) 
		{
			// save the cache image
			file_put_contents($cache_image_loc, $cache_image);
		}

		// serve the new image up to the browser
		$img->show_image($cache_image);
	}

	$handle 		= fopen($cache_image_loc, 'r');
	$cache_image 	= fread($handle, filesize($cache_image_loc));
	
	// serve the cached image up to the browser
	$img->show_image($cache_image);