<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            background-color: #f4f4f7;
            padding: 20px;
        }

        .email-wrapper {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .email-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff;
            padding: 30px 40px;
            text-align: center;
        }

        .email-header h1 {
            font-size: 24px;
            font-weight: 600;
            margin: 0;
        }

        .email-body {
            padding: 40px;
        }

        .email-content {
            font-size: 16px;
            color: #51545e;
        }

        .email-content p {
            margin-bottom: 16px;
        }

        .greeting {
            font-size: 18px;
            color: #333333;
            margin-bottom: 20px;
        }

        .signature {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eaeaea;
            color: #6b6e76;
        }

        .button {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff !important;
            text-decoration: none;
            padding: 14px 28px;
            border-radius: 6px;
            font-weight: 600;
            margin: 20px 0;
            transition: transform 0.2s ease;
        }

        .button:hover {
            transform: translateY(-2px);
        }

        .info-box {
            background-color: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 16px 20px;
            margin: 20px 0;
            border-radius: 0 6px 6px 0;
        }

        .warning-box {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 16px 20px;
            margin: 20px 0;
            border-radius: 0 6px 6px 0;
            color: #856404;
        }

        .email-footer {
            background-color: #f8f9fa;
            padding: 24px 40px;
            text-align: center;
            font-size: 13px;
            color: #9a9ea6;
        }

        .email-footer p {
            margin: 4px 0;
        }

        a {
            color: #667eea;
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }

        .link-text {
            word-break: break-all;
            background-color: #f8f9fa;
            padding: 12px 16px;
            border-radius: 6px;
            font-family: monospace;
            font-size: 14px;
            display: block;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="email-header">
            <h1>@yield('title')</h1>
        </div>
        <div class="email-body">
            <div class="email-content">
                @yield('content')
            </div>
        </div>
        <div class="email-footer">
            <p>© {{ date('Y') }} @yield('site_name', config('app.name')). All rights reserved.</p>
            <p>This is an automated message. Please do not reply directly to this email.</p>
        </div>
    </div>
</body>
</html>
