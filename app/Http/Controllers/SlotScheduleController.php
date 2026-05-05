<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\ClassDaySlot;
use App\Models\Kelas;
use App\Models\ClassSubject;
use App\Models\SlotSchedule;
use App\Models\Subject;
use App\Models\SwapRequest;
use App\Models\Teacher;
use App\Models\TeacherClassConstraint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SlotScheduleController extends Controller
{
    // ==========================================
    // SUBJECT MANAGEMENT (Admin)
    // ==========================================

    public function subjects()
    {
        $subjects = Subject::with('teachers:id,name')->orderBy('name')->get();
        return response()->json($subjects);
    }

    public function storeSubject(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:20|unique:subjects,code',
        ]);

        $subject = Subject::create([
            'name' => $request->name,
            'code' => strtoupper($request->code),
        ]);

        ActivityLog::log(Auth::id(), 'create_subject', "Membuat mata pelajaran: {$subject->name}");

        return response()->json($subject, 201);
    }

    public function updateSubject(Request $request, Subject $subject)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:20|unique:subjects,code,' . $subject->id,
        ]);

        $subject->update([
            'name' => $request->name,
            'code' => strtoupper($request->code),
        ]);

        ActivityLog::log(Auth::id(), 'update_subject', "Mengubah mata pelajaran: {$subject->name}");

        return response()->json($subject);
    }

    public function deleteSubject(Subject $subject)
    {
        $name = $subject->name;
        $subject->delete();

        ActivityLog::log(Auth::id(), 'delete_subject', "Menghapus mata pelajaran: {$name}");

        return response()->json(['message' => 'Mata pelajaran berhasil dihapus']);
    }

    public function assignTeacherToSubject(Request $request)
    {
        $request->validate([
            'subject_id' => 'required|exists:subjects,id',
            'teacher_id' => 'required|exists:teachers,id',
        ]);

        $subject = Subject::find($request->subject_id);
        $subject->teachers()->syncWithoutDetaching([$request->teacher_id]);

        ActivityLog::log(Auth::id(), 'assign_teacher', "Menugaskan guru ke mata pelajaran: {$subject->name}");

        return response()->json(['message' => 'Guru berhasil ditugaskan']);
    }

    public function removeTeacherFromSubject(Request $request)
    {
        $request->validate([
            'subject_id' => 'required|exists:subjects,id',
            'teacher_id' => 'required|exists:teachers,id',
        ]);

        $subject = Subject::find($request->subject_id);
        $subject->teachers()->detach($request->teacher_id);

        return response()->json(['message' => 'Guru berhasil dihapus dari mata pelajaran']);
    }

    // ==========================================
    // CLASS SUBJECT CONFIGURATION (Admin)
    // ==========================================

    public function classSubjects($classRoomId)
    {
        // Using ID directly to support Kelas ID
        $classSubjects = ClassSubject::with(['subject', 'teacher'])
            ->where('class_room_id', $classRoomId)
            ->get();

        return response()->json($classSubjects);
    }

    public function storeClassSubject(Request $request)
    {
        $request->validate([
            'class_room_id' => 'required|exists:kelas,id',
            'subject_id' => 'required|exists:subjects,id',
            'teacher_id' => 'nullable|exists:teachers,id',
            'weekly_hours' => 'required|integer|min:1|max:10',
        ]);

        $classSubject = ClassSubject::updateOrCreate(
            [
                'class_room_id' => $request->class_room_id,
                'subject_id' => $request->subject_id,
            ],
            [
                'weekly_hours' => $request->weekly_hours,
                'teacher_id' => $request->teacher_id,
            ]
        );

        return response()->json($classSubject, 201);
    }

    public function deleteClassSubject(ClassSubject $classSubject)
    {
        $classSubject->delete();
        return response()->json(['message' => 'Mata pelajaran dihapus dari kelas']);
    }

    // ==========================================
    // CLASS DAY SLOTS CONFIGURATION (Admin)
    // ==========================================

    public function classDaySlots($classRoomId)
    {
        $slots = ClassDaySlot::where('class_room_id', $classRoomId)->get();
        return response()->json($slots);
    }

    public function updateClassDaySlots(Request $request)
    {
        $request->validate([
            'class_room_id' => 'required|exists:kelas,id',
            'slots' => 'required|array',
            'slots.*.day' => 'required|in:sabtu,minggu,senin,selasa,rabu,kamis',
            'slots.*.total_slots' => 'required|integer|min:1|max:10',
        ]);

        foreach ($request->slots as $slotData) {
            ClassDaySlot::updateOrCreate(
                [
                    'class_room_id' => $request->class_room_id,
                    'day' => $slotData['day'],
                ],
                ['total_slots' => $slotData['total_slots']]
            );
        }

        return response()->json(['message' => 'Konfigurasi slot berhasil disimpan']);
    }

    // ==========================================
    // TEACHER CONSTRAINTS (Admin)
    // ==========================================

    public function teacherConstraints(Teacher $teacher)
    {
        $constraints = TeacherClassConstraint::with('classRoom')
            ->where('teacher_id', $teacher->id)
            ->get();

        return response()->json($constraints);
    }

    public function storeTeacherConstraint(Request $request)
    {
        $request->validate([
            'teacher_id' => 'required|exists:teachers,id',
            'class_room_id' => 'required|exists:class_rooms,id',
            'day' => 'required|in:sabtu,minggu,senin,selasa,rabu,kamis',
            'hours' => 'required|integer|min:1|max:10',
            'is_hard_constraint' => 'boolean',
        ]);

        $constraint = TeacherClassConstraint::create($request->all());

        return response()->json($constraint, 201);
    }

    public function deleteTeacherConstraint(TeacherClassConstraint $constraint)
    {
        $constraint->delete();
        return response()->json(['message' => 'Constraint dihapus']);
    }

    // ==========================================
    // SCHEDULE GENERATION (Admin)
    // ==========================================

    public function slotSchedules(Request $request)
    {
        $query = SlotSchedule::with(['classRoom', 'subject', 'teacher']);

        if ($request->class_room_id) {
            $query->where('class_room_id', $request->class_room_id);
        }

        if ($request->teacher_id) {
            $query->where('teacher_id', $request->teacher_id);
        }

        if ($request->day) {
            $query->where('day', $request->day);
        }

        $schedules = $query->orderBy('day')
            ->orderBy('slot_number')
            ->get();

        return response()->json($schedules);
    }

    public function generateSchedules(Request $request)
    {
        $request->validate([
            'class_room_ids' => 'required|array',
            'class_room_ids.*' => 'exists:kelas,id',
            'overwrite' => 'boolean',
        ]);

        // Check if schedules already exist
        $existingCount = SlotSchedule::whereIn('class_room_id', $request->class_room_ids)->count();
        if ($existingCount > 0 && !$request->overwrite) {
            return response()->json([
                'error' => 'Jadwal sudah ada. Konfirmasi overwrite untuk melanjutkan.',
                'existing_count' => $existingCount,
            ], 409);
        }

        try {
            DB::beginTransaction();

            // Clear existing schedules if overwriting
            if ($request->overwrite) {
                SlotSchedule::whereIn('class_room_id', $request->class_room_ids)->delete();
            }

            $errors = [];
            $created = 0;

            foreach ($request->class_room_ids as $classRoomId) {
                $result = $this->generateForClass($classRoomId);
                if (isset($result['error'])) {
                    $errors[] = $result['error'];
                } else {
                    $created += $result['created'];
                }
            }

            if (!empty($errors)) {
                DB::rollBack();
                return response()->json(['errors' => $errors], 422);
            }

            DB::commit();

            ActivityLog::log(Auth::id(), 'generate_schedules', "Generate jadwal untuk " . count($request->class_room_ids) . " kelas");

            return response()->json([
                'message' => 'Jadwal berhasil di-generate',
                'created' => $created,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function generateForClass($classRoomId)
    {
        $classRoom = Kelas::find($classRoomId);
        $classSubjects = ClassSubject::with(['subject.teachers', 'teacher'])
            ->where('class_room_id', $classRoomId)
            ->get();

        $daySlots = ClassDaySlot::where('class_room_id', $classRoomId)->get()->keyBy('day');
        $days = ['sabtu', 'minggu', 'senin', 'selasa', 'rabu', 'kamis'];

        // Build list of required lessons
        $pendingLessons = [];
        foreach ($classSubjects as $cs) {
            // Validation: Ensure there is at least one teacher available
            if (!$cs->teacher_id && $cs->subject->teachers->isEmpty()) {
                return ['error' => "Mata pelajaran {$cs->subject->name} belum memiliki guru. Silakan atur guru di menu 'Alokasi Kurikulum'."];
            }

            for ($i = 0; $i < $cs->weekly_hours; $i++) {
                $pendingLessons[] = [
                    'class_subject' => $cs,
                    'subject_id' => $cs->subject_id,
                    'subject_name' => $cs->subject->name,
                ];
            }
        }

        $created = 0;

        foreach ($days as $day) {
            $totalSlots = $daySlots[$day]->total_slots ?? 6;

            for ($slot = 1; $slot <= $totalSlots; $slot++) {
                // Try to find a lesson that fits this slot
                $assignedKey = null;
                $assignedTeacherId = null;

                foreach ($pendingLessons as $key => $lesson) {
                    $cs = $lesson['class_subject'];

                    // Determine candidate teachers
                    $candidateTeachers = [];
                    if ($cs->teacher_id) {
                        // Fixed teacher for this class
                        $candidateTeachers[] = $cs->teacher_id;
                    } else {
                        // Any teacher teaching this subject
                        $candidateTeachers = $cs->subject->teachers->pluck('id')->toArray();
                    }

                    // Check if any candidate teacher is available
                    foreach ($candidateTeachers as $tid) {
                        // Check if teacher is already scheduled in ANY class at this time
                        // This checks both existing schedules and ones we just created in this transaction
                        $isBusy = SlotSchedule::where('teacher_id', $tid)
                            ->where('day', $day)
                            ->where('slot_number', $slot)
                            ->exists();

                        if (!$isBusy) {
                            $assignedTeacherId = $tid;
                            $assignedKey = $key;
                            break 2; // Exit both teacher loop and lesson loop
                        }
                    }
                }

                // If we found a fit, schedule it
                if ($assignedKey !== null) {
                    $lesson = $pendingLessons[$assignedKey];

                    SlotSchedule::create([
                        'class_room_id' => $classRoomId,
                        'subject_id' => $lesson['subject_id'],
                        'teacher_id' => $assignedTeacherId,
                        'day' => $day,
                        'slot_number' => $slot,
                        'status' => 'normal',
                        'generated_by' => 'system',
                    ]);

                    // Remove from pending list
                    unset($pendingLessons[$assignedKey]);
                    $created++;
                }
            }
        }

        // Optional: Check if any lessons remain unscheduled
        // We could return a warning, but for now we just return formatted success
        return ['created' => $created];
    }

    public function updateSlotSchedule(Request $request, SlotSchedule $schedule)
    {
        $request->validate([
            'subject_id' => 'required|exists:subjects,id',
            'teacher_id' => 'required|exists:teachers,id',
        ]);

        // Check for conflicts
        $conflict = SlotSchedule::where('teacher_id', $request->teacher_id)
            ->where('day', $schedule->day)
            ->where('slot_number', $schedule->slot_number)
            ->where('id', '!=', $schedule->id)
            ->exists();

        if ($conflict) {
            return response()->json(['error' => 'Guru sudah mengajar pada slot ini'], 422);
        }

        $schedule->update([
            'subject_id' => $request->subject_id,
            'teacher_id' => $request->teacher_id,
            'generated_by' => 'admin',
        ]);

        ActivityLog::log(Auth::id(), 'update_schedule', "Mengubah jadwal slot {$schedule->slot_number} hari {$schedule->day}");

        return response()->json($schedule);
    }

    public function moveSlot(Request $request)
    {
        $request->validate([
            'source_id' => 'required|exists:slot_schedules,id',
            'target_day' => 'required|in:sabtu,minggu,senin,selasa,rabu,kamis',
            'target_slot' => 'required|integer|min:1|max:10',
            'class_room_id' => 'required|exists:kelas,id',
        ]);

        try {
            DB::beginTransaction();

            $source = SlotSchedule::where('id', $request->source_id)
                ->where('class_room_id', $request->class_room_id)
                ->firstOrFail();

            // Check if target slot is occupied by the SAME class
            $target = SlotSchedule::where('class_room_id', $request->class_room_id)
                ->where('day', $request->target_day)
                ->where('slot_number', $request->target_slot)
                ->first();

            if ($target) {
                // SWAP Logic
                // To avoid unique constraint violation on (class_room_id, day, slot_number), we use temporary placeholder or swapping attributes
                // But simpler: just swap subject_id and teacher_id and generated_by

                $tempSubject = $source->subject_id;
                $tempTeacher = $source->teacher_id;
                $tempGenBy = $source->generated_by;

                $source->update([
                    'subject_id' => $target->subject_id,
                    'teacher_id' => $target->teacher_id,
                    'generated_by' => $target->generated_by, // Keep track of who moved it? Or just admin
                ]);

                $target->update([
                    'subject_id' => $tempSubject,
                    'teacher_id' => $tempTeacher,
                    'generated_by' => $tempGenBy,
                ]);

                $message = "Jadwal berhasil ditukar";
            } else {
                // MOVE Logic
                $source->update([
                    'day' => $request->target_day,
                    'slot_number' => $request->target_slot,
                ]);
                $message = "Jadwal berhasil dipindahkan";
            }

            DB::commit();

            ActivityLog::log(Auth::id(), 'move_slot', "Memindahkan/Menukar jadwal kelas ID {$request->class_room_id}");

            return response()->json(['message' => $message]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Gagal memindahkan jadwal: ' . $e->getMessage()], 500);
        }
    }

    public function deleteSlotSchedule(SlotSchedule $schedule)
    {
        $schedule->delete();
        return response()->json(['message' => 'Jadwal dihapus']);
    }

    public function clearSchedules(Request $request)
    {
        $request->validate([
            'class_room_id' => 'sometimes|exists:kelas,id',
            'confirm' => 'required|boolean',
        ]);

        if (!$request->confirm) {
            return response()->json(['error' => 'Konfirmasi diperlukan'], 422);
        }

        $query = SlotSchedule::query();
        if ($request->class_room_id) {
            $query->where('class_room_id', $request->class_room_id);
        }

        $count = $query->count();
        $query->delete();

        ActivityLog::log(Auth::id(), 'clear_schedules', "Menghapus {$count} jadwal");

        return response()->json(['message' => "{$count} jadwal berhasil dihapus"]);
    }

    // ==========================================
    // SWAP REQUESTS (Guru & Admin)
    // ==========================================

    public function requestSwap(Request $request)
    {
        $request->validate([
            'schedule_id_1' => 'required|exists:slot_schedules,id',
            'schedule_id_2' => 'required|exists:slot_schedules,id',
            'notes' => 'nullable|string|max:500',
        ]);

        $user = Auth::user();
        $teacher = $user->teacher;

        if (!$teacher) {
            return response()->json(['error' => 'Anda bukan guru'], 403);
        }

        $schedule1 = SlotSchedule::find($request->schedule_id_1);
        $schedule2 = SlotSchedule::find($request->schedule_id_2);

        // Verify requester owns schedule 1
        if ($schedule1->teacher_id !== $teacher->id) {
            return response()->json(['error' => 'Anda tidak mengajar pada jadwal ini'], 403);
        }

        // Get target teacher
        $targetTeacher = Teacher::find($schedule2->teacher_id);
        if (!$targetTeacher) {
            return response()->json(['error' => 'Guru target tidak ditemukan'], 404);
        }

        // Check for potential conflicts after swap
        $conflict1 = SlotSchedule::where('teacher_id', $teacher->id)
            ->where('day', $schedule2->day)
            ->where('slot_number', $schedule2->slot_number)
            ->where('id', '!=', $schedule1->id)
            ->exists();

        $conflict2 = SlotSchedule::where('teacher_id', $targetTeacher->id)
            ->where('day', $schedule1->day)
            ->where('slot_number', $schedule1->slot_number)
            ->where('id', '!=', $schedule2->id)
            ->exists();

        if ($conflict1 || $conflict2) {
            return response()->json(['error' => 'Swap akan menyebabkan bentrok jadwal'], 422);
        }

        $swapRequest = SwapRequest::create([
            'requester_teacher_id' => $teacher->id,
            'target_teacher_id' => $targetTeacher->id,
            'schedule_id_1' => $schedule1->id,
            'schedule_id_2' => $schedule2->id,
            'status' => 'pending',
            'notes' => $request->notes,
        ]);

        // Mark schedules as pending swap
        $schedule1->update(['status' => 'pending_swap']);
        $schedule2->update(['status' => 'pending_swap']);

        ActivityLog::log(Auth::id(), 'request_swap', "Mengajukan swap jadwal");

        return response()->json($swapRequest, 201);
    }

    public function mySwapRequests()
    {
        $teacher = Auth::user()->teacher;

        if (!$teacher) {
            return response()->json(['error' => 'Anda bukan guru'], 403);
        }

        $requests = SwapRequest::with(['scheduleOne.classRoom', 'scheduleOne.subject', 'scheduleTwo.classRoom', 'scheduleTwo.subject', 'targetTeacher'])
            ->where('requester_teacher_id', $teacher->id)
            ->orWhere('target_teacher_id', $teacher->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json($requests);
    }

    public function cancelSwapRequest(SwapRequest $swapRequest)
    {
        $teacher = Auth::user()->teacher;

        if (!$teacher || $swapRequest->requester_teacher_id !== $teacher->id) {
            return response()->json(['error' => 'Tidak diizinkan'], 403);
        }

        if ($swapRequest->status !== 'pending') {
            return response()->json(['error' => 'Hanya bisa membatalkan request pending'], 422);
        }

        // Reset schedule status
        SlotSchedule::find($swapRequest->schedule_id_1)?->update(['status' => 'normal']);
        SlotSchedule::find($swapRequest->schedule_id_2)?->update(['status' => 'normal']);

        $swapRequest->delete();

        return response()->json(['message' => 'Request dibatalkan']);
    }

    // Admin endpoints for swap
    public function pendingSwapRequests()
    {
        $requests = SwapRequest::with([
            'requesterTeacher:id,name',
            'targetTeacher:id,name',
            'scheduleOne.classRoom',
            'scheduleOne.subject',
            'scheduleTwo.classRoom',
            'scheduleTwo.subject',
        ])
            ->pending()
            ->orderByDesc('created_at')
            ->get();

        return response()->json($requests);
    }

    public function approveSwapRequest(SwapRequest $swapRequest)
    {
        if ($swapRequest->status !== 'pending') {
            return response()->json(['error' => 'Request sudah diproses'], 422);
        }

        try {
            DB::beginTransaction();

            $schedule1 = SlotSchedule::find($swapRequest->schedule_id_1);
            $schedule2 = SlotSchedule::find($swapRequest->schedule_id_2);

            // Swap teachers
            $temp = $schedule1->teacher_id;
            $schedule1->update([
                'teacher_id' => $schedule2->teacher_id,
                'status' => 'normal',
            ]);
            $schedule2->update([
                'teacher_id' => $temp,
                'status' => 'normal',
            ]);

            $swapRequest->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            DB::commit();

            ActivityLog::log(Auth::id(), 'approve_swap', "Menyetujui swap request #{$swapRequest->id}");

            return response()->json(['message' => 'Swap disetujui']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function rejectSwapRequest(Request $request, SwapRequest $swapRequest)
    {
        if ($swapRequest->status !== 'pending') {
            return response()->json(['error' => 'Request sudah diproses'], 422);
        }

        // Reset schedule status
        SlotSchedule::find($swapRequest->schedule_id_1)?->update(['status' => 'normal']);
        SlotSchedule::find($swapRequest->schedule_id_2)?->update(['status' => 'normal']);

        $swapRequest->update([
            'status' => 'rejected',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'notes' => $request->notes ?? $swapRequest->notes,
        ]);

        ActivityLog::log(Auth::id(), 'reject_swap', "Menolak swap request #{$swapRequest->id}");

        return response()->json(['message' => 'Swap ditolak']);
    }

    // ==========================================
    // TEACHER SCHEDULE VIEW
    // ==========================================

    public function guruSchedules()
    {
        $teacher = Auth::user()->teacher;

        if (!$teacher) {
            return response()->json(['error' => 'Anda bukan guru'], 403);
        }

        $schedules = SlotSchedule::with(['classRoom', 'subject'])
            ->where('teacher_id', $teacher->id)
            ->orderBy('day')
            ->orderBy('slot_number')
            ->get();

        return response()->json($schedules);
    }

    // ==========================================
    // PARENT SCHEDULE VIEW (Read-Only)
    // ==========================================

    public function parentSchedules()
    {
        try {
            $user = Auth::user();

            // Match logic from ApiController::parentDashboard
            // Prioritize the direct relationship defined in User model
            $student = $user->student;

            // Fallback: If not found via relationship, try parent_user_id manual query
            if (!$student) {
                $student = \App\Models\Student::where('parent_user_id', $user->id)->first();
            }

            if (!$student) {
                return response()->json(['error' => 'Data siswa tidak ditemukan'], 404);
            }

            if (!$student->class_room_id) {
                // Return empty list instead of 404 to avoid scary error screen
                return response()->json([]);
            }

            $schedules = SlotSchedule::with(['subject:id,name', 'teacher:id,name', 'classRoom:id,name,grade'])
                ->where('class_room_id', $student->class_room_id)
                ->where('status', 'normal')
                ->orderBy('day')
                ->orderBy('slot_number')
                ->get();

            return response()->json($schedules);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Parent Schedule Error: ' . $e->getMessage());
            return response()->json(['error' => 'Gagal memuat jadwal: ' . $e->getMessage()], 500);
        }
    }

    public function parentGlobalSchedule(Request $request)
    {
        $schedules = SlotSchedule::with(['classRoom:id,name,grade', 'subject:id,name', 'teacher:id,name'])
            ->where('status', 'normal')
            ->orderBy('class_room_id')
            ->orderBy('day')
            ->orderBy('slot_number')
            ->get();

        return response()->json($schedules);
    }

    // ==========================================
    // EXPORT TO EXCEL
    // ==========================================

    public function exportSchedule(Request $request)
    {
        $request->validate([
            'type' => 'required|in:class,teacher,all',
            'class_room_id' => 'required_if:type,class|exists:class_rooms,id',
            'teacher_id' => 'required_if:type,teacher|exists:teachers,id',
        ]);

        // Simple CSV export (no PhpSpreadsheet dependency needed)
        $days = ['sabtu', 'minggu', 'senin', 'selasa', 'rabu', 'kamis'];
        $dayLabels = ['Sabtu', 'Ahad', 'Senin', 'Selasa', 'Rabu', 'Kamis'];

        if ($request->type === 'class') {
            $classRoom = Kelas::find($request->class_room_id);
            $schedules = SlotSchedule::with(['subject', 'teacher'])
                ->where('class_room_id', $request->class_room_id)
                ->where('status', 'normal')
                ->get()
                ->groupBy('day');

            $csv = "Jadwal Kelas: {$classRoom->name}\n";
            $csv .= "Jam," . implode(',', $dayLabels) . "\n";

            $maxSlots = 8;
            for ($slot = 1; $slot <= $maxSlots; $slot++) {
                $row = ["Jam ke-{$slot}"];
                foreach ($days as $day) {
                    $daySchedules = $schedules->get($day, collect());
                    $slotSchedule = $daySchedules->firstWhere('slot_number', $slot);
                    if ($slotSchedule) {
                        $row[] = "\"{$slotSchedule->subject->name} ({$slotSchedule->teacher->name})\"";
                    } else {
                        $row[] = "";
                    }
                }
                $csv .= implode(',', $row) . "\n";
            }

            $filename = "jadwal_{$classRoom->name}_" . date('Y-m-d') . ".csv";
        } else {
            $classRooms = Kelas::all();
            $csv = "Jadwal Semua Kelas\n\n";

            foreach ($classRooms as $classRoom) {
                $schedules = SlotSchedule::with(['subject', 'teacher'])
                    ->where('class_room_id', $classRoom->id)
                    ->where('status', 'normal')
                    ->get()
                    ->groupBy('day');

                $csv .= "Kelas: {$classRoom->name}\n";
                $csv .= "Jam," . implode(',', $dayLabels) . "\n";

                $maxSlots = 8;
                for ($slot = 1; $slot <= $maxSlots; $slot++) {
                    $row = ["Jam ke-{$slot}"];
                    foreach ($days as $day) {
                        $daySchedules = $schedules->get($day, collect());
                        $slotSchedule = $daySchedules->firstWhere('slot_number', $slot);
                        if ($slotSchedule) {
                            $row[] = "\"{$slotSchedule->subject->name} ({$slotSchedule->teacher->name})\"";
                        } else {
                            $row[] = "";
                        }
                    }
                    $csv .= implode(',', $row) . "\n";
                }
                $csv .= "\n";
            }

            $filename = "jadwal_semua_" . date('Y-m-d') . ".csv";
        }

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    // ==========================================
    // ACTIVITY LOGS (Admin)
    // ==========================================

    public function activityLogs(Request $request)
    {
        $logs = ActivityLog::with('user:id,name')
            ->orderByDesc('created_at')
            ->paginate(50);

        return response()->json($logs);
    }
}
