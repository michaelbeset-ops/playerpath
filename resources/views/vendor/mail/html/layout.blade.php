@props(['preheader' => null])
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<title>{{ config('app.name') }}</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<style>
/*
    Alleen wat je niet kunt inlinen. De rest staat in themes/playerpath.css en
    wordt bij het versturen in de opmaak gezet, want een groot deel van de
    mailprogramma's gooit een <style> weg.

    Mobiel eerst: de meeste ouders openen dit op een telefoon van 375 pixels.
*/
@media only screen and (max-width: 600px) {
.inner-body {
width: 100% !important;
border-radius: 12px !important;
}

.footer {
width: 100% !important;
}

.content-cell {
padding: 24px 20px !important;
}

h1 {
font-size: 20px !important;
}
}

@media only screen and (max-width: 500px) {
/* Een knop die de volle breedte pakt mis je niet met een duim. De omhullende
   tabel moet mee: die krimpt anders naar de tekst en dan doet de knop dat ook. */
.button-shell {
width: 100% !important;
}

.button {
display: block !important;
width: 100% !important;
box-sizing: border-box !important;
}
}
</style>
{!! $head ?? '' !!}
</head>
<body>

{{-- De regel die je in de inbox onder het onderwerp ziet staan. --}}
@if (filled($preheader))
<div class="preheader" style="display: none; max-height: 0; overflow: hidden; mso-hide: all;">{{ $preheader }}</div>
@endif

<table class="wrapper" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="center">
<table class="content" width="100%" cellpadding="0" cellspacing="0" role="presentation">
{!! $header ?? '' !!}

<!-- Email Body -->
<tr>
<td class="body" width="100%" cellpadding="0" cellspacing="0" style="border: hidden !important;">
<table class="inner-body" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
<!-- Body content -->
<tr>
<td class="content-cell">
{!! Illuminate\Mail\Markdown::parse($slot) !!}

{!! $subcopy ?? '' !!}
</td>
</tr>
</table>
</td>
</tr>

{!! $footer ?? '' !!}
</table>
</td>
</tr>
</table>
</body>
</html>
