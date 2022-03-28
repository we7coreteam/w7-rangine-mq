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

use InvalidArgumentException;
use Swoole\Coroutine;
use W7\Contract\Queue\QueueFactoryInterface;
use W7\Contract\Queue\QueueInterface;
use W7\Core\Helper\Traiter\AppCommonTrait;
use W7\Mq\Consumer\ConsumerAbstract;

/**
 * @mixin QueueInterface
 */
class QueueManager extends \Illuminate\Queue\QueueManager implements QueueFactoryInterface {
	use AppCommonTrait;

	protected $consumers = [];

	protected function getConfig($name) {
		if (! is_null($name) && $name !== 'null') {
			if (empty($this->app['config']['queue.connections'][$name])) {
				throw new \RuntimeException('queue connection ' . $name . ' not support, please check the configuration file at config/queue.php');
			}
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

	public function connection($name = null): QueueInterface {
		$name = $name ?: $this->getDefaultDriver();
		$contextName = $this->getContextKey($name);
		$connection = $this->getContext()->getContextDataByKey($contextName);

		if (! $connection instanceof QueueInterface) {
			try {
				/**
				 * @var QueueInterface $connection
				 */
				$connection = $this->resolve($name);
				if (method_exists($connection, 'setContainer')) {
					$connection->setContainer($this->app);
				}

				$this->getContext()->setContextDataByKey($contextName, $connection);
			} finally {
				if ($connection && isCo()) {
					Coroutine::defer(function () use ($connection, $contextName) {
						$this->releaseConnection($connection);
						$this->getContext()->setContextDataByKey($contextName, null);
					});
				}
			}
		}

		return $connection;
	}

	private function releaseConnection($connection) {
		if (method_exists($connection, 'releaseConnection')) {
			$connection->releaseConnection();
		}
		return true;
	}

	private function getContextKey($name): string {
		return sprintf('mq.connection.%s', $name);
	}

	public function channel($name = '') : QueueInterface {
		return $this->connection($name);
	}
}
