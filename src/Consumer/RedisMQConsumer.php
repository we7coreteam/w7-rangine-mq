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

namespace W7\Mq\Consumer;

use Illuminate\Queue\WorkerOptions;

class RedisMQConsumer extends ConsumerAbstract {
	/**
	 * @param  string  $connectionName
	 * @param  string  $queue
	 * @param  \Illuminate\Queue\WorkerOptions  $options
	 * @return void
	 */
	public function consume($connectionName, $queue, WorkerOptions $options) {
		itimeTick($options->sleep, function () use ($connectionName, $queue, $options) {
			$this->runNextJob($connectionName, $queue, $options);
		});
	}
}
