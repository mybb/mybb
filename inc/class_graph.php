<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

class Graph {

	/**
	 * The width of the image.
	 *
	 * @var integer
	 */
	public $img_width = 1000;

	/**
	 * The height of the image.
	 *
	 * @var integer
	 */
	public $img_height = 300;

	/**
	 * The image resource handle.
	 *
	 * @var resource
	 */
	private $im;

	/**
	 * The amount of x pixels to start inside the image for the graph
	 *
	 * @var integer
	 */
	public $inside_x = 65;

	/**
	 * The amount of y pixels to start inside the image for the graph
	 *
	 * @var integer
	 */
	public $inside_y = 30;

	/**
	 * The width of the inside graph
	 *
	 * @var integer
	 */
	public $inside_width = 930;

	/**
	 * The height of the inside graph
	 *
	 * @var integer
	 */
	public $inside_height = 220;

	/**
	 * The x, y points for the graph
	 *
	 * @var array
	 */
	public $points = array();

	/**
	 * The corresponding x labels for the graph
	 *
	 * @var integer
	 */
	public $x_labels = array();

	/**
	 * The bottom label for the graph
	 *
	 * @var string
	 */
	public $bottom_label = "";

	/**
	 * Constructor of class. Initializes the barebore graph.
	 *
	 * @return Graph
	 */
	public function __construct()
	{
		// Setup initial graph layout

		// Check for GD >= 2, create base image
		if(gd_version() >= 2)
		{
			$this->im = imagecreatetruecolor($this->img_width, $this->img_height);
		}
		else
		{
			$this->im = imagecreate($this->img_width, $this->img_height);
		}

		// No GD support, die.
		if(!$this->im)
		{
			return false;
		}

		if(function_exists("imageantialias"))
		{
			imageantialias($this->im, true);
		}

		// Fill the background
		imagefill($this->im, 0, 0, $this->color(239, 239, 239));

		// Create our internal working graph box
		$inside_end_x = $this->inside_x+$this->inside_width;
		$inside_end_y = $this->inside_y+$this->inside_height;
		$this->image_create_rectangle($this->inside_x, $this->inside_y, $inside_end_x, $inside_end_y, 4, $this->color(254, 254, 254));

		// Draw our three lines inside our internal working graph area
		for($i = 1; $i < 4; ++$i)
		{
			$y_value = $this->inside_y+(($this->inside_height/4)*$i);
			imageline($this->im, $this->inside_x, $y_value, $inside_end_x, $y_value, $this->color(185, 185, 185));
		}
	}

	/**
	 * Select and allocate a color to the internal image resource
	 *
	 * @param integer The red value
	 * @param integer The green value
	 * @param integer The blue value
	 * @return integer A color identifier
	 */
	private function color($red, $green, $blue)
	{
		return imagecolorallocate($this->im, $red, $green, $blue);
	}

	/**
	 * Creates a filled rectangle with optional rounded corners
	 *
	 * @param integer The initial x value
	 * @param integer The initial y value
	 * @param integer The ending x value
	 * @param integer The ending y value
	 * @param integer The optional radius
	 * @param integer The optional rectangle color (defaults to black)
	 */
	private function image_create_rectangle($x1, $y1, $x2, $y2, $radius=1, $color=null)
	{
		if($color == null)
		{
			$color = $this->color(0, 0, 0);
		}

		// Draw our rectangle
		imagefilledrectangle($this->im, $x1, $y1+$radius, $x2, $y2-$radius, $color);
		imagefilledrectangle($this->im, $x1+$radius, $y1, $x2-$radius, $y2, $color);

		if($radius > 0)
		{
			$diameter = $radius*2;

			// Now draw our four corners on the rectangle
			imagefilledellipse($this->im, $x1+$radius, $y1+$radius, $diameter, $diameter, $color);
			imagefilledellipse($this->im, $x1+$radius, $y2-$radius, $diameter, $diameter, $color);
			imagefilledellipse($this->im, $x2-$radius, $y2-$radius, $diameter, $diameter, $color);
			imagefilledellipse($this->im, $x2-$radius, $y1+$radius, $diameter, $diameter, $color);
		}
	}

