<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

use App\Models\ClassRoom;

class AdminKesiswaanController extends Controller
{
    public function index(Request $request)
    {
        $query = Student::query();

        if ($request->has('grade') && $request->grade !== '') {
            $query->where('grade', $request->grade);
        }

        if ($request->filled('class_room_id')) {
            $query->where('class_room_id', $request->class_room_id);
        }

        $students = $query->with('classRoom')->orderBy('grade')->orderBy('name')->get();
        // Also pass classRooms for filter dropdown if needed
        $classRooms = ClassRoom::orderBy('grade')->orderBy('name')->get();

        return \Inertia\Inertia::render('Dashboard/Kesiswaan/Index', [
            'students' => $students,
            'classRooms' => $classRooms,
            'filters' => $request->only(['grade', 'class_room_id']),
        ]);
    }

    public function create()
    {
        $classRooms = ClassRoom::orderBy('grade')->orderBy('name')->get();
        return \Inertia\Inertia::render('Dashboard/Kesiswaan/Create', [
            'classRooms' => $classRooms
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'nis' => [
                'nullable',
                'string',
                'unique:students,nis',
                'unique:users,nis',
                'unique:users,nip',
                'unique:teachers,nip',
            ],
            'nisn' => 'nullable|string|unique:students,nisn',
            'gender' => 'required|in:L,P',
            'grade' => 'required',
            'class_room_id' => 'nullable|exists:class_rooms,id',
            'status' => 'required|in:active,alumni,transferred',
            'graduation_year' => 'nullable|integer',
            'admission_year' => 'nullable|integer',
            'parent_name' => 'nullable|string|max:255',
            'birth_date' => 'nullable|date',
            'parent_phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'image' => 'nullable', // Allow string or file
            'password' => 'required_with:nis|string|min:6',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $request->validate(['image' => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048']);
            try {
                $imagePath = \App\Services\ImageService::upload($request->file('image'), 'students');
            } catch (\Exception $e) {
                return back()->withErrors(['image' => 'Gagal mengupload gambar.']);
            }
        } elseif ($request->filled('image')) {
            $imagePath = $request->input('image');
        }

        // Create User Account if NIS is provided
        $userId = null;
        if ($request->filled('nis')) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->nis . '@student.mi-alghazali.sch.id', // Dummy email
                'nis' => $request->nis,
                'password' => Hash::make($request->password),
                'role' => 'siswa',
                'must_change_password' => true,
            ]);
            $userId = $user->id;
        }

        Student::create([
            'user_id' => $userId,
            'name' => $request->name,
            'nis' => $request->nis,
            'plain_password' => $request->password, // Save plain password
            'nisn' => $request->nisn,
            'gender' => $request->gender,
            'grade' => $request->grade,
            'class_room_id' => $request->class_room_id,
            'status' => $request->status,
            'graduation_year' => $request->graduation_year,
            'admission_year' => $request->admission_year,
            'birth_date' => $request->birth_date,
            'parent_name' => $request->parent_name,
            'parent_phone' => $request->parent_phone,
            'address' => $request->address,
            'image' => $imagePath,
        ]);

        return redirect()->route('admin.kesiswaan.index')->with('success', 'Data siswa berhasil ditambahkan!');
    }

    public function edit(Student $student)
    {
        $classRooms = ClassRoom::orderBy('grade')->orderBy('name')->get();
        return \Inertia\Inertia::render('Dashboard/Kesiswaan/Edit', [
            'student' => $student,
            'classRooms' => $classRooms
        ]);
    }

