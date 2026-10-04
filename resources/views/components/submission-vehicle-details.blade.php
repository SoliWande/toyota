@props(['submission'])
<section class="mt-6 border-t border-slate-100 pt-6" aria-labelledby="vehicle-title-{{ $submission->id }}">
    <h2 id="vehicle-title-{{ $submission->id }}" class="font-semibold">Thông tin xe Toyota</h2>
    <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
        <div><dt class="text-slate-500">Dòng xe</dt><dd class="mt-1 break-words">{{ config('vehicles.toyota_models.'.$submission->vehicle_model, 'Chưa cung cấp') }}</dd></div>
        <div><dt class="text-slate-500">Năm đăng ký lần đầu</dt><dd class="mt-1">{{ $submission->first_registration_year ?? 'Chưa cung cấp' }}</dd></div>
        <div><dt class="text-slate-500">Màu xe</dt><dd class="mt-1 break-words">{{ $submission->vehicle_color ?? 'Chưa cung cấp' }}</dd></div>
        <div><dt class="text-slate-500">Biển số xe</dt><dd class="mt-1 break-words">{{ $submission->license_plate ?? 'Chưa cung cấp' }}</dd></div>
    </dl>
    <h3 class="mt-5 text-sm font-medium">Ảnh bằng chứng</h3>
    @if ($submission->evidence_image_path)
        <a href="{{ route('submissions.evidence', $submission) }}" target="_blank" rel="noopener noreferrer" class="mt-3 block rounded-xl focus-visible:outline-red-600" aria-label="Mở ảnh bằng chứng của {{ $submission->customer_name }}">
            <img src="{{ route('submissions.evidence', $submission) }}" alt="Ảnh bằng chứng của khai báo {{ $submission->customer_name }}" loading="lazy" class="max-h-80 w-full rounded-xl border border-slate-200 bg-slate-50 object-contain">
        </a>
        <p class="mt-2 text-xs text-slate-500">Ảnh riêng tư. Chọn ảnh để xem kích thước đầy đủ.</p>
    @else
        <p class="mt-2 text-sm text-amber-800">Khai báo cũ chưa có ảnh bằng chứng.</p>
    @endif
</section>
