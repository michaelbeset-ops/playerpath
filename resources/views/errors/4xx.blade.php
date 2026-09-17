@extends('errors.layout')

@section('code', isset($exception) && method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : '400')
@section('title', 'Dit lukte niet')
@section('message', 'Het verzoek kon niet worden verwerkt. Ga terug en probeer het nog een keer.')
