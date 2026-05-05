<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sarpras;
use App\Services\ImageService;
use Inertia\Inertia;

class AdminSarprasController extends Controller
{
    public function index()
    {
        $sarpras = Sarpras::orderBy('order')->orderBy('created_at', 'desc')->get();
        return Inertia::render('Dashboard/Sarpras/Index', [
            'sarpras' => $sarpras
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable', // Allow string from Media Library
            'order' => 'integer',
        ]);

        $data = $request->only(['name', 'description', 'order']);

        // Default values for legacy columns
        $data['category'] = 'Lainnya';
        $data['quantity'] = 1;
        $data['condition'] = 'Baik';

        if ($request->hasFile('image')) {
            $request->validate(['image' => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048']);
            $data['image'] = ImageService::upload($request->file('image'), 'sarpras');
        } elseif ($request->filled('image')) {
            $data['image'] = $request->input('image');
        }

        Sarpras::create($data);

        return back()->with('success', 'Sarpras berhasil ditambahkan!');
    }

    public function update(Request $request, Sarpras $sarpras)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'image' => 'nullable',
            'order' => 'integer',
        ]);

        $data = $request->only(['name', 'description', 'order']);

        if ($request->hasFile('image')) {
            $request->validate(['image' => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048']);
            // Safe delete old image
            if ($sarpras->image && str_starts_with($sarpras->image, 'sarpras/')) {
                ImageService::delete($sarpras->image);
            }
            $data['image'] = ImageService::upload($request->file('image'), 'sarpras');
        } elseif ($request->filled('image') && $request->input('image') !== $sarpras->image) {
            if ($sarpras->image && str_starts_with($sarpras->image, 'sarpras/')) {
                ImageService::delete($sarpras->image);
            }
            $data['image'] = $request->input('image');
        }

        $sarpras->update($data);

        return back()->with('success', 'Sarpras berhasil diperbarui!');
    }

    public function destroy($id)
    {
        $sarpras = Sarpras::findOrFail($id);
        if ($sarpras->image && str_starts_with($sarpras->image, 'sarpras/')) {
            ImageService::delete($sarpras->image);
        }
        $sarpras->delete();
        return back()->with('success', 'Sarpras berhasil dihapus!');
    }
}
