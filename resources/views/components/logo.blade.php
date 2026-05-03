@props(['horizontal' => false, 'class' => ''])
@if ($horizontal)
    <img src="{{ asset('logo/horizontal-dark.svg') }}" class="not-dark:hidden {{ $class }}">
    <img src="{{ asset('logo/horizontal-light.svg') }}" class="dark:hidden {{ $class }}">
@else
    <img src="{{ asset('logo/logo-dark.svg') }}" class="not-dark:hidden {{ $class }}">
    <img src="{{ asset('logo/logo-light.svg') }}" class="dark:hidden {{ $class }}">
@endif
