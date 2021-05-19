<div class="card card-body">
	<h1>{$title}</h1>
	{if isset($error) && $error != ''}
		<div class="error">{$error}</div>
	{/if}
	<a class="btn btn-success" href="{{url("/admin/user-list")}}">Go back</a>
	<form action="user-edit?action=submit" method="POST">
		{{csrf_field()}}
		<table class="input data table table-striped responsive-utilities jambo-table">
			<tr>
				<td>Name:</td>
				<td>
					<input type="hidden" name="id" {if !empty($user)}value="{$user.id}"{else}{/if}/>
					<input autocomplete="off" class="long" name="username" type="text"
						   {if !empty($user)}value="{$user.username}"{else}{/if}/>
				</td>
			</tr>
			<tr>
				<td>Email:</td>
				<td>
					<input autocomplete="off" class="long" name="email" type="text"
						   {if !empty($user)}value="{$user.email}"{else}{/if} />
				</td>
			</tr>
			<tr>
				<td>Password:</td>
				<td>
					<input autocomplete="off" class="long" name="password" type="password" value=""/>
					{if !empty($user.id)}
						<div class="hint">Only enter a password if you want to change it.</div>
					{/if}
				</td>
			</tr>
			<tr>
				<td><label for="role">Role:</label></td>
				<td>
					{html_radios id="role" name="role" values=$role_ids output=$role_names selected=$user.role.id separator="<br />"}
				</td>
			</tr>
			{if !empty($user.id)}
				<tr>
					<td>Grabs:</td>
					<td>
						<input class="short" name="grabs" type="text" value="{$user.grabs}" />
					</td>
				</tr>
				<tr>
					<td>Invites:</td>
					<td>
						<input class="short" name="invites" type="text" value="{$user.invites}" />
					</td>
				</tr>
			<tr>
				<td>Movie View:</td>
				<td>
					<input name="movieview" type="checkbox" value=1 {if $user.movieview==1}checked="checked"{/if}" />
				</td>
			</tr>
			<tr>
				<td>XXX View:</td>
				<td>
					<input name="xxxview" type="checkbox" value=1 {if $user.xxxview==1}checked="checked"{/if}" />
				</td>
			</tr>
			<tr>
				<td>Music View:</td>
				<td>
					<input name="musicview" type="checkbox" value=1 {if $user.musicview==1}checked="checked"{/if}" />
				</td>
			</tr>
			<tr>
				<td>Console View:</td>
				<td>
					<input name="consoleview" type="checkbox" value=1 {if $user.consoleview==1}checked="checked"{/if}" />
				</td>
			</tr>
			<tr>
				<td>Game View:</td>
				<td>
					<input name="gameview"
						   type="checkbox"
						   value=1
						   {if $user.gameview==1}checked="checked"{/if}" />
				</td>
			</tr>
			<tr>
				<td>Book View:</td>
				<td>
					<input name="bookview" type="checkbox" value=1 {if $user.bookview==1}checked="checked"{/if}" />
				</td>
			</tr>
			<tr>
				<td><label for="role">Role change date:</label></td>
				<td>
					<input name="rolechangedate" class="form-inline" type="text" style="width: 20%" data-provide="datepicker" data-date-format="yyyy-mm-dd" data-date-autoclose="true" data-date-today-highlight="true" data-date-clear-btn="true" value="{$user.rolechangedate}"/>
				</td>
			</tr>
			{/if}
			<tr>
				<td><label for="notes">Notes:</label></td>
				<td>
					<input id="notes" name='notes' type="text" value="{$user.notes|escape:htmlall}"/>
				</td>
			</tr>
			<tr>
				<td></td>
				<td>
					<input class="btn btn-success" type="submit" value="Save"/>
					{if !empty($user.id) && $user->role->id != 2}<a class="confirm_action btn btn-danger"
																	href="{{url("/admin/user-delete?id={$user.id}")}}">
							Delete user</a>{/if}
				</td>
			</tr>
		</table>
	</form>
</div>
