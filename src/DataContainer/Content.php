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
use Contao\Database;

class Content
{

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
