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
  // Text from the view (window.CheckoutTestText, admin.checkout_test.js); English if it is missing.
  const text = window.CheckoutTestText || {};
  const t = (key, fallback, replace = {}) => Object.entries(replace)
    .reduce((line, [name, value]) => line.split(`:${name}`).join(value), text[key] || fallback);

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
      select.append(new Option(t('choose_option', 'Choose :name', { name: group.name }), ''));
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
      if (!response.ok) throw new Error(body.message || t('ghn_lookup_failed', 'Could not load GHN data.'));
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
      badge.textContent = ok ? t('active', 'Active') : t('error', 'Error');
      statusMessage.textContent = message;
      if (settingsLink) settingsLink.hidden = ok;
    };

    getJson(ghn.dataset.statusUrl)
      .then((status) => {
        setStatus(status.ok, status.ok && status.shop_name ? t('status_with_shop', ':message Shop: :shop.', { message: status.message, shop: status.shop_name }) : status.message);
        if (!status.ok) {
          reset(province, t('ghn_unavailable', 'GHN is not connected'));
          return;
        }
        return getJson(ghn.dataset.provincesUrl).then((body) => fill(province, body.data, t('choose_province', 'Choose a province/city'), 'ProvinceName', 'ProvinceID'));
      })
      .catch((error) => {
        setStatus(false, error.message);
        reset(province, t('ghn_unavailable', 'GHN is not connected'));
      });

    province.addEventListener('change', () => {
      reset(district, t('loading', 'Loading…'));
      reset(ward, t('choose_district_first', 'Choose a district first'));
      codes.hidden = true;
      if (!province.value) return reset(district, t('choose_province_first', 'Choose a province first'));
      getJson(ghn.dataset.districtsUrl.replace('__ID__', province.value))
        .then((body) => fill(district, body.data, t('choose_district', 'Choose a district'), 'DistrictName', 'DistrictID'))
        .catch((error) => show('danger', escape(error.message)));
    });

    district.addEventListener('change', () => {
      reset(ward, t('loading', 'Loading…'));
      codes.hidden = true;
      if (!district.value) return reset(ward, t('choose_district_first', 'Choose a district first'));
      getJson(ghn.dataset.wardsUrl.replace('__ID__', district.value))
        .then((body) => fill(ward, body.data, t('choose_ward', 'Choose a ward'), 'WardName', 'WardCode'))
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

  // One Idempotency-Key per order attempt: resending the same form (after a timeout or a
  // network error) reuses it, so the server returns the order it may already have made.
  // A changed form is a new attempt with a new key. randomUUID needs a secure context.
  let attempt = null;
  const newKey = () => (window.crypto.randomUUID
    ? window.crypto.randomUUID()
    : [...window.crypto.getRandomValues(new Uint8Array(16))].map((b) => b.toString(16).padStart(2, '0')).join(''));
  const keyFor = (payload) => {
    const body = JSON.stringify(payload);
    if (!attempt || attempt.body !== body) attempt = { body, key: newKey() };
    return attempt.key;
  };

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    result.replaceChildren();

    if (!form.checkValidity()) {
      form.classList.add('was-validated');
      show('warning', escape(t('required_fields', 'Please fill in all required fields.')));
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
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'Idempotency-Key': keyFor(payload) },
        body: JSON.stringify(payload),
      });
      const body = await response.json().catch(() => ({}));
      if (response.status === 409) attempt = null; // key spent: the next submit starts over
      if (!response.ok) throw body;
      attempt = null; // placed: the next submit is a new order

      const number = body.data?.order?.order_number;
      const link = number ? `${form.dataset.ordersUrl}?q=${encodeURIComponent(number)}` : form.dataset.ordersUrl;
      const created = number
        ? escape(t('created_number', 'Order created: :number.')).split(':number').join(`<strong>${escape(number)}</strong>`)
        : escape(t('created', 'Order created.'));
      show('success', `${created} <a href="${link}">${escape(t('view_orders', 'View in the order list →'))}</a>`);
      if (body.data?.payment_url) window.location.assign(body.data.payment_url);
    } catch (error) {
      const message = error?.errors ? Object.values(error.errors).flat().join(' ') : (error?.message || t('create_failed', 'Could not create the order.'));
      show('danger', escape(message));
    } finally {
      submit.disabled = false;
    }
  });
})();
