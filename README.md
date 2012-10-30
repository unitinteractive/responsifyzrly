# Responsifyzrly

A small JS/PHP library for generating images for use in responsive designs. Responsiwazzit was built to provide functionality similar to [Adaptive Images] (https://github.com/MattWilcox/Adaptive-Images) without prescribing a method of implementation. It takes the form of a tiny library that will handle image generation and serving but won't do it automatically and leaves cache handling up to the developer.

The image delivered by Resposidiggity is optimized for the receiving device based on three parameters: resolution, pixel ratio, and bandwidth. The size of the image on the page is not considered, nor are styling breakpoints.

There is one Javascript file, one PHP file, and one image to include in your project.

## 50K.jpg

This image is used purely for bandwidth testing purposes. You may change the test image by adjusting the JS parameters so there is no need to use the included image specifically.

## responsifyzrly.js

The script should be included in the `<head>` of your HTML document. It sets a cookie, named "rspvly", that contains three properties about the target device:

* `resolution - Number` 	The reported width or height, whichever is greater. 
* `pixel_ratio - Number` 	The reported pixel ratio.
* `bandwidth - Number` 		The measured bandwidth - the higher the number the better.

### Options

The script allows for several options for customization and testing purposes:

* `speedTestUri - String` 				Relative path to the bandwidth testing image.
* `speedTestKB - Number` 				The size, in kilobytes, of the bandwidth testing image.
* `speedTestTimeout - Number` 			Time, in milliseconds, to wait before aborting the bandwidth test.
* `speedTestForceBandwidth - Number` 	Force a bandwidth measurement for testing.
* `cookieExpire - Number` 				Time, in minutes, until the cookie expires.
* `forceRefresh - Bool` 				Force the browser to refresh once after setting the cookie - this assures that the cookie will be set for all image requests.
* `forcePixelRatio - Number` 			Force a pixel ratio measurement for testing.
* `forceResolution - Number` 			Force a resolution measurement for testing.

Some or all of these may be set globaly at the top of the script:

```
var rspvly = {
	options : {
		'speedTestUri' : 				'images/70K.jpg',
		'speedTestKB' : 				70,
		'forceRefresh' : 				false
	}
};
```

## Responsifyzrly.php

The original impetus for Responsiyoohoo was to produce optimized images that could be cached using AWS and for that we needed control of when the image was generated and how it was stored/looked up. The PHP library takes a local image path and constructs an object with several useful properties and methods that make it easy to build a custom caching and serving system.

```
$img = new Responsifyzrly($local_path_to_image);
```

### Config

There are several constants that may be set in the class to customize your installation:

* `LOW_QUAL - Number` 		The quality, in percentage, of low-bandwidth JPGs.
* `MED_QUAL - Number` 		The quality, in percentage, of medium-bandwidth JPGs.
* `HIGH_QUAL - Number` 		The quality, in percentage, of high-bandwidth JPGs.
* `LOW_BAND - Number` 		The ceiling for a low-bandwidth score.
* `MED_BAND - Number` 		The ceiling for a medium-bandwidth score - anything higher than this will be considered high-bandwidth.
* `BROWSER_CACHE - Number` 	Time, in seconds, to keep the images in the user's browser cache.

### Properties

Several properties are set for the image upon instantiation:

* `cache_file_name - String` 	The name that the optimized image will have (eg. my-image-800-60.jpg).
* `is_image - Bool` 			Whether or not the given location pointed to an image that we could read.
* `max_width - Number` 			The largest width image that would be served constrained by original image width, device resolution, and device pixel ratio.
* `quality - Number` 			The quality that an optimized JPG will have based on the bandwidth score.
* `src_name - String` 			The source image file name.
* `src_path - String`			The local path to the source image.
* `src_width - Number` 			The width, in pixels, of the source image.

These can be accessed to help build the caching system.

```
$cache_image_loc = $cache_dir.$img->cache_file_name;
```

### Methods

#### generate_cache_image()

Generates and returns the image data, as a string, that can then be stored and served to the browser.

```
$cache_image = $img->generate_cache_image();
```

#### show_image()

Takes `$image_string` as a parameter, sets the appropriate headers, and echoes the data.

```
$img->show_image($cache_image);
```

### Full Usage Example

```
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
```

## Other Solutions

Responsihumina is heavily based on [Adaptive Images] (https://github.com/MattWilcox/Adaptive-Images) by Matt Wilcox and [Foresight.js] (https://github.com/adamdbradley/foresight.js) by Adam Bradley, both of which are fantastic solutions to the same problem that attack it from a different angle. Responsifizzle not doing it for you? Give those a look.