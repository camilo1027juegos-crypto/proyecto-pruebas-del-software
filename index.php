<?php

declare(strict_types=1);

use Rubicam\Seguridad\CabecerasSeguras;
use Rubicam\Seguridad\SesionPanel;
use Rubicam\Seguridad\TokenCsrf;

require_once __DIR__ . '/config/arranque.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/src/Vistas/ayudantes.php';

CabecerasSeguras::enviar(CabecerasSeguras::publico(CHAT_WEBHOOK_URL, !empty($_SERVER['HTTPS'])));
SesionPanel::iniciar();
$csrf = TokenCsrf::publico()->emitir();
?>

<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rubicam | Agencia de Aduanas Nivel 2 — Comercio Exterior en Colombia</title>
<meta name="description" content="Rubicam S.A.S., Agencia de Aduanas Nivel 2 autorizada por la DIAN. Agenciamiento aduanero, Zona Franca Cúcuta y asesoría arancelaria, jurídica y cambiaria con más de 30 años de experiencia.">
<link rel="canonical" href="<?= h(BASE_URL) ?>/">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">


<style>svg{width:1.25em;height:1.25em;display:inline-block;flex-shrink:0}</style>


<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/tailwind.build.css">
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/custom.css">
<link rel="icon" href="data:,">
</head>
<body class="font-sans text-slate-800 bg-white antialiased">

<a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-[100] focus:bg-white focus:px-4 focus:py-2 focus:rounded-md focus:shadow-lg">Saltar al contenido</a>


<div class="bg-rubi-navy text-slate-200 text-xs">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-9 flex items-center justify-between gap-4 overflow-x-auto">
    <div class="flex items-center gap-4 shrink-0">
      <span class="hidden sm:inline-flex items-center gap-1.5">
        <svg class="w-3.5 h-3.5 text-rubi-orange" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 1a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 13h12a1 1 0 00.707-1.707L16 10.586V7a6 6 0 00-6-6zm0 15a3 3 0 01-3-3h6a3 3 0 01-3 3z" clip-rule="evenodd"/></svg>
        <?= h(SITE_DIAN_LEVEL) ?>
      </span>
      <span class="hidden md:inline">NIT <?= h(SITE_NIT) ?></span>
    </div>
    <nav class="flex items-center gap-4 shrink-0" aria-label="Accesos institucionales">
      <a href="#cotizador" class="hover:text-white transition-colors">Trámites en línea</a>
      <a href="https://wa.me/<?= h(WHATSAPP_NUMBER) ?>" class="hover:text-white transition-colors" target="_blank" rel="noopener">PQR</a>
      <a href="panel/" class="hover:text-rubi-orange transition-colors font-medium">Ingreso Lobby</a>    </nav>
  </div>
</div>



