<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Student;

class AdminStudentAttendanceController extends Controller
{
    public function index(Request $request)
    {
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());
        $grade = $request->input('grade');
        $classRoomId = $request->input('class_room_id');

        $query = Student::where('status', 'active')->orderBy('name');

        if ($grade) {
            $query->where('grade', $grade);
        }

        if ($classRoomId) {
            $query->where('kelas_id', $classRoomId);
        }

        $students = $query->with('kelas.rombel')->withCount([
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
        ])
            ->get();

        $grades = Student::where('status', 'active')->distinct()->pluck('grade')->sort()->values();
        // Use Rombel via Kelas for filters? Or just use existing API for frontend filters. 
        // Backend doesn't strictly need to send all classRooms if frontend fetches them.
        // But let's keep it compatible if needed, or just send empty if unused.
        $classRooms = \App\Models\ClassRoom::orderBy('name')->get(); // Legacy support or remove?

        return Inertia::render('Dashboard/Kesiswaan/AttendanceReport', [
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

    public function export(Request $request)
    {
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());
        $grade = $request->input('grade');
        $classRoomId = $request->input('class_room_id');

        $query = Student::where('status', 'active')->orderBy('name');

        if ($grade) {
            $query->where('grade', $grade);
        }

        if ($classRoomId) {
            $query->where('kelas_id', $classRoomId);
        }

        $students = $query->with('kelas.rombel')->withCount([
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
        ])
            ->get();

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
                echo "<tr>";
                echo "<td>" . ($index + 1) . "</td>";
                echo "<td>{$student->name}</td>";
                echo "<td>'{$student->nis}</td>"; // ' to force string in excel if needed, or clear formatting
                echo "<td>{$student->grade}</td>";
                echo "<td>{$student->hadir_count}</td>";
                echo "<td>{$student->izin_count}</td>";
                echo "<td>{$student->sakit_count}</td>";
                echo "<td>{$student->alpha_count}</td>";
                echo "</tr>";
            }
            echo "</tbody></table></body></html>";
        }, $filename);
    }

    public function create(Request $request)
    {
        $date = $request->input('date', now()->toDateString());
        $grade = $request->input('grade');

        $grades = Student::where('status', 'active')
            ->distinct()
            ->pluck('grade')
            ->sort()
            ->values();

        $students = [];
        if ($grade) {
            $students = Student::where('status', 'active')
                ->where('grade', $grade)
                ->orderBy('name')
                ->get()
                ->map(function ($student) use ($date) {
                    $attendance = $student->attendances()->where('date', $date)->first();
                    return [
                        'id' => $student->id,
                        'name' => $student->name,
                        'nis' => $student->nis,
                        'status' => $attendance ? $attendance->status : 'hadir', // Default to present
                        'reason' => $attendance ? $attendance->reason : null,
                    ];
                });
        }

        return Inertia::render('Dashboard/Kesiswaan/InputAttendance', [
            'grades' => $grades,
            'students' => $students,
            'filters' => [
                'date' => $date,
                'grade' => $grade,
            ]
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'grade' => 'required',
            'attendances' => 'required|array',
            'attendances.*.student_id' => 'required|exists:students,id',
            'attendances.*.status' => 'required|in:hadir,izin,sakit,alpha',
            'attendances.*.reason' => 'nullable|string|max:255',
        ]);

        $date = $request->date;

        foreach ($request->attendances as $data) {
            \App\Models\StudentAttendance::updateOrCreate(
                [
                    'student_id' => $data['student_id'],
                    'date' => $date,
                ],
                [
                    'status' => $data['status'],
                    'reason' => $data['reason'],
                ]
            );
        }

        return redirect()->route('admin.student-attendance.index')->with('success', 'Data kehadiran berhasil disimpan.');
    }

    /**
     * Store single student attendance via AJAX
     */
    public function storeSingle(Request $request)
    {
        $request->validate([
            'student_id' => 'required|exists:students,id',
            'date' => 'required|date',
            'status' => 'required|in:hadir,izin,sakit,alpha',
            'reason' => 'nullable|string|max:255',
        ]);

        \App\Models\StudentAttendance::updateOrCreate(
            [
                'student_id' => $request->student_id,
                'date' => $request->date,
            ],
            [
                'status' => $request->status,
                'reason' => $request->reason,
            ]
        );

        return response()->json(['message' => 'Kehadiran berhasil disimpan.']);
    }
}
