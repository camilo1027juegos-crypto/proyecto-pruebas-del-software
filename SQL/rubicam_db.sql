-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 23-09-2026 a las 23:15:15
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `rubicam_db`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `documentos_do`
--

CREATE TABLE `documentos_do` (
  `id` int(10) UNSIGNED NOT NULL,
  `do_id` int(10) UNSIGNED NOT NULL,
  `nombre_archivo` varchar(255) NOT NULL,
  `ruta_archivo` varchar(255) NOT NULL,
  `tipo_documento` enum('Declaración de Importación','Factura Comercial','Lista de Empaque','RUT','BL/Guía aérea','Otro') NOT NULL,
  `fecha_subida` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `documentos_do`
--

INSERT INTO `documentos_do` (`id`, `do_id`, `nombre_archivo`, `ruta_archivo`, `tipo_documento`, `fecha_subida`) VALUES
(1, 1, 'Informe_Corporativo_Rubicam.docx', 'uploads/MED26IMP0840_1788192337_7be1f846.docx', 'RUT', '2026-08-31 16:05:37');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `procesos_do`
--

CREATE TABLE `procesos_do` (
  `id` int(10) UNSIGNED NOT NULL,
  `do_codigo` varchar(30) NOT NULL,
  `cliente` varchar(150) NOT NULL,
  `nit` varchar(20) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `regimen` enum('Importación','Exportación','Tránsito') NOT NULL,
  `sucursal` varchar(100) NOT NULL,
  `estado` varchar(60) NOT NULL DEFAULT 'En trámite',
  `fecha_apertura` date NOT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `archivo_adjunto` varchar(255) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `procesos_do`
--

INSERT INTO `procesos_do` (`id`, `do_codigo`, `cliente`, `nit`, `email`, `telefono`, `regimen`, `sucursal`, `estado`, `fecha_apertura`, `fecha_vencimiento`, `archivo_adjunto`, `observaciones`, `creado_en`, `actualizado_en`) VALUES
(1, 'MED26IMP0840', 'Textiles del Norte S.A.S.', '900123456-1', 'contacto@textilesdelnorte.com', '3001234567', 'Importación', 'Medellín', 'En trámite', '2026-08-19', '2026-11-17', 'uploads/MED26IMP0840_1788192337_7be1f846.docx', 'Pendiente Formulario 500.', '2026-08-31 14:47:32', '2026-08-31 16:05:37'),
(2, 'CTG26IMP0841', 'Comercializadora Caribe Ltda.', '900654321-2', 'operaciones@caribeltda.com', '3109876543', 'Importación', 'Cartagena', 'Presentada DIAN', '2026-07-17', '2026-10-15', NULL, 'fdggffg', '2026-08-31 14:47:32', '2026-08-31 16:05:52'),
(3, 'CUC26EXP0125', 'Agroindustrial Frontera S.A.S.', '901234567-3', 'exportaciones@agrofrontera.com', '3157894561', 'Exportación', 'Cúcuta', 'Finalizada', '2026-06-12', '2026-09-10', NULL, 'listo', '2026-08-31 14:47:32', '2026-08-31 16:06:05'),
(4, 'BOG26IMP0332', 'Distribuciones Andina S.A.', '900987654-4', 'importaciones@andina.com', '3201112233', 'Importación', 'Bogotá', 'Finalizada', '2026-05-23', '2026-08-21', NULL, 'Proceso cerrado sin novedades.', '2026-08-31 14:47:32', '2026-08-31 14:47:32');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(10) UNSIGNED NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `rol` enum('admin','operador','consulta') NOT NULL DEFAULT 'operador',
  `nombre_completo` varchar(150) NOT NULL,
  `sucursal` varchar(100) NOT NULL DEFAULT 'Cúcuta',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `usuario`, `password_hash`, `rol`, `nombre_completo`, `sucursal`, `activo`, `creado_en`) VALUES
(1, 'admin', '$2b$10$AN0UhGJXBW7mz9VEiB3.X.Dw6L69HKtLQV7pBUYdDbec5K9Mxm/Fu', 'admin', 'Administrador RUBICAM', 'Cúcuta', 1, '2026-08-31 14:47:32');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `documentos_do`
--
ALTER TABLE `documentos_do`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_documentos_do` (`do_id`);

--
-- Indices de la tabla `procesos_do`
--
ALTER TABLE `procesos_do`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `do_codigo` (`do_codigo`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `usuario` (`usuario`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `documentos_do`
--
ALTER TABLE `documentos_do`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `procesos_do`
--
ALTER TABLE `procesos_do`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `documentos_do`
--
ALTER TABLE `documentos_do`
  ADD CONSTRAINT `fk_documentos_do` FOREIGN KEY (`do_id`) REFERENCES `procesos_do` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
