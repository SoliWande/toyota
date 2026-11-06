<x-admin.layout title="Duyệt quản lý đại lý">
    <a href="{{ route('admin.dashboard') }}" class="text-red-700">← Quản trị</a>
    <h1 class="my-6 text-3xl font-bold">Duyệt quản lý đại lý</h1>
    <x-admin-feedback />
    @forelse ($accounts as $account)
        <section class="mb-4 rounded-2xl border border-slate-200 bg-white p-6">
            <h2 class="font-semibold">{{ $account->name }} · {{ $account->dealer?->name }}</h2>
            <p class="my-3 break-all text-sm text-slate-600">{{ $account->email }} · {{ $account->phone }} · {{ $account->created_at->format('d/m/Y H:i') }}</p>
            <form method="POST" action="{{ route('admin.dealer-registrations.review', [$account, 'approve']) }}">@csrf<button class="button-primary">Duyệt quản lý</button></form>
            <details class="mt-4"><summary class="cursor-pointer">Từ chối</summary>
                <form method="POST" action="{{ route('admin.dealer-registrations.review', [$account, 'reject']) }}">@csrf
                    <label for="reason-{{ $account->id }}" class="mt-3 block text-sm">Lý do hiển thị cho người đăng ký (tuỳ chọn)</label>
                    <textarea id="reason-{{ $account->id }}" name="rejection_reason" maxlength="2000" class="form-input" rows="2"></textarea>
                    <button class="mt-3 rounded-xl border border-red-300 px-5 py-3 text-red-700">Xác nhận từ chối</button>
                </form>
            </details>
        </section>
    @empty
        <p>Không có tài khoản quản lý chờ duyệt.</p>
    @endforelse
    {{ $accounts->links() }}
</x-admin.layout>
