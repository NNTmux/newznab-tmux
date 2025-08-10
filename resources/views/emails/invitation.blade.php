<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invitation to {{ $siteName }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .content {
            background-color: #ffffff;
            padding: 20px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
        }
        .button {
            display: inline-block;
            background-color: #007bff;
            color: #ffffff;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .button:hover {
            background-color: #0056b3;
        }
        .footer {
            margin-top: 20px;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
            font-size: 12px;
            color: #6c757d;
        }
        .expiry-notice {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 12px;
            border-radius: 4px;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>You're Invited!</h1>
        <p>{{ $invitedBy->username }} has invited you to join {{ $siteName }}</p>
    </div>

    <div class="content">
        <h2>Welcome to {{ $siteName }}</h2>

        <p>Hello!</p>

        <p>You have been invited by <strong>{{ $invitedBy->username }}</strong> to join {{ $siteName }}, our exclusive community.</p>

        <p>To accept this invitation and create your account, click the button below:</p>

        <div style="text-align: center;">
            <a href="{{ $registerUrl }}" class="button">Accept Invitation</a>
        </div>

        <p>Or copy and paste this link into your browser:</p>
        <p style="word-break: break-all; background-color: #f8f9fa; padding: 10px; border-radius: 4px;">
            {{ $registerUrl }}
        </p>

        <div class="expiry-notice">
            <strong>⚠️ Important:</strong> This invitation will expire on {{ $expiresAt->format('F j, Y \a\t g:i A') }}.
            Please complete your registration before this date.
        </div>

        <p>If you have any questions, please don't hesitate to contact us.</p>

        <p>Welcome aboard!</p>
        <p>The {{ $siteName }} Team</p>
    </div>

    <div class="footer">
        <p>This invitation was sent to {{ $invitation->email }}. If you did not expect this invitation, you can safely ignore this email.</p>
        <p>© {{ date('Y') }} {{ $siteName }}. All rights reserved.</p>
    </div>
</body>
</html>
