<div class="stress-section">
    <h3>Cấp 1 — Tính năng</h3>
    <x-client::editable key="stress.sections.features.text" tag="p">
        Section cấp một, chứa cấp hai bên dưới.
    </x-client::editable>

    {{-- Level 2: its own key, so its children reorder only among themselves. --}}
    <x-client::section-list
        key="stress.sections.features.children"
        :sections="['stress-speed', 'stress-safety']"
    />
</div>
