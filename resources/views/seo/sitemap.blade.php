{!! "<". "?xml version=\"1.0\" encoding=\"UTF-8\"?".">" !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    @foreach ($urls as $u)
        <url>
            <loc>{{ $u['loc'] }}</loc>
            @if (! empty($u['lastmod']))
                <lastmod>{{ $u['lastmod'] }}</lastmod>
            @endif

            <changefreq>{{ $u['changefreq'] }}</changefreq>
            <priority>{{ $u['priority'] }}</priority>
        </url>
    @endforeach
</urlset>
