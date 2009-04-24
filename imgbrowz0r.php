<?php

/* ---

	ImgBrowz0r, a simple PHP5 Gallery class
	Version 0.3-dev, May ??th, 2009
	http://61924.nl/projects/imgbrowz0r.html

	Copyright (c) 2008-2009 Frank Smit
	License: http://www.gzip.org/zlib/zlib_license.html

	This software is provided 'as-is', without any express or implied
	warranty. In no event will the authors be held liable for any damages
	arising from the use of this software.

	Permission is granted to anyone to use this software for any purpose,
	including commercial applications, and to alter it and redistribute it
	freely, subject to the following restrictions:

	1. The origin of this software must not be misrepresented; you must not
	   claim that you wrote the original software. If you use this software
	   in a product, an acknowledgment in the product documentation would be
	   appreciated but is not required.

	2. Altered source versions must be plainly marked as such, and must not be
	   misrepresented as being the original software.

	3. This notice may not be removed or altered from any source
	   distribution.

--- */

/* ---

	THIS IS A DEVELOPMENT VERSION!

	That means that it's not complete yet and there may be some bugs.
	But that does not mean you should not use it. Instead you should
	use and test it and report bugs and request features (but remind
	that this class must stay simple) so I can improve it. :D

--- */

define('IMGBROWZ0R_VERSION', '0.3-dev');

class imgbrowz0r
{
	private $config, $cur_directory, $cur_page;

	// Check if GD is loaded and set configuration
	public function __construct($config)
	{
		// First check if GD is enabled
		if (!extension_loaded('gd') && !function_exists('gd_info'))
			exit('<p><a href="http://www.php.net/manual/en/book.image.php">GD</a> is not enabled!</p>');

		// Set configuration
		$this->config = array(
			// Directory settings
			'images_dir'               => isset($config['images_dir']) ? $config['images_dir'] : exit('You have to set the full path to the directory with images!'),
			'cache_dir'                => isset($config['cache_dir']) ? $config['cache_dir'] : exit('You have to set the full path to the cache directory!'),

			// Url settings
			'main_url'                 => isset($config['main_url']) ? $config['main_url'] : exit('You have to set a main url!'),
			'images_url'               => isset($config['images_url']) ? $config['images_url'] : exit('You have to set the url to the directory with images!'),
			'cache_url'                => isset($config['cache_url']) ? $config['cache_url'] : exit('You have to set the url to the cache!'),

			// Sorting settings
			'sort_by'                  => isset($config['sort_by']) && in_array($config['sort_by'], array(1, 2, 3)) ? $config['sort_by'] : 3,
			'sort_order'               => isset($config['sort_order']) && $config['sort_order'] === true ? SORT_ASC : SORT_DESC,

			// Thumbnail settings
			'thumbs_per_page'          => isset($config['thumbs_per_page']) ? $config['thumbs_per_page'] : 12,
			'max_thumb_row'            => isset($config['max_thumb_row']) ? $config['max_thumb_row'] : 4,
			'max_thumb_width'          => isset($config['max_thumb_width']) ? $config['max_thumb_width'] : 200,
			'max_thumb_height'         => isset($config['max_thumb_height']) ? $config['max_thumb_height'] : 200,

			// Time settings
			'time_format'              => isset($config['time_format']) ? $config['time_format'] : 'F jS, Y',
			'time_zone'                => isset($config['time_zone']) ? $config['time_zone'] : 0,
			'enable_dst'               => isset($config['enable_dst']) && $config['enable_dst'] === true ? 1 : 0,

			// Misc settings
			'ignore_port'              => isset($config['ignore_port']) && $config['ignore_port'] === true  ? true : false
		);

		// Get current url
		$protocol = (!isset($_SERVER['HTTPS']) || strtolower($_SERVER['HTTPS']) == 'off') ? 'http://' : 'https://';
		$port = $this->config['ignore_port'] === false && (isset($_SERVER['SERVER_PORT']) && (($_SERVER['SERVER_PORT'] != '80' && $protocol == 'http://') || ($_SERVER['SERVER_PORT'] != '443' && $protocol == 'https://')) && strpos($_SERVER['HTTP_HOST'], ':') === false) ? ':'.$_SERVER['SERVER_PORT'] : '';
		$current_url = urldecode($protocol.$_SERVER['HTTP_HOST'].$port.$_SERVER['REQUEST_URI']);

		// Regex
		preg_match('/^'.str_replace(
		           array('{', '}', '[', ']', '?', '(', ')', '-', '.', '/', '=', '%PATH%'),
		           array('\{', '\}', '\[', '\]', '\?', '\(', '\)', '\-', '\.', '\/', '\=', '(.*?)'),
		           $this->config['main_url']).'$/i', $current_url, $matches);

		// Set current path/directory and page
		$raw_path = isset($matches[1]) ? trim($matches[1], ' /\n\t') : false;

		if ($raw_path !== false)
		{
			$this->cur_directory = preg_replace('/[^A-Za-z0-9,_ -()\[\]\{\}\/]/', '', substr($raw_path, 0, strrpos($raw_path, '/'))).'/';
			$this->cur_page = (int) substr($raw_path, strrpos($raw_path, '/')+1);
		}
		else
		{
			$this->cur_directory = false; // This should stay a string
			$this->cur_page = 1;
		}

		if ($this->cur_directory == '0/' || $this->cur_directory == '/')
			$this->cur_directory = false;
	}