<header id="site-header" class="sticky top-0 z-50 bg-white/95 backdrop-blur border-b border-slate-100 shadow-sm">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="h-16 lg:h-20 flex items-center justify-between">

      <a href="/" class="flex items-center gap-2 shrink-0" aria-label="Rubicam — Inicio">
        <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-rubi-navy text-rubi-orange font-extrabold text-lg">R</span>
        <span class="text-lg font-extrabold tracking-tight text-rubi-navy">RUBICAM</span>
      </a>

      <nav class="hidden lg:flex items-center gap-8 text-sm font-semibold text-slate-700" aria-label="Menú principal">
        <a href="#nosotros" class="hover:text-rubi-navy transition-colors">Nosotros</a>
        <div class="relative group">
          <button class="flex items-center gap-1 hover:text-rubi-navy transition-colors" aria-haspopup="true" aria-expanded="false">
            Servicios
            <svg class="w-3.5 h-3.5 transition-transform group-hover:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
          </button>
          <div class="absolute left-0 top-full pt-3 w-72 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-150">
            <div class="bg-white rounded-xl shadow-xl border border-slate-100 p-2">
              <a href="#servicios" class="block px-4 py-3 rounded-lg hover:bg-slate-50">
                <span class="block font-semibold text-rubi-navy">Agenciamiento Aduanero N2</span>
                <span class="block text-xs text-slate-500 mt-0.5">Nacionalización, regímenes DEX</span>
              </a>
              <a href="#servicios" class="block px-4 py-3 rounded-lg hover:bg-slate-50">
                <span class="block font-semibold text-rubi-navy">Zona Franca & Logística</span>
                <span class="block text-xs text-slate-500 mt-0.5">Cúcuta · almacenamiento · tráficos</span>
              </a>
              <a href="#servicios" class="block px-4 py-3 rounded-lg hover:bg-slate-50">
                <span class="block font-semibold text-rubi-navy">Asesoría Arancelaria y Jurídica</span>
                <span class="block text-xs text-slate-500 mt-0.5">DIAN · Banco de la República</span>
              </a>
            </div>
          </div>
        </div>
        <a href="#cobertura" class="hover:text-rubi-navy transition-colors">Cobertura</a>
        <a href="#cotizador" class="hover:text-rubi-navy transition-colors">Cotizador</a>
        <a href="#contacto" class="hover:text-rubi-navy transition-colors">Contacto</a>
      </nav>

      <div class="flex items-center gap-3">
        <a href="panel/" class="hidden sm:inline-flex items-center gap-2 bg-rubi-emerald hover:bg-rubi-emerald2 text-white text-sm font-semibold px-4 py-2.5 rounded-lg transition-colors shadow-sm">          Ingreso / Lobby
        </a>
        <button id="mobile-menu-btn" class="lg:hidden p-2 -mr-2 text-slate-700" aria-label="Abrir menú" aria-expanded="false" aria-controls="mobile-menu">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
      </div>
    </div>
  </div>

  <!-- Menú móvil -->
<nav id="mobile-menu" class="lg:hidden hidden border-t border-slate-100 bg-white" aria-label="Menú móvil">
    <div class="px-4 py-3 space-y-1">
      <a href="#nosotros" class="block px-3 py-2.5 rounded-lg text-sm font-semibold text-slate-700 hover:bg-slate-50">Nosotros</a>
      <a href="#servicios" class="block px-3 py-2.5 rounded-lg text-sm font-semibold text-slate-700 hover:bg-slate-50">Servicios</a>
      <a href="#cobertura" class="block px-3 py-2.5 rounded-lg text-sm font-semibold text-slate-700 hover:bg-slate-50">Cobertura</a>
      <a href="#cotizador" class="block px-3 py-2.5 rounded-lg text-sm font-semibold text-slate-700 hover:bg-slate-50">Cotizador</a>
      <a href="#contacto" class="block px-3 py-2.5 rounded-lg text-sm font-semibold text-slate-700 hover:bg-slate-50">Contacto</a>
      <a href="panel/" class="block mt-2 text-center bg-rubi-emerald text-white font-semibold px-3 py-2.5 rounded-lg">Ingreso / Lobby</a>
    </div>
  </nav>
</header>

<main id="main">

