<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Models\PhilosophyItem;
use App\Services\ImageService;

class AdminPhilosophyController extends Controller
{
    public function index()
    {
        $items = PhilosophyItem::orderBy('order')->get();
        return Inertia::render('Dashboard/Philosophy/Index', ['items' => $items]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'image' => 'required', // Allow string
            'order' => 'integer',
        ]);

        $data = $request->only(['title', 'description', 'order']);

        if ($request->hasFile('image')) {
            $request->validate(['image' => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048']);
            $data['image'] = ImageService::upload($request->file('image'), 'philosophy');
        } else {
            $data['image'] = $request->input('image');
        }

        PhilosophyItem::create($data);

        return back()->with('success', 'Filosofi berhasil ditambahkan!');
    }

    public function update(Request $request, PhilosophyItem $philosophy)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'image' => 'nullable',
            'order' => 'integer',
        ]);

        $data = $request->only(['title', 'description', 'order']);

        if ($request->hasFile('image')) {
            $request->validate(['image' => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048']);
            if ($philosophy->image && str_starts_with($philosophy->image, 'philosophy/')) {
                ImageService::delete($philosophy->image);
            }
            $data['image'] = ImageService::upload($request->file('image'), 'philosophy');
        } elseif ($request->filled('image') && $request->input('image') !== $philosophy->image) {
            if ($philosophy->image && str_starts_with($philosophy->image, 'philosophy/')) {
                ImageService::delete($philosophy->image);
            }
            $data['image'] = $request->input('image');
        }

        $philosophy->update($data);

        return back()->with('success', 'Filosofi berhasil diperbarui!');
    }

    public function destroy(PhilosophyItem $philosophy)
    {
        if ($philosophy->image && str_starts_with($philosophy->image, 'philosophy/')) {
            ImageService::delete($philosophy->image);
        }
        $philosophy->delete();
        return back()->with('success', 'Filosofi berhasil dihapus!');
    }
}
