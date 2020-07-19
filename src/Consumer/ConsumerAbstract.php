<?php

namespace W7\Mq\Consumer;

use Illuminate\Database\DetectsLostConnections;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Carbon;
use Psr\EventDispatcher\EventDispatcherInterface;
use Swoole\Timer;
use W7\Core\Exception\Handler\ExceptionHandler;
use W7\Mq\QueueManager;

class ConsumerAbstract {
	/**
	 * The queue manager instance.
	 *
	 * @var QueueManager
	 */
	protected $manager;

	/**
	 * The event dispatcher instance.
	 *
	 * @var EventDispatcherInterface
	 */
	protected $events;

	/**
	 * The exception handler instance.
	 *
	 * @var ExceptionHandler
	 */
	protected $exceptions;

	/**
	 * The callback used to determine if the application is in maintenance mode.
	 *
	 * @var callable
	 */
	protected $isDownForMaintenance;

	/**
	 * Create a new queue worker.
	 *
	 * @param  QueueManager  $manager
	 * @param  EventDispatcherInterface  $events
	 * @param  ExceptionHandler  $exceptions
	 * @param  callable  $isDownForMaintenance
	 * @return void
	 */
	public function __construct(QueueManager $manager,
								EventDispatcherInterface $events,
								ExceptionHandler $exceptions,
								callable $isDownForMaintenance)
	{
		$this->events = $events;
		$this->manager = $manager;
		$this->exceptions = $exceptions;
		$this->isDownForMaintenance = $isDownForMaintenance;
	}

	/**
	 * Listen to the given queue in a loop.
	 *
	 * @param  string  $connectionName
	 * @param  string  $queue
	 * @param  \Illuminate\Queue\WorkerOptions  $options
	 * @return void
	 */
	public function daemon($connectionName, $queue, WorkerOptions $options) {
		itimeTick($options->sleep, function () use ($connectionName, $queue, $options) {
			// First, we will attempt to get the next job off of the queue. We will also
			// register the timeout handler and reset the alarm for this job so it is
			// not stuck in a frozen state forever. Then, we can fire off this job.
			$job = $this->getNextJob(
				$this->manager->connection($connectionName), $queue
			);

			$timerId = $this->registerTimeoutHandler($job, $options);

			// If the daemon should run (not in maintenance mode, etc.), then we can run
			// fire off this job for processing. Otherwise, we will need to sleep the
			// worker so no more jobs are processed until they should be processed.
			if ($job) {
				$this->runJob($job, $connectionName, $options);
			}

			$this->resetTimeoutHandler($timerId);
		});
	}

	/**
	 * Register the worker timeout handler.
	 *
	 * @param  \Illuminate\Contracts\Queue\Job|null  $job
	 * @param  \Illuminate\Queue\WorkerOptions  $options
	 * @return void
	 */
	protected function registerTimeoutHandler($job, WorkerOptions $options) {
		return itimeTick(max($this->timeoutForJob($job, $options), 0), function () use ($job, $options) {
			if ($job) {
				$this->markJobAsFailedIfWillExceedMaxAttempts(
					$job->getConnectionName(), $job, (int) $options->maxTries, $this->maxAttemptsExceededException($job)
				);
			}
		});
	}

	/**
	 * Reset the worker timeout handler.
	 * @param $timerId
	 */
	protected function resetTimeoutHandler($timerId) {
		Timer::clear($timerId);
	}

	/**
	 * Get the appropriate timeout for the given job.
	 *
	 * @param  \Illuminate\Contracts\Queue\Job|null  $job
	 * @param  \Illuminate\Queue\WorkerOptions  $options
	 * @return int
	 */
	protected function timeoutForJob($job, WorkerOptions $options) {
		return $job && ! is_null($job->timeout()) ? $job->timeout() : $options->timeout;
	}

