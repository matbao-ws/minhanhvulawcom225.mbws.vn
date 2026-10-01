@props(['alerts' => []])

@foreach ($alerts as $alert)
    <div class="alert alert-{{ $alert['level'] }} border-0 d-flex flex-column flex-md-row align-items-md-center gap-3 mb-4 package-limit-alert" role="alert">
        <div class="d-flex align-items-start gap-3 flex-grow-1">
            <iconify-icon icon="{{ $alert['level'] === 'danger' ? 'solar:danger-triangle-bold-duotone' : 'solar:danger-circle-bold-duotone' }}" class="fs-6 flex-shrink-0"></iconify-icon>
            <div>
                <div class="fw-bold mb-1">{{ $alert['title'] }}</div>
                <div class="small">{{ $alert['message'] }}</div>
            </div>
        </div>
        <a href="https://support.matbao.ws/submitticket.php" target="_blank" rel="noopener noreferrer"
            class="btn btn-sm btn-primary d-inline-flex align-items-center justify-content-center gap-1 flex-shrink-0 text-nowrap">
            <iconify-icon icon="solar:chat-round-dots-bold-duotone" width="17" height="17"></iconify-icon>
            {{ __('admin.package.alert.upgrade_contact') }}
        </a>
    </div>
@endforeach
