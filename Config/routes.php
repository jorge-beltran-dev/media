<?php
/**
 * Forward missing media requests to the media serve funciton
 */
Router::connect(
	'/:mediaType/*',
	array('plugin' => 'media', 'controller' => 'media', 'action' => 'serve'),
	array('mediaType' => '(aud|doc|gen|ico|img|txt|vid)')
);