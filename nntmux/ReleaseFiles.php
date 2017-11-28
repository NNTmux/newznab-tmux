<?php

namespace nntmux;

use nntmux\db\DB;
use Carbon\Carbon;
use App\Models\ParHash;
use App\Models\ReleaseFile;

/**
 * This class handles storage and retrieval of releasefiles.
 */
class ReleaseFiles
{
    /**
     * @var \nntmux\db\DB
     */
    protected $pdo;

    /**
     * @var \nntmux\SphinxSearch
     */
    public $sphinxSearch;

    /**
     * @param \nntmux\db\DB $settings
     * @throws \Exception
     */
    public function __construct($settings = null)
    {
        $this->pdo = ($settings instanceof DB ? $settings : new DB());
        $this->sphinxSearch = new SphinxSearch();
    }

    /**
     * Get releasefiles row by id.
     *
     *
     * @param $id
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function get($id)
    {
        return ReleaseFile::query()->where('releases_id', $id)->orderBy('name')->get();
    }

    public function getByGuid($guid)
    {
        return ReleaseFile::query()
            ->join('releases', 'releases.id', '=', 'release_files.releases_id')
            ->where('releases.guid', $guid)
            ->orderBy('release_files.name')->get();
    }

    /**
     * Delete a releasefiles row.
     *
     * @param $id
     *
     * @return bool|\PDOStatement
     */
    public function delete($id)
    {
        $res = ReleaseFile::query()->where('releases_id', $id)->delete();
        $this->sphinxSearch->updateRelease($id, $this->pdo);

        return $res;
    }

    /**
     * Add new files for a release ID.
     *
     * @param int    $id          The ID of the release.
     * @param string $name        Name of the file.
     * @param string $hash        hash_16k of par2
     * @param int $size Size of the file.
     * @param int    $createdTime Unix time the file was created.
     * @param int    $hasPassword Does it have a password (see Releases class constants)?
     *
     * @return mixed
     */
    public function add($id, $name, $hash = '', $size, $createdTime, $hasPassword)
    {
        $insert = 0;

        $duplicateCheck = ReleaseFile::query()->where(['releases_id' => $id, 'name' => utf8_encode($name)])->first();

        if ($duplicateCheck === null) {
            $insert = ReleaseFile::query()->insertGetId(
                [
                    'releases_id' => $id,
                    'name' => utf8_encode($name),
                    'size' => $size,
                    'created_at' => Carbon::createFromTimestamp($createdTime),
                    'updated_at' => Carbon::now(),
                    'passworded' => $hasPassword,
                ]
            );

            if (\strlen($hash) === 32) {
                ParHash::query()->insert(['releases_id' => $id, 'hash' => $hash]);
            }
            $this->sphinxSearch->updateRelease($id, $this->pdo);
        }

        return $insert;
    }
}
