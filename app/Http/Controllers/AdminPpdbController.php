<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Ppdb;
use App\Models\PpdbInfo;
use Illuminate\Support\Facades\Storage;

class AdminPpdbController extends Controller
{
    // ... existing ...

    public function editInfo()
    {
        $info = PpdbInfo::first();
        if (!$info) {
            $info = PpdbInfo::create([
                'title' => 'Penerimaan Peserta Didik Baru',
                'description' => 'Informasi pendaftaran belum diatur.',
            ]);
        }
        return \Inertia\Inertia::render('Dashboard/Ppdb/EditInfo', [
            'info' => $info
        ]);
    }

    public function updateInfo(Request $request)
    {
        $request->validate([
            'title' => 'required|string',
            'description' => 'required|string',
            'image' => 'nullable', // Allow string
            'brochure' => 'nullable', // Allow string
        ]);

        $info = PpdbInfo::first(); // Assumes singleton

        $data = [
            'title' => $request->title,
            'description' => $request->description,
        ];

        // Handle Image
        if ($request->hasFile('image')) {
            $request->validate(['image' => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048']);
            if ($info->image && str_starts_with($info->image, 'ppdb-info/')) {
                \App\Services\ImageService::delete($info->image);
            }
            try {
                $data['image'] = \App\Services\ImageService::upload($request->file('image'), 'ppdb-info');
            } catch (\Exception $e) {
                return back()->withErrors(['image' => 'Gagal mengupload gambar.']);
            }
        } elseif ($request->filled('image') && $request->input('image') !== $info->image) {
            if ($info->image && str_starts_with($info->image, 'ppdb-info/')) {
                \App\Services\ImageService::delete($info->image);
            }
            $data['image'] = $request->input('image');
        }

        // Handle Brochure
        if ($request->hasFile('brochure')) {
            $request->validate(['brochure' => 'file|mimes:pdf,jpg,png,jpeg|max:5120']);
            if ($info->brochure_link && str_starts_with($info->brochure_link, 'ppdb-info/')) {
                Storage::disk('public')->delete($info->brochure_link);
            }
            $data['brochure_link'] = $request->file('brochure')->store('ppdb-info', 'public');
        } elseif ($request->filled('brochure') && $request->input('brochure') !== $info->brochure_link) {
            // Note: The frontend might send 'brochure' or 'brochure_link' depending on how useForm is set up.
            // Let's assume input name is 'brochure' mapping to column 'brochure_link'
            if ($info->brochure_link && str_starts_with($info->brochure_link, 'ppdb-info/')) {
                Storage::disk('public')->delete($info->brochure_link);
            }
            $data['brochure_link'] = $request->input('brochure');
        }

        $info->update($data);

        return redirect()->route('admin.ppdb.index')->with('success', 'Informasi PPDB berhasil diperbarui!');
    }

    public function index()
    {
        $ppdb = Ppdb::latest()->get();
        return \Inertia\Inertia::render('Dashboard/Ppdb/Index', [
            'ppdb' => $ppdb
        ]);
    }

    public function create()
    {
        return \Inertia\Inertia::render('Dashboard/Ppdb/Create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'nisn' => 'nullable|string',
            'gender' => 'required|in:L,P',
            'birth_place' => 'required|string',
            'birth_date' => 'required|date',
            'parent_name' => 'required|string',
            'phone' => 'nullable|string',
            'address' => 'required|string',
            'status' => 'required|in:pending,accepted,rejected',
        ]);

        Ppdb::create($request->all());

        return redirect()->route('admin.ppdb.index')->with('success', 'Data pendaftar berhasil ditambahkan!');
    }
}
