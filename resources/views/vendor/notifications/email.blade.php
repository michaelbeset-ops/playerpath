{{--
    Elke melding die per mail uitgaat komt hier langs.

    Wat deze versie anders doet dan de standaard van Laravel:

    - **De aanhef is Nederlands en de afzender is de school.** "Hello!" en
      "Regards, PlayerPath" onder een mail van Keepersschool Rob is precies
      waarom een ouder hem niet herkent.
    - **De voorbeeldregel in de inbox is de eerste zin van de mail.** Zonder
      die regel toont een telefoon de eerste woorden van de opmaak; met deze
      regel staat er waar het over gaat, en dat bepaalt of hij geopend wordt.
    - **De knop draagt de merkkleur van de school** (zie Support\Mail\MailBrand).
--}}
@php
    // De voorbeeldregel: de eerste zin, zonder opmaaktekens. Markdown-sterretjes
    // in een inboxvoorbeeld lezen als een fout.
    $preheader = trim(preg_replace('/[*_`#\[\]]|\(https?:\/\/[^)]*\)/u', '', (string) ($introLines[0] ?? '')));
@endphp
<x-mail::message :merk="$merk ?? null" :preheader="$preheader">
{{-- Aanhef --}}
@if (! empty($greeting))
# {{ $greeting }}
@else
# {{ $level === 'error' ? 'Er ging iets mis' : 'Hallo' }}
@endif

{{-- De tekst --}}
@foreach ($introLines as $line)
{{ $line }}

@endforeach

{{-- Eén knop, nooit twee: kiezen betekent dat een deel op geen van beide klikt. --}}
@isset($actionText)
<?php
    $color = match ($level) {
        'error' => 'error',
        default => 'primary',
    };
?>
<x-mail::button :url="$actionUrl" :color="$color" :merk="$merk ?? null">
{{ $actionText }}
</x-mail::button>
@endisset

{{-- Wat er ná de knop nog bij hoort --}}
@foreach ($outroLines as $line)
{{ $line }}

@endforeach

{{-- Groet --}}
@if (! empty($salutation))
{{ $salutation }}
@else
Met vriendelijke groet,<br>
{{ \App\Support\Mail\MailBrand::resolve($merk ?? null)['name'] }}
@endif

{{-- Werkt de knop niet? --}}
@isset($actionText)
<x-slot:subcopy>
Werkt de knop "{{ $actionText }}" niet? Kopieer dan deze link naar je browser: <span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
@endisset
</x-mail::message>
