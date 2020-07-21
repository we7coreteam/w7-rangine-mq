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

namespace W7\Mq\Consumer;

use Illuminate\Container\Container;
use Illuminate\Queue\WorkerOptions;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Jobs\RabbitMQJob;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

class RabbitMQConsumer extends ConsumerAbstract {
	/** @var Container */
	protected $container;

	/** @var string */
	protected $consumerTag;

	/** @var int */
	protected $prefetchSize;

	/** @var int */
	protected $prefetchCount;

	/** @var AMQPChannel */
	protected $channel;

	/** @var bool */
	protected $gotJob = false;

	public function setContainer(Container $value): void {
		$this->container = $value;
	}

	public function setConsumerTag(string $value): void {
		$this->consumerTag = $value;
	}

	public function setPrefetchSize(int $value): void {
		$this->prefetchSize = $value;
	}

	public function setPrefetchCount(int $value): void {
		$this->prefetchCount = $value;
	}

	public function consume($connectionName, $queue, WorkerOptions $options): void {
		/** @var RabbitMQQueue $connection */
		$connection = $this->manager->connection($connectionName);

		$this->channel = $connection->getChannel();

		$this->channel->basic_qos(
			$this->prefetchSize,
			$this->prefetchCount,
			null
		);

		$this->channel->basic_consume(
			$queue,
			$this->consumerTag,
			false,
			false,
			false,
			false,
			function (AMQPMessage $message) use ($connection, $options, $connectionName, $queue): void {
				$this->gotJob = true;

				$job = new RabbitMQJob(
					$this->container,
					$connection,
					$message,
					$connectionName,
					$queue
				);

				if (!$job) {
					return;
				}

				$timerId = $this->registerTimeoutHandler($job, $options);

				// If we're able to pull a job off of the stack, we will process it and then return
				// from this method. If there is no job on the queue, we will "sleep" the worker
				// for the specified number of seconds, then keep processing jobs after sleep.
				if ($job) {
					$this->runJob($job, $connectionName, $options);
				}

				$this->resetTimeoutHandler($timerId);
			}
		);

		while ($this->channel->is_consuming()) {
			try {
				$this->channel->wait();
			} catch (Throwable $exception) {
				$this->exceptions->report($exception);
			}
		}
	}
}
