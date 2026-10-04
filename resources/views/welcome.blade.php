<x-public.layout>
    @php($joinUrl = auth()->check() ? route(auth()->user()->homeRoute()) : route('register'))
    <section class="public-container grid items-center gap-10 pb-12 pt-12 lg:grid-cols-[1.1fr_1fr] lg:gap-16 lg:pb-20 lg:pt-20" aria-labelledby="hero-heading">
        <div>
            <p class="public-eyebrow"><span class="mr-2 inline-block h-1.5 w-1.5 rounded-full bg-red-700" aria-hidden="true"></span>Cộng đồng Toyota Veloz & Hilux</p>
            <h1 id="hero-heading" class="mt-6 text-[clamp(2.5rem,6vw,4.5rem)] font-semibold leading-[1.08] tracking-[-0.055em]">Mỗi kết nối.<br><span class="text-red-700">Một hành trình mới.</span></h1>
            <p class="mt-6 max-w-lg text-base leading-7 text-stone-600 sm:text-lg sm:leading-8">Cùng nhân viên kinh doanh và các đại lý Toyota kết nối thành viên với cộng đồng Veloz & Hilux. Lan tỏa giá trị, ghi nhận nỗ lực và cùng nhau tiến xa hơn.</p>
            <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-7">
                <a href="{{ $joinUrl }}" class="public-button">Tham gia ngay <x-public.arrow /></a>
                <a href="#hanh-trinh" class="inline-flex min-h-12 items-center justify-center gap-3 text-sm font-semibold text-stone-700">Khám phá chương trình <span aria-hidden="true">↓</span></a>
            </div>
            <p class="mt-7 text-xs leading-6 text-stone-500">Dành cho Sales tại các đại lý Toyota.<br>Khai báo cần có thông tin xe Toyota và ảnh bằng chứng.</p>
        </div>
        <x-public.journey-art />
    </section>

    <section aria-label="Chương trình qua những con số" class="public-container">
        <dl class="grid grid-cols-3 divide-x divide-stone-200 rounded-2xl border border-stone-200 bg-white text-center sm:text-left">
            <x-public.stat :value="$stats['sales']" label="Sales đang hoạt động" detail="Cùng lan tỏa tinh thần cộng đồng" />
            <x-public.stat :value="$stats['dealers']" label="Đại Lý đang hoạt động" detail="Mạng lưới cùng đồng hành" />
            <x-public.stat :value="$stats['members']" label="Thành viên đã ghi nhận" detail="Khai báo đã được xác minh và duyệt" />
        </dl>
    </section>

    <section id="hanh-trinh" class="public-container public-section" aria-labelledby="journey-heading">
        <div class="max-w-2xl"><p class="public-eyebrow">Cùng bắt đầu</p><h2 id="journey-heading" class="public-heading mt-4">Từ lời mời nhỏ.<br>Đến cộng đồng lớn.</h2></div>
        <div class="mt-10 grid gap-8 sm:grid-cols-3 sm:gap-10">
            @foreach ([['01', 'Đăng ký tham gia', 'Tạo tài khoản Sales, chọn đại lý Toyota và chờ admin duyệt tài khoản.'], ['02', 'Kết nối thành viên', 'Mời khách hàng tham gia cộng đồng Facebook, sau đó khai báo thông tin thành viên.'], ['03', 'Ghi nhận & vinh danh', 'Admin xác minh khai báo. Thành tích đã duyệt được tính vào bảng xếp hạng Sales và Đại Lý.']] as [$number, $title, $description])
                <div class="border-t border-stone-300 pt-5"><span class="text-xs font-semibold tracking-widest text-red-700">{{ $number }}</span><h3 class="mt-4 text-lg font-semibold">{{ $title }}</h3><p class="mt-3 text-sm leading-7 text-stone-600">{{ $description }}</p></div>
            @endforeach
        </div>
    </section>

    <section id="xep-hang" class="border-y border-stone-200 bg-[#f0efea]" aria-labelledby="ranking-heading">
        <div class="public-container public-section">
            <div class="flex flex-col justify-between gap-6 sm:flex-row sm:items-end">
                <div><p class="public-eyebrow">Những người dẫn nhịp</p><h2 id="ranking-heading" class="public-heading mt-4">Kết nối tạo nên thành tích.</h2><p class="mt-4 max-w-lg text-sm leading-7 text-stone-600">Ghi nhận nỗ lực của những Sales và Đại Lý đang lan tỏa cộng đồng. Chỉ thành viên đã được duyệt mới tính vào điểm số.</p></div>
                <nav aria-label="Kỳ xếp hạng xem trước" class="flex shrink-0 self-start rounded-full border border-stone-300 bg-white p-1">
                    @foreach ([\App\Enums\LeaderboardPeriod::Week, \App\Enums\LeaderboardPeriod::Month] as $option)
                        <a href="{{ route('home', ['period' => $option->value]) }}#xep-hang" @if ($period === $option) aria-current="page" @endif class="rounded-full px-5 py-3 text-sm font-semibold {{ $period === $option ? 'bg-stone-900 text-white' : 'text-stone-600 hover:text-stone-900' }}">{{ $option->label() }}</a>
                    @endforeach
                </nav>
            </div>
            <div class="mt-8 grid gap-5 lg:grid-cols-2"><x-public.ranking-preview title="Top Sales" :leaders="$topSales" type="sales" /><x-public.ranking-preview title="Top Đại Lý" :leaders="$topDealers" type="dealer" /></div>
            <div class="mt-7 flex flex-col justify-between gap-4 sm:flex-row sm:items-center"><p class="text-xs text-stone-500">Theo ngày khai báo · Múi giờ {{ config('app.timezone') }}</p><a href="{{ route('leaderboard', ['period' => $period->value]) }}" class="inline-flex min-h-12 items-center gap-3 text-sm font-semibold">Xem bảng xếp hạng đầy đủ <x-public.arrow /></a></div>
        </div>
    </section>

    <section class="public-container public-section" aria-labelledby="award-heading">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p class="public-eyebrow">Dấu ấn cộng đồng</p><h2 id="award-heading" class="public-heading mt-4">Nỗ lực xứng đáng được ghi nhận.</h2></div><a href="{{ route('awards.index') }}" class="inline-flex min-h-12 shrink-0 items-center gap-3 text-sm font-semibold">Lịch sử vinh danh <x-public.arrow /></a></div>
        @if ($latestAward)
            <div class="mt-8 border-t border-stone-200 pt-7"><div class="mb-7 flex flex-col justify-between gap-3 sm:flex-row sm:items-center"><div><p class="public-eyebrow">Kỳ công bố gần nhất</p><h3 class="mt-3 break-words text-xl font-semibold">{{ $latestAward->title }}</h3><p class="mt-2 text-xs text-stone-500">{{ $latestAward->period_start->format('d/m/Y') }} – {{ $latestAward->period_end->subDay()->format('d/m/Y') }} · Công bố {{ $latestAward->published_at->format('d/m/Y') }}</p></div><a href="{{ route('awards.show', $latestAward) }}" class="inline-flex min-h-12 items-center gap-3 text-sm font-semibold text-red-700">Xem kỳ vinh danh <x-public.arrow /></a></div>
                <x-award-results :sales="$latestAward->winners->filter(fn ($winner) => $winner->winner_type === \App\Enums\AwardWinnerType::Sales)" :dealers="$latestAward->winners->filter(fn ($winner) => $winner->winner_type === \App\Enums\AwardWinnerType::Dealer)" />
            </div>
        @else
            <div class="mt-8 flex flex-col gap-8 rounded-[2rem] bg-[#202a28] p-7 text-white sm:flex-row sm:items-center sm:justify-between sm:p-10"><div class="max-w-xl"><p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#c7d3c8]">Hành trình đang bắt đầu</p><h3 class="mt-4 text-2xl font-medium tracking-tight sm:text-3xl">Kết nối hôm nay.<br>Tạo nên dấu ấn ngày mai.</h3><p class="mt-4 text-sm leading-7 text-white/70">Chưa có kỳ vinh danh được công bố. Cùng tham gia và góp những kết nối đầu tiên cho cộng đồng.</p></div><span class="flex h-20 w-20 shrink-0 items-center justify-center rounded-full border border-white/20" aria-hidden="true"><svg width="35" height="35" viewBox="0 0 32 32" fill="none" stroke="#d9dfcb" stroke-width="1.2"><path d="m16 4 3.8 7.8 8.6 1.2-6.2 6 1.5 8.5-7.7-4-7.7 4 1.5-8.5-6.2-6 8.6-1.2L16 4Z" /></svg></span></div>
        @endif
    </section>

    <section id="the-le" class="public-container border-t border-stone-200 pb-16 pt-12 sm:pb-20 sm:pt-16" aria-labelledby="rules-heading">
        <div class="grid gap-8 lg:grid-cols-[1fr_1.4fr]"><div><p class="public-eyebrow">Minh bạch & công bằng</p><h2 id="rules-heading" class="public-heading mt-4">Thể lệ chương trình.</h2><p class="mt-4 max-w-sm text-sm leading-7 text-stone-600">Những nguyên tắc để mỗi kết nối được ghi nhận đúng người, đúng thời điểm.</p></div>
            <div class="divide-y divide-stone-200 border-y border-stone-200">
                @foreach (['Ai có thể tham gia?' => 'Nhân viên kinh doanh các đại lý Toyota đăng ký tài khoản, chọn đại lý và được admin duyệt trước khi sử dụng hệ thống. Khai báo khách hàng cần thông tin xe Toyota và 1 ảnh bằng chứng, không giới hạn dòng Veloz/Hilux.', 'Thành tích được ghi nhận thế nào?' => 'Sales khai báo thành viên đã tham gia cộng đồng Facebook. Admin xác minh thủ công; mỗi khai báo approved được tính một điểm. Một Facebook profile chỉ được approved một lần toàn hệ thống.', 'Điểm thuộc tuần hoặc tháng nào?' => 'Điểm tính theo thời điểm khai báo, không theo ngày admin duyệt. Điểm Đại Lý là tổng khai báo approved của Sales thuộc Đại Lý đó. Khi bằng điểm, ưu tiên người đạt điểm sớm hơn theo thời điểm khai báo.', 'Tôi có thể sửa khai báo không?' => 'Sales được sửa hoặc xóa khai báo của mình khi đang chờ duyệt. Sau khi đã được duyệt hoặc từ chối, khai báo chỉ được xem. Kết quả vinh danh đã công bố được giữ nguyên lịch sử.'] as $question => $answer)
                    <details class="group py-5"><summary class="flex min-h-6 cursor-pointer list-none items-center justify-between gap-5 text-sm font-semibold">{{ $question }}<span class="text-xl font-normal text-stone-500 group-open:hidden" aria-hidden="true">+</span><span class="hidden text-xl font-normal text-stone-500 group-open:inline" aria-hidden="true">−</span></summary><p class="mt-4 max-w-xl text-sm leading-7 text-stone-600">{{ $answer }}</p></details>
                @endforeach
            </div>
        </div>
    </section>

    <section class="border-t border-red-100 bg-[#f3e9e3] py-14 sm:py-20" aria-labelledby="join-heading"><div class="public-container flex flex-col justify-between gap-7 lg:flex-row lg:items-center"><div><p class="public-eyebrow">Bạn là một phần của hành trình</p><h2 id="join-heading" class="public-heading mt-4">Cộng đồng lớn hơn.<br>Bắt đầu từ lời mời của bạn.</h2><p class="mt-4 text-sm leading-7 text-stone-600">Cùng đại lý Toyota của bạn kết nối thành viên Veloz & Hilux.</p></div><a href="{{ $joinUrl }}" class="public-button self-start lg:shrink-0">Tham gia ngay <x-public.arrow /></a></div></section>
</x-public.layout>
