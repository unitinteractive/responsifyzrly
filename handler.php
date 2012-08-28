<?
	include('Responsifyzrly.php');

	$img 				= new Responsifyzrly($_SERVER['DOCUMENT_ROOT'].$_GET['img']);
	$cache_dir 			= $_SERVER['DOCUMENT_ROOT'].'/images/cache/';
	$cache_file 		= $img->get_cache_file_name();
	$cache_image_path 	= $cache_dir.$cache_file;

	if ( ! is_file($cache_dir.$cache_file))
	{
		$cache_image = $img->generate_cache_image();

		// does the directory exist already?
		if ( ! is_dir($cache_dir)) 
		{ 
			if ( ! mkdir($cache_dir, 0755, true)) 
			{
				// check again if it really doesn't exist to protect against race conditions
				if ( ! is_dir($cache_dir)) 
				{
					// uh-oh, failed to make that directory
				}
			}
		}

		file_put_contents($cache_dir.$cache_file, $cache_image);

		$img->show_image($cache_image);
	}

	$handle 		= fopen($cache_image_path, 'r');
	$cache_image 	= fread($handle, filesize($cache_image_path));
	
	$img->show_image($cache_image);
?>