<!-- ============================================================ -->
<!-- 3. HERO INMERSIVO                                             -->
<!-- ============================================================ -->
<section class="relative overflow-hidden bg-gradient-to-br from-rubi-navy via-rubi-navy2 to-rubi-emerald pt-20 pb-40 lg:pt-28 lg:pb-48">
  <div class="absolute inset-0 hero-grid opacity-20" aria-hidden="true"></div>
  <div class="absolute -top-24 -right-24 w-96 h-96 bg-rubi-orange/20 rounded-full blur-3xl" aria-hidden="true"></div>

  <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="max-w-3xl">
      <span class="inline-flex items-center gap-2 bg-white/10 text-white text-xs font-semibold px-3 py-1.5 rounded-full ring-1 ring-white/20 mb-6">
        <span class="w-1.5 h-1.5 rounded-full bg-rubi-orange"></span>
        Agencia de Aduanas 
      </span>
      <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold text-white leading-[1.1] tracking-tight">
        Comercio exterior sin fricciones,<br class="hidden sm:block"> con <span class="text-rubi-orange">respaldo regulatorio</span> real.
      </h1>
      <p class="mt-6 text-lg text-slate-200 max-w-xl">
        Más de <?= (int) SITE_YEARS ?> años facilitando importaciones, exportaciones y operaciones en
        Zona Franca. Agenciamiento aduanero, logística integrada y asesoría jurídica bajo un mismo grupo empresarial.
      </p>
      <div class="mt-9 flex flex-wrap items-center gap-4">
        <a href="#cotizador" class="inline-flex items-center gap-2 bg-rubi-orange hover:bg-orange-600 text-white font-semibold px-6 py-3.5 rounded-lg shadow-lg shadow-orange-500/20 transition-colors">
          Cotiza tu operación
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
        </a>
        <a href="panel/" class="inline-flex items-center gap-2 bg-white/10 hover:bg-white/20 text-white font-semibold px-6 py-3.5 rounded-lg ring-1 ring-white/25 transition-colors">
          Ingreso / Lobby
        </a>
      </div>
      <dl class="mt-14 grid grid-cols-3 gap-6 max-w-lg">
        <div>
          <dt class="sr-only">Años de experiencia</dt>
          <dd class="text-3xl font-extrabold text-white"><?= (int) SITE_YEARS ?>+</dd>
          <dd class="text-xs text-slate-300 mt-1">Años de experiencia</dd>
        </div>
        <div>
          <dt class="sr-only">Nivel DIAN</dt>
          <dd class="text-3xl font-extrabold text-white">N2</dd>
          <dd class="text-xs text-slate-300 mt-1">Agencia aduanera DIAN</dd>
        </div>
        <div>
          <dt class="sr-only">Ciudades</dt>
          <dd class="text-3xl font-extrabold text-white">5+</dd>
          <dd class="text-xs text-slate-300 mt-1">Ciudades con operación</dd>
        </div>
      </dl>
    </div>
  </div>
</section>


<section class="relative z-10 -mt-24 lg:-mt-28 px-4 sm:px-6 lg:px-8" aria-label="Trámites rápidos">
  <div class="max-w-7xl mx-auto">
    <div class="bg-white rounded-2xl shadow-2xl shadow-slate-900/10 ring-1 ring-slate-100 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 divide-y sm:divide-y-0 sm:divide-x divide-slate-100">

      <button type="button" data-open-modal="tracking-modal" class="quick-action group flex items-center gap-4 p-6 hover:bg-slate-50 transition-colors rounded-t-2xl lg:rounded-l-2xl lg:rounded-tr-none text-left">
        <span class="flex items-center justify-center w-11 h-11 rounded-xl bg-rubi-navy/10 text-rubi-navy group-hover:bg-rubi-navy group-hover:text-white transition-colors shrink-0">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
        </span>
        <span>
          <span class="block font-semibold text-sm text-slate-900">Consulta tu DO</span>
          <span class="block text-xs text-slate-500">Radicado / vencimientos</span>
        </span>
      </button>

      <a href="#cotizador" class="group flex items-center gap-4 p-6 hover:bg-slate-50 transition-colors">
        <span class="flex items-center justify-center w-11 h-11 rounded-xl bg-rubi-emerald/10 text-rubi-emerald group-hover:bg-rubi-emerald group-hover:text-white transition-colors shrink-0">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3v-6m-3 6v-9m-2 12h10a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
        </span>
        <span>
          <span class="block font-semibold text-sm text-slate-900">Cotizador exprés</span>
          <span class="block text-xs text-slate-500">Respuesta en minutos</span>
        </span>
      </a>

      <a href="https://wa.me/<?= h(WHATSAPP_NUMBER) ?>" target="_blank" rel="noopener" class="group flex items-center gap-4 p-6 hover:bg-slate-50 transition-colors">
        <span class="flex items-center justify-center w-11 h-11 rounded-xl bg-rubi-orange/10 text-rubi-orange group-hover:bg-rubi-orange group-hover:text-white transition-colors shrink-0">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
        </span>
        <span>
          <span class="block font-semibold text-sm text-slate-900">Canales digitales</span>
          <span class="block text-xs text-slate-500">WhatsApp / correo</span>
        </span>
      </a>

      <a href="#contacto" class="group flex items-center gap-4 p-6 hover:bg-slate-50 transition-colors rounded-b-2xl lg:rounded-r-2xl lg:rounded-bl-none">
        <span class="flex items-center justify-center w-11 h-11 rounded-xl bg-slate-900/10 text-slate-700 group-hover:bg-slate-900 group-hover:text-white transition-colors shrink-0">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        </span>
        <span>
          <span class="block font-semibold text-sm text-slate-900">Agenda asesoría</span>
          <span class="block text-xs text-slate-500">Con un especialista</span>
        </span>
      </a>

    </div>
  </div>
