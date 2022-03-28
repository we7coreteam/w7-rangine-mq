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

namespace W7\Mq\Queue;

use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue as RabbitMQQueueAbstract;
use W7\Contract\Queue\QueueInterface;

//todo 需要考虑任务发布是否一定到达的问题，事物或者confirm模式
class RabbitMQQueue extends RabbitMQQueueAbstract implements QueueInterface {
	protected $defaultHandler;

	/**
	 * Get the routing-key for when you use exchanges
	 * The default routing-key is the given destination.
	 *
	 * @param string $destination
	 * @return string
	 */
	public function getRoutingKey(string $destination): string {
		return parent::getRoutingKey($destination);
	}

	public function getExchange(string $exchange = null): ?string {
		return parent::getExchange($exchange);
	}

	protected function createPayloadArray($job, $queue, $data = ''): array {
		return is_object($job)
			? $this->createObjectPayload($job, $queue)
			: $this->createStringPayload($job, $queue, $data);
	}

	public function releaseConnection() {
		$this->close();
	}
}
