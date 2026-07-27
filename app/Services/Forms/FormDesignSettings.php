<?php

namespace App\Services\Forms;

use App\Models\Company;
use App\Models\FormOptionCatalog;
use App\Support\CurrentCompany;

class FormDesignSettings
{
    public const DEFAULT_MAX_IMAGE_KB = 2048;

    /** @var list<string> */
    public const DEFAULT_IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(
        private CurrentCompany $currentCompany,
    ) {}

    /**
     * @return array{max_image_size_kb: int, allowed_image_mimes: list<string>}
     */
    public function forCurrentCompany(): array
    {
        $companyId = $this->currentCompany->id();
        if ($companyId === null) {
            return $this->defaults();
        }

        $company = Company::query()->find($companyId);
        if ($company === null) {
            return $this->defaults();
        }

        return $this->fromCompany($company);
    }

    /**
     * @return array{max_image_size_kb: int, allowed_image_mimes: list<string>}
     */
    public function fromCompany(Company $company): array
    {
        $mimes = $company->form_allowed_image_mimes;
        if (! is_array($mimes) || $mimes === []) {
            $mimes = self::DEFAULT_IMAGE_MIMES;
        }

        return [
            'max_image_size_kb' => (int) ($company->form_max_image_size_kb ?: self::DEFAULT_MAX_IMAGE_KB),
            'allowed_image_mimes' => array_values($mimes),
        ];
    }

    /**
     * @return array{max_image_size_kb: int, allowed_image_mimes: list<string>}
     */
    public function defaults(): array
    {
        return [
            'max_image_size_kb' => self::DEFAULT_MAX_IMAGE_KB,
            'allowed_image_mimes' => self::DEFAULT_IMAGE_MIMES,
        ];
    }

    /**
     * @return list<array{id: int, name: string, slug: string, options: list<array{value: string, label: string}>}>
     */
    public function optionCatalogsForCurrentCompany(): array
    {
        $companyId = $this->currentCompany->id();
        if ($companyId === null) {
            return [];
        }

        return FormOptionCatalog::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get()
            ->map(fn (FormOptionCatalog $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'options' => $c->options ?? [],
            ])
            ->all();
    }
}
