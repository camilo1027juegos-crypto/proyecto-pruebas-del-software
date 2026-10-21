/**
 * assets/js/panel.js
 * -----------------------------------------------------------------------
 * Lógica de interfaz del panel operativo de DOs (tabla, filtros, modales,
 * formularios y llamadas AJAX). Se carga como archivo externo (no en línea)
 * para poder aplicar una Content-Security-Policy estricta: script-src 'self'.
 *
 * La configuración (catálogos, token CSRF, DOs iniciales) llega en el bloque
 * <script type="application/json" id="app-config"> que genera el servidor.
 * -----------------------------------------------------------------------
 */
(() => {
    'use strict';

    // ------------------------------------------------------------------
    // Configuración y rutas (relativas a /panel/)
    // ------------------------------------------------------------------
    const APP = JSON.parse(document.getElementById('app-config').textContent);
    const URL_PANEL   = 'dashboard.php';
    const URL_WEBHOOK = '../handlers/webhook_trigger.php';
    const URL_UPLOAD  = '../handlers/upload_documento.php';
    const EXT_PERMITIDAS = ['pdf', 'jpg', 'jpeg', 'png', 'docx'];

    const estado = {
        dos: Array.isArray(APP.dos) ? APP.dos : [],
        filtros: { texto: '', sucursal: '', regimen: '', nivel: '' },
        doActual: null,
        resaltar: null,
    };

    const NIVEL_UI = {
        al_dia:      { badge: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20', punto: 'bg-emerald-500', barra: 'bg-emerald-500' },
        seguimiento: { badge: 'bg-orange-50 text-orange-700 ring-orange-600/20',   punto: 'bg-orange-500',  barra: 'bg-orange-500' },
        critico:     { badge: 'bg-red-50 text-red-700 ring-red-600/20',             punto: 'bg-red-500',     barra: 'bg-red-500' },
        cerrado:     { badge: 'bg-slate-100 text-slate-600 ring-slate-500/20',      punto: 'bg-slate-400',   barra: 'bg-slate-300' },
    };
    const ESTADO_UI = {
        'En trámite':           'bg-sky-50 text-sky-700 ring-sky-600/20',
        'Presentada DIAN':      'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
        'Aceptada':             'bg-teal-50 text-teal-700 ring-teal-600/20',
        'Nacionalizado':        'bg-slate-100 text-slate-700 ring-slate-500/20',
        'Finalizada':           'bg-slate-100 text-slate-700 ring-slate-500/20',
        'Cerrado sin facturar': 'bg-stone-100 text-stone-700 ring-stone-500/20',
    };
    const ORDEN_NIVEL = { critico: 0, seguimiento: 1, al_dia: 2, cerrado: 3 };
    const CLASES_OK  = ['border-slate-300', 'bg-white'];
    const CLASES_ERR = ['border-red-400', 'bg-red-50'];

    // ------------------------------------------------------------------
    // Utilidades
    // ------------------------------------------------------------------
    const $  = (sel, ctx = document) => ctx.querySelector(sel);
    const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));
    const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const normalizar = (v) => String(v ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
    const plural = (n, uno, varios) => `${n} ${Math.abs(n) === 1 ? uno : varios}`;

    function icono(nombre, clases = 'h-4 w-4') {
        return `<svg class="${clases}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${APP.iconos[nombre] || ''}</svg>`;
    }
    function fechaCO(iso) {
        if (!iso) return '—';
        const [y, m, d] = String(iso).slice(0, 10).split('-');
        return (y && m && d) ? esc(`${d}/${m}/${y}`) : esc(iso);
    }
    function fechaHoraCO(valor) {
        if (!valor) return '—';
        const [fecha, hora = ''] = String(valor).split(' ');
        return `${fechaCO(fecha)} ${esc(hora.slice(0, 5))}`.trim();
    }
    function sumarDias(iso, dias) {
        const [y, m, d] = String(iso).split('-').map(Number);
        if (!y || !m || !d) return '';
        return new Date(Date.UTC(y, m - 1, d + dias)).toISOString().slice(0, 10);
    }
    function tamanoLegible(bytes) {
        if (bytes == null) return '—';
        if (bytes < 1024) return `${bytes} B`;
        if (bytes < 1048576) return `${Math.round(bytes / 1024)} KB`;
        return `${(bytes / 1048576).toFixed(1)} MB`;
    }
    function dvNit(base) {
        const pesos = [3, 7, 13, 17, 19, 23, 29, 37, 41, 43, 47, 53, 59, 67, 71];
        const digitos = String(base).split('').reverse();
        const suma = digitos.reduce((acc, dig, i) => acc + Number(dig) * (pesos[i] || 0), 0);
        const r = suma % 11;
        return r > 1 ? 11 - r : r;
    }
    function botonOcupado(boton, ocupado, texto) {
        if (!boton) return;
        const etiqueta = boton.querySelector('[data-texto-boton]');
        if (ocupado) {
            boton.disabled = true;
            if (etiqueta) { boton.dataset.textoOriginal = etiqueta.textContent; etiqueta.textContent = texto || 'Procesando…'; }
        } else {
            boton.disabled = false;
            if (etiqueta && boton.dataset.textoOriginal) etiqueta.textContent = boton.dataset.textoOriginal;
        }
    }

    // ------------------------------------------------------------------
    // Notificaciones
    // ------------------------------------------------------------------
    function toast(mensaje, tipo = 'info', duracion = 5000) {
        const estilos = {
            success: { caja: 'border-emerald-200', color: 'text-emerald-600', ic: 'check' },
            error:   { caja: 'border-red-200',     color: 'text-red-600',     ic: 'alert' },
            warning: { caja: 'border-orange-200',  color: 'text-orange-500',  ic: 'alert' },
            info:    { caja: 'border-sky-200',     color: 'text-sky-600',     ic: 'info' },
        };
        const e = estilos[tipo] || estilos.info;
        const el = document.createElement('div');
        el.className = `pointer-events-auto flex items-start gap-3 rounded-xl border ${e.caja} bg-white p-4 shadow-lg transition duration-300 opacity-0 translate-y-2`;
        el.setAttribute('role', tipo === 'error' ? 'alert' : 'status');
        el.innerHTML = `<span class="${e.color} mt-0.5">${icono(e.ic, 'h-5 w-5')}</span>
            <p class="flex-1 text-sm leading-snug text-slate-700"></p>
            <button type="button" class="text-slate-400 hover:text-slate-600" aria-label="Cerrar notificación">${icono('x')}</button>`;
        el.querySelector('p').textContent = mensaje;
        const contenedor = $('#toasts');
        while (contenedor.children.length >= 4) contenedor.firstElementChild.remove();
        contenedor.appendChild(el);
        requestAnimationFrame(() => el.classList.remove('opacity-0', 'translate-y-2'));
        const cerrar = () => { el.classList.add('opacity-0'); setTimeout(() => el.remove(), 300); };
        el.querySelector('button').addEventListener('click', cerrar);
        setTimeout(cerrar, duracion);
    }

    // ------------------------------------------------------------------
    // Peticiones: siempre esperan JSON { status, message, ... }
    // ------------------------------------------------------------------
    function sesionExpirada() {
        toast('Su sesión expiró. Redirigiendo al inicio de sesión…', 'warning');
        setTimeout(() => { window.location.href = 'index.php'; }, 1500);
    }

    function procesarRespuesta(httpStatus, texto) {
        let data = null;
        try { data = texto ? JSON.parse(texto) : null; } catch (_) { data = null; }

        if (!data || typeof data !== 'object') {
            console.error('Respuesta no JSON del servidor', httpStatus, String(texto).slice(0, 800));
            throw new Error(`El servidor devolvió una respuesta inesperada (HTTP ${httpStatus}). Revise logs/php-error.log.`);
        }
        if (httpStatus === 401) sesionExpirada();
        if (data.status !== 'success') {
            const error = new Error(data.message || `Error del servidor (HTTP ${httpStatus}).`);
            error.data = data;
            error.httpStatus = httpStatus;
            throw error;
        }
        return data;
    }

    async function fetchJSON(url, opciones = {}) {
        const config = {
            credentials: 'same-origin',
            ...opciones,
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': APP.csrf,
                ...(opciones.headers || {}),
            },
        };
        let respuesta;
        try {
            respuesta = await fetch(url, config);
        } catch (_) {
            throw new Error('No hay conexión con el servidor. Verifique su red e intente de nuevo.');
        }
        return procesarRespuesta(respuesta.status, await respuesta.text());
    }

    function subirConProgreso(url, formData, alProgresar) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', url);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('X-CSRF-Token', APP.csrf);
            xhr.timeout = 180000;
            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) alProgresar(Math.round((e.loaded / e.total) * 100));
            });
            xhr.onload = () => {
                try { resolve(procesarRespuesta(xhr.status, xhr.responseText)); } catch (err) { reject(err); }
            };
            xhr.onerror = () => reject(new Error('No hay conexión con el servidor. Verifique su red e intente de nuevo.'));
            xhr.ontimeout = () => reject(new Error('La subida tardó demasiado. Intente de nuevo.'));
            xhr.send(formData);
        });
    }

    // ------------------------------------------------------------------
    // Tabla, filtros y KPIs
    // ------------------------------------------------------------------
    function filtrar(lista) {
        const f = estado.filtros;
        const texto = normalizar(f.texto);
        return lista.filter((d) => {
            const nivel = d.semaforo.nivel;
            if (texto && !normalizar(`${d.do_codigo} ${d.cliente} ${d.nit}`).includes(texto)) return false;
            if (f.sucursal && d.sucursal !== f.sucursal) return false;
            if (f.regimen && d.regimen !== f.regimen) return false;
            if (f.nivel === 'activos' && nivel === 'cerrado') return false;
            if (f.nivel && f.nivel !== 'activos' && nivel !== f.nivel) return false;
            return true;
        });
    }

    function ordenar(lista) {
        return [...lista].sort((a, b) => {
            const na = ORDEN_NIVEL[a.semaforo.nivel] ?? 9;
            const nb = ORDEN_NIVEL[b.semaforo.nivel] ?? 9;
            if (na !== nb) return na - nb;
            if (a.semaforo.nivel === 'cerrado') return String(b.fecha_apertura).localeCompare(String(a.fecha_apertura));
            const ra = a.semaforo.dias_restantes ?? Infinity;
            const rb = b.semaforo.dias_restantes ?? Infinity;
            if (ra !== rb) return ra - rb;
            return b.semaforo.dias_transcurridos - a.semaforo.dias_transcurridos;
        });
    }

    function textoRestante(s) {
        if (s.nivel === 'cerrado') return { texto: 'Sin alerta DIAN', clase: 'text-slate-400' };
        if (s.dias_restantes === null || s.dias_restantes === undefined) return { texto: 'Sin fecha de vencimiento', clase: 'text-slate-400' };
        if (s.dias_restantes < 0) return { texto: `Vencido hace ${plural(-s.dias_restantes, 'día', 'días')}`, clase: 'font-semibold text-red-600' };
        if (s.dias_restantes === 0) return { texto: 'Vence hoy', clase: 'font-semibold text-red-600' };
        const clase = s.dias_restantes <= 15 ? 'font-semibold text-red-600' : 'text-slate-500';
        return { texto: `Vence en ${plural(s.dias_restantes, 'día', 'días')}`, clase };
    }

    function badgeEstado(valor) {
        const clases = ESTADO_UI[valor] || 'bg-slate-100 text-slate-700 ring-slate-500/20';
        return `<span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset ${clases}">${esc(valor)}</span>`;
    }

    function badgeSemaforo(s) {
        const ui = NIVEL_UI[s.nivel] || NIVEL_UI.al_dia;
        return `<span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset ${ui.badge}" title="${esc(s.descripcion)}">
                    <span class="h-2 w-2 rounded-full ${ui.punto}"></span>${esc(s.etiqueta)}</span>`;
    }

    function filaHTML(d) {
        const s = d.semaforo;
        const ui = NIVEL_UI[s.nivel] || NIVEL_UI.al_dia;
        const transcurridos = s.dias_transcurridos < 0
            ? `Inicia en ${plural(-s.dias_transcurridos, 'día', 'días')}`
            : plural(s.dias_transcurridos, 'día', 'días');
        const restante = textoRestante(s);
        const progreso = Math.max(0, Math.min(100, Number(s.progreso) || 0));
        const resaltado = d.id === estado.resaltar;
        const fondoFila = resaltado ? 'bg-amber-50' : '';
        const fondoFijo = resaltado ? 'bg-amber-50' : 'bg-white group-hover:bg-slate-50';
        const obs = d.ultima_observacion
            ? `<div class="mt-1 max-w-[210px] truncate text-xs text-slate-500" title="${esc(d.ultima_observacion)}">${esc(d.ultima_observacion)}</div>`
            : '';

        return `<tr data-id="${d.id}" class="group align-top transition-colors hover:bg-slate-50 ${fondoFila}">
            <td class="whitespace-nowrap py-3 pl-5 pr-3">
                <div class="font-bold tracking-wide text-rubi-navy">${esc(d.do_codigo)}</div>
                <div class="mt-0.5 text-xs text-slate-500">${esc(d.regimen)} · ${esc(d.sucursal)}</div>
            </td>
            <td class="px-3 py-3">
                <div class="max-w-[200px] truncate font-medium text-slate-800" title="${esc(d.cliente)}">${esc(d.cliente)}</div>
                <div class="mt-0.5 text-xs text-slate-500">NIT ${esc(d.nit)}</div>
            </td>
            <td class="px-3 py-3">${badgeEstado(d.estado)}${obs}</td>
            <td class="whitespace-nowrap px-3 py-3 text-xs">
                <div><span class="inline-block w-14 text-slate-400">Apertura</span><span class="font-medium text-slate-700">${fechaCO(d.fecha_apertura)}</span></div>
                <div class="mt-1"><span class="inline-block w-14 text-slate-400">Vence</span><span class="font-medium text-slate-700">${fechaCO(d.fecha_vencimiento)}</span></div>
            </td>
            <td class="whitespace-nowrap px-3 py-3">
                <div class="flex items-center gap-2">${badgeSemaforo(s)}<span class="text-sm font-semibold text-slate-800">${transcurridos}</span></div>
                <div class="mt-2 h-1.5 w-36 overflow-hidden rounded-full bg-slate-200" title="${progreso}% del plazo consumido"><div class="h-full rounded-full ${ui.barra}" style="width:${progreso}%"></div></div>
                <div class="mt-1 text-xs ${restante.clase}">${restante.texto}</div>
            </td>
            <td class="sticky right-0 whitespace-nowrap border-l border-slate-100 px-3 py-3 ${fondoFijo}">
                <div class="flex w-28 flex-col gap-1.5">
                    <button type="button" data-accion="estado" data-id="${d.id}" class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-rubi-navy transition hover:border-rubi-navy/40 hover:bg-slate-50">
                        ${icono('refresh', 'h-3.5 w-3.5')} ${APP.puedeEditar ? 'Estado' : 'Bitácora'}
                    </button>
                    <button type="button" data-accion="legajo" data-id="${d.id}" class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1.5 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100">
                        ${icono('paperclip', 'h-3.5 w-3.5')} Legajo
                        <span class="rounded-full bg-white px-1.5 text-[10px] font-bold text-emerald-700 ring-1 ring-emerald-200">${Number(d.total_documentos) || 0}</span>
                    </button>
                </div>
            </td>
        </tr>`;
    }

    function renderKpis() {
        const conteo = { activos: 0, al_dia: 0, seguimiento: 0, critico: 0, cerrado: 0 };
        estado.dos.forEach((d) => {
            const n = d.semaforo.nivel;
            conteo[n] = (conteo[n] || 0) + 1;
            if (n !== 'cerrado') conteo.activos += 1;
        });
        $$('[data-kpi]').forEach((tarjeta) => {
            const clave = tarjeta.dataset.kpi;
            tarjeta.querySelector('[data-kpi-num]').textContent = conteo[clave] ?? 0;
            const activo = estado.filtros.nivel === clave;
            tarjeta.classList.toggle('ring-2', activo);
            tarjeta.classList.toggle('ring-rubi-emerald', activo);
            tarjeta.setAttribute('aria-pressed', activo ? 'true' : 'false');
        });
    }

    function renderNav() {
        $$('[data-nav-regimen]').forEach((enlace) => {
            const regimen = enlace.dataset.navRegimen;
            const activo = regimen === ''
                ? !['Exportación', 'Importación'].includes(estado.filtros.regimen)
                : estado.filtros.regimen === regimen;
            enlace.classList.toggle('bg-rubi-emerald', activo);
            enlace.classList.toggle('text-white', activo);
            enlace.classList.toggle('text-slate-300', !activo);
            enlace.classList.toggle('hover:bg-white/5', !activo);
            enlace.classList.toggle('hover:text-white', !activo);
            if (activo) enlace.setAttribute('aria-current', 'page'); else enlace.removeAttribute('aria-current');
        });
    }

    function renderTabla() {
        const visibles = ordenar(filtrar(estado.dos));
        $('#tbl-body').innerHTML = visibles.map(filaHTML).join('');
        $('#txt-conteo').textContent = `Mostrando ${visibles.length} de ${plural(estado.dos.length, 'operación', 'operaciones')}`;

        const vacio = $('#estado-vacio');
        const hayFilas = visibles.length > 0;
        vacio.classList.toggle('hidden', hayFilas);
        vacio.classList.toggle('flex', !hayFilas);
        $('#estado-vacio-texto').textContent = estado.dos.length === 0
            ? 'Aún no hay órdenes de operación registradas.'
            : 'Ninguna operación coincide con los filtros aplicados.';
    }

    function render() {
        renderKpis();
        renderNav();
        renderTabla();
    }

    function sincronizarControles() {
        $('#f-texto').value = estado.filtros.texto;
        $('#f-sucursal').value = estado.filtros.sucursal;
        $('#f-regimen').value = estado.filtros.regimen;
        $('#f-nivel').value = estado.filtros.nivel;
    }

    function limpiarFiltros() {
        estado.filtros = { texto: '', sucursal: '', regimen: '', nivel: '' };
        sincronizarControles();
        render();
    }

    function mostrarAlertaCarga(mensaje) {
        const caja = $('#alerta-carga');
        $('#alerta-carga-texto').textContent = mensaje || '';
        caja.classList.toggle('hidden', !mensaje);
        caja.classList.toggle('flex', Boolean(mensaje));
    }

    async function cargarDatos({ silencioso = false } = {}) {
        const boton = $('#btn-recargar');
        boton.classList.add('animate-spin');
        try {
            const r = await fetchJSON(`${URL_PANEL}?action=listar`);
            estado.dos = Array.isArray(r.data?.dos) ? r.data.dos : [];
            APP.hoy = r.data?.hoy || APP.hoy;
            $('#txt-actualizado').textContent = r.data?.generado || '';
            mostrarAlertaCarga('');
            render();
        } catch (err) {
            if (!silencioso) toast(err.message, 'error');
        } finally {
            boton.classList.remove('animate-spin');
        }
    }

    function resaltarFila(id) {
        estado.resaltar = id;
        render();
        const fila = $(`#tbl-body tr[data-id="${id}"]`);
        if (fila) fila.scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(() => { if (estado.resaltar === id) { estado.resaltar = null; render(); } }, 4000);
    }

    // ------------------------------------------------------------------
    // Modales (accesibles: Esc, clic fuera, foco atrapado)
    // ------------------------------------------------------------------
    let modalAbierto = null;
    let focoPrevio = null;

    function abrirModal(id) {
        const modal = document.getElementById(id);
        if (!modal) return;
        if (modalAbierto && modalAbierto !== modal) cerrarModal(true);
        focoPrevio = document.activeElement;
        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
        modalAbierto = modal;
        setTimeout(() => {
            const foco = modal.querySelector('[data-autofoco]') || modal.querySelector('button, input, select, textarea');
            if (foco) foco.focus();
        }, 40);
    }

    function cerrarModal(forzar = false) {
        if (!modalAbierto) return;
        if (!forzar && modalAbierto.dataset.ocupado === '1') {
            toast('Espere a que termine la operación en curso.', 'info', 2500);
            return;
        }
        modalAbierto.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
        modalAbierto = null;
        estado.doActual = null;
        if (focoPrevio && typeof focoPrevio.focus === 'function') focoPrevio.focus();
    }

    function atraparFoco(e) {
        const enfocables = $$('a[href], button:not([disabled]), input:not([disabled]):not(.sr-only), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])', modalAbierto)
            .filter((el) => el.offsetParent !== null);
        if (enfocables.length === 0) return;
        const primero = enfocables[0];
        const ultimo = enfocables[enfocables.length - 1];
        if (e.shiftKey && document.activeElement === primero) { e.preventDefault(); ultimo.focus(); }
        else if (!e.shiftKey && document.activeElement === ultimo) { e.preventDefault(); primero.focus(); }
    }

    document.addEventListener('keydown', (e) => {
        if (!modalAbierto) return;
        if (e.key === 'Escape') cerrarModal();
        if (e.key === 'Tab') atraparFoco(e);
    });
    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-cerrar-modal]')) cerrarModal();
        else if (e.target.hasAttribute && e.target.hasAttribute('data-fondo-modal')) cerrarModal();
    });

    function limpiarErrorCampo(form, nombre) {
        const campo = form.elements[nombre];
        const p = $(`[data-error-para="${nombre}"]`, form);
        const pista = $(`[data-pista-para="${nombre}"]`, form);
        if (campo && campo.classList) {
            campo.classList.remove(...CLASES_ERR);
            campo.classList.add(...CLASES_OK);
            campo.removeAttribute('aria-invalid');
        }
        if (p) { p.textContent = ''; p.classList.add('hidden'); }
        if (pista) pista.classList.remove('hidden');
    }

    function limpiarErrores(form) {
        $$('[data-error-para]', form).forEach((p) => limpiarErrorCampo(form, p.dataset.errorPara));
    }

    function mostrarErrores(form, errores) {
        let primero = null;
        Object.entries(errores || {}).forEach(([campo, mensaje]) => {
            const input = form.elements[campo];
            const p = $(`[data-error-para="${campo}"]`, form);
            if (input) {
                input.classList.remove(...CLASES_OK);
                input.classList.add(...CLASES_ERR);
                input.setAttribute('aria-invalid', 'true');
                primero = primero || input;
            }
            if (p) { p.textContent = mensaje; p.classList.remove('hidden'); }
            const pista = $(`[data-pista-para="${campo}"]`, form);
            if (pista) pista.classList.add('hidden');
        });
        if (primero) primero.focus();
    }

    // ------------------------------------------------------------------
    // Modal: Nueva operación (DO)
    // ------------------------------------------------------------------
    const formNuevo = $('#form-nuevo');
    let prefijoSugerido = '';
    let vencimientoManual = false;

    function calcularPrefijo() {
        const suc = APP.prefijosSucursal[formNuevo.sucursal.value] || '';
        const reg = APP.prefijosRegimen[formNuevo.regimen.value] || '';
        const anio = (formNuevo.fecha_apertura.value || APP.hoy).slice(2, 4);
        return suc && reg ? `${suc}${anio}${reg}` : '';
    }

    function actualizarPrefijo() {
        const nuevo = calcularPrefijo();
        const campo = formNuevo.do_codigo;
        const actual = campo.value.trim().toUpperCase();
        if (actual === '' || (prefijoSugerido && actual.startsWith(prefijoSugerido))) {
            const consecutivo = prefijoSugerido && actual.startsWith(prefijoSugerido) ? actual.slice(prefijoSugerido.length) : '';
            campo.value = nuevo + consecutivo;
        }
        prefijoSugerido = nuevo;
        $('#hint-codigo').textContent = `Formato: ${nuevo || 'BOG26IMP'} + consecutivo, ej. ${nuevo || 'BOG26IMP'}0001`;
    }

    function actualizarPistaNit() {
        const pista = $('#hint-nit');
        const valor = formNuevo.nit.value.replace(/[\s.]/g, '');
        const m = valor.match(/^(\d{5,15})(?:-(\d)?)?$/);
        pista.className = 'mt-1 text-xs text-slate-500';
        if (!valor) { pista.textContent = 'El dígito de verificación se calcula automáticamente.'; return; }
        if (!m) { pista.textContent = 'Use solo números y, si lo tiene, el guion del DV (ej. 900123456-8).'; return; }
        const dv = dvNit(m[1]);
        if (m[2] !== undefined && m[2] !== '' && Number(m[2]) !== dv) {
            pista.className = 'mt-1 text-xs font-semibold text-red-600';
            pista.textContent = `El DV no corresponde: para ${m[1]} es ${dv}.`;
        } else {
            pista.className = 'mt-1 text-xs text-emerald-700';
            pista.textContent = `Se guardará como ${m[1]}-${dv}.`;
        }
    }

    function abrirNuevo() {
        formNuevo.reset();
        limpiarErrores(formNuevo);
        formNuevo.sucursal.value = APP.sucursales.includes(APP.sucursalUsuario) ? APP.sucursalUsuario : APP.sucursales[0];
        formNuevo.regimen.value = APP.regimenes[0];
        formNuevo.fecha_apertura.value = APP.hoy;
        formNuevo.fecha_apertura.max = APP.hoy;
        formNuevo.fecha_vencimiento.value = sumarDias(APP.hoy, APP.plazoDias);
        formNuevo.fecha_vencimiento.min = APP.hoy;
        vencimientoManual = false;
        prefijoSugerido = '';
        actualizarPrefijo();
        actualizarPistaNit();
        abrirModal('modal-nuevo');
    }

    if (formNuevo) {
        $('#btn-nuevo-do').addEventListener('click', abrirNuevo);
        formNuevo.sucursal.addEventListener('change', actualizarPrefijo);
        formNuevo.regimen.addEventListener('change', actualizarPrefijo);
        formNuevo.do_codigo.addEventListener('input', () => {
            const pos = formNuevo.do_codigo.selectionStart;
            formNuevo.do_codigo.value = formNuevo.do_codigo.value.toUpperCase().replace(/\s+/g, '');
            formNuevo.do_codigo.setSelectionRange(pos, pos);
        });
        formNuevo.nit.addEventListener('input', actualizarPistaNit);
        formNuevo.fecha_apertura.addEventListener('change', () => {
            const apertura = formNuevo.fecha_apertura.value;
            if (apertura) {
                formNuevo.fecha_vencimiento.min = apertura;
                if (!vencimientoManual) formNuevo.fecha_vencimiento.value = sumarDias(apertura, APP.plazoDias);
            }
            actualizarPrefijo();
        });
        formNuevo.fecha_vencimiento.addEventListener('change', () => { vencimientoManual = true; });
        // Al corregir un campo, su mensaje de error desaparece.
        ['input', 'change'].forEach((ev) => formNuevo.addEventListener(ev, (e) => {
            if (e.target.name && e.target.getAttribute('aria-invalid') === 'true') limpiarErrorCampo(formNuevo, e.target.name);
        }));

        formNuevo.addEventListener('submit', async (e) => {
            e.preventDefault();
            limpiarErrores(formNuevo);
            const datos = Object.fromEntries(new FormData(formNuevo).entries());
            datos.do_codigo = String(datos.do_codigo || '').trim().toUpperCase();

            const faltantes = {};
            if (!datos.do_codigo) faltantes.do_codigo = 'El código del DO es obligatorio.';
            if (!String(datos.cliente || '').trim()) faltantes.cliente = 'El cliente es obligatorio.';
            if (!String(datos.nit || '').trim()) faltantes.nit = 'El NIT es obligatorio.';
            if (!datos.fecha_apertura) faltantes.fecha_apertura = 'Indique la fecha de apertura.';
            if (!datos.fecha_vencimiento) faltantes.fecha_vencimiento = 'Indique la fecha de vencimiento.';
            if (Object.keys(faltantes).length) { mostrarErrores(formNuevo, faltantes); return; }

            const boton = formNuevo.querySelector('button[type="submit"]');
            const modal = $('#modal-nuevo');
            botonOcupado(boton, true, 'Guardando…');
            modal.dataset.ocupado = '1';
            try {
                const r = await fetchJSON(`${URL_PANEL}?action=crear`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(datos),
                });
                modal.dataset.ocupado = '0';
                cerrarModal();
                toast(r.message, 'success');
                if (r.data?.do) {
                    estado.dos = [r.data.do, ...estado.dos.filter((d) => d.id !== r.data.do.id)];
                    limpiarFiltros();
                    resaltarFila(r.data.do.id);
                }
                cargarDatos({ silencioso: true });
            } catch (err) {
                if (err.data?.errors) mostrarErrores(formNuevo, err.data.errors);
                toast(err.message, 'error', 7000);
            } finally {
                modal.dataset.ocupado = '0';
                botonOcupado(boton, false);
            }
        });
    }

    // ------------------------------------------------------------------
    // Modal: Actualizar estado / Observar
    // ------------------------------------------------------------------
    const formEstado = $('#form-estado');

    function renderBitacora(entradas) {
        const lista = $('#estado-bitacora');
        if (!entradas || entradas.length === 0) {
            lista.innerHTML = '<li class="text-sm text-slate-400">Sin observaciones registradas.</li>';
            return;
        }
        lista.innerHTML = entradas.map((en) => `
            <li class="relative border-l-2 border-slate-200 pl-4">
                <span class="absolute -left-[5px] top-1.5 h-2 w-2 rounded-full bg-rubi-emerald"></span>
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] text-slate-500">
                    ${en.fecha ? `<span class="font-semibold text-slate-600">${esc(en.fecha)}</span>` : '<span class="italic">Registro anterior</span>'}
                    ${en.usuario ? `<span>· ${esc(en.usuario)}</span>` : ''}
                    ${en.estado ? `<span class="rounded bg-slate-100 px-1.5 py-0.5 font-semibold text-slate-600">${esc(en.estado)}</span>` : ''}
                </div>
                <p class="mt-0.5 text-sm text-slate-700">${esc(en.texto)}</p>
            </li>`).join('');
    }

    function actualizarContador() {
        if (!formEstado) return;
        $('#e-contador').textContent = `${formEstado.observaciones.value.length} / 1000`;
    }

    function abrirEstado(id) {
        const d = estado.dos.find((x) => x.id === id);
        if (!d) return;
        abrirModal('modal-estado');
        estado.doActual = d;
        $('#estado-codigo').textContent = d.do_codigo;
        $('#estado-cliente').textContent = `${d.cliente} · NIT ${d.nit} · ${d.regimen} · ${d.sucursal}`;
        const restante = textoRestante(d.semaforo);
        $('#estado-resumen').innerHTML = `
            <span class="text-slate-500">Estado actual:</span> ${badgeEstado(d.estado)}
            <span class="ml-2 text-slate-500">Alerta:</span> ${badgeSemaforo(d.semaforo)}
            <span class="ml-2 text-slate-500">${plural(d.semaforo.dias_transcurridos, 'día', 'días')} desde la apertura · <span class="${restante.clase}">${restante.texto}</span></span>`;
        if (formEstado) {
            formEstado.reset();
            formEstado.nuevo_estado.value = APP.estados.includes(d.estado) ? d.estado : APP.estados[0];
            actualizarContador();
        }
        renderBitacora(d.bitacora);
    }

    if (formEstado) {
        formEstado.observaciones.addEventListener('input', actualizarContador);
        $$('[data-obs-rapida]', formEstado).forEach((chip) => {
            chip.addEventListener('click', () => {
                const area = formEstado.observaciones;
                const actual = area.value.trim();
                area.value = actual ? `${actual.replace(/[.\s]+$/, '')}. ${chip.dataset.obsRapida}` : chip.dataset.obsRapida;
                area.focus();
                actualizarContador();
            });
        });

        formEstado.addEventListener('submit', async (e) => {
            e.preventDefault();
            const d = estado.doActual;
            if (!d) return;

            const payload = {
                do_id: Number(d.id),
                do_codigo: d.do_codigo,
                nuevo_estado: formEstado.nuevo_estado.value,
                observaciones: formEstado.observaciones.value.trim(),
                usuario: APP.usuario,
            };
            if (payload.nuevo_estado === d.estado && payload.observaciones === '') {
                toast('No hay cambios: elija otro estado o escriba una observación.', 'warning');
                formEstado.observaciones.focus();
                return;
            }

            const boton = formEstado.querySelector('button[type="submit"]');
            const modal = $('#modal-estado');
            botonOcupado(boton, true, 'Guardando y notificando…');
            modal.dataset.ocupado = '1';
            try {
                const r = await fetchJSON(URL_WEBHOOK, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                modal.dataset.ocupado = '0';
                cerrarModal();
                const fallaN8n = r.webhook && r.webhook.enviado === false;
                toast(r.message, fallaN8n ? 'warning' : 'success', fallaN8n ? 10000 : 5000);
                await cargarDatos({ silencioso: true });
                resaltarFila(d.id);
            } catch (err) {
                toast(err.message, 'error', 7000);
            } finally {
                modal.dataset.ocupado = '0';
                botonOcupado(boton, false);
            }
        });
    }

    // ------------------------------------------------------------------
    // Modal: Documentos / Legajo aduanero
    // ------------------------------------------------------------------
    const formLegajo = $('#form-legajo');
    const inputArchivo = $('#l-archivo');

    function claseExtension(ext) {
        if (ext === 'pdf') return 'bg-red-50 text-red-600';
        if (ext === 'docx') return 'bg-sky-50 text-sky-600';
        return 'bg-emerald-50 text-emerald-600';
    }

    function renderChecklist(documentos) {
        const presentes = new Set((documentos || []).map((doc) => doc.tipo_documento));
        $('#legajo-checklist').innerHTML = APP.tiposDocumento
            .filter((t) => t !== 'Otro')
            .map((t) => presentes.has(t)
                ? `<span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">${icono('check', 'h-3.5 w-3.5')} ${esc(t)}</span>`
                : `<span class="inline-flex items-center gap-1 rounded-full bg-white px-2.5 py-1 text-xs font-medium text-slate-500 ring-1 ring-inset ring-slate-200">${esc(t)}</span>`)
            .join('');
    }

    function renderDocumentos(documentos) {
        renderChecklist(documentos);
        const lista = $('#legajo-lista');
        if (!documentos || documentos.length === 0) {
            lista.innerHTML = '<li class="py-6 text-center text-sm text-slate-400">Este DO aún no tiene documentos adjuntos.</li>';
            return;
        }
        lista.innerHTML = documentos.map((doc) => {
            const acciones = doc.existe
                ? `${doc.visualizable ? `<a href="${esc(doc.url_ver)}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-50">${icono('eye', 'h-3.5 w-3.5')} Ver</a>` : ''}
                   <a href="${esc(doc.url_descarga)}" class="inline-flex items-center gap-1 rounded-lg bg-rubi-navy px-2 py-1 text-xs font-semibold text-white hover:bg-rubi-navy2">${icono('download', 'h-3.5 w-3.5')} Descargar</a>`
                : '<span class="text-xs font-semibold text-red-600">Archivo no disponible</span>';
            return `<li class="flex items-center gap-3 py-3">
                <span class="flex h-10 w-10 shrink-0 flex-col items-center justify-center rounded-lg ${claseExtension(doc.extension)}">
                    ${icono('file', 'h-4 w-4')}<span class="text-[9px] font-bold uppercase">${esc(doc.extension)}</span>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-slate-800" title="${esc(doc.nombre_archivo)}">${esc(doc.nombre_archivo)}</p>
                    <p class="text-xs text-slate-500"><span class="font-semibold text-slate-600">${esc(doc.tipo_documento)}</span> · ${tamanoLegible(doc.tamano)} · ${fechaHoraCO(doc.fecha_subida)}</p>
                </div>
                <div class="flex shrink-0 items-center gap-1.5">${acciones}</div>
            </li>`;
        }).join('');
    }

    async function cargarDocumentos(doId) {
        const lista = $('#legajo-lista');
        lista.innerHTML = '<li class="py-6 text-center text-sm text-slate-400">Cargando documentos…</li>';
        try {
            const r = await fetchJSON(`${URL_PANEL}?action=documentos&do_id=${encodeURIComponent(doId)}`);
            if (estado.doActual && estado.doActual.id === doId) renderDocumentos(r.data?.documentos || []);
        } catch (err) {
            lista.innerHTML = `<li class="py-6 text-center text-sm text-red-600">${esc(err.message)}</li>`;
        }
    }

    function reiniciarSubida() {
        if (!formLegajo) return;
        formLegajo.reset();
        $('#l-archivo-nombre').textContent = 'Seleccione o arrastre un archivo';
        $('#l-progreso').classList.add('hidden');
        $('#l-progreso-barra').style.width = '0%';
    }

    function abrirLegajo(id) {
        const d = estado.dos.find((x) => x.id === id);
        if (!d) return;
        abrirModal('modal-legajo');
        estado.doActual = d;
        $('#legajo-codigo').textContent = d.do_codigo;
        $('#legajo-cliente').textContent = `${d.cliente} · ${d.regimen} · ${d.sucursal}`;
        $('#legajo-checklist').innerHTML = '';
        reiniciarSubida();
        cargarDocumentos(d.id);
    }

    function validarArchivo(archivo) {
        if (!archivo) return 'Seleccione un archivo.';
        const ext = archivo.name.split('.').pop().toLowerCase();
        if (!EXT_PERMITIDAS.includes(ext)) return 'Formato no permitido. Solo PDF, JPG, PNG o DOCX.';
        if (archivo.size === 0) return 'El archivo está vacío.';
        if (archivo.size > APP.maxBytes) return `El archivo pesa ${tamanoLegible(archivo.size)}; el máximo es 10 MB.`;
        return null;
    }

    if (formLegajo) {
        const zona = $('#l-zona');
        const mostrarNombre = () => {
            const archivo = inputArchivo.files[0];
            $('#l-archivo-nombre').textContent = archivo ? `${archivo.name} (${tamanoLegible(archivo.size)})` : 'Seleccione o arrastre un archivo';
        };
        inputArchivo.addEventListener('change', mostrarNombre);
        ['dragenter', 'dragover'].forEach((ev) => zona.addEventListener(ev, (e) => {
            e.preventDefault();
            zona.classList.add('border-emerald-500', 'bg-emerald-50');
        }));
        ['dragleave', 'drop'].forEach((ev) => zona.addEventListener(ev, (e) => {
            e.preventDefault();
            zona.classList.remove('border-emerald-500', 'bg-emerald-50');
        }));
        zona.addEventListener('drop', (e) => {
            if (e.dataTransfer && e.dataTransfer.files.length) {
                inputArchivo.files = e.dataTransfer.files;
                mostrarNombre();
            }
        });

        formLegajo.addEventListener('submit', async (e) => {
            e.preventDefault();
            const d = estado.doActual;
            if (!d) return;
            const archivo = inputArchivo.files[0];
            const error = validarArchivo(archivo);
            if (error) { toast(error, 'error'); return; }

            const datos = new FormData();
            datos.append('do_id', String(d.id));
            datos.append('tipo_documento', formLegajo.tipo_documento.value);
            datos.append('documento', archivo);

            const boton = formLegajo.querySelector('button[type="submit"]');
            const modal = $('#modal-legajo');
            const barra = $('#l-progreso-barra');
            const textoProgreso = $('#l-progreso-texto');
            $('#l-progreso').classList.remove('hidden');
            botonOcupado(boton, true, 'Subiendo…');
            modal.dataset.ocupado = '1';
            try {
                const r = await subirConProgreso(URL_UPLOAD, datos, (p) => {
                    barra.style.width = `${p}%`;
                    textoProgreso.textContent = p < 100 ? `${p} %` : 'Validando archivo…';
                });
                toast(r.message, 'success');
                reiniciarSubida();
                await cargarDocumentos(d.id);
                cargarDatos({ silencioso: true });
            } catch (err) {
                toast(err.message, 'error', 7000);
                $('#l-progreso').classList.add('hidden');
            } finally {
                modal.dataset.ocupado = '0';
                botonOcupado(boton, false);
            }
        });
    }

    // ------------------------------------------------------------------
    // Eventos generales
    // ------------------------------------------------------------------
    $('#tbl-body').addEventListener('click', (e) => {
        const boton = e.target.closest('button[data-accion]');
        if (!boton) return;
        const id = Number(boton.dataset.id);
        if (boton.dataset.accion === 'estado') abrirEstado(id);
        if (boton.dataset.accion === 'legajo') abrirLegajo(id);
    });

    let temporizadorBusqueda;
    $('#f-texto').addEventListener('input', (e) => {
        clearTimeout(temporizadorBusqueda);
        temporizadorBusqueda = setTimeout(() => { estado.filtros.texto = e.target.value; render(); }, 150);
    });
    $('#f-sucursal').addEventListener('change', (e) => { estado.filtros.sucursal = e.target.value; render(); });
    $('#f-regimen').addEventListener('change', (e) => { estado.filtros.regimen = e.target.value; render(); });
    $('#f-nivel').addEventListener('change', (e) => { estado.filtros.nivel = e.target.value; render(); });
    $('#f-limpiar').addEventListener('click', limpiarFiltros);
    $('#btn-recargar').addEventListener('click', () => cargarDatos());

    // Exportación (MR-010): descarga lo que el servidor filtre con sucursal, régimen y nivel actuales.
    function exportar(formato) {
        const parametros = new URLSearchParams({ action: 'exportar', formato });
        ['sucursal', 'regimen', 'nivel'].forEach((clave) => {
            if (estado.filtros[clave]) parametros.set(clave, estado.filtros[clave]);
        });
        window.location.href = `${URL_PANEL}?${parametros.toString()}`;
    }
    $('#btn-exportar-csv').addEventListener('click', () => exportar('csv'));
    $('#btn-exportar-json').addEventListener('click', () => exportar('json'));

    $$('[data-kpi]').forEach((tarjeta) => tarjeta.addEventListener('click', () => {
        const clave = tarjeta.dataset.kpi;
        estado.filtros.nivel = estado.filtros.nivel === clave ? '' : clave;
        sincronizarControles();
        render();
    }));

    // Menú lateral
    const sidebar = $('#sidebar');
    const fondoSidebar = $('#sidebar-fondo');
    const alternarMenu = (abrir) => {
        sidebar.classList.toggle('-translate-x-full', !abrir);
        fondoSidebar.classList.toggle('hidden', !abrir);
        $('#btn-menu').setAttribute('aria-expanded', abrir ? 'true' : 'false');
    };
    $('#btn-menu').addEventListener('click', () => alternarMenu(sidebar.classList.contains('-translate-x-full')));
    fondoSidebar.addEventListener('click', () => alternarMenu(false));
    $$('[data-nav-regimen]').forEach((enlace) => enlace.addEventListener('click', (e) => {
        e.preventDefault();
        estado.filtros.regimen = enlace.dataset.navRegimen;
        sincronizarControles();
        render();
        alternarMenu(false);
    }));

    // Refresco automático cada 5 minutos (mantiene el semáforo al día).
    setInterval(() => {
        if (!document.hidden && !modalAbierto) cargarDatos({ silencioso: true });
    }, 5 * 60 * 1000);

    // ------------------------------------------------------------------
    // Inicio
    // ------------------------------------------------------------------
    if (APP.errorCarga) mostrarAlertaCarga(APP.errorCarga);
    $('#txt-actualizado').textContent = APP.generado || '';
    render();
})();
