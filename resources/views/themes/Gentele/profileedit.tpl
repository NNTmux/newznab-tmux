<div class="header">
    <h2>Edit Profile > <strong>{$user.username|escape:"htmlall"}</strong></h2>
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
            <div class="col-lg-12 col-sm-12 col-12">
                <div class="card card-default">
                    <div class="card-body">
                        {if $error != ''}
                            <div class="alert alert-danger">{$error}</div>
                        {/if}
                        {{Form::open(['url' => 'profileedit?action=submit'])}}
                        <ul class="nav nav-tabs" id="profileTab" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" href="#settings" id="settings-tab" data-bs-toggle="tab" role="tab" aria-controls="settings" aria-selected="true"><i class="fa fa-cogs fa-spin"></i>
                                    Settings</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#downloaders" id="downloaders-tab" data-bs-toggle="tab" role="tab" aria-controls="downloaders" aria-selected="false"><i class="fa fa-cogs fa-spin"></i>Downloaders</a>
                            </li>
                        </ul>
                        <div class="tab-content" id="profileTabContent">
                            <div class="tab-pane fade show active" id="settings" role="tabpanel" aria-labelledby="settings-tab">
                                <table class="data table table-striped">
                                    <tbody>
                                    <tr class="bg-aqua-active">
                                        <td colspan="2" style="padding-left: 8px;">
                                            <strong>Profile</strong></td>
                                    </tr>
                                    <tr>
                                        <th width="200">Current email</th>
                                        <td>{$user.email|escape:"htmlall"}</td>
                                    </tr>
                                    <tr>
                                        <th width="200">E-Mail</th>
                                        <td>
                                            <input id="email" class="form-inline" name="email"
                                                   type="text"
                                                   value="">
                                            <div class="hint">Only enter your email if you want to change it. If you change your email you will need to verify it. You will not be able to access the site until verification is complete.
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th width="200">Password</th>
                                        <td>
                                            <input autocomplete="off" id="password" name="password"
                                                   type="password" class="form-inline" value="">
                                            <div class="hint">Only enter your password if you want
                                                to change it.
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th width="200">Confirm password</th>
                                        <td>
                                            <input autocomplete="off" id="password_confirmation"
                                                   name="password_confirmation" type="password"
                                                   class="form-inline" value="">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th width="200">API Key</th>
                                        <td>
                                            {$user.api_token}
                                        </td>
                                    </tr>
                                    </tbody>
                                </table>
                                <table class="data table table-striped">
                                    <tbody>
                                    <tr class="bg-aqua-active">
                                        <th colspan="2" style="padding-left: 8px;"><strong>Excluded Categories</strong></th>
                                    </tr>
                                    <tr>
                                        <th width="200"></th>
                                        {if $user->can('view console')}
                                    <tr>
                                        <td>View Console releases</td>
                                        <td>
                                            {html_radios id="viewconsole" name='viewconsole' values=$yesno_ids output=$yesno_names selected={(int)$user->hasDirectPermission('view console')} separator='<br />'}
                                        </td>
                                    </tr>
                                    {/if}
                                    {if $user->can('view movies')}
                                        <tr>
                                            <td>View Movie releases</td>
                                            <td>
                                                {html_radios id="viewmovies" name='viewmovies' values=$yesno_ids output=$yesno_names selected={(int)$user->hasDirectPermission('view movies')} separator='<br />'}
                                            </td>
                                        </tr>
                                    {/if}
                                    {if $user->can('view audio')}
                                        <tr>
                                            <td>View Audio releases</td>
                                            <td>
                                                {html_radios id="viewaudio" name='viewaudio' values=$yesno_ids output=$yesno_names selected={(int)$user->hasDirectPermission('view audio')} separator='<br />'}
                                            </td>
                                        </tr>
                                    {/if}
                                    {if $user->can('view pc')}
                                        <tr>
                                            <td>View PC releases</td>
                                            <td>
                                                {html_radios id="viewpc" name='viewpc' values=$yesno_ids output=$yesno_names selected={(int)$user->hasDirectPermission('view pc')} separator='<br />'}
                                            </td>
                                        </tr>
                                    {/if}
                                    {if $user->can('view tv')}
                                        <tr>
                                            <td>View TV releases</td>
                                            <td>
                                                {html_radios id="viewtv" name='viewtv' values=$yesno_ids output=$yesno_names selected={(int)$user->hasDirectPermission('view tv')} separator='<br />'}
                                            </td>
                                        </tr>
                                    {/if}
                                    {if $user->can('view adult')}
                                        <tr>
                                            <td>View Adult releases</td>
                                            <td>
                                                {html_radios id="viewadult" name='viewadult' values=$yesno_ids output=$yesno_names selected={(int)$user->hasDirectPermission('view adult')} separator='<br />'}
                                            </td>
                                        </tr>
                                    {/if}
                                    {if $user->can('view books')}
                                        <tr>
                                            <td>View Book releases</td>
                                            <td>
                                                {html_radios id="viewbooks" name='viewbooks' values=$yesno_ids output=$yesno_names selected={(int)$user->hasDirectPermission('view books')} separator='<br />'}
                                            </td>
                                        </tr>
                                    {/if}
                                    {if $user->can('view other')}
                                        <tr>
                                            <td>View Other releases</td>
                                            <td>
                                                {html_radios id="viewother" name='viewother' values=$yesno_ids output=$yesno_names selected={(int)$user->hasDirectPermission('view other')} separator='<br />'}
                                            </td>
                                        </tr>
                                    {/if}

                                    </tbody>
                                </table>
                                <table class="data table table-striped">
                                    <tbody>
                                    <tr class="bg-aqua-active">
                                        <td colspan="2" style="padding-left: 8px;"><strong>UI
                                                Preferences</strong></td>
                                    </tr>
                                    <tr>
                                        <th width="200">Movie Page</th>
                                        <td><input type="checkbox" name="movieview"
                                                   class="onoffswitch-checkbox" id="movieview"
                                                   {if $user.movieview == "1"}checked{/if}> Browse
                                            movie covers. Only shows movies with known IMDB info.
                                        </td>
                                    </tr>
                                    <tr>
                                        <th width="200">Music Page</th>
                                        <td><input type="checkbox" name="musicview"
                                                   class="onoffswitch-checkbox" id="musicview"
                                                   {if $user.musicview == "1"}checked{/if}> Browse
                                            music covers. Only shows music with known lookup info.
                                        </td>
                                    </tr>
                                    <tr>
                                        <th width="200">Console Page</th>
                                        <td><input type="checkbox" name="consoleview"
                                                   class="onoffswitch-checkbox" id="consoleview"
                                                   {if $user.consoleview == "1"}checked{/if}> Browse
                                            console covers. Only shows games with known lookup info.
                                        </td>
                                    </tr>
                                    <tr>
                                        <th width="200">Games Page</th>
                                        <td><input type="checkbox" name="gameview"
                                                   class="onoffswitch-checkbox" id="gameview"
                                                   {if $user.gameview == "1"}checked{/if}> Browse game
                                            covers. Only shows games with known lookup info.
                                        </td>
                                    </tr>
                                    <tr>
                                        <th width="200">Book Page</th>
                                        <td><input type="checkbox" name="bookview"
                                                   class="onoffswitch-checkbox" id="bookview"
                                                   {if $user.bookview == "1"}checked{/if}> Browse book
                                            covers. Only shows books with known lookup info.
                                        </td>
                                    </tr>
                                    <tr>
                                        <th width="200">XXX Page</th>
                                        <td><input type="checkbox" name="xxxview"
                                                   class="onoffswitch-checkbox" id="xxxview"
                                                   {if $user.xxxview == "1"}checked{/if}> Browse XXX
                                            covers. Only shows XXX releases with known lookup info.
                                        </td>
                                    </tr>
                                    </tbody>
                                </table>
                                <table class="data table table-striped">
                                    <tbody>
                                    <tr class="bg-aqua-active">
                                        <td colspan="2" style="padding-left: 8px;"><strong>Enable or disable 2FA: </strong>
                                            <a href="{{url("{'2fa'}")}}"> Here</a></td>
                                    </tr>
                                    <br>

                                    </tbody>
                                </table>
                                {if {{App\Models\Settings::settingValue('site.main.userselstyle')}} == 1}
                                    <table class="data table table-striped">
                                        <tbody>
                                        <tr class="bg-aqua-active">
                                            <td colspan="2" style="padding-left: 8px;"><strong>Site theme</strong></td>
                                        </tr>
                                        <tr>
                                            <td>
                                                {html_options id="style" name='style' values=$themelist output=$themelist selected=$user.style}
                                            </td>
                                        </tr>
                                        </tbody>
                                    </table>
                                {/if}
                            </div>
                            <div class="tab-pane fade" id="downloaders" role="tabpanel" aria-labelledby="downloaders-tab">
                                <div class="alert alert-info">
                                    These settings are only needed if you want to be able to push NZB's
                                    to your downloader straight from the website. You don't need this
                                    for automation software like Sonarr, Sickbeard, SickRage, SickGear
                                    or Couchpotato to
                                    function.
                                </div>
                                <br>
                                {if {{App\Models\Settings::settingValue('apps.sabnzbplus.integrationtype')}} != 1}
                                    <table class="data table table-striped">
                                        <tbody>
                                        <tr class="bg-aqua-active">
                                            <td colspan="2" style="padding-left: 8px;"><strong>Queue
                                                    type
                                                    <small>(NZBGet or SABnzbd)</small>
                                                </strong></td>
                                        </tr>
                                        <tr>
                                            <th width="200">Select type</th>
                                            <td>
                                                {html_options id="queuetypeids" name='queuetypeids' values=$queuetypeids output=$queuetypes selected=$user.queuetype}
                                                <span class="form-text.text-muted">Pick the type of queue you wish to use, once you save your profile, the page will reload, the box will appear and you can fill out the details.</span>
                                            </td>
                                        </tr>
                                        </tbody>
                                    </table>
                                {/if}
                                {if $user.queuetype == 1 && {{App\Models\Settings::settingValue('apps.sabnzbplus.integrationtype')}} == 2}
                                    <table class="data table table-striped">
                                        <tbody>
                                        <tr class="bg-aqua-active">
                                            <td colspan="2" style="padding-left: 8px;">
                                                <strong>SABnzbd</strong>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th width="200">URL</th>
                                            <td><input id="saburl" class="form-inline"
                                                       name="saburl" type="text"
                                                       placeholder="SABNZBd URL"
                                                       value="{$saburl_selected}"></td>
                                        </tr>
                                        <tr>
                                            <th width="200">API Key</th>
                                            <td><input id="sabapikey" class="form-inline"
                                                       name="sabapikey" type="text"
                                                       placeholder="SABNZbd API Key"
                                                       value="{$sabapikey_selected}"></td>
                                        </tr>
                                        <tr>
                                            <th width="200">API Key Type</th>
                                            <td>
                                                {html_radios id="sabapikeytype" name='sabapikeytype' values=$sabapikeytype_ids output=$sabapikeytype_names selected=$sabapikeytype_selected separator='<br />'}
                                                <div class="hint">
                                                    Select the type of api key you entered in the
                                                    above setting. Using your full SAB api key will
                                                    allow you access to the SAB queue from within
                                                    this site.
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th width="200">Priority Level</th>
                                            <td>
                                                {html_options id="sabpriority" class="form-inline" name='sabpriority' values=$sabpriority_ids output=$sabpriority_names selected=$sabpriority_selected}
                                                <div class="hint">Set the priority level for NZBs that
                                                    are added to your queue
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th width="200">Setting Storage</th>
                                            <td>
                                                {html_radios id="sabsetting" name='sabsetting' values=$sabsetting_ids output=$sabsetting_names selected=$sabsetting_selected separator='&nbsp;&nbsp;'}{if $sabsetting_selected == 2}&nbsp;&nbsp;[
                                                    <a class="confirm_action"
                                                       href="?action=clearcookies">Clear Cookies</a>
                                                    ]{/if}
                                                <div class="hint">Where to store the SAB setting.<br/>&bull;
                                                    <b>Cookie</b> will store the setting in your
                                                    browsers coookies and will only work when using your
                                                    current browser.<br/>&bull; <b>Site</b> will store
                                                    the setting in your user account enabling it to work
                                                    no matter where you are logged in from.<br/><span
                                                        class="warning"><b>Please Note:</b></span>
                                                    You should only store your full SAB api key with
                                                    sites you trust.
                                                </div>
                                            </td>
                                        </tr>
                                        </tbody>
                                    </table>
                                {/if}
                                {if $user.queuetype == 2 && ({{App\Models\Settings::settingValue('apps.sabnzbplus.integrationtype')}} == 0 || {{App\Models\Settings::settingValue('apps.sabnzbplus.integrationtype')}} == 2)}
                                    <table class="data table table-striped">
                                        <tbody>
                                        <tr class="bg-aqua-active">
                                            <td colspan="2" style="padding-left: 8px;">
                                                <strong>NZBget</strong>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th width="200">URL</th>
                                            <td><input id="nzbgeturl" placeholder="NZBGet URL"
                                                       class="form-inline" name="nzbgeturl"
                                                       type="text" value="{$user.nzbgeturl}"/></td>
                                        </tr>
                                        <tr>
                                            <th width="200">Username / Password</th>
                                            <td>
                                                <div class="form-inline">
                                                    <input id="nzbgetusername"
                                                           placeholder="NZBGet Username"
                                                           class="form-inline"
                                                           name="nzbgetusername" type="text"
                                                           value="{$user.nzbgetusername}"/>
                                                    /
                                                    <input id="nzbgetpassword"
                                                           placeholder="NZBGet Password"
                                                           class="form-inline"
                                                           name="nzbgetpassword" type="text"
                                                           value="{$user.nzbgetpassword}"/>
                                                </div>
                                            </td>
                                        </tr>
                                        </tbody>
                                    </table>
                                {/if}
                                <br/>
                            </div>
                        </div>
                        {{Form::submit('Save', ['class' => 'btn btn-success'])}}
                        {{Form::close()}}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
