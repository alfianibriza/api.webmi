<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\ProfileSection;
use App\Services\ImageService;

class AdminProfileController extends Controller
{
    public function index()
    {
        // Default redirect to first section or list
        return redirect()->route('admin.profile-school.edit', 'sejarah');
    }

    public function edit($key)
    {
        $section = ProfileSection::firstOrCreate(
            ['key' => $key],
            ['title' => ucfirst(str_replace('_', ' ', $key)), 'content' => 'Content pending...']
        );

        return Inertia::render('Dashboard/Profile/Edit', [
            'section' => $section,
            'sections_list' => ['sejarah', 'proker_kepala', 'visi', 'misi', 'ekstrakurikuler', 'prestasi']
        ]);
    }

    public function update(Request $request, $key)
    {
        $section = ProfileSection::where('key', $key)->firstOrFail();

        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'image' => 'nullable', // Allow string or file
        ]);

        $data = $request->only(['title', 'content']);

        if ($request->hasFile('image')) {
            $request->validate(['image' => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048']);
            // Delete old image
            if ($section->image) {
                ImageService::delete($section->image);
            }
            // Upload new image
            $data['image'] = ImageService::upload($request->file('image'), 'profile');
        } elseif ($request->filled('image')) {
            $data['image'] = $request->input('image');
        } elseif ($request->has('image') && $request->input('image') === null) {
            // Explicitly set to null if cleared
            if ($section->image) {
                ImageService::delete($section->image);
            }
            $data['image'] = null;
        }

        $section->update($data);

        return redirect()->route('admin.profile-school.edit', $key)->with('success', 'Profil berhasil diperbarui!');
    }
}
