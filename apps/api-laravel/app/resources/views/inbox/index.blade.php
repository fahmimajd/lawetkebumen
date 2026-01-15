@extends('layouts.app')

@section('content')
    <section class="panel panel--list">
        <x-inbox.list />
    </section>

    <section class="panel panel--thread">
        <x-inbox.thread />
        <x-inbox.composer />
    </section>
@endsection
