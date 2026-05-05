<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Models\Extracurricular;
use App\Services\ImageService;

class AdminExtracurricularController extends Controller
{
    public function index()
    {
        $items = Extracurricular::orderBy('order')->get();
        return Inertia::render('Dashboard/Extracurricular/Index', ['items' => $items]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'image' => 'required', // Removed strict image file validation to allow string paths
            'order' => 'integer',
        ]);

        $data = $request->only(['name', 'description', 'order']);

        if ($request->hasFile('image')) {
            $request->validate(['image' => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048']);
            $data['image'] = ImageService::upload($request->file('image'), 'extracurricular');
        } else {
            // Assume it's a string path from Media Library
            $data['image'] = $request->input('image');
        }

        Extracurricular::create($data);

        return back()->with('success', 'Ekstrakurikuler berhasil ditambahkan!');
    }

    public function update(Request $request, Extracurricular $extracurricular)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'image' => 'nullable', // Allow string or file
            'order' => 'integer',
        ]);

        $data = $request->only(['name', 'description', 'order']);

        if ($request->hasFile('image')) {
            $request->validate(['image' => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048']);

            // Delete old image only if it was a dedicated upload (heuristic)
            if ($extracurricular->image && str_starts_with($extracurricular->image, 'extracurricular/')) {
                ImageService::delete($extracurricular->image);
            }
            $data['image'] = ImageService::upload($request->file('image'), 'extracurricular');
        } elseif ($request->filled('image') && $request->input('image') !== $extracurricular->image) {
            // String path changed (Media Library selection changed)
            if ($extracurricular->image && str_starts_with($extracurricular->image, 'extracurricular/')) {
                ImageService::delete($extracurricular->image);
            }
            $data['image'] = $request->input('image');
        }

        $extracurricular->update($data);

        return back()->with('success', 'Ekstrakurikuler berhasil diperbarui!');
    }

    public function destroy(Extracurricular $extracurricular)
    {
        // Safe delete: only delete if in dedicated folder
        if ($extracurricular->image && str_starts_with($extracurricular->image, 'extracurricular/')) {
            ImageService::delete($extracurricular->image);
        }
        $extracurricular->delete();
        return back()->with('success', 'Ekstrakurikuler berhasil dihapus!');
    }
}
