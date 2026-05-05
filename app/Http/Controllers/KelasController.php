<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Kelas;
use App\Models\Rombel;
use App\Models\Student;
use App\Models\Tingkat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KelasController extends Controller
{
    /**
     * Get all Kelas for a specific academic year
     */
    public function index(Request $request)
    {
        $academicYearId = $request->query('academic_year_id');

        $query = Kelas::with(['tingkat', 'rombel', 'academicYear']);

        if ($academicYearId) {
            $query->where('academic_year_id', $academicYearId);
        }

        return response()->json($query->orderBy('tingkat_id')->orderBy('rombel_id')->get());
    }

    /**
     * Generate Kelas for active academic year
     */
    public function generateKelas(Request $request)
    {
        $activeYear = AcademicYear::where('status', 'active')->first();
        if (!$activeYear) {
            return response()->json(['message' => 'Tidak ada tahun ajaran aktif!'], 400);
        }

        $existingKelas = Kelas::where('academic_year_id', $activeYear->id)->count();
        if ($existingKelas > 0) {
            return response()->json(['message' => 'Kelas untuk tahun ajaran ini sudah di-generate!'], 400);
        }

        $tingkats = Tingkat::all();
        $rombels = Rombel::where('status', 'active')->get();

        if ($tingkats->isEmpty() || $rombels->isEmpty()) {
            return response()->json(['message' => 'Data Tingkat atau Rombel belum tersedia!'], 400);
        }

        $createdCount = 0;

        DB::transaction(function () use ($tingkats, $rombels, $activeYear, &$createdCount) {
            foreach ($tingkats as $tingkat) {
                foreach ($rombels as $rombel) {
                    Kelas::create([
                        'tingkat_id' => $tingkat->id,
                        'rombel_id' => $rombel->id,
                        'academic_year_id' => $activeYear->id,
                        'name' => $tingkat->level . $rombel->name,
                        'status' => 'active',
                    ]);
                    $createdCount++;
                }
            }
        });

        return response()->json([
            'message' => "Berhasil membuat $createdCount kelas untuk tahun ajaran {$activeYear->name}!",
            'created' => $createdCount
        ]);
    }

    /**
     * Promote students using new Kelas system
     */
    public function promoteStudents(Request $request)
    {
        $activeYear = AcademicYear::where('status', 'active')->first();
        if (!$activeYear) {
            return response()->json(['message' => 'Tidak ada tahun ajaran aktif!'], 400);
        }

        $kelasCount = Kelas::where('academic_year_id', $activeYear->id)->count();
        if ($kelasCount == 0) {
            return response()->json(['message' => 'Kelas belum di-generate! Jalankan "Generate Kelas" terlebih dahulu.'], 400);
        }

        $studentsToPromote = Student::where('status', 'active')
            ->whereHas('kelas', function ($q) use ($activeYear) {
                $q->where('academic_year_id', '!=', $activeYear->id);
            })
            ->orWhereNull('kelas_id')
            ->get();

        if ($studentsToPromote->isEmpty()) {
            return response()->json(['message' => 'Tidak ada siswa yang perlu dinaikkan kelas.']);
        }

        $promotedCount = 0;
        $graduatedCount = 0;
        $errors = [];

        DB::transaction(function () use ($studentsToPromote, $activeYear, &$promotedCount, &$graduatedCount, &$errors) {
            foreach ($studentsToPromote as $student) {
                $currentKelas = $student->kelas;
                if (!$currentKelas) {
                    $errors[] = "Siswa {$student->name} tidak memiliki kelas.";
                    continue;
                }

                $currentTingkat = $currentKelas->tingkat->level;
                $currentRombel = $currentKelas->rombel;

                if ($currentTingkat == 6) {
                    Student::where('id', $student->id)->update([
                        'status' => 'alumni',
                        'grade' => 'Lulus',
                        'graduation_year' => date('Y'),
                        'kelas_id' => null
                    ]);
                    $graduatedCount++;
                } else {
                    $nextTingkat = Tingkat::where('level', $currentTingkat + 1)->first();

                    if (!$nextTingkat) {
                        $errors[] = "Tingkat berikutnya tidak ditemukan untuk {$student->name}.";
                        continue;
                    }

                    $newKelas = Kelas::where('tingkat_id', $nextTingkat->id)
                        ->where('rombel_id', $currentRombel->id)
                        ->where('academic_year_id', $activeYear->id)
                        ->first();

                    if (!$newKelas) {
                        $errors[] = "Kelas baru tidak ditemukan untuk {$student->name}.";
                        continue;
                    }

                    Student::where('id', $student->id)->update([
                        'kelas_id' => $newKelas->id,
                        'grade' => (string) $nextTingkat->level
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
     * Get all Tingkat (Kelas levels)
     */
    public function getTingkat()
    {
        $tingkat = Tingkat::orderBy('level')->get();
        return response()->json($tingkat);
    }

    /**
     * Get Rombel (Detail Kelas) - optionally filtered by kelas_id (tingkat_id)
     */
    public function getRombel(Request $request)
    {
        $query = Rombel::with('tingkat')->where('status', 'active');

        if ($request->has('kelas_id')) {
            $query->where('kelas_id', $request->kelas_id);
        }

        $rombel = $query->orderBy('kelas_id')->orderBy('name')->get();
        return response()->json($rombel);
    }

    /**
     * Get Kelas Aktif (active class instances) - optionally filtered
     */
    public function getKelasAktif(Request $request)
    {
        $query = Kelas::with([
            'tingkat',
            'rombel',
            'academicYear',
            'students' => function ($q) {
                $q->wherePivot('status', 'active')->orderBy('name');
            }
        ]);

        if ($request->has('tingkat_id')) {
            $query->where('tingkat_id', $request->tingkat_id);
        }

        if ($request->has('rombel_id')) {
            $query->where('rombel_id', $request->rombel_id);
        }

        if ($request->has('academic_year_id')) {
            $query->where('academic_year_id', $request->academic_year_id);
            // Relax status check if year is explicitly requested (to allow historical data)
        } else {
            // Default: get active academic year's kelas AND enforce active status
            $activeYear = AcademicYear::where('status', 'active')->first();
            if ($activeYear) {
                $query->where('academic_year_id', $activeYear->id);
            }
            $query->where('status', 'active');
        }

        $kelas = $query->orderBy('tingkat_id')->orderBy('rombel_id')->get();
        return response()->json($kelas);
    }

    /**
     * Store new Rombel (Detail Kelas)
     */
    public function storeRombel(Request $request)
    {
        $request->validate([
            'kelas_id' => 'required|exists:tingkat,id',
            'name' => 'required|string|max:255',
            'status' => 'nullable|in:active,inactive'
        ]);

        $rombel = Rombel::create([
            'kelas_id' => $request->kelas_id,
            'name' => $request->name,
            'status' => $request->status ?? 'active'
        ]);

        return response()->json([
            'message' => 'Detail kelas berhasil ditambahkan',
            'data' => $rombel
        ], 201);
    }

    /**
     * Update Rombel (Detail Kelas)
     */
    public function updateRombel(Request $request, $id)
    {
        $rombel = Rombel::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'status' => 'nullable|in:active,inactive'
        ]);

        $rombel->update([
            'name' => $request->name,
            'status' => $request->status ?? $rombel->status
        ]);

        return response()->json([
            'message' => 'Detail kelas berhasil diupdate',
            'data' => $rombel
        ]);
    }

    /**
     * Delete Rombel (Detail Kelas)
     */
    public function deleteRombel($id)
    {
        $rombel = Rombel::findOrFail($id);

        // Check if rombel is used in any Kelas Aktif
        $kelasCount = Kelas::where('rombel_id', $id)->count();
        if ($kelasCount > 0) {
            return response()->json([
                'message' => 'Detail kelas tidak dapat dihapus karena masih digunakan di kelas aktif'
            ], 400);
        }

        $rombel->delete();

        return response()->json([
            'message' => 'Detail kelas berhasil dihapus'
        ]);
    }
    /**
     * Store new Kelas Detail (Active Class)
     * Finds or creates Rombel, then creates Kelas for the specific year.
     */
    public function storeDetail(Request $request)
    {
        $request->validate([
            'academic_year_id' => 'required|exists:academic_years,id',
            'tingkat_id' => 'required|exists:tingkat,id',
            'name' => 'required|string|max:255', // e.g., "A", "B"
        ]);

        DB::transaction(function () use ($request) {
            // 1. Find or Create Rombel (Master Data)
            $rombel = Rombel::firstOrCreate(
                [
                    'kelas_id' => $request->tingkat_id,
                    'name' => $request->name
                ],
                ['status' => 'active']
            );

            // 2. Create active Kelas for this year
            $tingkat = Tingkat::find($request->tingkat_id);
            $className = $tingkat->level . $rombel->name; // e.g. "1A"

            Kelas::firstOrCreate(
                [
                    'academic_year_id' => $request->academic_year_id,
                    'tingkat_id' => $request->tingkat_id,
                    'rombel_id' => $rombel->id,
                ],
                [
                    'name' => $className,
                    'status' => 'active'
                ]
            );
        });

        return response()->json(['message' => 'Detail kelas berhasil ditambahkan ke tahun ajaran ini']);
    }

    /**
     * Delete Kelas Detail (Active Class)
     */
    public function deleteDetail($id)
    {
        $kelas = Kelas::findOrFail($id);

        if ($kelas->students()->exists()) {
            return response()->json(['message' => 'Kelas tidak dapat dihapus karena masih memiliki siswa.'], 400);
        }

        $kelas->delete();

        return response()->json(['message' => 'Detail kelas berhasil dihapus dari tahun ajaran ini']);
    }

    /**
     * Update Kelas Detail (Active Class)
     * Allows changing the rombel/name of an existing Kelas record
     */
    public function updateDetail(Request $request, $id)
    {
        $kelas = Kelas::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255', // e.g., "A", "B"
        ]);

        DB::transaction(function () use ($request, $kelas) {
            // 1. Find or Create new Rombel (Master Data) with new name
            $rombel = Rombel::firstOrCreate(
                [
                    'kelas_id' => $kelas->tingkat_id,
                    'name' => $request->name
                ],
                ['status' => 'active']
            );

            // 2. Update the Kelas record with new rombel
            $tingkat = Tingkat::find($kelas->tingkat_id);
            $className = $tingkat->level . $rombel->name; // e.g. "1A"

            $kelas->update([
                'rombel_id' => $rombel->id,
                'name' => $className
            ]);
        });

        return response()->json(['message' => 'Detail kelas berhasil diperbarui']);
    }
}
