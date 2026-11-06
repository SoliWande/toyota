@props(['rows', 'label' => 'Phân trang bảng xếp hạng'])
<nav class="wc-lb-pagination" aria-label="{{ $label }}">
    <p>Trang {{ number_format($rows->currentPage(), 0, ',', '.') }} / {{ number_format($rows->lastPage(), 0, ',', '.') }}</p>
    @if ($rows->onFirstPage())
        <span class="wc-lb-prev" aria-disabled="true">‹ Trước</span>
    @else
        <a class="wc-lb-prev" href="{{ $rows->previousPageUrl() }}" rel="prev">‹ Trước</a>
    @endif
    @if ($rows->hasMorePages())
        <a class="wc-lb-next" href="{{ $rows->nextPageUrl() }}" rel="next">Sau ›</a>
    @else
        <span class="wc-lb-next" aria-disabled="true">Sau ›</span>
    @endif
</nav>
