@props([
    'paginator',
    'itemLabel' => 'records',
])

@if($paginator->hasPages())
    @php
        $currentPage = (int) $paginator->currentPage();
        $lastPage = (int) $paginator->lastPage();

        if ($lastPage <= 7) {
            $visiblePages = range(1, $lastPage);
        } else {
            $windowStart = max(2, $currentPage - 2);
            $windowEnd = min($lastPage - 1, $currentPage + 2);

            if ($currentPage <= 4) {
                $windowEnd = min($lastPage - 1, 5);
            }

            if ($currentPage >= $lastPage - 3) {
                $windowStart = max(2, $lastPage - 4);
            }

            $visiblePages = array_values(array_unique(array_merge(
                [1],
                range($windowStart, $windowEnd),
                [$lastPage],
            )));
        }
    @endphp

    <nav class="hg-pagination" role="navigation" aria-label="Pagination navigation">
        <div class="hg-pagination-summary">
            Showing {{ $paginator->firstItem() }} to {{ $paginator->lastItem() }} of {{ $paginator->total() }} {{ $itemLabel }}
        </div>

        <div class="hg-pagination-links">
            @if($paginator->onFirstPage())
                <span class="hg-pagination-link is-disabled" aria-disabled="true">Previous</span>
            @else
                <a class="hg-pagination-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>
            @endif

            @php $previousVisiblePage = null; @endphp

            @foreach($visiblePages as $page)
                @if($previousVisiblePage !== null && $page > $previousVisiblePage + 1)
                    <span class="hg-pagination-ellipsis" aria-hidden="true">...</span>
                @endif

                @if($page === $currentPage)
                    <span class="hg-pagination-link is-active" aria-current="page">{{ $page }}</span>
                @else
                    <a
                        class="hg-pagination-link"
                        href="{{ $paginator->url($page) }}"
                        aria-label="Go to page {{ $page }}"
                    >{{ $page }}</a>
                @endif

                @php $previousVisiblePage = $page; @endphp
            @endforeach

            @if($paginator->hasMorePages())
                <a class="hg-pagination-link" href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
            @else
                <span class="hg-pagination-link is-disabled" aria-disabled="true">Next</span>
            @endif
        </div>
    </nav>
@endif
