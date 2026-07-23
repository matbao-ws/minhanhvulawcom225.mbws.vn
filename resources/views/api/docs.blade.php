<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $siteBranding['name'] }} API Documentation</title>
    <link rel="icon" href="{{ $siteBranding['favicon_url'] }}">
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5.11.0/swagger-ui.css" />
    <style>
        html {
            box-sizing: border-box;
            overflow: -margin-top-collapse;
        }
        *, *:before, *:after {
            box-sizing: inherit;
        }
        body {
            margin: 0;
            background: #fafafa;
            font-family: sans-serif;
        }
        .swagger-ui .topbar {
            background-color: #0b1120;
            padding: 10px 0;
        }
        .swagger-ui .topbar .download-url-wrapper input[type=text] {
            border: 2px solid #5d87ff;
            border-radius: 4px;
        }
        .swagger-ui .topbar .download-url-wrapper .download-url-button {
            background: #5d87ff;
            border-radius: 4px;
        }
        .swagger-ui .info .title {
            color: #0b1120;
        }
        .back-to-admin {
            position: absolute;
            top: 14px;
            right: 20px;
            z-index: 100;
        }
        .back-to-admin a {
            display: inline-block;
            background: rgba(255, 255, 255, 0.15);
            color: #fff;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.2s ease;
            border: 1px solid rgba(255, 255, 255, 0.25);
        }
        .back-to-admin a:hover {
            background: #5d87ff;
            border-color: #5d87ff;
        }
    </style>
</head>
<body>
    <div class="back-to-admin">
        <a href="{{ url('/admin') }}">← Quay lại Admin</a>
    </div>

    <div id="swagger-ui"></div>

    <script src="https://unpkg.com/swagger-ui-dist@5.11.0/swagger-ui-bundle.js" charset="UTF-8"></script>
    <script src="https://unpkg.com/swagger-ui-dist@5.11.0/swagger-ui-standalone-preset.js" charset="UTF-8"></script>
    <script>
        window.onload = function() {
            const ui = SwaggerUIBundle({
                url: "{{ asset('docs/openapi.json') }}",
                dom_id: '#swagger-ui',
                deepLinking: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIStandalonePreset
                ],
                plugins: [
                    SwaggerUIBundle.plugins.DownloadUrl
                ],
                layout: "BaseLayout",
                persistAuthorization: true
            });
            window.ui = ui;
        };
    </script>
</body>
</html>
