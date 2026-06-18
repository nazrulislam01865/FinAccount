@props(['brand' => \App\Support\HisebGhorBrand::data()])

<footer {{ $attributes->class(['hg-footer']) }}>
    <span>&copy; {{ now()->year }} {{ $brand['name'] ?? 'HisebGhor' }}. All rights reserved.</span>
    <span>
        System design, development, and intellectual property are owned by
        <a href="{{ $brand['footer_owner_url'] ?? 'https://itqanconsulting.com/' }}" target="_blank" rel="noopener noreferrer">
            <strong>{{ $brand['footer_owner'] ?? 'ITQAN Consulting' }}</strong>
        </a>
    </span>
</footer>
