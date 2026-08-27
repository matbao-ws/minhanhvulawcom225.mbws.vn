<div class="client-sandbox__section">
    <h3>Section: Tính năng</h3>
    <x-client::editable key="dev.sections.features.text" tag="p">
        Section này chứa section con. Con chỉ đổi chỗ được với nhau, không nhảy ra ngoài cha.
    </x-client::editable>

    {{-- Nested list: its own key, so its children can only reorder among themselves. --}}
    <x-client::section-list
        key="dev.sections.features.children"
        :sections="['speed', 'safety', 'support']"
    />
</div>
