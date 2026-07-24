<?php

namespace Tests\Feature\Api;

use App\Models\CatalogItem;
use App\Models\Company;
use App\Models\FormDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogCrudApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_catalog_item(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@noah.local')->first();
        $company = Company::query()->first();
        $item = CatalogItem::query()->first();
        $token = $admin->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->putJson("/api/v1/catalog/items/{$item->id}", ['name' => 'Compresor actualizado'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Compresor actualizado');
    }
}
