@props([
    'url',
    'color' => 'primary',
    'align' => 'center',
    'merk' => null,
])
@php
    $m = \App\Support\Mail\MailBrand::resolve($merk);

    // Een foutknop blijft rood, ook bij een school met een eigen kleur: "er
    // ging iets mis" hoort overal hetzelfde te betekenen. Zie CLAUDE.md over
    // white-label - statuskleuren zijn van PlayerPath.
    $achtergrond = $color === 'error' ? '#B42318' : $m['color'];
    $tekst = $color === 'error' ? '#FFFFFF' : $m['onColor'];
@endphp
<table class="action" align="{{ $align }}" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="{{ $align }}">
<table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="{{ $align }}">
<table class="button-shell" border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td>
{{-- Padding in plaats van de randentruc van Laravel: dat geeft een knop die
     op elke telefoon minstens 44 pixels hoog is, de ondergrens uit CLAUDE.md. --}}
<a href="{{ $url }}" class="button button-{{ $color }}" target="_blank" rel="noopener" style="background-color: {{ $achtergrond }}; border: none; border-radius: 10px; color: {{ $tekst }}; display: inline-block; font-size: 16px; font-weight: 600; line-height: 24px; padding: 14px 28px; text-align: center; text-decoration: none;">{!! $slot !!}</a>
</td>
</tr>
</table>
</td>
</tr>
</table>
</td>
</tr>
</table>
