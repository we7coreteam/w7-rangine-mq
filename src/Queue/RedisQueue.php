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

namespace W7\Mq\Queue;

use Illuminate\Queue\LuaScripts;
use Psr\SimpleCache\CacheInterface;
use W7\Core\Cache\CacheManager;

class RedisQueue extends \Illuminate\Queue\RedisQueue implements QueueInterface {
	/**
	 * The Redis factory implementation.
	 *
	 * @var CacheManager
	 */
	protected $redis;

	/**
	 * Create a new Redis queue instance.
	 *
	 * @param  CacheManager $redis
	 * @param  string  $default
	 * @param  string|null  $connection
	 * @param  int  $retryAfter
	 * @param  int|null  $blockFor
	 * @return void
	 */
	public function __construct($redis, $default = 'default', $connection = null, $retryAfter = 60, $blockFor = null) {
		$this->redis = $redis;
		$this->default = $default;
		$this->blockFor = $blockFor;
		$this->connection = $connection;
		$this->retryAfter = $retryAfter;
	}

	/**
	 * Get the connection for the queue.
	 *
	 * @return CacheInterface
	 */
	public function getConnection() {
		return $this->redis->channel($this->connection);
	}

	public function size($queue = null) {
		$queue = $this->getQueue($queue);

		return $this->getConnection()->eval(
			LuaScripts::size(),
			[$queue, $queue.':delayed', $queue.':reserved'],
			3
		);
	}

	/**
	 * Push a raw payload onto the queue.
	 *
	 * @param  string  $payload
	 * @param  string|null  $queue
	 * @param  array  $options
	 * @return mixed
	 */
	public function pushRaw($payload, $queue = null, array $options = []) {
		$this->getConnection()->eval(
			LuaScripts::push(),
			[$this->getQueue($queue),
			$this->getQueue($queue).':notify', $payload],
			2
		);

		return json_decode($payload, true)['id'] ?? null;
	}

	/**
	 * Migrate the delayed jobs that are ready to the regular queue.
	 *
	 * @param  string  $from
	 * @param  string  $to
	 * @return array
	 */
	public function migrateExpiredJobs($from, $to) {
		return $this->getConnection()->eval(
			LuaScripts::migrateExpiredJobs(),
			[$from, $to, $to.':notify', $this->currentTime()],
			3
		);
	}

	/**
	 * Retrieve the next job from the queue.
	 *
	 * @param  string  $queue
	 * @param  bool  $block
	 * @return array
	 */
	protected function retrieveNextJob($queue, $block = true) {
		$nextJob = $this->getConnection()->eval(
			LuaScripts::pop(),
			[$queue, $queue.':reserved', $queue.':notify',
			$this->availableAt($this->retryAfter)],
			3
		);

		if (empty($nextJob)) {
			return [null, null];
		}

		[$job, $reserved] = $nextJob;

		if (! $job && ! is_null($this->blockFor) && $block &&
			$this->getConnection()->blpop([$queue.':notify'], $this->blockFor)) {
			return $this->retrieveNextJob($queue, false);
		}

		return [$job, $reserved];
	}

	/**
	 * Delete a reserved job from the reserved queue and release it.
	 *
	 * @param  string  $queue
	 * @param  \Illuminate\Queue\Jobs\RedisJob  $job
	 * @param  int  $delay
	 * @return void
	 */
	public function deleteAndRelease($queue, $job, $delay) {
		$queue = $this->getQueue($queue);

		$this->getConnection()->eval(
			LuaScripts::release(),
			[$queue.':delayed', $queue.':reserved',
			$job->getReservedJob(), $this->availableAt($delay)],
			2
		);
	}
}