</section>


<section id="servicios" class="py-24 lg:py-32 bg-slate-50">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="max-w-2xl mb-14">
      <span class="text-sm font-bold text-rubi-emerald uppercase tracking-wider">Nuestros servicios</span>
      <h2 class="mt-3 text-3xl sm:text-4xl font-extrabold text-rubi-navy tracking-tight">Tres ejes, un solo grupo empresarial</h2>
      <p class="mt-4 text-slate-600">Respaldados por más de <?= (int) SITE_YEARS ?> años de experiencia en comercio exterior,
        integrando comercializadoras internacionales, importación, Zona Franca y agenciamiento aduanero.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

      <article class="lg:col-span-2 lg:row-span-2 bg-rubi-navy rounded-2xl p-8 lg:p-10 flex flex-col justify-between min-h-[320px] relative overflow-hidden group">
        <div class="absolute -right-10 -bottom-10 w-56 h-56 bg-rubi-orange/10 rounded-full blur-2xl group-hover:bg-rubi-orange/20 transition-colors" aria-hidden="true"></div>
        <div class="relative">
          <span class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-rubi-orange/20 text-rubi-orange mb-6">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          </span>
          <h3 class="text-2xl font-bold text-white">Agenciamiento Aduanero Nivel 2</h3>
          <p class="mt-3 text-slate-300 max-w-md">Nacionalización, inspecciones físicas y documentales, y gestión de
            regímenes de importación y exportación (DEX) con respaldo regulatorio directo ante la DIAN.</p>
        </div>
        <a href="#cotizador" class="relative inline-flex items-center gap-2 text-white font-semibold text-sm mt-8 group-hover:gap-3 transition-all">
          Solicitar agenciamiento
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
        </a>
      </article>

      <article class="bg-white rounded-2xl p-8 ring-1 ring-slate-100 hover:ring-rubi-emerald/30 hover:shadow-lg transition-all">
        <span class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-rubi-emerald/10 text-rubi-emerald mb-6">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
        </span>
        <h3 class="text-xl font-bold text-rubi-navy">Zona Franca Cúcuta & Logística</h3>
        <p class="mt-2 text-sm text-slate-600">Almacenamiento, distribución y tráficos internacionales como usuario
          industrial en Zona Franca.</p>
      </article>

      <article class="bg-white rounded-2xl p-8 ring-1 ring-slate-100 hover:ring-rubi-orange/30 hover:shadow-lg transition-all">
        <span class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-rubi-orange/10 text-rubi-orange mb-6">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
        </span>
        <h3 class="text-xl font-bold text-rubi-navy">Asesoría Arancelaria y Jurídica</h3>
        <p class="mt-2 text-sm text-slate-600">Acompañamiento normativo, cambiario y jurídico ante la DIAN y el
          Banco de la República.</p>
      </article>

    </div>
  </div>
</section>


