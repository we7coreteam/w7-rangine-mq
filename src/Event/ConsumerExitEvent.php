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

namespace W7\Mq\Event;

class ConsumerExitEvent {
	public $reason;
	public $exitCode;

	public function __construct($reason, $exitCode = 0) {
		$this->reason = $reason;
		$this->exitCode = $exitCode;
	}
}
