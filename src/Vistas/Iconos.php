<?php

/**
 * src/Vistas/Iconos.php — Íconos SVG (trazos 24×24) compartidos por la vista PHP y el JavaScript del panel.
 */

declare(strict_types=1);

namespace Rubicam\Vistas;

final class Iconos
{
    public const TRAZOS = [
        'plus'      => '<path d="M12 5v14M5 12h14"/>',
        'search'    => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'x'         => '<path d="M6 6l12 12M18 6 6 18"/>',
        'refresh'   => '<path d="M20 11a8 8 0 0 0-14.9-3.9M4 4v4h4M4 13a8 8 0 0 0 14.9 3.9M20 20v-4h-4"/>',
        'paperclip' => '<path d="m21 11-8.5 8.5a5 5 0 0 1-7-7L14 3.9a3.5 3.5 0 0 1 5 5l-8.6 8.6a2 2 0 0 1-2.8-2.8L15.5 7"/>',
        'folder'    => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
        'check'     => '<path d="m5 13 4 4L19 7"/>',
        'alert'     => '<path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>',
        'clock'     => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'archive'   => '<path d="M3 4h18v4H3zM5 8v11a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8M10 12h4"/>',
        'download'  => '<path d="M12 4v11m0 0-4-4m4 4 4-4M5 20h14"/>',
        'eye'       => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'upload'    => '<path d="M12 20V9m0 0-4 4m4-4 4 4M5 4h14"/>',
        'logout'    => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 17l5-5-5-5M15 12H3"/>',
        'menu'      => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'pin'       => '<path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/>',
        'grid'      => '<path d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z"/>',
        'flow'      => '<circle cx="6" cy="6" r="2.5"/><circle cx="18" cy="18" r="2.5"/><circle cx="18" cy="6" r="2.5"/><path d="M8.5 6h7M6 8.5v5a4 4 0 0 0 4 4h5.5"/>',
        'export'    => '<path d="M7 17 17 7M9 7h8v8"/>',
        'import'    => '<path d="M17 7 7 17M15 17H7V9"/>',
        'chart'     => '<path d="M4 20V10M10 20V4M16 20v-7M2 20h20"/>',
        'file'      => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6"/>',
        'info'      => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
        'chevron'   => '<path d="m9 6 6 6-6 6"/>',
        'user'      => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
    ];

    public static function svg(string $nombre, string $clases = 'h-4 w-4'): string
    {
        return '<svg class="' . htmlspecialchars($clases, ENT_QUOTES, 'UTF-8') . '" viewBox="0 0 24 24" fill="none" '
            . 'stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" '
            . 'aria-hidden="true">' . (self::TRAZOS[$nombre] ?? '') . '</svg>';
    }
}