<section id="nosotros" class="py-24 lg:py-32">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid lg:grid-cols-2 gap-14 items-center">
    <div>
      <span class="text-sm font-bold text-rubi-emerald uppercase tracking-wider">Grupo empresarial</span>
      <h2 class="mt-3 text-3xl sm:text-4xl font-extrabold text-rubi-navy tracking-tight">Un ecosistema completo de comercio exterior</h2>
      <p class="mt-4 text-slate-600">Rubicam no es solo una agencia de aduanas: es un grupo empresarial conformado
        por comercializadoras internacionales, una empresa importadora, usuario industrial en Zona Franca Cúcuta y
        la Agencia de Aduanas — todo bajo un mismo estándar de cumplimiento y trazabilidad.</p>
      <ul class="mt-8 space-y-4">
        <li class="flex gap-3">
          <svg class="w-5 h-5 text-rubi-emerald shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
          <span class="text-sm text-slate-700"><strong class="text-rubi-navy">Nivel 2 DIAN</strong> — máxima capacidad operativa autorizada para agenciamiento aduanero.</span>
        </li>
        <li class="flex gap-3">
          <svg class="w-5 h-5 text-rubi-emerald shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
          <span class="text-sm text-slate-700"><strong class="text-rubi-navy">Operación fronteriza directa</strong> — presencia en los pasos Tienditas y Atanasio Girardot.</span>
        </li>
        <li class="flex gap-3">
          <svg class="w-5 h-5 text-rubi-emerald shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
          <span class="text-sm text-slate-700"><strong class="text-rubi-navy">Trazabilidad digital</strong> — panel Lobby con semáforo de vencimientos de Órdenes de Operación.</span>
        </li>
      </ul>
    </div>
    <div class="grid grid-cols-2 gap-4">
      <div class="bg-rubi-navy rounded-2xl p-6 text-white">
        <div class="text-3xl font-extrabold"><?= (int) SITE_YEARS ?>+</div>
        <div class="text-xs text-slate-300 mt-1">Años de experiencia</div>
      </div>
      <div class="bg-rubi-emerald rounded-2xl p-6 text-white">
        <div class="text-3xl font-extrabold">N2</div>
        <div class="text-xs text-emerald-100 mt-1">Nivel DIAN</div>
      </div>
      <div class="bg-slate-100 rounded-2xl p-6">
        <div class="text-3xl font-extrabold text-rubi-navy">4</div>
        <div class="text-xs text-slate-500 mt-1">Empresas del grupo</div>
      </div>
      <div class="bg-rubi-orange rounded-2xl p-6 text-white">
        <div class="text-3xl font-extrabold">2</div>
        <div class="text-xs text-orange-100 mt-1">Pasos fronterizos</div>
      </div>
    </div>
  </div>
</section>


<section id="cobertura" class="py-24 lg:py-32 bg-rubi-navy relative overflow-hidden">
  <div class="absolute inset-0 hero-grid opacity-10" aria-hidden="true"></div>
  <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="max-w-2xl mb-14">
      <span class="text-sm font-bold text-rubi-orange uppercase tracking-wider">Cobertura nacional</span>
      <h2 class="mt-3 text-3xl sm:text-4xl font-extrabold text-white tracking-tight">Presencia donde tu operación lo necesita</h2>
    </div>
    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-5">
      <?php
        $sedes = [
          ['city' => 'Cúcuta', 'tag' => 'Sede principal · Zona Franca', 'main' => true],
          ['city' => 'Envigado', 'tag' => 'Oficina regional'],
          ['city' => 'Bogotá', 'tag' => 'Operaciones'],
          ['city' => 'Cartagena', 'tag' => 'Operaciones portuarias'],
        ];
        foreach ($sedes as $s) : ?>
        <div class="bg-white/5 ring-1 ring-white/10 rounded-xl p-6 hover:bg-white/10 transition-colors">
            <?php if (!empty($s['main'])) : ?>
            <span class="inline-block text-[10px] font-bold uppercase tracking-wider text-rubi-orange mb-2">Principal</span>
            <?php endif; ?>
          <div class="text-lg font-bold text-white"><?= h($s['city']) ?></div>
          <div class="text-xs text-slate-300 mt-1"><?= h($s['tag']) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <p class="mt-8 text-sm text-slate-300 max-w-2xl">
      Operación fronteriza directa a través del Puente Internacional Tienditas y el paso Atanasio Girardot.
    </p>
  </div>
</section>


