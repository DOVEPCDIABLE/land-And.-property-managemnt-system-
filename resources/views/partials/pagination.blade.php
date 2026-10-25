@if ($paginator->hasPages())
<nav aria-label="Pagination">
@if ($paginator->onFirstPage())<span class="muted">Previous</span>@else<a rel="prev" href="{{ $paginator->previousPageUrl() }}">← Previous</a>@endif
<span class="muted">Page {{ $paginator->currentPage() }}</span>
@if ($paginator->hasMorePages())<a rel="next" href="{{ $paginator->nextPageUrl() }}">Next →</a>@else<span class="muted">Next</span>@endif
</nav>
@endif
