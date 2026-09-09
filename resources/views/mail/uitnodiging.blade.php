{{--
    De uitnodigingsmail: de eerste keer dat iemand deze school in zijn inbox
    ziet. Drie dingen en verder niets — van wie hij komt, wat je eraan hebt,
    en één knop.

    Het logo staat niet meer hier maar in de gedeelde mailschil (zie
    resources/views/vendor/mail); anders stond het er bij deze ene mail twee
    keer, en zou een merkwijziging op twee plekken moeten gebeuren.
--}}
<x-mail::message :merk="$merk" :preheader="$school->name.' nodigt je uit voor je eigen account.'">
# Hallo {{ $naam }}

@if ($isOuder)
@php($wie = $kinderen ? implode(' en ', $kinderen) : 'je kind')
{{ $school->name }} gebruikt PlayerPath om de ontwikkeling van spelers bij te houden. In je eigen account zie je de spelerskaart en de voortgang van {{ $wie }}, wanneer de trainingen zijn en wat er nog openstaat.

Klik hieronder om je account te activeren en een wachtwoord te kiezen.
@else
{{ $school->name }} gebruikt PlayerPath om trainingen te plannen en spelers te beoordelen. Met je eigen account zie je jouw trainingen, vink je aanwezigheid af en vul je na afloop in een halve minuut de rapporten in.

Klik hieronder om je account te activeren en een wachtwoord te kiezen.
@endif

<x-mail::button :url="$url" :merk="$merk">
Account activeren
</x-mail::button>

Deze uitnodiging is geldig tot {{ $verlooptOp }}. Werkt de knop niet meer, vraag {{ $school->name }} dan om een nieuwe.

Met vriendelijke groet,<br>
{{ $school->name }}

<x-slot:subcopy>
Werkt de knop niet? Kopieer dan deze link naar je browser: <span class="break-all">{{ $url }}</span>
</x-slot:subcopy>
</x-mail::message>