<section id="cotizador" class="py-24 lg:py-32">
  <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="text-center max-w-xl mx-auto mb-12">
      <span class="text-sm font-bold text-rubi-emerald uppercase tracking-wider">Cotizador exprés</span>
      <h2 class="mt-3 text-3xl sm:text-4xl font-extrabold text-rubi-navy tracking-tight">Cuéntanos tu operación</h2>
      <p class="mt-4 text-slate-600">Completa el formulario y un asesor especializado te contactará con una propuesta preliminar.</p>
    </div>

    <form id="quote-form" class="relative bg-white rounded-2xl ring-1 ring-slate-100 shadow-xl shadow-slate-900/5 p-6 sm:p-10" action="handlers/quote-handler.php" method="post" novalidate>
      <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
      <div class="absolute -left-[9999px]" aria-hidden="true">
        <label for="q_website">No llenar</label>
        <input type="text" id="q_website" name="website" tabindex="-1" autocomplete="off">
      </div>

      <div class="grid sm:grid-cols-2 gap-6">
        <div>
          <label for="q-operation" class="block text-sm font-semibold text-slate-700 mb-1.5">Tipo de operación</label>
          <select id="q-operation" name="operation" required class="w-full rounded-lg border-slate-200 focus:border-rubi-emerald focus:ring-rubi-emerald text-sm">
            <option value="">Selecciona…</option>
            <option value="importacion">Importación</option>
            <option value="exportacion">Exportación</option>
            <option value="zona_franca">Operación en Zona Franca</option>
            <option value="asesoria">Solo asesoría</option>
          </select>
          <p class="quote-error text-xs text-red-600 mt-1 hidden">Selecciona el tipo de operación.</p>
        </div>
        <div>
          <label for="q-value" class="block text-sm font-semibold text-slate-700 mb-1.5">Valor CIF estimado (USD)</label>
          <input type="text" inputmode="decimal" id="q-value" name="cif_value" placeholder="Ej. 15000" class="w-full rounded-lg border-slate-200 focus:border-rubi-emerald focus:ring-rubi-emerald text-sm">
          <p class="quote-error text-xs text-red-600 mt-1 hidden">Ingresa un valor numérico válido.</p>
        </div>
        <div>
          <label for="q-origin" class="block text-sm font-semibold text-slate-700 mb-1.5">País de origen</label>
          <input type="text" id="q-origin" name="origin" class="w-full rounded-lg border-slate-200 focus:border-rubi-emerald focus:ring-rubi-emerald text-sm">
        </div>
        <div>
          <label for="q-destination" class="block text-sm font-semibold text-slate-700 mb-1.5">Destino / ciudad</label>
          <input type="text" id="q-destination" name="destination" class="w-full rounded-lg border-slate-200 focus:border-rubi-emerald focus:ring-rubi-emerald text-sm">
        </div>
        <div>
          <label for="q-name" class="block text-sm font-semibold text-slate-700 mb-1.5">Nombre</label>
          <input type="text" id="q-name" name="name" required maxlength="200" class="w-full rounded-lg border-slate-200 focus:border-rubi-emerald focus:ring-rubi-emerald text-sm">
          <p class="quote-error text-xs text-red-600 mt-1 hidden">Ingresa tu nombre.</p>
        </div>
        <div>
          <label for="q-email" class="block text-sm font-semibold text-slate-700 mb-1.5">Correo electrónico</label>
          <input type="email" id="q-email" name="email" required maxlength="200" class="w-full rounded-lg border-slate-200 focus:border-rubi-emerald focus:ring-rubi-emerald text-sm">
          <p class="quote-error text-xs text-red-600 mt-1 hidden">Ingresa un correo válido.</p>
        </div>
        <div class="sm:col-span-2">
          <label for="q-phone" class="block text-sm font-semibold text-slate-700 mb-1.5">Teléfono de contacto</label>
          <input type="text" id="q-phone" name="phone" required class="w-full rounded-lg border-slate-200 focus:border-rubi-emerald focus:ring-rubi-emerald text-sm">
          <p class="quote-error text-xs text-red-600 mt-1 hidden">Ingresa un teléfono válido.</p>
        </div>
      </div>

      <button type="submit" class="mt-8 w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-rubi-emerald hover:bg-rubi-emerald2 text-white font-semibold px-8 py-3.5 rounded-lg transition-colors">
        <span id="quote-submit-label">Enviar cotización</span>
        <span id="quote-spinner" class="hidden w-4 h-4 border-2 border-white/40 border-t-white rounded-full animate-spin"></span>
      </button>

      <div id="quote-response" class="mt-4 text-sm font-medium" role="status" aria-live="polite"></div>
    </form>
  </div>
</section>


