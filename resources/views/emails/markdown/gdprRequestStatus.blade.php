@component('mail::message')
# {{ $heading }}

{{ $body }}

@component('mail::panel')
**Request type:** {{ $requestType }}
**Status:** {{ $status }}
**Requester:** {{ $requester }}
**Requested:** {{ $requestedAt }}
@if($completedAt)
**Completed:** {{ $completedAt }}
@endif
@endcomponent

@if($adminNotes)
## Administrator notes

{{ $adminNotes }}
@endif

@if($actionUrl && $actionText)
@component('mail::button', ['url' => $actionUrl])
{{ $actionText }}
@endcomponent
@endif

If you did not request this or have questions, please contact the site administrators.

— The {{ $site }} System
@endcomponent

