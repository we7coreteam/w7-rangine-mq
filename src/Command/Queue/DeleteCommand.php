<?php

/**
 * Rangine MQ
 *
 * (c) We7Team 2019 <https://www.rangine.com>
 *
 * document http://s.w7.cc/index.php?c=wiki&do=view&id=317&list=2284
 *
 * visited https://www.rangine.com for more details
 */

namespace W7\Mq\Command\Queue;

use Symfony\Component\Console\Input\InputOption;
use W7\Console\Command\CommandAbstract;
use W7\Core\Exception\CommandException;
use W7\Core\Facades\Config;
use W7\Core\Facades\Container;
use W7\Mq\Queue\RabbitMQQueue;
use W7\Mq\QueueManager;

class DeleteCommand extends CommandAbstract {
	protected $description = 'Declare queue';

	protected function configure() {
		$this->addOption('--queue', null, InputOption::VALUE_REQUIRED, 'the queue name');
		$this->addOption('--unused', null, InputOption::VALUE_OPTIONAL, 'check if queue has no consumers', 0);
		$this->addOption('--empty', null, InputOption::VALUE_OPTIONAL, 'check if queue is empty', 0);
		parent::configure();
	}

	public function handle($options) {
		if (empty($options['queue'])) {
			throw new CommandException('the option queue not be empty');
		}

		$queueConfig = Config::get('queue.queue.' . $options['queue']);
		$queueName = $queueConfig['queue'] ?? '';
		if (empty($queueName)) {
			throw new CommandException('queue name config missing, please check the configuration config/queue.php');
		}
		/**
		 * @var QueueManager $queueManager
		 */
		$queueManager = Container::singleton('queue');
		/**
		 * @var RabbitMQQueue $queue
		 */
		$queue = $queueManager->connection($options['queue']);

		if (! $queue->isQueueExists($queueName)) {
			$this->output->warning('Queue does not exist.');

			return;
		}

		$queue->deleteQueue(
			$queueName,
			(bool) $options['unused'],
			(bool) $options['empty']
		);

		$this->output->info('Queue deleted successfully.');
	}
}