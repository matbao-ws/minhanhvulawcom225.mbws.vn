@php
  $canEdit = \App\Support\ClientEditorChrome::editorActive();
  $cardDefaults = [
    'item-1' => [
      'icon' => 'fa-shield-halved',
      'title' => 'Đội ngũ luật sư giỏi',
      'desc' => 'Kinh nghiệm chuyên sâu đa lĩnh vực.',
    ],
    'item-2' => [
      'icon' => 'fa-money-bill-wave',
      'title' => 'Chi phí hợp lý',
      'desc' => 'Minh bạch, tối ưu, không phí ẩn.',
    ],
    'item-3' => [
      'icon' => 'fa-clock',
      'title' => 'Xử lý nhanh chóng',
      'desc' => 'Tiết kiệm thời gian, đúng tiến độ.',
    ],
    'item-4' => [
      'icon' => 'fa-heart',
      'title' => 'Tư vấn tận tâm',
      'desc' => 'Luôn lắng nghe và thấu hiểu.',
    ],
    'item-5' => [
      'icon' => 'fa-lock',
      'title' => 'Bảo mật tuyệt đối',
      'desc' => 'Giữ bí mật mọi thông tin khách hàng.',
    ],
  ];
@endphp
<!-- SECTION: VÌ SAO CHỌN MINH ANH VŨ? (THEO ĐÚNG MẪU YÊU CẦU) -->
<section class="py-16 sm:py-20 bg-slate-50 border-t border-slate-200" @if($canEdit) data-outline-section="home-why-choose" data-outline-label="Vì Sao Chọn Minh Anh Vũ?" @endif>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

    <div class="text-center max-w-3xl mx-auto mb-14 space-y-3" @if($canEdit) data-outline-group="home-why-choose-header" data-outline-kind="header" data-outline-group-label="Tiêu đề mục" @endif>
      <h2 class="text-2xl sm:text-3xl lg:text-4xl font-bold uppercase text-navy-900 tracking-tight relative inline-block">
        <x-client::editable key="home.why_choose.title" tag="span">Vì Sao Chọn Minh Anh Vũ?</x-client::editable>
        <span class="block w-24 h-1 bg-brand-red mx-auto mt-3 rounded-full"></span>
      </h2>
      <x-client::editable key="home.why_choose.subtitle" tag="p" class="text-base sm:text-lg text-slate-600 font-normal">Cam kết mang đến sự an tâm tuyệt đối và hiệu quả pháp lý cao nhất.</x-client::editable>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-6" @if($canEdit) data-outline-group="home-why-choose-grid" data-outline-kind="grid" data-outline-group-label="5 cam kết" @endif>
      @foreach($lists->items('home.why_choose.cards', array_keys($cardDefaults)) as $id)
        @php
          $d = $cardDefaults[$id] ?? $cardDefaults['item-1'];
        @endphp
        <x-client::list-item list="home.why_choose.cards" :item="$id" class="bg-white rounded-2xl p-7 text-center border border-slate-200 shadow-sm hover:shadow-xl transition-all duration-300 group flex flex-col justify-between" data-outline-group="home-why-choose-card-{{ $id }}" data-outline-kind="card" data-outline-group-label="{{ $d['title'] }}">
          <div @if($canEdit) data-outline-group="home-why-choose-content-{{ $id }}" data-outline-kind="content" data-outline-group-label="Nội dung cam kết" @endif>
            <div class="w-16 h-16 rounded-full bg-red-50 text-brand-red flex items-center justify-center text-2xl mx-auto mb-5 group-hover:scale-110 group-hover:bg-brand-red group-hover:text-white transition duration-300 shadow-sm">
              <i class="fa-solid {{ $d['icon'] }}"></i>
            </div>
            <x-client::editable :key="'home.why_choose.cards.' . $id . '.title'" tag="h3" class="font-bold text-navy-900 text-lg mb-2 group-hover:text-brand-red transition">{{ $d['title'] }}</x-client::editable>
            <x-client::editable :key="'home.why_choose.cards.' . $id . '.desc'" tag="p" class="text-sm text-slate-500 font-normal leading-relaxed">{{ $d['desc'] }}</x-client::editable>
          </div>
        </x-client::list-item>
      @endforeach
    </div>
  </div>
</section>