	// Reads the gallery directories and files
	public function browse()
	{
		@set_time_limit(180); // 3 Minutes

		$output = null;
		$dirs = array();
		$imgs = array();

		$full_path = $this->cur_directory === false ? $this->config['images_dir'].'/' : $this->config['images_dir'].'/'.$this->cur_directory;

		// Start capturing output
		ob_start();

		echo '<div id="imgbrowz0r">';

		if (is_dir($full_path) && ($handle = opendir($full_path)))
		{
			// Scan directories and files
			while (($file = readdir($handle)) !== false)
			{
				if (is_dir($full_path.'/'.$file))
				{
					// Exclude . and ..
					if ($file == '.' || $file == '..')
						continue;

					$dirs[] = array(0, $file, 'dir', filectime($full_path.'/'.$file));
				}
				else
				{
					// Check if file is an supported image type
					$image_extension = $this->get_ext($file);
					if (!in_array($image_extension, array('gif', 'jpg', 'jpeg', 'jpe', 'jif', 'jfif', 'jfi', 'png')))
						continue;

					$imgs[] = array(1, $file, $image_extension, filectime($full_path.'/'.$file));
				}
			}

			closedir($handle);

			// Sort arrays
			if (($count_dirs = count($dirs)) > 0)
			{
				foreach($dirs as $res) $sortAux[] = $res[$this->config['sort_by']];
				array_multisort($sortAux, $this->config['sort_order'], $dirs);
			}

			if (($count_imgs = count($imgs)) > 0)
			{
				foreach($imgs as $res2) $sortAux2[] = $res2[$this->config['sort_by']];
				array_multisort($sortAux2, $this->config['sort_order'], $imgs);
			}

			// Calculate pages
			$page_count = (int) ceil(($count_dirs + $count_imgs) / $this->config['thumbs_per_page']);
			$this->cur_page = $this->cur_page > 0 && $this->cur_page <= $page_count ? $this->cur_page : 1;

			// Merge and slice arrays
			$items = array_merge($dirs, $imgs);
			$items = array_slice($items, ($this->cur_page -1) * $this->config['thumbs_per_page'], $this->config['thumbs_per_page']);
			$item_count = count($items);

			// Create navigation
			$breadcrumbs = '<p class="img-breadcrumbs">Breadcrumbs: '.$this->breadcrumbs().'</p>';
			$pagination = $page_count > 1 ? "\n\t\t".'<p class="img-pagination">Pages: '.$this->pagination($page_count).'</p>' : null;

			// Echo, echo~
			echo "\n\t", '<p class="img-statistics">There '.($count_dirs !== 1 ? 'are '.$count_dirs.' directories' : 'is 1 directory').' and '.
			     ($count_imgs !== 1 ? $count_imgs.' images' : '1 image').' in this directory.</p>', "\n",
			     "\n\t", '<div class="img-navigation">', "\n\t\t", $breadcrumbs, $pagination, "\n\t", '</div>', "\n";

			$row_count = 1;
			if ($item_count > 0)
			{
				echo "\n\t", '<div class="img-row">', "\n";

				foreach ($items as $k => $item)
				{
					if ($item[0] === 1)
					{
						$image_cache_dir = md5($this->cur_directory);
						$image_thumbnail = $image_cache_dir.'/'.$item[1]; // The name of the thumbnail

						if (!is_dir($this->config['cache_dir'].'/'.$image_cache_dir))
							mkdir($this->config['cache_dir'].'/'.$image_cache_dir, 0777);

						if (!file_exists($this->config['cache_dir'].'/'.$image_thumbnail))
							$this->make_thumb($this->cur_directory, $item[1], $image_thumbnail);

						echo "\t\t", '<div class="img-thumbnail img-column-', $row_count, '"><a href="', $this->config['images_url'], '/', $this->cur_directory,
						     $item[1], '" title="', $item[1], '"><img src="', $this->config['cache_url'], '/', $image_thumbnail,
						     '" alt="', $image_thumbnail, '" /></a><span>', $this->format_time($item[3]), '</span></div>', "\n";
					}
					else
						echo "\t\t", '<div class="img-directory img-column-', $row_count, '"><a href="',
						     str_replace('%PATH%',  $this->cur_directory.$item[1], $this->config['main_url']), '/1" title="', $item[1], '">',
						     $item[1], '</a><span>', $this->format_time($item[3]), '</span></div>', "\n";

					if ($row_count === $this->config['max_thumb_row'] && $k < ($item_count-1))
					{
						echo "\t", '</div>', "\n\t", '<div class="img-row">', "\n";
						$row_count = 0;
					}

					++$row_count;
				}

				echo "\t", '</div>', "\n";
			}
			else
				echo "\n\t", '<p class="img-empty-directory">There are no images or directories in this directory.</p>', "\n";

			echo "\n\t", '<div class="clear">&nbsp;</div>', "\n", "\n\t", '<div class="img-navigation">',
			     $pagination, "\n\t\t", $breadcrumbs, "\n\t", '</div>', "\n";
		}
		else
			 echo "\n\t", '<p class="img-directory-not-found">This directory does not exist!</p>', "\n";

		echo '</div>', "\n";

		// Stop capturing output
		$output = ob_get_contents();
		ob_end_clean();

		return $output;
	}

