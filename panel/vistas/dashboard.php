<?php

/**
 * panel/vistas/dashboard.php
 * -----------------------------------------------------------------------
 * Plantilla HTML del panel operativo (solo presentación: no consulta la base
 * de datos ni procesa formularios). Recibe $pagina desde panel/dashboard.php.
 * El JavaScript vive en assets/js/panel.js.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

use Rubicam\Dominio\Catalogos;
use Rubicam\Dominio\SemaforoDo;
use Rubicam\Seguridad\SesionPanel;

require_once dirname(__DIR__, 2) . '/src/Vistas/ayudantes.php';

/** @var array{puedeEditar: bool, sucursalUsuario: string, sucursalesFiltro: list<string>, configJs: array<string, mixed>} $pagina */
$puedeEditar      = $pagina['puedeEditar'];
$sucursalUsuario  = $pagina['sucursalUsuario'];
$sucursalesFiltro = $pagina['sucursalesFiltro'];
$configJs         = $pagina['configJs'];

$claseInput = 'block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm placeholder:text-slate-400 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/20 disabled:bg-slate-50';
$claseLabel = 'mb-1 block text-xs font-bold uppercase tracking-wide text-slate-600';
$claseNavActivo = 'bg-rubi-emerald text-white';
$claseNav = 'flex items-center gap-3 rounded-lg px-3 py-2.5 transition-colors';
?>
<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Órdenes de Operación · SIA RUBICAM</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>svg{width:1.25em;height:1.25em;display:inline-block;flex-shrink:0}</style>
<link rel="stylesheet" href="../assets/css/tailwind.build.css">
<link rel="icon" href="data:,">
</head>
<body class="h-full bg-slate-100 font-sans text-slate-800 antialiased">

<noscript>
    <div class="bg-red-600 px-4 py-3 text-center text-sm font-semibold text-white">El panel necesita JavaScript habilitado para funcionar.</div>
</noscript>

