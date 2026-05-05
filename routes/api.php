<?php

use App\Http\Controllers\ApiController;
use App\Http\Controllers\AcademicController;
use App\Http\Controllers\KelasController;
use App\Http\Controllers\StudentClassController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// ==========================================
// PUBLIC ENDPOINTS (No Auth Required)
// ==========================================

// Home Page Data
Route::get('/home', [ApiController::class, 'home']);
Route::get('/slider-images', [ApiController::class, 'sliderImages']);

// Profile Madrasah
Route::get('/profile', [ApiController::class, 'profile']);
Route::get('/teachers', [ApiController::class, 'teachers']);
Route::get('/extracurriculars', [ApiController::class, 'extracurriculars']);
Route::get('/sarpras', [ApiController::class, 'sarpras']);
Route::get('/achievements', [ApiController::class, 'achievements']);
Route::get('/achievements/{achievement}', [ApiController::class, 'achievementDetail']);

// Posts/News
Route::get('/posts', [ApiController::class, 'posts']);
Route::get('/posts/{post:slug}', [ApiController::class, 'postDetail']);

// PMB/PPDB
Route::get('/ppdb-info', [ApiController::class, 'ppdbInfo']);

// Kesiswaan (Public)
Route::get('/kesiswaan', [ApiController::class, 'kesiswaan']);
Route::get('/students-public', [ApiController::class, 'publicStudents']);

// Contact Settings (Public - for footer)
Route::get('/contact-settings', [ApiController::class, 'publicContactSettings']);

// ===========================================
// AUTHENTICATION ENDPOINTS
// ==========================================

Route::post('/login', [ApiController::class, 'login']);
Route::post('/logout', [ApiController::class, 'logout'])->middleware('auth:sanctum');

// ==========================================
// PROTECTED ENDPOINTS (Auth Required)
// ==========================================

