<?php
Configure::write('MediaPlugin', array(
	'enabled' => true,
	'useTokens' => false,
	'links' => 'hard',
	'fallback' => 'favicon.ico',
	'storeOriginal' => false,
	'store' => true,
));