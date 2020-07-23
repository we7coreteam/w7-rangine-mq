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

use Illuminate\Contracts\Queue\Queue;
use W7\Mq\Task\QueueTaskAbstract;

interface QueueInterface extends Queue {
	public function setDefaultHandler(string $handlerClass);
	public function getDefaultHandler() : QueueTaskAbstract;
	public function pushData($data);
}
