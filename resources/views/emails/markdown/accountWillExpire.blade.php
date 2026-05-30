@component('mail::message')
# Account expiration notice

Dear {{ $username }},

@component('mail::panel')
**Heads up:** Your **{{ $account }}** role expires in less than **{{ $days }} day(s)**.
@endcomponent

@if($hasPendingRole)
Your **{{ $pendingRoleName }}** role is already scheduled to take effect on **{{ $pendingRoleStartDate }}** after your current role expires.

No renewal action is needed for that pending role. If these details do not look right, please reach out before the expiry date.
@else
To continue enjoying uninterrupted access to all your current features and benefits, please take action before your subscription expires.
@endif

If you have any questions about renewing your account, please don't hesitate to reach out.

Best regards,<br>
The {{ $site }} Team
@endcomponent
