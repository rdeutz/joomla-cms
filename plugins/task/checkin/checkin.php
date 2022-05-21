<?php
/**
 * @package     Joomla.Plugins
 * @subpackage  Task.Checkin
 *
 * @copyright   (C) 2022 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// Restrict direct access
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status as TaskStatus;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Database\Exception\ExecutionFailureException;
use Joomla\Event\SubscriberInterface;

/**
 * Task plugin with routines to check in check out item.
 *
 * @since  __DEPLOY_VERSION__
 */
class PlgTaskCheckin extends CMSPlugin implements SubscriberInterface
{
	use TaskPluginTrait;

	/**
	 * @var    \Joomla\Database\DatabaseDriver
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	protected $db;

	/**
	 * @var string[]
	 * @since 4.1.0
	 */
	protected const TASKS_MAP = [
		'plg_task_checkin_task_get' => [
			'langConstPrefix' => 'PLG_TASK_CHECKIN',
			'form'            => 'checkin_params',
			'method'          => 'makeCheckin',
		],
	];

	/**
	 * @var boolean
	 * @since __DEPLOY_VERSION__
	 */
	protected $autoloadLanguage = true;

	/**
	 * @inheritDoc
	 *
	 * @return string[]
	 *
	 * @since __DEPLOY_VERSION__
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			'onTaskOptionsList' => 'advertiseRoutines',
			'onExecuteTask'     => 'standardRoutineHandler',
			'onContentPrepareForm' => 'enhanceTaskItemForm',
		];
	}

	/**
	 * Standard routine method for the checkin routine.
	 *
	 * @param   ExecuteTaskEvent  $event  The onExecuteTask event
	 *
	 * @return integer  The exit code
	 *
	 * @since __DEPLOY_VERSION__
	 * @throws Exception
	 */
	protected function makeCheckin(ExecuteTaskEvent $event): int
	{
		$tables = $this->db->getTableList();
		$prefix = Factory::getApplication()->get('dbprefix');
		$delay = (int) $event->getArgument('params')->delay ?? 1;
		$failed = false;

		foreach ($tables as $tn)
		{
			// Make sure we get the right tables based on prefix.
			if (stripos($tn, $prefix) !== 0)
			{
				continue;
			}

			$fields = $this->db->getTableColumns($tn, false);

			if (!(isset($fields['checked_out']) && isset($fields['checked_out_time'])))
			{
				continue;
			}

			$query = $this->db->getQuery(true)
				->update($this->db->quoteName($tn))
				->set($this->db->quoteName('checked_out') . ' = NULL')
				->set($this->db->quoteName('checked_out_time') . ' = NULL')
			;

			if ($fields['checked_out']->Null === 'YES')
			{
				$query->where($this->db->quoteName('checked_out') . ' IS NOT NULL');
			}
			else
			{
				$query->where($this->db->quoteName('checked_out') . ' > 0');
			}

			if ($delay > 0)
			{
				$delayTime = JDate::getInstance()->sub(new DateInterval('PT1H'));
				$query->where(
					$this->db->quoteName('checked_out_time') . ' < ' . $this->db->quote($delayTime)
				);
			}

			$this->db->setQuery($query);

			try
			{
				$this->db->execute();
			}
			catch (ExecutionFailureException $e)
			{
				// This failure isn't critical, don't care too much
				$failed = true;
			}
		}

		return $failed ? TaskStatus::INVALID_EXIT : TaskStatus::OK;
	}
}
