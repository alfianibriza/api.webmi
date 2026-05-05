<?php

namespace App\Http\Controllers;

use App\Models\Achievement;
use App\Services\ImageService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminAchievementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $achievements = Achievement::orderBy('date', 'desc')->get();
        return Inertia::render('Dashboard/Achievement/Index', [
            'achievements' => $achievements
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('Dashboard/Achievement/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'rank' => 'nullable|string|max:255',
            'level' => 'required|in:Kecamatan,Kabupaten,Provinsi',
            'description' => 'nullable|string',
            'date' => 'required|date',
            'image' => 'nullable', // Allow string from Media Library
        ]);

        $data = $request->only(['title', 'rank', 'level', 'description', 'date']);

        if ($request->hasFile('image')) {
            $request->validate(['image' => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048']);
            $data['image'] = ImageService::upload($request->file('image'), 'achievements');
        } elseif ($request->filled('image')) {
            $data['image'] = $request->input('image');
        }

        Achievement::create($data);

        return redirect()->route('admin.achievement.index')->with('success', 'Data prestasi berhasil ditambahkan!');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Achievement $achievement)
    {
        return Inertia::render('Dashboard/Achievement/Edit', [
            'achievement' => $achievement
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Achievement $achievement)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'rank' => 'nullable|string|max:255',
            'level' => 'required|in:Kecamatan,Kabupaten,Provinsi',
            'description' => 'nullable|string',
            'date' => 'required|date',
            'image' => 'nullable', // Allow string from Media Library
        ]);

        $data = $request->only(['title', 'rank', 'level', 'description', 'date']);

        if ($request->hasFile('image')) {
            $request->validate(['image' => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048']);
            // Safe delete old image
            if ($achievement->image && str_starts_with($achievement->image, 'achievements/')) {
                ImageService::delete($achievement->image);
            }
            $data['image'] = ImageService::upload($request->file('image'), 'achievements');
        } elseif ($request->filled('image')) {
            // If string path provided (Media Library)
            $data['image'] = $request->input('image');
        } elseif ($request->has('image') && $request->input('image') === null) {
            // Explicitly set to null (remove image) if sent as null
            if ($achievement->image && str_starts_with($achievement->image, 'achievements/')) {
                ImageService::delete($achievement->image);
            }
            $data['image'] = null;
        }

        $achievement->update($data);

        return redirect()->route('admin.achievement.index')->with('success', 'Data prestasi berhasil diperbarui!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Achievement $achievement)
    {
        if ($achievement->image && str_starts_with($achievement->image, 'achievements/')) {
            ImageService::delete($achievement->image);
        }
        $achievement->delete();

        return redirect()->back()->with('success', 'Data prestasi berhasil dihapus!');
    }
}
