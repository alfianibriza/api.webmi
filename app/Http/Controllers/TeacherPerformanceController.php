<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;

class TeacherPerformanceController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->input('month', Carbon::now()->month);
        $year = $request->input('year', Carbon::now()->year);

        // Get all teachers
        $teachers = User::where('role', 'guru')
            ->with(['teacher.subjects']) // Load subjects via teacher profile
            ->withCount([
                // Attendance Counts
                'attendances as present_count' => function ($query) use ($month, $year) {
                    $query->whereMonth('date', $month)->whereYear('date', $year)->where('status', 'hadir');
                },
                'attendances as permit_count' => function ($query) use ($month, $year) {
                    $query->whereMonth('date', $month)->whereYear('date', $year)->where('status', 'izin');
                },
                'attendances as sick_count' => function ($query) use ($month, $year) {
                    $query->whereMonth('date', $month)->whereYear('date', $year)->where('status', 'sakit');
                },
                'attendances as alpha_count' => function ($query) use ($month, $year) {
                    $query->whereMonth('date', $month)->whereYear('date', $year)->where('status', 'alpha');
                },
                // Task Counts
                'taskAssignees as total_tasks',
                'taskAssignees as completed_tasks' => function ($query) {
                    $query->whereIn('status', ['submitted', 'approved']);
                },
                'taskAssignees as approved_tasks' => function ($query) {
                    $query->where('status', 'approved');
                }
            ])
            ->get()
            ->map(function ($user) {
                $total = $user->total_tasks;
                $completed = $user->completed_tasks;
                $percentage = $total > 0 ? round(($completed / $total) * 100) : 0;

                // Determine status label
                $status = 'Proses';
                if ($percentage == 100)
                    $status = 'Selesai';
                elseif ($percentage < 50)
                    $status = 'Lambat';

                // Get Subject Name
                $subject = $user->teacher && $user->teacher->subjects->first()
                    ? $user->teacher->subjects->first()->name
                    : ($user->teacher->subject ?? '-');

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'photo' => $user->teacher->image ?? $user->profile_photo_url,
                    'subject' => $subject,
                    'total_tasks' => $total,
                    'completed_tasks' => $completed,
                    'percentage' => $percentage,
                    'status' => $status,
                    'present_count' => $user->present_count,
                    'permit_count' => $user->permit_count,
                    'sick_count' => $user->sick_count,
                    'alpha_count' => $user->alpha_count,
                ];
            });

        // Top 5 Diligent (based on present_count)
        $top = $teachers->sortByDesc('present_count')->take(5)->values();

        // Bottom 3 (for evaluation)
        $bottom = $teachers->sortBy('present_count')->take(3)->values(); // Sort ascending (lowest present first)

        // Sorted by task percentage for the list
        $sortedByTasks = $teachers->sortByDesc('percentage')->values();

        return response()->json([
            'month' => (int) $month,
            'year' => (int) $year,
            'teachers' => $sortedByTasks, // Full list for table
            'top' => $top, // For attendance cards
            'bottom' => $bottom // For attendance cards
        ]);
    }
}
