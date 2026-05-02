{{-- Override: avoid multiple @forelse in one file (can compile to invalid $__empty_-1 on some PHP/Blade combinations). --}}
# {{ $exception->class() }} - {!! $exception->title() !!}

{!! $exception->message() !!}

PHP {{ PHP_VERSION }}
Laravel {{ app()->version() }}
{{ $exception->request()->httpHost() }}

## Stack Trace

@foreach($exception->frames() as $index => $frame)
{{ $index }} - {{ $frame->file() }}:{{ $frame->line() }}
@endforeach

## Request

{{ $exception->request()->method() }} {{ \Illuminate\Support\Str::start($exception->request()->path(), '/') }}

## Headers

@php($exceptionRequestHeaders = $exception->requestHeaders())
@if ($exceptionRequestHeaders === [])
No header data available.
@else
@foreach ($exceptionRequestHeaders as $key => $value)
* **{{ $key }}**: {!! $value !!}

@endforeach
@endif

## Route Context

@php($exceptionRouteContext = $exception->applicationRouteContext())
@if ($exceptionRouteContext === [])
No routing data available.
@else
@foreach ($exceptionRouteContext as $name => $value)
{{ $name }}: {!! $value !!}

@endforeach
@endif

## Route Parameters

@if ($routeParametersContext = $exception->applicationRouteParametersContext())
{!! $routeParametersContext !!}
@else
No route parameter data available.
@endif

## Database Queries

@php($exceptionQueries = $exception->applicationQueries())
@if ($exceptionQueries === [])
No database queries detected.
@else
@foreach ($exceptionQueries as $query)
* {{ $query['connectionName'] }} - {!! $query['sql'] !!} ({{ $query['time'] }} ms)

@endforeach
@endif
