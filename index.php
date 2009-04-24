<?php

error_reporting(E_ALL);

// Some stuff for generating stats
function file_size($size)
{
	$units = array('b', 'Kb', 'Mb', 'Gb', 'Tb', 'Pb', 'Eb');

	for ($i = 0; $size > 1024; $i++)
		$size /= 1024;

	return round($size, 2).' '.$units[$i];
}

function get_microtime($microtime=false)
{
	if ($microtime === false)
		$microtime = microtime();

	list($usec, $sec) = explode(' ', $microtime);
	return ((float)$usec + (float)$sec);
}

$start_timer = microtime();

// Include class
require 'imgbrowz0r.php';

// These are all setting with every setting set to the default setting.
$config = array(
	// Directory settings. Without trailing slash. (required)
	'images_dir'               => '/var/www/imgbrowz0r/images',
	'cache_dir'                => '/var/www/imgbrowz0r/cache',

	// Url settings. Without trailing slash. (required)
	// %PATH% is replaced with the directory location and page number
	'main_url'                 => 'http://isamu/imgbrowz0r/index.php?q=%PATH%',
	'images_url'               => 'http://isamu/imgbrowz0r/images',
	'cache_url'                => 'http://isamu/imgbrowz0r/cache',

	// Sorting settings (optional)
	'sort_by'                  => 3, // 1 = filename, 2 = extension (png, gif, etc.), 3 = inode change time of file
	'sort_order'               => false, // true = ascending, false = descending

	// Thumbnail settings (optional)
	'thumbs_per_page'          => 12, // Amount of thumbnails per page
	'max_thumb_row'            => 4, // Amount of thumbnails on a row
	'max_thumb_width'          => 200, // Maximum width of thumbnail
	'max_thumb_height'         => 200, // Maximum height of thumbnail

	// Time settings (optional)
	'time_format'              => 'F jS, Y', // Date formatting. Look at the PHP date() for help: http://nl3.php.net/manual/en/function.date.php
	'time_zone'                => 0, // Timezone. Example: 1
	'enable_dst'               => false, // Daylight saving time (DST). Set this to true to enable it.

	// Misc settings (optional)
	'ignore_port'              => false
	);

// Start the class
$gallery = new imgbrowz0r($config);

// XHTML stuff
?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

	<title>ImgBrowz0r 0.3-dev</title>

	<style type="text/css">
		body { padding: 0;margin:0;text-align: center;font-size:90%;line-height: 1.5;background-color: #fff;font-family: Tahoma, Verdana, sans-serif }

		a:link, a:visited { color: #F52542;text-decoration: underline }
		a:hover, a:active { color: #333 }
		strong { font-weight: bold }

		#imgbrowz0r { margin: 0 auto;width: 970px;text-align: left }
		#imgbrowz0r .img-row { padding: 0 1em }
		#imgbrowz0r .img-directory a:link,
		#imgbrowz0r .img-directory a:visited { font-family: Georgia, "Times New Roman", Times, serif;text-decoration: none }

		#imgbrowz0r .img-thumbnail a:link img,
		#imgbrowz0r .img-thumbnail a:visited img { border: 1px dashed #aaa }
		#imgbrowz0r .img-thumbnail a:hover img,
		#imgbrowz0r .img-thumbnail a:active img { border-color: #F52542 }

		#imgbrowz0r .img-directory a:link,
		#imgbrowz0r .img-directory a:visited { background: #111;border: 1px dashed #aaa;font-size: 2.5em;color: #ccc }
		#imgbrowz0r .img-directory a:hover,
		#imgbrowz0r .img-directory a:active { border-color: #F52542;color: #F52542 }

		<?php /* Output ImgBrowz0r CSS */ echo $gallery->output_style(); ?>
	</style>
</head>
<body>

<?php /* Display images and directories */ echo $gallery->browse(); ?>

<?php

// Showing some stats
echo '<p>Processing time: ', round(get_microtime(microtime()) - get_microtime($start_timer), 5), ' &amp;&amp; Memory usage: ', file_size(memory_get_usage()), '</p>';

?>


</body>
</html>
