<div class="well well-sm">
    <h1>{$title}</h1>

    <a class="btn btn-success" href="{$smarty.const.WWW_TOP}/admin/role-list"><i class="fa fa-arrow-left"></i> Go
        back</a>
    {{Form::open(['url' => 'admin/role-add?action=submit', 'method' => 'post'])}}

    <table class="input data table table-striped responsive-utilities jambo-table">

        <tr>
            <td>Name:</td>
            <td>
                <input name="name" type="text" value=" " />
                    <div class="hint">The name of the role</div>
            </td>
        </tr>

        <tr>
            <td>Api Requests:</td>
            <td>
                <input name="apirequests" type="text" value=""/>
                <div class="hint">Number of api requests allowed per 24 hour period</div>
            </td>
        </tr>

        <tr>
            <td>Api rate limit:</td>
            <td>
                <input name="rate_limit" type="text" value=""/>
                <div class="hint">Number of api requests allowed per 1 minute</div>
            </td>
        </tr>

        <tr>
            <td>Download Requests:</td>
            <td>
                <input name="downloadrequests" type="text" value=""/>
                <div class="hint">Number of downloads allowed per 24 hour period</div>
            </td>
        </tr>

        <tr>
            <td>Invites:</td>
            <td>
                <input name="defaultinvites" type="text" value=""/>
                <div class="hint">Default number of invites to give users on account creation</div>
            </td>
        </tr>

        <tr>
            <td>Can Preview:</td>
            <td>
                {html_radios id="canpreview" name='canpreview' values=$yesno_ids output=$yesno_names selected=0 separator='<br />'}
                <div class="hint">Whether the role can preview screenshots</div>
            </td>
        </tr>

        <tr>
            <td>Hide Ads:</td>
            <td>
                {html_radios id="hideads" name='hideads' values=$yesno_ids output=$yesno_names selected=0 separator='<br />'}
                <div class="hint">Whether ad's are hidden</div>
            </td>
        </tr>

        <tr>
            <td>Edit Release:</td>
            <td>
                {html_radios id="editrelease" name='editrelease' values=$yesno_ids output=$yesno_names selected=0 separator='<br />'}
                <div class="hint">Can the role edit releases</div>
            </td>
        </tr>
        <tr>
            <td>Donation amount:</td>
            <td>
                <input name="donation" type="text" value="0"/>
            </td>
        </tr>
        <tr>
            <td>Years Added:</td>
            <td>
                <input name="addyears" type="text" value="0"/>
            </td>
        </tr>
        <tr>
            <td>Is Default Role:</td>
            <td>
                {html_radios id="role" name='isdefault' values=$yesno_ids output=$yesno_names selected=0 separator='<br />'}
                <div class="hint">Make this the default role for new users</div>
            </td>
        </tr>
        <tr>
            <td>Can view Console releases</td>
            <td>
                {html_radios id="viewconsole" name='viewconsole' values=$yesno_ids output=$yesno_names selected=0 separator='<br />'}
                <div class="hint">Can this role view console releases</div>
            </td>
        </tr>
        <tr>
            <td>Can view Movie releases</td>
            <td>
                {html_radios id="viewmovies" name='viewmovies' values=$yesno_ids output=$yesno_names selected=0 separator='<br />'}
                <div class="hint">Can this role view movie releases</div>
            </td>
        </tr>
        <tr>
            <td>Can view Audio releases</td>
            <td>
                {html_radios id="viewaudio" name='viewaudio' values=$yesno_ids output=$yesno_names selected=0 separator='<br />'}
                <div class="hint">Can this role view audio releases</div>
            </td>
        </tr>
        <tr>
            <td>Can view PC releases</td>
            <td>
                {html_radios id="viewpc" name='viewpc' values=$yesno_ids output=$yesno_names selected=0 separator='<br />'}
                <div class="hint">Can this role view pc releases</div>
            </td>
        </tr>
        <tr>
            <td>Can view TV releases</td>
            <td>
                {html_radios id="viewtv" name='viewtv' values=$yesno_ids output=$yesno_names selected=0 separator='<br />'}
                <div class="hint">Can this role view tv releases</div>
            </td>
        </tr>
        <tr>
            <td>Can view Adult releases</td>
            <td>
                {html_radios id="viewadult" name='viewadult' values=$yesno_ids output=$yesno_names selected=0 separator='<br />'}
                <div class="hint">Can this role view adult releases</div>
            </td>
        </tr>
        <tr>
            <td>Can view Book releases</td>
            <td>
                {html_radios id="viewbooks" name='viewbooks' values=$yesno_ids output=$yesno_names selected=0 separator='<br />'}
                <div class="hint">Can this role view book releases</div>
            </td>
        </tr>
        <tr>
            <td>Can view Other releases</td>
            <td>
                {html_radios id="viewother" name='viewother' values=$yesno_ids output=$yesno_names selected=0 separator='<br />'}
                <div class="hint">Can this role view other releases</div>
            </td>
        </tr>

        <tr>
            <td></td>
            <td>
                <input class="btn btn-success" type="submit" value="Save"/>
            </td>
        </tr>

    </table>

    {{Form::close()}}
</div>
