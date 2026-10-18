{{-- The text of a toolbox meeting for the mobile app's web view: the platform's styles, sized for a phone.
     It tells the app its height, so the app scrolls it as part of the page. --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    @include('partials.rich-content-css')
    <style>
        html, body { margin: 0; padding: 0; background: transparent; }
        body { font-family: -apple-system, Roboto, 'Segoe UI', sans-serif; font-size: 16px; color: #0f172a; padding: 2px 2px 8px; }
        .erp-rich img { max-width: 100% !important; height: auto !important; }
        .erp-rich table { display: block; overflow-x: auto; }
        :root { --primary-500: #002b45; --primary-600: #002b45; }
    </style>
</head>
<body>
    <div class="erp-rich" id="content">{{ $meeting->renderedBody() }}</div>
    <script>
        (function () {
            function report() {
                var height = document.getElementById('content').getBoundingClientRect().height + 16;
                if (window.ReactNativeWebView) {
                    window.ReactNativeWebView.postMessage(JSON.stringify({ height: Math.ceil(height) }));
                }
            }
            window.addEventListener('load', report);
            window.addEventListener('resize', report);
            Array.prototype.forEach.call(document.images, function (image) { image.addEventListener('load', report); });
            if (window.ResizeObserver) { new ResizeObserver(report).observe(document.getElementById('content')); }
            report();
        })();
    </script>
</body>
</html>
