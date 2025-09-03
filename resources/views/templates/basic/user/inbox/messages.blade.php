@php
    $user = auth()->user();
    $whatsapp = @$user->currentWhatsapp();
@endphp

@foreach ($messages->getCollection()->sortBy('ordering') as $message)
    @include('Template::user.inbox.single_message', ['message' => $message])
@endforeach
