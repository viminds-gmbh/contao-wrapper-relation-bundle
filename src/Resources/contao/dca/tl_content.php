<?php

/**
 * Contao Open Source CMS
 *
 * Wrapper Relation Extension by Qbus
 *
 * @author  Alex Wuttke <alw@qbus.de>
 * @license LGPL-3.0+
 */

use Qbus\WrapperRelationBundle\DataContainer\Callback\Content;

/**
 * Callbacks
 */
foreach (['oncreate', 'onsubmit', 'oncut', 'oncopy', 'ondelete'] as $callback) {
	$GLOBALS['TL_DCA']['tl_content']['config'][$callback.'_callback'][] = [
		Content::class,
		$callback
	];
}

/**
 * Fields
 */
$GLOBALS['TL_DCA']['tl_content']['fields']['wrapperId'] = ['sql' => "int(10) unsigned NOT NULL default '0'"];
