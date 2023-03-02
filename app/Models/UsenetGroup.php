<?php

namespace App\Models;

use Blacklight\ColorCLI;
use Blacklight\NNTP;
use Blacklight\NZB;
use Blacklight\ReleaseImage;
use Blacklight\Releases;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * App\Models\Group.
 *
 * @property int $id
 * @property string $name
 * @property int $backfill_target
 * @property int $first_record
 * @property string|null $first_record_postdate
 * @property int $last_record
 * @property string|null $last_record_postdate
 * @property string|null $last_updated
 * @property int|null $minfilestoformrelease
 * @property int|null $minsizetoformrelease
 * @property bool $active
 * @property bool $backfill
 * @property string|null $description
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Release[] $release
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UsenetGroup whereActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UsenetGroup whereBackfill($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UsenetGroup whereBackfillTarget($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UsenetGroup whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UsenetGroup whereFirstRecord($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UsenetGroup whereFirstRecordPostdate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UsenetGroup whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UsenetGroup whereLastRecord($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UsenetGroup whereLastRecordPostdate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UsenetGroup whereLastUpdated($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UsenetGroup whereMinfilestoformrelease($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UsenetGroup whereMinsizetoformrelease($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UsenetGroup whereName($value)
 *
 * @mixin \Eloquent
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UsenetGroup newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UsenetGroup newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UsenetGroup query()
 */
class UsenetGroup extends Model
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
     * @return array|\Illuminate\Database\Eloquent\Collection|static[]
     */
    public static function getActiveBackfill($order)
    {
        switch ($order) {
            case '':
            case 'normal':
                return self::query()->where('backfill', '=', 1)->where('last_record', '<>', 0)->orderBy('name')->get();
                break;
            case 'date':
                return self::query()->where('backfill', '=', 1)->where('last_record', '<>', 0)->orderBy('first_record_postdate', 'DESC')->get();
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
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    public static function getByName($grp)
    {
        return self::query()->where('name', $grp)->first();
    }

    /**
     * Get a group name using its ID.
     *
     * @param  int|string  $id  The group ID.
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
     * @param  string  $name  The group name.
     * @return false|int false on failure, groups_id on success.
     */
    public static function getIDByName($name)
    {
        $res = self::query()->where('name', $name)->first(['id']);

        return $res === null ? false : $res->id;
    }

    /**
     * Gets a count of all groups in the table limited by parameters.
     *
     * @param  string  $groupname  Constrain query to specific group name
     * @param  int  $active  Constrain query to active status
     * @return mixed
     */
    public static function getGroupsCount($groupname = '', $active = -1)
    {
        $res = self::query();

        if ($groupname !== '') {
            $res->where('name', 'like', '%'.$groupname.'%');
        }

        if ($active > -1) {
            $res->where('active', $active);
        }

        return $res === null ? 0 : $res->count(['id']);
    }

    /**
     * @param  string  $groupname
     * @param  null  $active
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public static function getGroupsRange($groupname = '', $active = null)
    {
        $groups = self::query()->groupBy('id')->orderBy('name');

        if ($groupname !== '') {
            $groups->where('name', 'like', '%'.$groupname.'%');
        }

        if ($active === true) {
            $groups->where('active', '=', 1);
        } elseif ($active === false) {
            $groups->where('active', '=', 0);
        }

        return $groups->paginate(config('nntmux.items_per_page'));
    }

    /**
     * Update an existing group.
     *
     *
     * @return int
     */
    public static function updateGroup($group)
    {
        return self::query()->where('id', $group['id'])->update(
            [
                'name' => trim($group['name']),
                'description' => trim($group['description']),
                'backfill_target' => $group['backfill_target'],
                'first_record' => $group['first_record'],
                'last_record' => $group['last_record'],
                'last_updated' => now(),
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
     * @param  string  $groupName  The full name of the usenet group being evaluated
     * @return string|bool The name of the group replacing shorthand prefix or false if groupname was malformed
     */
    public static function isValidGroup($groupName)
    {
        if (preg_match('/^([\w\-]+\.)+[\w\-]+$/i', $groupName)) {
            return preg_replace('/^a\.b\./i', 'alt.binaries.', $groupName, 1);
        }

        return false;
    }

    /**
     * Add a new group.
     *
     *
     * @return int|mixed
     */
    public static function addGroup($group)
    {
        $checkOld = UsenetGroup::query()->where('name', trim($group['name']))->first();
        if (empty($checkOld)) {
            return self::query()->insertGetId([
                'name' => trim($group['name']),
                'description' => isset($group['description']) ? trim($group['description']) : '',
                'backfill_target' => $group['backfill_target'] ?? 1,
                'first_record' => $group['first_record'] ?? 0,
                'last_record' => $group['last_record'] ?? 0,
                'active' => $group['active'] ?? 0,
                'backfill' => $group['backfill'] ?? 0,
                'minsizetoformrelease' => $group['minsizetoformrelease'] ?? null,
                'minfilestoformrelease' => $group['minfilestoformrelease'] ?? null,
            ]);
        }

        return $checkOld->id;
    }

    /**
     * Delete a group.
     *
     * @param  int|string  $id  ID of the group.
     *
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
     * @param  string|int  $id  The group ID.
     *
     * @throws \Exception
     */
    public static function reset($id): bool
    {
        // Remove rows from part repair.
        MissedPart::query()->where('groups_id', $id)->delete();

        // Reset the group stats.
        return self::query()->where('id', $id)->update(
            [
                'backfill_target' => 1,
                'first_record' => 0,
                'first_record_postdate' => null,
                'last_record' => 0,
                'last_record_postdate' => null,
                'last_updated' => null,
                'active' => 0,
            ]
        );
    }

    /**
     * Reset all groups.
     */
    public static function resetall(): bool
    {
        foreach (self::$cbpm as $tablePrefix) {
            DB::statement("TRUNCATE TABLE {$tablePrefix}");
        }

        // Reset the group stats.

        return self::query()->update(
            [
                'backfill_target' => 1,
                'first_record' => 0,
                'first_record_postdate' => null,
                'last_record' => 0,
                'last_record_postdate' => null,
                'last_updated' => null,
                'active' => 0,
            ]
        );
    }

    /**
     * Purge a single group or all groups.
     *
     * @param  int|string|bool  $id  The group ID. If false, purge all groups.
     *
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

        $releases = new Releases();
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

    /**
     * Adds new newsgroups based on a regular expression match against USP available.
     *
     * @param  string  $groupList
     * @param  int  $active
     * @param  int  $backfill
     * @return array|string
     *
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
                return 'Problem fetching usenet_groups from usenet.';
            }

            $regFilter = '/'.$groupList.'/i';

            $ret = [];

            foreach ($groups as $group) {
                if (preg_match($regFilter, $group['group']) > 0) {
                    $res = self::getIDByName($group['group']);
                    if ($res === false) {
                        self::addGroup(
                            [
                                'name' => $group['group'],
                                'active' => $active,
                                'backfill' => $backfill,
                                'description' => 'Added by bulkAdd',
                            ]
                        );
                        $ret[] = ['group' => $group['group'], 'msg' => 'Created'];
                    }
                }
            }

            if (\count($ret) === 0) {
                $ret[] = ['group' => '', 'msg' => 'No groups found with your regex or groups already exist in database, try again!'];
            }
        }

        return $ret;
    }

    /**
     * Updates the group active/backfill status.
     *
     * @param  int  $id  Which group ID
     * @param  string  $column  Which column active/backfill
     * @param  int  $status  Which status we are setting
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
     * Disable group that does not exist on USP server.
     *
     * @param  int  $id  The Group ID to disable
     */
    public static function disableIfNotExist($id): void
    {
        self::updateGroupStatus($id, 'active');
        (new ColorCLI())->error('Group does not exist on server, disabling');
    }
}