<!-- ============================ SIDEBAR ============================ -->
<div id="sidebar-fondo" class="fixed inset-0 z-30 hidden bg-slate-900/50 lg:hidden"></div>
<aside id="sidebar" class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col bg-gradient-to-b from-rubi-navy to-[#061f2e] text-slate-300 transition-transform duration-200 lg:translate-x-0" aria-label="Menú lateral">
    <div class="flex items-center gap-3 border-b border-white/10 px-5 py-5">
        <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-white/10 text-lg font-extrabold text-rubi-orange ring-1 ring-white/15">R</span>
        <div>
            <div class="text-base font-extrabold tracking-wide text-white">RUBICAM</div>
            <div class="text-[11px] font-bold uppercase tracking-widest text-rubi-orange">SIA · Nivel 2</div>
        </div>
    </div>

    <nav class="flex-1 space-y-1 px-3 py-4 text-sm font-medium" aria-label="Menú principal">
        <span class="<?= $claseNav ?> cursor-not-allowed text-slate-500" title="Próximamente" aria-disabled="true"><?= icono('grid') ?> Maestras</span>
        <a href="#" data-nav-regimen="" class="<?= $claseNav ?> <?= $claseNavActivo ?>"><?= icono('flow') ?> Operación</a>
        <a href="#" data-nav-regimen="Exportación" class="<?= $claseNav ?> text-slate-300 hover:bg-white/5 hover:text-white"><?= icono('export') ?> Exportaciones</a>
        <a href="#" data-nav-regimen="Importación" class="<?= $claseNav ?> text-slate-300 hover:bg-white/5 hover:text-white"><?= icono('import') ?> Importaciones</a>
        <span class="<?= $claseNav ?> cursor-not-allowed text-slate-500" title="Próximamente" aria-disabled="true"><?= icono('chart') ?> Reportes DO</span>
    </nav>

    <div class="border-t border-white/10 p-4">
        <div class="mb-3 flex items-center gap-3">
            <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-white/10 text-slate-200"><?= icono('user', 'h-5 w-5') ?></span>
            <div class="min-w-0">
                <div class="truncate text-sm font-semibold text-white"><?= h(SesionPanel::nombreUsuario()) ?></div>
                <div class="text-xs capitalize text-slate-400"><?= h(SesionPanel::rol()) ?></div>
            </div>
        </div>
        <a href="logout.php" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-red-300 transition-colors hover:bg-red-500/10 hover:text-red-200"><?= icono('logout') ?> Cerrar sesión</a>
    </div>
</aside>

<!-- ============================ CONTENIDO ============================ -->
<div class="lg:pl-64">
    <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur">
        <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6 lg:px-8">
            <div class="flex items-center gap-3">
                <button type="button" id="btn-menu" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 lg:hidden" aria-label="Abrir menú" aria-controls="sidebar" aria-expanded="false"><?= icono('menu', 'h-5 w-5') ?></button>
                <nav aria-label="Ruta" class="flex items-center gap-1.5 text-sm text-slate-500">
                    Operación <?= icono('chevron', 'h-3.5 w-3.5 text-slate-400') ?> <strong class="font-semibold text-rubi-navy">Órdenes de Operación (DO)</strong>
                </nav>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <?php if ($sucursalUsuario !== '') : ?>
                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-rubi-emerald"><?= icono('pin', 'h-3.5 w-3.5') ?> <?= h($sucursalUsuario) ?></span>
                <?php endif; ?>
                <span class="rounded-full bg-orange-50 px-3 py-1 text-xs font-bold text-orange-600">Año operativo <?= date('Y') ?></span>
                <?php if ($puedeEditar) : ?>
                <button type="button" id="btn-nuevo-do" class="inline-flex items-center gap-1.5 rounded-lg bg-rubi-orange px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-orange-600 focus:outline-none focus:ring-2 focus:ring-orange-500/40">
                    <?= icono('plus') ?> Nueva operación (DO)
                </button>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="px-4 py-6 sm:px-6 lg:px-8">
        <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight text-rubi-navy">Órdenes de Operación</h1>
                <p class="mt-1 text-sm text-slate-500">Seguimiento de DOs y semáforo DIAN: plazo de referencia de <?= SemaforoDo::PLAZO_DIAN_DIAS ?> días desde la apertura.</p>
            </div>
            <div class="flex items-center gap-2 text-xs text-slate-500">
                Actualizado: <span id="txt-actualizado" class="font-semibold text-slate-700"><?= h(date('d/m/Y H:i')) ?></span>
                <button type="button" id="btn-exportar-csv" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 hover:text-rubi-emerald" title="Descargar las operaciones filtradas en CSV (Excel)">Exportar CSV</button>
                <button type="button" id="btn-exportar-json" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 hover:text-rubi-emerald" title="Descargar las operaciones filtradas en JSON">JSON</button>
                <button type="button" id="btn-recargar" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 hover:text-rubi-emerald" title="Recargar datos" aria-label="Recargar datos"><?= icono('refresh') ?></button>
            </div>
        </div>

        <div id="alerta-carga" class="mb-6 hidden items-start gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700" role="alert">
            <?= icono('alert', 'mt-0.5 h-5 w-5 shrink-0') ?><span id="alerta-carga-texto"></span>
        </div>

        <!-- KPIs (clic = filtrar) -->
        <section class="mb-6 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5" aria-label="Indicadores">
            <?php
            $kpis = [
                ['activos', 'Activas', 'folder', 'bg-sky-50 text-sky-600'],
                ['al_dia', 'Al día', 'check', 'bg-emerald-50 text-emerald-600'],
                ['seguimiento', 'En seguimiento', 'clock', 'bg-orange-50 text-orange-600'],
                ['critico', 'Críticas', 'alert', 'bg-red-50 text-red-600'],
                ['cerrado', 'Finalizadas', 'archive', 'bg-slate-100 text-slate-500'],
            ];
            foreach ($kpis as [$clave, $etiqueta, $ic, $colores]) : ?>
            <button type="button" data-kpi="<?= $clave ?>" class="flex items-center justify-between rounded-2xl border border-slate-200 bg-white p-4 text-left shadow-sm transition hover:border-slate-300 hover:shadow">
                <span>
                    <span data-kpi-num class="block text-2xl font-extrabold text-rubi-navy">0</span>
                    <span class="block text-xs font-semibold uppercase tracking-wide text-slate-500"><?= h($etiqueta) ?></span>
                </span>
                <span class="flex h-10 w-10 items-center justify-center rounded-xl <?= $colores ?>"><?= icono($ic, 'h-5 w-5') ?></span>
            </button>
            <?php endforeach; ?>
        </section>

        <!-- Panel principal -->
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="grid gap-3 border-b border-slate-100 p-4 md:grid-cols-12 md:items-end sm:p-5">
                <div class="md:col-span-4">
                    <label for="f-texto" class="<?= $claseLabel ?>">Buscar</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400"><?= icono('search') ?></span>
                        <input type="search" id="f-texto" class="<?= $claseInput ?> pl-9" placeholder="N.º de DO, cliente o NIT" autocomplete="off">
                    </div>
                </div>
                <div class="md:col-span-2">
                    <label for="f-sucursal" class="<?= $claseLabel ?>">Sucursal</label>
                    <select id="f-sucursal" class="<?= $claseInput ?>">
                        <option value="">Todas</option>
                        <?php foreach ($sucursalesFiltro as $s) : ?>
                        <option value="<?= h($s) ?>"><?= h($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label for="f-regimen" class="<?= $claseLabel ?>">Régimen</label>
                    <select id="f-regimen" class="<?= $claseInput ?>">
                        <option value="">Todos</option>
                        <?php foreach (Catalogos::REGIMENES as $r) : ?>
                        <option value="<?= h($r) ?>"><?= h($r) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label for="f-nivel" class="<?= $claseLabel ?>">Nivel de alerta</label>
                    <select id="f-nivel" class="<?= $claseInput ?>">
                        <option value="">Todos</option>
                        <option value="activos">Activos (sin finalizar)</option>
                        <option value="al_dia">Al día</option>
                        <option value="seguimiento">En seguimiento</option>
                        <option value="critico">Críticos</option>
                        <option value="cerrado">Finalizados</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <button type="button" id="f-limpiar" class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 hover:text-slate-800">
                        <?= icono('x') ?> Limpiar filtros
                    </button>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-slate-50/60 px-4 py-2.5 text-xs text-slate-500 sm:px-5">
                <span id="txt-conteo" aria-live="polite">—</span>
                <span class="flex flex-wrap items-center gap-x-4 gap-y-1">
                    <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-emerald-500"></span>Al día &lt; <?= SemaforoDo::DIAS_SEGUIMIENTO ?> d</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-orange-500"></span>Seguimiento <?= SemaforoDo::DIAS_SEGUIMIENTO ?>–<?= SemaforoDo::DIAS_CRITICO ?> d</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-red-500"></span>Crítico &gt; <?= SemaforoDo::DIAS_CRITICO ?> d o vencido</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-slate-400"></span>Cerrado: sin alerta</span>
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[980px] text-left text-sm">
                    <thead class="bg-slate-50 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th scope="col" class="py-3 pl-5 pr-3">DO</th>
                            <th scope="col" class="px-3 py-3">Cliente</th>
                            <th scope="col" class="px-3 py-3">Estado</th>
                            <th scope="col" class="px-3 py-3">Fechas</th>
                            <th scope="col" class="px-3 py-3">Semáforo DIAN</th>
                            <th scope="col" class="sticky right-0 border-l border-slate-100 bg-slate-50 px-3 py-3">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tbl-body" class="divide-y divide-slate-100"></tbody>
                </table>
            </div>

            <div id="estado-vacio" class="hidden flex-col items-center justify-center gap-2 px-4 py-14 text-center">
                <span class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400"><?= icono('folder', 'h-6 w-6') ?></span>
                <p id="estado-vacio-texto" class="text-sm font-medium text-slate-600"></p>
            </div>
        </section>
    </main>
</div>

<?php if ($puedeEditar) : ?>
<!-- ===================== MODAL: NUEVA OPERACIÓN ===================== -->
<div id="modal-nuevo" class="fixed inset-0 z-50 hidden overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="modal-nuevo-titulo">
    <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
    <div class="relative flex min-h-full items-start justify-center p-4 sm:items-center sm:p-6" data-fondo-modal>
        <div class="relative w-full max-w-2xl rounded-2xl bg-white shadow-2xl">
            <form id="form-nuevo" novalidate>
                <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-6 py-4">
                    <div>
                        <h2 id="modal-nuevo-titulo" class="text-lg font-extrabold text-rubi-navy">Nueva operación (DO)</h2>
                        <p class="text-xs text-slate-500">Se crea en estado «<?= h(Catalogos::ESTADO_INICIAL) ?>».</p>
                    </div>
                    <button type="button" data-cerrar-modal class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600" aria-label="Cerrar"><?= icono('x', 'h-5 w-5') ?></button>
                </div>

                <div class="grid gap-4 px-6 py-5 sm:grid-cols-2">
                    <div>
                        <label for="n-sucursal" class="<?= $claseLabel ?>">Sucursal *</label>
                        <select id="n-sucursal" name="sucursal" class="<?= $claseInput ?>" required>
                            <?php foreach (Catalogos::SUCURSALES as $s) : ?>
                            <option value="<?= h($s) ?>"><?= h($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p data-error-para="sucursal" class="mt-1 hidden text-xs text-red-600"></p>
                    </div>
                    <div>
                        <label for="n-regimen" class="<?= $claseLabel ?>">Régimen *</label>
                        <select id="n-regimen" name="regimen" class="<?= $claseInput ?>" required>
                            <?php foreach (Catalogos::REGIMENES as $r) : ?>
                            <option value="<?= h($r) ?>"><?= h($r) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p data-error-para="regimen" class="mt-1 hidden text-xs text-red-600"></p>
                    </div>
                    <div class="sm:col-span-2">
                        <label for="n-codigo" class="<?= $claseLabel ?>">Código DO *</label>
                        <input id="n-codigo" name="do_codigo" type="text" maxlength="30" class="<?= $claseInput ?> font-semibold uppercase tracking-wide" placeholder="BOG26IMP0001" autocomplete="off" required data-autofoco>
                        <p id="hint-codigo" data-pista-para="do_codigo" class="mt-1 text-xs text-slate-500"></p>
                        <p data-error-para="do_codigo" class="mt-1 hidden text-xs text-red-600"></p>
                    </div>
                    <div>
                        <label for="n-cliente" class="<?= $claseLabel ?>">Cliente *</label>
                        <input id="n-cliente" name="cliente" type="text" maxlength="150" class="<?= $claseInput ?>" placeholder="Razón social" required>
                        <p data-error-para="cliente" class="mt-1 hidden text-xs text-red-600"></p>
                    </div>
                    <div>
                        <label for="n-nit" class="<?= $claseLabel ?>">NIT *</label>
                        <input id="n-nit" name="nit" type="text" maxlength="20" class="<?= $claseInput ?>" placeholder="900123456-8" inputmode="numeric" required>
                        <p id="hint-nit" data-pista-para="nit" class="mt-1 text-xs text-slate-500">El dígito de verificación se calcula automáticamente.</p>
                        <p data-error-para="nit" class="mt-1 hidden text-xs text-red-600"></p>
                    </div>
                    <div>
                        <label for="n-apertura" class="<?= $claseLabel ?>">Fecha de apertura *</label>
                        <input id="n-apertura" name="fecha_apertura" type="date" class="<?= $claseInput ?>" required>
                        <p data-error-para="fecha_apertura" class="mt-1 hidden text-xs text-red-600"></p>
                    </div>
                    <div>
                        <label for="n-vencimiento" class="<?= $claseLabel ?>">Fecha de vencimiento *</label>
                        <input id="n-vencimiento" name="fecha_vencimiento" type="date" class="<?= $claseInput ?>" required>
                        <p class="mt-1 text-xs text-slate-500">Sugerida: apertura + <?= SemaforoDo::PLAZO_DIAN_DIAS ?> días (DIAN).</p>
                        <p data-error-para="fecha_vencimiento" class="mt-1 hidden text-xs text-red-600"></p>
                    </div>
                    <div>
                        <label for="n-email" class="<?= $claseLabel ?>">Correo del cliente <span class="font-normal normal-case text-slate-400">(opcional)</span></label>
                        <input id="n-email" name="email" type="email" maxlength="150" class="<?= $claseInput ?>" placeholder="operaciones@cliente.com">
                        <p data-error-para="email" class="mt-1 hidden text-xs text-red-600"></p>
                    </div>
                    <div>
                        <label for="n-telefono" class="<?= $claseLabel ?>">Teléfono <span class="font-normal normal-case text-slate-400">(opcional)</span></label>
                        <input id="n-telefono" name="telefono" type="tel" maxlength="30" class="<?= $claseInput ?>" placeholder="300 000 0000">
                        <p data-error-para="telefono" class="mt-1 hidden text-xs text-red-600"></p>
                    </div>
                    <div class="sm:col-span-2">
                        <label for="n-observacion" class="<?= $claseLabel ?>">Observación inicial</label>
                        <textarea id="n-observacion" name="observacion" rows="3" maxlength="1000" class="<?= $claseInput ?>" placeholder="Ej.: Pendiente Formulario 500 y factura comercial original."></textarea>
                        <p data-error-para="observacion" class="mt-1 hidden text-xs text-red-600"></p>
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-2 border-t border-slate-100 bg-slate-50/60 px-6 py-4 sm:flex-row sm:justify-end">
                    <button type="button" data-cerrar-modal class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancelar</button>
                    <button type="submit" class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-rubi-emerald px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-rubi-emerald2 disabled:cursor-not-allowed disabled:opacity-60"><?= icono('plus') ?> <span data-texto-boton>Crear DO</span></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ================= MODAL: ACTUALIZAR ESTADO / OBSERVAR ================= -->
<div id="modal-estado" class="fixed inset-0 z-50 hidden overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="modal-estado-titulo">
    <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
    <div class="relative flex min-h-full items-start justify-center p-4 sm:items-center sm:p-6" data-fondo-modal>
        <div class="relative w-full max-w-2xl rounded-2xl bg-white shadow-2xl">
            <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-6 py-4">
                <div class="min-w-0">
                    <h2 id="modal-estado-titulo" class="text-lg font-extrabold text-rubi-navy"><?= $puedeEditar ? 'Actualizar estado / Observar' : 'Bitácora de seguimiento' ?> — <span id="estado-codigo"></span></h2>
                    <p id="estado-cliente" class="truncate text-xs text-slate-500"></p>
                </div>
                <button type="button" data-cerrar-modal class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600" aria-label="Cerrar"><?= icono('x', 'h-5 w-5') ?></button>
            </div>

            <div id="estado-resumen" class="flex flex-wrap items-center gap-2 border-b border-slate-100 bg-slate-50/60 px-6 py-3 text-xs"></div>

            <?php if ($puedeEditar) : ?>
            <form id="form-estado" class="space-y-4 px-6 py-5" novalidate>
                <div>
                    <label for="e-estado" class="<?= $claseLabel ?>">Nuevo estado</label>
                    <select id="e-estado" name="nuevo_estado" class="<?= $claseInput ?>" data-autofoco>
                        <?php foreach (Catalogos::ESTADOS as $e) : ?>
                        <option value="<?= h($e) ?>"><?= h($e) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <div class="mb-1 flex items-end justify-between">
                        <label for="e-observaciones" class="block text-xs font-bold uppercase tracking-wide text-slate-600">Observación de seguimiento</label>
                        <span id="e-contador" class="text-[11px] text-slate-400">0 / 1000</span>
                    </div>
                    <textarea id="e-observaciones" name="observaciones" rows="3" maxlength="1000" class="<?= $claseInput ?>" placeholder="Ej.: Selectividad física antinarcóticos programada para mañana 8:00 a. m."></textarea>
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        <?php foreach (['Selectividad física antinarcóticos', 'Inspección previa', 'Visto bueno INVIMA', 'Levante autorizado', 'Pendiente documentos del cliente'] as $rapida) : ?>
                        <button type="button" data-obs-rapida="<?= h($rapida) ?>" class="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-600 transition hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-700">+ <?= h($rapida) ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <p class="flex items-start gap-2 rounded-lg bg-sky-50 px-3 py-2 text-xs text-sky-700"><?= icono('info', 'mt-0.5 h-4 w-4 shrink-0') ?> Al guardar se registra en la bitácora y se envía una alerta a n8n.</p>
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" data-cerrar-modal class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancelar</button>
                    <button type="submit" class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-rubi-emerald px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-rubi-emerald2 disabled:cursor-not-allowed disabled:opacity-60"><?= icono('check') ?> <span data-texto-boton>Guardar y notificar</span></button>
                </div>
            </form>
            <?php endif; ?>

            <div class="border-t border-slate-100 px-6 py-5">
                <h3 class="mb-3 text-xs font-bold uppercase tracking-wide text-slate-500">Bitácora (más reciente primero)</h3>
                <ol id="estado-bitacora" class="max-h-64 space-y-3 overflow-y-auto pr-1"></ol>
            </div>
        </div>
    </div>
</div>

<!-- ================= MODAL: DOCUMENTOS / LEGAJO ADUANERO ================= -->
<div id="modal-legajo" class="fixed inset-0 z-50 hidden overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="modal-legajo-titulo">
    <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
    <div class="relative flex min-h-full items-start justify-center p-4 sm:items-center sm:p-6" data-fondo-modal>
        <div class="relative w-full max-w-2xl rounded-2xl bg-white shadow-2xl">
            <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-6 py-4">
                <div class="min-w-0">
                    <h2 id="modal-legajo-titulo" class="text-lg font-extrabold text-rubi-navy">Legajo aduanero — <span id="legajo-codigo"></span></h2>
                    <p id="legajo-cliente" class="truncate text-xs text-slate-500"></p>
                </div>
                <button type="button" data-cerrar-modal class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600" aria-label="Cerrar"><?= icono('x', 'h-5 w-5') ?></button>
            </div>

            <div id="legajo-checklist" class="flex flex-wrap gap-1.5 border-b border-slate-100 bg-slate-50/60 px-6 py-3"></div>

            <?php if ($puedeEditar) : ?>
            <form id="form-legajo" class="space-y-3 border-b border-slate-100 px-6 py-5" novalidate>
                <div class="grid gap-3 sm:grid-cols-5">
                    <div class="sm:col-span-2">
                        <label for="l-tipo" class="<?= $claseLabel ?>">Tipo de documento</label>
                        <select id="l-tipo" name="tipo_documento" class="<?= $claseInput ?>" data-autofoco>
                            <?php foreach (Catalogos::TIPOS_DOCUMENTO as $t) : ?>
                            <option value="<?= h($t) ?>"><?= h($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sm:col-span-3">
                        <span class="<?= $claseLabel ?>">Archivo</span>
                        <label id="l-zona" for="l-archivo" class="flex cursor-pointer items-center gap-3 rounded-lg border-2 border-dashed border-slate-300 bg-white px-3 py-2 text-sm text-slate-500 transition hover:border-emerald-500 hover:bg-emerald-50/40">
                            <?= icono('upload', 'h-5 w-5 shrink-0 text-slate-400') ?>
                            <span id="l-archivo-nombre" class="min-w-0 truncate">Seleccione o arrastre un archivo</span>
                        </label>
                        <input id="l-archivo" name="documento" type="file" class="sr-only" accept=".pdf,.jpg,.jpeg,.png,.docx,application/pdf,image/jpeg,image/png,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
                    </div>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-xs text-slate-500">PDF, JPG, PNG o DOCX · máximo 10 MB.</p>
                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-rubi-emerald px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-rubi-emerald2 disabled:cursor-not-allowed disabled:opacity-60"><?= icono('upload') ?> <span data-texto-boton>Subir documento</span></button>
                </div>
                <div id="l-progreso" class="hidden">
                    <div class="h-2 overflow-hidden rounded-full bg-slate-200"><div id="l-progreso-barra" class="h-full w-0 rounded-full bg-rubi-emerald transition-all"></div></div>
                    <p id="l-progreso-texto" class="mt-1 text-right text-[11px] text-slate-500">0 %</p>
                </div>
            </form>
            <?php endif; ?>

            <div class="px-6 py-5">
                <h3 class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500">Documentos adjuntos</h3>
                <ul id="legajo-lista" class="max-h-72 divide-y divide-slate-100 overflow-y-auto"></ul>
            </div>

            <div class="flex justify-end border-t border-slate-100 bg-slate-50/60 px-6 py-3">
                <button type="button" data-cerrar-modal class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Notificaciones -->
<div id="toasts" class="pointer-events-none fixed right-4 top-4 z-[60] flex w-[calc(100%-2rem)] max-w-sm flex-col gap-2" aria-live="polite"></div>

<script type="application/json" id="app-config"><?= json_encode($configJs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
<script src="../assets/js/panel.js" defer></script>
</body>
</html>
