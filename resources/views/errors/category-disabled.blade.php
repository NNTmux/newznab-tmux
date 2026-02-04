<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ __('Category Disabled') }}</title>

        <style>
            /*! normalize.css v8.0.1 | MIT License | github.com/necolas/normalize.css */html{line-height:1.15;-webkit-text-size-adjust:100%}body{margin:0}a{background-color:transparent}code{font-family:monospace,monospace;font-size:1em}[hidden]{display:none}html{font-family:system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica Neue,Arial,Noto Sans,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol,Noto Color Emoji;line-height:1.5}*,:after,:before{box-sizing:border-box;border:0 solid #e2e8f0}a{color:inherit;text-decoration:inherit}
        </style>

        <style>
            body {
                font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";
            }

            .container {
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                background-color: #f3f4f6;
            }

            @media (prefers-color-scheme: dark) {
                .container {
                    background-color: #1f2937;
                }

                .card {
                    background-color: #374151;
                }

                .title {
                    color: #f9fafb;
                }

                .message {
                    color: #d1d5db;
                }

                .icon-container {
                    background-color: #fef3c7;
                }
            }

            .card {
                max-width: 28rem;
                margin: 1rem;
                padding: 2rem;
                background-color: #ffffff;
                border-radius: 0.75rem;
                box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
                text-align: center;
            }

            .icon-container {
                width: 4rem;
                height: 4rem;
                margin: 0 auto 1.5rem;
                border-radius: 9999px;
                background-color: #fef3c7;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .icon {
                width: 2rem;
                height: 2rem;
                color: #f59e0b;
            }

            .title {
                font-size: 1.5rem;
                font-weight: 700;
                color: #111827;
                margin-bottom: 0.5rem;
            }

            .category-name {
                color: #3b82f6;
            }

            .message {
                color: #6b7280;
                margin-bottom: 1.5rem;
                line-height: 1.6;
            }

            .button {
                display: inline-block;
                padding: 0.75rem 1.5rem;
                background-color: #3b82f6;
                color: #ffffff;
                font-weight: 600;
                border-radius: 0.5rem;
                text-decoration: none;
                transition: background-color 0.2s ease;
            }

            .button:hover {
                background-color: #2563eb;
            }

            .back-link {
                display: block;
                margin-top: 1rem;
                color: #6b7280;
                text-decoration: underline;
                font-size: 0.875rem;
            }

            .back-link:hover {
                color: #4b5563;
            }

            @media (prefers-color-scheme: dark) {
                .back-link {
                    color: #9ca3af;
                }

                .back-link:hover {
                    color: #d1d5db;
                }
            }
        </style>
    </head>
    <body class="antialiased">
        <div class="container">
            <div class="card">
                <div class="icon-container">
                    <svg class="icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>

                <h1 class="title">
                    <span class="category-name">{{ $category }}</span> {{ isset($isSubcategory) && $isSubcategory ? 'Subcategory' : 'Category' }} Disabled
                </h1>

                <p class="message">
                    @if(isset($isSubcategory) && $isSubcategory)
                        You have excluded the <strong>{{ $category }}</strong> subcategory in your profile settings.
                        To access this content, please remove this subcategory from your exclusions in your profile.
                    @else
                        You have disabled viewing of the <strong>{{ $category }}</strong> category in your profile settings.
                        To access this content, please enable the category in your profile.
                    @endif
                </p>

                <a href="{{ url('profileedit') }}" class="button">
                    Go to Profile Settings
                </a>

                <a href="{{ url('/') }}" class="back-link">
                    ← Back to Home
                </a>
            </div>
        </div>
    </body>
</html>

