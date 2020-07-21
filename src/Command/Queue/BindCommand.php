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
use W7\Core\Facades\Config;
use W7\Core\Facades\Container;
use W7\Mq\Queue\RabbitMQQueue;
use W7\Mq\QueueManager;

class BindCommand extends CommandAbstract {
	protected $description = 'Bind queue to exchange';

	protected function configure() {
		$this->addOption('--queue', null, InputOption::VALUE_REQUIRED, 'the queue name');
	}

	public function handle($options) {
		/**
		 * @var QueueManager $queueManager
		 */
		$queueManager = Container::singleton('queue');
		$queueConfig = Config::get('queue.queue.' . $options['queue']);
		/**
		 * @var RabbitMQQueue $queue
		 */
		$queue = $queueManager->connection($options['queue']);

		$queue->bindQueue(
			$queueConfig['queue'],
			$queueConfig['options']['queue']['exchange'] ?? '',
			$queue->getRoutingKey($options['queue'])
		);

		$this->output->info('Queue bound to exchange successfully.');
	}
}
