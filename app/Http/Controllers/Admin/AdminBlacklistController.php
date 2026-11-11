<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\BlacklistConstants;
use App\Http\Controllers\BasePageController;
use App\Models\Category;
use App\Services\BlacklistService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminBlacklistController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function index(Request $request): mixed
    {
        $this->setAdminPrefs();
        $svc = new BlacklistService;

        $meta_title = $title = 'Binary Black/White List';

        $binlist = $svc->getBlacklist(false);

        $lastSearch = trim($this->scalarInput($request, 'search'));
        if ($lastSearch !== '') {
            $binlist = array_values(array_filter($binlist, static function (object $entry) use ($lastSearch): bool {
                return str_contains(mb_strtolower(implode(' ', [$entry->groupname ?? '', $entry->regex ?? '', $entry->description ?? ''])), mb_strtolower($lastSearch));
            }));
        }

        $this->viewData = array_merge($this->viewData, [
            'lastSearch' => $lastSearch,
            'binlist' => $binlist,
            'title' => $title,
            'meta_title' => $meta_title,
        ]);

        return view('admin.blacklist.index', $this->viewData);
    }

    /**
     * @return RedirectResponse|View|void
     *
     * @throws \Exception
     */
    public function edit(Request $request)
    {
        $this->setAdminPrefs();
        $svc = new BlacklistService;
        $error = '';
        $regex = ['id' => '', 'groupname' => '', 'regex' => '', 'description' => '', 'msgcol' => 1, 'status' => 1, 'optype' => 1];
        $meta_title = $title = 'Binary Black/White list';

        switch ($this->formAction($request, ['view', 'addtest'])) {
            case 'submit':
                if ($request->input('groupname') === '') {
                    $error = 'Group must be a valid usenet group';
                    break;
                }

                if ($request->input('regex') === '') {
                    $error = 'Regex cannot be empty';
                    break;
                }

                if (empty($request->input('id'))) {
                    $svc->addBlacklist($request->all());
                } else {
                    $svc->updateBlacklist($request->all());
                }

                return redirect()->to('admin/binaryblacklist-list');

            case 'addtest':
                if ($request->has('regex') && $request->has('groupname')) {
                    $regex += [
                        'groupname' => $request->input('groupname'),
                        'regex' => $request->input('regex'),
                        'ordinal' => 1,
                        'status' => 1,
                    ];
                }
                break;

            case 'view':
            default:
                if ($request->has('id')) {
                    $title = 'Binary Black/Whitelist Edit';
                    $regex = $svc->getBlacklistByID((int) $request->input('id'));
                } else {
                    $title = 'Binary Black/Whitelist Add';
                    $regex += [
                        'status' => 1,
                        'optype' => 1,
                        'msgcol' => 1,
                    ];
                }
                break;
        }

        if ($error !== '') {
            // Keep what was typed so a rejected submission is not lost.
            $regex = array_merge($regex, $request->only(array_keys($regex)));
        }

        $this->viewData = array_merge($this->viewData, [
            'error' => $error,
            'regex' => (object) $regex,
            'status_ids' => [Category::STATUS_ACTIVE, Category::STATUS_INACTIVE],
            'status_names' => ['Yes', 'No'],
            'optype_ids' => [1, 2],
            'optype_names' => ['Black', 'White'],
            'msgcol_ids' => [
                BlacklistConstants::BLACKLIST_FIELD_SUBJECT,
                BlacklistConstants::BLACKLIST_FIELD_FROM,
                BlacklistConstants::BLACKLIST_FIELD_MESSAGEID,
            ],
            'msgcol_names' => ['Subject', 'Poster', 'MessageId'],
            'title' => $title,
            'meta_title' => $meta_title,
        ]);

        return view('admin.blacklist.edit', $this->viewData);
    }

    /**
     * Delete a single blacklist entry and return to the list.
     */
    public function destroy(int $id): RedirectResponse
    {
        (new BlacklistService)->deleteBlacklist($id);

        return redirect()->back()->with('success', 'Blacklist deleted successfully');
    }
}
