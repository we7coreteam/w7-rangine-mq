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

namespace W7\Mq\Server;

use W7\Core\Facades\Config;
use W7\Core\Process\ProcessServerAbstract;
use W7\Mq\Process\ConsumerProcess;

class Server extends ProcessServerAbstract {
	public function __construct() {
		//添加process 到server.php中
		$mqSetting = Config::get($this->getType() . '.setting', []);
		Config::set('server.' . $this->getType(), $mqSetting);

		parent::__construct();
	}

	public function getType() {
		return 'mq';
	}

	protected function checkSetting() {
		$connections = Config::get('queue.connections', []);
		foreach ($connections as &$connection) {
			//如果是全部启动的话，enable和配置中的值保持一致
			$connection['enable'] = $connection['enable'] ?? true;
		}

		Config::set('queue.connections', $connections);

		$this->setting['worker_num'] = $this->getWorkerNum();
		if ($this->setting['worker_num'] == 0) {
			throw new \RuntimeException('the list of started mq is empty, please check the configuration in config/queue.php');
		}

		return parent::checkSetting();
	}

	private function getWorkerNum() {
		$workerNum = 0;
		$connections = Config::get('queue.connections', []);
		foreach ($connections as $name => $connection) {
			if (empty($connection['enable'])) {
				continue;
			}
			$workerNum += $connection['worker_num'] ?? 1;
		}

		return $workerNum;
	}

	protected function register() {
		$connections = Config::get('queue.connections', []);
		foreach ($connections as $name => $connection) {
			if (empty($connection['enable'])) {
				continue;
			}
			$this->pool->registerProcess($name, ConsumerProcess::class, $connection['worker_num'] ?? 1);
		}
	}
}