	/**
	 * Creates a nicer thick line for angled lines
	 *
	 * @param integer The initial x value
	 * @param integer The initial y value
	 * @param integer The ending x value
	 * @param integer The ending y value
	 * @param integer The optional rectangle color (defaults to black)
	 * @param integer The optional thickness (defaults to 1)
	 */
	private function imagelinethick($x1, $y1, $x2, $y2, $color, $thick = 1)
	{
		if($thick == 1)
		{
			return imageline($this->im, $x1, $y1, $x2, $y2, $color);
		}

		$t = $thick / 2 - 0.5;
		if($x1 == $x2 || $y1 == $y2)
		{
			return imagefilledrectangle($this->im, round(min($x1, $x2) - $t), round(min($y1, $y2) - $t), round(max($x1, $x2) + $t), round(max($y1, $y2) + $t), $color);
		}

		$k = ($y2 - $y1) / ($x2 - $x1); //y = kx + q
		$a = $t / sqrt(1 + pow($k, 2));
		$points = array(
			round($x1 - (1+$k)*$a), round($y1 + (1-$k)*$a),
			round($x1 - (1-$k)*$a), round($y1 - (1+$k)*$a),
			round($x2 + (1+$k)*$a), round($y2 - (1-$k)*$a),
			round($x2 + (1-$k)*$a), round($y2 + (1+$k)*$a),
		);
		imagefilledpolygon($this->im, $points, 4, $color);

		return imagepolygon($this->im, $points, 4, $color);
	}

	/**
	 * Adds an array of x, y points to the internal points array
	 *
	 * @param array The array of x, y points to add
	 */
	public function add_points($points)
	{
		$this->points = array_merge($this->points, $points);
	}

	/**
	 * Adds an array of x labels to the internal labels array
	 *
	 * @param array The array of x labels to add
	 */
	public function add_x_labels($labels)
	{
		$this->x_labels = array_merge($this->x_labels, $labels);
	}

	/**
	 * Sets a bottom label
	 *
	 * @param string The bottom label to set
	 */
	public function set_bottom_label($label)
	{
		$this->bottom_label = $label;
	}

	/**
	 * Renders the graph to memory
	 *
	 */
	public function render()
	{
		// Get our max's and min's
		$asorted = $this->points;
		sort($asorted, SORT_NUMERIC);
		$min = $asorted[0];
		$max = $asorted[count($asorted)-1];

		// Scale based on how many points we need to shove into 930 pixels of width
		$x_delta = $this->inside_width/count($this->points);

		// Scale our y axis to 220 pixels
		$y_scale_factor = ($max-$min)/$this->inside_height;

		// Get our Y initial
		$y_initial = $this->inside_y+$this->inside_height;

		// Get our scale for finding our points of reference to place our x axis labels
		$x_label_scale = ceil(count($this->points)/20);
		$x_label_points = array();

		foreach($this->points as $x => $y)
		{
			if(($x_label_scale == 0 || (($x+1) % $x_label_scale) == 0) && $x != 0)
			{
				$x_label_points[] = $x;

				imagedashedline($this->im, $this->inside_x+($x_delta*$x), 30, $this->inside_x+($x_delta*$x), $y_initial, $this->color(185, 185, 185));

				imagefilledellipse($this->im, $this->inside_x+($x_delta*$x), $y_initial-$next_y_scaled+0.5, 8, 8, $this->color(84, 92, 209));
			}

			// Look ahead to find our next point, if there is one
			if(!array_key_exists($x+1, $this->points))
			{
				break;
			}
			$next_y = $this->points[$x+1];

			if($y_scale_factor == 0)
			{
				$y_scaled = $next_y_scaled = 0;
			}
			else
			{
				$y_scaled = ($y-$min)/$y_scale_factor;
				$next_y_scaled = ($next_y-$min)/$y_scale_factor;
			}

			// Draw our line
			$this->imagelinethick($this->inside_x+($x_delta*$x), $y_initial-$y_scaled, $this->inside_x+($x_delta*($x+1)), $y_initial-$next_y_scaled, $this->color(84, 92, 209), 3);
		}

		// Draw our x labels
		foreach($x_label_points as $x)
		{
			$label = $this->x_labels[$x];
			$text_width = imagefontwidth(2)*strlen($label);
			$x = $this->inside_x+($x_delta*$x)-($text_width/2);

			imagestring($this->im, 2, $x, $y_initial+5, $label, $this->color(0, 0, 0));
		}

		// Draw our bottom label
		imagestring($this->im, 2, ($this->img_width / 2), $y_initial+25, $this->bottom_label, $this->color(0, 0, 0));

		if($max > 4)
		{
			// Draw our y labels
			for($i = 1; $i < 4; ++$i)
			{
				$y_value = $this->inside_y+(($this->inside_height/4)*$i);
				imagestring($this->im, 2, 5, $y_value-7, my_number_format(round($min+(($max-$min)/4)*(4-$i))), $this->color(0, 0, 0));
			}
		}
		imagestring($this->im, 2, 5, $this->inside_y+$this->inside_height-7, my_number_format($min), $this->color(0, 0, 0));
		imagestring($this->im, 2, 5, $this->inside_y-7, my_number_format($max), $this->color(0, 0, 0));
	}

	/**
	 * Outputs the graph to the screen in PNG format
	 *
	 */
	public function output()
	{
		// Output the image
		header("Content-type: image/png");
		imagepng($this->im);
		imagedestroy($this->im);
		exit;
	}
}

