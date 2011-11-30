<?php
$config['Media']['versions'] = array(
	'tiny' => array('fitCrop', 20, 20),
	'icon' => array('fitCrop', 32, 32),
	'thumb' => array('fitInside', 50, 200),
	'small' => array('fitInside', 150, 300),
	'medium' => array('fitInside', '250', 500),
	'large' => array('fitInside', 600, 600),
	'default' => array('fitInside', 600, 600),
);