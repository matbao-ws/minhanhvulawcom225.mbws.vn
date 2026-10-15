{{-- Show/hide one row of a list in a click: the module's own bulk endpoint (same permission,
     validation and activity log as the bulk toolbar) with this one id. Inside a list marked
     data-ajax-list it changes in place (admin-ajax.js); without JavaScript it is a normal submit.
     Without the update permission it is only the badge.

     Params: $action (bulk route), $id, $active (bool), $can (bool), $activeLabel, $inactiveLabel. --}}
@if($can)
    <form method="POST" action="{{ $action }}" class="status-switch d-inline-flex align-items-center gap-2 mb-0" data-status-switch>
        @csrf
        @method('PATCH')
        <input type="hidden" name="ids[]" value="{{ $id }}">
        <input type="hidden" name="action" value="{{ $active ? 'deactivate' : 'activate' }}">
        <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" role="switch" id="status-switch-{{ $id }}" @checked($active)
                   title="{{ $active ? __('admin.ajax.show') : __('admin.ajax.hide') }}"
                   onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()">
            @if(($active ? $activeLabel : $inactiveLabel) !== '')
                <label class="form-check-label small fw-semibold {{ $active ? 'text-success' : 'text-warning' }}" for="status-switch-{{ $id }}">{{ $active ? $activeLabel : $inactiveLabel }}</label>
            @endif
        </div>
    </form>
@else
    <span class="badge {{ $active ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' }} fw-semibold">{{ $active ? $activeLabel : $inactiveLabel }}</span>
@endif
