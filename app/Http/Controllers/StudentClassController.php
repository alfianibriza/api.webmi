<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentClassController extends Controller
{
    /**
     * Get students not enrolled in any class for the specified academic year.
     */
    public function getCandidates(Request $request)
    {
        $request->validate([
            'academic_year_id' => 'required|exists:academic_years,id',
            'grade' => 'nullable|string' // Optional filter by grade
        ]);

        $academicYearId = $request->academic_year_id;

        $query = Student::where('status', 'active')
            ->whereDoesntHave('classrooms', function ($q) use ($academicYearId) {
                // Check pivot table via the relationship
                $q->whereHas('academicYear', function ($y) use ($academicYearId) {
                    $y->where('id', $academicYearId);
                })
                    ->whereIn('classroom_student.status', ['active', 'transferred']);
            });

        // Optional: Filter by 'grade' if we want to limit candidates to a specific level (e.g. only grade 1 students for Class 1A)
        // However, 'grade' in students table might be outdated or just 'newly admitted'. 
        // Let's allow filtering if requested.
        if ($request->filled('grade')) {
            $query->where('grade', $request->grade);
        }

        return response()->json($query->orderBy('name')->get(['id', 'name', 'nis', 'grade', 'image']));
    }

    /**
     * Store students into a class (Enrollment).
     */
    public function store(Request $request)
    {
        $request->validate([
            'kelas_id' => 'required|exists:kelas,id',
            'student_ids' => 'required|array',
            'student_ids.*' => 'exists:students,id',
        ]);

        $kelas = Kelas::with('academicYear')->findOrFail($request->kelas_id);
        $academicYearId = $kelas->academic_year_id;

        DB::beginTransaction();
        try {
            $enrolledCount = 0;
            $skippedCount = 0;
            $errors = [];

            foreach ($request->student_ids as $studentId) {
                // 1. Check if student is already in ANY class for this academic year
                $existingEnrollment = DB::table('classroom_student')
                    ->join('kelas', 'classroom_student.kelas_id', '=', 'kelas.id')
                    ->where('classroom_student.student_id', $studentId)
                    ->where('kelas.academic_year_id', $academicYearId)
                    ->whereIn('classroom_student.status', ['active', 'transferred']) // status checklist
                    ->exists();

                if ($existingEnrollment) {
                    $student = Student::find($studentId);
                    $errors[] = "Siswa {$student->name} sudah terdaftar di kelas lain pada tahun ajaran ini.";
                    $skippedCount++;
                    continue;
                }

                // 2. Enroll student
                $kelas->students()->attach($studentId, ['status' => 'active']);

                // 3. Update 'grade' column in students table (legacy/convenience)
                // We use the 'level' from 'tingkat' relation of the class
                $student = Student::find($studentId);
                if ($kelas->tingkat) {
                    $student->grade = $kelas->tingkat->level; // e.g., '1', '2'
                    $student->save();
                }

                $enrolledCount++;
            }

            if ($enrolledCount === 0 && count($errors) > 0) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Gagal menambahkan siswa.',
                    'errors' => $errors
                ], 422);
            }

            DB::commit();

            return response()->json([
                'message' => "Berhasil menambahkan {$enrolledCount} siswa. Diabaikan: {$skippedCount}.",
                'errors' => $errors // Optional info
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Terjadi kesalahan sistem', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove student from class (Drop/Delete from pivot).
     */
    public function destroy(Kelas $kelas, Student $student)
    {
        $kelas->students()->detach($student->id);
        return response()->json(['message' => 'Siswa berhasil dihapus dari kelas.']);
    }
}