Route::middleware('auth:sanctum')->group(function () {
    // Current User
    Route::get('/user', function (Request $request) {
        return $request->user(); // Removed load(['teacher', 'student.classRoom']) as tables are dropped
    });
    Route::put('/profile/update', [ApiController::class, 'updateProfile']);
    Route::get('/active-academic-year', [ApiController::class, 'getActiveAcademicYear']);

    // Dashboard Stats
    Route::get('/dashboard/stats', [ApiController::class, 'dashboardStats']);
    Route::get('/dashboard/bendahara-stats', [\App\Http\Controllers\ApiController::class, 'bendaharaDashboard']); // Added for Bendahara

    // ==========================================
    // ADMIN & GURU ROUTES
    // ==========================================
    Route::middleware('role:admin,guru,tu,kepala')->group(function () {
        // Posts CRUD
        Route::get('/admin/posts', [ApiController::class, 'adminPosts']);
        Route::post('/admin/posts', [ApiController::class, 'storePost']);
        Route::get('/admin/posts/{post}', [ApiController::class, 'editPost']);
        Route::put('/admin/posts/{post}', [ApiController::class, 'updatePost']);
        Route::delete('/admin/posts/{post}', [ApiController::class, 'deletePost']);

        // Kesiswaan/Students CRUD
        Route::get('/admin/students', [ApiController::class, 'adminStudents']);
        Route::post('/admin/students', [ApiController::class, 'storeStudent']);
        Route::get('/admin/students/{student}', [ApiController::class, 'editStudent']);
        Route::put('/admin/students/{student}', [ApiController::class, 'updateStudent']);
        Route::delete('/admin/students/{student}', [ApiController::class, 'deleteStudent']);
        Route::post('/admin/students/promote', [ApiController::class, 'promoteStudents']);
        Route::post('/admin/students/demote', [ApiController::class, 'demoteStudents']);

        // Class Rooms
        Route::get('/admin/class-rooms', [ApiController::class, 'classRooms']);
        Route::post('/admin/class-rooms', [ApiController::class, 'storeClassRoom']);
        Route::put('/admin/class-rooms/{classRoom}', [ApiController::class, 'updateClassRoom']);
        Route::delete('/admin/class-rooms/{classRoom}', [ApiController::class, 'deleteClassRoom']);

        // Student Attendance
        Route::get('/admin/student-attendance', [ApiController::class, 'studentAttendance']);
        Route::post('/admin/student-attendance', [ApiController::class, 'storeStudentAttendance']);
        Route::get('/admin/student-attendance/report', [ApiController::class, 'studentAttendanceReport']);
        Route::get('/admin/student-attendance/export', [ApiController::class, 'exportStudentAttendance']);
        Route::get('/admin/attendance', [ApiController::class, 'attendance']);

        // Teacher Attendance
        Route::get('/admin/ptk-attendance', [ApiController::class, 'adminPtkAttendance']);
        Route::post('/admin/ptk-attendance', [ApiController::class, 'storeAdminPtkAttendance']);
        Route::get('/admin/ptk-attendance/report', [ApiController::class, 'adminPtkAttendanceReport']);

        // PTK (Teachers)
        Route::get('/admin/ptk', [ApiController::class, 'adminPtk']);
        Route::post('/admin/ptk', [ApiController::class, 'storePtk']);
        Route::put('/admin/ptk/{teacher}', [ApiController::class, 'updatePtk']);
        Route::delete('/admin/ptk/{teacher}', [ApiController::class, 'deletePtk']);

        // Users Management
        Route::get('/admin/users', [ApiController::class, 'adminUsers']);
        Route::get('/admin/users/export', [ApiController::class, 'adminUsersExport']);
        // Unregistered teachers/students routes rely on teachers/students tables - Comment out
        // Route::get('/admin/users/unregistered-teachers', [ApiController::class, 'getUnregisteredTeachers']);
        // Route::get('/admin/users/unregistered-students', [ApiController::class, 'getUnregisteredStudents']);

        // Media Library
        Route::get('/admin/media', [ApiController::class, 'mediaIndex']);
        Route::post('/admin/media', [ApiController::class, 'mediaStore']);
        Route::delete('/admin/media', [ApiController::class, 'mediaDestroy']);

        // Alumni Management
        Route::apiResource('/admin/alumni', \App\Http\Controllers\AlumniController::class);

        // Get Existing Roles
        Route::get('/admin/roles', [ApiController::class, 'getExistingRoles']);
    });

    // Shared Report Route (Admin, Guru, TU, Kepala, Bendahara)
    Route::get('/admin/ptk-attendance/report', [ApiController::class, 'adminPtkAttendanceReport'])
        ->middleware('role:admin,guru,tu,kepala,bendahara');

    // Shared Class Rooms List (Admin, Guru, TU, Kepala, Bendahara)
    Route::get('/admin/class-rooms', [ApiController::class, 'classRooms'])
        ->middleware('role:admin,guru,tu,kepala,bendahara');

    // Shared Students List (Admin, Guru, TU, Kepala, Bendahara)
    Route::get('/admin/students', [ApiController::class, 'adminStudents'])
        ->middleware('role:admin,guru,tu,kepala,bendahara');

    // ==========================================
    // ADMIN ONLY ROUTES
    // ==========================================
    Route::middleware('role:admin,tu,kepala')->group(function () {
        // Sarpras CRUD
        Route::apiResource('/admin/sarpras', ApiController::class . '@sarpras');
        Route::get('/admin/sarpras', [ApiController::class, 'adminSarpras']);
        Route::post('/admin/sarpras', [ApiController::class, 'storeSarpras']);
        Route::put('/admin/sarpras/{sarpras}', [ApiController::class, 'updateSarpras']);
        Route::delete('/admin/sarpras/{sarpras}', [ApiController::class, 'deleteSarpras']);

        // PMB/PPDB CRUD
        Route::get('/admin/pmb', [ApiController::class, 'adminPmb']);
        Route::post('/admin/pmb', [ApiController::class, 'storePmb']);
        Route::get('/admin/pmb/{pmb}', [ApiController::class, 'showPmb']);
        Route::put('/admin/pmb/{pmb}', [ApiController::class, 'updatePmb']);
        Route::delete('/admin/pmb/{pmb}', [ApiController::class, 'deletePmb']);
        Route::get('/admin/pmb/info', [ApiController::class, 'adminPmbInfo']);
        Route::post('/admin/pmb/info', [ApiController::class, 'updatePmbInfo']);

        // Academic Year & Promotion
        Route::get('/admin/academic-years', [AcademicController::class, 'index']);
        Route::post('/admin/academic-years', [AcademicController::class, 'store']);
        Route::put('/admin/academic-years/{academicYear}', [AcademicController::class, 'update']);
        Route::delete('/admin/academic-years/{academicYear}', [AcademicController::class, 'destroy']);
        Route::post('/admin/academic-years/{academicYear}/active', [AcademicController::class, 'setActive']);
        Route::post('/admin/academic-years/{academicYear}/close', [AcademicController::class, 'close']);

        // Kelas Management
        Route::get('/admin/kelas/detail', [KelasController::class, 'index']);
        Route::post('/admin/kelas/detail', [KelasController::class, 'store']);
        Route::put('/admin/kelas/detail/{kelas}', [KelasController::class, 'update']);
        Route::delete('/admin/kelas/detail/{kelas}', [KelasController::class, 'destroy']);

        // Enrollment
        Route::get('/kelas/candidates', [StudentClassController::class, 'getCandidates']);
        Route::post('/kelas/enroll', [StudentClassController::class, 'enrollStudents']);
        Route::delete('/kelas/{kelasId}/students/{studentId}', [StudentClassController::class, 'removeStudent']);

        // Philosophy CRUD
        Route::get('/admin/philosophy', [ApiController::class, 'adminPhilosophy']);
        Route::post('/admin/philosophy', [ApiController::class, 'storePhilosophy']);
        Route::put('/admin/philosophy/{philosophy}', [ApiController::class, 'updatePhilosophy']);
        Route::delete('/admin/philosophy/{philosophy}', [ApiController::class, 'deletePhilosophy']);

        // Achievement CRUD
        Route::get('/admin/achievements', [ApiController::class, 'adminAchievements']);
        Route::post('/admin/achievements', [ApiController::class, 'storeAchievement']);
        Route::put('/admin/achievements/{achievement}', [ApiController::class, 'updateAchievement']);
        Route::delete('/admin/achievements/{achievement}', [ApiController::class, 'deleteAchievement']);

        // Extracurricular CRUD
        Route::get('/admin/extracurriculars', [ApiController::class, 'adminExtracurriculars']);
        Route::post('/admin/extracurriculars', [ApiController::class, 'storeExtracurricular']);
        Route::put('/admin/extracurriculars/{extracurricular}', [ApiController::class, 'updateExtracurricular']);
        Route::delete('/admin/extracurriculars/{extracurricular}', [ApiController::class, 'deleteExtracurricular']);

        // Profile School Settings (Sejarah, Visi Misi, Proker Kepala)
        Route::get('/admin/profile-school/{key}', [ApiController::class, 'getProfileSection']);
        Route::patch('/admin/profile-school/{key}', [ApiController::class, 'updateProfileSection']);
    });

    // ==========================================
    // ADMIN & BENDAHARA ROUTES
    // ==========================================
    Route::middleware('role:admin,bendahara,kepala')->group(function () {
        // Donation Management
        Route::get('/admin/donations', [ApiController::class, 'adminDonations']);
        Route::get('/admin/donations/settings', [ApiController::class, 'donationSettings']);
        Route::post('/admin/donations/settings', [ApiController::class, 'updateDonationSettings']);
        Route::patch('/admin/donations/{donation}', [ApiController::class, 'updateDonationStatus']);
        Route::delete('/admin/donations/{donation}', [ApiController::class, 'deleteDonation']);

        // Financial Obligations (Tanggungan)
        Route::get('/admin/financial-obligations', [\App\Http\Controllers\FinancialObligationController::class, 'index']);
        Route::post('/admin/financial-obligations', [\App\Http\Controllers\FinancialObligationController::class, 'store']);
        Route::get('/admin/financial-obligations/create', [\App\Http\Controllers\FinancialObligationController::class, 'create']);
        Route::get('/admin/financial-obligations/{financialObligation}', [\App\Http\Controllers\FinancialObligationController::class, 'show']);
        Route::put('/admin/financial-obligations/{financialObligation}', [\App\Http\Controllers\FinancialObligationController::class, 'update']);
        Route::delete('/admin/financial-obligations/{financialObligation}', [\App\Http\Controllers\FinancialObligationController::class, 'destroy']);
        Route::post('/admin/student-obligations/{studentObligation}/verify', [\App\Http\Controllers\FinancialObligationController::class, 'verify']);



        // Users CRUD
        Route::post('/admin/users', [ApiController::class, 'storeUser']);
        Route::put('/admin/users/{user}', [ApiController::class, 'updateUser']);
        Route::delete('/admin/users/{user}', [ApiController::class, 'deleteUser']);

        // Task Guru (Admin)
        Route::get('/admin/tasks', [\App\Http\Controllers\TaskController::class, 'index']);
        Route::post('/admin/tasks', [\App\Http\Controllers\TaskController::class, 'store']);
        Route::get('/admin/tasks/{task}', [\App\Http\Controllers\TaskController::class, 'show']);
        Route::put('/admin/tasks/{task}', [\App\Http\Controllers\TaskController::class, 'update']);
        Route::delete('/admin/tasks/{task}', [\App\Http\Controllers\TaskController::class, 'destroy']);
        Route::post('/admin/task-assignees/{assignee}/verify', [\App\Http\Controllers\TaskController::class, 'verifySubmission']);

        // PTK Attendance Report (for Bendahara)
        // PTK Attendance Report (Moved to shared route)
    });

    // ==========================================
    // GURU ONLY ROUTES
    // ==========================================
    Route::middleware('role:guru')->group(function () {
        // Teacher Attendance (New System)
        Route::get('/guru/attendance', [\App\Http\Controllers\AttendanceController::class, 'index']);
        Route::post('/guru/attendance/check-in', [\App\Http\Controllers\AttendanceController::class, 'checkIn']);
        Route::post('/guru/attendance/check-out', [\App\Http\Controllers\AttendanceController::class, 'checkOut']);
        Route::get('/guru/my-qr', [ApiController::class, 'guruMyQr']);

        // Task Guru (Guru)
        Route::get('/guru/tasks', [\App\Http\Controllers\GuruTaskController::class, 'index']);
        Route::post('/guru/task-assignees/{assignee}/submit', [\App\Http\Controllers\GuruTaskController::class, 'submit']);
    });

    // ==========================================
    // ADMIN - TEACHER ATTENDANCE APPROVAL
    // ==========================================
    Route::middleware('role:admin,kepala')->group(function () {
        Route::get('/admin/teacher-attendance', [\App\Http\Controllers\AttendanceController::class, 'index']);
        Route::post('/admin/teacher-attendance/{attendance}/approve', [\App\Http\Controllers\AttendanceController::class, 'approve']);
        Route::post('/admin/teacher-attendance/{attendance}/reject', [\App\Http\Controllers\AttendanceController::class, 'reject']);
        Route::get('/admin/teacher-attendance/settings', [\App\Http\Controllers\AttendanceController::class, 'getSettings']);
        Route::post('/admin/teacher-attendance/settings', [\App\Http\Controllers\AttendanceController::class, 'updateSettings']);

        // Teacher Performance Stats
        Route::get('/admin/teacher-performance', [\App\Http\Controllers\TeacherPerformanceController::class, 'index']);
    });

    // ==========================================
    // SISWA ROUTES
    // ==========================================
    Route::middleware('role:siswa,wali_murid')->group(function () {
        Route::get('/my-schedules', [ApiController::class, 'mySchedules']);
    });

    Route::middleware('role:siswa')->group(function () {
        // Route::get('/siswa/schedule', [ApiController::class, 'siswaSchedule']); // Replaced by my-schedules
        Route::get('/siswa/donations', [ApiController::class, 'siswaDonations']);
        Route::post('/siswa/donations', [ApiController::class, 'siswaDonationStore']);

        // Student Financial Obligations
        Route::get('/siswa/financial-obligations', [\App\Http\Controllers\StudentFinancialController::class, 'index']);
        Route::post('/siswa/financial-obligations/{studentObligation}/pay', [\App\Http\Controllers\StudentFinancialController::class, 'pay']);


    });

    // Donations (All authenticated users)
    Route::get('/donations', [ApiController::class, 'donations']);
    Route::post('/donations', [ApiController::class, 'storeDonation']);
    Route::get('/donation-settings', [ApiController::class, 'donationSettings']);

    // Parent Dashboard Data
    Route::get('/parent/dashboard', [ApiController::class, 'parentDashboard']);

    // ==========================================
    // SLOT SCHEDULE SYSTEM (NEW)
    // ==========================================

    Route::middleware('role:admin,kepala,tu')->group(function () {
        // Controllers do not exist in this project yet
        /*
        Route::get('/admin/subjects', [\App\Http\Controllers\SubjectController::class, 'index']);
        Route::post('/admin/subjects', [\App\Http\Controllers\SubjectController::class, 'store']);
        Route::put('/admin/subjects/{subject}', [\App\Http\Controllers\SubjectController::class, 'update']);
        Route::delete('/admin/subjects/{subject}', [\App\Http\Controllers\SubjectController::class, 'destroy']);
        Route::post('/admin/subjects/assign-teacher', [\App\Http\Controllers\SubjectController::class, 'assignTeacher']);
        Route::post('/admin/subjects/remove-teacher', [\App\Http\Controllers\SubjectController::class, 'removeTeacher']);

        Route::get('/admin/class-rooms/{classRoom}/subjects', [\App\Http\Controllers\ClassSubjectController::class, 'index']);
        Route::post('/admin/class-subjects', [\App\Http\Controllers\ClassSubjectController::class, 'store']);
        Route::delete('/admin/class-subjects/{classSubject}', [\App\Http\Controllers\ClassSubjectController::class, 'destroy']);

        Route::get('/admin/class-rooms/{classRoom}/day-slots', [\App\Http\Controllers\ClassDaySlotController::class, 'index']);
        Route::post('/admin/class-day-slots', [\App\Http\Controllers\ClassDaySlotController::class, 'store']);

        Route::get('/admin/teacher-constraints', [\App\Http\Controllers\TeacherClassConstraintController::class, 'index']);
        Route::post('/admin/teacher-constraints', [\App\Http\Controllers\TeacherClassConstraintController::class, 'store']);
        Route::delete('/admin/teacher-constraints/{constraint}', [\App\Http\Controllers\TeacherClassConstraintController::class, 'destroy']);

        Route::get('/admin/slot-schedules', [\App\Http\Controllers\SlotScheduleController::class, 'index']);
        Route::post('/admin/slot-schedules/generate', [\App\Http\Controllers\SlotScheduleController::class, 'generate']);
        Route::post('/admin/slot-schedules/move', [\App\Http\Controllers\SlotScheduleController::class, 'move']);
        Route::put('/admin/slot-schedules/{slotSchedule}', [\App\Http\Controllers\SlotScheduleController::class, 'update']);
        Route::delete('/admin/slot-schedules/{slotSchedule}', [\App\Http\Controllers\SlotScheduleController::class, 'destroy']);
        Route::post('/admin/slot-schedules/clear', [\App\Http\Controllers\SlotScheduleController::class, 'clear']);
        Route::get('/admin/slot-schedules/export', [\App\Http\Controllers\SlotScheduleController::class, 'export']);

        Route::get('/admin/swap-requests/pending', [\App\Http\Controllers\SwapRequestController::class, 'pending']);
        Route::post('/admin/swap-requests/{swapRequest}/approve', [\App\Http\Controllers\SwapRequestController::class, 'approve']);
        Route::post('/admin/swap-requests/{swapRequest}/reject', [\App\Http\Controllers\SwapRequestController::class, 'reject']);

        Route::get('/admin/activity-logs', [\App\Http\Controllers\ActivityLogController::class, 'index']);
        */
    });
    // ==========================================
    // ANNOUNCEMENTS & NOTIFICATIONS
    // ==========================================

    // Admin & Bendahara Routes (Announcements)
    Route::middleware('role:admin,bendahara,tu,kepala')->group(function () {
        Route::get('/admin/announcements', [\App\Http\Controllers\AnnouncementController::class, 'index']);
        Route::post('/admin/announcements', [\App\Http\Controllers\AnnouncementController::class, 'store']);
        Route::delete('/admin/announcements/{announcement}', [\App\Http\Controllers\AnnouncementController::class, 'destroy']);
    });

    // User Notifications (All Authenticated)
    Route::get('/notifications', [\App\Http\Controllers\AnnouncementController::class, 'myNotifications']);
    Route::post('/notifications/{id}/read', [\App\Http\Controllers\AnnouncementController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [\App\Http\Controllers\AnnouncementController::class, 'markAllAsRead']);

});

