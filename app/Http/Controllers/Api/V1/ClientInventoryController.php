<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Client;
use App\Models\Site;
use App\Services\Catalog\ClientInventorySyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ClientInventoryController extends Controller
{
    public function __construct(
        private readonly ClientInventorySyncService $sync,
    ) {}

    public function index(Client $client): JsonResponse
    {
        $items = Asset::query()
            ->where('client_id', $client->id)
            ->with(['site', 'catalogItem.equipmentType', 'baseCatalogItem'])
            ->orderBy('tag')
            ->get()
            ->map(fn (Asset $asset) => $this->format($asset));

        return response()->json(['data' => $items]);
    }

    public function store(Request $request, Client $client): JsonResponse
    {
        $data = $request->validate([
            'site_id' => [
                'required',
                Rule::exists('sites', 'id')->where(fn ($q) => $q->where('client_id', $client->id)),
            ],
            'catalog_item_id' => ['required', 'exists:catalog_items,id'],
            'tag' => ['required', 'string', 'max:64'],
            'serial_number' => ['nullable', 'string', 'max:128'],
            'location_label' => ['nullable', 'string', 'max:128'],
            'ocr_plate_text' => ['nullable', 'string', 'max:512'],
            'status' => ['nullable', 'string', 'max:32'],
        ]);

        $asset = Asset::query()->create([
            ...$data,
            'client_id' => $client->id,
            'base_catalog_item_id' => $data['catalog_item_id'],
            'sync_mode' => 'linked',
        ]);

        return response()->json(['data' => $this->format($asset->load(['site', 'catalogItem.equipmentType', 'baseCatalogItem']))], 201);
    }

    public function update(Request $request, Client $client, Asset $asset): JsonResponse
    {
        $this->assertClientAsset($client, $asset);

        $data = $request->validate([
            'site_id' => [
                'sometimes',
                Rule::exists('sites', 'id')->where(fn ($q) => $q->where('client_id', $client->id)),
            ],
            'tag' => ['sometimes', 'string', 'max:64'],
            'serial_number' => ['nullable', 'string', 'max:128'],
            'location_label' => ['nullable', 'string', 'max:128'],
            'ocr_plate_text' => ['nullable', 'string', 'max:512'],
            'status' => ['nullable', 'string', 'max:32'],
        ]);

        $asset->update($data);

        return response()->json(['data' => $this->format($asset->fresh(['site', 'catalogItem.equipmentType', 'baseCatalogItem']))]);
    }

    public function updateImage(Request $request, Client $client, Asset $asset): JsonResponse
    {
        $this->assertClientAsset($client, $asset);

        $request->validate([
            'image' => ['required', 'image', 'max:4096', 'mimes:jpg,jpeg,png,webp'],
        ]);

        if ($asset->image_path && Storage::disk('public')->exists($asset->image_path)) {
            Storage::disk('public')->delete($asset->image_path);
        }

        $path = $request->file('image')->store('client-inventory/'.$client->id, 'public');
        $asset->update(['image_path' => $path]);

        return response()->json(['data' => $this->format($asset->fresh(['site', 'catalogItem.equipmentType', 'baseCatalogItem']))]);
    }

    public function detachCatalog(Client $client, Asset $asset): JsonResponse
    {
        $this->assertClientAsset($client, $asset);

        $this->sync->detachToCustomCopy($asset);

        return response()->json([
            'data' => $this->format($asset->fresh(['site', 'catalogItem.equipmentType', 'baseCatalogItem'])),
            'message' => 'Se creó una copia personalizada del artículo. Ya no se sincronizará automáticamente con el catálogo.',
        ]);
    }

    public function resetCatalog(Client $client, Asset $asset): JsonResponse
    {
        $this->assertClientAsset($client, $asset);

        $this->sync->resetToCatalogBase($asset);

        return response()->json([
            'data' => $this->format($asset->fresh(['site', 'catalogItem.equipmentType', 'baseCatalogItem'])),
            'message' => 'Artículo restablecido desde el catálogo base.',
        ]);
    }

    public function destroy(Client $client, Asset $asset): JsonResponse
    {
        $this->assertClientAsset($client, $asset);

        if ($asset->routines()->exists()) {
            return response()->json(['message' => 'No se puede eliminar: hay servicios vinculados.'], 422);
        }

        $asset->delete();

        return response()->json(null, 204);
    }

    private function assertClientAsset(Client $client, Asset $asset): void
    {
        if ($asset->client_id !== $client->id) {
            abort(404);
        }
    }

  /**
     * @return array<string, mixed>
     */
    private function format(Asset $asset): array
    {
        $imageUrl = null;
        if ($asset->image_path && Storage::disk('public')->exists($asset->image_path)) {
            $imageUrl = Storage::disk('public')->url($asset->image_path);
        }

        return [
            'id' => $asset->id,
            'client_id' => $asset->client_id,
            'site_id' => $asset->site_id,
            'site' => $asset->site,
            'catalog_item_id' => $asset->catalog_item_id,
            'base_catalog_item_id' => $asset->base_catalog_item_id,
            'catalog_item' => $asset->catalogItem,
            'base_catalog_item' => $asset->baseCatalogItem,
            'tag' => $asset->tag,
            'serial_number' => $asset->serial_number,
            'location_label' => $asset->location_label,
            'ocr_plate_text' => $asset->ocr_plate_text,
            'sync_mode' => $asset->sync_mode,
            'detached_at' => $asset->detached_at?->toIso8601String(),
            'status' => $asset->status,
            'image_url' => $imageUrl,
        ];
    }
}
