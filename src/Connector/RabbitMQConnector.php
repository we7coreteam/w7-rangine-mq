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

use Illuminate\Support\Arr;
use PhpAmqpLib\Connection\AbstractConnection;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Connectors\RabbitMQConnector as RabbitMQConnectorAbstract;
use W7\Core\Helper\Compate\SwooleHelper;
use W7\Mq\Amqp\Connection\AMQPSwooleConnection;
use W7\Mq\Queue\RabbitMQQueue;

class RabbitMQConnector extends RabbitMQConnectorAbstract implements ConnectorInterface {
	protected function createConnection(array $config): AbstractConnection {
		if (!Arr::get($config, 'connection', null)) {
			if (SwooleHelper::checkLoadSwooleExtension(false)) {
				$config['connection'] = AMQPSwooleConnection::class;
			}
		}

		return parent::createConnection($config);
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
