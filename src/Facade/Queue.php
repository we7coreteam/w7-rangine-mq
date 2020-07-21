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

namespace W7\Mq\Facade;

use W7\Core\Facades\FacadeAbstract;
use W7\Mq\Queue\QueueInterface;

/**
 * Class Queue
 * @package W7\Mq\Facade
 *
 * @method static QueueInterface channel($name = '');
 * @method static int size($queue = null);
 * @method static mixed push($job, $data = '', $queue = null);
 * @method static mixed pushOn($queue, $job, $data = '');
 * @method static mixed pushRaw($payload, $queue = null, array $options = []);
 * @method static mixed later($delay, $job, $data = '', $queue = null);
 * @method static mixed laterOn($queue, $delay, $job, $data = '');
 * @method static mixed bulk($jobs, $data = '', $queue = null);
 * @method static \Illuminate\Contracts\Queue\Job|null pop($queue = null);
 * @method static string getConnectionName();
 * @method static \Illuminate\Contracts\Queue\Queue setConnectionName($name);
 */
class Queue extends FacadeAbstract {
	protected static function getFacadeAccessor() {
		return 'queue';
	}
}
