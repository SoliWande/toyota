@props(['rows'])
@if ($rows->hasPages())
<nav class="wc-staff-page-links" aria-label="Phân trang nhân viên">
    <span>Trang {{ $rows->currentPage() }} / {{ $rows->lastPage() }} · {{ number_format($rows->total()) }} nhân viên</span>
    @if ($rows->onFirstPage())<span aria-disabled="true">‹ Trước</span>@else<a rel="prev" href="{{ $rows->previousPageUrl() }}">‹ Trước</a>@endif
    @if ($rows->hasMorePages())<a rel="next" href="{{ $rows->nextPageUrl() }}">Sau ›</a>@else<span aria-disabled="true">Sau ›</span>@endif
</nav>
@endif
