<?php

namespace App\Http\Controllers;

use App\Models\Achievement;
use App\Models\Attendance;
use App\Models\ClassRoom;
use App\Models\Donation;
use App\Models\DonationSetting;
use App\Models\Extracurricular;
use App\Models\PhilosophyItem;
use App\Models\Post;
use App\Models\Ppdb;
use App\Models\PpdbInfo;
use App\Models\ProfileSection;
use App\Models\Sarpras;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\Teacher;
use App\Models\Subject;
use App\Models\Task;
use App\Models\TaskAssignee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ApiController extends Controller
{
  // ==========================================
  // PUBLIC ENDPOINTS
  // ==========================================

  /**
   * Home page data (slider images + kepala image)
   */
  public function home()
  {
    $files = glob(public_path('images/slider/*.*'));
    $sliderImages = array_map(fn($file) => url('/images/slider/' . basename($file)), $files);

    $kepalaFiles = glob(public_path('images/kepala/*.*'));
    $kepalaImage = !empty($kepalaFiles) ? url('/images/kepala/' . basename($kepalaFiles[0])) : null;

    return response()->json([
      'sliderImages' => $sliderImages,
      'kepalaImage' => $kepalaImage,
      'students_count' => Student::where('status', 'active')->count(),
      'teachers_count' => Teacher::where('status', 'active')->count(),
    ]);
  }

  /**
   * Slider images only
   */
  public function sliderImages()
  {
    $files = glob(public_path('images/slider/*.*'));
    $sliderImages = array_map(fn($file) => url('/images/slider/' . basename($file)), $files);

    return response()->json($sliderImages);
  }

  /**
   * Profile Madrasah data
   */
  public function profile()
  {
    return response()->json([
      'sections' => ProfileSection::all()->keyBy('key'),
      'philosophies' => PhilosophyItem::orderBy('order')->get(),
      'teachers' => $this->getTeachersWithSubjectCodes(),
      'groupPhoto' => ProfileSection::where('key', 'ptk_group_photo')->first(),
      'extracurriculars' => Extracurricular::orderBy('order')->get(),
      'sarpras' => Sarpras::orderBy('order')->get(),
      'achievements' => Achievement::orderBy('date', 'desc')->get(),
    ]);
  }

  /**
   * Helper to get teachers with subject codes
   */
  private function getTeachersWithSubjectCodes()
  {
    $teachers = Teacher::where('status', 'active')->orderBy('name')->get();
    $subjects = Subject::pluck('code', 'name');

    $teachers->transform(function ($teacher) use ($subjects) {
      if (str_starts_with($teacher->position, 'Guru Mapel ')) {
        $subjectName = substr($teacher->position, 11);
        if (isset($subjects[$subjectName])) {
          $teacher->position = 'Guru Mapel ' . $subjects[$subjectName];
        }
      }
      return $teacher;
    });

    // Sort: Kepala Madrasah first
    $sorted = $teachers->sortBy(function ($teacher) {
      return $teacher->position === 'Kepala Madrasah' ? 0 : 1;
    });

    return $sorted->values();
  }

  /**
   * Get existing roles from users table
   */
  public function getExistingRoles()
  {
    $roles = User::select('role')
      ->whereNotNull('role')
      ->distinct()
      ->orderBy('role')
      ->pluck('role');

    return response()->json($roles);
  }

  /**
   * Teachers list
   */
  public function teachers()
  {
    return response()->json([
      'teachers' => $this->getTeachersWithSubjectCodes(),
      'groupPhoto' => ProfileSection::where('key', 'ptk_group_photo')->first(),
    ]);
  }

  /**
   * Extracurriculars list
   */
  public function extracurriculars()
  {
    return response()->json(Extracurricular::orderBy('order')->get());
  }

  /**
   * Sarpras (Facilities) list
   */
  public function sarpras()
  {
    return response()->json(Sarpras::orderBy('order')->get());
  }

  /**
   * Achievements list
   */
  public function achievements()
  {
    return response()->json(Achievement::orderBy('date', 'desc')->get());
  }

  /**
   * Achievement detail
   */
  public function achievementDetail(Achievement $achievement)
  {
    return response()->json($achievement);
  }

  /**
   * Posts list
   */
  public function posts()
  {
    return response()->json(Post::where('status', 'published')->latest()->get());
  }

  /**
   * Post detail
   */
  public function postDetail(Post $post)
  {
    return response()->json($post);
  }

  /**
   * PPDB Info
   */
  public function ppdbInfo()
  {
    return response()->json(PpdbInfo::first());
  }

  /**
   * Kesiswaan data (public view - achievements)
   */
  public function kesiswaan()
  {
    return response()->json([
      'achievements' => Achievement::orderBy('date', 'desc')->get(),
    ]);
  }

  /**
   * Public students list
   */
  public function publicStudents(Request $request)
  {
    $query = Student::where('status', 'active')
      ->with('classRoom:id,name,grade'); // Eager load classroom with specific columns

    if ($request->filled('grade')) {
      $query->where('grade', $request->grade);
    }

    if ($request->filled('class_room_id')) {
      $query->where('class_room_id', $request->class_room_id);
    }

    // You might want to select only necessary columns for public view
    // to avoid exposing sensitive data if any
    $students = $query->orderBy('grade')
      ->orderBy('class_room_id') // Group by class implicitly
      ->orderBy('name')
      ->get(['id', 'name', 'grade', 'class_room_id', 'image', 'nis']);

    // Get grades and classRooms for filter
    $grades = Student::where('status', 'active')->distinct()->pluck('grade')->sort()->values();
    $classRooms = ClassRoom::orderBy('grade')->orderBy('name')->get();

    return response()->json([
      'students' => $students,
      'grades' => $grades,
      'classRooms' => $classRooms,
    ]);
  }

  /**
   * Public contact settings (for footer)
   */
  public function publicContactSettings()
  {
    $settings = DonationSetting::first();

    return response()->json([
      'wa_number' => $settings?->wa_number,
    ]);
  }

  // ==========================================
  // AUTHENTICATION
  // ==========================================

  /**
   * Login
   */
  public function login(Request $request)
  {
    $request->validate([
      'email' => 'required|string',
      'password' => 'required',
    ]);

    $identifier = $request->email;
    $user = null;

    // Check if input is email format
    if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
      $user = User::where('email', $identifier)->first();
    }

    // If not found by email, try NIP (for teachers)
    if (!$user) {
      $user = User::where('nip', $identifier)->first();
    }

    // If not found by NIP, try NIS (for students)
    if (!$user) {
      $user = User::where('nis', $identifier)->first();
    }

    if (!$user || !Hash::check($request->password, $user->password)) {
      throw ValidationException::withMessages([
        'email' => ['Kredensial yang diberikan tidak valid.'],
      ]);
    }

    // Create token
    $token = $user->createToken('api-token')->plainTextToken;

    // Try to load relations, but don't fail if tables don't exist
    try {
      $user->load(['teacher', 'student']);
    } catch (\Exception $e) {
      // Relations may fail if tables are missing - that's ok
    }

    return response()->json([
      'user' => $user,
      'token' => $token,
      'must_change_password' => $user->must_change_password ?? false,
    ]);
  }

  /**
   * Logout
   */
  public function logout(Request $request)
  {
    $request->user()->currentAccessToken()->delete();

    return response()->json(['message' => 'Berhasil logout']);
  }

  /**
   * Update Profile
   */
  public function updateProfile(Request $request)
  {
    $user = $request->user();

    $request->validate([
      'name' => 'required|string|max:255',
      'email' => 'required|email|unique:users,email,' . $user->id,
      'password' => 'nullable|string|min:6|confirmed',
    ]);

    $data = [
      'name' => $request->name,
      'email' => $request->email,
    ];

    if ($request->filled('password')) {
      $data['password'] = Hash::make($request->password);
      $data['plain_password'] = $request->password;
      $data['must_change_password'] = false;
    }

    $user->update($data);

    return response()->json([
      'message' => 'Profil berhasil diperbarui!',
      'user' => $user->load(['teacher', 'student']),
    ]);
  }

  /**
   * Get Active Academic Year
   */
  public function getActiveAcademicYear()
  {
    $activeYear = \App\Models\AcademicYear::where('status', 'active')->first();
    return response()->json($activeYear);
  }

  // ==========================================
  // DASHBOARD
  // ==========================================

  /**
   * Dashboard stats
   */
  /**
   * Dashboard stats
   */
  public function dashboardStats()
  {
    return response()->json([
      'posts_count' => Post::count(),
      'media_count' => count(\Illuminate\Support\Facades\Storage::disk('public')->files('media')),
      'users_count' => User::where('role', '!=', 'student')->where('role', '!=', 'wali_murid')->count(),
      'achievements_count' => Achievement::count(),
      'ppdb_count' => Ppdb::count(),
      'students_count' => Student::where('status', 'active')->count(),
      'teachers_count' => Teacher::where('status', 'active')->count(),
      'alumni_count' => Student::where('status', 'alumni')->count(),
      'recent_posts' => Post::latest()->take(5)->get(),
      'student_distribution' => Student::selectRaw('grade, count(*) as count')
        ->where('status', 'active')
        ->whereNotNull('grade')
        ->groupBy('grade')
        ->get(),
      'achievement_distribution' => Achievement::selectRaw('level, count(*) as count')
        ->whereNotNull('level')
        ->groupBy('level')
        ->get(),
      'task_stats' => [
        'total' => Task::count(),
        'submitted' => TaskAssignee::where('status', 'submitted')->count(),
        'approved' => TaskAssignee::where('status', 'approved')->count(),
        'rejected' => TaskAssignee::where('status', 'rejected')->count(),
        'pending' => TaskAssignee::where('status', 'pending')->count(),
      ]
    ]);
  }

  /**
   * Bendahara Dashboard stats
   */
  public function bendaharaDashboard()
  {
    return response()->json([
      'donations' => [
        'total_amount' => 0,
        'pending_count' => 0
      ],
      'financial_obligations' => [
        'total_collected' => 0,
        'pending_count' => 0
      ]
    ]);
  }

  /**
   * Parent Dashboard
   */
  public function parentDashboard(Request $request)
  {
    // Return empty/safe data for Parent Dashboard
    return response()->json([
      'student' => null,
      'attendance' => [],
      'summary' => ['hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alpha' => 0],
      'filters' => ['month' => (int) $request->input('month', now()->month), 'year' => (int) $request->input('year', now()->year)],
      'schedules' => [],
    ]);
  }

  // ==========================================
  // ADMIN POSTS
  // ==========================================

  public function adminPosts()
  {
    return response()->json(Post::latest()->get());
  }

  public function storePost(Request $request)
  {
    $request->validate([
      'title' => 'required|string|max:255',
      'content' => 'required',
      'image' => 'nullable',
      'status' => 'nullable|in:draft,published',
      'created_at' => 'nullable|date',
    ]);

    $imagePath = null;
    if ($request->hasFile('image')) {
      $request->validate(['image' => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048']);
      $imagePath = \App\Services\ImageService::upload($request->file('image'), 'posts');
    } elseif ($request->filled('image')) {
      $imagePath = $request->input('image');
    }

    $post = Post::create([
      'title' => $request->title,
      'slug' => Str::slug($request->title),
      'content' => $request->content,
      'image' => $imagePath,
      'user_id' => auth()->id(),
      'status' => $request->input('status', 'published'),
      'created_at' => $request->created_at ? date('Y-m-d H:i:s', strtotime($request->created_at)) : now(),
    ]);

    return response()->json(['message' => 'Berita berhasil dipublish!', 'post' => $post], 201);
  }

  public function editPost(Post $post)
  {
    return response()->json($post);
  }

  public function updatePost(Request $request, Post $post)
  {
    $request->validate([
      'title' => 'required|string|max:255',
      'content' => 'required',
      'image' => 'nullable',
      'status' => 'required|in:draft,published',
      'created_at' => 'nullable|date',
    ]);

    $data = [
      'title' => $request->title,
      'slug' => Str::slug($request->title),
      'content' => $request->content,
      'status' => $request->status,
    ];

    if ($request->created_at) {
      $data['created_at'] = date('Y-m-d H:i:s', strtotime($request->created_at));
    }

    if ($request->hasFile('image')) {
      $request->validate(['image' => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048']);
      \App\Services\ImageService::delete($post->image);
      $data['image'] = \App\Services\ImageService::upload($request->file('image'), 'posts');
    } elseif ($request->filled('image')) {
      $data['image'] = $request->input('image');
    } elseif ($request->has('image') && $request->input('image') === null) {
      \App\Services\ImageService::delete($post->image);
      $data['image'] = null;
    }

    $post->update($data);

    return response()->json(['message' => 'Berita berhasil diperbarui!', 'post' => $post]);
  }

  public function deletePost(Post $post)
  {
    \App\Services\ImageService::delete($post->image);
    $post->delete();

    return response()->json(['message' => 'Berita berhasil dihapus!']);
  }

  // ==========================================
  // ADMIN STUDENTS
  // ==========================================

  public function adminStudents(Request $request)
  {
    // Eager load relationships.
    // We load 'classrooms' filtered by the relevant academic year

    // Resolve Academic Year: Use param or default to Global Active Year
    $targetYearId = $request->input('academic_year_id');
    if (!$targetYearId) {
      $activeYear = \App\Models\AcademicYear::where('status', 'active')->first();
      $targetYearId = $activeYear ? $activeYear->id : null;
    }

    $query = Student::with([
      'user',
      'classRoom', // Legacy
      'classrooms' => function ($q) use ($targetYearId) {
        // Only load classroom data for the target year
        if ($targetYearId) {
          $q->where('academic_year_id', $targetYearId);
        } else {
          // Fallback if no year found (shouldn't happen in normal flow), just active ones
          $q->wherePivot('status', 'active');
        }
      },
      'classrooms.tingkat',
      'classrooms.rombel'
    ]);

    // Filter by new Academic Structure: Tingkat
    if ($request->filled('tingkat_id')) {
      $query->whereHas('classrooms', function ($q) use ($request, $targetYearId) {
        $q->where('tingkat_id', $request->tingkat_id);

        if ($targetYearId) {
          $q->where('academic_year_id', $targetYearId);
        } else {
          $q->where('classroom_student.status', 'active');
        }
      });
    }

    // Filter by new Academic Structure: Rombel (Detail Kelas)
    if ($request->filled('rombel_id')) {
      $query->whereHas('classrooms', function ($q) use ($request, $targetYearId) {
        $q->where('rombel_id', $request->rombel_id);

        if ($targetYearId) {
          $q->where('academic_year_id', $targetYearId);
        } else {
          $q->where('classroom_student.status', 'active');
        }
      });
    }

    // Legacy filters
    if ($request->filled('class_room_id')) {
      $query->where('class_room_id', $request->class_room_id);
    }

    if ($request->filled('grade')) {
      $query->where('grade', $request->grade);
    }

    if ($request->filled('status')) {
      $query->where('status', $request->status);
    }

    // Default sorting
    if (!$request->filled('class_room_id') && !$request->filled('tingkat_id')) {
      $query->orderBy('grade', 'asc');
    }

    return response()->json([
      'students' => $query->orderBy('name')->get(),
      'classRooms' => ClassRoom::all(), // Legacy
    ]);
  }

  public function storeStudent(Request $request)
  {
    $request->validate([
      'name' => 'required|string|max:255',
      'nis' => 'required|string|unique:students',
      'class_room_id' => 'nullable|exists:class_rooms,id',
      'kelas_aktif_id' => 'nullable|exists:kelas,id', // Validate incoming kelas_aktif_id
      'gender' => 'required|in:L,P',
      'birth_place' => 'nullable|string',
      'birth_date' => 'nullable|date',
      'address' => 'nullable|string',
      'father_name' => 'nullable|string',
      'mother_name' => 'nullable|string',
      'parent_name' => 'nullable|string', // Keep for legacy compatibility if API still sends it
      'parent_phone' => 'nullable|string',
      'image' => 'nullable', // Removed stricter image file validation here, done conditionally below
    ]);

    $data = $request->except('image', 'kelas_aktif_id');

    // Map kelas_aktif_id to kelas_id
    if ($request->has('kelas_aktif_id')) {
      $data['kelas_id'] = $request->kelas_aktif_id;
    }

    if ($request->hasFile('image')) {
      $request->validate(['image' => 'image|mimes:jpeg,png,jpg,gif|max:2048']);
      $path = $request->file('image')->store('students', 'public');
      $data['image'] = $path;
    } elseif ($request->filled('image')) {
      // If image is a string (path from media library)
      $data['image'] = $request->input('image');
    }

    $student = Student::create($data);

    return response()->json(['message' => 'Siswa berhasil ditambahkan!', 'student' => $student], 201);
  }

  public function editStudent(Student $student)
  {
    return response()->json([
      'student' => $student->load(['user', 'classRoom']),
      'classRooms' => ClassRoom::all(),
    ]);
  }

  public function updateStudent(Request $request, Student $student)
  {
    $request->validate([
      'name' => 'required|string|max:255',
      'nis' => 'required|string|unique:students,nis,' . $student->id,
      'class_room_id' => 'nullable|exists:class_rooms,id',
      'gender' => 'required|in:L,P',
      'birth_place' => 'nullable|string',
      'birth_date' => 'nullable|date',
      'father_name' => 'nullable|string',
      'mother_name' => 'nullable|string',
      'parent_phone' => 'nullable|string',
      'address' => 'nullable|string',
      'image' => 'nullable',
    ]);

    $data = $request->except('image');

    // Handle Image
    if ($request->hasFile('image')) {
      $request->validate(['image' => 'image|mimes:jpeg,png,jpg,gif|max:2048']);

      // Delete old image if exists and it was a local file (not essential but good practice)
      // Note: If using media library, we might not want to delete files aggressively if they are used elsewhere.

      $path = $request->file('image')->store('students', 'public');
      $data['image'] = $path;
    } elseif ($request->filled('image')) {
      // If image is filled (string path from media library)
      $data['image'] = $request->input('image');
      // If the image string is different, we just update the path in DB.
    } elseif ($request->has('image') && $request->input('image') === null) {
      // If image is explicitly null (deleted)
      $data['image'] = null;
    }

    $student->update($data);

    return response()->json(['message' => 'Siswa berhasil diperbarui!', 'student' => $student]);
  }

  public function deleteStudent(Student $student)
  {
    $student->delete();

    return response()->json(['message' => 'Siswa berhasil dihapus!']);
  }

  public function promoteStudents()
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

    // 3. Promote PMB "accepted" to Grade 1
    $acceptedPmbs = Ppdb::where('status', 'accepted')->get();

    // Get last NIS to increment safely
    $lastNis = Student::max('nis');
    if (!$lastNis) {
      $lastNis = date('Y') . '000';
    }

    foreach ($acceptedPmbs as $pmb) {
      // Check for duplicate to avoid double promotion
      $exists = Student::where('name', $pmb->name)
        ->where('birth_date', $pmb->birth_date)
        ->exists();

      if ($exists)
        continue;

      // Increment NIS
      $lastNis = (string) ((int) $lastNis + 1);

      Student::create([
        'name' => $pmb->name,
        'nis' => $lastNis,
        'nisn' => $pmb->nisn, // Assuming nisn exists in Ppdb
        'gender' => $pmb->gender,
        'birth_place' => $pmb->birth_place,
        'birth_date' => $pmb->birth_date,
        'address' => $pmb->address,
        'parent_name' => $pmb->parent_name,
        'parent_phone' => $pmb->phone,
        'grade' => '1',
        'status' => 'active',
        'admission_year' => date('Y'),
      ]);

      // Optional: Update PMB status to indicate processed?
      // $pmb->update(['status' => 'processed']); // If we had that status
    }

    return response()->json(['message' => 'Siswa berhasil dinaikkan kelas!']);
  }

  public function demoteStudents()
  {
    // 1. Demote Alumni (current year graduates) back to Grade 6
    // Only demote those who graduated THIS YEAR to avoid messing up old alumni
    $currentYear = date('Y');
    Student::where('status', 'alumni')
      ->where('graduation_year', $currentYear)
      ->update([
        'grade' => '6',
        'status' => 'active',
        'graduation_year' => null
      ]);

    // 2. Demote active grades (2->1, 3->2, etc.) ascending to avoid conflict
    // e.g., if we do 6->5 first, they become 5. Then 5->4 moves them to 4. We don't want that.
    // So we must start from 2->1.
    // Wait. If I do 2->1. Those students are now 1.
    // Then I do 3->2. Those are now 2.  SAFE.
    // So Ascending order: 2, 3, 4, 5, 6
    $grades = ['2', '3', '4', '5', '6'];
    foreach ($grades as $grade) {
      $prevGrade = (string) ($grade - 1);
      Student::where('grade', $grade)->where('status', 'active')->update([
        'grade' => $prevGrade
      ]);
    }

    return response()->json(['message' => 'Siswa berhasil diturunkan kelas!']);
  }

  // ==========================================
  // CLASS ROOMS
  // ==========================================

  public function classRooms()
  {
    return response()->json(ClassRoom::orderBy('grade')->orderBy('name')->get());
  }

  public function storeClassRoom(Request $request)
  {
    $request->validate([
      'grade' => 'required|integer|min:1|max:6',
      'name' => 'required|string|max:255',
    ]);

    // Check for duplicate name within the same grade
    $exists = ClassRoom::where('grade', $request->grade)
      ->where('name', $request->name)
      ->exists();

    if ($exists) {
      return response()->json([
        'message' => 'Nama rombel sudah ada untuk kelas tersebut'
      ], 422);
    }

    $classRoom = ClassRoom::create([
      'grade' => $request->grade,
      'name' => $request->name,
    ]);

    return response()->json([
      'message' => 'Rombel berhasil ditambahkan!',
      'classRoom' => $classRoom
    ], 201);
  }

  public function updateClassRoom(Request $request, ClassRoom $classRoom)
  {
    $request->validate([
      'grade' => 'required|integer|min:1|max:6',
      'name' => 'required|string|max:255',
    ]);

    // Check for duplicate name within the same grade (excluding current record)
    $exists = ClassRoom::where('grade', $request->grade)
      ->where('name', $request->name)
      ->where('id', '!=', $classRoom->id)
      ->exists();

    if ($exists) {
      return response()->json([
        'message' => 'Nama rombel sudah ada untuk kelas tersebut'
      ], 422);
    }

    $classRoom->update([
      'grade' => $request->grade,
      'name' => $request->name,
    ]);

    return response()->json([
      'message' => 'Rombel berhasil diperbarui!',
      'classRoom' => $classRoom
    ]);
  }

  public function deleteClassRoom(ClassRoom $classRoom)
  {
    // Check if classroom has students
    if ($classRoom->students()->exists()) {
      return response()->json([
        'message' => 'Tidak dapat menghapus rombel yang memiliki siswa.'
      ], 422);
    }

    $classRoom->delete();

    return response()->json(['message' => 'Rombel berhasil dihapus!']);
  }

  // ==========================================
  // PTK ATTENDANCE (ADMIN INPUT)
  // ==========================================

  public function adminPtkAttendance(Request $request)
  {
    $date = $request->input('date', now()->toDateString());

    // Get all active teachers
    $teachers = Teacher::where('status', 'active')->orderBy('name')->get();

    // Get existing attendance for the date
    $attendances = Attendance::whereDate('date', $date)
      ->get()
      ->keyBy('user_id'); // Key by user_id for easier frontend mapping

    // Transform to simplified structure if needed, or just return raw
    return response()->json([
      'teachers' => $teachers,
      'attendances' => $attendances,
      'date' => $date
    ]);
  }

  public function storeAdminPtkAttendance(Request $request)
  {
    $request->validate([
      'date' => 'required|date',
      'attendances' => 'required|array',
      'attendances.*.user_id' => 'required|exists:users,id', // Note: Teachers are users
      'attendances.*.status' => 'required|in:hadir,izin,sakit,alpha',
    ]);

    $date = $request->date;

    foreach ($request->attendances as $att) {
      // Find the teacher user ID (ensure we work with user_id matching users table)
      // The frontend should send the User ID associated with the teacher

      Attendance::updateOrCreate(
        [
          'user_id' => $att['user_id'],
          'date' => $date
        ],
        [
          'status' => $att['status'],
          'type' => 'daily', // Mark as daily/admin input
          'location_status' => 'valid', // Admin input is always valid loc
          'time' => now()->toTimeString(), // Optional: record update time
        ]
      );
    }

    return response()->json(['message' => 'Absensi guru berhasil disimpan!']);
  }

  public function adminPtkAttendanceReport(Request $request)
  {
    $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
    $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());

    $user = $request->user();

    // Get active teachers query
    $teachersQuery = Teacher::where('status', 'active')->orderBy('name');

    // If logged in as Guru, only show own data
    if ($user->role === 'guru') {
      // Assuming teacher record is linked to user
      // or user ID is enough if we trust the link
      // Determine teacher ID from user
      $teachersQuery->where('user_id', $user->id);
    }

    $teachers = $teachersQuery->get();

    // Map manually because we need to count from 'attendances' table based on 'user_id'
    // Teacher model usually has 'user' relation or 'user_id'. 
    // Attendance table uses 'user_id'.
    // So we need to match Teacher->user_id with Attendance->user_id.

    // Efficient way:
    // Get all attendances in range, grouped by user_id and status
    $statsQuery = Attendance::selectRaw('user_id, status, count(*) as count')
      ->whereBetween('date', [$startDate, $endDate])
      ->groupBy('user_id', 'status');

    if ($user->role === 'guru') {
      $statsQuery->where('user_id', $user->id);
    }

    $stats = $statsQuery->get();

    // Transform to structured data
    $report = $teachers->map(function ($teacher) use ($stats) {
      $user_id = $teacher->user_id; // Check if teacher has user_id, it should.

      $hadir = $stats->where('user_id', $user_id)->where('status', 'hadir')->first()?->count ?? 0;
      $izin = $stats->where('user_id', $user_id)->where('status', 'izin')->first()?->count ?? 0;
      $sakit = $stats->where('user_id', $user_id)->where('status', 'sakit')->first()?->count ?? 0;
      $alpha = $stats->where('user_id', $user_id)->where('status', 'alpha')->first()?->count ?? 0;

      return [
        'id' => $teacher->id,
        'name' => $teacher->name,
        'nip' => $teacher->nip,
        'hadir_count' => $hadir,
        'izin_count' => $izin,
        'sakit_count' => $sakit,
        'alpha_count' => $alpha,
      ];
    });

    return response()->json([
      'teachers' => $report,
      'filters' => [
        'start_date' => $startDate,
        'end_date' => $endDate
      ]
    ]);
  }

  // ==========================================
  // STUDENT ATTENDANCE
  // ==========================================

  public function studentAttendance(Request $request)
  {
    $date = $request->input('date', now()->toDateString());
    $classId = $request->input('class_room_id') ?? $request->input('class_id');

    // Get students based on class
    $studentsQuery = Student::where('status', 'active')->orderBy('name');

    if ($request->filled('grade')) {
      $studentsQuery->where('grade', $request->grade);
    }

    if ($classId) {
      $studentsQuery->where('kelas_id', $classId);
    }

    // Load classRoom relation for KELAS column
    $students = $studentsQuery->with('kelas.rombel')->get();

    // Get attendances for the date and students
    $attendancesQuery = StudentAttendance::with(['student', 'student.kelas'])
      ->whereDate('date', $date);

    if ($request->filled('grade')) {
      $attendancesQuery->whereHas('student', fn($q) => $q->where('grade', $request->grade));
    }

    if ($classId) {
      $attendancesQuery->whereHas('student', fn($q) => $q->where('kelas_id', $classId));
    }
    $attendances = $attendancesQuery->get();

    // Get available grades from Students (for filter dropdown)
    $grades = Student::where('status', 'active')->distinct()->pluck('grade')->sort()->values();

    return response()->json([
      'students' => $students,
      'attendances' => $attendances,
      'classRooms' => ClassRoom::all(),
      'grades' => $grades,
    ]);
  }

  public function storeStudentAttendance(Request $request)
  {
    $request->validate([
      'attendances' => 'required|array',
      'attendances.*.student_id' => 'required|exists:students,id',
      'attendances.*.date' => 'required|date',
      'attendances.*.status' => 'required|in:hadir,izin,sakit,alpha',
    ]);

    foreach ($request->attendances as $att) {
      StudentAttendance::updateOrCreate(
        ['student_id' => $att['student_id'], 'date' => $att['date']],
        ['status' => $att['status']]
      );
    }

    return response()->json(['message' => 'Kehadiran berhasil disimpan!']);
  }

  /**
   * Laporan Kehadiran Murid dengan statistik
   */
  public function studentAttendanceReport(Request $request)
  {
    $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
    $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());
    $grade = $request->input('grade');
    $classRoomId = $request->input('class_room_id');

    $query = Student::where('status', 'active')->orderBy('name');

    if ($grade) {
      // Filter by grade through classrooms (many-to-many) relationship
      $query->whereHas('classrooms', function ($q) use ($grade) {
        $q->where('classroom_student.status', 'active')
          ->whereHas('tingkat', function ($tq) use ($grade) {
            $tq->where('level', $grade);
          });
      });
    }

    if ($classRoomId) {
      // Filter by specific kelas_id through classrooms pivot
      $query->whereHas('classrooms', function ($q) use ($classRoomId) {
        $q->where('kelas.id', $classRoomId)
          ->where('classroom_student.status', 'active');
      });
    }

    // Eager load active classroom with rombel and tingkat
    $students = $query->with([
      'classrooms' => function ($q) {
        $q->where('classroom_student.status', 'active')
          ->with(['rombel', 'tingkat']);
      }
    ])->withCount([
          'attendances as hadir_count' => function ($query) use ($startDate, $endDate) {
            $query->where('status', 'hadir')->whereBetween('date', [$startDate, $endDate]);
          },
          'attendances as izin_count' => function ($query) use ($startDate, $endDate) {
            $query->where('status', 'izin')->whereBetween('date', [$startDate, $endDate]);
          },
          'attendances as sakit_count' => function ($query) use ($startDate, $endDate) {
            $query->where('status', 'sakit')->whereBetween('date', [$startDate, $endDate]);
          },
          'attendances as alpha_count' => function ($query) use ($startDate, $endDate) {
            $query->where('status', 'alpha')->whereBetween('date', [$startDate, $endDate]);
          }
        ])->get();

    // Grades and Rooms logic - adjusted for new structure if needed, or keep legacy for non-filtered
    // Actually for filter dropdowns we use API getTingkat/getKelasAktif in frontend, so these might be redundant but kept for safety
    $grades = Student::where('status', 'active')->distinct()->pluck('grade')->sort()->values();
    $classRooms = \App\Models\Kelas::with('rombel')->get(); // Changed to Active Class list if needed, or keep legacy if frontend requires legacy list (but frontend was updated)

    return response()->json([
      'students' => $students,
      'grades' => $grades,
      'classRooms' => $classRooms,
      'filters' => [
        'start_date' => $startDate,
        'end_date' => $endDate,
        'grade' => $grade,
        'class_room_id' => $classRoomId,
      ]
    ]);
  }

  /**
   * Export Laporan Kehadiran Murid
   */
  public function exportStudentAttendance(Request $request)
  {
    $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
    $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());
    $grade = $request->input('grade');
    $classRoomId = $request->input('class_room_id');

    $query = Student::where('status', 'active')->orderBy('name');

    if ($grade) {
      // Filter by grade through classrooms (many-to-many) relationship
      $query->whereHas('classrooms', function ($q) use ($grade) {
        $q->where('classroom_student.status', 'active')
          ->whereHas('tingkat', function ($tq) use ($grade) {
            $tq->where('level', $grade);
          });
      });
    }

    if ($classRoomId) {
      // Filter by specific kelas_id through classrooms pivot
      $query->whereHas('classrooms', function ($q) use ($classRoomId) {
        $q->where('kelas.id', $classRoomId)
          ->where('classroom_student.status', 'active');
      });
    }

    // Eager load active classroom with rombel and tingkat
    $students = $query->with([
      'classrooms' => function ($q) {
        $q->where('classroom_student.status', 'active')
          ->with(['rombel', 'tingkat']);
      }
    ])->withCount([
          'attendances as hadir_count' => function ($query) use ($startDate, $endDate) {
            $query->where('status', 'hadir')->whereBetween('date', [$startDate, $endDate]);
          },
          'attendances as izin_count' => function ($query) use ($startDate, $endDate) {
            $query->where('status', 'izin')->whereBetween('date', [$startDate, $endDate]);
          },
          'attendances as sakit_count' => function ($query) use ($startDate, $endDate) {
            $query->where('status', 'sakit')->whereBetween('date', [$startDate, $endDate]);
          },
          'attendances as alpha_count' => function ($query) use ($startDate, $endDate) {
            $query->where('status', 'alpha')->whereBetween('date', [$startDate, $endDate]);
          }
        ])->get();

    $filename = 'Laporan_Kehadiran_' . ($grade ? 'Kelas_' . $grade . '_' : '') . $startDate . '_sd_' . $endDate . '.xls';

    return response()->streamDownload(function () use ($students, $startDate, $endDate, $grade) {
      echo "<html><head><style>table, th, td { border: 1px solid black; border-collapse: collapse; padding: 5px; } th { background-color: #f2f2f2; }</style></head><body>";
      echo "<h2>Laporan Kehadiran Murid</h2>";
      echo "<p>Periode: $startDate s/d $endDate</p>";
      if ($grade) {
        echo "<p>Kelas: $grade</p>";
      }
      echo "<table>";
      echo "<thead><tr>
              <th>No</th>
              <th>Nama Siswa</th>
              <th>NIS</th>
              <th>Kelas</th>
              <th>Hadir</th>
              <th>Izin</th>
              <th>Sakit</th>
              <th>Alpha</th>
            </tr></thead>";
      echo "<tbody>";
      foreach ($students as $index => $student) {
        $className = $student->kelas ? $student->kelas->name : ($student->grade ?? '-');
        echo "<tr>";
        echo "<td>" . ($index + 1) . "</td>";
        echo "<td>{$student->name}</td>";
        echo "<td>'{$student->nis}</td>";
        echo "<td>{$className}</td>";
        echo "<td>{$student->hadir_count}</td>";
        echo "<td>{$student->izin_count}</td>";
        echo "<td>{$student->sakit_count}</td>";
        echo "<td>{$student->alpha_count}</td>";
        echo "</tr>";
      }
      echo "</tbody></table></body></html>";
    }, $filename);
  }

  // ==========================================
  // TEACHER ATTENDANCE
  // ==========================================

  public function attendance(Request $request)
  {
    $query = Attendance::with('teacher');

    if ($request->filled('date')) {
      $query->whereDate('date', $request->date);
    }

    return response()->json($query->latest('date')->paginate(50));
  }

  // ==========================================
  // ADMIN PTK (TEACHERS)
  // ==========================================

  public function adminPtk()
  {
    return response()->json([
      'teachers' => Teacher::orderBy('name')->get(),
      'groupPhoto' => ProfileSection::where('key', 'ptk_group_photo')->first(),
    ]);
  }

  public function storePtk(Request $request)
  {
    $request->validate([
      'name' => 'required|string|max:255',
      'nip' => 'nullable|string|max:20|unique:teachers,nip',
      'gender' => 'required|in:L,P',
      'birth_place' => 'required|string|max:255',
      'birth_date' => 'required|date',
      'address' => 'required|string',
      'position' => 'required|string|max:255',
      'status' => 'required|in:active,inactive',
      'image' => 'nullable',
    ]);

    $imagePath = null;
    if ($request->hasFile('image')) {
      $request->validate(['image' => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048']);
      $imagePath = \App\Services\ImageService::upload($request->file('image'), 'teachers');
    } elseif ($request->filled('image')) {
      $imagePath = $request->input('image');
    }

    $teacher = Teacher::create([
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

    return response()->json(['message' => 'Data PTK berhasil ditambahkan!', 'teacher' => $teacher], 201);
  }

  public function updatePtk(Request $request, Teacher $teacher)
  {
    $data = $request->all();

    if ($request->hasFile('image')) {
      \App\Services\ImageService::delete($teacher->image);
      $data['image'] = \App\Services\ImageService::upload($request->file('image'), 'teachers');
    }

    $teacher->update($data);

    return response()->json(['message' => 'PTK berhasil diperbarui!', 'teacher' => $teacher]);
  }

  public function deletePtk(Teacher $teacher)
  {
    \App\Services\ImageService::delete($teacher->image);
    $teacher->delete();

    return response()->json(['message' => 'PTK berhasil dihapus!']);
  }

  // ==========================================
  // ADMIN SARPRAS
  // ==========================================

  public function adminSarpras()
  {
    return response()->json(Sarpras::orderBy('order')->get());
  }

  public function storeSarpras(Request $request)
  {
    $request->validate([
      'name' => 'required|string|max:255',
      'description' => 'nullable|string',
      'image' => 'nullable',
    ]);

    $data = $request->all();
    $data['order'] = Sarpras::max('order') + 1;

    if ($request->hasFile('image')) {
      $data['image'] = \App\Services\ImageService::upload($request->file('image'), 'sarpras');
    }

    $sarpras = Sarpras::create($data);

    return response()->json(['message' => 'Sarpras berhasil ditambahkan!', 'sarpras' => $sarpras], 201);
  }

  public function updateSarpras(Request $request, Sarpras $sarpras)
  {
    $data = $request->all();

    if ($request->hasFile('image')) {
      \App\Services\ImageService::delete($sarpras->image);
      $data['image'] = \App\Services\ImageService::upload($request->file('image'), 'sarpras');
    }

    $sarpras->update($data);

    return response()->json(['message' => 'Sarpras berhasil diperbarui!', 'sarpras' => $sarpras]);
  }

  public function deleteSarpras(Sarpras $sarpras)
  {
    \App\Services\ImageService::delete($sarpras->image);
    $sarpras->delete();

    return response()->json(['message' => 'Sarpras berhasil dihapus!']);
  }

  // ==========================================
  // ADMIN PMB/PPDB
  // ==========================================

  public function adminPmb()
  {
    return response()->json(Ppdb::latest()->get());
  }

  public function verifySubmission(Request $request, TaskAssignee $assignee)
  {
    $request->validate([
      'status' => 'required|in:approved,rejected',
      'admin_feedback' => 'nullable|string'
    ]);

    $assignee->update([
      'status' => $request->status === 'approved' ? 'approved' : 'rejected',
      'admin_feedback' => $request->admin_feedback,
      'completed_at' => now(),
    ]);

    return response()->json(['message' => 'Status tugas berhasil diperbarui']);
  }

  public function adminUsersExport(Request $request)
  {
    $query = User::query();

    if ($request->filled('role')) {
      $query->where('role', $request->role);
    }

    if ($request->filled('search')) {
      $search = $request->search;
      $query->where(function ($q) use ($search) {
        $q->where('name', 'like', "%{$search}%")
          ->orWhere('email', 'like', "%{$search}%");
      });
    }

    $users = $query->get();
    $filename = "users_export_" . date('Y-m-d_H-i-s') . ".csv";

    $headers = [
      "Content-type" => "text/csv",
      "Content-Disposition" => "attachment; filename=$filename",
      "Pragma" => "no-cache",
      "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
      "Expires" => "0"
    ];

    $callback = function () use ($users) {
      $file = fopen('php://output', 'w');

      // Add BOM for Excel UTF-8 compatibility
      fputs($file, "\xEF\xBB\xBF");

      // Use semicolon as delimiter for Excel in regions like Indonesia
      fputcsv($file, ['Nama', 'Email', 'Password', 'Role', 'Identitas (NIP/NIS)', 'Status'], ';');

      foreach ($users as $user) {
        $identity = $user->nip ?? $user->nis ?? '-';
        // Determine status logic (assuming active if not soft deleted, or if there's a specific status field)
        // Since User model doesn't strictly have 'status' visible in fillable, we'll check if teacher/student has status
        $status = 'Active';
        if ($user->teacher) {
          $status = $user->teacher->status;
        } elseif ($user->student) {
          $status = $user->student->status;
        }

        fputcsv($file, [
          $user->name,
          $user->email,
          $user->plain_password,
          $user->role,
          $identity,
          $status
        ], ';');
      }

      fclose($file);
    };

    return response()->stream($callback, 200, $headers);
  }
  public function storePmb(Request $request)
  {
    $request->validate([
      'name' => 'required|string|max:255',
      'birth_place' => 'required|string',
      'birth_date' => 'required|date',
      'gender' => 'required|in:L,P',
      'parent_name' => 'required|string',
      'phone' => 'required|string',
      'address' => 'required|string',
      'status' => 'nullable|in:pending,accepted,rejected',
    ]);

    $data = $request->all();
    if (!$request->filled('status')) {
      $data['status'] = 'pending';
    }

    $ppdb = Ppdb::create($data);

    return response()->json(['message' => 'Pendaftaran berhasil!', 'ppdb' => $ppdb], 201);
  }

  public function showPmb(Ppdb $pmb)
  {
    return response()->json($pmb);
  }

  public function updatePmb(Request $request, Ppdb $pmb)
  {
    $request->validate([
      'name' => 'required|string|max:255',
      'birth_place' => 'required|string',
      'birth_date' => 'required|date',
      'gender' => 'required|in:L,P',
      'parent_name' => 'required|string',
      'phone' => 'required|string',
      'address' => 'required|string',
      'status' => 'required|in:pending,accepted,rejected',
    ]);

    $pmb->update($request->all());

    return response()->json(['message' => 'Data pendaftar berhasil diperbarui!', 'ppdb' => $pmb]);
  }

  public function deletePmb(Ppdb $pmb)
  {
    $pmb->delete();

    return response()->json(['message' => 'Data pendaftar berhasil dihapus!']);
  }

  public function adminPmbInfo()
  {
    return response()->json(PpdbInfo::first());
  }

  public function updatePmbInfo(Request $request)
  {
    $info = PpdbInfo::first();

    if ($info) {
      $info->update($request->all());
    } else {
      $info = PpdbInfo::create($request->all());
    }

    return response()->json(['message' => 'Info PMB berhasil diperbarui!', 'info' => $info]);
  }

  // ==========================================
  // ADMIN PHILOSOPHY
  // ==========================================

  public function adminPhilosophy()
  {
    return response()->json(PhilosophyItem::orderBy('order')->get());
  }

  public function storePhilosophy(Request $request)
  {
    $request->validate([
      'title' => 'required|string|max:255',
      'description' => 'required|string',
    ]);

    $data = $request->all();
    $data['order'] = PhilosophyItem::max('order') + 1;

    if ($request->hasFile('image')) {
      $data['image'] = \App\Services\ImageService::upload($request->file('image'), 'philosophy');
    }

    $item = PhilosophyItem::create($data);

    return response()->json(['message' => 'Item filosofi berhasil ditambahkan!', 'item' => $item], 201);
  }

  public function updatePhilosophy(Request $request, PhilosophyItem $philosophy)
  {
    $data = $request->all();

    if ($request->hasFile('image')) {
      \App\Services\ImageService::delete($philosophy->image);
      $data['image'] = \App\Services\ImageService::upload($request->file('image'), 'philosophy');
    }

    $philosophy->update($data);

    return response()->json(['message' => 'Item filosofi berhasil diperbarui!', 'item' => $philosophy]);
  }

  public function deletePhilosophy(PhilosophyItem $philosophy)
  {
    \App\Services\ImageService::delete($philosophy->image);
    $philosophy->delete();

    return response()->json(['message' => 'Item filosofi berhasil dihapus!']);
  }

  // ==========================================
  // ADMIN ACHIEVEMENTS
  // ==========================================

  public function adminAchievements()
  {
    return response()->json(Achievement::orderBy('date', 'desc')->get());
  }

  public function storeAchievement(Request $request)
  {
    $request->validate([
      'title' => 'required|string|max:255',
      'description' => 'nullable|string',
      'date' => 'required|date',
      'level' => 'nullable|string',
    ]);

    $data = $request->all();

    if ($request->hasFile('image')) {
      $data['image'] = \App\Services\ImageService::upload($request->file('image'), 'achievements');
    }

    $achievement = Achievement::create($data);

    return response()->json(['message' => 'Prestasi berhasil ditambahkan!', 'achievement' => $achievement], 201);
  }

  public function updateAchievement(Request $request, Achievement $achievement)
  {
    $data = $request->all();

    if ($request->hasFile('image')) {
      \App\Services\ImageService::delete($achievement->image);
      $data['image'] = \App\Services\ImageService::upload($request->file('image'), 'achievements');
    }

    $achievement->update($data);

    return response()->json(['message' => 'Prestasi berhasil diperbarui!', 'achievement' => $achievement]);
  }

  public function deleteAchievement(Achievement $achievement)
  {
    \App\Services\ImageService::delete($achievement->image);
    $achievement->delete();

    return response()->json(['message' => 'Prestasi berhasil dihapus!']);
  }

  // ==========================================
  // ADMIN EXTRACURRICULARS
  // ==========================================

  public function adminExtracurriculars()
  {
    return response()->json(Extracurricular::orderBy('order')->get());
  }

  public function storeExtracurricular(Request $request)
  {
    $request->validate([
      'name' => 'required|string|max:255',
      'description' => 'nullable|string',
    ]);

    $data = $request->all();
    $data['order'] = Extracurricular::max('order') + 1;

    if ($request->hasFile('image')) {
      $data['image'] = \App\Services\ImageService::upload($request->file('image'), 'extracurriculars');
    }

    $item = Extracurricular::create($data);

    return response()->json(['message' => 'Ekskul berhasil ditambahkan!', 'extracurricular' => $item], 201);
  }

  public function updateExtracurricular(Request $request, Extracurricular $extracurricular)
  {
    $data = $request->all();

    if ($request->hasFile('image')) {
      \App\Services\ImageService::delete($extracurricular->image);
      $data['image'] = \App\Services\ImageService::upload($request->file('image'), 'extracurriculars');
    }

    $extracurricular->update($data);

    return response()->json(['message' => 'Ekskul berhasil diperbarui!', 'extracurricular' => $extracurricular]);
  }

  public function deleteExtracurricular(Extracurricular $extracurricular)
  {
    \App\Services\ImageService::delete($extracurricular->image);
    $extracurricular->delete();

    return response()->json(['message' => 'Ekskul berhasil dihapus!']);
  }

  // ==========================================
  // SCHEDULES
  // ==========================================

  public function classSchedules()
  {
    return response()->json([
      'schedules' => Schedule::where('type', 'class')->with('classRoom')->latest()->get(),
      'classRooms' => ClassRoom::all(),
    ]);
  }

  public function ptsSchedules()
  {
    return response()->json([
      'schedules' => Schedule::where('type', 'pts')->with('classRoom')->latest()->get(),
      'classRooms' => ClassRoom::all(),
    ]);
  }

  public function pasSchedules()
  {
    return response()->json([
      'schedules' => Schedule::where('type', 'pas')->with('classRoom')->latest()->get(),
      'classRooms' => ClassRoom::all(),
    ]);
  }

  public function storeSchedule(Request $request)
  {
    $request->validate([
      'title' => 'required|string|max:255',
      'type' => 'required|in:class,pts,pas',
      'grade' => 'nullable|string',
      'class_room_id' => 'nullable|exists:class_rooms,id',
      'file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:5120',
      'file_path' => 'nullable|string',
      'description' => 'nullable|string',
    ]);

    // Either file upload or file_path from media library is required
    if (!$request->hasFile('file') && !$request->filled('file_path')) {
      return response()->json(['message' => 'File atau file_path harus diisi'], 422);
    }

    // Determine file_path - prefer uploaded file, fallback to media library path
    if ($request->hasFile('file')) {
      $path = $request->file('file')->store('schedules', 'public');
      $filePath = '/storage/' . $path;
    } else {
      $filePath = $request->file_path;
    }

    $schedule = Schedule::create([
      'title' => $request->title,
      'type' => $request->type,
      'grade' => $request->grade,
      'class_room_id' => $request->class_room_id,
      'file_path' => $filePath,
      'description' => $request->description,
    ]);

    return response()->json(['message' => 'Jadwal berhasil diupload!', 'schedule' => $schedule], 201);
  }

  public function updateSchedule(Request $request, Schedule $schedule)
  {
    $request->validate([
      'title' => 'required|string|max:255',
      'type' => 'required|in:class,pts,pas',
      'grade' => 'nullable|string',
      'class_room_id' => 'nullable|exists:class_rooms,id',
      'file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:5120',
      'file_path' => 'nullable|string',
      'description' => 'nullable|string',
    ]);

    $data = [
      'title' => $request->title,
      'type' => $request->type,
      'grade' => $request->grade,
      'class_room_id' => $request->class_room_id,
      'description' => $request->description,
    ];

    if ($request->hasFile('file')) {
      $path = $request->file('file')->store('schedules', 'public');
      $data['file_path'] = '/storage/' . $path;
    } elseif ($request->filled('file_path')) {
      $data['file_path'] = $request->file_path;
    }

    $schedule->update($data);

    return response()->json(['message' => 'Jadwal berhasil diperbarui!', 'schedule' => $schedule]);
  }

  public function deleteSchedule(Schedule $schedule)
  {
    $schedule->delete();

    return response()->json(['message' => 'Jadwal berhasil dihapus!']);
  }

  // ==========================================
  // DONATIONS
  // ==========================================

  public function donations()
  {
    return response()->json(Donation::where('user_id', auth()->id())->latest()->get());
  }

  public function storeDonation(Request $request)
  {
    $request->validate([
      'donor_name' => 'required|string|max:255',
      'transaction_number' => 'required|string|max:100',
      'amount' => 'required|numeric|min:1000',
    ]);

    $donation = Donation::create([
      'user_id' => auth()->id(),
      'donor_name' => $request->donor_name,
      'transaction_number' => $request->transaction_number,
      'amount' => $request->amount,
      'status' => 'pending',
    ]);

    return response()->json(['message' => 'Donasi berhasil diajukan!', 'donation' => $donation], 201);
  }

  public function adminDonations()
  {
    return response()->json(Donation::with('user')->latest()->get());
  }

  public function updateDonationStatus(Request $request, Donation $donation)
  {
    $request->validate([
      'status' => 'required|in:pending,approved,rejected',
    ]);

    $donation->update(['status' => $request->status]);

    return response()->json(['message' => 'Status donasi berhasil diperbarui!', 'donation' => $donation]);
  }

  public function deleteDonation(Donation $donation)
  {
    $donation->delete();

    return response()->json(['message' => 'Donasi berhasil dihapus!']);
  }

  public function donationSettings()
  {
    return response()->json(DonationSetting::first());
  }

  public function updateDonationSettings(Request $request)
  {
    $settings = DonationSetting::first();

    if ($settings) {
      $settings->update($request->all());
    } else {
      $settings = DonationSetting::create($request->all());
    }

    return response()->json(['message' => 'Pengaturan donasi berhasil diperbarui!', 'settings' => $settings]);
  }

  // ==========================================
  // ADMIN USERS
  // ==========================================

  public function adminUsers(Request $request)
  {
    $query = User::query();

    if ($request->filled('role')) {
      $query->where('role', $request->role);
    }

    if ($request->filled('search')) {
      $search = $request->search;
      $query->where(function ($q) use ($search) {
        $q->where('name', 'like', "%{$search}%")
          ->orWhere('email', 'like', "%{$search}%");
      });
    }

    return response()->json($query->get());
  }

  /**
   * Get list of teachers without user account
   */
  public function getUnregisteredTeachers()
  {
    $teachers = Teacher::whereNull('user_id')
      ->where('status', 'active')
      ->orderBy('name')
      ->get(['id', 'name', 'nip', 'position']);

    return response()->json($teachers);
  }

  /**
   * Get list of students without user account
   */
  public function getUnregisteredStudents()
  {
    $students = Student::whereNull('user_id')
      ->where('status', 'active')
      ->orderBy('name')
      ->get(['id', 'name', 'nis', 'nisn']);

    return response()->json($students);
  }

  public function storeUser(Request $request)
  {
    // Validate based on role
    $rules = [
      'role' => 'required|in:admin,guru,siswa,wali_murid,bendahara,tu,kepala',
      'password' => 'required|string|min:6',
    ];

    // Admin role requires manual name and email input
    if ($request->role === 'admin') {
      $rules['name'] = 'required|string|max:255';
      $rules['email'] = 'required|string|email|max:255|unique:users';
    } elseif ($request->role === 'wali_murid') {
      // Wali murid requires name, and student_id to link
      $rules['name'] = 'required|string|max:255';
      $rules['student_id'] = 'required|integer|exists:students,id';
    } else {
      // Guru and Siswa require person_id (teacher_id or student_id)
      $rules['person_id'] = 'required|integer';
    }

    $request->validate($rules);

    $user = null;

    \Illuminate\Support\Facades\DB::transaction(function () use ($request, &$user) {
      if ($request->role === 'admin') {
        // Create admin account manually
        $user = User::create([
          'name' => $request->name,
          'email' => $request->email,
          'password' => Hash::make($request->password),
          'plain_password' => $request->password,
          'role' => 'admin',
        ]);
      } elseif ($request->role === 'guru') {
        // Create account from existing teacher data
        $teacher = Teacher::findOrFail($request->person_id);

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
      } elseif ($request->role === 'bendahara') {
        // Create account from existing teacher data (Bendahara is also a Teacher/PTK)
        $teacher = Teacher::findOrFail($request->person_id);

        // Generate email from NIP or name
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
      } elseif ($request->role === 'kepala') {
        // Create account from existing teacher data (Kepala is also a Teacher/PTK)
        $teacher = Teacher::findOrFail($request->person_id);

        // Generate email from NIP or name
        $baseEmail = $teacher->nip ?: strtolower(str_replace(' ', '.', $teacher->name));
        $email = $baseEmail . '@kepala.mi-alghazali.sch.id';
        $counter = 1;
        while (User::where('email', $email)->exists()) {
          $email = $baseEmail . $counter . '@kepala.mi-alghazali.sch.id';
          $counter++;
        }

        $user = User::create([
          'name' => $teacher->name,
          'email' => $email,
          'nip' => $teacher->nip,
          'password' => Hash::make($request->password),
          'plain_password' => $request->password,
          'role' => 'kepala',
          'must_change_password' => true,
        ]);

        // Link user to teacher
        $teacher->update([
          'user_id' => $user->id,
          'plain_password' => $request->password,
        ]);
      } elseif ($request->role === 'siswa') {
        // Create account from existing student data
        $student = Student::findOrFail($request->person_id);

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
        $student = Student::findOrFail($request->student_id);

        // Generate email from parent name
        $baseEmail = strtolower(str_replace(' ', '.', $request->name));
        $email = $baseEmail . '@wali.mi-alghazali.sch.id';
        $counter = 1;
        while (User::where('email', $email)->exists()) {
          $email = $baseEmail . $counter . '@wali.mi-alghazali.sch.id';
          $counter++;
        }

        $user = User::create([
          'name' => $request->name,
          'email' => $email,
          'student_id' => $student->id,
          'password' => Hash::make($request->password),
          'plain_password' => $request->password,
          'role' => 'wali_murid',
          'must_change_password' => true,
        ]);
      } elseif ($request->role === 'tu') {
        // Create account from existing teacher data (for TU)
        $teacher = Teacher::findOrFail($request->person_id);

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
      }
    });

    return response()->json(['message' => 'Akun berhasil ditambahkan!', 'user' => $user], 201);
  }

  public function updateUser(Request $request, User $user)
  {
    $request->validate([
      'name' => 'required|string|max:255',
      'email' => 'required|email|unique:users,email,' . $user->id,
      'role' => 'required|in:admin,guru,siswa,wali_murid,bendahara,tu,kepala',
      'student_id' => $request->role === 'wali_murid' ? 'required|integer|exists:students,id' : 'nullable|integer',
    ]);

    $data = $request->only(['name', 'email', 'role']);

    if ($request->filled('password')) {
      $data['password'] = Hash::make($request->password);
      $data['plain_password'] = $request->password;
    }

    $user->update($data);

    return response()->json(['message' => 'User berhasil diperbarui!', 'user' => $user]);
  }

  public function deleteUser(User $user)
  {
    $user->delete();

    return response()->json(['message' => 'User berhasil dihapus!']);
  }

  // ==========================================
  // PROFILE SECTIONS
  // ==========================================

  public function getProfileSection($key)
  {
    return response()->json(ProfileSection::where('key', $key)->first());
  }

  public function updateProfileSection(Request $request, $key)
  {
    $section = ProfileSection::where('key', $key)->first();

    if ($section) {
      $section->update($request->all());
    } else {
      $section = ProfileSection::create(array_merge(['key' => $key], $request->all()));
    }

    return response()->json(['message' => 'Profil berhasil diperbarui!', 'section' => $section]);
  }

  // ==========================================
  // GURU ROUTES
  // ==========================================

  public function guruAttendanceCreate()
  {
    return response()->json(['message' => 'Ready to submit attendance']);
  }

  public function guruAttendanceStore(Request $request)
  {
    $request->validate([
      'date' => 'required|date',
      'status' => 'required|in:hadir,izin,sakit',
    ]);

    $teacher = auth()->user()->teacher;

    if (!$teacher) {
      return response()->json(['message' => 'Data guru tidak ditemukan'], 404);
    }

    Attendance::updateOrCreate(
      ['teacher_id' => $teacher->id, 'date' => $request->date],
      ['status' => $request->status, 'time' => now()->format('H:i:s')]
    );

    return response()->json(['message' => 'Kehadiran berhasil dicatat!']);
  }

  public function guruMyQr()
  {
    $teacher = auth()->user()->teacher;

    return response()->json([
      'teacher' => $teacher,
      'qr_data' => 'teacher-' . ($teacher->id ?? 0),
    ]);
  }

  // ==========================================
  // SISWA ROUTES
  // ==========================================

  public function mySchedules(Request $request)
  {
    $user = $request->user();
    $student = $user->student;

    // If user is student, they are the student. If wali_murid, they have a student relation.
    // Logic handles both as long as $user->student is set.

    if (!$student) {
      return response()->json(['schedules' => []]);
    }

    $schedules = Schedule::query()
      ->where(function ($q) use ($student) {
        // Match specific Class Room
        if ($student->class_room_id) {
          $q->orWhere('class_room_id', $student->class_room_id);
        }
        // Match Grade
        if ($student->grade) {
          $q->orWhere('grade', $student->grade);
        }
        // Match Global (No Class, No Grade)
        $q->orWhere(function ($sub) {
          $sub->whereNull('class_room_id')->whereNull('grade');
        });
      })
      ->with('classRoom')
      ->latest()
      ->get();

    return response()->json(['schedules' => $schedules]);
  }

  public function siswaDonations()
  {
    return response()->json(Donation::where('user_id', auth()->id())->latest()->get());
  }

  public function siswaDonationStore(Request $request)
  {
    return $this->storeDonation($request);
  }

  // ==========================================
  // MEDIA LIBRARY
  // ==========================================

  public function mediaIndex()
  {
    $files = \Illuminate\Support\Facades\Storage::disk('public')->files('media');

    $media = array_map(function ($file) {
      return [
        'name' => basename($file),
        'path' => '/storage/' . $file,
        'url' => asset('storage/' . $file),
        'size' => \Illuminate\Support\Facades\Storage::disk('public')->size($file),
      ];
    }, $files);

    return response()->json($media);
  }

  public function mediaStore(Request $request)
  {
    $request->validate([
      'file' => 'required|file|max:5120',
    ]);

    $path = $request->file('file')->store('media', 'public');

    return response()->json([
      'message' => 'File berhasil diupload!',
      'path' => '/storage/' . $path,
      'url' => asset('storage/' . $path),
    ], 201);
  }

  public function mediaDestroy(Request $request)
  {
    $request->validate([
      'path' => 'required|string',
    ]);

    $path = str_replace('/storage/', '', $request->path);
    \Illuminate\Support\Facades\Storage::disk('public')->delete($path);

    return response()->json(['message' => 'File berhasil dihapus!']);
  }
}
