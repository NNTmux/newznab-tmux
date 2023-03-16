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
                        </div>
                        {{Form::submit('Save', ['class' => 'btn btn-success'])}}
                        {{Form::close()}}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
