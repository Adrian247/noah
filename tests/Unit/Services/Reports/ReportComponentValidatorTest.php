<?php

namespace Tests\Unit\Services\Reports;

use App\Services\Reports\ReportComponentValidator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReportComponentValidatorTest extends TestCase
{
    public function test_accepts_paragraph_label_and_divider(): void
    {
        $validator = new ReportComponentValidator;
        $out = $validator->validate([
            [
                'type' => 'paragraph',
                'field' => 'placa',
                'label' => 'Placa',
                'show_field_key' => false,
                'align' => 'left',
            ],
            [
                'type' => 'divider',
                'style' => 'dashed',
                'margin_pt' => 20,
            ],
        ]);

        $this->assertSame('placa', $out[0]['field']);
        $this->assertSame('Placa', $out[0]['label']);
        $this->assertFalse($out[0]['show_field_key']);
        $this->assertSame('dashed', $out[1]['style']);
        $this->assertSame(20, $out[1]['margin_pt']);
    }

    public function test_preserves_color_and_size_pt(): void
    {
        $validator = new ReportComponentValidator;
        $out = $validator->validate([
            [
                'type' => 'title',
                'text' => 'Hola',
                'align' => 'center',
                'color' => '#d97706',
                'size_pt' => 18,
            ],
        ]);

        $this->assertSame('#d97706', $out[0]['color']);
        $this->assertSame(18, $out[0]['size_pt']);
        $this->assertSame('center', $out[0]['align']);
    }

    public function test_rejects_unknown_component_type(): void
    {
        $this->expectException(ValidationException::class);

        (new ReportComponentValidator)->validate([
            ['type' => 'canvas_widget'],
        ]);
    }
}