	// Create css rules
	public function output_style()
	{
		return '
#imgbrowz0r .img-thumbnail a,
#imgbrowz0r .img-directory a { display: block;margin: 0 auto;width: '.$this->config['max_thumb_width'].'px }

#imgbrowz0r .img-thumbnail,
#imgbrowz0r .img-directory { float: left;padding: 1.5em 0;width: '.(round(100 / $this->config['max_thumb_row'], 2)).'%;text-align: center }
#imgbrowz0r .img-column-1 { clear: left }

#imgbrowz0r .img-directory a:link,
#imgbrowz0r .img-directory a:visited { height: '.($this->config['max_thumb_width'] * 0.8).'px;line-height: '.($this->config['max_thumb_width'] * 0.8 - 10).'px }

/* http://sonspring.com/journal/clearing-floats */
html body div.clear,
html body span.clear { background: none;border: 0;clear: both;display: block;float: none;font-size: 0;list-style: none;
                       margin: 0;padding: 0;overflow: hidden;visibility: hidden;width: 0;height: 0 }
';
	}

	// Generate breadcrumbs
	private function breadcrumbs()
	{
		$path_parts = $this->cur_directory !== false ? explode('/', trim($this->cur_directory, '/')) : array();
		$count_path = count($path_parts);

		if ($count_path > 0)
			for ($x=0; $x < $count_path; ++$x)
				$output[] = '<a href="index.php?q='.implode('/', array_slice($path_parts, 0, ($x+1))).'/1">'.$path_parts[$x].'</a>';

		return '<a href="'.str_replace('%PATH%',  '0/1', $this->config['main_url']).'">root</a>'.(isset($output) ? ' / '.implode(' / ', $output) : null);
	}

	// Generate page navigation
	private function pagination($page_count)
	{
		$cur_dir = $this->cur_directory !== false ? trim($this->cur_directory, '/') : 0;

		// Previous and next links
		$prev = $this->cur_page > 1 ? '<a href="'.str_replace('%PATH%',  $cur_dir.'/'.($this->cur_page - 1), $this->config['main_url']).'">&laquo;</a>' : null;
		$next = $this->cur_page < $page_count ? '<a href="'.str_replace('%PATH%',  $cur_dir.'/'.($this->cur_page + 1), $this->config['main_url']).'">&raquo;</a>' : null;

		// First and last page
		$first = $this->cur_page === 1 ? '<span class="img-current-page"><strong>1</strong></span>' : '<a href="'.str_replace('%PATH%',  $cur_dir.'/1', $this->config['main_url']).'">1</a>';
		$last = $this->cur_page === $page_count ? '<strong>'.$page_count.'</strong>' : '<a href="'.str_replace('%PATH%',  $cur_dir.'/'.$page_count, $this->config['main_url']).'">'.$page_count.'</a>';

		// Other pages
		if ($page_count > 5)
		{
			$start = $this->cur_page - 2 > 2 ? $this->cur_page - 2 : 2;
			$end = $this->cur_page + 2 < $page_count - 1 ? $this->cur_page + 2 : $page_count-1;
			if ($this->cur_page === 1) $end += 2;

			for ($x=$start; $x <= $end; ++$x)
				if ($this->cur_page !== $x)
					$pages[] = '<a href="'.str_replace('%PATH%',  $cur_dir.'/'.$x, $this->config['main_url']).'">'.$x.'</a>';
				else
					$pages[] = '<span class="img-current-page"><strong>'.$x.'</strong></span>';
		}
		else
		{
			$end = $page_count - 1;
			for ($x=2; $x <= $end; ++$x)
				if ($this->cur_page !== $x)
					$pages[] = '<a href="'.str_replace('%PATH%',  $cur_dir.'/'.$x, $this->config['main_url']).'">'.$x.'</a>';
				else
					$pages[] = '<span class="img-current-page"><strong>'.$x.'</strong></span>';
		}

		return $prev.' '.$first.($this->cur_page > 4 ? ' ... ' : ' ').implode(' ', $pages).($this->cur_page < $page_count - 3 ? ' ... ' : ' ').$last.' '.$next;
	}

