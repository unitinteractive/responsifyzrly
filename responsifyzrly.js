
rspvly = {
	options : {
		//'speedTestTimeout' : 700,
		//'speedTestForceBandwidth' : 49,
		//'forceResolution' : 480,
		//'forcePixelRatio' : 2,
		//'cookieExpire' : 1,
		//'forceRefresh' : FALSE
	}
};

( 
	function( rspvly, window, document )
	{
		// option defaults
		var opts 				= rspvly.options || {};
		opts.speedTestUri 		= opts.speedTestUri || 'http://designpro.co/images/50K.jpg';
		opts.speedTestKB 		= opts.speedTestKB || 50;
		opts.speedTestTimeout 	= opts.speedTestTimeout || 300;
		opts.cookieExpire 		= opts.cookieExpire || 30;
		opts.forceRefresh 		= opts.forceRefresh || TRUE;
		
		


		// basic properties to set in the cookie
		var props = {
			'resolution' 	: Math.max( screen.width, screen.height ),
			'pixel_ratio' 	: window.devicePixelRatio ? window.devicePixelRatio : '1',
		};

		if (opts.forceResolution)
		{
			props.resolution = opts.forceResolution;
		}

		if (opts.forcePixelRatio)
		{
			props.pixel_ratio = opts.forcePixelRatio;
		}

		


		// runs when the test image has loaded or definitively failed to load
		var endSpeedTest = function( bandwidth )
		{
			props.bandwidth = bandwidth;

			clearTimeout( speedTimeOut );

			setCookie();
		}

		


		var setCookie = function()
		{
			var cookie = 'rspvly=';

			for (var key in props)
			{
				cookie += key + '=' + props[key] + '&';
			}

			cookie += '; path=/;';

			var now = new Date();
			
			now.setTime( now.getTime() );

			var expireDate 	= opts.cookieExpire * 60 * 1000;
			expireDate		= new Date( now.getTime() + expireDate );

			cookie += 'expires=' + expireDate.toUTCString();

			document.cookie = cookie;

			if( opts.forceRefresh )
			{
				// refresh now that the cookie is set
				document.location.reload(true);
			}
		}

		


		// from http://techpatterns.com/downloads/javascript_cookies.php
		var getCookie = function()
		{
			// first we'll split this cookie up into name/value pairs
			// note: document.cookie only returns name=value, not the other components
			var a_all_cookies 	= document.cookie.split( ';' );
			var a_temp_cookie 	= '';
			var cookie_name 	= '';
			var cookie_value 	= '';
			var b_cookie_found 	= false; // set boolean t/f default f

			for ( i = 0; i < a_all_cookies.length; i++ )
			{
				// now we'll split apart each name=value pair
				a_temp_cookie = a_all_cookies[i].split( '=' );

				// and trim left/right whitespace while we're at it
				cookie_name = a_temp_cookie[0].replace(/^\s+|\s+$/g, '');

				// if the extracted name matches passed check_name
				if ( cookie_name == 'rspvly' )
				{
					b_cookie_found = true;
					
					// we need to handle case where cookie has no value but exists (no = sign, that is):
					if ( a_temp_cookie.length > 1 )
					{
						cookie_value = unescape( a_temp_cookie[1].replace(/^\s+|\s+$/g, '') );
					}
					
					// note that in cases where cookie is initialized but no value, null is returned
					return cookie_value;
					
					break;
				}

				a_temp_cookie 	= null;
				cookie_name 	= '';
			}

			if ( ! b_cookie_found )
			{
				return false;
			}
		}

		


		// don't do anything if there is already a cookie set
		if ( ! getCookie())
		{
			// the following algorithm is adapted from Foresight.js Copyright (c) 2012, Adam Bradley
			// Available via the MIT license
			// https://github.com/adamdbradley/foresight.js
			// modified for responsifyzrly.js by R.A. Ray
			
			// force that this device has a certain bandwidth
			if ( opts.speedTestForceBandwidth ) 
			{
				endSpeedTest( opts.speedTestForceBandwidth );

				return;
			}

			var speedTestImg = document.createElement( 'img' );
			var endTime;
			var startTime;
			var speedTestTimeoutMS;

			speedTestImg.onload = function () 
			{
				// speed test image download completed
				// figure out how long it took and an estimated connection speed
				endTime 		= ( new Date() ).getTime();
				var duration 	= ( endTime - startTime ) / 1000;
				duration 		= ( duration > 0 ? duration : 1 ); // just to ensure we don't divide by 0

				var bandwidth 	= ( ( opts.speedTestKB * 1024 * 8 ) / duration ) / 1024;

				endSpeedTest( bandwidth );
			};

			speedTestImg.onerror = function () 
			{
				// fallback incase there was an error downloading the speed test image
				endSpeedTest( 0 );
			};

			speedTestImg.onabort = function () 
			{
				// fallback in case there was an abort during the speed test image
				endSpeedTest( 0 );
			};

			// begin the network connection speed test image download
			startTime = ( new Date() ).getTime();

			if ( document.location.protocol === 'https:' ) 
			{
				// if the current document is SSL, make sure the speed test request
				// uses https so there are no ugly security warnings from the browser
				speedTestUri = speedTestUri.replace( 'http:', 'https:' );
			}

			speedTestImg.src = opts.speedTestUri + "?r=" + Math.random();

			// calculate the maximum number of milliseconds it 'should' take to download an XX Kbps file
			// set a timeout to abort if the speed test download takes too long
			// Adding 350ms to account for TCP slow start, quickAndDirty === TRUE
			speedTestTimeoutMS = ( ( ( opts.speedTestKB * 8 ) / opts.speedTestTimeout ) * 1000 ) + 350;
			
			var speedTimeOut = setTimeout( function () 
				{
					endSpeedTest( 0 );
				},
				speedTestTimeoutMS 
			);
		}
	}
)
( this.rspvly = this.rspvly || {}, this, this.document );