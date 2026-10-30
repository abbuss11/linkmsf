{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url><loc>{{ route('job-postings.index') }}</loc><changefreq>hourly</changefreq></url>
@foreach ($organizations as $organization)
    <url><loc>{{ route('career-pages.show', $organization->slug) }}</loc><lastmod>{{ $organization->updated_at?->toAtomString() }}</lastmod></url>
@endforeach
@foreach ($postings as $posting)
    <url><loc>{{ route('job-postings.show', $posting->slug) }}</loc><lastmod>{{ $posting->updated_at?->toAtomString() }}</lastmod></url>
@endforeach
</urlset>
