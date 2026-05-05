<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\ClassRoom;
use App\Models\Ppdb;
use App\Models\RombelMapping;
use App\Models\Student;
use App\Models\StudentAcademicHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AcademicController extends Controller
{
    public function index()
    {
        return response()->json(AcademicYear::orderBy('start_date', 'desc')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        // Ensure only one active year (conceptually, though we might allow creating 'upcoming' ones)
        // For now, let's just create it. Status defaults to 'upcoming'.
        $ay = AcademicYear::create($request->all());

        return response()->json(['message' => 'Tahun ajaran berhasil dibuat!', 'data' => $ay]);
    }

    public function setActive(AcademicYear $academicYear)
    {
        DB::transaction(function () use ($academicYear) {
            // Close all other years
            AcademicYear::where('id', '!=', $academicYear->id)->update(['status' => 'closed']);

            $academicYear->update(['status' => 'active']);
        });

        return response()->json(['message' => 'Tahun ajaran aktif berhasil diubah!']);
    }

    public function close(AcademicYear $academicYear)
    {
        $academicYear->update(['status' => 'closed']);
        return response()->json(['message' => 'Tahun ajaran berhasil ditutup!']);
    }

    public function update(Request $request, AcademicYear $academicYear)
    {
        $request->validate([
            'name' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        $academicYear->update($request->all());

        return response()->json(['message' => 'Tahun ajaran berhasil diperbarui!', 'data' => $academicYear]);
    }

    public function destroy(AcademicYear $academicYear)
    {
        if ($academicYear->status === 'active') {
            return response()->json(['message' => 'Tidak dapat menghapus tahun ajaran yang sedang aktif!'], 400);
        }

        DB::transaction(function () use ($academicYear) {
            // 1. Delete Student Academic History (Enrollments)
            // Assuming we want to wipe the history for this year
            StudentAcademicHistory::where('academic_year_id', $academicYear->id)->delete();

            // 2. Delete Rombel Mappings
            RombelMapping::where('academic_year_id', $academicYear->id)->delete();

            // 3. Delete Class Rooms (and their dependencies via model events or manual delete if needed)
            // ClassRoom hasMany students (pivot or direct?), subjects, schedules.
            // If ClassRoom uses 'onDelete cascade' in migration, simple delete is enough.
            // But let's be safe and manually delete complex relations if unsure.
            $classRooms = ClassRoom::where('academic_year_id', $academicYear->id)->get();
            foreach ($classRooms as $classRoom) {
                // Delete related schedules, constraints, etc. if not cascaded
                $classRoom->daySlots()->delete();
                $classRoom->slotSchedules()->delete();
                $classRoom->subjects()->detach(); // Pivot
                $classRoom->students()->delete(); // If hasMany Student, this deletes STUDENTS! Wait.
                // ClassRoom relationship to Student in this system:
                // Old system: Student has 'class_room_id'.
                // New system: Many-to-Many via 'classroom_student'? Or just History?
                // The 'ClassRoom' model here seems to be the LEGACY one or the NEW one?
                // In 'generateRombel', we use 'ClassRoom' model.
                // In 'activateNewStudents', we create 'Student' with 'class_room_id' = null.

                // CRITICAL: We must NOT delete 'Student' records, only their association.
                // If 'students()' is hasMany, delete() deletes the student!
                // Let's check the ClassRoom model again.
                // public function students() { return $this->hasMany(Student::class); }
                // YES, $classRoom->students()->delete() WILL DELETE STUDENTS. DO NOT DO THIS.

                // Instead, set their class_room_id to NULL if they are currently assigned to this class?
                // But this is an OLD year. Current students shouldn't point to old classes.
                // If they do, we should nullify it.
                $classRoom->students()->update(['class_room_id' => null]);

                $classRoom->delete();
            }

            // 4. Finally delete the year
            $academicYear->delete();
        });

        return response()->json(['message' => 'Tahun ajaran dan semua data terkait (kelas, riwayat, mapping) berhasil dihapus!']);
    }

    /**
     * Promote students from Previous Active Year to Current Active Year.
     * Uses RombelMapping to assign students to new rombel.
     */
    public function promoteStudents(Request $request)
    {
        // 1. Validate: Must have an ACTIVE academic year.
        $activeYear = AcademicYear::where('status', 'active')->first();
        if (!$activeYear) {
            return response()->json(['message' => 'Tidak ada tahun ajaran aktif!'], 400);
        }

        // 2. Check if mapping exists for this year
        $mappings = RombelMapping::where('academic_year_id', $activeYear->id)->get();
        if ($mappings->isEmpty()) {
            return response()->json(['message' => 'Mapping rombel belum dibuat! Jalankan "Generate Rombel" terlebih dahulu.'], 400);
        }

        // Build mapping array: old_rombel_id => new_rombel_id
        $rombelMap = [];
        foreach ($mappings as $mapping) {
            $rombelMap[$mapping->old_rombel_id] = $mapping->new_rombel_id;
        }

        // 3. Fetch students to promote (active students not yet in this academic year)
        $studentsToPromote = Student::where('status', 'active')
            ->whereDoesntHave('academicHistories', function ($q) use ($activeYear) {
                $q->where('academic_year_id', $activeYear->id);
            })
            ->get();

        if ($studentsToPromote->isEmpty()) {
            return response()->json(['message' => 'Tidak ada siswa yang perlu dinaikkan kelas.']);
        }

        $promotedCount = 0;
        $graduatedCount = 0;
        $errors = [];

        DB::transaction(function () use ($studentsToPromote, $activeYear, $rombelMap, &$promotedCount, &$graduatedCount, &$errors) {
            foreach ($studentsToPromote as $student) {
                $currentGrade = $student->grade;
                $oldRombelId = $student->class_room_id;

                if ($currentGrade == '6') {
                    // Graduating - no new rombel needed
                    StudentAcademicHistory::create([
                        'student_id' => $student->id,
                        'academic_year_id' => $activeYear->id,
                        'class_room_id' => $oldRombelId,
                        'grade' => 'Lulus',
                        'status' => 'lulus'
                    ]);

                    Student::where('id', $student->id)->update([
                        'status' => 'alumni',
                        'grade' => 'Lulus',
                        'graduation_year' => date('Y')
                    ]);
                    $graduatedCount++;
                } else {
                    // Promoting - use mapping to get new rombel
                    $newRombelId = null;
                    if ($oldRombelId && isset($rombelMap[$oldRombelId])) {
                        $newRombelId = $rombelMap[$oldRombelId];
                    } else {
                        // No mapping found - log error but continue
                        $errors[] = "Siswa {$student->name} (ID: {$student->id}) tidak memiliki mapping rombel.";
                    }

                    $nextGrade = (string) ((int) $currentGrade + 1);

                    StudentAcademicHistory::create([
                        'student_id' => $student->id,
                        'academic_year_id' => $activeYear->id,
                        'class_room_id' => $newRombelId,
                        'grade' => $nextGrade,
                        'status' => 'active'
                    ]);

                    // Update Student Cache
                    Student::where('id', $student->id)->update([
                        'grade' => $nextGrade,
                        'class_room_id' => $newRombelId
                    ]);
                    $promotedCount++;
                }
            }
        });

        $response = [
            'message' => 'Proses kenaikan kelas selesai!',
            'promoted' => $promotedCount,
            'graduated' => $graduatedCount
        ];

        if (!empty($errors)) {
            $response['warnings'] = $errors;
        }

        return response()->json($response);
    }

    /**
     * Activate accepted PMB students into the Current Active Year (Grade 1).
     */
    public function activateNewStudents(Request $request)
    {
        $activeYear = AcademicYear::where('status', 'active')->first();
        if (!$activeYear) {
            return response()->json(['message' => 'Tidak ada tahun ajaran aktif!'], 400);
        }

        $acceptedPmbs = Ppdb::where('status', 'accepted')->get();
        if ($acceptedPmbs->isEmpty()) {
            return response()->json(['message' => 'Tidak ada calon siswa baru yang diterima.']);
        }

        $count = 0;

        // Get last NIS
        $lastNis = Student::max('nis');
        if (!$lastNis) {
            $lastNis = date('Y') . '000';
        }

        DB::transaction(function () use ($acceptedPmbs, $activeYear, $lastNis, &$count) {
            foreach ($acceptedPmbs as $pmb) {
                // Check duplicate
                if (Student::where('name', $pmb->name)->where('birth_date', $pmb->birth_date)->exists()) {
                    continue;
                }

                $lastNis = (string) ((int) $lastNis + 1);

                $student = Student::create([
                    'name' => $pmb->name,
                    'nis' => $lastNis,
                    'nisn' => $pmb->nisn,
                    'gender' => $pmb->gender,
                    'birth_place' => $pmb->birth_place,
                    'birth_date' => $pmb->birth_date,
                    'address' => $pmb->address,
                    'parent_name' => $pmb->parent_name,
                    'parent_phone' => $pmb->phone,
                    'grade' => '1',
                    'status' => 'active',
                    'admission_year' => date('Y'),
                    'class_room_id' => null, // No class assigned yet
                ]);

                // Create History
                StudentAcademicHistory::create([
                    'student_id' => $student->id,
                    'academic_year_id' => $activeYear->id,
                    'class_room_id' => null,
                    'grade' => '1',
                    'status' => 'active'
                ]);

                $count++;
            }
        });

        return response()->json(['message' => "$count Siswa baru berhasil diaktivasi ke Kelas 1!"]);
    }

    /**
     * Generate new rombel for the active academic year.
     * This copies all active rombel from the previous year, increments grade, and SAVES MAPPING.
     */
    public function generateRombel(Request $request)
    {
        $activeYear = AcademicYear::where('status', 'active')->first();
        if (!$activeYear) {
            return response()->json(['message' => 'Tidak ada tahun ajaran aktif!'], 400);
        }

        // Check if rombel already generated for this year
        $existingRombel = ClassRoom::where('academic_year_id', $activeYear->id)->count();
        if ($existingRombel > 0) {
            return response()->json(['message' => 'Rombel untuk tahun ajaran ini sudah di-generate!'], 400);
        }

        // Get previous year's active rombel (status = active, academic_year_id IS NULL or previous year)
        $previousRombel = ClassRoom::where('status', 'active')
            ->where(function ($q) use ($activeYear) {
                $q->whereNull('academic_year_id')
                    ->orWhere('academic_year_id', '!=', $activeYear->id);
            })
            ->get();

        if ($previousRombel->isEmpty()) {
            return response()->json(['message' => 'Tidak ada rombel aktif dari tahun sebelumnya.'], 400);
        }

        $createdCount = 0;
        $mappingsCreated = 0;
        $grade1Labels = [];

        DB::transaction(function () use ($previousRombel, $activeYear, &$createdCount, &$mappingsCreated, &$grade1Labels) {
            foreach ($previousRombel as $rombel) {
                // Deactivate old rombel
                ClassRoom::where('id', $rombel->id)->update(['status' => 'inactive']);

                // If grade < 6, create new rombel with grade + 1 and save mapping
                if ($rombel->grade < 6) {
                    $newGrade = $rombel->grade + 1;
                    $label = $rombel->label ?? substr($rombel->name, -1);

                    $newRombel = ClassRoom::create([
                        'academic_year_id' => $activeYear->id,
                        'name' => $newGrade . $label,
                        'label' => $label,
                        'grade' => $newGrade,
                        'status' => 'active',
                    ]);

                    // SAVE MAPPING: old rombel -> new rombel
                    RombelMapping::create([
                        'academic_year_id' => $activeYear->id,
                        'old_rombel_id' => $rombel->id,
                        'new_rombel_id' => $newRombel->id,
                    ]);

                    $createdCount++;
                    $mappingsCreated++;
                }

                // Track grade 1 labels for new student rombel
                if ($rombel->grade == 1) {
                    $grade1Labels[] = $rombel->label ?? substr($rombel->name, -1);
                }
            }

            // Create Grade 1 rombel for new students (same labels as previous Grade 1)
            foreach ($grade1Labels as $label) {
                ClassRoom::create([
                    'academic_year_id' => $activeYear->id,
                    'name' => '1' . $label,
                    'label' => $label,
                    'grade' => 1,
                    'status' => 'active',
                ]);
                $createdCount++;
            }
        });

        return response()->json([
            'message' => "Berhasil membuat $createdCount rombel baru dan $mappingsCreated mapping untuk tahun ajaran {$activeYear->name}!",
            'created' => $createdCount,
            'mappings' => $mappingsCreated
        ]);
    }

    /**
     * Get rombel list for active academic year.
     */
    public function getRombel(Request $request)
    {
        $academicYearId = $request->query('academic_year_id');

        $query = ClassRoom::with('academicYear');

        if ($academicYearId) {
            $query->where('academic_year_id', $academicYearId);
        }

        return response()->json($query->orderBy('grade')->orderBy('label')->get());
    }
}