	// The legendary thumbnail generater
	private function make_thumb($image_dir, $image_name, $image_thumbnail)
	{
		//Check if thumb dir exists
		if (is_writable($this->config['cache_dir']) && !is_dir($this->config['cache_dir']))
			exit('Cache directory does not exist or is not writable!');

		$image_dir = $image_dir !== false ? $image_dir.'/' : null;

		// Get image information
		$image_info = $this->get_image_info($this->config['images_dir'].'/'.$image_dir.'/'.$image_name);

		// Check if file is an supported image type
		if (!in_array($image_info['extension'], array('gif', 'jpg', 'jpeg', 'jpe', 'jif', 'jfif', 'jfi', 'png')))
			return false;

		// Open the image so we can make a thumbnail
		if ($image_info['type'] == 3)
			$image = imagecreatefrompng($this->config['images_dir'].'/'.$image_dir.$image_name);
		else if ($image_info['type'] == 2)
			$image = imagecreatefromjpeg($this->config['images_dir'].'/'.$image_dir.'/'.$image_name);
		else if ($image_info['type'] == 1)
			$image = imagecreatefromgif($this->config['images_dir'].'/'.$image_dir.'/'.$image_name);
		else
			return false;

		// Calculate new width and height
		$zoomw = $image_info['width'] / 200;
		$zoomh = $image_info['height'] / 200;

		$zoom = ($zoomw > $zoomh) ? $zoomw : $zoomh;

		if ($image_info['width'] < 200 && $image_info['height'] < 200)
		{
			$thumb_width = $image_info['width'];
			$thumb_height = $image_info['height'];
		}
		else
		{
			$thumb_width = $image_info['width'] / $zoom;
			$thumb_height = $image_info['height'] / $zoom;
		}

		// Create an image for the thumbnail
		$thumbnail = imagecreatetruecolor($thumb_width, $thumb_height);

		// Preserve transparency in PNG and GIF images
		if ($image_info['type'] === 3)
		{
			$alpha_color = imagecolorallocatealpha($thumbnail, 0, 0, 0, 127);
			imagefill($thumbnail, 0, 0, $alpha_color);
			imagesavealpha($thumbnail, true);
		}
		else if ($image_info['type'] === 1 && ($transparent_index = imagecolortransparent($image)) >= 0)
		{
			$transparent_color = imagecolorsforindex($image, $transparent_index);
			$transparent_index = imagecolorallocate($thumbnail, $transparent_color['red'], $transparent_color['green'], $transparent_color['blue']);
			imagefill($thumbnail, 0, 0, $transparent_index);
			imagecolortransparent($thumbnail, $transparent_index);
		}

		// Copy and resize image
		imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, $thumb_width, $thumb_height, $image_info['width'], $image_info['height']);

		// Save the thumbnail, but first check what kind of file it is
		if ($image_info['type'] == 3)
			imagepng($thumbnail, $this->config['cache_dir'].'/'.$image_thumbnail);
		else if ($image_info['type'] == 2)
			imagejpeg($thumbnail, $this->config['cache_dir'].'/'.$image_thumbnail, 85);
		else if ($image_info['type'] == 1)
		{
			imagetruecolortopalette($thumbnail, true, 256);
			imagegif($thumbnail, $this->config['cache_dir'].'/'.$image_thumbnail);
		}

		// Destroy
		imagedestroy($thumbnail);
	}

	// Format unix timestamp to a human readable date
	private function format_time($timestamp)
	{
		return gmdate($this->config['time_format'], ($timestamp + $this->config['time_zone'] * 3600));
	}

	// Get info from image (width, height, type, extension)
	private function get_image_info($filepath)
	{
		$getimagesize = getimagesize($filepath);

		return array('width' => $getimagesize[0], 'height' => $getimagesize[1], 'type' => $getimagesize[2], 'extension' => $this->get_ext($filepath));
	}

	// Get extension from filename (returns the extension without the dot)
	private function get_ext($file_name)
	{
		return strtolower(substr($file_name, strrpos($file_name, '.')+1));
	}
}

?>
