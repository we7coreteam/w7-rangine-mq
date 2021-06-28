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

namespace W7\Mq\Command\Queue\Failed;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use W7\App;
use W7\Command\Support\Composer;
use W7\Console\Command\CommandAbstract;
use W7\Core\Exception\CommandException;
use W7\DatabaseTool\Migrate\MigrationCreator;

class CreateTableCommand extends CommandAbstract {
	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Create a migration for the failed queue jobs database table';

	/**
	 * The filesystem instance.
	 *
	 * @var \Illuminate\Filesystem\Filesystem
	 */
	protected $files;

	/**
	 * @var \Illuminate\Support\Composer
	 */
	protected $composer;

	public function __construct(string $name = null) {
		parent::__construct($name);

		$this->files = new Filesystem();
		$this->composer = new Composer($this->files, App::getApp()->getBasePath());
	}

	public function handle($options) {
		$table = $this->getConfig()->get('queue.failed.table');
		if (!$table) {
			throw new CommandException('configuration queue.failed.table missing, please check the configuration config/queue.php');
		}

		$this->replaceMigration(
			$this->createBaseMigration($table),
			$table,
			Str::studly($table)
		);

		$this->output->info('Migration created successfully!');

		$this->composer->dumpAutoloads();
	}

	protected function createBaseMigration($table = 'failed_jobs') {
		$filesystem = new Filesystem();
		$creator = new MigrationCreator($filesystem);
		return $creator->create(
			'create_'.$table.'_table',
			App::getApp()->getBasePath() . '/database/migrations'
		);
	}

	protected function replaceMigration($path, $table, $tableClassName) {
		$stub = str_replace(
			['{{table}}', '{{tableClassName}}', '{{time}}'],
			[$table, $tableClassName, date('Y_m_d_His')],
			$this->files->get(dirname(__DIR__, 2) . '/stubs/failed_jobs.stub')
		);

		$this->files->put($path, $stub);
	}
}
