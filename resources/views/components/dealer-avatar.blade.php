@props(['user'])
<span {{ $attributes->class(['wc-dealer-avatar']) }}>
    <span aria-label="Ảnh đại diện mặc định">{{ mb_substr($user->name, 0, 1) }}</span>
    @if ($user->avatar_path)
        <img src="{{ route('profile.avatar') }}" alt="Ảnh đại diện của {{ $user->name }}" width="96" height="96" data-fallback-image>
    @endif
</span>
