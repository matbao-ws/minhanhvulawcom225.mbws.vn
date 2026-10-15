@php
    $bulkFormId = $bulkFormId ?? 'bulk-actions-form';
    $bulkActionUrl = $bulkActionUrl ?? '#';
    $bulkItemLabel = $bulkItemLabel ?? 'mục';
@endphp

<form id="{{ $bulkFormId }}" method="POST" action="{{ $bulkActionUrl }}" class="d-none">
    @csrf
    @method('PATCH')
    <input type="hidden" name="action" value="" data-bulk-action-input>
</form>

<div class="d-none flex-wrap align-items-center gap-2 px-4 py-3 border-bottom bg-light-subtle" data-bulk-toolbar="{{ $bulkFormId }}">
    <span class="fw-semibold text-dark me-1"><span data-bulk-count>0</span> {{ $bulkItemLabel }} đã chọn</span>
    <button type="button" class="btn btn-sm btn-outline-success" data-bulk-action="activate">
        <i class="ti ti-circle-check me-1"></i>Hiển thị
    </button>
    <button type="button" class="btn btn-sm btn-outline-secondary" data-bulk-action="deactivate">
        <i class="ti ti-eye-off me-1"></i>Tạm ẩn
    </button>
    <button type="button" class="btn btn-sm btn-outline-danger" data-bulk-action="delete">
        <i class="ti ti-trash me-1"></i>Xóa đã chọn
    </button>
    <button type="button" class="btn btn-sm btn-link text-secondary ms-auto" data-bulk-clear>Bỏ chọn</button>
</div>

@once
    @push('scripts')
        <script>
            // Listens on the document, so it keeps working after admin-ajax.js swaps the list in.
            (function () {
                const boxes = function (formId) {
                    return Array.from(document.querySelectorAll('[data-bulk-select="' + formId + '"]'));
                };

                const refresh = function (formId) {
                    const toolbar = document.querySelector('[data-bulk-toolbar="' + formId + '"]');
                    if (!toolbar) return;
                    const all = boxes(formId);
                    const total = all.filter(function (box) { return box.checked; }).length;
                    toolbar.classList.toggle('d-none', total === 0);
                    toolbar.classList.toggle('d-flex', total > 0);
                    toolbar.querySelector('[data-bulk-count]').textContent = String(total);

                    const selectAll = document.querySelector('[data-bulk-select-all="' + formId + '"]');
                    if (selectAll) {
                        selectAll.checked = total > 0 && total === all.length;
                        selectAll.indeterminate = total > 0 && total < all.length;
                    }
                };

                document.addEventListener('change', function (event) {
                    const box = event.target.closest('[data-bulk-select], [data-bulk-select-all]');
                    if (!box) return;
                    const formId = box.dataset.bulkSelect || box.dataset.bulkSelectAll;
                    if (box.hasAttribute('data-bulk-select-all')) {
                        boxes(formId).forEach(function (item) { item.checked = box.checked; });
                    }
                    refresh(formId);
                });

                document.addEventListener('click', function (event) {
                    const toolbar = event.target.closest('[data-bulk-toolbar]');
                    if (!toolbar) return;
                    const formId = toolbar.dataset.bulkToolbar;

                    if (event.target.closest('[data-bulk-clear]')) {
                        boxes(formId).forEach(function (item) { item.checked = false; });
                        refresh(formId);
                        return;
                    }

                    const button = event.target.closest('[data-bulk-action]');
                    const form = document.getElementById(formId);
                    const actionInput = form ? form.querySelector('[data-bulk-action-input]') : null;
                    const total = boxes(formId).filter(function (item) { return item.checked; }).length;
                    if (!button || !actionInput || total === 0) return;

                    const action = button.dataset.bulkAction;
                    const run = function () {
                        actionInput.value = action;
                        window.AdminAjax ? window.AdminAjax.submit(form) : form.submit();
                    };

                    if (action !== 'delete') return run();
                    const message = 'Xóa vĩnh viễn ' + total + ' mục đã chọn? Hành động này không thể hoàn tác.';
                    if (!window.Swal) {
                        if (window.confirm(message)) run();
                        return;
                    }
                    Swal.fire({
                        title: @json(__('catalog.actions.confirm_delete')),
                        text: message,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#e32326',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: @json(__('catalog.actions.delete')),
                        cancelButtonText: @json(__('catalog.actions.cancel')),
                        focusCancel: true,
                    }).then(function (result) {
                        if (result.isConfirmed) run();
                    });
                });
            })();
        </script>
    @endpush
@endonce
