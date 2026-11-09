/**
 * Main JavaScript for Minh Anh Vu Law Firm (MAV Law)
 * Pure Vanilla ES6 - No jQuery required
 */

document.addEventListener('DOMContentLoaded', () => {
  initHeader();
  initMobileMenu();
  initHeroSlider();
  initTestimonialSlider();
  initConsultationForms();
  initBackToTop();
});

/* 1. Header Sticky & Scroll Effect */
function initHeader() {
  const header = document.getElementById('main-header');
  if (!header) return;

  window.addEventListener('scroll', () => {
    if (window.scrollY > 50) {
      header.classList.add('shadow-lg', 'bg-brand-darker/95');
    } else {
      header.classList.remove('shadow-lg');
    }
  }, { passive: true });
}

/* 2. Mobile Drawer Navigation */
function initMobileMenu() {
  const openBtn = document.getElementById('mobile-menu-btn');
  const closeBtn = document.getElementById('close-drawer-btn');
  const drawer = document.getElementById('mobile-drawer');
  const backdrop = document.getElementById('drawer-backdrop');

  if (!openBtn || !drawer) return;

  function openDrawer() {
    drawer.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
  }

  function closeDrawer() {
    drawer.classList.add('hidden');
    document.body.style.overflow = '';
  }

  openBtn.addEventListener('click', openDrawer);
  if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
  if (backdrop) backdrop.addEventListener('click', closeDrawer);

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !drawer.classList.contains('hidden')) {
      closeDrawer();
    }
  });
}

/* 3. Hero Carousel Slider */
function initHeroSlider() {
  const slider = document.getElementById('hero-slider') || document.querySelector('.hero-slider');
  if (!slider) return;

  const section = slider.closest('section') || document;
  const slides = slider.querySelectorAll('.slider-item, .hero-slide');
  const prevBtn = document.getElementById('slider-prev') || section.querySelector('.hero-prev');
  const nextBtn = document.getElementById('slider-next') || section.querySelector('.hero-next');
  const dotsContainer = document.getElementById('slider-dots') || section.querySelector('.hero-dots');

  if (slides.length <= 1) return;

  let currentIndex = 0;
  let timer = null;
  const totalSlides = slides.length;

  if (dotsContainer) {
    dotsContainer.innerHTML = '';
    slides.forEach((_, idx) => {
      const dot = document.createElement('button');
      dot.type = 'button';
      dot.className = `w-3 h-3 rounded-full transition-all duration-300 ${idx === 0 ? 'bg-brand-red w-8' : 'bg-white/50 hover:bg-white'}`;
      dot.setAttribute('aria-label', `Slide ${idx + 1}`);
      dot.addEventListener('click', () => goToSlide(idx));
      dotsContainer.appendChild(dot);
    });
  }

  function updateDots() {
    if (!dotsContainer) return;
    const dots = dotsContainer.querySelectorAll('button');
    dots.forEach((dot, idx) => {
      if (idx === currentIndex) {
        dot.className = 'w-8 h-3 rounded-full bg-brand-red transition-all duration-300';
      } else {
        dot.className = 'w-3 h-3 rounded-full bg-white/50 hover:bg-white transition-all duration-300';
      }
    });
  }

  function goToSlide(index) {
    slides[currentIndex].classList.add('opacity-0', 'pointer-events-none');
    slides[currentIndex].classList.remove('opacity-100', 'z-10', 'active');

    currentIndex = (index + totalSlides) % totalSlides;

    slides[currentIndex].classList.remove('opacity-0', 'pointer-events-none');
    slides[currentIndex].classList.add('opacity-100', 'z-10', 'active');

    updateDots();
    resetTimer();
  }

  function nextSlide() {
    if (document.body.classList.contains('client-edit-active')) return;
    goToSlide(currentIndex + 1);
  }

  function prevSlide() {
    goToSlide(currentIndex - 1);
  }

  function startTimer() {
    clearInterval(timer);
    timer = setInterval(nextSlide, 6000);
  }

  function resetTimer() {
    clearInterval(timer);
    startTimer();
  }

  if (nextBtn) nextBtn.addEventListener('click', () => { goToSlide(currentIndex + 1); resetTimer(); });
  if (prevBtn) prevBtn.addEventListener('click', () => { prevSlide(); resetTimer(); });

  slider.addEventListener('mouseenter', () => clearInterval(timer));
  slider.addEventListener('mouseleave', startTimer);

  let touchStartX = 0;
  slider.addEventListener('touchstart', (e) => {
    touchStartX = e.changedTouches[0].screenX;
  }, { passive: true });

  slider.addEventListener('touchend', (e) => {
    const touchEndX = e.changedTouches[0].screenX;
    if (touchStartX - touchEndX > 50) goToSlide(currentIndex + 1);
    if (touchEndX - touchStartX > 50) prevSlide();
  }, { passive: true });

  startTimer();
}