<section id="contacto" class="py-20 bg-gradient-to-r from-rubi-emerald to-rubi-emerald2">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-6 text-center sm:text-left">
    <div>
      <h2 class="text-2xl sm:text-3xl font-extrabold text-white">¿Ya eres cliente Rubicam?</h2>
      <p class="text-emerald-50 mt-1">Ingresa a tu panel para consultar tus órdenes de operación en tiempo real.</p>
    </div>
    <div class="flex flex-wrap gap-3 justify-center">
      <a href="panel/" class="inline-flex items-center gap-2 bg-white text-rubi-emerald font-semibold px-6 py-3 rounded-lg hover:bg-emerald-50 transition-colors">Ingreso / Lobby</a>
      <a href="https://wa.me/<?= h(WHATSAPP_NUMBER) ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-2 bg-rubi-orange text-white font-semibold px-6 py-3 rounded-lg hover:bg-orange-600 transition-colors">Habla con un asesor</a>
    </div>  </div>
</section>

</main>

<!-- ============================================================ -->
<!-- 10. FOOTER CORPORATIVO                                        -->
<!-- ============================================================ -->
<footer class="bg-rubi-navy text-slate-300">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 grid grid-cols-2 lg:grid-cols-5 gap-10">

    <div class="col-span-2">
      <div class="flex items-center gap-2 mb-4">
        <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-white/10 text-rubi-orange font-extrabold">R</span>
        <span class="text-white font-extrabold">RUBICAM</span>
      </div>
      <p class="text-sm text-slate-400 max-w-xs"><?= h(SITE_LEGAL_NAME) ?> — <?= h(SITE_DIAN_LEVEL) ?>. NIT <?= h(SITE_NIT) ?>.</p>
      <div class="flex gap-3 mt-5">
        <a href="#" aria-label="LinkedIn" class="w-9 h-9 rounded-full bg-white/5 hover:bg-white/15 flex items-center justify-center transition-colors text-xs">in</a>
        <a href="#" aria-label="Instagram" class="w-9 h-9 rounded-full bg-white/5 hover:bg-white/15 flex items-center justify-center transition-colors text-xs">ig</a>
      </div>
    </div>

    <div>
      <h3 class="text-white font-semibold text-sm mb-4">Servicios</h3>
      <ul class="space-y-2.5 text-sm">
        <li><a href="#servicios" class="hover:text-white">Agenciamiento Aduanero</a></li>
        <li><a href="#servicios" class="hover:text-white">Zona Franca & Logística</a></li>
        <li><a href="#servicios" class="hover:text-white">Asesoría Arancelaria</a></li>
      </ul>
    </div>

    <div>
      <h3 class="text-white font-semibold text-sm mb-4">Sedes</h3>
      <ul class="space-y-2.5 text-sm">
        <li><?= h(OFFICE_CUCUTA) ?></li>
        <li><?= h(OFFICE_ENVIGADO) ?></li>
      </ul>
    </div>

    <div>
      <h3 class="text-white font-semibold text-sm mb-4">Contacto</h3>
      <ul class="space-y-2.5 text-sm">
        <li><a href="mailto:<?= h(EMAIL_CONTACT) ?>" class="hover:text-white"><?= h(EMAIL_CONTACT) ?></a></li>
        <li><a href="tel:<?= h(PHONE_ENVIGADO) ?>" class="hover:text-white"><?= h(PHONE_ENVIGADO) ?></a></li>
        <li><a href="<?= h(LOBBY_URL) ?>" class="hover:text-white font-medium">Ingreso / Lobby</a></li>
      </ul>
    </div>
  </div>

  <div class="border-t border-white/10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5 flex flex-col sm:flex-row justify-between items-center gap-3 text-xs text-slate-400">
      <span>&copy; <?= date('Y') ?> <?= h(SITE_LEGAL_NAME) ?> — Todos los derechos reservados.</span>
      <span><?= h(SITE_DIAN_LEVEL) ?></span>
    </div>
  </div>
</footer>

