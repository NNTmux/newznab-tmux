<?php

namespace nntmux\processing;

use App\Models\MultigroupPosters;
use nntmux\NZBMultiGroup;
use nntmux\utility\Utility;


class ProcessReleasesMultiGroup extends ProcessReleases
{
	/**
	 * @var NZBMultiGroup
	 */
	public $nzb;

	/**
	 * ProcessReleasesMultiGroup constructor.
	 *
	 * @param array $options
	 *
	 * @throws \Exception
	 */
	public function __construct(array $options = [])
	{
		parent::__construct($options);
		$this->nzb = new NZBMultiGroup($this->pdo);
	}

	/**
	 * Form fromNamesQuery for creating NZBs
	 *
	 * @void
	 */
	protected function formFromNamesQuery(): void
	{
		$this->fromNamesQuery = '';
	}

	/**
	 * @param $fromName
	 *
	 * @return bool
	 */
	public static function isMultiGroup($fromName): bool
	{
		$poster = MultigroupPosters::query()->where('poster', '=', $fromName)->first();
		return (empty($poster) ? false : true);
	}

	/**
	 * This method exists to prevent the parent one from over-writing the $this->tables property.
	 *
	 * @param int $groupID Unused with mgr
	 *
	 * @return void
	 */
	protected function initiateTableNames($groupID): void
	{
		$this->tables = self::tableNames();
	}

	/**
	 * Returns MGR table names
	 *
	 * @return array
	 */
	public static function tableNames(): array
	{
		return [
			'cname' => 'multigroup_collections',
			'bname' => 'multigroup_binaries',
			'pname' => 'multigroup_parts',
			'prname' => 'multigroup_missed_parts',
		];
	}
}

