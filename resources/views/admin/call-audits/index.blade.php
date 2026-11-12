<x-admin.layout title="Kiểm tra ngẫu nhiên">
    @vite(['resources/css/admin-call-audits.css', 'resources/js/admin-call-audits.js'])
    <div class="ca-page" data-call-audit data-api="{{ url('/admin/call-audits') }}" data-month="{{ $month }}" data-purpose="{{ $purpose ?? 'general_audit' }}" data-batch="{{ $batchId ?? '' }}">
        <header class="ca-heading"><div><h1>Kiểm tra ngẫu nhiên</h1><p data-subtitle></p></div><section class="ca-progress" hidden><div><span>Đã gọi</span><strong data-progress></strong></div><div class="ca-track"><i data-progress-bar></i></div></section></header>
        <nav class="ca-tabs" aria-label="Kiểm tra ngẫu nhiên">
            @foreach (['needs_call' => 'Cần gọi', 'recall' => 'Gọi lại', 'completed' => 'Đã xong'] as $key => $label)
                <button type="button" data-tab="{{ $key }}" @if ($key === 'needs_call') aria-current="true" @endif>{{ $label }}<b data-tab-count="{{ $key }}"></b></button>
            @endforeach
        </nav>
        <div class="ca-split"><div class="ca-left"><div class="ca-list" data-list></div><section class="ca-dealers" hidden><h2>TỈ LỆ SAI THEO ĐẠI LÝ</h2><div data-dealers></div></section></div>
            <form class="ca-workspace" data-workspace hidden>
                <header class="ca-customer"><div><h2 data-name></h2><p data-description></p></div><button type="button" class="ca-phone" data-start><span><img src="{{ asset('admin/c08/phone.png') }}" width="14" height="14" alt=""></span><b data-phone></b></button></header>
                <section class="ca-script"><h3>KỊCH BẢN GỌI, KHOẢNG 30 GIÂY</h3><p data-script></p></section>
                <fieldset class="ca-questions" disabled>
                    @for ($i = 1; $i <= 3; $i++)
                        <div class="ca-question"><b>{{ $i }}</b><p data-question="{{ $i }}"></p><div class="ca-answers">
                            @foreach (['true' => 'Đúng', 'false' => 'Sai'] as $value => $label)
                                <label><input type="radio" name="answer_{{ $i }}" value="{{ $value }}"><span><img src="{{ asset('admin/c08/'.($value === 'true' ? 'tick' : 'cross').'.png') }}" width="14" height="14" alt=""><img class="ca-white-tick" src="{{ asset('admin/c08/tick-white.png') }}" width="14" height="14" alt="">{{ $label }}</span></label>
                            @endforeach
                        </div></div>
                    @endfor
                </fieldset>
                <fieldset class="ca-result" disabled><h3>KẾT QUẢ</h3><div class="ca-outcomes">
                    @foreach (['correct' => 'Đúng thông tin', 'wrong' => 'Sai thông tin', 'no_answer' => 'Không nghe máy'] as $key => $label)
                        <label><input type="radio" name="outcome" value="{{ $key }}"><span><i></i>{{ $label }}</span></label>
                    @endforeach
                </div><textarea name="note" maxlength="2000" placeholder="Ghi chú (tuỳ chọn)" aria-label="Ghi chú (tuỳ chọn)"></textarea><div class="ca-note"><img src="{{ asset('admin/c08/info.png') }}" width="16" height="16" alt=""><p data-warning data-wrong-warning="Thông tin khách hàng chưa khớp. Kết quả sẽ được ghi nhận để kiểm tra và xử lý.">Không nghe máy: tự hẹn gọi lại sau 1 ngày; 2 lần không nghe thì bỏ qua, không tính là sai.</p></div></fieldset>
                <footer><button type="submit" class="ca-save" disabled>Lưu kết quả</button></footer>
            </form>
        </div>
    </div>
    <div class="ad-toast ca-toast" data-admin-toast data-call-toast role="status" hidden><span class="ad-toast-icon"><img src="{{ asset('admin/c08/toast-tick.png') }}" width="14" height="14" alt=""></span><span>Đã lưu: Đúng thông tin · chuyển sang khách tiếp theo</span></div>
</x-admin.layout>
