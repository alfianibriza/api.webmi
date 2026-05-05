<?php

namespace App\Http\Controllers;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class AdminTeacherController extends Controller
{
    public function index()
    {
        $teachers = Teacher::with('user')->latest()->get();
        $groupPhoto = \App\Models\ProfileSection::where('key', 'ptk_group_photo')->first();

        return Inertia::render('Dashboard/Ptk/Index', [
            'teachers' => $teachers,
            'groupPhoto' => $groupPhoto
        ]);
    }

    public function create()
    {
        $classrooms = \App\Models\ClassRoom::orderBy('name')->get();
        $subjects = \App\Models\Subject::orderBy('name')->get();
        $extracurriculars = \App\Models\Extracurricular::orderBy('name')->get();

        return Inertia::render('Dashboard/Ptk/Create', [
            'classrooms' => $classrooms,
            'subjects' => $subjects,
            'extracurriculars' => $extracurriculars,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'nip' => [
                'nullable',
                'string',
                'max:20',
                'unique:teachers,nip',
                'unique:users,nis',
                'unique:students,nis',
            ],
            'gender' => 'required|in:L,P',
            'birth_place' => 'required|string|max:255',
            'birth_date' => 'required|date',
            'address' => 'required|string',
            'position' => 'required|string|max:255',
            'status' => 'required|in:active,inactive',
            'image' => 'nullable', // Allow string or file
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $request->validate(['image' => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048']);
            try {
                $imagePath = \App\Services\ImageService::upload($request->file('image'), 'teachers');
            } catch (\Exception $e) {
                return back()->withErrors(['image' => 'Gagal mengupload gambar.']);
            }
        } elseif ($request->filled('image')) {
            $imagePath = $request->input('image');
        }

        Teacher::create([
            'name' => $request->name,
            'nip' => $request->nip,
            'gender' => $request->gender,
            'birth_place' => $request->birth_place,
            'birth_date' => $request->birth_date,
            'address' => $request->address,
            'position' => $request->position,
            'status' => $request->status,
            'image' => $imagePath,
            // user_id dan plain_password tetap null - akan diisi saat membuat akun di Manajemen Akun
        ]);

        return redirect()->route('admin.ptk.index')->with('success', 'Data PTK berhasil ditambahkan!');
    }

    public function edit(Teacher $teacher)
    {
        return Inertia::render('Dashboard/Ptk/Edit', [
            'teacher' => $teacher
        ]);
    }

    public function update(Request $request, Teacher $teacher)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'nip' => [
                'nullable',
                'string',
                'max:20',
                'unique:users,nip,' . ($teacher->user_id ?? ''),
                'unique:users,nis',
                'unique:students,nis',
            ],
            'gender' => 'required|in:L,P',
            'birth_place' => 'required|string|max:255',
            'birth_date' => 'required|date',
            'address' => 'required|string',
            'position' => 'required|string|max:255',
            'status' => 'required|in:active,inactive',
            'image' => 'nullable', // Allow string or file
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($request, $teacher) {
            $data = [
                'name' => $request->name,
                'nip' => $request->nip,
                'gender' => $request->gender,
                'birth_place' => $request->birth_place,
                'birth_date' => $request->birth_date,
                'address' => $request->address,
                'position' => $request->position,
                'status' => $request->status,
            ];

            if ($request->hasFile('image')) {
                $request->validate(['image' => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048']);
                if ($teacher->image) {
                    \App\Services\ImageService::delete($teacher->image); // Only delete if replacing with new upload
                }
                try {
                    $data['image'] = \App\Services\ImageService::upload($request->file('image'), 'teachers');
                } catch (\Exception $e) {
                    throw new \Exception('Gagal mengupload gambar.');
                }
            } elseif ($request->filled('image')) {
                // If string path provided
                $data['image'] = $request->input('image');
            } elseif ($request->has('image') && $request->input('image') === null) {
                // Explicitly set to null (remove image) if sent as null
                if ($teacher->image) {
                    \App\Services\ImageService::delete($teacher->image);
                }
                $data['image'] = null;
            }

            $teacher->update($data);

            // Also update User if exists
            if ($teacher->user_id) {
                $user = User::find($teacher->user_id);
                if ($user) {
                    $userUpdateData = [
                        'name' => $request->name,
                        'nip' => $request->nip,
                    ];

                    if ($request->filled('password')) {
                        $userUpdateData['password'] = Hash::make($request->password);
                        $teacher->update(['plain_password' => $request->password]);
                    }

                    $user->update($userUpdateData);
                }
            }
        });

        return redirect()->route('admin.ptk.index')->with('success', 'Data PTK berhasil diperbarui!');
    }

    public function destroy(Teacher $teacher)
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($teacher) {
            if ($teacher->image) {
                \App\Services\ImageService::delete($teacher->image);
            }

            // Delete associated user
            if ($teacher->user_id) {
                User::destroy($teacher->user_id);
            }

            $teacher->delete();
        });

        return redirect()->route('admin.ptk.index')->with('success', 'Data PTK dan Akun berhasil dihapus!');
    }

    public function deleteImage(Teacher $teacher)
    {
        if ($teacher->image) {
            \App\Services\ImageService::delete($teacher->image);
            $teacher->update(['image' => null]);
            return back()->with('success', 'Foto PTK berhasil dihapus!');
        }
        return back()->with('error', 'PTK tidak memiliki foto.');
    }

    public function uploadGroupPhoto(Request $request)
    {
        $request->validate([
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'existing_path' => 'nullable|string',
        ]);

        if (!$request->hasFile('image') && !$request->filled('existing_path')) {
            return back()->withErrors(['image' => 'Silakan pilih gambar.']);
        }

        $section = \App\Models\ProfileSection::firstOrNew(['key' => 'ptk_group_photo']);

        // If we are uploading a NEW file
        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($section->image) {
                \App\Services\ImageService::delete($section->image);
            }
            try {
                $path = \App\Services\ImageService::upload($request->file('image'), 'school');
                $section->image = $path;
            } catch (\Exception $e) {
                return back()->withErrors(['image' => 'Gagal mengupload gambar.']);
            }
        }
        // If we are using an EXISTING file from library
        elseif ($request->filled('existing_path')) {
            $section->image = $request->existing_path;
        }

        $section->title = 'Foto Bersama PTK';
        $section->content = 'Foto Bersama Guru dan Staff'; // Default content
        $section->save();

        return back()->with('success', 'Foto Bersama berhasil diupdate!');
    }
}