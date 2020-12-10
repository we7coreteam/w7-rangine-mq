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

namespace W7\Mq\Amqp\Connection;

use PhpAmqpLib\Connection\AbstractConnection;
use W7\Mq\Amqp\Wire\IO\SwooleIO;

class AMQPSwooleConnection extends AbstractConnection {
	public function __construct(
		string $host,
		int $port,
		string $user,
		string $password,
		string $vhost = '/',
		bool $insist = false,
		string $loginMethod = 'AMQPLAIN',
		$loginResponse = null,
		string $locale = 'en_US',
		float $connectionTimeout = 3.0,
		float $readWriteTimeout = 3.0,
		$context = null,
		bool $keepalive = false,
		int $heartbeat = 0
	) {
		$io = new SwooleIO($host, $port, $connectionTimeout, $readWriteTimeout, $context, $keepalive, $heartbeat);

		parent::__construct(
			$user,
			$password,
			$vhost,
			$insist,
			$loginMethod,
			$loginResponse,
			$locale,
			$io,
			$heartbeat,
			(int) $connectionTimeout
		);
	}

	protected static function try_create_connection($host, $port, $user, $password, $vhost, $options) {
		$insist = isset($options['insist']) ?
			$options['insist'] : false;
		$login_method = isset($options['login_method']) ?
			$options['login_method'] :'AMQPLAIN';
		$login_response = isset($options['login_response']) ?
			$options['login_response'] : null;
		$locale = isset($options['locale']) ?
			$options['locale'] : 'en_US';
		$keepalive = isset($options['keepalive']) ?
			$options['keepalive'] : false;
		$heartbeat = isset($options['heartbeat']) ?
			$options['heartbeat'] : 0;
		$connection_timeout = isset($options['connection_timeout']) ?
			$options['connection_timeout'] : 3.0;
		$read_write_timeout = isset($options['read_write_timeout']) ?
			$options['read_write_timeout'] : 3.0;
		$context = isset($options['context']) ?
			$options['context'] : null;
		return new static(
			$host,
			$port,
			$user,
			$password,
			$vhost,
			$insist,
			$login_method,
			$login_response,
			$locale,
			$connection_timeout,
			$read_write_timeout,
			$context,
			$keepalive,
			$heartbeat);
	}

	public function getIO() {
		return $this->io;
	}
}
