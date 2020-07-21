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

use W7\Core\Cache\CacheManager;
use W7\Mq\Queue\RedisQueue;

class RedisConnector extends \Illuminate\Queue\Connectors\RedisConnector implements ConnectorInterface {
	/**
	 * Create a new Redis queue connector instance.
	 *
	 * @param  CacheManager  $redis
	 * @param  string|null  $connection
	 * @return void
	 */
	public function __construct($redis, $connection = null) {
		$this->redis = $redis;
		$this->connection = $connection;
	}
	/**
	 * Establish a queue connection.
	 *
	 * @param  array  $config
	 * @return \Illuminate\Contracts\Queue\Queue
	 */
	public function connect(array $config) {
		return new RedisQueue(
			$this->redis,
			$config['queue'],
			$config['connection'] ?? $this->connection,
			$config['retry_after'] ?? 60,
			$config['block_for'] ?? null
		);
	}
}
