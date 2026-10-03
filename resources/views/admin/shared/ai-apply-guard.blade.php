{{-- Admins with ai.use but not ai.apply can ask for suggestions and read them, never write them:
     the apply buttons of every AI modal are hidden and each modal says why. The routes enforce
     can:ai.apply regardless; this only keeps the screen honest. --}}
@once
    @cannot('ai.apply')
        @push('styles')
            <style>
                .js-ai-apply, .js-ai-edit-apply, .js-option-apply, .js-ai-alt-save { display: none !important; }
            </style>
        @endpush
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    document.querySelectorAll('.modal[id^="ai-"] .modal-body').forEach(function (body) {
                        const note = document.createElement('div');
                        note.className = 'alert alert-info small py-2';
                        note.textContent = @json(__('ai.errors.cannot_apply'));
                        body.prepend(note);
                    });
                });
            </script>
        @endpush
    @endcannot
@endonce
