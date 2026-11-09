@php
  $locale = app()->getLocale();
  $canEdit = \App\Support\ClientEditorChrome::editorActive();
@endphp
<!-- SECTION: FORM ĐĂNG KÝ TƯ VẤN (CÓ THUỘC TÍNH NAME ĐẦY ĐỦ) -->
<section class="py-16 sm:py-20 bg-navy-900 text-white" @if($canEdit) data-outline-section="home-consultation-form" data-outline-label="Form đăng ký tư vấn pháp lý" @endif>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="max-w-4xl mx-auto bg-navy-950 p-8 sm:p-12 rounded-3xl border border-navy-800 shadow-2xl space-y-8" @if($canEdit) data-outline-group="home-consultation-card" data-outline-kind="card" data-outline-group-label="Khung đăng ký tư vấn" @endif>

      <div class="text-center space-y-3" @if($canEdit) data-outline-group="home-consultation-header" data-outline-kind="header" data-outline-group-label="Tiêu đề Form" @endif>
        <x-client::editable key="home.consultation.eyebrow" tag="span" class="text-brand-red font-bold text-sm uppercase tracking-wider">Liên hệ khẩn cấp 24/7</x-client::editable>
        <x-client::editable key="home.consultation.title" tag="h2" class="text-2xl sm:text-3xl font-bold uppercase tracking-tight text-white">ĐĂNG KÝ TƯ VẤN PHÁP LÝ</x-client::editable>
        <x-client::editable key="home.consultation.subtitle" tag="p" class="text-slate-300 text-base font-normal max-w-xl mx-auto">Để lại thông tin vụ việc, Luật sư Minh Anh Vũ sẽ liên hệ lại tư vấn định hướng trong vòng 30 phút.</x-client::editable>
      </div>

      <form action="{{ route('client.contact.submit', ['locale' => $locale]) }}" method="post" class="consultation-form space-y-4" data-ajax-form="true">
        @csrf
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <input type="text" name="fullname" required placeholder="Họ &amp; tên của bạn *" class="w-full px-4 py-3.5 rounded-lg border border-slate-300 text-base focus:ring-1 focus:ring-brand-red focus:border-brand-red outline-none bg-white font-normal text-slate-800 shadow-sm" />
          </div>
          <div>
            <input type="email" name="email" required placeholder="Địa chỉ email *" class="w-full px-4 py-3.5 rounded-lg border border-slate-300 text-base focus:ring-1 focus:ring-brand-red focus:border-brand-red outline-none bg-white font-normal text-slate-800 shadow-sm" />
          </div>
          <div>
            <input type="tel" name="phone" required placeholder="Số điện thoại liên hệ *" class="w-full px-4 py-3.5 rounded-lg border border-slate-300 text-base focus:ring-1 focus:ring-brand-red focus:border-brand-red outline-none bg-white font-normal text-slate-800 shadow-sm" />
          </div>
        </div>

        <div>
          <textarea rows="4" name="message" required placeholder="Nội dung vướng mắc cần luật sư tư vấn chi tiết..." class="w-full px-4 py-3.5 rounded-lg border border-slate-300 text-base focus:ring-1 focus:ring-brand-red focus:border-brand-red outline-none bg-white font-normal text-slate-800 shadow-sm"></textarea>
        </div>

        <div class="text-center pt-2">
          <x-client::editable key="home.consultation.submit" tag="button" type="submit" class="px-10 py-4 bg-brand-red hover:bg-brand-redDark text-white text-sm font-bold uppercase tracking-wider rounded-lg shadow-lg shadow-red-600/30 transition">Gửi Đến Luật Minh Anh Vũ</x-client::editable>
        </div>
      </form>

    </div>
  </div>
</section>
