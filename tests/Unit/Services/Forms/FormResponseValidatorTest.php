<?php

namespace Tests\Unit\Services\Forms;

use App\Models\Company;
use App\Models\FormOptionCatalog;
use App\Services\Forms\FormDesignSettings;
use App\Services\Forms\FormResponseValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FormResponseValidatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_required_field_must_be_present(): void
    {
        $validator = app(FormResponseValidator::class);
        $schema = [
            'sections' => [
                [
                    'fields' => [
                        ['key' => 'obs', 'type' => 'text', 'label' => 'Observación', 'required' => true],
                    ],
                ],
            ],
        ];

        $this->expectException(ValidationException::class);
        $validator->validate($schema, [], 1);
    }

    public function test_select_value_must_belong_to_catalog(): void
    {
        $company = Company::query()->create(['name' => 'Test Co']);
        $catalog = FormOptionCatalog::query()->create([
            'company_id' => $company->id,
            'name' => 'Estados',
            'slug' => 'estados',
            'options' => [['value' => 'ok', 'label' => 'OK']],
        ]);

        $validator = app(FormResponseValidator::class);
        $schema = [
            'sections' => [
                [
                    'fields' => [
                        [
                            'key' => 'estado',
                            'type' => 'select',
                            'label' => 'Estado',
                            'option_catalog_id' => $catalog->id,
                        ],
                    ],
                ],
            ],
        ];

        $this->expectException(ValidationException::class);
        $validator->validate($schema, ['estado' => 'invalid'], $company->id);
    }

    public function test_photo_gallery_respects_max_and_captions(): void
    {
        $validator = app(FormResponseValidator::class);
        $schema = [
            'sections' => [
                [
                    'fields' => [
                        [
                            'key' => 'fotos',
                            'type' => 'photo',
                            'label' => 'Fotos',
                            'allow_multiple' => true,
                            'max_images' => 2,
                            'caption_enabled' => true,
                            'caption_required' => true,
                        ],
                    ],
                ],
            ],
        ];

        $this->expectException(ValidationException::class);
        $validator->validate($schema, [
            'fotos' => [
                ['path' => 'evidence/a.jpg'],
                ['path' => 'evidence/b.jpg', 'caption' => ''],
            ],
        ], 1);
    }
}
