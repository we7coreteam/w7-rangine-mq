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

namespace W7\Mq\Connector;

use PhpAmqpLib\Connection\AbstractConnection;
use Psr\EventDispatcher\EventDispatcherInterface;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Connectors\RabbitMQConnector as RabbitMQConnectorAbstract;
use W7\Mq\Queue\RabbitMQQueue;

class RabbitMQConnector extends RabbitMQConnectorAbstract {
	/**
	 * @var EventDispatcherInterface
	 */
	protected $dispatcher;

	public function __construct(EventDispatcherInterface $dispatcher) {
		$this->dispatcher = $dispatcher;
	}

	/**
	 * @param string $worker
	 * @param AbstractConnection $connection
	 * @param string $queue
	 * @param array $options
	 * @return \Illuminate\Contracts\Queue\Queue|\VladimirYuldashev\LaravelQueueRabbitMQ\Horizon\RabbitMQQueue|\VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue|RabbitMQQueue
	 */
	protected function createQueue(string $worker, AbstractConnection $connection, string $queue, array $options = []) {
		return new RabbitMQQueue($connection, $queue, $options);
	}
}
