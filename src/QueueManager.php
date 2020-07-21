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

namespace W7\Mq;

use Illuminate\Contracts\Queue\Queue;
use InvalidArgumentException;
use W7\Core\Facades\Context;
use W7\Mq\Consumer\ConsumerAbstract;

/**
 * @mixin \Illuminate\Contracts\Queue\Queue
 */
class QueueManager extends \Illuminate\Queue\QueueManager {
	protected $consumers = [];

	protected function getConfig($name) {
		if (! is_null($name) && $name !== 'null') {
			return $this->app['config']['queue.connections'][$name];
		}

		return ['driver' => 'null'];
	}

	public function addConsumer($driver, \Closure $resolver) {
		$this->consumers[$driver] = $resolver;
	}

	public function getConsumer($name) : ConsumerAbstract {
		$config = $this->getConfig($name);

		if (! isset($this->consumers[$config['driver']])) {
			throw new InvalidArgumentException("No consumer for {$config['driver']}");
		}

		return call_user_func($this->consumers[$config['driver']], $config);
	}

	/**
	 * Resolve a queue connection instance.
	 *
	 * @param  string|null  $name
	 * @return Queue
	 */
	public function connection($name = null) {
		$name = $name ?: $this->getDefaultDriver();
		$contextName = $this->getContextKey($name);
		$connection = Context::getContextDataByKey($contextName);

		if (! $connection instanceof Queue) {
			try {
				/**
				 * @var Queue $connection
				 */
				$connection = $this->resolve($name);
				$connection->setContainer($this->app);
				Context::setContextDataByKey($contextName, $connection);
			} finally {
				if ($connection && isCo()) {
					defer(function () use ($connection, $contextName) {
						$this->releaseConnection($connection);
						Context::setContextDataByKey($contextName, null);
					});
				}
			}
		}

		return $connection;
	}

	private function releaseConnection($connection) {
		return true;
	}

	private function getContextKey($name): string {
		return sprintf('mq.connection.%s', $name);
	}

	public function channel($name = '') {
		return $this->connection($name);
	}
}
