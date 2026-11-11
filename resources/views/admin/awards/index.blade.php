<x-admin.layout title="Vinh danh">
    @vite('resources/css/admin-awards.css')
    @php
        $periodLabel = fn ($award) => $award->period_type === \App\Enums\AwardPeriod::Monthly
            ? 'Tháng '.$award->period_start->format('n/Y')
            : $award->period_type->label().' '.$award->period_start->format('d/m').' – '.$award->period_end->subDay()->format('d/m/Y');
        $vn = fn ($date) => $date->setTimezone('Asia/Ho_Chi_Minh');
    @endphp
    <div class="ac-page">
        <header class="ac-head"><h1>Vinh danh</h1><p>Mỗi tháng một kỳ, chốt theo giờ Việt Nam. Chỉ công bố sau khi kiểm tra top 3.</p></header>
        <div class="ac-split">
            <nav class="ac-periods" aria-label="Các kỳ">
                @foreach ($awards as $award)
                    <a href="{{ route('admin.awards.index', array_merge(\Illuminate\Support\Arr::except($filters, ['selected', 'page']), ['selected' => $award->id])) }}" @class(['ac-period', 'ac-period-selected' => $selected?->is($award)]) @if ($selected?->is($award)) aria-current="page" @endif>
                        <strong>{{ $periodLabel($award) }}</strong>
                        <small>{{ $award->published_at ? 'Công bố '.$vn($award->published_at)->format('d/m') : 'Chốt số liệu '.$vn($award->period_end)->format('d/m, H:i') }}</small>
                        @if ($award->published_at)<x-admin.badge variant="approved">Đã công bố</x-admin.badge>@endif
                    </a>
                @endforeach
            </nav>
            @if ($selected)
                <section class="ac-detail" data-award-id="{{ $selected->id }}">
                    <header><h2>Kỳ {{ mb_strtolower($periodLabel($selected)) }}</h2><a href="{{ route('admin.awards.show', $selected) }}" class="ac-preview" target="_blank" rel="noopener noreferrer"><img src="{{ asset('admin/icons/c07-preview.png') }}" width="16" height="16" alt="">Xem trước trang vinh danh</a></header>
                    <div class="ac-steps">
                        <div class="ac-step"><span class="ac-step-number">1</span><div><strong>Chốt số liệu</strong><small>{{ $vn($selected->period_end)->format('d/m, H:i') }} giờ VN</small></div></div>
                        <div class="ac-step"><span class="ac-step-number">2</span><div><strong>Kiểm tra top 3</strong><small>Gọi 5 khách mỗi đại lý</small></div></div>
                        <div class="ac-step ac-step-publish"><span @class(['ac-step-number', 'ac-complete' => $selected->published_at])>@if ($selected->published_at)<img src="{{ asset('admin/icons/c07-check.png') }}" width="14" height="14" alt="">@else 3 @endif</span><div><strong>Công bố</strong><small>Hiện trên trang Vinh danh</small></div></div>
                    </div>
                    <div class="ac-table" role="table" aria-label="Kiểm tra top 3">
                        <div class="ac-row ac-table-head" role="row"><span role="columnheader">HẠNG</span><span role="columnheader">ĐẠI LÝ</span><span role="columnheader">ĐIỂM</span><span role="columnheader">MẪU GỌI</span><span role="columnheader">KẾT QUẢ</span><span role="columnheader" aria-label="Trạng thái kiểm tra"></span></div>
                        @foreach ($dealers as $winner)
                            <div class="ac-row" role="row" data-award-dealer>
                                <div role="cell" aria-label="Hạng {{ $winner['rank'] }}"><span class="ac-rank ac-rank-{{ $winner['rank'] }}">@if (in_array((int) $winner['rank'], [1, 2, 3], true))<img src="{{ asset('admin/icons/c07-rank-'.$winner['rank'].'.png') }}" width="14" height="14" alt="">@else{{ $winner['rank'] }}@endif</span></div>
                                <strong role="cell">{{ $winner['dealer_name_snapshot'] }}</strong>
                                <b role="cell">{{ number_format($winner['score'], 0, ',', '.') }}</b>
                                <span role="cell" class="ac-no-evidence">—</span><span role="cell" class="ac-no-evidence">—</span><span role="cell"></span>
                            </div>
                        @endforeach
                    </div>
                    <div class="ac-threshold"><img src="{{ asset('admin/icons/c07-info.png') }}" width="16" height="16" alt=""><p>Ngưỡng loại: sai quá [x]% số khách được gọi (chờ TMV chốt). Không nghe máy 2 lần thì không tính là sai.</p></div>
                    <footer class="ac-actions"><button type="button" class="ac-call" disabled><img src="{{ asset('admin/icons/c07-phone.png') }}" width="16" height="16" alt="">Mở danh sách gọi</button><div class="ac-spacer"></div><p><img src="{{ asset('admin/icons/c07-lock.png') }}" width="14" height="14" alt="">Mở khi cả 3 đại lý đã kiểm tra xong</p><button type="button" class="ac-publish" disabled>Công bố kỳ</button></footer>
                </section>
            @endif
        </div>
    </div>
</x-admin.layout>
