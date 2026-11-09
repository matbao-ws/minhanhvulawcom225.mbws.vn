@extends('client.layouts.app')

@section('title', 'Liên Hệ Luật Sư Tư Vấn | CÔNG TY LUẬT MINH ANH VŨ - CÔNG TY LUẬT TNHH MTV MINH ANH VŨ')
@section('meta_description', 'Liên hệ tư vấn pháp lý với Công ty Luật TNHH MTV Minh Anh Vũ. Trụ sở chính: 60/2 Đường số 2 Phường Hiệp Bình, Thành phố Hồ Chí Minh. Hotline 24/7: 0397.999.123.')

@section('content')
  @php
    $locale = app()->getLocale();
    $canEdit = \App\Support\ClientEditorChrome::editorActive();
    $brandName = $siteBranding['name'] ?? 'CÔNG TY LUẬT TNHH MTV MINH ANH VŨ';
    $contact = $siteBranding['contact'] ?? [];
    $phone = !empty($contact['phone']) ? $contact['phone'] : '0397.999.123';
    $phoneHref = 'tel:'.preg_replace('/[^0-9+]/', '', $phone);
    $email = !empty($contact['email']) ? $contact['email'] : 'trolyluatsu@minhanhvulaw.com';
    $address = !empty($contact['address']) ? $contact['address'] : '60/2 Đường số 2 Phường Hiệp Bình, Thành phố Hồ Chí Minh';
  @endphp

  @include('client.partials.page-banner', [
    'title' => 'Liên Hệ Luật Sư Tư Vấn',
    'titleKey' => 'contact.banner.title',
    'bgKey' => 'contact.banner.bg',
    'breadcrumbs' => [
      ['label' => 'Liên hệ', 'url' => null],
    ],
  ])

  <main class="py-16 sm:py-20 bg-slate-50 flex-1" @if($canEdit) data-outline-section="contact-main" data-outline-label="Liên hệ Luật sư" @endif>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">

      <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-start" @if($canEdit) data-outline-group="contact-grid" data-outline-kind="grid" data-outline-group-label="Bố cục Liên hệ" @endif>

        <!-- Cột thông tin liên hệ -->
        <div class="lg:col-span-5 bg-white rounded-2xl p-8 sm:p-10 shadow-sm border border-slate-200 space-y-8" @if($canEdit) data-outline-group="contact-info-card" data-outline-kind="card" data-outline-group-label="Thông tin kết nối" @endif>
          <div>
            <x-client::editable key="contact.info.eyebrow" tag="span" class="text-brand-red font-bold text-sm uppercase tracking-wider">Thông tin kết nối</x-client::editable>
            <h2 class="text-2xl sm:text-3xl font-bold text-navy-900 tracking-tight mt-1">{{ $brandName }}</h2>
            <x-client::editable key="contact.info.slogan" tag="p" class="text-brand-red font-bold text-base mt-1">"Quản trị rủi ro pháp lý cùng bạn"</x-client::editable>
          </div>

          <div class="space-y-6">
            <div class="flex items-start gap-4">
              <div class="w-11 h-11 rounded-lg bg-red-50 text-brand-red flex items-center justify-center text-lg flex-shrink-0 shadow-sm">
                <i class="fa-solid fa-location-dot"></i>
              </div>
              <div>
                <x-client::editable key="contact.info.hq_label" tag="strong" class="block font-bold text-navy-900 text-base">Trụ sở chính</x-client::editable>
                <span class="text-sm text-slate-600 font-normal">{{ $address }}</span>
              </div>
            </div>

            <div class="flex items-start gap-4">
              <div class="w-11 h-11 rounded-lg bg-red-50 text-brand-red flex items-center justify-center text-lg flex-shrink-0 shadow-sm">
                <i class="fa-solid fa-phone"></i>
              </div>
              <div>
                <x-client::editable key="contact.info.phone_label" tag="strong" class="block font-bold text-navy-900 text-base">Điện thoại / Hotline 24/7</x-client::editable>
                <span class="text-sm text-slate-600 font-normal">
                  <x-client::editable key="contact.info.phone_prefix" tag="span">Hotline:</x-client::editable>
                  <a href="{{ $phoneHref }}" class="text-brand-red font-bold hover:underline">{{ $phone }}</a>
                </span>
              </div>
            </div>

            <div class="flex items-start gap-4">
              <div class="w-11 h-11 rounded-lg bg-red-50 text-brand-red flex items-center justify-center text-lg flex-shrink-0 shadow-sm">
                <i class="fa-solid fa-envelope"></i>
              </div>
              <div>
                <x-client::editable key="contact.info.email_label" tag="strong" class="block font-bold text-navy-900 text-base">Hòm thư điện tử</x-client::editable>
                <span class="text-sm text-slate-600 font-normal">{{ $email }}</span>
              </div>
            </div>

            <div class="flex items-start gap-4">
              <div class="w-11 h-11 rounded-lg bg-red-50 text-brand-red flex items-center justify-center text-lg flex-shrink-0 shadow-sm">
                <i class="fa-solid fa-clock"></i>
              </div>
              <div>
                <x-client::editable key="contact.info.hours_label" tag="strong" class="block font-bold text-navy-900 text-base">Thời gian làm việc</x-client::editable>
                <x-client::editable key="contact.info.hours_value" tag="span" class="text-sm text-slate-600 font-normal">Thứ 2 - Thứ 7: 08:00 - 17:30 (Chủ nhật đặt hẹn trước)</x-client::editable>
              </div>
            </div>
          </div>
        </div>

        <!-- Cột Form liên hệ -->
        <div class="lg:col-span-7 bg-white rounded-2xl p-8 sm:p-10 shadow-sm border border-slate-200" @if($canEdit) data-outline-group="contact-form-card" data-outline-kind="card" data-outline-group-label="Form gửi yêu cầu" @endif>
          <x-client::editable key="contact.form.title" tag="h3" class="text-2xl sm:text-3xl font-bold text-navy-900 tracking-tight mb-2">Gửi Yêu Cầu Cho Luật Sư</x-client::editable>
          <x-client::editable key="contact.form.subtitle" tag="p" class="text-sm text-slate-500 font-normal mb-6">Luật sư chuyên ngành sẽ phản hồi trong vòng 30 phút làm việc.</x-client::editable>

          <form action="{{ route('client.contact.submit', ['locale' => $locale]) }}" method="post" class="consultation-form space-y-4" data-ajax-form="true">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <x-client::editable key="contact.form.label_name" tag="label" class="block text-sm font-semibold text-navy-900 uppercase mb-1">Họ và tên của bạn *</x-client::editable>
                <input type="text" name="fullname" required placeholder="Nguyễn Văn A" class="w-full px-4 py-3.5 rounded-lg border border-slate-300 text-base focus:ring-1 focus:ring-brand-red focus:border-brand-red outline-none font-normal shadow-sm" />
              </div>
              <div>
                <x-client::editable key="contact.form.label_phone" tag="label" class="block text-sm font-semibold text-navy-900 uppercase mb-1">Số điện thoại *</x-client::editable>
                <input type="tel" name="phone" required placeholder="0397 999 123" class="w-full px-4 py-3.5 rounded-lg border border-slate-300 text-base focus:ring-1 focus:ring-brand-red focus:border-brand-red outline-none font-normal shadow-sm" />
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <x-client::editable key="contact.form.label_email" tag="label" class="block text-sm font-semibold text-navy-900 uppercase mb-1">Địa chỉ Email</x-client::editable>
                <input type="email" name="email" placeholder="example@email.com" class="w-full px-4 py-3.5 rounded-lg border border-slate-300 text-base focus:ring-1 focus:ring-brand-red focus:border-brand-red outline-none font-normal shadow-sm" />
              </div>
              <div>
                <x-client::editable key="contact.form.label_service" tag="label" class="block text-sm font-semibold text-navy-900 uppercase mb-1">Lĩnh vực cần tư vấn</x-client::editable>
                <select name="service" data-editable-ignore="tùy chọn trong thẻ select không chọn trực tiếp được từ bộ sửa" class="w-full px-4 py-3.5 rounded-lg border border-slate-300 text-base focus:ring-1 focus:ring-brand-red focus:border-brand-red outline-none bg-white font-normal text-slate-700 shadow-sm">
                  <option value="Tranh tụng & Tư vấn giải quyết tranh chấp">Tranh tụng &amp; Tư vấn giải quyết tranh chấp</option>
                  <option value="Pháp chế doanh nghiệp & Tư vấn hàng tháng">Pháp chế doanh nghiệp &amp; Tư vấn hàng tháng</option>
                  <option value="Thành lập công ty & Đăng ký kinh doanh">Thành lập công ty &amp; Đăng ký kinh doanh</option>
                  <option value="Giấy phép lao động & Visa di trú">Giấy phép lao động &amp; Visa di trú</option>
                  <option value="Báo cáo thuế & Kế toán tài chính">Báo cáo thuế &amp; Kế toán tài chính</option>
                  <option value="Luật sư riêng cá nhân & Hộ tịch">Luật sư riêng cá nhân &amp; Hộ tịch</option>
                  <option value="Hợp tác kinh doanh">Hợp tác kinh doanh</option>
                  <option value="Vấn đề pháp lý khác">Vấn đề pháp lý khác</option>
                </select>
              </div>
            </div>

            <div>
              <x-client::editable key="contact.form.label_message" tag="label" class="block text-sm font-semibold text-navy-900 uppercase mb-1">Chi tiết yêu cầu hoặc câu hỏi</x-client::editable>
              <textarea rows="4" name="message" placeholder="Mô tả cụ thể vụ việc để luật sư chuẩn bị tài liệu nghiên cứu trước..." class="w-full px-4 py-3.5 rounded-lg border border-slate-300 text-base focus:ring-1 focus:ring-brand-red focus:border-brand-red outline-none font-normal shadow-sm"></textarea>
            </div>

            <button type="submit" class="w-full py-4 rounded-lg bg-brand-red hover:bg-brand-redDark text-white font-bold text-sm uppercase tracking-wider shadow-lg shadow-red-600/30 transition">
              <i class="fa-solid fa-paper-plane mr-2"></i>
              <x-client::editable key="contact.form.submit" tag="span">Gửi Yêu Cầu Cho Luật Sư</x-client::editable>
            </button>
          </form>
        </div>
      </div>

    </div>
  </main>
@endsection
