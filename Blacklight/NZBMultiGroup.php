<?php

namespace Blacklight;

/**
 * Class for reading and writing NZB files on the hard disk,
 * building folder paths to store the NZB files.
 */
class NZBMultiGroup extends NZB
{
    /**
     * NZBMultiGroup constructor.
     *
     * @throws \Exception
     */
    public function __construct()
    {
        parent::__construct();
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
