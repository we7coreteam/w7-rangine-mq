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

class RabbitMQQueue extends RabbitMQQueueAbstract implements QueueInterface {
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
}
