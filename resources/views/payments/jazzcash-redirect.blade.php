<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Redirecting to JazzCash</title>
</head>
<body>
    <p>Redirecting you to JazzCash to complete payment…</p>

    <form id="jazzcash-checkout" method="POST" action="{{ $actionUrl }}">
        @foreach ($fields as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach
        <noscript>
            <button type="submit">Continue to JazzCash</button>
        </noscript>
    </form>

    <script>
        document.getElementById('jazzcash-checkout').submit();
    </script>
</body>
</html>
