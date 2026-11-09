@php
  $canEdit = \App\Support\ClientEditorChrome::editorActive();
@endphp
<!-- SECTION: ĐỐI TÁC TIN CẬY -->
<section class="py-14 bg-white border-t border-slate-200" @if($canEdit) data-outline-section="home-partners" data-outline-label="Đối tác tin cậy" @endif>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="text-center mb-9" @if($canEdit) data-outline-group="home-partners-header" data-outline-kind="header" data-outline-group-label="Tiêu đề Đối tác" @endif>
      <x-client::editable key="home.partners.title" tag="h2" class="text-xl sm:text-2xl font-bold uppercase text-navy-900 tracking-tight">ĐỐI TÁC TIN CẬY</x-client::editable>
    </div>
    <div class="grid grid-cols-3 sm:grid-cols-6 gap-6 items-center justify-items-center opacity-85 hover:opacity-100 transition duration-300">
      @foreach($trustedPartners ?? collect() as $partner)
        <img src="{{ $partner->logo_url }}" alt="{{ $content->get($partner, 'name') }}" class="h-10 object-contain grayscale hover:grayscale-0 transition" />
      @endforeach
    </div>
  </div>
</section>
