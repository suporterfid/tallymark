<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('shared.title', ['site' => $siteName]) }}</title>
</head>
<body>
    <main>
        <h1>{{ __('shared.title', ['site' => $siteName]) }}</h1>
        <dl>
            <div><dt>{{ __('shared.pageviews') }}</dt><dd>{{ number_format($pageviews) }}</dd></div>
            <div><dt>{{ __('shared.sessions') }}</dt><dd>{{ number_format($sessions) }}</dd></div>
            <div><dt>{{ __('shared.conversions') }}</dt><dd>{{ number_format($conversions) }}</dd></div>
        </dl>
    </main>
</body>
</html>
