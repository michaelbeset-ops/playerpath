@props(['merk' => null, 'preheader' => null])
@php($m = \App\Support\Mail\MailBrand::resolve($merk))
<x-mail::layout>
{{-- Kop --}}
<x-slot:header>
<x-mail::header :url="$m['url']" :merk="$m">
{{ $m['name'] }}
</x-mail::header>
</x-slot:header>

{{-- De inhoud --}}
{{ $slot }}

{{-- Onderschrift --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{{ $subcopy }}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Voet --}}
<x-slot:footer>
<x-mail::footer>
@if ($m['platform'])
{{ $m['name'] }}@if ($m['email']) · {{ $m['email'] }}@endif @if ($m['phone']) · {{ $m['phone'] }}@endif

Verstuurd met PlayerPath.
@else
© {{ date('Y') }} {{ $m['name'] }}
@endif
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
