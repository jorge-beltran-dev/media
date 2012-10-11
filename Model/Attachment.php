<?php
/**
 * Attachment Model File
 *
 * Copyright (c) 2007-2010 David Persson
 *
 * Distributed under the terms of the MIT License.
 * Redistributions of files must retain the above copyright notice.
 *
 * PHP version 5
 * CakePHP version 1.3
 *
 * @package    media
 * @subpackage media.models
 * @copyright  2007-2010 David Persson <davidpersson@gmx.de>
 * @license    http://www.opensource.org/licenses/mit-license.php The MIT License
 * @link       http://github.com/davidpersson/media
 */

/**
 * Attachment Model Class
 *
 * A ready-to-use model combining multiple behaviors. This model is named "Attachment" to avoid
 * Name conflicts with other classes - IE the Media vendor
 *
 * @package    media
 * @subpackage media.models
 */
class Attachment extends MediaAppModel {

/**
 * name property
 *
 * @var string 'Attachment'
 * @access public
 */
	public $name = 'Attachment';

/**
 * useTable property
 *
 * @var string 'media'
 * @access public
 */
	public $useTable = 'media';

/**
 * actsAs property
 *
 * @var array
 * @access public
 */
	var $actsAs = array(
		/* moving files around on upload */
		'Media.Transfer' => array(
			'trustClient' => false,
			'createDirectory' => true,
			'alternativeFile' => 100
		),
		/* adds the filepath in results. deleted files and versions, checkRepresent? */
		'Media.Coupler',
	   	/* adds metadata */
		'Media.Meta' => array(
			'level' => 2
		)
	);

/**
 * hasMany property
 *
 * @var array
 * @access public
 */
	var $hasMany = array(
		'MediaLink'
	);

/**
 * Validation rules for file and alternative fields
 *
 * For more information on the rules used here
 * see the source of TransferBehavior and MediaBehavior or
 * the test case for MediaValidation.
 *
 * If you experience problems with your model not validating,
 * try commenting the mimeType rule or providing less strict
 * settings for single rules.
 *
 * `checkExtension()` and `checkMimeType()` take both a blacklist and
 * a whitelist. If you are on windows make sure that you addtionally
 * specify the `'tmp'` extension in case you are using a whitelist.
 *
 * @var array
 * @access public
 */
	public $validate = array(
		'file' => array(
			'resource'   => array('rule' => 'checkResource'),
			'access'     => array('rule' => 'checkAccess'),
			/*
			'location'   => array('rule' => array('checkLocation', array(
				MEDIA_TRANSFER, '/tmp/'
			))),
			*/
			'permission' => array('rule' => array('checkPermission', '*')),
			'size'       => array('rule' => array('checkSize', '5M')),
			'pixels'     => array('rule' => array('checkPixels', '1600x1600')),
			'extension'  => array('rule' => array('checkExtension', false, array(
				'jpg', 'jpeg', 'png', 'tif', 'tiff', 'gif', 'pdf', 'tmp'
			))),
			'mimeType'   => array('rule' => array('checkMimeType', false, array(
				'image/jpeg', 'image/png', 'image/tiff', 'image/gif', 'application/pdf'
		)))),
		'alternative' => array(
			'rule'       => 'checkRepresent',
			'on'         => 'create',
			'required'   => false,
			'allowEmpty' => true,
		)
	);

	// var $virtualFields = array(
	//	'path' => "CONCAT_WS('/', dirname, basename)",
	// );

/**
 * Generate a version of a file
 *
 * Uncomment to force Generator Behavior to use this method when
 * generating versions of files.
 *
 * If you want to fall back from your code back to the default method use:
 * `return $this->Behaviors->Generator->makeVersion($this, $file, $process);`
 *
 * $process an array with the following contents:
 *  directory - The destination directory (If this method was called
 *              by `make()` the directory is already created)
 *  version - The version requested to be processed (e.g. `l`)
 *  instructions - An array containing which names of methods to be called
 *
 * @param file $file Absolute path to source file
 * @param array $process version, directory, instructions
 * @return boolean `true` if version for the file was successfully stored
 */
	// function makeVersion($file, $process) {
	// }

/**
 * beforeSave method
 *
 * @return void
 * @access public
	function beforeSave() {
		if (!$this->id && isset($this->data[$this->alias]['checksum'])) {
			$duplicate = $this->find('first', array(
				'checksum' => $this->data[$this->alias]['checksum']
			));
			if ($duplicate) {
				$this->id = $duplicate[$this->alias]['id'];
				$this->data = $duplicate;
				return false;
			}
		}
		return true;
	}
 */

/**
 * duplicates method
 *
 * @return void
 * @access public
 */
	function duplicates() {
		$db =& ConnectionManager::getDataSource($this->useDbConfig);
		$subquery = $db->expression('(SELECT checksum FROM media GROUP BY checksum HAVING COUNT(checksum) > 1)');
		return $this->find('all', array(
			'conditions' => array(
				'checksum' => $subquery
			),
			'order' => 'checksum DESC'
		));
	}

/**
 * Returns the relative path to the destination file
 *
 * Uncomment to force Transfer Behavior to use this method when
 * determining the destination path instead of the builtin one.
 *
 * @param array $via Information about the temporary file
 * @param array $from Information about the source file
 * @return string The path to the destination file or false
 */
	// function transferTo($via, $from) {
	// }

}