	/**
	 * Process the next job on the queue.
	 *
	 * @param  string  $connectionName
	 * @param  string  $queue
	 * @param  \Illuminate\Queue\WorkerOptions  $options
	 * @return void
	 */
	public function runNextJob($connectionName, $queue, WorkerOptions $options) {
		$job = $this->getNextJob(
			$this->manager->connection($connectionName), $queue
		);

		// If we're able to pull a job off of the stack, we will process it and then return
		// from this method. If there is no job on the queue, we will "sleep" the worker
		// for the specified number of seconds, then keep processing jobs after sleep.
		if ($job) {
			return $this->runJob($job, $connectionName, $options);
		}
	}

	/**
	 * Get the next job from the queue connection.
	 *
	 * @param  \Illuminate\Contracts\Queue\Queue  $connection
	 * @param  string  $queue
	 * @return \Illuminate\Contracts\Queue\Job|null
	 */
	protected function getNextJob($connection, $queue){
		try {
			foreach (explode(',', $queue) as $queue) {
				if (! is_null($job = $connection->pop($queue))) {
					return $job;
				}
			}
		} catch (\Throwable $e) {
			$this->exceptions->report($e);
		}
	}

	/**
	 * Process the given job.
	 *
	 * @param  \Illuminate\Contracts\Queue\Job  $job
	 * @param  string  $connectionName
	 * @param  \Illuminate\Queue\WorkerOptions  $options
	 * @return void
	 */
	protected function runJob($job, $connectionName, WorkerOptions $options){
		try {
			return $this->process($connectionName, $job, $options);
		} catch (\Throwable $e) {
			$this->exceptions->report($e);
		}
	}

	/**
	 * Process the given job from the queue.
	 *
	 * @param  string  $connectionName
	 * @param  \Illuminate\Contracts\Queue\Job  $job
	 * @param  \Illuminate\Queue\WorkerOptions  $options
	 * @return void
	 *
	 * @throws \Throwable
	 */
	public function process($connectionName, $job, WorkerOptions $options){
		try {
			// First we will raise the before job event and determine if the job has already ran
			// over its maximum attempt limits, which could primarily happen when this job is
			// continually timing out and not actually throwing any exceptions from itself.
			$this->raiseBeforeJobEvent($connectionName, $job);

			$this->markJobAsFailedIfAlreadyExceedsMaxAttempts(
				$connectionName, $job, (int) $options->maxTries
			);

			if ($job->isDeleted()) {
				return $this->raiseAfterJobEvent($connectionName, $job);
			}

			// Here we will fire off the job and let it process. We will catch any exceptions so
			// they can be reported to the developers logs, etc. Once the job is finished the
			// proper events will be fired to let any listeners know this job has finished.
			$job->fire();

			$this->raiseAfterJobEvent($connectionName, $job);
		} catch (\Exception $e) {
			$this->handleJobException($connectionName, $job, $options, $e);
		} catch (\Throwable $e) {
			$this->handleJobException(
				$connectionName, $job, $options, $e
			);
		}
	}

	/**
	 * Handle an exception that occurred while the job was running.
	 *
	 * @param  string  $connectionName
	 * @param  \Illuminate\Contracts\Queue\Job  $job
	 * @param  \Illuminate\Queue\WorkerOptions  $options
	 * @param  \Exception  $e
	 * @return void
	 *
	 * @throws \Exception
	 */
	protected function handleJobException($connectionName, $job, WorkerOptions $options, $e) {
		try {
			// First, we will go ahead and mark the job as failed if it will exceed the maximum
			// attempts it is allowed to run the next time we process it. If so we will just
			// go ahead and mark it as failed now so we do not have to release this again.
			if (! $job->hasFailed()) {
				$this->markJobAsFailedIfWillExceedMaxAttempts(
					$connectionName, $job, (int) $options->maxTries, $e
				);
			}

			$this->raiseExceptionOccurredJobEvent(
				$connectionName, $job, $e
			);
		} finally {
			// If we catch an exception, we will attempt to release the job back onto the queue
			// so it is not lost entirely. This'll let the job be retried at a later time by
			// another listener (or this same one). We will re-throw this exception after.
			if (! $job->isDeleted() && ! $job->isReleased() && ! $job->hasFailed()) {
				$job->release(
					method_exists($job, 'delaySeconds') && ! is_null($job->delaySeconds())
						? $job->delaySeconds()
						: $options->delay
				);
			}
		}

		throw $e;
	}

