<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Http\Request;

class AdminScheduleController extends Controller
{
    public function classSchedules()
    {
        $schedules = \App\Models\Schedule::where('type', 'class')->with('classRoom')->latest()->get();
        $classRooms = \App\Models\ClassRoom::all();

        return Inertia::render('Dashboard/Schedule/Class', [
            'schedules' => $schedules,
            'classRooms' => $classRooms
        ]);
    }

    public function ptsSchedules()
    {
        $schedules = \App\Models\Schedule::where('type', 'pts')->latest()->get();
        return Inertia::render('Dashboard/Schedule/PTS', [
            'schedules' => $schedules
        ]);
    }

    public function pasSchedules()
    {
        $schedules = \App\Models\Schedule::where('type', 'pas')->latest()->get();
        return Inertia::render('Dashboard/Schedule/PAS', [
            'schedules' => $schedules
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|in:class,pts,pas',
            'file' => 'required', // Allow string or file
            'class_room_id' => 'nullable|exists:class_rooms,id',
            'description' => 'nullable|string'
        ]);

        $path = null;
        if ($request->hasFile('file')) {
            $request->validate(['file' => 'mimes:pdf,jpg,jpeg,png|max:2048']);
            $storedPath = $request->file('file')->store('schedules', 'public');
            $path = '/storage/' . $storedPath;
        } elseif ($request->filled('file')) {
            // Assume string from Media Library (e.g., 'uploads/filename.ext')
            // Ensure consistency with /storage/ prefix
            $inputPath = $request->input('file');
            // If inputPath doesn't start with /storage/, add it (assuming it's a relative path from storage root)
            // But Media Library paths usually are relative to storage root (e.g. 'uploads/xx').
            // The frontend might send 'uploads/xx'.
            // Controller expects '/storage/uploads/xx' in database.
            $path = str_starts_with($inputPath, '/storage/') ? $inputPath : '/storage/' . $inputPath;
        }

        \App\Models\Schedule::create([
            'title' => $request->title,
            'type' => $request->type,
            'file_path' => $path,
            'class_room_id' => $request->class_room_id,
            'description' => $request->description,
        ]);

        return redirect()->back()->with('success', 'Jadwal berhasil ditambahkan.');
    }

    public function destroy(\App\Models\Schedule $schedule)
    {
        if ($schedule->file_path) {
            $path = str_replace('/storage/', '', $schedule->file_path);
            // Safe delete: only if in 'schedules/' folder
            if (str_starts_with($path, 'schedules/') && \Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($path);
            }
        }

        $schedule->delete();
        return redirect()->back()->with('success', 'Jadwal berhasil dihapus.');
    }
}
