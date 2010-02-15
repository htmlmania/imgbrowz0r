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

// These are all settings (set to default). The settings are not validated since you have to configure everything.
// There is a chance that ImgBrowz0r stops working if you enter the wrong values.
$config = array(
	// Directory settings. These are required. Without trailing slash. (required)
	'images_dir'               => '/var/www/imgbrowz0r/images',
	'cache_dir'                => '/var/www/imgbrowz0r/cache',

	// Url settings. These are required. Without trailing slash. (required)
	// %PATH% is replaced with the directory location and page number
	'main_url'                 => 'http://example.com/imgbrowz0r/example.php?q=%PATH%',
	'images_url'               => 'http://example.com/imgbrowz0r/images',
	'cache_url'                => 'http://example.com/imgbrowz0r/cache',

	// Sorting settings (optional)
	'dir_sort_by'              => 3, // 1 = filename, 2 = extension (dir), 3 = inode change time of file
	'img_sort_by'              => 3, // 1 = filename, 2 = extension (png, gif, etc.), 3 = inode change time of file

	// The sort order settings can have the following values:
	// SORT_ASC, SORT_DESC, SORT_REGULAR, SORT_NUMERIC, SORT_STRING
	// SORT_ASC = ascending, SORT_DESC = descending
	'dir_sort_order'           => SORT_DESC,
	'img_sort_order'           => SORT_DESC,

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
	'ignore_port'              => false, // Ignore port in url. Set this to true to ignore the port.
	'dir_thumbs'               => false, // Show a thumbnail in a category box. Default is false.
	'random_thumbs'            => false, // Use random thumbnails for categories. Default is false.
	'read_thumb_limit'         => 0 // See README for information about this setting.
	);

// Start the class
$gallery = new imgbrowz0r($config);

// XHTML stuff
?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

	<title>ImgBrowz0r <?php echo IMGBROWZ0R_VERSION ?></title>

	<style type="text/css">
		body { padding: 0;margin:0;text-align: center;font-size:90%;line-height: 1.5;background-color: #fff;font-family: Tahoma, Verdana, sans-serif }

		a:link, a:visited { color: #F52542;text-decoration: underline }
		a:hover, a:active { color: #333 }
		strong { font-weight: bold }

		/* This is some example CSS. You can change this to your own liking. */
		.img-description, .imgbrowz0r-navigation, #imgbrowz0r { margin: 0 auto;width: 970px;text-align: left }
		.imgbrowz0r-navigation { padding: 1.5em 0 0.5em }
		.imgbrowz0r-navigation .img-statistics { margin-top: 1.5em }

		.img-description { margin: 1.5em auto 0 }

		#imgbrowz0r .img-row { padding: 0 1em }

		#imgbrowz0r .img-directory span.img-dir-name,
		#imgbrowz0r .img-directory span.img-thumb-date { display: block }
		#imgbrowz0r .img-directory span.img-dir-name { font-weight: bold;font-size: 1.2em }

		#imgbrowz0r .img-column-1 { clear: left }

		#imgbrowz0r .img-thumbnail,
		#imgbrowz0r .img-directory { float: left;padding: 1.5em 0;width: 25%;text-align: center }

		#imgbrowz0r .img-thumbnail a,
		#imgbrowz0r .img-directory a {
			display: block;margin: 0 auto;
			width: 200px;height: 160px;line-height: 150px;
			background-repeat: no-repeat;background-position: 50% 50%; }

		#imgbrowz0r .img-directory a:link, #imgbrowz0r .img-directory a:visited, #imgbrowz0r .img-thumbnail a:link,
		#imgbrowz0r .img-thumbnail a:visited { background-color: #000;border: 1px solid #000;font-size: 1.5em;color: #ccc;text-decoration: none }
		#imgbrowz0r .img-directory a:active, #imgbrowz0r .img-directory a:hover, #imgbrowz0r .img-thumbnail a:active,
		#imgbrowz0r .img-thumbnail a:hover { border-color: #F52542;color: #F52542 }

		/* http://sonspring.com/journal/clearing-floats */
		html body div.clear,
		html body span.clear { background: none;border: 0;clear: both;display: block;float: none;font-size: 0;list-style: none;
				       margin: 0;padding: 0;overflow: hidden;visibility: hidden;width: 0;height: 0 }
	</style>
</head>
<body>

<?php

// Prepare everything. This function must be called before
// you call other functions. (required)
$gallery->init();

// Generate navigation and statistics. (optional, but remommended)
// The output of the functions are now assigned to variabled, but
// you can also call the functions directly.
$gallery_breadcrumbs = $gallery->breadcrumbs();
$gallery_pagination = $gallery->pagination();
$gallery_statistics = $gallery->statistics();

// Display description of the current directory. (optional)
echo $gallery->description();

// Display navigation
echo '<div class="imgbrowz0r-navigation">', "\n\t", $gallery_breadcrumbs, "\n\t", $gallery_pagination, "\n\t", $gallery_statistics, "\n", '</div>', "\n\n";

// Display images and directories. (required)
echo $gallery->browse();

// Display navigation
echo '<div class="imgbrowz0r-navigation">', "\n\t", $gallery_pagination, "\n\t", $gallery_breadcrumbs, "\n", '</div>', "\n\n";

// Showing some stats (optional)
echo '<p>Processing time: ', round(get_microtime(microtime()) - get_microtime($start_timer), 5),
	' &amp;&amp; Memory usage: ', file_size(memory_get_usage()),
	' &amp;&amp; Memory peak: ', file_size(memory_get_peak_usage()), '</p>';

?>

</body>
</html>