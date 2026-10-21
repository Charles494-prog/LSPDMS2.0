@extends(request()->boolean('widget') ? 'layouts.chat' : 'layouts.app')

@section('title', 'LSPDMS Help')

@push('styles')
@include('messages.partials.assistant-styles')
@endpush

@section('content')
@include('messages.partials.assistant-panel', ['embedded' => false])
@endsection

@push('scripts')
@vite('resources/js/assistant.js')
@endpush
