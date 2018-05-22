<div class="panel">
	<div class="panel-body pagination2">
		<div class="row">
			<div class="alert alert-info">
			<span style="align-content: center"> This page will redirect you to site outside of {$site->title} to make your payment
			<br>
			If, for some reason, your account isn't updated automaticaly, please send us an email or use our contact form to inform us so we can fix the issue.</span>
			</div>
		</div>
	</div>
	<table class="data table table-condensed responsive-utilities jambo-table">
		{foreach $donation as $donate}
			<form method="post" action="btc_payment?action=submit">
                {{csrf_field()}}
				<thead>
				<tr>
					<th>{$donate->name} ({$donate->donation}$)</th>
				</tr>
				</thead>
				<td>
					<input type="hidden" name="price" value="{$donate->donation}">
					<input type="hidden" name="role" value="{$donate->id}">
					<input type="hidden" name="addyears" value="{$donate->addyears}">
					<input type="submit" class="btn btn-primary" value="Pay with BTC">
				</td>
			</form>
		{/foreach}
	</table>
</div>
