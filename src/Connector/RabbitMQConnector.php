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
use W7\Mq\Amqp\Connection\AMQPSwooleConnection;
use W7\Mq\Queue\RabbitMQQueue;

class RabbitMQConnector extends RabbitMQConnectorAbstract implements ConnectorInterface {
	protected function createConnection(array $config): AbstractConnection {
		if (!Arr::get($config, 'connection', null)) {
			static $hasLoadSwooleExtension = true;
			if ($hasLoadSwooleExtension && extension_loaded('swoole') && version_compare(SWOOLE_VERSION, '4.4.0', '>=')) {
				$config['connection'] = AMQPSwooleConnection::class;
			} else {
				$hasLoadSwooleExtension = false;
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
