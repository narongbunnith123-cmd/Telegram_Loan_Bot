@if ($paginator->hasPages())
    <div class="pagination-bar">
        <span class="mr-1 text-gray-400">
            Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}
        </span>

        {{-- Previous --}}
        @if ($paginator->onFirstPage())
            <span class="page-arrow disabled">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4">
                    <polyline points="15 18 9 12 15 6" />
                </svg>
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="page-arrow" rel="prev">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4">
                    <polyline points="15 18 9 12 15 6" />
                </svg>
            </a>
        @endif

        {{-- Page numbers --}}
        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="page-pill disabled">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="page-pill active">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="page-pill">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        {{-- Next --}}
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="page-arrow" rel="next">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4">
                    <polyline points="9 18 15 12 9 6" />
                </svg>
            </a>
        @else
            <span class="page-arrow disabled">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4">
                    <polyline points="9 18 15 12 9 6" />
                </svg>
            </span>
        @endif
    </div>
@endif
