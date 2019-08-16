<div class="card card-body">
    <h1>{$title}</h1>
    <div style="float:right;">
        <form name="usersearch" action="">
            {{csrf_field()}}
            <label for="username">Username</label>
            <input id="username" type="text" name="username" value="{$username}" size="10"/>
            &nbsp;&nbsp;
            <label for="email">Email</label>
            <input id="email" type="text" name="email" value="{$email}" size="10"/>
            &nbsp;&nbsp;
            <label for="host">Host</label>
            <input id="host" type="text" name="host" value="{$host}" size="10"/>
            &nbsp;&nbsp;
            <label for="role">Role</label>
            <select name="role">
                <option value="">-- any --</option>
                {html_options values=$role_ids output=$role_names selected=$role}
            </select>
            &nbsp;&nbsp
            <input class="btn btn-outline-success" type="submit" value="Search"/>
        </form>
    </div>

    {$userlist->onEachSide(5)->links()}

    <br/><br/>

    <table style="width:100%;margin-top:10px;" class="data table table-striped responsive-utilities jambo-table">

        <tr>
            <th>Name<br/><a title="Sort Descending" href="{$orderbyusername_desc}"><span><i
                            class="fa fa-chevron-down"></i></span></a><a title="Sort Ascending"
                                                                         href="{$orderbyusername_asc}"><span><i
                            class="fa fa-chevron-up"></i></span></a></th>
            <th>Email<br/><a title="Sort Descending" href="{$orderbyemail_desc}"><span><i
                            class="fa fa-chevron-down"></i></span></a><a title="Sort Ascending"
                                                                         href="{$orderbyemail_asc}"><span><i
                            class="fa fa-chevron-up"></i></span></a></th>
            <th>Host<br/><a title="Sort Descending" href="{$orderbyhost_desc}"><span><i class="fa fa-chevron-down"></i></span></a><a
                    title="Sort Ascending" href="{$orderbyhost_asc}"><span><i
                            class="fa fa-chevron-up"></i></span></a></th>
            <th>Join Date<br/><a title="Sort Descending" href="{$orderbycreatedat_desc}"><span><i
                            class="fa fa-chevron-down"></i></span></a><a title="Sort Ascending"
                                                                         href="{$orderbycreatedat_asc}"><span><i
                            class="fa fa-chevron-up"></i></span></a></th>
            <th>Last Login<br/><a title="Sort Descending" href="{$orderbylastlogin_desc}"><span><i
                            class="fa fa-chevron-down"></i></span></a><a title="Sort Ascending"
                                                                         href="{$orderbylastlogin_asc}"><span><i
                            class="fa fa-chevron-up"></i></span></a></th>
            <th>API Access<br/><a title="Sort Descending" href="{$orderbyapiaccess_desc}"><span><i
                            class="fa fa-chevron-down"></i></span></a><a title="Sort Ascending"
                                                                         href="{$orderbyapiaccess_asc}"><span><i
                            class="fa fa-chevron-up"></i></span></a></th>
            <th>API Requests<br/><a title="Sort Descending" href="{$orderbyapirequests_desc}"><span><i
                            class="fa fa-chevron-down"></i></span></a><a title="Sort Ascending"
                                                                         href="{$orderbyapirequests_asc}"><span><i
                            class="fa fa-chevron-up"></i></span></a></th>
            <th class="mid">Grabs<br/><a title="Sort Descending" href="{$orderbygrabs_desc}"><span><i
                            class="fa fa-chevron-down"></i></span></a><a title="Sort Ascending"
                                                                         href="{$orderbygrabs_asc}"><span><i
                            class="fa fa-chevron-up"></i></span></a></th>
            <th class="mid">Invites</th>
            <th class="mid">Notes</th>
            <th>Role<br/><a title="Sort Descending" href="{$orderbyrole_desc}"><span><i class="fa fa-chevron-down"></i></span></a><a
                    title="Sort Ascending" href="{$orderbyrole_asc}"><span><i
                            class="fa fa-chevron-up"></i></span></a></th>
            <th>Role Expiration Date<br/><a title="Sort Descending" href="{$orderbyrolechangedate_desc}"><span><i
                            class="fa fa-chevron-down"></i></span></a><a title="Sort Ascending"
                                                                         href="{$orderbyrolechangedate_asc}"><span><i
                            class="fa fa-chevron-up"></i></span></a></th>
            <th>Verified<br/><a title="Sort Descending" href="{$orderbyverification_desc}"><span><i
                            class="fa fa-chevron-down"></i></span></a><a title="Sort Ascending"
                                                                         href="{$orderbyverification_asc}"><span><i
                            class="fa fa-chevron-up"></i></span></a></th>
            <th>Options</th>
        </tr>

        {foreach $userlist as $user}
            <tr class="{cycle values=",alt"}">
                <td><a title="Edit user"
                       href="{{url("/admin/user-edit?id={$user->id}")}}">{$user->username}</a>
                </td>
                <td><a title="View profile" href="{{url("/profile?id={$user->id}")}}">{$user->email}</a>
                </td>
                <td>{$user->host}</td>
                <td title="{$user->created_at}">{$user->created_at}</td>
                <td title="{$user->lastlogin}">{$user->lastlogin}</td>
                <td title="{$user->apiaccess}">{$user->apiaccess}</td>
                <td>{$user->apirequests}</td>
                <td class="mid">{$user->grabs}</td>
                <td class="mid">{$user->invites}</td>
                <td class="mid"><a title="{if $user->notes|count_characters > 0}View{else}Add{/if} Notes"
                                   href="{{url("/admin/user-edit?id={$user->id}#notes")}}"><img
                            src="{{url("/shared/images/icons/{if $user->notes|count_characters > 0}note_edit.png{else}note_add.png{/if}")}}"
                            alt=""/></a></td>
                <td>{$user->rolename}</td>
                <td>{if !empty($user->rolechangedate)}{$user->rolechangedate}{/if}</td>
                <td>{if {$user->verified} == 1} Yes {else} No {/if}</td>
                <td>{if $user->roles_id !="2"}<a class="confirm_action"
                                                 href="{{url("/admin/user-delete?id={$user->id}")}}">
                            delete</a>{/if}
                </td>
            </tr>
        {/foreach}
    </table>
</div>