<!-- ============================================================ -->
<!-- MODAL: Consulta de DO / Radicado (simulación de tracking)     -->
<!-- ============================================================ -->
<div id="tracking-modal" class="fixed inset-0 z-[70] hidden items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="tracking-title">
  <div class="absolute inset-0 bg-slate-900/60" data-close-modal="tracking-modal"></div>
  <div class="relative bg-white rounded-2xl shadow-2xl max-w-md w-full p-6 sm:p-8">
    <button type="button" class="absolute top-4 right-4 text-slate-400 hover:text-slate-600" data-close-modal="tracking-modal" aria-label="Cerrar">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
    <h3 id="tracking-title" class="text-xl font-bold text-rubi-navy">Consulta tu DO</h3>
    <p class="text-sm text-slate-500 mt-1">Ingresa el número de radicado de tu Orden de Operación.</p>
    <form id="tracking-form" class="mt-6">
      <label for="tracking-code" class="sr-only">Número de radicado</label>
      <input type="text" id="tracking-code" placeholder="Ej. DO-2026-004821" class="w-full rounded-lg border-slate-200 focus:border-rubi-navy focus:ring-rubi-navy text-sm">
      <button type="submit" class="mt-4 w-full bg-rubi-navy text-white font-semibold py-3 rounded-lg hover:bg-rubi-navy2 transition-colors">Consultar</button>
    </form>
    <div id="tracking-result" class="mt-5 hidden"></div>
    <p class="text-xs text-slate-400 mt-4">La consulta completa de radicados requiere iniciar sesión en el <a href="<?= h(LOBBY_URL) ?>" class="text-rubi-emerald font-medium">Lobby</a>.</p>
  </div>
</div>

<!-- ============================================================ -->
<!-- WIDGET FLOTANTE "RUBI" (asistente virtual)                    -->
<!-- ============================================================ -->
<button id="rubi-toggle" type="button" class="fixed bottom-5 right-5 z-[80] w-14 h-14 rounded-full bg-rubi-emerald hover:bg-rubi-emerald2 text-white shadow-xl shadow-emerald-900/20 flex items-center justify-center transition-transform hover:scale-105" aria-haspopup="dialog" aria-controls="rubi-panel" aria-expanded="false" aria-label="Abrir asistente virtual Rubi">
  <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
</button>

<div id="rubi-panel" class="fixed bottom-24 right-5 z-[80] hidden w-[calc(100vw-2.5rem)] max-w-sm bg-white rounded-2xl shadow-2xl ring-1 ring-slate-100 overflow-hidden" role="dialog" aria-labelledby="rubi-title">
  <div class="bg-rubi-navy px-5 py-4 flex items-center justify-between">
    <div class="flex items-center gap-3">
      <span class="w-9 h-9 rounded-full bg-rubi-orange/20 text-rubi-orange flex items-center justify-center font-bold">R</span>
      <div>
        <div id="rubi-title" class="text-white font-semibold text-sm">Rubi · Asistente Rubicam</div>
        <div class="text-emerald-300 text-xs flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>En línea</div>
      </div>
    </div>
    <button type="button" id="rubi-close" class="text-white/70 hover:text-white" aria-label="Cerrar chat">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
  </div>
  <div id="rubi-messages" class="h-80 overflow-y-auto p-4 space-y-3 bg-slate-50 text-sm">
    <div class="bg-white rounded-xl rounded-tl-sm px-4 py-2.5 shadow-sm max-w-[85%]">
      ¡Hola! 👋 Soy Rubi, el asistente virtual de Rubicam. ¿En qué puedo ayudarte con tu operación de comercio exterior?
    </div>
  </div>
  <form id="rubi-form" class="p-3 border-t border-slate-100 flex gap-2">
    <label for="rubi-input" class="sr-only">Escribe tu mensaje</label>
    <input type="text" id="rubi-input" placeholder="Escribe tu mensaje…" class="flex-1 rounded-lg border-slate-200 focus:border-rubi-emerald focus:ring-rubi-emerald text-sm" autocomplete="off">
    <button type="submit" class="bg-rubi-emerald hover:bg-rubi-emerald2 text-white w-10 h-10 rounded-lg flex items-center justify-center shrink-0" aria-label="Enviar mensaje">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
    </button>
  </form>
  <p class="text-[11px] text-slate-400 px-4 pb-3">Conectado vía webhook n8n. Las respuestas pueden tardar unos segundos.</p>
</div>

<script src="<?= ASSETS_URL ?>/js/app.js"></script>
<script>
  window.RUBICAM_CONFIG = {
    csrfToken: <?= json_encode($csrf) ?>,
    chatWebhookUrl: <?= json_encode(CHAT_WEBHOOK_URL) ?>
  };
</script>
</body>
</html>