    public function update(Request $request, Student $student)
    {
        \Illuminate\Support\Facades\Log::info('Update Student Request:', $request->all());

        $request->validate([
            'name' => 'required|string|max:255',
            'nis' => [
                'nullable',
                'string',
                'unique:students,nis,' . $student->id,
                'unique:users,nis,' . ($student->user_id ?? 'NULL'),
                'unique:users,nip',
                'unique:teachers,nip',
            ],
            'nisn' => 'nullable|string|unique:students,nisn,' . $student->id,
            'gender' => 'required|in:L,P',
            'grade' => 'required',
            'class_room_id' => 'nullable|exists:class_rooms,id',
            'status' => 'required|in:active,alumni,transferred',
            'graduation_year' => 'nullable|integer',
            'admission_year' => 'nullable|integer',
            'parent_name' => 'nullable|string|max:255',
            'birth_date' => 'nullable|date',
            'parent_phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'image' => 'nullable', // Allow string or file
            'password' => 'nullable|string|min:6',
        ]);

        $data = [
            'name' => $request->name,
            'nis' => $request->nis,
            'nisn' => $request->nisn,
            'gender' => $request->gender,
            'grade' => $request->grade,
            'class_room_id' => $request->class_room_id,
            'status' => $request->status,
            'graduation_year' => $request->graduation_year,
            'admission_year' => $request->admission_year,
            'birth_date' => $request->birth_date,
            'parent_name' => $request->parent_name,
            'parent_phone' => $request->parent_phone,
            'address' => $request->address,
        ];

        if ($request->filled('password')) {
            $data['plain_password'] = $request->password;
        }

        if ($request->hasFile('image')) {
            $request->validate(['image' => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048']);
            if ($student->image) {
                \App\Services\ImageService::delete($student->image);
            }
            try {
                $data['image'] = \App\Services\ImageService::upload($request->file('image'), 'students');
            } catch (\Exception $e) {
                return back()->withErrors(['image' => 'Gagal mengupload gambar.']);
            }
        } elseif ($request->filled('image')) {
            $data['image'] = $request->input('image');
        } elseif ($request->has('image') && $request->input('image') === null) {
            // Explicitly set to null if cleared
            if ($student->image) {
                \App\Services\ImageService::delete($student->image);
            }
            $data['image'] = null;
        }

        $student->update($data);

        // Update User if exists or create if just added NIS
        if ($student->user_id) {
            $user = User::find($student->user_id);
            if ($user) {
                $userUpdateData = [
                    'name' => $request->name,
                    'nis' => $request->nis,
                ];

                if ($request->filled('password')) {
                    $userUpdateData['password'] = Hash::make($request->password);
                }

                $user->update($userUpdateData);
            }
        } else if ($request->filled('nis')) {
            // Create User if it didn't exist but NIS is now provided
            $user = User::create([
                'name' => $request->name,
                'email' => $request->nis . '@student.mi-alghazali.sch.id',
                'nis' => $request->nis,
                'password' => Hash::make($request->password ?? 'siswa123'),
                'role' => 'siswa',
                'must_change_password' => true,
            ]);
            $student->update(['user_id' => $user->id]);
        }

        return redirect()->route('admin.kesiswaan.index')->with('success', 'Data siswa berhasil diperbarui!');
    }

    public function destroy(Student $student)
    {
        if ($student->image) {
            \App\Services\ImageService::delete($student->image);
        }
        if ($student->user_id) {
            User::destroy($student->user_id);
        }
        $student->delete();

        return redirect()->route('admin.kesiswaan.index')->with('success', 'Data siswa berhasil dihapus!');
    }

    public function deleteImage(Student $student)
    {
        if ($student->image) {
            \App\Services\ImageService::delete($student->image);
            $student->update(['image' => null]);
            return back()->with('success', 'Foto siswa berhasil dihapus!');
        }
        return back()->with('error', 'Siswa tidak memiliki foto.');
    }

    public function promote()
    {
        // 1. Promote Grade 6 to Alumni
        Student::where('grade', '6')->where('status', 'active')->update([
            'grade' => 'Lulus',
            'status' => 'alumni',
            'graduation_year' => date('Y')
        ]);

        // 2. Promote other grades (5->6, 4->5, etc.) descending to avoid conflict
        $grades = ['5', '4', '3', '2', '1'];
        foreach ($grades as $grade) {
            $nextGrade = (string) ($grade + 1);
            Student::where('grade', $grade)->where('status', 'active')->update([
                'grade' => $nextGrade
            ]);
        }

        return redirect()->route('admin.kesiswaan.index')->with('success', 'Siswa berhasil dinaikkan kelas!');
    }
}
