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

class DeclareCommand extends CommandAbstract {
	protected $description = 'Declare queue';

	protected function configure() {
		$this->addOption('--queue', null, InputOption::VALUE_REQUIRED, 'the queue name');
		$this->addOption('--durable', null, InputOption::VALUE_OPTIONAL, 'the queue durable', 1);
		$this->addOption('--auto-delete', null, InputOption::VALUE_OPTIONAL, 'auto delete queue', 0);
		parent::configure();
	}

	public function handle($options) {
		if (empty($options['queue'])) {
			throw new CommandException('the option queue not be empty');
		}

		$queueConfig = $this->getConfig()->get('queue.queue.' . $options['queue']);
		$queueName = $queueConfig['queue'] ?? '';
		if (empty($queueName)) {
			throw new CommandException('queue name config missing, please check the configuration config/queue.php');
		}
		/**
		 * @var QueueManager $queueManager
		 */
		$queueManager = $this->getContainer()->get(QueueFactoryInterface::class);
		/**
		 * @var RabbitMQQueue $queue
		 */
		$queue = $queueManager->connection($options['queue']);

		if ($queue->isQueueExists($queueName)) {
			$this->output->warning('Queue already exists.');

			return;
		}

		$queue->declareQueue(
			$queueName,
			(bool) $options['durable'],
			(bool) $options['auto-delete']
		);

		$this->output->info('Queue declared successfully.');
	}
}
