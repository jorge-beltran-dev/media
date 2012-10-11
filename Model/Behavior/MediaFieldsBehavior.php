<?php
/**
 * MediaFields is a behavior which allows you to have all the benefits of managing your site's
 * media with a dedicated model (and table, for fundamental metadata) without the disadvantage
 * of being forced to query the database to know, for example, what avatar goes with what user.
 *
 * See config/sql/easier.sql for the schema related to using this behavior
 *
 * PHP versions 4 and 5
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
 * @subpackage    media.models.behaviors
 * @since         v 1.0 (09-Dec-2009)
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

/**
 * MediaFieldsBehavior class
 *
 * @uses          ModelBehavior
 * @package       media
 * @subpackage    media.models.behaviors
 */
class MediaFieldsBehavior extends ModelBehavior {

/**
 * defaultSettings property
 *
 * @var array
 * @access protected
 */
	var $_defaultSettings = array(
		'baseDirectory' => 'uploads/',
		'afterFind' => false,
		'mediaModel' => 'Media.Attachment',
		'linkModel' => 'MediaLink',
		'fields' => array(),
		'__modelsInitialized' => false,
	);

/**
 * setup method
 *
 * Possible options:
 * afterFind : whether to run afterFind logic, defaults to false
 * mediaModel: the name of the model responsible for all media, defaults to Attachment
 * linkModel: the name of the model responsible for linking media to whatever is using it
 * fields: which fields are either in the model which represent media, or are virtual and should be
 * 	populated. If the media fields are virtual(i.e. you don't wnat to denormalize and have urls
 * 	in a field) afterFind must manually be set to true. It is not automatic for performance reasons
 *
 * Allow any of the following formats:
 * var $actsAs = array('Media->MediaFields' => array('fields' => 'foo'));
 * var $actsAs = array('Media->MediaFields' => array(
 * 		'fields' => array(
 * 			'foo'
 * 		)
 * ));
 * var $actsAs = array('Media->MediaFields' => array(
 * 		'fields' => array(
 * 			'foo' => array(
 * 				'file' => 'field_to_store_filename',
 * 				'mimetype' => 'field_to_store_mimetype',
 * 				'size' => 'field_to_store_size',
 * 			)
 * 		)
 * ));
 *
 * Any field, virtual or real, which the media plugin automatically populates for the Attachment
 * model can be retrieved or stored on/in the model data
 *
 * @param mixed $Model
 * @param array $config array()
 * @return void
 * @access public
 */
	function setup(&$Model, $config = array()) {
		$this->settings[$Model->alias] = am ($this->_defaultSettings, $config);
		if (!empty($this->settings[$Model->alias]['fields'])) {
			if (!is_array($this->settings[$Model->alias]['fields'])) {
				$this->settings[$Model->alias]['fields'] = array($this->settings[$Model->alias]['fields']);
			}
			foreach($this->settings[$Model->alias]['fields'] as $set => &$fields) {
				if (is_numeric($set) && !is_array($fields)) {
					unset ($this->settings[$Model->alias]['fields'][$set]);
					$this->settings[$Model->alias]['fields'][$fields] = array('file' => $fields);
				}
			}
		}
		if ($this->settings[$Model->alias]['baseDirectory'][0] !== DS &&
			!strpos($this->settings[$Model->alias]['baseDirectory'], ':')) {
			$this->settings[$Model->alias]['baseDirectory'] = APP . $this->settings[$Model->alias]['baseDirectory'];
		}
	}

/**
 * afterFind method
 *
 * If the setting "afterFind" is set to true (defaults to false) and recursive is 0 or greater
 * For each MediaField defined, look for it and set the data in the result set
 *
 * @param mixed $Model
 * @param mixed $results
 * @param bool $primary false
 * @return void
 * @access public
 */
	function afterFind(&$Model, $results, $primary = false) {
		extract($this->settings[$Model->alias]);
		if (!$afterFind || !$fields || $Model->recursive < 0) {
			return $results;
		}
		$this->setupMediaModels($Model);
		if (isset($results[0][$Model->alias][$Model->primaryKey])) {
			foreach ($results as $key => &$result) {
				$this->_fillFields($Model, $result, $this->settings[$Model->alias]);
			}
		} elseif(isset($results[$Model->alias][$Model->primaryKey])) {
			$this->_fillFields($Model, $results, $this->settings[$Model->alias]);
		}
		return $results;
	}

/**
 * afterSave method
 *
 * If Media was saved (in beforeValidate) create the link between this model instance and
 * the media
 *
 * @param mixed $Model
 * @return void
 * @access public
 */
	function afterSave(&$Model) {
		foreach($this->settings[$Model->alias]['fields'] as $alias => &$fields) {
			if (isset($fields['__id'])) {
				$modelField = current($fields);
				if (strpos($modelField, '.')) {
					list ($mAlias, $modelField) = explode('.', $modelField);
					$fId = $Model->$mAlias->id;
				} else {
					$mAlias = $Model->alias;
					$fId = $Model->id;
				}
				if (empty($LinkModel)) {
					$LinkModel = $this->settings[$Model->alias]['MediaModel']->{$this->settings[$Model->alias]['linkModel']};
				}
				$toSave = array(
					'model' => $mAlias,
					'foreign_id' => $fId,
					'field' => $modelField
				);
				$LinkModel->create();
				$LinkModel->id = $LinkModel->field('id', $toSave);

				$toSave['media_id'] = $fields['__id'];
				$LinkModel->save($toSave);

				unset($fields['__id']);
			}
		}
	}

/**
 * beforeValidate method
 *
 * Call _saveFields to check for uploaded Media
 *
 * @param mixed $Model
 * @return void
 * @access public
 */
	function beforeValidate(&$Model) {
		extract($this->settings[$Model->alias]);
		if (!$fields) {
			return true;
		}
		return $this->_saveFields($Model, $Model->data, $this->settings[$Model->alias]);
	}

/**
 * fillFields method
 *
 * For each field(set) find the linked Media - and populate the specifid fields on the current model
 * Return a relative file path.
 *
 * Ordinarily this method is only called if a MediaField is defined but not stored on the model itself
 *
 * @param mixed $Model
 * @param mixed $row
 * @param mixed $settings
 * @return void
 * @access protected
 */
	function _fillFields(&$Model, &$row, $settings) {
		foreach ($settings['fields'] as $alias => $set) {
			$settings['MediaModel']->Behaviors->disable('RestrictSite');
			$media = $settings['MediaModel']->find('first', array(
					'recursive' => 0,
					'conditions' => array(
						$settings['linkModel'] . '.model' => $Model->alias,
						$settings['linkModel'] . '.foreign_id' => $row[$Model->alias][$Model->primaryKey],
						'field' => $alias,
					)
			));
			if (!$media) {
				continue;
			}
			$media = current($media);
			foreach($set as $mediaField => $modelField) {
				if (!isset($media[$mediaField])) {
					continue;
				}
				if (strpos($modelField, '.')) {
					list ($mAlias, $modelField) = explode('.', $modelField);
				} else {
					$mAlias = $Model->alias;
				}
				if ($mediaField === 'file') {
					$media[$mediaField] = str_replace(array($baseDirectory, '\\'), array('', '/'), $media[$mediaField]);
				}
				$row[$mAlias][$modelField] = $media[$mediaField];
			}
		}
	}

/**
 * models method
 *
 * Initialize the model instances on first use, thereafter do nothing
 * If defined, this method will remove any existing hasMany association between the Media model
 * and the MediaLink model. However, if this method is run the user is most likely about to save
 * data and/or is not directly querying the Media model- i.e. it shouldn't have any real world
 * concequence
 *
 * @param mixed $Model
 * @return void
 * @access public
 */
	function setupMediaModels(&$Model) {
		if (!empty($this->settings[$Model->alias]['__modelsInitialized'])) {
			return;
		}
		$model = $this->settings[$Model->alias]['mediaModel'];
		$this->settings[$Model->alias]['MediaModel'] =& ClassRegistry::init($model);
		if (strpos($model, '.')) {
			list($_, $this->settings[$Model->alias]['mediaModel']) =
				explode('.', $this->settings[$Model->alias]['mediaModel']);
		}
		$model = $this->settings[$Model->alias]['linkModel'];
		$this->settings[$Model->alias]['MediaModel']->unbindModel(array(
			'hasMany' => array(
				$model
			)
		), false);
		if (!isset($this->settings[$Model->alias]['MediaModel']->$model)) {
			$this->settings[$Model->alias]['MediaModel']->bindModel(array(
				'hasOne' => array($model)
			), false);
		}
		if (strpos($model, '.')) {
			list($_, $this->settings[$Model->alias]['linkModel']) =
				explode('.', $this->settings[$Model->alias]['linkModel']);
		}
		$this->settings[$Model->alias]['__modelsInitialized'] = true;
	}

/**
 * Loop on the defined MediaFields - if any of them are a file upload - create the media
 * Store/give a relative file path
 *
 * @param mixed $Model
 * @param mixed $data
 * @param mixed $settings
 * @return void
 * @access protected
 */
	function _saveFields(&$Model, &$data, &$settings) {
		$return = array();
		foreach ($settings['fields'] as $alias => $set) {
			if (!isset($data[$Model->alias][$alias]) || !is_array($data[$Model->alias][$alias])) {
				continue;
			}
			$this->setupMediaModels($Model);
			$settings['MediaModel']->create();
			$mediaResult = $settings['MediaModel']->set(array(
				'file' => $data[$Model->alias][$alias]
			));
			if (!$settings['MediaModel']->validates()) {
				$return[$alias] = false;
				continue;
			} elseif (empty($settings['MediaModel']->data[$settings['mediaModel']]['file'])) {
				$data[$Model->alias][$alias] = null;
				continue;
			}
			$mediaResult = $settings['MediaModel']->save();
			if ($mediaResult) {
				$this->settings[$Model->alias]['fields'][$alias]['__id'] = $settings['MediaModel']->id;
				$mediaResult = $settings['MediaModel']->read();
			}
			foreach($set as $field => $modelField) {
				if (strpos($modelField, '.')) {
					list ($mAlias, $modelField) = explode('.', $modelField);
				} else {
					$mAlias = $Model->alias;
				}
				if (empty($mediaResult[$settings['MediaModel']->alias][$field])) {
					$value = null;
				} else {
					$value = $mediaResult[$settings['MediaModel']->alias][$field];
				}
				if ($field === 'file') {
					$value = str_replace($settings['baseDirectory'], '',
						$mediaResult[$settings['MediaModel']->alias]['dirname']);
					$value = str_replace(str_replace(APP, '', $settings['baseDirectory']), '',
						$mediaResult[$settings['MediaModel']->alias]['dirname']);
					$value .= '/' . $mediaResult[$settings['MediaModel']->alias]['basename'];
				}
				$Model->data[$mAlias][$modelField] = $value;
			}
		}
		foreach($return as $result) {
			if (!$result) {
				return false;
			}
		}
		return $data;
	}
}