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
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Failed\DatabaseFailedJobProvider;
use Illuminate\Queue\Failed\NullFailedJobProvider;
use W7\App;
use W7\Core\Database\ConnectionResolver;
use W7\Core\Events\Dispatcher;
use W7\Core\Exception\HandlerExceptions;
use W7\Core\Facades\Event;
use W7\Core\Provider\ProviderAbstract;
use W7\Core\Server\ServerEnum;
use W7\Core\Server\ServerEvent;
use W7\Mq\Connector\RabbitMQConnector;
use W7\Mq\Consumer\RabbitMQConsumer;
use W7\Mq\Event\JobExceptionOccurredEvent;
use W7\Mq\Event\JobFailedEvent;
use W7\Mq\Event\JobProcessedEvent;
use W7\Mq\Event\JobProcessingEvent;
use W7\Mq\Server\Server;

class ServiceProvider extends ProviderAbstract {
	public function register() {
		$this->registerServer('queue', Server::class);
		/**
		 * @var ServerEvent $event
		 */
		$event = $this->container->singleton(ServerEvent::class);
		$this->registerServerEvent('queue', $event->getDefaultEvent()[ServerEnum::TYPE_PROCESS]);

		$this->registerCommand();

		$this->registerManager();
		$this->registerConnection();
		$this->registerFailedJobServices();
		$this->registerEventListener();
	}

	/**
	 * Register the queue manager.
	 *
	 * @return void
	 */
	protected function registerManager() {
		$this->container->set('queue', function () {
			$queueConfig = $this->config->get('queue.queue', []);
			/**
			 * @var Container $container
			 */
			$container = $this->container->get(Container::class);
			$container['config']['queue.default'] = $this->config->get('queue.default', 'rabbit_mq');
			$container['config']['queue.connections'] = $queueConfig;

			$manager = new QueueManager($container);
			$this->registerConnectorAndConsumer($manager);

			$container->singleton(\Illuminate\Contracts\Events\Dispatcher::class, function () {
				return $this->container->get(Dispatcher::class);
			});
			$container->singleton(\Illuminate\Contracts\Bus\Dispatcher::class, function () use ($container, $manager) {
				return new \Illuminate\Bus\Dispatcher($container, function ($connection = null) use ($manager) {
					return $manager->connection($connection);
				});
			});

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
	 * @param  QueueManager  $manager
	 * @return void
	 */
	public function registerConnectorAndConsumer($manager) {
		foreach (['Rabbit'] as $connector) {
			$this->{"register{$connector}ConnectorAndConsumer"}($manager);
		}
	}

	/**
	 * Register the Redis queue connector.
	 *
	 * @param  QueueManager  $manager
	 * @return void
	 */
	protected function registerRabbitConnectorAndConsumer($manager) {
		$manager->addConnector('rabbit_mq', function () {
			return new RabbitMQConnector($this->container->singleton(Dispatcher::class));
		});
		$manager->addConsumer('rabbit_mq', function ($options = []) use ($manager) {
			$consumer = new RabbitMQConsumer($manager, $this->container->singleton(Dispatcher::class), $this->container->singleton(HandlerExceptions::class)->getHandler());
			$consumer->setContainer($this->container->singleton(Container::class));
			$consumer->setConsumerTag($options['customer_tag'] ?? (App::NAME . '_' . microtime(true) . '_' . getmypid()));
			$consumer->setPrefetchCount($options['prefetch_count'] ?? 0);
			$consumer->setPrefetchSize($options['prefetch_size'] ?? 0);

			return $consumer;
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

		/**
		 * @var Container $container
		 */
		$container = $this->container->get(Container::class);
		$container->singleton('queue.failer', function () {
			return $this->container->singleton('queue.failer');
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
	 * Listen for the queue events in order to update the console output.
	 *
	 * @return void
	 */
	protected function registerEventListener() {
		$this->registerEvent(JobFailed::class, function ($event) {
			/**
			 * @var JobFailed $event
			 */
			Event::dispatch(new JobFailedEvent($event->connectionName, $event->job, $event->exception));

			$this->logFailedJob($event);
		});
		$this->registerEvent(JobProcessing::class, function ($event) {
			/**
			 * @var JobProcessing $event
			 */
			Event::dispatch(new JobProcessingEvent($event->connectionName, $event->job));
		});
		$this->registerEvent(JobProcessed::class, function ($event) {
			/**
			 * @var JobProcessed $event
			 */
			Event::dispatch(new JobProcessedEvent($event->connectionName, $event->job));
		});
		$this->registerEvent(JobExceptionOccurred::class, function ($event) {
			/**
			 * @var JobExceptionOccurred $event
			 */
			Event::dispatch(new JobExceptionOccurredEvent($event->connectionName, $event->job, $event->exception));
		});
	}

	/**
	 * Store a failed job event.
	 *
	 * @param  \Illuminate\Queue\Events\JobFailed  $event
	 * @return void
	 */
	protected function logFailedJob(JobFailed $event) {
		$this->container->singleton('queue.failer')->log(
			$event->connectionName,
			$event->job->getQueue(),
			$event->job->getRawBody(),
			$event->exception
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
