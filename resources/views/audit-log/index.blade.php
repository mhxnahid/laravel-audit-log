@extends(config('audit-log.layout', 'audit-log::layouts.app'))

@section('content')
    <audit-log></audit-log>
@endsection

@section('script')
    <script>
        window.auditLogFilters = @json($filters);
        window.auditLogDataUrl    = '{{ route("audit-log.data") }}';
    </script>
@endsection
