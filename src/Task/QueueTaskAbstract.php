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

namespace W7\Mq\Task;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use W7\App;
use W7\Core\Facades\Container;
use W7\Core\Facades\Context;
use W7\Core\Task\TaskAbstract;

abstract class QueueTaskAbstract extends TaskAbstract {
	use InteractsWithQueue, Queueable;

	protected $data;

	public function __construct($data = []) {
		$this->data = $data;
	}

	public function setData($data) {
		$this->data = $data;
	}

	final public function handle() {
		return $this->run(App::$server, Context::getCoroutineId(), Container::get('worker_id'), $this->data);
	}
}
