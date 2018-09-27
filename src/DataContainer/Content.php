<?php

/**
 * Contao Open Source CMS
 *
 * Wrapper Relation Extension by Qbus
 *
 * @author  Alex Wuttke <alw@qbus.de>
 * @license LGPL-3.0+
 */

namespace Qbus\WrapperRelationBundle\DataContainer;

use Contao\DataContainer;
use Contao\Input;
use Contao\Database;
use Contao\System;

class Content
{

	public function onload(DataContainer $dc) {
		$session = System::getContainer()->get('session');
		if (isset($_GET['clipboard'])) {
			$session->set('QBUS_WRAPPER_RELATION_CLIPBOARD', []);
		}

		// A separate clipboard is needed because Contao's CLIPBOARD is cleared
		// before the oncut_callback is called.
		$clipboardName = 'QBUS_WRAPPER_RELATION_CLIPBOARD';
		if (!$session->has($clipboardName)) {
			$session->set($clipboardName, []);
		}
		$clipboard = $session->get($clipboardName);

		$db = Database::getInstance();

		if (Input::get('act') === 'paste') {
			$id = Input::get('id');
			$element = $db->prepare('SELECT * FROM tl_content WHERE id = ?')->execute($id);
			$clipboard[$id] = $element->pid;
			$session->set($clipboardName, $clipboard);
		}

		$contaoClipboard = $session->get('CLIPBOARD');
		if (
			$contaoClipboard[$dc->table]['mode'] === 'cutAll'
			&& \is_array($contaoClipboard[$dc->table]['id'])
		) {
			// Only one ID is needed because the parent is the same for all: It
			// is not possible to "select multiple" across parents. Use the last
			// ID so that setAllWrapperIds() is called only after the last
			// element has been cut.
			$lastId = \end($contaoClipboard[$dc->table]['id']);
			$element = $db->prepare('SELECT * FROM tl_content WHERE id = ?')->execute($lastId);
			$clipboard[$lastId] = $element->pid;
			$session->set($clipboardName, $clipboard);
		}
	}

	public function oncreate($table, $insertId, $set, DataContainer $dc) {
		if (empty($GLOBALS['TL_WRAPPERS']['start'])) {
			return;
		}

		$wrapperId = $this->getWrapperId($set['pid'], $set['sorting']);
		$this->setWrapperId($insertId, $wrapperId);

		// This usually doesn't do anything because by default, the type at
		// creation is always "text".
		if (
			in_array($set['type'], $GLOBALS['TL_WRAPPERS']['start'])
			|| in_array($set['type'], $GLOBALS['TL_WRAPPERS']['stop'])
		) {
			$this->setAllWrapperIds($set['pid']);
		}
	}

	public function onsubmit(DataContainer $dc = null) {
		if ($dc === null || empty($GLOBALS['TL_WRAPPERS']['start'])) {
			return;
		}

		$type = $dc->activeRecord->type;
		$pid = $dc->activeRecord->pid;
		if (
			in_array($type, $GLOBALS['TL_WRAPPERS']['start'])
			|| in_array($type, $GLOBALS['TL_WRAPPERS']['stop'])
		) {
			$this->setAllWrapperIds($pid, null, $dc->id);
		}
	}

	public function oncut(DataContainer $dc) {
		if (empty($GLOBALS['TL_WRAPPERS']['start'])) {
			return;
		}

		$session = System::getContainer()->get('session');
		$clipboardName = 'QBUS_WRAPPER_RELATION_CLIPBOARD';
		$clipboard = $session->get($clipboardName);
		if (isset($clipboard[$dc->id])) {
			$this->setAllWrapperIds($clipboard[$dc->id]);
			unset($clipboard[$dc->id]);
			$session->set($clipboardName, $clipboard);
		}
		$this->onCutOrCopy($dc->id);
	}

	public function oncopy($insertId, DataContainer $dc) {
		$this->onCutOrCopy($insertId);
	}

	public function onCutOrCopy($id) {
		if (empty($GLOBALS['TL_WRAPPERS']['start'])) {
			return;
		}

		$db = Database::getInstance();
		$element = $db->prepare("SELECT * FROM tl_content WHERE id = ?")->execute($id);
		$wrapperId = $this->getWrapperId($element->pid, $element->sorting);
		$this->setWrapperId($element->id, $wrapperId);

		if (
			in_array($element->type, $GLOBALS['TL_WRAPPERS']['start'])
			|| in_array($element->type, $GLOBALS['TL_WRAPPERS']['stop'])
		) {
			$this->setAllWrapperIds($element->pid);
		}
	}

	public function ondelete(DataContainer $dc) {
		if (empty($GLOBALS['TL_WRAPPERS']['start'])) {
			return;
		}

		$db = Database::getInstance();
		$element = $db->prepare("SELECT * FROM tl_content WHERE id = ?")->execute($dc->id);

		if (
			in_array($element->type, $GLOBALS['TL_WRAPPERS']['start'])
			|| in_array($element->type, $GLOBALS['TL_WRAPPERS']['stop'])
		) {
			$this->setAllWrapperIds($element->pid, $element->id);
		}
	}


	protected function getWrapperId($pid, $sorting, $exclude = null, $include = null) {
		// For deleted wrappers
		$excludeStmt = $exclude === null ? '' : ' AND id != ' . $exclude;
		// For wrappers whose type hasn't been saved yet (onsubmit)
		$includeStmt = $include === null ? '' : ' OR id = ' . $include;
		$arrWrappers = array_merge($GLOBALS['TL_WRAPPERS']['start'], $GLOBALS['TL_WRAPPERS']['stop']);
		$strWrappers = "'".implode("','", $arrWrappers)."'";
		$statement = "SELECT * FROM tl_content WHERE pid = ? AND (type IN(".$strWrappers.")".$includeStmt.")".$excludeStmt." AND sorting < ? ORDER BY sorting DESC";
		$db = Database::getInstance();
		$precedingWrapper = $db->prepare($statement)->execute($pid, $sorting);
		$wrapperId = 0;
		$level = 0;
		while ($precedingWrapper->next() && $wrapperId === 0) {
			if (in_array($precedingWrapper->type, $GLOBALS['TL_WRAPPERS']['start'])) {
				if ($level === 0) {
					$wrapperId = $precedingWrapper->id;
				}
				// Should be no different from `elseif (level > 0)`
				else {
					$level--;
				}
			}
			elseif (in_array($precedingWrapper->type, $GLOBALS['TL_WRAPPERS']['stop'])) {
				$level++;
			}
		}

		return $wrapperId;
	}

	protected function setWrapperId($id, $wrapperId) {
		$db = Database::getInstance();
		$db->prepare('UPDATE tl_content SET wrapperId = ? WHERE id = ?')->execute($wrapperId, $id);
	}

	protected function setAllWrapperIds($pid, $exclude = null, $include = null) {
		$db = Database::getInstance();
		$el = $db->prepare("SELECT * FROM tl_content WHERE pid = ?")->execute($pid);
		while ($el->next()) {
			// No need to update the element that will be deleted anyway
			if ($el->id !== $exclude) {
				$wrapperId = $this->getWrapperId($el->pid, $el->sorting, $exclude, $include);
				$this->setWrapperId($el->id, $wrapperId);
			}
		}
	}

}
