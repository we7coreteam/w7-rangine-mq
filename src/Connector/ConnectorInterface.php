<?php

namespace W7\Mq\Connector;

interface ConnectorInterface {
	public function connect(array $config);
}
