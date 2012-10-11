<?php
/**
 * A controller for serving up media files - Experimental
 *
 * PHP version 4 and 5
 *
 * Copyright (c) 2009, Andy Dawson
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @filesource
 * @copyright     Copyright (c) 2009, Andy Dawson
 * @link          www.ad7six.com
 * @package       media
 * @subpackage    media.controllers
 * @since         v 1.0 (01-Oct-2009)
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

/**
 * MediaController class
 *
 * @uses          MediaAppController
 * @package       media
 * @subpackage    media.controllers
 */
class MediaController extends MediaAppController {

/**
 * settings property
 *
 * Enabled - whether to do anything at all, or just trigger an error as if this controller didn't exist
 * Links - How to handle duplicate references to the same file
 * 	true|sym - use symlinks where possible
 * 	hard - use hard links where possible
 * 	false - dont link, copy files (also the fallback in the event linking fails)
 * fallback - the file to use in the event the request is invalid, or valid but there's an error
 * storeOriginal - copy originals to the webroot when processing a version?
 * store - store the request in the webroot?
 *
 * @var array
 * @access public
 */
	var $settings = array(
		'enabled' => true,
		'useTokens' => false,
		'links' => 'hard',
		'fallback' => 'favicon.ico',
		'autoFallback' => true,
		'storeOriginal' => false,
		'store' => true,
		'versions' => array(
		)
	);

/**
 * uses property
 *
 * @var array
 * @access public
 */
	var $uses = array();

/**
 * components property
 *
 * @var array
 * @access public
 */
	var $components = array();

/**
 * helpers property
 *
 * @var array
 * @access public
 */
	var $helpers = array();

/**
 * autoRender property
 *
 * @var bool false
 * @access public
 */
	var $autoRender = false;

/**
 * view property
 *
 * @var string 'Media'
 * @access public
 */
	var $view = 'Media';

/**
 * mergeVars method
 *
 * As light as possible
 *
 * @return void
 * @access private
 */
	function __mergeVars() {}

/**
 * Set the fallback image, used for any request for an image that doesn't exist
 * Set the target to the current url
 *
 * @return void
 * @access public
 */
	function beforeFilter() {
		$this->_setup();

		if (!$this->settings['enabled']) {
			return $this->cakeError('error404', array(array(
				'className' => 'DisabledMediaController',
				'plugin' => $this->plugin,
				'webroot' => $this->webroot,
				'url' => $url,
				'base' => $this->base
			)));
		}
		$this->__target = WWW_ROOT . ltrim($this->params->url, '/');
	}

/**
 * beforeRender method
 *
 * @return void
 * @access public
 */
	function beforeRender() {}

/**
 * serve method
 *
 * If the setting useTokens is true, check that the request token matches the url to
 * prevent a user just typing a url in the addressbar and DOS ing the server. If the request
 * is not authentic, put a link to the fallback in the webroot so that subsequent requests
 * do not hit php.
 *
 * If the request is for the original file - link or copy it to the webroot
 * If the request is for a version, after checking the original exists -
 * 	Generate it, and put it in the webroot
 *
 * can process any of the following types of request, given that uploads/img/example.jpg exists
 * /img/example.jpg
 * 	link, or copy, the original to the request url (webroot/img/example.jpg)
 * /img/example,small.jpg
 * 	see bootstrap/media.php - process the request using the small config
 * /img/example,99x10.jpg
 * 	see bootstrap/media.php - process the request using the 99x10 config
 * /img/example,100x100.jpg
 * 	generate a 100x100 'fit' image
 * /img/example,fitCrop,50,50.jpg
 * 	use fitCrop, passing array(50, 50) as the params
 * /img/example,zoomCrop,50,50,center.jpg
 * 	use zoomCrop, passing array(50, 50, center) as the params
 * /img/example,someother,1,2,3,foo,bar,zum.jpg
 * 	use someother, passing array(1, 2, 3, foo, bar, zum) as the params
 * etc.
 *
 * The request does not have to be for an image, any type of media can be handled the same way
 * if an adapter method exists to generate the desired version. That said, currently there are
 * only appropriate methods in the vendors for images
 *
 * @return void
 * @access public
 */
	function serve() {
		$url = $this->params->url;
		if ($this->settings['useTokens']) {
			if (!isset($this->params['url']['token'])) {
				return $this->_serve();
			}
			$compare = $this->params['url']['token'];
			App::import('Core', 'Security');
			$hash = Security::hash($url,null, true);
			if (!Configure::read() && $hash !== $compare) {
				return $this->_serve();
			}
		}
		if (!strpos($url, ',')) {
			if ($this->_linkOriginal($url, $this->settings['store'])) {
				return $this->_serve();
			}
			header("HTTP/1.0 404 Not Found");
			return $this->_serve();
		}
		$params = array();
		preg_match('@,(.*)\.@', $url, $matches);
		$version = $matches[1];
		if (isset($this->settings['versions'][$version])) {
			$params = $this->settings['versions'][$version];
		}

		if (!$params) {
			if (preg_match('@^(\d*)x(\d*)$@', $version, $dims)) {
				$params = array('fit', $dims[0], $dims[1]);
			} elseif (strpos($version, ',')) {
				$params = explode(',', $version);
			} else {
				$isImage = false;
				if ($isImage && !empty($this->settings['versions']['default'])) {
					$params = $this->settings['versions']['default'];
				} else {
					return $this->_serve();
				}
			}
		}
		$original = $this->_linkOriginal($url);
		if (!$original) {
			header("HTTP/1.0 404 Not Found");
			if ($this->settings['autoFallback']) {
				if (file_exists(WWW_ROOT . 'img' . DS . $version . '.gif')) {
					$this->_link(WWW_ROOT . 'img' . DS . $version . '.gif');
				} else {
					$dims = array_slice($params, 1, 2);
					$version = implode($dims, 'x');
					if (file_exists(WWW_ROOT . 'img' . DS . $version . '.gif')) {
						$this->_link(WWW_ROOT . 'img' . DS . $version . '.gif');
					}
				}
			}
			$this->_serve();
		}
		App::uses('Media', 'Media.Libs/Media');
		$Media = Media::factory($original);
		$function = array_shift($params);
		call_user_func_array(array($Media, $function), $params);
		if ($this->settings['store']) {
			$Media->store(WWW_ROOT . $url, false, false);
		}
		$this->_serve();
	}

/**
 * exec method
 *
 * @param mixed $cmd
 * @param mixed $out null
 * @return void
 * @access protected
 */
	protected function _exec($cmd, &$out = null) {
		return !exec($cmd, $out);
	}

/**
 * link method
 *
 * Link source to target,
 * If no source is provided, use the fallback
 * If no target is specified use the current url
 *
 * @param mixed $source null
 * @param mixed $target null
 * @return void
 * @access protected
 */
	function _link($source = null, $target = null) {
		if (!$source) {
			$source = $this->settings['fallback'];
		}
		if (!$target) {
			$target = $this->__target;
		}
		$cmdSource = escapeshellarg($source);
		$cmdDir = escapeshellarg(dirname($target));
		$cmdTarget = escapeshellarg($target);
		App::uses('Folder','Utility');
		new Folder(dirname($target), true);
		if (DS === '/' && $this->settings['links']) {
			if ($this->settings['links'] === 'hard') {
				$cmd = "ln -f $cmdSource $cmdTarget";
			} else {
				$cmd = "ln -sf $cmdSource $cmdTarget";
			}
			$this->_exec($cmd);
			if (file_exists($target)) {
				return true;
			}
		}
		copy($source, $target);
	}

/**
 * linkOriginal method
 *
 * Check that the original uploaded file exists, don't check if the webroot file exists
 * (which could just be a link to the fallback)
 *
 * @param mixed $url null
 * @return void
 * @access protected
 */
	function _linkOriginal($url = null, $storeOverride = null) {
		$url = ltrim($url, '/');
		$original = preg_replace('@,.*\.@', '.', $url);
		if (file_exists(WWW_ROOT . $original)) {
			return WWW_ROOT . $original;
		}
		$upload = APP . 'uploads' . DS . $original;
		if (!file_exists($upload)) {
			return false;
		}
		if ($this->settings['storeOriginal'] || $storeOverride) {
			$this->_link($upload, WWW_ROOT . $original);
			return WWW_ROOT . $original;
		}
		return $upload;
	}

/**
 * serve method
 *
 * If the current target doesn't exist, link it to the fallback
 * Serve it up
 *
 * @param mixed $target
 * @return void
 * @access protected
 */
	function _serve() {
	
		if (!file_exists($this->__target)) {
			$this->_link();
		}

		$data['id'] = basename($this->__target);
		$data['path']= str_replace(APP, '', dirname($this->__target)) . DS;
		$data['extension'] = array_pop(explode('.', $data['id']));
		$data['name'] = basename($this->params->url);
		$this->set($data);
		$this->viewClass = 'Media';
		Configure::write('debug', 0);
		$this->render();
	}

	function _setup() {
		if (file_exists(CONFIGS . 'media.php')) {
			include(CONFIGS . 'media.php');
		} else {
			include(dirname(dirname(__FILE__)) . DS . 'Config' . DS . 'media.php');
		}

		$this->settings = array_merge($this->settings, $config['Media']);

		if (!file_exists($this->settings['fallback'])) {
			$this->settings['fallback'] = WWW_ROOT . $this->settings['fallback'];
		}
	}
}
