<!doctype html>
<html lang="{{ $previewLocale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $page->getTranslation('title', $previewLocale, false) }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; color: #20242a; font-family: Arial, sans-serif; }
        img { max-width: 100%; }
    </style>
</head>
<body>
    {!! $html !!}
</body>
</html>