/* 4. Testimonials Slider */
function initTestimonialSlider() {
  const container = document.getElementById('testimonial-slider');
  if (!container) return;

  const slides = container.querySelectorAll('.testimonial-item');
  const dotsContainer = document.getElementById('testimonial-dots');
  if (slides.length <= 1) return;

  let current = 0;

  if (dotsContainer) {
    dotsContainer.innerHTML = '';
    slides.forEach((_, idx) => {
      const dot = document.createElement('button');
      dot.type = 'button';
      dot.className = `w-3 h-3 rounded-full transition-all duration-300 ${idx === 0 ? 'bg-brand-500 w-6' : 'bg-gray-300 hover:bg-gray-400'}`;
      dot.addEventListener('click', () => showTestimonial(idx));
      dotsContainer.appendChild(dot);
    });
  }

  function showTestimonial(idx) {
    slides[current].classList.add('hidden');
    current = idx;
    slides[current].classList.remove('hidden');

    if (dotsContainer) {
      const dots = dotsContainer.querySelectorAll('button');
      dots.forEach((d, i) => {
        d.className = i === current ? 'w-6 h-3 rounded-full bg-brand-500 transition-all duration-300' : 'w-3 h-3 rounded-full bg-gray-300 hover:bg-gray-400 transition-all duration-300';
      });
    }
  }

  setInterval(() => {
    if (document.body.classList.contains('client-edit-active')) return;
    showTestimonial((current + 1) % slides.length);
  }, 5000);
}

/* 5. Consultation & Contact Forms */
function initConsultationForms() {
  const forms = document.querySelectorAll('form[data-ajax-form="true"], form.consultation-form');
  forms.forEach(form => {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (document.body.classList.contains('client-edit-active')) return;

      const submitBtn = form.querySelector('button[type="submit"]');
      const originalText = submitBtn ? submitBtn.innerHTML : '';

      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i> Đang gửi yêu cầu...';
      }

      const formData = new FormData(form);
      if (!formData.get('name') && formData.get('fullname')) {
        formData.set('name', formData.get('fullname'));
      }
      if (!formData.get('email')) {
        formData.set('email', 'khachhang@minhanhvulaw.com');
      }
      if (formData.get('service')) {
        const msg = formData.get('message') || '';
        formData.set('message', `[${formData.get('service')}] ${msg}`.trim());
      } else if (!formData.get('message')) {
        formData.set('message', 'Đăng ký tư vấn pháp lý');
      }

      const csrfMeta = document.querySelector('meta[name="csrf-token"]');
      const headers = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
      if (csrfMeta && csrfMeta.content) {
        headers['X-CSRF-TOKEN'] = csrfMeta.content;
      }

      const endpoint = form.getAttribute('action') || '/lien-he';
      let isSuccess = true;
      let respMessage = 'Luật sư của Công ty Luật Minh Anh Vũ sẽ liên hệ với bạn qua số điện thoại đã cung cấp trong vòng 30 phút.';

      try {
        const res = await fetch(endpoint, {
          method: 'POST',
          headers,
          body: formData
        });
        const result = await res.json().catch(() => ({ success: res.ok }));
        if (result && result.message) {
          respMessage = result.message;
        }
        if (!res.ok || (result && result.success === false)) {
          isSuccess = false;
        }
      } catch (err) {
        console.log('Form submit info:', err);
      } finally {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.innerHTML = originalText;
        }

        let alertBox = form.querySelector('.form-alert');
        if (!alertBox) {
          alertBox = document.createElement('div');
          form.appendChild(alertBox);
        }

        if (isSuccess) {
          alertBox.className = 'form-alert mt-4 p-4 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm flex items-start gap-3 animate-fade-in';
          alertBox.innerHTML = `
            <i class="fa-solid fa-circle-check text-green-600 text-lg mt-0.5 flex-shrink-0"></i>
            <div>
              <strong class="block font-semibold">Gửi yêu cầu tư vấn thành công!</strong>
              <span>${respMessage}</span>
            </div>
          `;
          form.reset();
        } else {
          alertBox.className = 'form-alert mt-4 p-4 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm flex items-start gap-3 animate-fade-in';
          alertBox.innerHTML = `
            <i class="fa-solid fa-circle-exclamation text-brand-red text-lg mt-0.5 flex-shrink-0"></i>
            <div>
              <strong class="block font-semibold">Thông báo</strong>
              <span>${respMessage}</span>
            </div>
          `;
        }
      }
    });
  });
}

/* 6. Back to Top Button */
function initBackToTop() {
  const btn = document.getElementById('back-to-top');
  if (!btn) return;

  window.addEventListener('scroll', () => {
    if (window.scrollY > 400) {
      btn.classList.remove('opacity-0', 'pointer-events-none');
      btn.classList.add('opacity-100');
    } else {
      btn.classList.add('opacity-0', 'pointer-events-none');
      btn.classList.remove('opacity-100');
    }
  }, { passive: true });

  btn.addEventListener('click', () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });
}
