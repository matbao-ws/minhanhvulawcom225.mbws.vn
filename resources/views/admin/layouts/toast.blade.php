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

@if(session()->has(\App\Exceptions\PackageLimitExceeded::FLASH_KEY))
    {{-- A package refusal stays on screen until acknowledged: a toast that fades
         after a few seconds reads like a save that quietly did nothing. --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            Swal.fire({
                icon: 'warning',
                title: @json(__('admin.package.limit_reached_title')),
                html: @json(e(session(\App\Exceptions\PackageLimitExceeded::FLASH_KEY)))
                    @if(auth()->user()?->isSuperAdmin())
                        + '<div class="small text-muted mt-3">' + @json(e(__('admin.package.limit_superadmin_hint'))) + '</div>'
                    @endif
                    ,
                confirmButtonText: @json(__('admin.package.alert.upgrade_contact')),
                confirmButtonColor: '#e32326',
                showCancelButton: true,
                cancelButtonText: @json(__('admin.package.limit_ok')),
                allowOutsideClick: false,
            }).then(function (result) {
                // Same support desk as the layout's quota banner.
                if (result.isConfirmed) {
                    window.open('https://support.matbao.ws/submitticket.php', '_blank', 'noopener');
                }
            });
        });
    </script>
@endif

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