	/**
	 * Mark the given job as failed if it has exceeded the maximum allowed attempts.
	 *
	 * This will likely be because the job previously exceeded a timeout.
	 *
	 * @param  string  $connectionName
	 * @param  \Illuminate\Contracts\Queue\Job  $job
	 * @param  int  $maxTries
	 * @return void
	 */
	protected function markJobAsFailedIfAlreadyExceedsMaxAttempts($connectionName, $job, $maxTries) {
		$maxTries = ! is_null($job->maxTries()) ? $job->maxTries() : $maxTries;

		$timeoutAt = $job->timeoutAt();

		if ($timeoutAt && Carbon::now()->getTimestamp() <= $timeoutAt) {
			return;
		}

		if (! $timeoutAt && ($maxTries === 0 || $job->attempts() <= $maxTries)) {
			return;
		}

		$this->failJob($job, $e = $this->maxAttemptsExceededException($job));

		throw $e;
	}

	/**
	 * Mark the given job as failed if it has exceeded the maximum allowed attempts.
	 *
	 * @param  string  $connectionName
	 * @param  \Illuminate\Contracts\Queue\Job  $job
	 * @param  int  $maxTries
	 * @param  \Exception  $e
	 * @return void
	 */
	protected function markJobAsFailedIfWillExceedMaxAttempts($connectionName, $job, $maxTries, $e) {
		$maxTries = ! is_null($job->maxTries()) ? $job->maxTries() : $maxTries;

		if ($job->timeoutAt() && $job->timeoutAt() <= Carbon::now()->getTimestamp()) {
			$this->failJob($job, $e);
		}

		if ($maxTries > 0 && $job->attempts() >= $maxTries) {
			$this->failJob($job, $e);
		}
	}

	/**
	 * Mark the given job as failed and raise the relevant event.
	 *
	 * @param  \Illuminate\Contracts\Queue\Job  $job
	 * @param  \Exception  $e
	 * @return void
	 */
	protected function failJob($job, $e) {
		return $job->fail($e);
	}

	/**
	 * Raise the before queue job event.
	 *
	 * @param  string  $connectionName
	 * @param  \Illuminate\Contracts\Queue\Job  $job
	 * @return void
	 */
	protected function raiseBeforeJobEvent($connectionName, $job) {
		$this->events->dispatch(new JobProcessing(
			$connectionName, $job
		));
	}

	/**
	 * Raise the after queue job event.
	 *
	 * @param  string  $connectionName
	 * @param  \Illuminate\Contracts\Queue\Job  $job
	 * @return void
	 */
	protected function raiseAfterJobEvent($connectionName, $job) {
		$this->events->dispatch(new JobProcessed(
			$connectionName, $job
		));
	}

	/**
	 * Raise the exception occurred queue job event.
	 *
	 * @param  string  $connectionName
	 * @param  \Illuminate\Contracts\Queue\Job  $job
	 * @param  \Exception  $e
	 * @return void
	 */
	protected function raiseExceptionOccurredJobEvent($connectionName, $job, $e) {
		$this->events->dispatch(new JobExceptionOccurred(
			$connectionName, $job, $e
		));
	}

	/**
	 * Create an instance of MaxAttemptsExceededException.
	 *
	 * @param  \Illuminate\Contracts\Queue\Job|null  $job
	 * @return \Illuminate\Queue\MaxAttemptsExceededException
	 */
	protected function maxAttemptsExceededException($job) {
		return new MaxAttemptsExceededException(
			$job->resolveName().' has been attempted too many times or run too long. The job may have previously timed out.'
		);
	}

	/**
	 * Get the queue manager instance.
	 *
	 * @return QueueManager
	 */
	public function getManager()
	{
		return $this->manager;
	}

	/**
	 * Set the queue manager instance.
	 *
	 * @param  QueueManager  $manager
	 * @return void
	 */
	public function setManager(QueueManager $manager)
	{
		$this->manager = $manager;
	}
}