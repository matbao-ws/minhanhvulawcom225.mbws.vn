@extends('admin.layouts.app')

@section('title', __('admin.roles.edit'))

@section('content')
    <div class="card bg-primary-subtle shadow-none position-relative overflow-hidden mb-4">
        <div class="card-body px-4 py-3">
            <h4 class="fw-semibold mb-0">{{ __('admin.roles.edit') }}: {{ $role->name }}</h4>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.roles.update', $role) }}" class="admin-form-with-sticky-actions" id="roleForm">
        @csrf
        @method('PUT')
        @include('admin.roles._form')
    </form>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('roleForm');
            if (form) {
                form.addEventListener('submit', function (e) {
                    e.preventDefault();

                    Swal.fire({
                        title: "{{ __('admin.roles.saving') }}",
                        text: "{{ __('admin.roles.please_wait') }}",
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    const formData = new FormData(form);

                    fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        },
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) {
                            return response.json().then(err => { throw err; });
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: "{{ __('admin.settings.success') }}",
                                text: data.message || "{{ __('admin.roles.updated') }}",
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                window.location.href = "{{ route('admin.roles.index') }}";
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: "{{ __('admin.settings.error') }}",
                                text: data.message || "{{ __('admin.roles.update_failed') }}"
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        let errMsg = 'Không thể kết nối đến máy chủ.';
                        if (error.errors) {
                            errMsg = Object.values(error.errors).flat().join('\n');
                        } else if (error.message) {
                            errMsg = error.message;
                        }
                        Swal.fire({
                            icon: 'error',
                            title: "{{ __('admin.settings.error') }}",
                            text: errMsg
                        });
                    });
                });
            }
        });
    </script>
@endpush
