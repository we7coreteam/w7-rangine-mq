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

namespace W7\Mq\Process;

use Illuminate\Queue\WorkerOptions;
use Swoole\Process;
use W7\Contract\Queue\QueueFactoryInterface;
use W7\Core\Process\ProcessAbstract;
use W7\Mq\QueueManager;

class ConsumerProcess extends ProcessAbstract {
	public function check() {
		return true;
	}

	public function getProcessName() {
		return parent::getProcessName() . ' mq';
	}

	protected function run(Process $process) {
		/**
		 * @var QueueManager $queueManager
		 */
		$queueManager = $this->getContainer()->get(QueueFactoryInterface::class);
		$config = $this->getConfig()->get('queue.queue.' . $this->getName());
		$consumer = $queueManager->getConsumer($this->getName());
		$consumer->setProcess($process);

		$consumer->consume($this->getName(), $config['queue'], new WorkerOptions(
			$config['queue'],
			$config['delay'] ?? 0,
			$config['memory'] ?? 128,
			$config['timeout'] ?? 60,
			$config['sleep'] ?? 3,
			$config['tries'] ?? 1,
			$config['force'] ?? false,
			$config['stop_after_empty'] ?? false
		));
	}
}
