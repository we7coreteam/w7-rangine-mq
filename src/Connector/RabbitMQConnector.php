<?php

/**
 * WeEngine Api System
 *
 * (c) We7Team 2019 <https://www.w7.cc>
 *
 * This is not a free software
 * Using it under the license terms
 * visited https://www.w7.cc for more details
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
			if (SwooleHelper::checkLoadSwooleExtension(false) && isCo()) {
				$config['connection'] = AMQPSwooleConnection::class;
			}
		}

		return parent::createConnection($config);
	}

	protected function createQueue(string $worker, AbstractConnection $connection, string $queue, bool $dispatchAfterCommit, array $options = []) {
		return new RabbitMQQueue($connection, $queue, $dispatchAfterCommit, $options);
	}
}
