<?php

/* ---

	ImgBrowz0r, a simple PHP5 Gallery class
	Version 0.3.7, May ??th, 2010
	http://61924.nl/projects/imgbrowz0r.html

	Copyright (c) 2008-2010 Frank Smit
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

define('IMGBROWZ0R_VERSION', '0.3.7');

class ImgBrowz0r
{
	protected $config, $cache, $output, $cur_directory, $cur_page, $files, $page_count,
	          $count_files = 0, $count_dirs = 0, $count_imgs = 0, $full_path;

	// All image extensions/types that browsers support
    protected $image_types = array('.gif' => '', '.jpg' => '', '.jpeg' => '', '.jpe' => '',
                                   '.jif' => '', '.jfif' => '', '.jfi' => '', '.png' => '');

	public $status = 200;

	// Check if GD is loaded and set configuration
	public function __construct($config, $cache = null)
	{
		// Check if all the required values are set
		if (!isset($config['images_dir']) || !isset($config['cache_dir']) || !isset($config['main_url']) ||
		    !isset($config['images_url']) || !isset($config['cache_url']))
			exit('"images_dir", "cache_dir", "main_url", "images_url" or "cache_url" is not set! Please check your configuration.');

		// Set configuration
		$this->config = array();

		// Directory settings
		$this->config['images_dir']          = $config['images_dir'];
		$this->config['cache_dir']           = $config['cache_dir'];

		// Url settings
		$this->config['main_url']            = $config['main_url'];
		$this->config['images_url']          = $config['images_url'];
		$this->config['cache_url']           = $config['cache_url'];

		// Sorting settings
		$this->config['dir_sort_by']         = isset($config['dir_sort_by']) && in_array($config['dir_sort_by'], array(1, 2, 3)) ?
		                                       $config['dir_sort_by'] : 3;
		$this->config['dir_sort_order']      = isset($config['dir_sort_order']) ? $config['dir_sort_order'] : SORT_DESC;

		$this->config['img_sort_by']         = isset($config['img_sort_by']) && in_array($config['img_sort_by'], array(1, 2, 3, 4)) ?
		                                       $config['img_sort_by'] : 3;
		$this->config['img_sort_order']      = isset($config['img_sort_order']) ? $config['img_sort_order'] : SORT_DESC;

		// Thumbnail settings
		$this->config['thumbs_per_page']     = isset($config['thumbs_per_page']) ? $config['thumbs_per_page'] : 12;
		$this->config['max_thumb_row']       = isset($config['max_thumb_row']) ? $config['max_thumb_row'] : 4;
		$this->config['max_thumb_width']     = isset($config['max_thumb_width']) ? $config['max_thumb_width'] : 200;
		$this->config['max_thumb_height']    = isset($config['max_thumb_height']) ? $config['max_thumb_height'] : 200;

		// Time and timezone settings
		$this->config['time_format']         = isset($config['time_format']) ? $config['time_format'] : 'F jS, Y';
		$this->config['time_zone']           = isset($config['time_zone']) ? $config['time_zone']: 'Europe/Amsterdam';

		if ($this->config['time_zone'])
			date_default_timezone_set($this->config['time_zone']);

		// Misc settings
		$this->config['ignore_port']         = isset($config['ignore_port']) && $config['ignore_port']  ? true : false;
		$this->config['dir_thumbs']          = isset($config['dir_thumbs']) && $config['dir_thumbs']  ? true : false;
		$this->config['random_thumbs']       = isset($config['random_thumbs']) && $config['random_thumbs']  ? true : false;
		$this->config['read_thumb_limit']    = isset($config['read_thumb_limit']) ? $config['read_thumb_limit'] : 0;

		if (!$this->config['random_thumbs'])
			$this->config['read_thumb_limit'] = 1;

		$this->cache = $cache;
	}

	public function init()
	{
		// Get current url
		$protocol = !isset($_SERVER['HTTPS']) || strtolower($_SERVER['HTTPS']) === 'off' ? 'http://' : 'https://';
		$port = !$this->config['ignore_port'] && (isset($_SERVER['SERVER_PORT']) && (($_SERVER['SERVER_PORT'] != '80'
		        && $protocol === 'http://') || ($_SERVER['SERVER_PORT'] != '443' && $protocol === 'https://'))
		        && !strpos($_SERVER['HTTP_HOST'], ':')) ? ':'.$_SERVER['SERVER_PORT'] : '';
		$current_url = urldecode($protocol.$_SERVER['HTTP_HOST'].$port.$_SERVER['REQUEST_URI']);

		// Regex - extract the path and page number from the URL
		preg_match('/^'.str_replace('%PATH%', '([^%]+)', preg_quote($this->config['main_url'], '/')).'$/i', $current_url, $matches);
		//preg_match('/^'.str_replace('%PATH%', '([A-Za-z0-9 \/\-_\.]+)', preg_quote($this->config['main_url'], '/')).'$/i',
		//	$current_url, $matches);

		// Set current path/directory and page number
		$raw_path = isset($matches[1]) ? trim($matches[1], " \\/\n\t") : false;

		if ($raw_path)
		{
			$this->cur_directory = str_replace(array('<', '>', '"', '\'', '&',' ;', '%', '..'), '',
				substr($raw_path, 0, strrpos($raw_path, '/')).'/');
			$this->cur_page = (int) substr($raw_path, strrpos($raw_path, '/') + 1);
		}
		else
		{
			$this->cur_directory = false;
			$this->cur_page = 1;
		}

		if ($this->cur_directory === '0/' || $this->cur_directory === '/')
			$this->cur_directory = false;

		$dirs = $imgs = array();
		$this->full_path = !$this->cur_directory ? $this->config['images_dir'].'/' : $this->config['images_dir'].'/'.$this->cur_directory;

		if (is_dir($this->full_path))
		{
			if ($this->cache && $data = $this->cache->read('dc-'.crc32($this->full_path).'-'.$this->cur_page))
			{
				list($this->count_dirs, $this->count_imgs, $this->count_files,
					$this->page_count, $this->output) = $data;

				$this->cur_page = $this->cur_page > 0 && $this->cur_page <= $this->page_count ? $this->cur_page : 1;

				return;
			}

			// Read current directory and put all the directories and images in an array
            $dir = new DirectoryIterator($this->full_path);

            foreach ($dir as $fi)
            {
				$filename = $fi->getFilename();
				$type = $fi->getType();

				if ($type === 'dir' && $filename[0] != '.')
				{
					$dirs[] = array(0, $filename, 'dir', $fi->getCTime());
				}
				elseif ($type === 'file' && isset($this->image_types[$image_extension = $this->get_ext($filename)]))
				{
					if ($image_extension != 'png' && $image_extension != 'gif')
					{
						// Use DateTimeOriginal when it exists instead of filectime
						$exif_data = @exif_read_data($fi->getPathname());
						$timestamp = $exif_data && isset($exif_data['DateTimeOriginal']) ? strtotime($exif_data['DateTimeOriginal']) : $fi->getCTime();
					}
					else
						$timestamp = $fi->getCTime();

					$imgs[] = array(1, $filename, $image_extension, $timestamp);
				}
            }

			unset($dir);

			// Sort arrays
			if (($this->count_dirs = count($dirs)) > 0)
			{
				foreach($dirs as $res) $sort_dirs[] = $res[$this->config['dir_sort_by']];
				array_multisort($sort_dirs, $this->config['dir_sort_order'], $dirs);
			}

			if (($this->count_imgs = count($imgs)) > 0)
			{
				foreach($imgs as $res) $sort_files[] = $res[$this->config['img_sort_by']];
				array_multisort($sort_files, $this->config['img_sort_order'], $imgs);
			}

			// Calculate pages
			$this->page_count = (int) ceil(($this->count_dirs + $this->count_imgs) / $this->config['thumbs_per_page']);
			$this->cur_page = $this->cur_page > 0 && $this->cur_page <= $this->page_count ? $this->cur_page : 1;

			// Merge and slice arrays
			$this->files = array_slice(
				array_merge($dirs, $imgs),
				($this->cur_page-1) * $this->config['thumbs_per_page'],
				$this->config['thumbs_per_page']);

			$this->count_files = count($this->files);
		}
		else
			$this->status = 404;
	}

	// Reads the gallery directories and files
	public function browse()
	{
		// Check status code and file count
		if ($this->status === 404)
			return '<div id="imgbrowz0r">'."\n\t".'<p class="img-directory-not-found">This directory does not exist!</p>'."\n".'</div>'."\n";
		else if ($this->count_files === 0)
			return '<div id="imgbrowz0r">'."\n\t".'<p class="img-empty-directory">There are no images or directories in this directory.</p>'.
			       "\n".'</div>'."\n";

		if ($this->output)
			return $this->output;

		#@set_time_limit(180); // 3 Minutes
		$row_count = 1;

		// Start capturing output
		ob_start();
		echo '<div id="imgbrowz0r">', "\n\t", '<div class="img-row">', "\n";

		foreach ($this->files as $k => $file)
		{
			if ($file[0] === 1)
			{
				$image_cache_dir = crc32($this->cur_directory);
				$image_thumbnail = $image_cache_dir.'/'.$file[3].'_'.$file[1]; // The name of the thumbnail

				// Generate thumbnail if there isn't one
				if (!file_exists($this->config['cache_dir'].'/'.$image_thumbnail))
				{
					// Create the directory where the thumbnail is stored if it doesn't exist
					if (!is_dir($this->config['cache_dir'].'/'.$image_cache_dir))
						mkdir($this->config['cache_dir'].'/'.$image_cache_dir, 0777);

					$this->make_thumb($this->cur_directory, $file[1], $image_thumbnail);
				}

				// Image thumbnail markup
				echo "\t\t", '<div class="img-thumbnail img-column-', $row_count, '"><a href="', $this->config['images_url'],
				     '/', $this->cur_directory, $file[1], '" style="background-image: url(\'', $this->config['cache_url'], '/',
					 $image_thumbnail, '\')" title="', $file[1], '">', $file[1], '</a><span>', $this->format_time($file[3]),
				     '</span></div>', "\n";
			}
			else
			{
				if ($this->config['dir_thumbs'])
				{
					$dir_hash = crc32($this->cur_directory.$file[1].'/');

					// Get a list of thumbnails
					$dir_thumbs = $this->read_cache($dir_hash, $this->cur_directory.$file[1].'/');

					// Choose a thumbnail to show
					$dir_thumbnail = isset($dir_thumbs[0]) ? ' style="background-image: url(\''.$this->config['cache_url'].'/'.
					                 $dir_hash.'/'.$dir_thumbs[(!$this->config['random_thumbs'] ? 0 :
									 mt_rand(0, count($dir_thumbs)-1))].'\')"' : null;

					// Directory thumbnail markup
					echo "\t\t", '<div class="img-directory img-column-', $row_count, '"><a href="',
					     str_ireplace('%PATH%',  $this->cur_directory.$file[1].'/1', $this->config['main_url']), '"',
					     $dir_thumbnail, ' title="', $file[1], '">', $file[1], '</a><span class="img-dir-name">', $file[1],
					     '</span><span class="img-thumb-date">', $this->format_time($file[3]), '</span></div>', "\n";
				}
				else
				{
					// Display a directory without a thumbnail
					echo "\t\t", '<div class="img-directory img-no-thumbnail img-column-', $row_count, '"><a href="',
					     str_ireplace('%PATH%',  $this->cur_directory.$file[1].'/1', $this->config['main_url']),
					     '" title="', $file[1], '"><span>', $file[1], '</span></a><span>', $this->format_time($file[3]),
					     '</span></div>', "\n";
				}
			}

			if ($row_count === $this->config['max_thumb_row'] && $k < ($this->count_files - 1))
			{
				echo "\t", '</div>', "\n\t", '<div class="img-row">', "\n";
				$row_count = 0;
			}

			++$row_count;
		}

		echo "\t", '</div>', "\n\n\t", '<div class="clear">&nbsp;</div>', "\n", '</div>', "\n\n";

		$this->output = ob_get_clean();

		if ($this->cache)
		{
			$this->cache->write('dc-'.crc32($this->full_path).'-'.$this->cur_page, array(
				$this->count_dirs,
				$this->count_imgs,
				$this->count_files,
				$this->page_count,
				$this->output,
			));
		}

		// Stop capturing output
		return $this->output;
	}

	// Returns the image/directory count
	public function statistics()
	{
		// Check status code
		if ($this->status === 404 || $this->count_files === 0)
			return;

		return '<p class="img-statistics">There '.($this->count_dirs !== 1 ? 'are '.$this->count_dirs.' directories' : 'is 1 directory').
		       ' and '.($this->count_imgs !== 1 ? $this->count_imgs.' images' : '1 image').' in this directory.</p>';
	}

	// Generate breadcrumbs
	public function breadcrumbs()
	{
		// Check status code
		if ($this->status === 404)
			return;

		$path_parts = $this->cur_directory ? explode('/', trim($this->cur_directory, '/')) : array();

		// Generate breadcrumb links
		if (isset($path_parts[0]))
			foreach ($path_parts as $k => $part)
				$output[] = '<a href="'.str_ireplace('%PATH%',  implode('/', array_slice($path_parts, 0, ($k+1))).'/1' ,
				            $this->config['main_url']).'">'.$part.'</a>';

		return '<p class="img-breadcrumbs"><span>Breadcrumbs: </span><a href="'.str_ireplace('%PATH%',  '0/1', $this->config['main_url']).
		       '">Root</a>'.(isset($output) ? ' / '.implode(' / ', $output) : null).'</p>';
	}

	// Generate page navigation
	public function pagination()
	{
		$cur_page_nr = $this->cur_page;
		$page_count = $this->page_count;
		$cur_dir = $this->cur_directory ? rtrim($this->cur_directory, '/') : 0;
		$link = str_replace('%PATH%', $cur_dir.'/%d', $this->config['main_url']);

		if ($page_count == 1 || $cur_page_nr > $page_count)
			return;

		// Define some important variables
		$prev_nr = 0;
		$range = 3;
		$padding = array(0 => 3, 1 => 2, 2 => 1, 3 => 0);
		$html = array();

		// Calculate start- and endpoint
		$start = $cur_page_nr-$range < 1 ? 1 : $cur_page_nr-$range;
		$end = $cur_page_nr+$range > $page_count ? $page_count : $cur_page_nr+$range;

		// Calculate left and right padding
		$left_padding = $padding[$end - $cur_page_nr];
		$right_padding = $padding[$cur_page_nr - $start];

		// Add padding to the start- and endpoint
		$start = $start - $left_padding < 1 ? 1 : $start - $left_padding;
		$end = $right_padding + $end > $page_count ? $page_count : $right_padding + $end;

		$page_nrs = range($start, $end);

		// Add a first and last page if necessary
		if ($start != 1)
			array_unshift($page_nrs, 1);
		if ($end < $page_count)
			$page_nrs[] = $page_count;

		// Previous page link
		$html[] = $cur_page_nr > 1 ? '<a href="'.sprintf($link, ($cur_page_nr-1)).'">Previous</a>' : '<span>Previous</span>';

		// Shoop da loop
		foreach ($page_nrs as $i => $nr)
		{
			if ($prev_nr+1 < $nr)
				$html[] = '&hellip;';

			$html[] = '<a href="'.sprintf($link, $nr).'">'.($nr == $cur_page_nr ? '<strong>'.$nr.'</strong>' : $nr).'</a>';
			$prev_nr = $nr;
		}

		// Next page link
		$html[] = $cur_page_nr < $page_count ? '<a href="'.sprintf($link, $cur_page_nr+1).'">Next</a>' : '<span>Next</span>';

		return '<p class="pagination">'.implode('&nbsp;&nbsp;', $html).'</p>';
	}

	/* Display description of the current directory
	   Html tags are stripped from the description except the following tags:
	   <p>, <strong>, <em>, <a>, <br />, <h1>, <h2> and <h3> */
	public function description()
	{
		if (file_exists($this->full_path.'.desc'))
			return '<div class="img-description">'.
			strip_tags(file_get_contents($this->full_path.'.desc'), '<p><strong><em><a><br><h1><h2><h3>').
			'</div>';
	}

	// The legendary thumbnail generater
	protected function make_thumb($image_dir, $image_name, $image_thumbnail)
	{
		$image_dir = $image_dir ? $image_dir.'/' : null;
		$image_info = $this->get_image_info($this->config['images_dir'].'/'.$image_dir.'/'.$image_name);

		// Check if file is an supported image type
		if (!isset($this->image_types[$image_info['extension']]))
			return false;

		// Open the image so we can make a thumbnail
		if ($image_info['type'] === 3)
			$image = imagecreatefrompng($this->config['images_dir'].'/'.$image_dir.$image_name);
		else if ($image_info['type'] === 2)
			$image = imagecreatefromjpeg($this->config['images_dir'].'/'.$image_dir.$image_name);
		else if ($image_info['type'] === 1)
			$image = imagecreatefromgif($this->config['images_dir'].'/'.$image_dir.$image_name);

		// Calculate new width and height
		$zoomw = $image_info['width'] / $this->config['max_thumb_width'];
		$zoomh = $image_info['height'] / $this->config['max_thumb_height'];
		$zoom = ($zoomw > $zoomh) ? $zoomw : $zoomh;

		if ($image_info['width'] < $this->config['max_thumb_width'] && $image_info['height'] < $this->config['max_thumb_height'])
		{
			// Preserve the original dimensions of the image is smaller than the maximum height and width
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

		// Preserve transparency in PNG
		if ($image_info['type'] === 3)
		{
			$alpha_color = imagecolorallocatealpha($thumbnail, 0, 0, 0, 127);
			imagefill($thumbnail, 0, 0, $alpha_color);
			imagesavealpha($thumbnail, true);
		}

		// Preserve transparency in GIF images
		else if ($image_info['type'] === 1 && ($transparent_index = imagecolortransparent($image)) >= 0)
		{
			$transparent_color = imagecolorsforindex($image, $transparent_index);
			$transparent_index = imagecolorallocate($thumbnail, $transparent_color['red'], $transparent_color['green'],
				$transparent_color['blue']);
			imagefill($thumbnail, 0, 0, $transparent_index);
			imagecolortransparent($thumbnail, $transparent_index);
		}

		// Copy and resize image
		imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, $thumb_width, $thumb_height, $image_info['width'], $image_info['height']);

		// Save the thumbnail, but first check what kind of file it is
		if ($image_info['type'] === 3)
			imagepng($thumbnail, $this->config['cache_dir'].'/'.$image_thumbnail);
		else if ($image_info['type'] === 2)
			imagejpeg($thumbnail, $this->config['cache_dir'].'/'.$image_thumbnail, 85); // 85 is the quality of the Jpeg thumbnail
		else if ($image_info['type'] === 1)
		{
			imagetruecolortopalette($thumbnail, true, 256);
			imagegif($thumbnail, $this->config['cache_dir'].'/'.$image_thumbnail);
		}

		// Destroy! (cleanup)
		imagedestroy($image);
		imagedestroy($thumbnail);
	}

	// Reads the cache folder for thumbnails. If none is found it makes one thumbnail
	protected function read_cache($cache_path, $category_path)
	{
		$thumbnails = array();

		// Look for thumbnails in the cache dir
		if (is_dir($this->config['cache_dir'].'/'.$cache_path))
		{
			$file_count = 0;
			$dir = new DirectoryIterator($this->config['cache_dir'].'/'.$cache_path);

			foreach ($dir as $index => $fi)
			{
				$filename = $fi->getFilename();

				if (isset($this->image_types[$this->get_ext($filename)]))
				{
					$thumbnails[] = $filename;
					++$file_count;

					if ($file_count === $this->config['read_thumb_limit'])
						break;
				}
			}

			unset($dir);
		}
		else
			mkdir($this->config['cache_dir'].'/'.$cache_path, 0777);

		// Generate a thumbnail if none is found
		if (!isset($thumbnails[0]))
		{
			$file_count = 0;
			$dir = new DirectoryIterator($this->config['images_dir'].'/'.$category_path);

			foreach ($dir as $fi)
			{
				$filename = $fi->getFilename();

				if (isset($this->image_types[$this->get_ext($filename)]))
				{
					// The name of the thumbnail
					$image_thumbnail = $cache_path.'/'.filectime($this->config['images_dir'].'/'.$category_path.'/'.$filename).'_'.$filename;

					$thumbnails[] = basename($image_thumbnail);
					$this->make_thumb($category_path, $filename, $image_thumbnail);

					++$file_count;

					// Change 1 to a higher number if you want to generate more thumbnails
					// for a directory, but this also takes more time and memory.
					if ($file_count === 1)
						break;
				}
			}

			unset($dir);
		}

		return $thumbnails;
	}

	// Format unix timestamp to a human readable date
	protected function format_time($timestamp)
	{
		return date($this->config['time_format'], $timestamp);
	}

	// Get info from image (width, height, type, extension)
	// http://php.net/manual/en/function.getimagesize.php
	protected function get_image_info($filepath)
	{
		$getimagesize = getimagesize($filepath);

		return array(
			'width' => $getimagesize[0],
			'height' => $getimagesize[1],
			'type' => $getimagesize[2],
			'extension' => $this->get_ext($filepath));
	}

	// Get extension from filename (returns the extension with the dot)
	protected function get_ext($filename)
	{
		// return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
		return strtolower(substr($filename, strrpos($filename, '.'))); // This is a bit faster
	}
}

class ImgBrowz0rCache
{
	private $cache_path, $cache_lifetime;

	public function __construct($cache_path = '/tmp', $cache_lifetime = 3600)
	{
		$this->cache_path = $cache_path;
		$this->cache_lifetime = $cache_lifetime;
	}

	public function read($name)
	{
		if (file_exists($this->cache_path.'/'.$name.'.php') &&
		    (time() - filectime($this->cache_path.'/'.$name.'.php')) < $this->cache_lifetime)
		{
			return require $this->cache_path.'/'.$name.'.php';
		}

		return false;
	}

	public function write($name, $data)
	{
		return file_put_contents($this->cache_path.'/'.$name.'.php',
			'<?php return '.var_export($data, true).';');
	}
}
