<?php

namespace App\Http\Controllers;

use App\Models\ReleaseComment;
use App\Models\Settings;
use App\Models\User;
use App\Models\UserDownload;
use App\Models\UserExcludedCategory;
use App\Models\UserRequest;
use Blacklight\SABnzbd;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileController extends BasePageController
{
    /**
     * ProfileController constructor.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @throws \Exception
     */
    public function __construct(Request $request)
    {
        parent::__construct($request);
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @throws \Exception
     */
    public function show(Request $request)
    {

        $sab = new SABnzbd();

        $userID = Auth::id();
        $privileged = User::isAdmin($userID) || User::isModerator($userID);
        $privateProfiles = (int) Settings::settingValue('..privateprofiles') === 1;
        $publicView = false;

        if ($privileged || ! $privateProfiles) {
            $altID = ($request->has('id') && (int) $request->input('id') >= 0) ? (int) $request->input('id') : false;
            $altUsername = ($request->has('name') && \strlen($request->input('name')) > 0) ? $request->input('name') : false;

            // If both 'id' and 'name' are specified, 'id' should take precedence.
            if ($altID === false && $altUsername !== false) {
                $user = User::getByUsername($altUsername);
                if ($user) {
                    $altID = $user['id'];
                    $userID = $altID;
                }
            } elseif ($altID !== false) {
                $userID = $altID;
                $publicView = true;
            }
        }

        $downloadlist = UserDownload::getDownloadRequestsForUser($userID);
        $this->smarty->assign('downloadlist', $downloadlist);

        $data = User::find($userID);
        if ($data === null) {
            abort(404);
        }

        $theme = $this->theme;

        // Check if the user selected a theme.
        if (! isset($data['style']) || $data['style'] === 'None') {
            $data['style'] = 'Using the admin selected theme.';
        }

        $offset = $request->input('offset') ?? 0;
        $this->smarty->assign(
            [
                'apirequests'       => UserRequest::getApiRequests($userID),
                'grabstoday'        => UserDownload::getDownloadRequests($userID),
                'userinvitedby'     => $data['invitedby'] !== '' ? User::find($data['invitedby']) : '',
                'user'              => $data,
                'privateprofiles'   => $privateProfiles,
                'publicview'        => $publicView,
                'privileged'        => $privileged,
                'pagertotalitems'   => ReleaseComment::getCommentCountForUser($userID),
                'pageroffset'       => $offset,
                'pageritemsperpage' => config('nntmux.items_per_page'),
                'pagerquerybase'    => '/profile?id='.$userID.'&offset=',
                'pagerquerysuffix'  => '#comments',
            ]
        );

        $sabApiKeyTypes = [
            SABnzbd::API_TYPE_NZB => 'Nzb Api Key',
            SABnzbd::API_TYPE_FULL => 'Full Api Key',
        ];
        $sabPriorities = [
            SABnzbd::PRIORITY_FORCE  => 'Force', SABnzbd::PRIORITY_HIGH => 'High',
            SABnzbd::PRIORITY_NORMAL => 'Normal', SABnzbd::PRIORITY_LOW => 'Low',
        ];
        $sabSettings = [1 => 'Site', 2 => 'Cookie'];

// Pager must be fetched after the variables are assigned to smarty.
        $this->smarty->assign(
            [
                'pager'         => $this->smarty->fetch($theme.'/pager.tpl'),
                'commentslist'  => ReleaseComment::getCommentsForUserRange($userID, $offset, config('nntmux.items_per_page')),
                'exccats'       => implode(',', UserExcludedCategory::getCategoryExclusionNames($userID)),
                'saburl'        => $sab->url,
                'sabapikey'     => $sab->apikey,
                'sabapikeytype' => $sab->apikeytype !== '' ? $sabApiKeyTypes[$sab->apikeytype] : '',
                'sabpriority'   => $sab->priority !== '' ? $sabPriorities[$sab->priority] : '',
                'sabsetting'    => $sabSettings[$sab->checkCookie() === true ? 2 : 1],
            ]
        );

        $meta_title = 'View User Profile';
        $meta_keywords = 'view,profile,user,details';
        $meta_description = 'View User Profile for '.$data['username'];

        $content = $this->smarty->fetch($this->theme.'/profile.tpl');

        $this->smarty->assign(
            [
                'content' => $content,
                'meta_title' => $meta_title,
                'meta_keywords' => $meta_keywords,
                'meta_description' => $meta_description,
            ]
        );
       $this->pagerender();
    }
}
