<?php

namespace W7\Mq\Connector;

use W7\Mq\Queue\RedisQueue;

class RedisConnector extends \Illuminate\Queue\Connectors\RedisConnector implements ConnectorInterface {
	/**
	 * Establish a queue connection.
	 *
	 * @param  array  $config
	 * @return \Illuminate\Contracts\Queue\Queue
	 */
	public function connect(array $config) {
		return new RedisQueue(
			$this->redis, $config['queue'],
			$config['connection'] ?? $this->connection,
			$config['retry_after'] ?? 60,
			$config['block_for'] ?? null
		);
	}
}
