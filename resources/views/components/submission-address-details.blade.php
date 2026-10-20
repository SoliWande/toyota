@props(['submission'])
<div><dt class="text-slate-500">Tỉnh/Thành</dt><dd class="mt-1 break-words">{{ $submission->province_name ?? 'Chưa cung cấp' }}</dd></div>
<div><dt class="text-slate-500">Phường/Xã</dt><dd class="mt-1 break-words">{{ $submission->ward_name ?? 'Chưa cung cấp' }}</dd></div>
<div><dt class="text-slate-500">Địa chỉ chi tiết</dt><dd class="mt-1 whitespace-pre-wrap break-words">{{ $submission->address_line ?? 'Chưa cung cấp' }}</dd></div>
