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

use Illuminate\Container\Container;
use Illuminate\Queue\Failed\DatabaseFailedJobProvider;
use Illuminate\Queue\Failed\NullFailedJobProvider;
use W7\Core\Database\ConnectionResolver;
use W7\Core\Provider\ProviderAbstract;
use W7\Mq\Connector\DatabaseConnector;
use W7\Mq\Connector\RedisConnector;

class ServiceProvider extends ProviderAbstract {
	public function register() {
		$this->registerManager();
		$this->registerConnection();
		$this->registerFailedJobServices();
	}

	/**
	 * Register the queue manager.
	 *
	 * @return void
	 */
	protected function registerManager() {
		$this->container->set('queue', function () {
			/**
			 * @var Container $container
			 */
			$container = $this->container->get(Container::class);
			$container['config']['queue.default'] = $this->config->get('queue.default', 'default');
			$container['config']['queue.connections'] = $this->config->get('queue.connections', []);

			$manager = new QueueManager($container);
			$this->registerConnectors($manager);

			return $manager;
		});
	}

	/**
	 * Register the default queue connection binding.
	 *
	 * @return void
	 */
	protected function registerConnection() {
		$this->container->set('queue.connection', function () {
			return $this->container->singleton('queue')->connection();
		});
	}

	/**
	 * Register the connectors on the queue manager.
	 *
	 * @param  \Illuminate\Queue\QueueManager  $manager
	 * @return void
	 */
	public function registerConnectors($manager) {
		foreach (['Database', 'Redis'] as $connector) {
			$this->{"register{$connector}Connector"}($manager);
		}
	}

	/**
	 * Register the database queue connector.
	 *
	 * @param  \Illuminate\Queue\QueueManager  $manager
	 * @return void
	 */
	protected function registerDatabaseConnector($manager) {
		$manager->addConnector('database', function () {
			return new DatabaseConnector($this->container->get(ConnectionResolver::class));
		});
	}

	/**
	 * Register the Redis queue connector.
	 *
	 * @param  \Illuminate\Queue\QueueManager  $manager
	 * @return void
	 */
	protected function registerRedisConnector($manager) {
		$manager->addConnector('redis', function () {
			return new RedisConnector($this->app['redis']);
		});
	}

	/**
	 * Register the failed job services.
	 *
	 * @return void
	 */
	protected function registerFailedJobServices() {
		$this->container->set('queue.failer', function () {
			$config = $this->config->get('queue.failed', []);

			if (isset($config['table'])) {
				return $this->databaseFailedJobProvider($config);
			} else {
				return new NullFailedJobProvider;
			}
		});
	}

	/**
	 * Create a new database failed job provider.
	 *
	 * @param  array  $config
	 * @return \Illuminate\Queue\Failed\DatabaseFailedJobProvider
	 */
	protected function databaseFailedJobProvider($config) {
		return new DatabaseFailedJobProvider(
			$this->container->get(ConnectionResolver::class),
			$config['database'],
			$config['table']
		);
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides() {
		return [
			'queue', 'queue.failer', 'queue.connection',
		];
	}
}
