@props(['people', 'count' => null])
<section class="wc-staff-card wc-pending-staff">
    <header><h2>Nhân viên chờ duyệt <span>{{ $count ?? $people->count() }}</span></h2><p>Xác nhận nhân viên thuộc đại lý trước khi duyệt.</p></header>
    @forelse ($people as $person)
        <div class="wc-staff-request"><div class="wc-staff-person"><span class="wc-staff-initial">{{ mb_substr($person->name, 0, 1) }}</span><div><strong>{{ $person->name }}</strong><p>{{ $person->email }}</p>@if ($person->phone)<small>{{ $person->phone }}</small>@endif<small>Đăng ký {{ $person->created_at?->format('d/m/Y') }}</small></div></div>
            <div class="wc-staff-review-actions">
                <details><summary class="wc-staff-button wc-staff-outline">Từ chối</summary><form class="wc-reject-form" method="POST" action="{{ route('dealer.staff.review', [$person, 'reject']) }}" data-account-form>@csrf
                    <label for="staff-reason-{{ $person->id }}">Lý do hiển thị cho người đăng ký (tuỳ chọn)</label><textarea id="staff-reason-{{ $person->id }}" name="rejection_reason" maxlength="2000" rows="3"></textarea>
                    <button class="wc-staff-button" data-account-submit>Xác nhận từ chối</button>
                </form></details>
                <form method="POST" action="{{ route('dealer.staff.review', [$person, 'approve']) }}" data-account-form>@csrf<button class="wc-staff-button" data-account-submit>Duyệt</button></form>
            </div>
        </div>
    @empty<p class="wc-staff-empty">Không có nhân viên chờ duyệt.</p>@endforelse
    @if (($count ?? 0) > $people->count())<a href="{{ route('dealer.sales.index', ['status' => 'pending']) }}">Xem tất cả {{ $count }} yêu cầu →</a>@endif
</section>
