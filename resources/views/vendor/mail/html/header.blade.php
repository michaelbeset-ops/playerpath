@props(['url', 'merk' => null])
@php($m = \App\Support\Mail\MailBrand::resolve($merk))
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
@if ($m['logo'])
@if (! empty($m['logoWidth']))
{{-- Ons eigen logo met een vaste maat: Outlook negeert CSS-breedtes op
     plaatjes en zou hem anders 1060 pixels breed tonen. --}}
<img src="{{ $m['logo'] }}" class="logo" alt="{{ $m['name'] }}" width="{{ $m['logoWidth'] }}" height="{{ $m['logoHeight'] ?? '' }}" style="display: block; width: {{ $m['logoWidth'] }}px; max-width: 100%; height: auto; margin: 0 auto; border: 0;">
@else
<img src="{{ $m['logo'] }}" class="logo" alt="{{ $m['name'] }}">
@endif
@else
{{-- Zonder logo staat de naam er in de merkkleur: dan is er tenminste
     iets herkenbaars boven de mail. --}}
<span style="color: {{ $m['color'] }}; font-size: 18px; font-weight: 700; letter-spacing: -0.01em;">{!! $slot !!}</span>
@endif
</a>
</td>
</tr>
