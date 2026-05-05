<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Schedule;
use Illuminate\Support\Facades\Auth;

class ScheduleController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        // Ensure user is a student/guardian
        if ($user->role !== 'siswa') {
            return redirect()->route('dashboard');
        }

        $student = $user->student;

        if (!$student) {
            return redirect()->route('dashboard')->with('error', 'Data siswa tidak ditemukan.');
        }

        $classSchedules = [];
        if ($student->class_room_id) {
            $classSchedules = Schedule::where('type', 'class')
                ->where('class_room_id', $student->class_room_id)
                ->latest()
                ->get();
        }

        $ptsSchedules = Schedule::where('type', 'pts')->latest()->get();
        $pasSchedules = Schedule::where('type', 'pas')->latest()->get();

        return Inertia::render('Dashboard/Schedule/Index', [
            'classSchedules' => $classSchedules,
            'ptsSchedules' => $ptsSchedules,
            'pasSchedules' => $pasSchedules,
            'studentClass' => $student->classRoom ? "Kelas " . $student->classRoom->grade . " " . $student->classRoom->name : 'Belum Ada Kelas'
        ]);
    }
}
