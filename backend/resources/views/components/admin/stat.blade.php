{{-- Thẻ số liệu của admin: <x-admin.stat icon="book" label="Truyện" :value="12" hint="..." tone="accent" href="..." /> --}}
@props(['icon', 'label', 'value', 'hint' => null, 'tone' => 'accent', 'href' => null])
@php $tag = $href ? 'a' : 'div'; @endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->class(['stat']) }}>
    <div class="stat-icon tone-{{ $tone }}"><i class="bi bi-{{ $icon }}"></i></div>
    <div style="min-width:0">
        <div class="stat-label">{{ $label }}</div>
        <div class="stat-value">{{ $value }}</div>
        @if ($hint)
            <div class="stat-hint">{{ $hint }}</div>
        @endif
        {{ $slot }}
    </div>
</{{ $tag }}>
