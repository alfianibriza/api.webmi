<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('nis', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('name')->paginate(10);

        return \Inertia\Inertia::render('Dashboard/User/Index', [
            'users' => $users,
            'filters' => $request->only(['role', 'search']),
        ]);
    }

    public function create()
    {
        $unregisteredTeachers = \App\Models\Teacher::whereNull('user_id')
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'nip']);

        $unregisteredStudents = \App\Models\Student::whereNull('user_id')
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'nis', 'nisn']);

        return \Inertia\Inertia::render('Dashboard/User/Create', [
            'unregisteredTeachers' => $unregisteredTeachers,
            'unregisteredStudents' => $unregisteredStudents,
        ]);
    }

    /**
     * Get list of teachers without user account (for API)
     */
    public function getUnregisteredTeachers()
    {
        $teachers = \App\Models\Teacher::whereNull('user_id')
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'nip']);

        return response()->json($teachers);
    }

    /**
     * Get list of students without user account (for API)
     */
    public function getUnregisteredStudents()
    {
        $students = \App\Models\Student::whereNull('user_id')
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'nis', 'nisn']);

        return response()->json($students);
    }

    public function store(Request $request)
    {
        // Debug Request
        \Illuminate\Support\Facades\Log::info('Store User Request:', $request->all());

        // Normalize role
        if ($request->has('role')) {
            $request->merge(['role' => strtolower(trim($request->role))]);
        }

        // Validate based on role
        $rules = [
            'role' => 'required|string',
            'password' => 'required|string|min:6',
        ];

        // Admin role requires manual name and email input
        if ($request->role === 'admin') {
            $rules['name'] = 'required|string|max:255';
            $rules['email'] = 'required|string|email|max:255|unique:users';
        } elseif ($request->role === 'wali_murid') {
            // Wali murid requires name and student_id to link
            $rules['name'] = 'required|string|max:255';
            $rules['student_id'] = 'required|integer|exists:students,id';
        } else {
            // Guru and Siswa require person_id (teacher_id or student_id)
            $rules['person_id'] = 'required|integer';
        }

        $request->validate($rules);

        \Illuminate\Support\Facades\DB::transaction(function () use ($request) {
            if ($request->role === 'admin') {
                // Create admin account manually
                User::create([
                    'name' => $request->name,
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                    'plain_password' => $request->password,
                    'role' => 'admin',
                ]);
            } elseif ($request->role === 'guru') {
                // Create account from existing teacher data
                $teacher = \App\Models\Teacher::findOrFail($request->person_id);

                // Check if NIP is already used
                if ($teacher->nip && User::where('nip', $teacher->nip)->exists()) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'nip' => ['NIP ' . $teacher->nip . ' sudah digunakan oleh akun lain. Silakan cek data user.'],
                    ]);
                }

                // Generate email from NIP or name
                $baseEmail = $teacher->nip ?: strtolower(str_replace(' ', '.', $teacher->name));
                $email = $baseEmail . '@mi-alghazali.sch.id';
                $counter = 1;
                while (User::where('email', $email)->exists()) {
                    $email = $baseEmail . $counter . '@mi-alghazali.sch.id';
                    $counter++;
                }

                $user = User::create([
                    'name' => $teacher->name,
                    'email' => $email,
                    'nip' => $teacher->nip,
                    'password' => Hash::make($request->password),
                    'plain_password' => $request->password,
                    'role' => 'guru',
                    'must_change_password' => true,
                ]);

                // Link user to teacher
                $teacher->update([
                    'user_id' => $user->id,
                    'plain_password' => $request->password,
                ]);
            } elseif ($request->role === 'tu') {
                // Create account from existing teacher data (for TU)
                $teacher = \App\Models\Teacher::findOrFail($request->person_id);

                // Check if NIP is already used
                if ($teacher->nip && User::where('nip', $teacher->nip)->exists()) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'nip' => ['NIP ' . $teacher->nip . ' sudah digunakan oleh akun lain. Silakan cek data user.'],
                    ]);
                }

                // Generate email from NIP or name
                $baseEmail = $teacher->nip ?: strtolower(str_replace(' ', '.', $teacher->name));
                $email = $baseEmail . '@tu.mi-alghazali.sch.id';
                $counter = 1;
                while (User::where('email', $email)->exists()) {
                    $email = $baseEmail . $counter . '@tu.mi-alghazali.sch.id';
                    $counter++;
                }

                $user = User::create([
                    'name' => $teacher->name,
                    'email' => $email,
                    'nip' => $teacher->nip,
                    'password' => Hash::make($request->password),
                    'plain_password' => $request->password,
                    'role' => 'tu',
                    'must_change_password' => true,
                ]);

                // Link user to teacher
                $teacher->update([
                    'user_id' => $user->id,
                    'plain_password' => $request->password,
                ]);
            } elseif ($request->role === 'siswa') {
                // Create account from existing student data
                $student = \App\Models\Student::findOrFail($request->person_id);

                // Generate email from NIS
                $baseEmail = $student->nis ?: strtolower(str_replace(' ', '.', $student->name));
                $email = $baseEmail . '@student.mi-alghazali.sch.id';
                $counter = 1;
                while (User::where('email', $email)->exists()) {
                    $email = $baseEmail . $counter . '@student.mi-alghazali.sch.id';
                    $counter++;
                }

                $user = User::create([
                    'name' => $student->name,
                    'email' => $email,
                    'nis' => $student->nis,
                    'password' => Hash::make($request->password),
                    'plain_password' => $request->password,
                    'role' => 'siswa',
                    'must_change_password' => true,
                ]);

                // Link user to student
                $student->update([
                    'user_id' => $user->id,
                    'plain_password' => $request->password,
                ]);
            } elseif ($request->role === 'wali_murid') {
                // Create wali_murid account linked to a student
                $student = \App\Models\Student::findOrFail($request->student_id);

                // Generate email from parent name
                $baseEmail = strtolower(str_replace(' ', '.', $request->name));
                $email = $baseEmail . '@wali.mi-alghazali.sch.id';
                $counter = 1;
                while (User::where('email', $email)->exists()) {
                    $email = $baseEmail . $counter . '@wali.mi-alghazali.sch.id';
                    $counter++;
                }

                User::create([
                    'name' => $request->name,
                    'email' => $email,
                    'student_id' => $student->id,
                    'password' => Hash::make($request->password),
                    'plain_password' => $request->password,
                    'role' => 'wali_murid',
                ]);
            } elseif ($request->role === 'bendahara') {
                // Create account from existing teacher data (for Bendahara)
                $teacher = \App\Models\Teacher::findOrFail($request->person_id);

                // Check if NIP is already used
                if ($teacher->nip && User::where('nip', $teacher->nip)->exists()) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'nip' => ['NIP ' . $teacher->nip . ' sudah digunakan oleh akun lain. Silakan cek data user.'],
                    ]);
                }

                // Generate email
                $baseEmail = $teacher->nip ?: strtolower(str_replace(' ', '.', $teacher->name));
                $email = $baseEmail . '@bendahara.mi-alghazali.sch.id';
                $counter = 1;
                while (User::where('email', $email)->exists()) {
                    $email = $baseEmail . $counter . '@bendahara.mi-alghazali.sch.id';
                    $counter++;
                }

                $user = User::create([
                    'name' => $teacher->name,
                    'email' => $email,
                    'nip' => $teacher->nip,
                    'password' => Hash::make($request->password),
                    'plain_password' => $request->password,
                    'role' => 'bendahara',
                    'must_change_password' => true,
                ]);

                // Link user to teacher
                $teacher->update([
                    'user_id' => $user->id,
                    'plain_password' => $request->password,
                ]);
            }
        });

        return redirect()->route('admin.users.index')->with('success', 'Akun berhasil ditambahkan!');
    }

    public function edit(User $user)
    {
        return \Inertia\Inertia::render('Dashboard/User/Edit', [
            'user' => $user
        ]);
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'role' => 'required|in:admin,guru,siswa,wali_murid,tu',
            'password' => 'nullable|string|min:6',
            'nip' => [
                'nullable',
                'string',
                'unique:users,nip,' . $user->id,
                'unique:users,nis',
                'unique:students,nis',
            ],
            'nis' => [
                'nullable',
                'string',
                'unique:users,nis,' . $user->id,
                'unique:users,nip',
                'unique:teachers,nip',
            ],
            'student_id' => $request->role === 'wali_murid' ? 'required|integer|exists:students,id' : 'nullable|integer',
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($request, $user) {
            $oldRole = $user->role;

            $data = [
                'name' => $request->name,
                'email' => $request->email,
                'role' => $request->role,
                'nip' => $request->nip,
                'nis' => $request->nis,
            ];

            if ($request->filled('password')) {
                $data['password'] = Hash::make($request->password);
                $data['plain_password'] = $request->password;
            }

            $user->update($data);

            // Cleanup if role changed
            if ($oldRole !== $request->role) {
                if (($oldRole === 'guru' || $oldRole === 'tu') && $user->teacher) {
                    if ($user->teacher->image) {
                        \App\Services\ImageService::delete($user->teacher->image);
                    }
                    $user->teacher->delete();
                } elseif ($oldRole === 'siswa' && $user->student) {
                    if ($user->student->image) {
                        \App\Services\ImageService::delete($user->student->image);
                    }
                    $user->student->delete();
                }
            }

            // Sync data to Teacher table if role is guru or tu
            if ($user->role === 'guru' || $user->role === 'tu') {
                $teacher = $user->teacher; // reload or check existence
                if ($teacher) {
                    $teacherData = [
                        'name' => $request->name,
                        'nip' => $request->nip,
                    ];
                    if ($request->filled('password')) {
                        $teacherData['plain_password'] = $request->password;
                    }
                    $teacher->update($teacherData);
                } else {
                    // Create teacher profile if it doesn't exist (e.g. role changed to guru)
                    \App\Models\Teacher::create([
                        'user_id' => $user->id,
                        'name' => $user->name,
                        'nip' => $user->nip,
                        'plain_password' => $request->filled('password') ? $request->password : $user->plain_password,
                        'status' => 'active',
                    ]);
                }
            }
            // Sync data to Student table if role is siswa
            elseif ($user->role === 'siswa') {
                $student = $user->student;
                if ($student) {
                    $studentData = [
                        'name' => $request->name,
                        'nis' => $request->nis,
                    ];
                    if ($request->filled('password')) {
                        $studentData['plain_password'] = $request->password;
                    }
                    $student->update($studentData);
                } else {
                    // Create student profile if it doesn't exist
                    \App\Models\Student::create([
                        'user_id' => $user->id,
                        'name' => $user->name,
                        'nis' => $user->nis,
                        'plain_password' => $request->filled('password') ? $request->password : $user->plain_password,
                        'status' => 'active',
                    ]);
                }
            }
        });

        return redirect()->route('admin.users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(User $user)
    {
        if ($user->id === \Illuminate\Support\Facades\Auth::id()) {
            return back()->with('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($user) {
            // Delete associated images
            if ($user->teacher && $user->teacher->image) {
                \App\Services\ImageService::delete($user->teacher->image);
            }
            if ($user->student && $user->student->image) {
                \App\Services\ImageService::delete($user->student->image);
            }

            $user->delete();
        });

        return redirect()->route('admin.users.index')->with('success', 'Akun berhasil dihapus!');
    }
}
