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

namespace W7\Mq\Command\Queue;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Composer;
use Illuminate\Support\Str;
use W7\Console\Command\CommandAbstract;
use W7\Core\Facades\Config;

class TableCommand extends CommandAbstract {
	protected $description = 'Create a migration for the queue jobs database table';

	/**
	 * @var Filesystem
	 */
	protected $filesystem;

	/**
	 * @var Composer
	 */
	protected $composer;

	/**
	 * FailedTableCommand constructor.
	 * @param string|null $name
	 */
	public function __construct(string $name = null) {
		parent::__construct($name);

		$this->filesystem = new Filesystem();
		$this->composer = new Composer($this->filesystem, BASE_PATH);
	}

	/**
	 * @param $options
	 * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
	 */
	public function handle($options) {
		$table = Config::get('queue.connections.database.table');

		$datePrefix = date('Y_m_d_His');
		$this->replaceMigration(
			BASE_PATH . '/database/migrations/' . $datePrefix . '_create_'.$table.'_table.php',
			$table,
			Str::studly($table) . $datePrefix
		);

		$this->output->info('Migration created successfully!');

		$this->composer->dumpAutoloads();
	}

	/**
	 * @param $path
	 * @param $table
	 * @param $tableClassName
	 * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
	 */
	protected function replaceMigration($path, $table, $tableClassName) {
		$stub = str_replace(
			['{{table}}', '{{tableClassName}}'],
			[$table, $tableClassName],
			$this->filesystem->get(__DIR__.'/Stubs/jobs.stub')
		);

		$this->filesystem->put($path, $stub);
	}
}
