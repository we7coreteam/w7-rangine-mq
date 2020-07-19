<?php

namespace W7\Mq;

/**
 * @mixin \Illuminate\Contracts\Queue\Queue
 */
class QueueManager extends \Illuminate\Queue\QueueManager {
	protected function getConfig($name) {
		if (! is_null($name) && $name !== 'null') {
			return $this->app['config']["queue.connections"][$name];
		}

		return ['driver' => 'null'];
	}
}
