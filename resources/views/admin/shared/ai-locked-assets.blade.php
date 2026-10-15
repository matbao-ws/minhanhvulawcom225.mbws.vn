{{-- AI entry points that are shown but locked (App\Support\AiAccess): any element with data-ai-locked
     (a button, a menu link, a card action) opens a dialog that says why and what to do, instead of
     its tool. Included once by the admin layout, so it works on every page and in the sidebar. --}}
<style>
    .is-ai-locked { position: relative; cursor: pointer; opacity: .62; filter: grayscale(.35); }
    .is-ai-locked:hover, .is-ai-locked:focus-visible { opacity: .85; }
    .ai-lock-icon { margin-left: .3rem; font-size: .9em; vertical-align: -.05em; }
    .ai-superadmin-note { display: inline-flex; align-items: center; gap: .25rem; padding: .05rem .45rem; border-radius: 999px; color: #92400e; background: #fef3c7; font-size: 11px; font-weight: 700; }
</style>
<script>
    (function () {
        const text = {
            close: @json(__('ai.locked.close')),
        };
        document.addEventListener('click', function (event) {
            const element = event.target.closest('[data-ai-locked]');
            if (!element) return;
            // Captured before any tool's own handler: a locked button never reaches the AI.
            event.preventDefault();
            event.stopImmediatePropagation();
            let access = {};
            try { access = JSON.parse(element.dataset.aiLocked || '{}'); } catch (error) { return; }
            const options = {
                icon: access.reason === 'permission' ? 'info' : 'warning',
                title: access.title || '',
                text: access.message || '',
                confirmButtonText: access.action_label || text.close,
                showCancelButton: Boolean(access.action_url),
                cancelButtonText: text.close,
                customClass: { confirmButton: 'btn btn-primary', cancelButton: 'btn btn-light ms-2' },
                buttonsStyling: false,
            };
            const open = () => { if (access.action_url) window.open(access.action_url, '_blank', 'noopener'); };
            if (window.Swal) {
                Swal.fire(options).then((result) => { if (result.isConfirmed) open(); });
            } else if (window.confirm((access.title ? access.title + '\n\n' : '') + (access.message || '')) && access.action_url) {
                open();
            }
        }, true);
    })();
</script>
