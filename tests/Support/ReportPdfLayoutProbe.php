<?php

namespace Tests\Support;

use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Comprueba layout de PDF de reportes (páginas, márgenes aproximados, portada a sangre).
 */
final class ReportPdfLayoutProbe
{
    public function __construct(
        public readonly int $pageCount,
        /** @var list<array{page:int,x:float,y:float,width:float,height:float,text:string}> */
        public readonly array $textBoxes,
        public readonly string $rawPdf,
    ) {}

    public static function fromHtml(string $html, bool $enablePhp = false): self
    {
        $pdf = Pdf::loadHTML($html)->setPaper('a4');
        $dom = $pdf->getDomPDF();
        $dom->set_option('isPhpEnabled', $enablePhp);
        $dom->render();
        $raw = $pdf->output();
        $pageCount = $dom->getCanvas()->get_page_count();

        $tmp = tempnam(sys_get_temp_dir(), 'noah-pdf-');
        if ($tmp === false) {
            throw new \RuntimeException('No se pudo crear archivo temporal.');
        }
        file_put_contents($tmp, $raw);
        $boxes = self::extractTextBoxes($tmp);
        @unlink($tmp);

        return new self($pageCount, $boxes, $raw);
    }

    /**
     * @return list<array{page:int,x:float,y:float,width:float,height:float,text:string}>
     */
    private static function extractTextBoxes(string $pdfPath): array
    {
        $out = tempnam(sys_get_temp_dir(), 'noah-bbox-');
        if ($out === false) {
            return [];
        }
        $xmlPath = $out.'.xml';
        exec('pdftotext -bbox '.escapeshellarg($pdfPath).' '.escapeshellarg($xmlPath).' 2>/dev/null');
        if (! is_readable($xmlPath)) {
            @unlink($out);

            return [];
        }
        $xml = simplexml_load_file($xmlPath);
        @unlink($xmlPath);
        @unlink($out);
        if ($xml === false) {
            return [];
        }
        $boxes = [];
        $pageNum = 0;
        foreach ($xml->body->doc->page ?? [] as $page) {
            $pageNum++;
            foreach ($page->word ?? [] as $word) {
                $attrs = $word->attributes();
                if ($attrs === null) {
                    continue;
                }
                $text = trim((string) $word);
                if ($text === '') {
                    continue;
                }
                $boxes[] = [
                    'page' => $pageNum,
                    'x' => (float) $attrs['xMin'],
                    'y' => (float) $attrs['yMin'],
                    'width' => (float) $attrs['xMax'] - (float) $attrs['xMin'],
                    'height' => (float) $attrs['yMax'] - (float) $attrs['yMin'],
                    'text' => $text,
                ];
            }
        }

        return $boxes;
    }

    public function minXOnPage(int $page): ?float
    {
        $xs = array_map(
            fn (array $b) => $b['x'],
            array_filter($this->textBoxes, fn (array $b) => $b['page'] === $page),
        );

        return $xs === [] ? null : min($xs);
    }

    public function pageText(int $page): string
    {
        $parts = [];
        foreach ($this->textBoxes as $box) {
            if ($box['page'] === $page) {
                $parts[] = $box['text'];
            }
        }

        return implode(' ', $parts);
    }

    public function hasTextOnPage(int $page, string $needle): bool
    {
        return str_contains($this->pageText($page), $needle);
    }

    public function bboxAvailable(): bool
    {
        return $this->textBoxes !== [];
    }
}
