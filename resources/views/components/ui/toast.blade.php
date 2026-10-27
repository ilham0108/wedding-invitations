{{-- Session flashes handed to SweetAlert2 (resources/js/notifications.js). @json escapes <, >, & for a safe <script>. --}}
@if ($messages)
    <script type="application/json" id="flash-messages">@json($messages)</script>
@endif
