<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="rtrim((string) config('app.url'), '/')">
{{ config('app.name') }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer: why this email came, where to get help (system setting "support_email"). Markdown: keep these lines unindented. --}}
<x-slot:footer>
<x-mail::footer>
{{ __('This email was sent automatically by :app.', ['app' => config('app.name')]) }}
@if (filled(config('app.support_email')))
{{ __('Need help? Write to') }} [{{ config('app.support_email') }}](mailto:{{ config('app.support_email') }}).
@endif

© {{ date('Y') }} {{ config('app.name') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
