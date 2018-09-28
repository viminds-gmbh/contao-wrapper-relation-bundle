<?php

/**
 * Contao Open Source CMS
 *
 * Wrapper Relation Extension by Qbus
 *
 * @author  Alex Wuttke <alw@qbus.de>
 * @license LGPL-3.0+
 */

/**
 * Callbacks
 */
foreach (['onload', 'oncreate', 'onsubmit', 'oncut', 'oncopy', 'ondelete'] as $callback) {
	$GLOBALS['TL_DCA']['tl_content']['config'][$callback.'_callback'][] = [
		'qbus_wrapper_relation.data_container.content',
		$callback
	];
}

/**
 * Fields
 */
$GLOBALS['TL_DCA']['tl_content']['fields']['wrapperId'] = ['sql' => "int(10) unsigned NOT NULL default '0'"];
