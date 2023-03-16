<div class="header">
    <h2>Profile > <strong>{$user.username|escape:"htmlall"}</strong></h2>

    <div class="breadcrumb-wrapper">
        <ol class="breadcrumb">
            <li><a href="{{url("{$site->home_link}")}}">Home</a></li>
            / Profile / {$user.username|escape:"htmlall"}
        </ol>
    </div>
</div>
<div class="row">
    <div class="col-md-12">
        <div class="row">
            <div class="col-lg-12">
                <div class="card card-default">
                    <div class="card-body">
                        <div class="card-body">
                            <ul class="nav nav-tabs">
                                <li class="nav-item">
                                    <a class="nav-link active" href="#">Main</a>
                                    <table cellpadding="0" cellspacing="0" width="100%">
                                        <tbody>
                                        <tr valign="top">
                                            <td>
                                                <table class="data table table-striped">
                                                    <tbody>
                                                    <tr class="bg-blue-sky">
                                                        <td colspan="2" style="padding-left: 8px;">
                                                            <strong>General</strong>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <th width="200">Username</th>
                                                        <td>{$user.username|escape:"htmlall"}</td>
                                                    </tr>
                                                    {if (isset($isadmin) && $isadmin === "true") || !$publicview}
                                                        <tr>
                                                            <th width="200" title="Not public">E-mail</th>
                                                            <td>{$user.email}</td>
                                                        </tr>
                                                    {/if}
                                                    <tr>
                                                        <th width="200">Registered</th>
                                                        <td>{$user.created_at|date_format}
                                                            ({$user.created_at|timeago} ago)
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <th width="200">Last Login</th>
                                                        <td>{$user.lastlogin|date_format}
                                                            ({$user.lastlogin|timeago} ago)
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <th width="200">Role</th>
                                                        <td>{$user->role->name}</td>
                                                    </tr>
                                                    {if !empty($user.rolechangedate)}
                                                        <tr>
                                                            <th width="200">Role expiration date</th>
                                                            <td>{$user.rolechangedate|date_format:"%A, %B %e, %Y"}</td>
                                                        </tr>
                                                    {/if}
                                                    </tbody>
                                                </table>
                                                <table class="data table table-striped">
                                                    <tbody>
                                                    <tr class="bg-blue-sky">
                                                        <td colspan="2" style="padding-left: 8px;"><strong>UI
                                                                Preferences</strong></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Theme:</th>
                                                        <td>{$user.style}</td>
                                                    </tr>
                                                    <tr>
                                                        <th>Cover view:</th>
                                                        <td>
                                                            {if $user.movieview == "1"}View movie covers{else}View standard movie category{/if}
                                                            <br/>
                                                            {if $user.musicview == "1"}View music covers{else}View standard music category{/if}
                                                            <br/>
                                                            {if $user.consoleview == "1"}View console covers{else}View standard console category{/if}
                                                            <br/>
                                                            {if $user.gameview == "1"}View games covers{else}View standard games category{/if}
                                                            <br/>
                                                            {if $user.bookview == "1"}View book covers{else}View standard book category{/if}
                                                            <br/>
                                                            {if $user.xxxview == "1"}View xxx covers{else}View standard xxx category{/if}
                                                            <br/>
                                                        </td>
                                                    </tr>
                                                    </tbody>
                                                </table>
                                                <table class="data data table table-striped">
                                                    <tbody>
                                                    <tr class="bg-blue-sky">
                                                        <td colspan="2" style="padding-left: 8px;"><strong>API &
                                                                Downloads</strong></td>
                                                    </tr>
                                                    <tr>
                                                        <th>API Hits last 24 hours</th>
                                                        <td>
                                                            <span>
                                                                {$apirequests}
                                                            </span>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <th>Downloads last 24 hours</th>
                                                        <td>
                                                            <span>
                                                                {$grabstoday}
                                                            </span> /
                                                            {$user->role->downloadrequests}
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <th>Downloads Total</th>
                                                        <td>{$user.grabs}</td>
                                                    </tr>
                                                    {if (isset($isadmin) && $isadmin === "true") || !$publicview}
                                                        <tr>
                                                            <th title="Not public">API/RSS Key</th>
                                                            <td>
                                                                <a href="{{url("/rss/full-feed?dl=1&amp;i={$user.id}&amp;api_token={$user.api_token}")}}">{$user.api_token}</a>
                                                                <a href="{{url("profileedit?action=newapikey")}}"
                                                                   class="badge bg-danger">GENERATE NEW
                                                                    KEY</a>
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <th title="Admin Notes">Notes:</th>
                                                            <td>{$user.notes|escape:htmlall}{if $user.notes|count_characters > 0}
                                                                    <br/>
                                                                {/if}{if (isset($isadmin) && $isadmin === "true")}<a
                                                                    href="{{url("/admin/user-edit.php?id={$user.id}#notes")}}"
                                                                    class="badge bg-info">Add/Edit</a>{/if}</td>
                                                        </tr>
                                                    {/if}
                                                    </tbody>
                                                </table>
                                                {if ($user.id === $userdata.id || $isadmin === "true") && $site->registerstatus == 1}
                                                    <table class="data data table table-striped">
                                                        <tbody>
                                                        <tr class="bg-blue-sky">
                                                            <td colspan="2" style="padding-left: 8px;"><strong>Invites</strong>
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                        <tr>
                                                            <th title="Not public">Send Invite:</th>
                                                            <td>{$user.invites}
                                                                {if $user.invites > 0}
                                                                    [
                                                                    <a id="lnkSendInvite"
                                                                       onclick="return false;" href="#">Send
                                                                        Invite</a>
                                                                    ]
                                                                    <span title="Your invites will be reduced when the invitation is claimed."
                                                                          class="invitesuccess"
                                                                          id="divInviteSuccess"></span>
                                                                    <span class="invitefailed"
                                                                          id="divInviteError"></span>
                                                                    <div style="display:none;" id="divInvite">
                                                                        {{Form::open(['id' => 'frmSendInvite', 'method' => 'get'])}}
                                                                        {{Form::label('txtInvite', 'Email')}}
                                                                        {{Form::text('txtInvite', null, ['id' => 'txtInvite'])}}
                                                                        {{Form::submit('Send')}}
                                                                        {{Form::close()}}
                                                                    </div>
                                                                {/if}
                                                            </td>
                                                        </tr>
                                                        {if $userinvitedby && $userinvitedby.username != ""}
                                                            <tr>
                                                                <th width="200">Invited By</th>
                                                                {if $privileged || !$privateprofiles}
                                                                    <td>
                                                                        <a title="View {$userinvitedby.username}'s profile"
                                                                           href="{{url("/profile?name={$userinvitedby.username}")}}">{$userinvitedby.username}</a>
                                                                    </td>
                                                                {else}
                                                                    <td>
                                                                        {$userinvitedby.username}
                                                                    </td>
                                                                {/if}
                                                            </tr>
                                                        {/if}
                                                        </tbody>
                                                    </table>
                                                {/if}
                                                {if (isset($isadmin) && $isadmin === "true") && $downloadlist|@count > 0}
                                                    <table class="data data table table-striped">
                                                        <tbody>
                                                        <tr class="bg-blue-sky">
                                                            <td colspan="2" style="padding-left: 8px;"><strong>Downloads
                                                                    for user</strong>
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <th>date</th>
                                                            <th>release</th>
                                                        </tr>
                                                        {foreach $downloadlist as $download}
                                                            {if $download@iteration == 10}
                                                                <tr class="more">
                                                                    <td colspan="3"><a
                                                                            onclick="$('tr.extra').toggle();$('tr.more').toggle();return false;"
                                                                            href="#">show all...</a></td>
                                                                </tr>
                                                            {/if}
                                                            <tr {if $download@iteration >= 10}class="extra"
                                                                style="display:none;"{/if}>
                                                                <td width="80"
                                                                    title="{$download.timestamp}">{$download.timestamp|date_format}</td>
                                                                <td>{if $download->release->guid == ""}n/a{else}<a
                                                                        href="{{url("/details/{$download->release->guid}")}}">{$download->release->searchname}</a>{/if}
                                                                </td>
                                                            </tr>
                                                        {/foreach}
                                                    </table>
                                                {/if}
                                            </td>
                                        </tr>
                                        </tbody>
                                    </table>
                                </li>
                            </ul>
                        </div>
                        {if (isset($isadmin) && $isadmin === "true") || !$publicview}
                            <a class="btn btn-success" href="{{route("profileedit")}}">Edit
                                Profile</a>
                        {/if}
                        {if $isadmin === "false" && !$publicview}
                            <a class="btn btn-warning confirm_action"
                               href="{{url("profile_delete?id={$user.id}")}}">Delete your account</a>
                        {/if}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
