<?php

namespace App\Models;

use Blacklight\NZB;
use Blacklight\NNTP;
use Blacklight\ColorCLI;
use Blacklight\Releases;
use Blacklight\ReleaseImage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    /**
     * @var bool
     */
    protected $dateFormat = false;

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var array
     */
    protected $guarded = [];

    /**
     * @var array
     */
    protected static $cbpm = ['collections', 'binaries', 'parts', 'missed_parts'];

    /**
     * @var array
     */
    protected static $cbppTableNames;

    /**
     * @var bool
     */
    protected $allasmgr;

    /**
     * Group constructor.
     *
     * @throws \Exception
     */
    public function __construct()
    {
        parent::__construct();
        $this->allasmgr = (int) Settings::settingValue('..allasmgr') === 1;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function release()
    {
        return $this->hasMany(Release::class, 'groups_id');
    }

    /**
     * Returns an associative array of groups for list selection.
     *
     *
     * @return array
     */
    public static function getGroupsForSelect(): array
    {
        $groups = self::getActive();

        $temp_array = [];
        $temp_array[-1] = '--Please Select--';


            foreach ($groups as $group) {
                $temp_array[$group['name']] = $group['name'];
            }

        return $temp_array;
    }

    /**
     * Get all properties of a single group by its ID.
     *
     *
     * @param $id
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    public static function getGroupByID($id)
    {
        return self::query()->where('id', $id)->first();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public static function getActive()
    {
        return self::query()->where('active', '=', 1)->orderBy('name')->get();
    }

    /**
     * Get active backfill groups ordered by name ascending.
     *
     *
     * @param $order
     * @return array|\Illuminate\Database\Eloquent\Collection|static[]
     */
    public static function getActiveBackfill($order)
    {
        switch ($order) {
            case '':
            case 'normal':
                return self::query()->where('backfill', '=', 1)->where('last_record', '!=', 0)->orderBy('name')->get();
                break;
            case 'date':
                return self::query()->where('backfill', '=', 1)->where('last_record', '!=', 0)->orderBy('first_record_postdate', 'DESC')->get();
                break;
            default:
                return [];
        }
    }

    /**
     * Get all active group IDs.
     *
     *
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public static function getActiveIDs()
    {
        return self::query()->where('active', '=', 1)->orderBy('name')->get(['id']);
    }

    /**
     * Get all group columns by Name.
     *
     *
     * @param $grp
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    public static function getByName($grp)
    {
        return self::query()->where('name', $grp)->first();
    }

    /**
     * Get a group name using its ID.
     *
     * @param int|string $id The group ID.
     *
     * @return string Empty string on failure, groupName on success.
     */
    public static function getNameByID($id): string
    {
        $res = self::query()->where('id', $id)->first(['name']);

        return $res !== null ? $res->name : '';
    }

    /**
     * Get a group ID using its name.
     *
     * @param string $name The group name.
     *
     * @return string|int Empty string on failure, groups_id on success.
     */
    public static function getIDByName($name)
    {
        $res = self::query()->where('name', $name)->first(['id']);

        return $res === null ? '' : $res->id;
    }

    /**
     * Gets a count of all groups in the table limited by parameters.
     *
     * @param string $groupname Constrain query to specific group name
     * @param int    $active    Constrain query to active status
     *
     * @return mixed
     */
    public static function getGroupsCount($groupname = '', $active = -1)
    {
        $res = self::query();

        if ($groupname !== '') {
            $res->where('name', 'LIKE', '%'.$groupname.'%');
        }

        if ($active > -1) {
            $res->where('active', $active);
        }

        return $res === null ? 0 : $res->count(['id']);
    }

    /**
     * Gets all groups and associated release counts.
     *
     *
     * @param bool $offset
     * @param bool $limit
     * @param string $groupname
     * @param null|bool $active
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public static function getGroupsRange($offset = false, $limit = false, $groupname = '', $active = null)
    {
        $groups = self::query()->groupBy('id')->orderBy('name');

        if ($groupname !== '') {
            $groups->where('name', 'LIKE', '%'.$groupname.'%');
        }

        if ($active === true) {
            $groups->where('active', '=', 1);
        } elseif ($active === false) {
            $groups->where('active', '=', 0);
        }

        if ($offset !== false) {
            $groups->limit($limit)->offset($offset);
        }

        return $groups->get();
    }

    /**
     * Update an existing group.
     *
     * @param array $group
     *
     * @return bool
     */
    public static function updateGroup($group): bool
    {
        return self::query()->where('id', $group['id'])->update(
            [
                'name' => trim($group['name']),
                'description' => trim($group['description']),
                'backfill_target' => $group['backfill_target'],
                'first_record' => $group['first_record'],
                'last_record' => $group['last_record'],
                'last_updated' => Carbon::now(),
                'active' => $group['active'],
                'backfill' => $group['backfill'],
                'minsizetoformrelease' => $group['minsizetoformrelease'] === '' ? null : $group['minsizetoformrelease'],
                'minfilestoformrelease' => $group['minfilestoformrelease'] === '' ? null : $group['minfilestoformrelease'],
            ]
        );
    }

    /**
     * Checks group name is standard and replaces any shorthand prefixes.
     *
     * @param string $groupName The full name of the usenet group being evaluated
     *
     * @return string|bool The name of the group replacing shorthand prefix or false if groupname was malformed
     */
    public static function isValidGroup($groupName)
    {
        if (preg_match('/^([\w-]+\.)+[\w-]+$/i', $groupName)) {
            return preg_replace('/^a\.b\./i', 'alt.binaries.', $groupName, 1);
        }

        return false;
    }

    /**
     * Add a new group.
     *
     * @param array $group
     *
     * @return bool
     */
    public static function addGroup($group): bool
    {
        return self::query()->insertGetId(
            [
                'name' => trim($group['name']),
                'description' => isset($group['description']) ? trim($group['description']) : '',
                'backfill_target' => $group['backfill_target'] ?? 1,
                'first_record' => $group['first_record'] ?? 0,
                'last_record' => $group['last_record'] ?? 0,
                'active' => $group['active'] ?? 0,
                'backfill' => $group['backfill'] ?? 0,
                'minsizetoformrelease' => $group['minsizetoformrelease'] ?? null,
                'minfilestoformrelease' => $group['minfilestoformrelease'] ?? null,
            ]
        );
    }

    /**
     * Delete a group.
     *
     * @param int|string $id ID of the group.
     *
     * @return bool
     * @throws \Exception
     */
    public static function deleteGroup($id): bool
    {
        self::purge($id);

        return self::query()->where('id', $id)->delete();
    }

    /**
     * Reset a group.
     *
     * @param string|int $id The group ID.
     *
     * @return bool
     * @throws \Exception
     */
    public static function reset($id): bool
    {
        // Remove rows from part repair.
        MissedPart::query()->where('groups_id', $id)->delete();

        foreach (self::$cbpm as $tablePrefix) {
            DB::unprepared(
                "DROP TABLE IF EXISTS {$tablePrefix}_{$id}"
            );
        }

        // Reset the group stats.
        return self::query()->where('id', $id)->update(
            [
                'backfill_target' => 1,
                'first_record' => 0,
                'first_record_postdate' => null,
                'last_record' => 0,
                'člast_record_postdate' => null,
                'last_updated' => null,
                'active' => 0,
            ]
        );
    }

    /**
     * Reset all groups.
     *
     * @return bool
     */
    public static function resetall(): bool
    {
        foreach (self::$cbpm as $tablePrefix) {
            DB::unprepared("TRUNCATE TABLE {$tablePrefix}");
        }

        $groups = self::query()->select(['id'])->get();

        if ($groups instanceof \Traversable) {
            foreach ($groups as $group) {
                foreach (self::$cbpm as $tablePrefix) {
                    DB::unprepared("DROP TABLE IF EXISTS {$tablePrefix}_{$group['id']}");
                }
            }
        }

        // Reset the group stats.

        return self::query()->update(
            [
                'backfill_target' => 1,
                'first_record' => 0,
                'first_record_postdate' => null,
                'last_record' => 0,
                'člast_record_postdate' => null,
                'last_updated' => null,
                'active' => 0,
            ]
        );
    }

    /**
     * Purge a single group or all groups.
     *
     * @param int|string|bool $id The group ID. If false, purge all groups.
     * @throws \Exception
     */
    public static function purge($id = false)
    {
        if ($id === false) {
            self::resetall();
        } else {
            self::reset($id);
        }

        $res = Release::query()->select(['id', 'guid']);

        if ($id !== false) {
            $res->where('groups_id', $id);
        }

        $res->get();

        if ($res instanceof \Traversable) {
            $releases = new Releases(['Groups' => self::class]);
            $nzb = new NZB();
            $releaseImage = new ReleaseImage();
            foreach ($res as $row) {
                $releases->deleteSingle(
                    [
                        'g' => $row['guid'],
                        'i' => $row['id'],
                    ],
                    $nzb,
                    $releaseImage
                );
            }
        }
    }

    /**
     * Adds new newsgroups based on a regular expression match against USP available.
     *
     * @param string $groupList
     * @param int $active
     * @param int $backfill
     *
     * @return array|string
     * @throws \Exception
     */
    public static function addBulk($groupList, $active = 1, $backfill = 1)
    {
        if (preg_match('/^\s*$/m', $groupList)) {
            $ret = 'No group list provided.';
        } else {
            $nntp = new NNTP(['Echo' => false]);
            if ($nntp->doConnect() !== true) {
                return 'Problem connecting to usenet.';
            }
            $groups = $nntp->getGroups();
            $nntp->doQuit();

            if ($nntp->isError($groups)) {
                return 'Problem fetching groups from usenet.';
            }

            $regFilter = '/'.$groupList.'/i';

            $ret = [];

            foreach ($groups as $group) {
                if (preg_match($regFilter, $group['group']) > 0) {
                    $res = self::getIDByName($group['group']);
                    if ($res === '') {
                        self::addGroup(
                            [
                                'name'        => $group['group'],
                                'active'      => $active,
                                'backfill'    => $backfill,
                                'description' => 'Added by bulkAdd',
                            ]
                        );
                        $ret[] = ['group' => $group['group'], 'msg' => 'Created'];
                    }
                }
            }

            if (\count($ret) === 0) {
                $ret = 'No groups found with your regex, try again!';
            }
        }

        return $ret;
    }

    /**
     * Updates the group active/backfill status.
     *
     * @param int    $id     Which group ID
     * @param string $column Which column active/backfill
     * @param int    $status Which status we are setting
     *
     * @return string
     */
    public static function updateGroupStatus($id, $column, $status = 0): string
    {
        self::query()->where('id', $id)->update(
            [
                $column => $status,
            ]
        );

        return "Group {$id} has been ".(($status === 0) ? 'deactivated' : 'activated').'.';
    }

    /**
     * Get the names of the collections/binaries/parts/part repair tables.
     * If TPG is on, try to create new tables for the groups_id, if we fail, log the error and exit.
     *
     * @param int $groupID ID of the group.
     *
     * @return array The table names.
     * @throws \Exception
     */
    public function getCBPTableNames($groupID): array
    {
        $groupKey = $groupID;

        // Check if buffered and return. Prevents re-querying MySQL when TPG is on.
        if (isset(self::$cbppTableNames[$groupKey])) {
            return self::$cbppTableNames[$groupKey];
        }

        if (config('nntmux.echocli') && $this->allasmgr === false && self::createNewTPGTables($groupID) === false) {
            exit('There is a problem creating new TPG tables for this group ID: '.$groupID.PHP_EOL);
        }

        $tables = [];
        $tables['cname'] = 'collections_'.$groupID;
        $tables['bname'] = 'binaries_'.$groupID;
        $tables['pname'] = 'parts_'.$groupID;
        $tables['prname'] = 'missed_parts_'.$groupID;

        // Buffer.
        self::$cbppTableNames[$groupKey] = $tables;

        return $tables;
    }

    /**
     * Check if the tables exist for the groups_id, make new tables for table per group.
     *
     * @param int $groupID
     *
     * @return bool
     */
    public static function createNewTPGTables($groupID): bool
    {
        foreach (self::$cbpm as $tablePrefix) {
            if (DB::unprepared(
                    "CREATE TABLE IF NOT EXISTS {$tablePrefix}_{$groupID} LIKE {$tablePrefix}"
                ) === null
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Disable group that does not exist on USP server.
     *
     * @param int $id The Group ID to disable
     */
    public static function disableIfNotExist($id): void
    {
        self::updateGroupStatus($id, 'active');
        ColorCLI::doEcho(
            ColorCLI::error(
                'Group does not exist on server, disabling'
            ), true
        );
    }
}
