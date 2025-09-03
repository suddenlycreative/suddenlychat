@php
    $bgClasses = ['bg--success', 'bg--info', 'bg--warning', 'bg--danger'];
    $hash      = crc32(@$contact->fullName);
    $bgClass   = $bgClasses[$hash % count($bgClasses)];
@endphp


@if (@$contact->image)
    <div class="contact_thumb ">
        <img src="{{ @$contact->imageSrc }}" alt="Image">
    </div>
@else
    <div class="contact_thumb   {{ $bgClass }}">
        {{ __(@$contact->fullNameShortForm) }}
    </div>
@endif
