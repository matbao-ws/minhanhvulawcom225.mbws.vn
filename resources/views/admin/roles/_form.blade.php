@php
    $selected = old('permissions', $role->permissions ?? []);
    $isWildcard = in_array('*', $role->permissions ?? [], true);
    // preserveKeys: the module name is the key and it becomes the permission code.
    $grouped = collect($modules)->groupBy('group', true);
@endphp

<div class="card">
    <div class="card-body">
        <div class="row">
            <div class="col-md-12 mb-4">
                <label class="form-label fw-semibold text-dark" for="name">{{ __('admin.roles.fields.name') }} <span class="text-danger">*</span></label>
                <input type="text" class="form-control text-dark" id="name" name="name" value="{{ old('name', $role->name) }}" placeholder="{{ __('admin.roles.fields.name_placeholder') }}" required>
            </div>

            <div class="col-md-12">
                <label class="form-label fw-semibold text-dark mb-3">{{ __('admin.roles.fields.select_permissions') }}</label>

                @if($modules === [])
                    <div class="alert alert-warning mb-0">{{ __('admin.roles.no_grantable_permissions') }}</div>
                @endif

                @foreach($grouped as $group => $groupModules)
                    <div class="border rounded p-3 mb-3">
                        <h6 class="fw-bold text-dark mb-3">{{ __('admin.roles.groups.'.$group) }}</h6>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr class="text-nowrap">
                                        <th style="min-width: 220px;">{{ __('admin.roles.fields.module') }}</th>
                                        @foreach(['view', 'create', 'update', 'delete', 'use'] as $action)
                                            <th class="text-center" style="width: 96px;">{{ __('admin.roles.actions.'.$action) }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($groupModules as $module => $definition)
                                        <tr>
                                            <td class="fw-semibold text-dark">{{ $definition['label'] }}</td>
                                            @foreach(['view', 'create', 'update', 'delete', 'use'] as $action)
                                                <td class="text-center">
                                                    @if(in_array($action, $definition['actions'], true))
                                                        @php($code = $module.'.'.$action)
                                                        <div class="form-check form-switch d-inline-block m-0">
                                                            <input class="form-check-input" type="checkbox" name="permissions[]"
                                                                   value="{{ $code }}" id="perm_{{ $module }}_{{ $action }}"
                                                                   aria-label="{{ __('admin.roles.actions.'.$action) }} — {{ $definition['label'] }}"
                                                                   @checked($isWildcard || in_array($code, $selected, true))>
                                                        </div>
                                                    @else
                                                        <span class="text-muted">—</span>
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

@include('admin.shared.form-actions', ['cancelUrl' => route('admin.roles.index')])
