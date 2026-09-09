@props(['url', 'merk' => null])
@php($m = \App\Support\Mail\MailBrand::resolve($merk))
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
@if ($m['logo'])
<img src="{{ $m['logo'] }}" class="logo" alt="{{ $m['name'] }}">
@else
{{-- Zonder logo staat de naam er in de merkkleur: dan is er tenminste
     iets herkenbaars boven de mail. --}}
<span style="color: {{ $m['color'] }}; font-size: 18px; font-weight: 700; letter-spacing: -0.01em;">{!! $slot !!}</span>
@endif
</a>
</td>
</tr>
