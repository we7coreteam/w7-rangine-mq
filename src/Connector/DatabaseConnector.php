<?php

namespace W7\Mq\Connector;

use W7\Mq\Queue\DatabaseQueue;

class DatabaseConnector extends \Illuminate\Queue\Connectors\DatabaseConnector implements ConnectorInterface {
	/**
	 * Establish a queue connection.
	 *
	 * @param  array  $config
	 * @return \Illuminate\Contracts\Queue\Queue
	 */
	public function connect(array $config) {
		return new DatabaseQueue(
			$this->connections->connection($config['connection'] ?? null),
			$config['table'],
			$config['queue'],
			$config['retry_after'] ?? 60
		);
	}
}
