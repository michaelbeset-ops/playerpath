@extends('errors.layout')

@section('code', isset($exception) && method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : '500')
@section('title', 'Er ging iets mis')
@section('message', 'Dit lag niet aan jou. Probeer het over een paar minuten nog een keer; blijft het misgaan, laat het dan je school weten.')
