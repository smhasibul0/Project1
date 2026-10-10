<?php

namespace App\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response;

/**
 * A printable page as a PDF file to download — the same view the browser prints,
 * rendered in print mode with `$pdf` set, so the two never drift apart.
 *
 * The PDF fonts have no taka sign, so every ৳ is drawn in a small font cut from
 * GNU FreeSans (resources/fonts/FreeSans-Taka.ttf, GPL-3+ with the font exception)
 * that holds just that one character.
 */
class PdfDocument
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function download(string $view, array $data, string $filename): Response
    {
        // dompdf keeps the metrics of the fonts it loads here.
        File::ensureDirectoryExists(storage_path('fonts'));

        $html = static::withTakaSign(view($view, $data + ['pdf' => true])->render());

        return Pdf::loadHTML($html)
            ->setPaper('a4')
            ->setOption(['default_media_type' => 'print', 'default_font' => 'DejaVu Sans'])
            ->download($filename);
    }

    private static function withTakaSign(string $html): string
    {
        $html = str_replace('৳', '<span class="taka-sign">৳</span>', $html);

        $font = resource_path('fonts/FreeSans-Taka.ttf');
        $faces = collect(['normal', 'bold'])->crossJoin(['normal', 'italic'])
            ->map(fn (array $style) => "@font-face { font-family: 'TakaSign'; src: url('{$font}') format('truetype'); font-weight: {$style[0]}; font-style: {$style[1]}; }")
            ->implode("\n");

        return str_replace('</head>', "<style>\n{$faces}\n.taka-sign { font-family: 'TakaSign'; font-size: 1.3em; line-height: 1; }\n</style>\n</head>", $html);
    }
}
