<div class="stress-subsection">
    <h4>Cấp 2 — Tốc độ</h4>
    <x-client::editable key="stress.sections.speed.text" tag="p">
        Section cấp hai, chứa cấp ba.
    </x-client::editable>

    {{-- Level 3. Three levels is where a scoping mistake stops being theoretical. --}}
    <x-client::section-list
        key="stress.sections.speed.children"
        :sections="['stress-cache', 'stress-cdn']"
    />
</div>
