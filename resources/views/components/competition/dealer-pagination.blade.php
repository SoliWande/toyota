@props(['rows'])
@if ($rows->hasPages())
<nav class="wc-d-pagination" aria-label="Phân trang đại lý">
    <p>Trang {{ $rows->currentPage() }} / {{ $rows->lastPage() }} · {{ number_format($rows->total(), 0, ',', '.') }} đại lý</p>
    @if ($rows->onFirstPage())<span aria-disabled="true">‹ Trước</span>@else<a rel="prev" href="{{ $rows->previousPageUrl() }}">‹ Trước</a>@endif
    @if ($rows->hasMorePages())<a rel="next" href="{{ $rows->nextPageUrl() }}">Sau ›</a>@else<span aria-disabled="true">Sau ›</span>@endif
</nav>
@endif
