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
use W7\Contract\Queue\QueueFactoryInterface;
use W7\Core\Exception\CommandException;
use W7\Mq\Queue\RabbitMQQueue;
use W7\Mq\QueueManager;

class BindCommand extends CommandAbstract {
	protected $description = 'Bind queue to exchange';

	protected function configure() {
		$this->addOption('--queue', null, InputOption::VALUE_REQUIRED, 'the queue name');
		parent::configure();
	}

	public function handle($options) {
		if (empty($options['queue'])) {
			throw new CommandException('the option queue not be empty');
		}
		/**
		 * @var QueueManager $queueManager
		 */
		$queueManager = $this->getContainer()->singleton(QueueFactoryInterface::class);
		$queueConfig = $this->getConfig()->get('queue.queue.' . $options['queue']);
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
