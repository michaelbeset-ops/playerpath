{{--
    De uitnodigingsmail.

    Bewust een eigen sjabloon en niet de standaard: deze mail moet er in de
    inbox uitzien alsof hij van de school komt, met haar logo en haar naam
    bovenaan. Alle andere mail draagt alleen de afzendernaam van de school;
    hier is het de eerste keer dat iemand ons ziet, en dan telt het meest of hij
    de afzender herkent.
--}}
@component('mail::message')
@if ($logo)
<p style="text-align: center; margin-bottom: 24px;">
<img src="{{ $logo }}" alt="{{ $school->name }}" style="max-height: 64px; max-width: 220px;">
</p>
@endif

# Hallo {{ $naam }}

@if ($isOuder)
@php($wie = $kinderen ? implode(' en ', $kinderen) : 'je kind')
{{ $school->name }} gebruikt PlayerPath om de ontwikkeling van spelers bij te houden. In je eigen account zie je de spelerskaart en de voortgang van {{ $wie }}, wanneer de trainingen zijn en wat er nog openstaat.

Klik hieronder om je account te activeren en een wachtwoord te kiezen.
@else
{{ $school->name }} gebruikt PlayerPath om trainingen te plannen en spelers te beoordelen. Met je eigen account zie je jouw trainingen, vink je aanwezigheid af en vul je na afloop in een halve minuut de rapporten in.

Klik hieronder om je account te activeren en een wachtwoord te kiezen.
@endif

@component('mail::button', ['url' => $url])
Account activeren
@endcomponent

Deze uitnodiging is geldig tot {{ $verlooptOp }}. Werkt de knop niet meer, vraag {{ $school->name }} dan om een nieuwe.

Met vriendelijke groet,<br>
{{ $school->name }}

@slot('subcopy')
Werkt de knop niet? Kopieer dan deze link naar je browser: {{ $url }}
@endslot
@endcomponent
