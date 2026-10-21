
(function () {
  'use strict';

  const cfg = window.RUBICAM_CONFIG || {};

 
  const menuBtn = document.getElementById('mobile-menu-btn');
  const mobileMenu = document.getElementById('mobile-menu');
  if (menuBtn && mobileMenu) {
    menuBtn.addEventListener('click', () => {
      const isOpen = !mobileMenu.classList.contains('hidden');
      mobileMenu.classList.toggle('hidden');
      menuBtn.setAttribute('aria-expanded', String(!isOpen));
    });
  }


  function openModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden';
    const firstInput = modal.querySelector('input, button');
    if (firstInput) firstInput.focus();
  }
  function closeModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.body.style.overflow = '';
  }
  document.querySelectorAll('[data-open-modal]').forEach((btn) => {
    btn.addEventListener('click', () => openModal(btn.dataset.openModal));
  });
  document.querySelectorAll('[data-close-modal]').forEach((btn) => {
    btn.addEventListener('click', () => closeModal(btn.dataset.closeModal));
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      document.querySelectorAll('[role="dialog"]:not(.hidden)').forEach((m) => closeModal(m.id));
    }
  });

  const quoteForm = document.getElementById('quote-form');
  if (quoteForm) {
    const submitLabel = document.getElementById('quote-submit-label');
    const spinner = document.getElementById('quote-spinner');
    const responseBox = document.getElementById('quote-response');

    function setFieldError(field, message) {
      field.classList.add('field-invalid');
      const errorEl = field.parentElement.querySelector('.quote-error');
      if (errorEl) {
        errorEl.textContent = message;
        errorEl.classList.remove('hidden');
      }
    }
    function clearFieldError(field) {
      field.classList.remove('field-invalid');
      const errorEl = field.parentElement.querySelector('.quote-error');
      if (errorEl) errorEl.classList.add('hidden');
    }

    function validateQuoteForm() {
      let valid = true;
      const operation = quoteForm.querySelector('#q-operation');
      const cifValue = quoteForm.querySelector('#q-value');
      const name = quoteForm.querySelector('#q-name');
      const email = quoteForm.querySelector('#q-email');
      const phone = quoteForm.querySelector('#q-phone');

      [operation, cifValue, name, email, phone].forEach(clearFieldError);

      if (!operation.value) { setFieldError(operation, 'Selecciona el tipo de operación.'); valid = false; }
      if (cifValue.value && !/^\d+(\.\d{1,2})?$/.test(cifValue.value.trim())) {
        setFieldError(cifValue, 'Ingresa un valor numérico válido.'); valid = false;
      }
      if (!name.value.trim()) { setFieldError(name, 'Ingresa tu nombre.'); valid = false; }
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
        setFieldError(email, 'Ingresa un correo válido.'); valid = false;
      }
      if (!/^[0-9+\-\s()]{7,20}$/.test(phone.value.trim())) {
        setFieldError(phone, 'Ingresa un teléfono válido.'); valid = false;
      }
      return valid;
    }

    quoteForm.addEventListener('submit', function (e) {
      e.preventDefault();
      responseBox.textContent = '';
      responseBox.className = 'mt-4 text-sm font-medium';

      if (!validateQuoteForm()) return;

      submitLabel.textContent = 'Enviando…';
      spinner.classList.remove('hidden');
      quoteForm.querySelector('button[type="submit"]').disabled = true;

      const formData = new FormData(quoteForm);

      fetch(quoteForm.action, {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      })
        .then((res) => res.json().then((data) => ({ status: res.status, data })))
        .then(({ data }) => {
          responseBox.textContent = data.message;
          responseBox.classList.add(data.ok ? 'text-emerald-700' : 'text-red-600');
          if (data.ok) quoteForm.reset();
        })
        .catch(() => {
          responseBox.textContent = 'Ocurrió un error de red. Inténtalo nuevamente.';
          responseBox.classList.add('text-red-600');
        })
        .finally(() => {
          submitLabel.textContent = 'Enviar cotización';
          spinner.classList.add('hidden');
          quoteForm.querySelector('button[type="submit"]').disabled = false;
        });
    });
  }


  const trackingForm = document.getElementById('tracking-form');
  if (trackingForm) {
    trackingForm.addEventListener('submit', function (e) {
      e.preventDefault();
      const code = document.getElementById('tracking-code').value.trim();
      const result = document.getElementById('tracking-result');
      if (!code) return;

      const states = [
        { label: 'En inspección documental', color: 'amber' },
        { label: 'Nacionalizada', color: 'emerald' },
        { label: 'Próxima a vencer (3 días)', color: 'orange' },
        { label: 'En tránsito a Zona Franca', color: 'sky' },
      ];
      const idx = Math.abs(code.split('').reduce((a, c) => a + c.charCodeAt(0), 0)) % states.length;
      const state = states[idx];

      result.innerHTML = `
        <div class="rounded-lg bg-${state.color}-50 border border-${state.color}-200 px-4 py-3">
          <div class="text-xs text-slate-500">Radicado</div>
          <div class="font-semibold text-slate-800">${code.replace(/[<>]/g, '')}</div>
          <div class="mt-2 inline-flex items-center gap-1.5 text-sm font-medium text-${state.color}-700">
            <span class="w-2 h-2 rounded-full bg-${state.color}-500"></span>${state.label}
          </div>
          <p class="text-xs text-slate-400 mt-2">Esto es una vista previa. Inicia sesión en el Lobby para el detalle completo y notificaciones.</p>
        </div>`;
      result.classList.remove('hidden');
    });
  }


  
  const rubiToggle = document.getElementById('rubi-toggle');
  const rubiPanel = document.getElementById('rubi-panel');
  const rubiClose = document.getElementById('rubi-close');
  const rubiForm = document.getElementById('rubi-form');
  const rubiInput = document.getElementById('rubi-input');
  const rubiMessages = document.getElementById('rubi-messages');

  function toggleRubi(show) {
    if (!rubiPanel || !rubiToggle) return;
    const willShow = show ?? rubiPanel.classList.contains('hidden');
    rubiPanel.classList.toggle('hidden', !willShow);
    rubiToggle.setAttribute('aria-expanded', String(willShow));
    if (willShow && rubiInput) rubiInput.focus();
  }
  if (rubiToggle) rubiToggle.addEventListener('click', () => toggleRubi());
  if (rubiClose) rubiClose.addEventListener('click', () => toggleRubi(false));

  function appendMessage(text, from) {
    if (!rubiMessages) return;
    const bubble = document.createElement('div');
    if (from === 'user') {
      bubble.className = 'bg-rubi-emerald text-white rounded-xl rounded-tr-sm px-4 py-2.5 ml-auto max-w-[85%] shadow-sm';
    } else {
      bubble.className = 'bg-white rounded-xl rounded-tl-sm px-4 py-2.5 shadow-sm max-w-[85%]';
    }
    bubble.textContent = text;
    rubiMessages.appendChild(bubble);
    rubiMessages.scrollTop = rubiMessages.scrollHeight;
  }

  if (rubiForm) {
    rubiForm.addEventListener('submit', function (e) {
      e.preventDefault();
      const text = rubiInput.value.trim();
      if (!text) return;
      appendMessage(text, 'user');
      rubiInput.value = '';

      if (!cfg.chatWebhookUrl) {
        setTimeout(() => {
          appendMessage('Gracias por tu mensaje. Un asesor humano te contactará pronto. (Webhook de n8n aún no configurado — ver README.md.)', 'bot');
        }, 500);
        return;
      }

      fetch(cfg.chatWebhookUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: text, source: 'rubicam-web' }),
      })
        .then((res) => res.json())
        .then((data) => appendMessage(data.reply || 'Gracias, en breve te responderemos.', 'bot'))
        .catch(() => appendMessage('No pudimos conectar con el asistente en este momento.', 'bot'));
    });
  }
})();
