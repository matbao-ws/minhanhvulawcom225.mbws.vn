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
            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('[data-bulk-toolbar]').forEach(function (toolbar) {
                    const formId = toolbar.dataset.bulkToolbar;
                    const form = document.getElementById(formId);
                    const selectAll = document.querySelector('[data-bulk-select-all="' + formId + '"]');
                    const checkboxes = Array.from(document.querySelectorAll('[data-bulk-select="' + formId + '"]'));
                    const count = toolbar.querySelector('[data-bulk-count]');
                    const actionInput = form ? form.querySelector('[data-bulk-action-input]') : null;

                    if (!form || !actionInput || !checkboxes.length) {
                        return;
                    }

                    const selected = function () {
                        return checkboxes.filter(function (checkbox) { return checkbox.checked; });
                    };

                    const refresh = function () {
                        const selectedItems = selected();
                        const total = selectedItems.length;
                        toolbar.classList.toggle('d-none', total === 0);
                        toolbar.classList.toggle('d-flex', total > 0);
                        count.textContent = String(total);

                        if (selectAll) {
                            selectAll.checked = total > 0 && total === checkboxes.length;
                            selectAll.indeterminate = total > 0 && total < checkboxes.length;
                        }
                    };

                    checkboxes.forEach(function (checkbox) {
                        checkbox.addEventListener('change', refresh);
                    });

                    if (selectAll) {
                        selectAll.addEventListener('change', function () {
                            checkboxes.forEach(function (checkbox) {
                                checkbox.checked = selectAll.checked;
                            });
                            refresh();
                        });
                    }

                    toolbar.querySelector('[data-bulk-clear]').addEventListener('click', function () {
                        checkboxes.forEach(function (checkbox) { checkbox.checked = false; });
                        refresh();
                    });

                    toolbar.querySelectorAll('[data-bulk-action]').forEach(function (button) {
                        button.addEventListener('click', function () {
                            const total = selected().length;
                            const action = button.dataset.bulkAction;
                            if (total === 0) {
                                return;
                            }

                            if (action === 'delete' && !window.confirm('Xóa vĩnh viễn ' + total + ' mục đã chọn? Hành động này không thể hoàn tác.')) {
                                return;
                            }

                            actionInput.value = action;
                            form.submit();
                        });
                    });
                });
            });
        </script>
    @endpush
@endonce
