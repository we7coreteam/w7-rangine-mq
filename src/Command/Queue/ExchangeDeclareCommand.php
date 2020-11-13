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

class ExchangeDeclareCommand extends CommandAbstract {
	protected $description = 'Declare exchange';

	protected function configure() {
		$this->addOption('--queue', null, InputOption::VALUE_REQUIRED, 'the queue name');
		$this->addOption('--type', null, InputOption::VALUE_OPTIONAL, 'the exchange type', 'direct');
		$this->addOption('--durable', null, InputOption::VALUE_OPTIONAL, 'the queue durable', 1);
		$this->addOption('--auto-delete', null, InputOption::VALUE_OPTIONAL, 'auto delete queue', 0);
		parent::configure();
	}

	public function handle($options) {
		if (empty($options['queue'])) {
			throw new CommandException('the option queue not be empty');
		}

		$queueConfig = $this->getConfig()->get('queue.queue.' . $options['queue']);
		$exchange = $queueConfig['options']['queue']['exchange'] ?? '';
		if (empty($exchange)) {
			throw new CommandException('queue exchange config missing, please check the configuration config/queue.php');
		}
		/**
		 * @var QueueManager $queueManager
		 */
		$queueManager = $this->getContainer()->singleton(QueueFactoryInterface::class);
		/**
		 * @var RabbitMQQueue $queue
		 */
		$queue = $queueManager->connection($options['queue']);
		if ($queue->isExchangeExists($exchange)) {
			$this->output->warning('Exchange already exists.');

			return;
		}

		$queue->declareExchange(
			$exchange,
			$this->option('type'),
			(bool) $options['durable'],
			(bool) $options['auto-delete']
		);

		$this->output->info('Exchange declared successfully.');
	}
}
