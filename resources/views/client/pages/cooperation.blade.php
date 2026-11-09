@extends('client.layouts.app')

@section('title', 'Hợp Tác Kinh Doanh Cùng Công Ty Luật Minh Anh Vũ - CÔNG TY LUẬT TNHH MTV MINH ANH VŨ')
@section('meta_description', 'Chính sách hợp tác kinh doanh, hợp lực chuyên môn và đồng hành dài hạn cùng Công ty Luật TNHH MTV Minh Anh Vũ.')

@section('content')
  @php
    $locale = app()->getLocale();
    $canEdit = \App\Support\ClientEditorChrome::editorActive();
  @endphp

  @include('client.partials.page-banner', [
    'title' => 'Hợp Tác Kinh Doanh Cùng Minh Anh Vũ',
    'titleKey' => 'cooperation.banner.title',
    'bgKey' => 'cooperation.banner.bg',
    'breadcrumbs' => [
      ['label' => 'Hợp tác kinh doanh', 'url' => null],
    ],
  ])

  <main class="py-16 sm:py-20 bg-slate-50 flex-1" @if($canEdit) data-outline-section="cooperation-main" data-outline-label="Hợp tác kinh doanh" @endif>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">

        <div class="lg:col-span-8 space-y-10" @if($canEdit) data-outline-group="cooperation-content" data-outline-kind="column" data-outline-group-label="Nội dung Hợp tác kinh doanh" @endif>

          <!-- KHỐI 1: VỀ CHÚNG TÔI -->
          <div class="bg-white rounded-2xl p-8 sm:p-10 border border-slate-200 shadow-sm space-y-4" @if($canEdit) data-outline-group="cooperation-block-1" data-outline-kind="card" data-outline-group-label="Về chúng tôi" @endif>
            <x-client::editable key="cooperation.about.eyebrow" tag="span" class="text-brand-red font-bold text-sm uppercase tracking-wider">Hãng luật uy tín</x-client::editable>
            <x-client::editable key="cooperation.about.title" tag="h2" class="text-2xl sm:text-3xl font-bold text-navy-900 tracking-tight">VỀ CHÚNG TÔI</x-client::editable>
            <div class="w-16 h-1 bg-brand-red rounded-full"></div>
            <div class="space-y-4 text-slate-600 leading-relaxed text-base sm:text-lg text-justify font-normal">
              <x-client::editable key="cooperation.about.p1" tag="p" :html="true"><strong>Công ty Luật TNHH MTV Minh Anh Vũ</strong> được hình thành trên nền tảng kết hợp giữa đội ngũ luật sư năng động, tận tâm và các chuyên gia có nhiều năm kinh nghiệm trong hoạt động tư vấn pháp lý, quản trị doanh nghiệp và giải quyết tranh chấp.</x-client::editable>
              <x-client::editable key="cooperation.about.p2" tag="p">Với định hướng xây dựng một tổ chức hành nghề luật chuyên nghiệp, minh bạch và thực tiễn, Minh Anh Vũ cung cấp các giải pháp pháp lý toàn diện cho cá nhân, doanh nghiệp, nhà đầu tư trong và ngoài nước.</x-client::editable>
              <x-client::editable key="cooperation.about.p3" tag="p">Phạm vi dịch vụ của chúng tôi bao gồm tư vấn pháp luật thường xuyên, pháp lý doanh nghiệp, đầu tư, thương mại, hợp đồng, lao động, đất đai, thủ tục hành chính, giải quyết tranh chấp và tham gia tố tụng, cùng nhiều dịch vụ pháp lý chuyên sâu khác theo nhu cầu của khách hàng.</x-client::editable>
              <x-client::editable key="cooperation.about.p4" tag="p" :html="true">Tại Minh Anh Vũ, chúng tôi không chỉ tập trung vào việc giải quyết một vấn đề pháp lý cụ thể, mà còn chú trọng <strong>nhận diện và kiểm soát rủi ro ngay từ đầu</strong>. Mỗi phương án tư vấn đều được xây dựng trên cơ sở pháp luật, tính khả thi trong thực tiễn và mục tiêu dài hạn của khách hàng.</x-client::editable>
              <x-client::editable key="cooperation.about.p5" tag="p">Chúng tôi cam kết mang đến dịch vụ pháp lý tận tâm, minh bạch, hiệu quả và bảo mật, với mong muốn trở thành người đồng hành pháp lý đáng tin cậy trong quá trình phát triển của mỗi khách hàng.</x-client::editable>
            </div>
          </div>

          <!-- KHỐI 2: MỤC TIÊU HỢP TÁC VÀ PHÁT TRIỂN -->
          <div class="bg-white rounded-2xl p-8 sm:p-10 border border-slate-200 shadow-sm space-y-4" @if($canEdit) data-outline-group="cooperation-block-2" data-outline-kind="card" data-outline-group-label="Mục tiêu hợp tác" @endif>
            <x-client::editable key="cooperation.goal.eyebrow" tag="span" class="text-brand-red font-bold text-sm uppercase tracking-wider">Chiến lược liên kết</x-client::editable>
            <x-client::editable key="cooperation.goal.title" tag="h2" class="text-2xl sm:text-3xl font-bold text-navy-900 tracking-tight">MỤC TIÊU HỢP TÁC VÀ PHÁT TRIỂN</x-client::editable>
            <div class="w-16 h-1 bg-brand-red rounded-full"></div>
            <x-client::editable key="cooperation.goal.desc" tag="p" class="text-slate-600 leading-relaxed text-base sm:text-lg text-justify font-normal">Chính sách hợp tác của Công ty Luật Minh Anh Vũ được xây dựng với mục tiêu thiết lập những mối quan hệ lâu dài, chuyên nghiệp và cùng tạo ra giá trị giữa Minh Anh Vũ với các cá nhân, doanh nghiệp và đối tác trong nhiều lĩnh vực.</x-client::editable>
          </div>

          <!-- KHỐI 3: HỢP LỰC CHUYÊN MÔN & ĐỒNG HÀNH DÀI HẠN -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6" @if($canEdit) data-outline-group="cooperation-block-3" data-outline-kind="grid" data-outline-group-label="Hợp lực & Đồng hành" @endif>

            <div class="bg-white rounded-2xl p-8 border border-slate-200 shadow-sm space-y-4" @if($canEdit) data-outline-group="cooperation-synergy" data-outline-kind="card" data-outline-group-label="Hợp lực chuyên môn" @endif>
              <div class="w-12 h-12 rounded-xl bg-red-50 text-brand-red flex items-center justify-center text-xl">
                <i class="fa-solid fa-handshake"></i>
              </div>
              <x-client::editable key="cooperation.synergy.title" tag="h3" class="text-xl font-bold text-navy-900 uppercase">HỢP LỰC CHUYÊN MÔN</x-client::editable>
              <x-client::editable key="cooperation.synergy.desc" tag="p" class="text-sm sm:text-base text-slate-600 leading-relaxed text-justify font-normal">Minh Anh Vũ mong muốn xây dựng mạng lưới hợp tác với các tổ chức, doanh nghiệp và chuyên gia có cùng định hướng phát triển. Trên cơ sở thế mạnh của mỗi bên, các đối tác có thể giới thiệu, kết nối khách hàng có nhu cầu sử dụng dịch vụ phù hợp, qua đó mở rộng hệ sinh thái dịch vụ và gia tăng giá trị dành cho khách hàng.</x-client::editable>
            </div>

            <div class="bg-white rounded-2xl p-8 border border-slate-200 shadow-sm space-y-4" @if($canEdit) data-outline-group="cooperation-longterm" data-outline-kind="card" data-outline-group-label="Đồng hành dài hạn" @endif>
              <div class="w-12 h-12 rounded-xl bg-red-50 text-brand-red flex items-center justify-center text-xl">
                <i class="fa-solid fa-chart-line"></i>
              </div>
              <x-client::editable key="cooperation.longterm.title" tag="h3" class="text-xl font-bold text-navy-900 uppercase">ĐỒNG HÀNH DÀI HẠN</x-client::editable>
              <x-client::editable key="cooperation.longterm.desc" tag="p" class="text-sm sm:text-base text-slate-600 leading-relaxed text-justify font-normal">Chúng tôi hướng đến những quan hệ hợp tác không chỉ dựa trên cơ hội kinh doanh trước mắt, mà được xây dựng trên nền tảng uy tín, trách nhiệm và lợi ích bền vững. Thông qua sự phối hợp chặt chẽ giữa các bên, Minh Anh Vũ kỳ vọng cùng đối tác tạo lập những giá trị lâu dài, đồng thời nâng cao chất lượng dịch vụ và trải nghiệm của khách hàng.</x-client::editable>
            </div>

          </div>

          <!-- KHỐI 4: GIẢI PHÁP HIỆU QUẢ, KIỂM SOÁT RỦI RO -->
          <div class="bg-white rounded-2xl p-8 sm:p-10 border border-slate-200 shadow-sm space-y-4" @if($canEdit) data-outline-group="cooperation-block-4" data-outline-kind="card" data-outline-group-label="Giải pháp hiệu quả" @endif>
            <div class="w-12 h-12 rounded-xl bg-navy-900 text-white flex items-center justify-center text-xl">
              <i class="fa-solid fa-shield-halved"></i>
            </div>
            <x-client::editable key="cooperation.solution.title" tag="h2" class="text-2xl sm:text-3xl font-bold text-navy-900 tracking-tight">GIẢI PHÁP HIỆU QUẢ, KIỂM SOÁT RỦI RO</x-client::editable>
            <div class="w-16 h-1 bg-brand-red rounded-full"></div>
            <div class="space-y-4 text-slate-600 leading-relaxed text-base sm:text-lg text-justify font-normal">
              <x-client::editable key="cooperation.solution.p1" tag="p">Trong mỗi hoạt động tư vấn hoặc hợp tác, Minh Anh Vũ luôn đặt trọng tâm vào việc nhận diện rủi ro pháp lý, xây dựng phương án phù hợp và bảo vệ tốt nhất quyền, lợi ích hợp pháp của khách hàng.</x-client::editable>
              <x-client::editable key="cooperation.solution.p2" tag="p">Đối với doanh nghiệp và nhà đầu tư, mục tiêu của chúng tôi là góp phần xây dựng nền tảng pháp lý an toàn, hỗ trợ quá trình ra quyết định và tạo điều kiện để khách hàng tập trung phát triển hoạt động kinh doanh một cách ổn định và bền vững.</x-client::editable>
            </div>
          </div>

          <!-- KHỐI 5: LỜI TRI ÂN TỪ MINH ANH VŨ -->
          <div class="bg-navy-900 rounded-2xl p-8 sm:p-12 text-white border border-navy-800 shadow-xl space-y-6" @if($canEdit) data-outline-group="cooperation-block-5" data-outline-kind="card" data-outline-group-label="Lời tri ân" @endif>
            <div class="flex items-center gap-3">
              <div class="bg-brand-red text-white p-2.5 rounded-lg shadow">
                <i class="fa-solid fa-heart text-2xl"></i>
              </div>
              <x-client::editable key="cooperation.gratitude.title" tag="h2" class="text-2xl sm:text-3xl font-bold uppercase tracking-tight text-white">LỜI TRI ÂN TỪ MINH ANH VŨ</x-client::editable>
            </div>
            <div class="w-20 h-1 bg-brand-red rounded-full"></div>

            <x-client::editable key="cooperation.gratitude.quote" tag="blockquote" class="p-4 bg-navy-950/80 rounded-xl border-l-4 border-brand-red italic text-base sm:text-lg text-slate-200">“Sự tin tưởng của khách hàng không chỉ là sự ghi nhận đối với chất lượng dịch vụ, mà còn là trách nhiệm để Minh Anh Vũ không ngừng hoàn thiện trong từng công việc được giao.”</x-client::editable>

            <div class="space-y-4 text-slate-300 leading-relaxed text-base text-justify font-normal">
              <x-client::editable key="cooperation.gratitude.p1" tag="p">Trong quá trình hình thành và phát triển, Công ty Luật Minh Anh Vũ trân trọng từng cơ hội được đồng hành cùng khách hàng trong việc giải quyết các vấn đề pháp lý, bảo vệ quyền lợi và xây dựng nền tảng pháp lý an toàn cho hoạt động kinh doanh cũng như cuộc sống.</x-client::editable>
              <x-client::editable key="cooperation.gratitude.p2" tag="p">Mỗi sự tin tưởng, mỗi phản hồi tích cực và mỗi lần khách hàng tiếp tục lựa chọn Minh Anh Vũ đều là nguồn động lực để đội ngũ luật sư và chuyên gia của chúng tôi không ngừng nâng cao năng lực chuyên môn, chuẩn hóa quy trình làm việc và hoàn thiện chất lượng dịch vụ.</x-client::editable>
              <x-client::editable key="cooperation.gratitude.p3" tag="p">Minh Anh Vũ hiểu rằng trong lĩnh vực pháp lý, điều khách hàng cần không chỉ là một câu trả lời đúng quy định, mà còn là một giải pháp phù hợp với hoàn cảnh thực tế, được thực hiện bằng tinh thần trách nhiệm và sự tận tâm.</x-client::editable>
              <x-client::editable key="cooperation.gratitude.p4" tag="p">Chúng tôi chân thành cảm ơn Quý khách hàng và Quý đối tác đã dành sự tin tưởng cho Công ty Luật Minh Anh Vũ. Đó là nền tảng để chúng tôi tiếp tục theo đuổi định hướng trở thành một tổ chức hành nghề luật chuyên nghiệp, thực tiễn, đáng tin cậy và đồng hành lâu dài cùng khách hàng.</x-client::editable>
            </div>
          </div>

          <!-- FORM ĐĂNG KÝ HỢP TÁC -->
          <div class="bg-white rounded-2xl p-8 sm:p-10 border border-slate-200 shadow-sm space-y-6" @if($canEdit) data-outline-group="cooperation-form-card" data-outline-kind="card" data-outline-group-label="Form đăng ký hợp tác" @endif>
            <div>
              <x-client::editable key="cooperation.form.title" tag="h3" class="text-2xl font-bold text-navy-900">Kết Nối Hợp Tác Cùng Minh Anh Vũ</x-client::editable>
              <x-client::editable key="cooperation.form.subtitle" tag="p" class="text-sm text-slate-500 font-normal mt-1">Vui lòng gửi thông tin, Ban Giám đốc MAV Law sẽ trực tiếp liên hệ trao đổi.</x-client::editable>
            </div>

            <form action="{{ route('client.contact.submit', ['locale' => $locale]) }}" method="post" class="consultation-form space-y-4" data-ajax-form="true">
              @csrf
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <x-client::editable key="cooperation.form.label_name" tag="label" class="block text-sm font-semibold text-navy-900 uppercase mb-1">Họ tên / Tổ chức đại diện *</x-client::editable>
                  <input type="text" name="fullname" required placeholder="Nguyễn Văn A / Công ty XYZ" class="w-full px-4 py-3.5 rounded-lg border border-slate-300 text-base focus:ring-1 focus:ring-brand-red focus:border-brand-red outline-none font-normal shadow-sm" />
                </div>
                <div>
                  <x-client::editable key="cooperation.form.label_phone" tag="label" class="block text-sm font-semibold text-navy-900 uppercase mb-1">Số điện thoại *</x-client::editable>
                  <input type="tel" name="phone" required placeholder="0397 999 123" class="w-full px-4 py-3.5 rounded-lg border border-slate-300 text-base focus:ring-1 focus:ring-brand-red focus:border-brand-red outline-none font-normal shadow-sm" />
                </div>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <x-client::editable key="cooperation.form.label_email" tag="label" class="block text-sm font-semibold text-navy-900 uppercase mb-1">Địa chỉ Email</x-client::editable>
                  <input type="email" name="email" placeholder="example@email.com" class="w-full px-4 py-3.5 rounded-lg border border-slate-300 text-base focus:ring-1 focus:ring-brand-red focus:border-brand-red outline-none font-normal shadow-sm" />
                </div>
                <div>
                  <x-client::editable key="cooperation.form.label_service" tag="label" class="block text-sm font-semibold text-navy-900 uppercase mb-1">Hình thức hợp tác</x-client::editable>
                  <select name="service" data-editable-ignore="tùy chọn trong thẻ select không chọn trực tiếp được từ bộ sửa" class="w-full px-4 py-3.5 rounded-lg border border-slate-300 text-base focus:ring-1 focus:ring-brand-red focus:border-brand-red outline-none bg-white font-normal text-slate-700 shadow-sm">
                    <option value="Hợp tác kết nối khách hàng">Hợp tác kết nối khách hàng</option>
                    <option value="Hợp tác chuyên môn chuyên sâu">Hợp tác chuyên môn chuyên sâu</option>
                    <option value="Đối tác dịch vụ doanh nghiệp">Đối tác dịch vụ doanh nghiệp</option>
                    <option value="Hình thức hợp tác khác">Hình thức hợp tác khác</option>
                  </select>
                </div>
              </div>

              <div>
                <x-client::editable key="cooperation.form.label_message" tag="label" class="block text-sm font-semibold text-navy-900 uppercase mb-1">Nội dung trao đổi ban đầu</x-client::editable>
                <textarea rows="4" name="message" placeholder="Mô tả sơ lược về định hướng và mong muốn hợp tác cùng MAV Law..." class="w-full px-4 py-3.5 rounded-lg border border-slate-300 text-base focus:ring-1 focus:ring-brand-red focus:border-brand-red outline-none font-normal shadow-sm"></textarea>
              </div>

              <button type="submit" class="w-full py-4 rounded-lg bg-brand-red hover:bg-brand-redDark text-white font-bold text-sm uppercase tracking-wider shadow-lg shadow-red-600/30 transition">
                <i class="fa-solid fa-paper-plane mr-2"></i>
                <x-client::editable key="cooperation.form.submit" tag="span">Gửi Yêu Cầu Hợp Tác</x-client::editable>
              </button>
            </form>
          </div>

        </div>

        <div class="lg:col-span-4">
          @include('client.partials.service-sidebar', ['activeSlug' => 'hop-tac-kinh-doanh'])
        </div>

      </div>
    </div>
  </main>
@endsection
