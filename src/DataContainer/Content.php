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

use Symfony\Component\HttpFoundation\RequestStack;
use Doctrine\DBAL\Connection;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DataContainer;
use Contao\Input;

class Content
{

	private $requestStack;
	private $connection;
	private $framework;

	public function __construct(RequestStack $requestStack, Connection $connection, ContaoFramework $framework) {
		$this->requestStack = $requestStack;
		$this->connection = $connection;
		$this->framework = $framework;
	}

	public function onload(DataContainer $dc) {
		$session = $this->requestStack->getSession();

		if (!$session->isStarted()) {
			return;
		}

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

		$id = null;
		$inputAdapter = $this->framework->getAdapter(Input::class);
		if ($inputAdapter->get('act') === 'paste') {
			$id = $inputAdapter->get('id');
		}
		$contaoClipboard = $session->get('CLIPBOARD');
		if (
			isset($contaoClipboard[$dc->table]['mode'])
			&& $contaoClipboard[$dc->table]['mode'] === 'cutAll'
			&& \is_array($contaoClipboard[$dc->table]['id'])
		) {
			// Only one ID is needed because the parent is the same for all: It
			// is not possible to "select multiple" across parents. Use the last
			// ID so that setAllWrapperIds() is called only after the last
			// element has been cut.
			$id = \end($contaoClipboard[$dc->table]['id']);
		}
		if ($id !== null) {
			$stmt = $this->connection->prepare('SELECT * FROM tl_content WHERE id = ?');
			$result = $stmt->execute([$id]);
			if (\is_bool($result)) {
				$result = $stmt;
			}
			$element = $result->fetchAssociative();
			$clipboard[$id] = $element['pid'];
			$session->set($clipboardName, $clipboard);
		}
	}

	public function oncreate($table, $insertId, $set, DataContainer $dc) {
		if (empty($GLOBALS['TL_WRAPPERS']['start'])) {
			return;
		}

		$wrapperId = $this->getWrapperId($set['pid'], $set['ptable'], $set['sorting']);
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

		$session = $this->requestStack->getSession();

		if (!$session->isStarted()) {
			return;
		}

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

		$stmt = $this->connection->prepare('SELECT * FROM tl_content WHERE id = ?');
		$result = $stmt->execute([$id]);
		if (\is_bool($result)) {
			$result = $stmt;
		}
		$element = $result->fetchAssociative();
		$wrapperId = $this->getWrapperId($element['pid'], $element['ptable'], $element['sorting']);
		$this->setWrapperId($element['id'], $wrapperId);

		if (
			in_array($element['type'], $GLOBALS['TL_WRAPPERS']['start'])
			|| in_array($element['type'], $GLOBALS['TL_WRAPPERS']['stop'])
		) {
			$this->setAllWrapperIds($element['pid']);
		}
	}

	public function ondelete(DataContainer $dc) {
		if (empty($GLOBALS['TL_WRAPPERS']['start'])) {
			return;
		}

		$stmt = $this->connection->prepare('SELECT * FROM tl_content WHERE id = ?');
		$result = $stmt->execute([$dc->id]);
		if (\is_bool($result)) {
			$result = $stmt;
		}
		$element = $result->fetchAssociative();

		if (
			in_array($element['type'], $GLOBALS['TL_WRAPPERS']['start'])
			|| in_array($element['type'], $GLOBALS['TL_WRAPPERS']['stop'])
		) {
			$this->setAllWrapperIds($element['pid'], $element['id']);
		}
	}


	protected function getWrapperId($pid, $ptable, $sorting, $exclude = null, $include = null) {
		// For deleted wrappers
		$excludeStmt = $exclude === null ? '' : ' AND id != ' . $exclude;
		// For wrappers whose type hasn't been saved yet (onsubmit)
		$includeStmt = $include === null ? '' : ' OR id = ' . $include;
		$arrWrappers = array_merge($GLOBALS['TL_WRAPPERS']['start'], $GLOBALS['TL_WRAPPERS']['stop']);
		$strWrappers = "'".implode("','", $arrWrappers)."'";
		$statement = "SELECT * FROM tl_content WHERE pid = ? AND ptable = ? AND invisible != '1' AND (type IN(".$strWrappers.")".$includeStmt.")".$excludeStmt." AND sorting < ? ORDER BY sorting DESC";
		$stmt = $this->connection->prepare($statement);
		$result = $stmt->execute([$pid, $ptable, $sorting]);
		if (\is_bool($result)) {
			$result = $stmt;
		}
		$wrapperId = 0;
		$level = 0;
		while (($precedingWrapper = $result->fetchAssociative()) !== false && $wrapperId === 0) {
			if (in_array($precedingWrapper['type'], $GLOBALS['TL_WRAPPERS']['start'])) {
				if ($level === 0) {
					$wrapperId = $precedingWrapper['id'];
				}
				// Should be no different from `elseif (level > 0)`
				else {
					$level--;
				}
			}
			elseif (in_array($precedingWrapper['type'], $GLOBALS['TL_WRAPPERS']['stop'])) {
				$level++;
			}
		}

		return $wrapperId;
	}

	protected function setWrapperId($id, $wrapperId) {
		$stmt = $this->connection->prepare('UPDATE tl_content SET wrapperId = ? WHERE id = ?');
		$stmt->execute([$wrapperId, $id]);
	}

	protected function setAllWrapperIds($pid, $exclude = null, $include = null) {
		$stmt = $this->connection->prepare("SELECT * FROM tl_content WHERE pid = ?");
		$result = $stmt->execute([$pid]);
		if (\is_bool($result)) {
			$result = $stmt;
		}
		while (($el = $result->fetchAssociative()) !== false) {
			// No need to update the element that will be deleted anyway
			if ($el['id'] !== $exclude) {
				$wrapperId = $this->getWrapperId($el['pid'], $el['ptable'], $el['sorting'], $exclude, $include);
				$this->setWrapperId($el['id'], $wrapperId);
			}
		}
	}

}
