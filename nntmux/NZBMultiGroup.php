<?php

namespace nntmux;

/**
 * Class for reading and writing NZB files on the hard disk,
 * building folder paths to store the NZB files.
 */
class NZBMultiGroup extends NZB
{
    /**
     * Default constructor.
     *
     *
     * @param $pdo
     *
     * @throws \Exception
     */
    public function __construct(&$pdo)
    {
        parent::__construct($pdo);
    }

    /**
     * Initiate class vars when writing NZB's.
     *
     *
     * @param int $groupID
     */
    public function initiateForWrite($groupID)
    {
        $this->_tableNames = [
			'cName' => 'multigroup_collections',
			'bName' => 'multigroup_binaries',
			'pName' => 'multigroup_parts',
		];

        $this->setQueries();
    }
}
