@php($document ??= require resource_path('content/campaign-rules.php'))
<x-dealer-registration.layout title="Thể lệ đầy đủ" :rules="true" :description="$document['title']">
    <div class="dr-container dr-full-rules">
        <header class="dr-rules-heading">
            <p class="dr-rules-eyebrow">THỂ LỆ CHƯƠNG TRÌNH</p>
            <h1>Cộng đồng Toyota Veloz &amp; Hilux</h1>
            <p class="dr-rules-date">{{ $document['introduction'] }}</p>
        </header>
            <div class="dr-rules-split">
                <article class="dr-rules-document" aria-label="Thể lệ chương trình">
                    @foreach ($document['sections'] as $section)
                        <section id="rules-{{ $section['id'] }}" tabindex="-1" aria-labelledby="rules-heading-{{ $section['id'] }}">
                            <span class="dr-rules-number" aria-hidden="true">{{ sprintf('%02d', $loop->iteration) }}</span>
                            <div><h2 id="rules-heading-{{ $section['id'] }}"><span class="dr-rules-mobile-number">{{ $loop->iteration }}. </span>{{ $section['title'] }}</h2><p>{{ $section['body'] }}</p></div>
                        </section>
                    @endforeach
                </article>
                <aside class="dr-rules-toc">
                    <nav aria-label="Mục lục thể lệ"><ol>@foreach ($document['sections'] as $section)<li><a href="#rules-{{ $section['id'] }}"><span aria-hidden="true">{{ sprintf('%02d', $loop->iteration) }}</span>{{ $section['title'] }}</a></li>@endforeach</ol></nav>
                    <a class="dr-button dr-rules-join" href="{{ route('dealers.index') }}">Đăng ký tham gia</a>
                </aside>
            </div>
    </div>
</x-dealer-registration.layout>
