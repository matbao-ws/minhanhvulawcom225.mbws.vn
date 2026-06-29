@php
    $toast = null;

    foreach (['success', 'status', 'error', 'warning', 'info'] as $key) {
        if (session()->has($key)) {
            $toast = [
                'icon' => $key === 'status' ? 'success' : $key,
                'message' => session($key),
            ];
            break;
        }
    }

    if (! $toast && $errors->any()) {
        $toast = [
            'icon' => 'error',
            'message' => $errors->first(),
        ];
    }
@endphp

@if($toast)
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3500,
                timerProgressBar: true,
                didOpen: function (toast) {
                    toast.addEventListener('mouseenter', Swal.stopTimer);
                    toast.addEventListener('mouseleave', Swal.resumeTimer);
                }
            }).fire({
                icon: @json($toast['icon']),
                title: @json($toast['message'])
            });
        });
    </script>
@endif
