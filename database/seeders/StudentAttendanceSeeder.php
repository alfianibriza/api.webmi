<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Student;
use App\Models\StudentAttendance;
use Carbon\Carbon;

class StudentAttendanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $students = Student::where('status', 'active')->get();
        $startDate = Carbon::now()->startOfMonth();
        $endDate = Carbon::now();

        foreach ($students as $student) {
            // Generate dummy attendance for each day up to today
            $current = $startDate->copy();
            while ($current <= $endDate) {
                if ($current->isWeekend()) {
                    $current->addDay();
                    continue;
                }

                $rand = rand(1, 100);
                $status = 'hadir';
                $reason = null;

                if ($rand > 90) { // 10% chance absent
                    $type = rand(1, 3);
                    if ($type == 1)
                        $status = 'izin';
                    elseif ($type == 2)
                        $status = 'sakit';
                    else
                        $status = 'alpha';

                    if ($status != 'alpha')
                        $reason = 'Dummy reason';
                }

                StudentAttendance::create([
                    'student_id' => $student->id,
                    'date' => $current->toDateString(),
                    'status' => $status,
                    'reason' => $reason,
                ]);

                $current->addDay();
            }
        }
    }
}
