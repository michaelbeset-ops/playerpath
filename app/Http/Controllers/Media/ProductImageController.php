<?php

namespace App\Http\Controllers\Media;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\Media\ProductImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * De afbeelding bij een aanbod. Optioneel: een aanbod zonder afbeelding
 * staat gewoon zonder plaatje in de shop. Alleen wie het aanbod mag
 * bewerken (de eigenaar).
 */
class ProductImageController extends Controller
{
    public function __construct(protected ProductImage $afbeeldingen) {}

    public function store(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $request->validate([
            'photo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
        ], [
            'photo.required' => 'Kies een afbeelding.',
            'photo.image' => 'Kies een afbeelding.',
            'photo.mimes' => 'Gebruik een png, jpg of webp.',
            'photo.max' => 'De afbeelding mag hoogstens 5 MB groot zijn.',
        ]);

        $this->afbeeldingen->store($product, $request->file('photo'));

        return back()->with('status', 'De afbeelding is opgeslagen.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $this->afbeeldingen->delete($product);

        return back()->with('status', 'De afbeelding is verwijderd.');
    }
}
