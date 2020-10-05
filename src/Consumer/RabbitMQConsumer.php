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
use W7\Mq\Queue\RabbitMQQueue;

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

		//Direct模式,可以使用rabbitMQ自带的Exchange：default Exchange 。所以不需要将Exchange进行任何绑定(binding)操作 。消息传递时，RouteKey必须完全匹配，才会被队列接收，否则该消息会被抛弃
		//如果声明了exchange,需要进行绑定,Fanout模式不需要
		if ($connection->getExchange()) {
			$connection->bindQueue($queue, $connection->getExchange(), $connection->getRoutingKey($queue));
		}

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
				$this->stopIfNecessary($options, time());
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
