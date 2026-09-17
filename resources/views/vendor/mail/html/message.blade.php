@props(['merk' => null, 'preheader' => null])
@php($m = \App\Support\Mail\MailBrand::resolve($merk))
<x-mail::layout :preheader="$preheader">
{{-- Kop: het logo van de school, of haar naam. Nooit dat van PlayerPath -
     de ouder heeft zich bij haar aangemeld, niet bij ons. --}}
<x-slot:header>
<x-mail::header :url="$m['url']" :merk="$m">
{{ $m['name'] }}
</x-mail::header>
</x-slot:header>

{{-- De inhoud --}}
{!! $slot !!}

{{-- Onderschrift --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Voet: wie de afzender is en hoe je hem bereikt. Een mail waar je niet op
     kunt reageren is een mail waarover gebeld wordt. --}}
<x-slot:footer>
<x-mail::footer>
@if ($m['fromSchool'])
{{ $m['name'] }}@if ($m['email']) · [{{ $m['email'] }}](mailto:{{ $m['email'] }})@endif @if ($m['phone']) · {{ $m['phone'] }}@endif

Verstuurd met PlayerPath.
@else
{{-- Zonder school is PlayerPath zelf de afzender: dan één regel, niet de
     naam en daarna nog eens "© PlayerPath". --}}
© {{ date('Y') }} {{ $m['name'] }}@if ($m['phone']) · Vragen? Bel {{ $m['phone'] }}@endif
@endif
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
