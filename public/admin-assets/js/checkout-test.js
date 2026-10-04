/*
 * Admin → Thử đặt hàng. Places an order through the public checkout API and looks up GHN
 * address codes through the admin proxy (the GHN token never reaches the browser).
 */
(() => {
  const form = document.getElementById('checkout-test-form');
  if (!form) return;

  const items = document.getElementById('checkout-test-items');
  const template = document.getElementById('checkout-test-item-template');
  const result = document.getElementById('checkout-test-result');
  const products = JSON.parse(document.getElementById('checkout-test-products').textContent);
  const byId = Object.fromEntries(products.map((p) => [String(p.id), p]));
  const money = (n) => new Intl.NumberFormat('vi-VN').format(Math.round(n)) + ' ₫';

  const escape = (text) => String(text ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  const show = (type, html) => {
    result.innerHTML = `<div class="alert alert-${type} small mb-0">${html}</div>`;
  };

  // ---- Product lines ------------------------------------------------------

  const renderOptions = (row) => {
    const target = row.querySelector('[data-role=options]');
    const product = byId[row.querySelector('[name=product_id]').value];
    target.replaceChildren();
    (product?.options || []).forEach((group) => {
      const col = document.createElement('div');
      col.className = 'col-md-4';
      const label = document.createElement('label');
      label.className = 'form-label small mb-1';
      label.textContent = group.name;
      const select = document.createElement('select');
      select.className = 'form-select form-select-sm';
      select.name = 'option_value_id';
      select.required = true;
      select.append(new Option(`Chọn ${group.name}`, ''));
      group.values.forEach((value) => select.append(new Option(value.label, value.id)));
      col.append(label, select);
      target.append(col);
    });
  };

  const summarize = () => {
    let subtotal = 0;
    const rows = [...items.querySelectorAll('[data-role=item]')];
    rows.forEach((row) => {
      const product = byId[row.querySelector('[name=product_id]').value];
      subtotal += (product?.price || 0) * Math.max(1, Number(row.querySelector('[name=quantity]').value) || 1);
    });
    form.querySelector('[data-role=line-count]').textContent = rows.length;
    form.querySelector('[data-role=subtotal]').textContent = money(subtotal);
  };

  const addItem = () => {
    items.append(template.content.cloneNode(true));
    renderOptions(items.lastElementChild);
    summarize();
  };

  form.addEventListener('click', (event) => {
    const button = event.target.closest('[data-action]');
    if (!button) return;
    if (button.dataset.action === 'add-item') addItem();
    if (button.dataset.action === 'remove-item' && items.children.length > 1) {
      button.closest('[data-role=item]').remove();
      summarize();
    }
  });
  form.addEventListener('change', (event) => {
    if (event.target.name === 'product_id') renderOptions(event.target.closest('[data-role=item]'));
    if (['product_id', 'quantity'].includes(event.target.name)) summarize();
  });
  form.addEventListener('input', (event) => {
    if (event.target.name === 'quantity') summarize();
  });

  if (products.length) addItem();

  // ---- GHN ----------------------------------------------------------------

  const ghn = document.getElementById('ghn-address');
  if (ghn) {
    const province = document.getElementById('ghn-province');
    const district = document.getElementById('ghn-district');
    const ward = document.getElementById('ghn-ward');
    const codes = document.getElementById('ghn-codes');
    const badge = document.getElementById('ghn-status-badge');
    const statusMessage = document.getElementById('ghn-status-message');
    const settingsLink = document.getElementById('ghn-settings-link');

    const getJson = async (url) => {
      const response = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
      const body = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(body.message || 'Không lấy được dữ liệu GHN.');
      return body;
    };

    const fill = (select, rows, placeholder, label, value) => {
      select.replaceChildren(new Option(placeholder, ''));
      rows.forEach((row) => select.append(new Option(row[label], row[value])));
      select.disabled = false;
    };

    const reset = (select, placeholder) => {
      select.replaceChildren(new Option(placeholder, ''));
      select.disabled = true;
    };

    const setStatus = (ok, message) => {
      badge.className = `badge ${ok ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'}`;
      badge.textContent = ok ? 'Hoạt động' : 'Lỗi';
      statusMessage.textContent = message;
      if (settingsLink) settingsLink.hidden = ok;
    };

    getJson(ghn.dataset.statusUrl)
      .then((status) => {
        setStatus(status.ok, status.ok && status.shop_name ? `${status.message} Shop: ${status.shop_name}.` : status.message);
        if (!status.ok) {
          reset(province, 'GHN chưa kết nối được');
          return;
        }
        return getJson(ghn.dataset.provincesUrl).then((body) => fill(province, body.data, 'Chọn tỉnh/thành', 'ProvinceName', 'ProvinceID'));
      })
      .catch((error) => {
        setStatus(false, error.message);
        reset(province, 'GHN chưa kết nối được');
      });

    province.addEventListener('change', () => {
      reset(district, 'Đang tải…');
      reset(ward, 'Chọn quận/huyện trước');
      codes.hidden = true;
      if (!province.value) return reset(district, 'Chọn tỉnh trước');
      getJson(ghn.dataset.districtsUrl.replace('__ID__', province.value))
        .then((body) => fill(district, body.data, 'Chọn quận/huyện', 'DistrictName', 'DistrictID'))
        .catch((error) => show('danger', escape(error.message)));
    });

    district.addEventListener('change', () => {
      reset(ward, 'Đang tải…');
      codes.hidden = true;
      if (!district.value) return reset(ward, 'Chọn quận/huyện trước');
      getJson(ghn.dataset.wardsUrl.replace('__ID__', district.value))
        .then((body) => fill(ward, body.data, 'Chọn phường/xã', 'WardName', 'WardCode'))
        .catch((error) => show('danger', escape(error.message)));
    });

    ward.addEventListener('change', () => {
      codes.hidden = !ward.value;
      codes.querySelector('[data-role=district-id]').textContent = district.value;
      codes.querySelector('[data-role=ward-code]').textContent = ward.value;
    });

    // The administrative names end the address the order stores.
    form.addEventListener('submit', () => {
      if (!ward.value) return;
      const address = form.elements.shipping_address;
      const suffix = [ward, district, province].map((s) => s.selectedOptions[0].text).join(', ');
      if (!address.value.includes(suffix)) address.value = [address.value.trim(), suffix].filter(Boolean).join(', ');
    }, true);
  }

  // ---- Submit -------------------------------------------------------------

  // Sent only once the ward is chosen: a district without its ward cannot ship.
  const ghnCodes = () => {
    const ward = document.getElementById('ghn-ward')?.value;
    if (!ward) return {};
    return {
      ghn_province_id: Number(document.getElementById('ghn-province').value) || null,
      ghn_district_id: Number(document.getElementById('ghn-district').value),
      ghn_ward_code: ward,
    };
  };

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    result.replaceChildren();

    if (!form.checkValidity()) {
      form.classList.add('was-validated');
      show('warning', 'Vui lòng điền đủ các trường bắt buộc.');
      return;
    }

    const submit = form.querySelector('[type=submit]');
    submit.disabled = true;

    const data = new FormData(form);
    const payload = {
      customer_name: data.get('customer_name'),
      customer_email: data.get('customer_email'),
      customer_phone: data.get('customer_phone'),
      payment_method: data.get('payment_method'),
      shipping_address: data.get('shipping_address'),
      notes: data.get('notes') || null,
      // GHN codes go with the order: the shipment is then one click, or automatic on confirm.
      ...ghnCodes(),
      items: [...items.querySelectorAll('[data-role=item]')].map((row) => ({
        product_id: Number(row.querySelector('[name=product_id]').value),
        quantity: Number(row.querySelector('[name=quantity]').value),
        option_value_ids: [...row.querySelectorAll('[name=option_value_id]')].map((s) => Number(s.value)).filter(Boolean),
      })),
    };

    try {
      const response = await fetch(form.dataset.checkoutUrl, {
        method: 'POST',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const body = await response.json().catch(() => ({}));
      if (!response.ok) throw body;

      const number = body.data?.order?.order_number;
      const link = number ? `${form.dataset.ordersUrl}?q=${encodeURIComponent(number)}` : form.dataset.ordersUrl;
      show('success', `Tạo đơn thành công${number ? `: <strong>${escape(number)}</strong>` : ''}. <a href="${link}">Xem trong danh sách đơn hàng →</a>`);
      if (body.data?.payment_url) window.location.assign(body.data.payment_url);
    } catch (error) {
      const message = error?.errors ? Object.values(error.errors).flat().join(' ') : (error?.message || 'Không thể tạo đơn.');
      show('danger', escape(message));
    } finally {
      submit.disabled = false;
    }
  });
})();
