<?php

namespace App\Http\Controllers;

use App\Models\ClassRoom;
use App\Models\FinancialObligation;
use App\Models\Student;
use App\Models\StudentObligation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinancialObligationController extends Controller
{
    public function index()
    {
        $obligations = FinancialObligation::withCount([
            'studentObligations',
            'studentObligations as paid_count' => function ($query) {
                $query->where('status', 'paid');
            }
        ])
            ->latest()
            ->get();

        return response()->json($obligations);
    }

    public function create()
    {
        return response()->json([
            'classrooms' => ClassRoom::all(),
            'students' => Student::select('id', 'name', 'class_room_id')->get()
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'due_date' => 'nullable|date',
            'target_type' => 'required|in:all,selected',
            'student_ids' => 'required_if:target_type,selected|array',
            'student_ids.*' => 'exists:students,id',
            'class_room_id' => 'nullable|exists:class_rooms,id' // Optional filter for 'all'
        ]);

        DB::transaction(function () use ($request) {
            $obligation = FinancialObligation::create([
                'title' => $request->title,
                'amount' => $request->amount,
                'due_date' => $request->due_date,
                'description' => $request->description,
            ]);

            $studentQuery = Student::query();

            // Only active students
            $studentQuery->where('status', 'active');

            if ($request->target_type === 'selected') {
                $studentQuery->whereIn('id', $request->student_ids);
            } elseif ($request->class_room_id) {
                $studentQuery->where('class_room_id', $request->class_room_id);
            }

            $students = $studentQuery->get();

            foreach ($students as $student) {
                StudentObligation::create([
                    'financial_obligation_id' => $obligation->id,
                    'student_id' => $student->id,
                    'status' => 'pending'
                ]);
            }
        });

        return response()->json(['message' => 'Tanggungan berhasil dibuat.'], 201);
    }


    public function update(Request $request, FinancialObligation $financialObligation)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'due_date' => 'nullable|date',
            'description' => 'nullable|string',
        ]);

        $financialObligation->update([
            'title' => $request->title,
            'amount' => $request->amount,
            'due_date' => $request->due_date,
            'description' => $request->description,
        ]);

        return response()->json(['message' => 'Tanggungan berhasil diperbarui.']);
    }

    public function show(FinancialObligation $financialObligation)
    {
        $financialObligation->load(['studentObligations.student.classRoom', 'studentObligations.verifier']);

        return response()->json([
            'obligation' => $financialObligation,
            'records' => $financialObligation->studentObligations
        ]);
    }

    public function verify(Request $request, StudentObligation $studentObligation)
    {
        $request->validate([
            'action' => 'required|in:approve,reject'
        ]);

        if ($request->action === 'approve') {
            $studentObligation->update([
                'status' => 'paid',
                'verified_at' => now(),
                'verified_by' => auth()->id()
            ]);
        } else {
            $studentObligation->update([
                'status' => 'pending',
                'verified_at' => null,
                'verified_by' => null,
                'proof_image' => null // Optional: clear proof if rejected? Or keep it? keeping it for history might be better but status pending allows re-upload.
            ]);
        }

        return response()->json(['message' => 'Status updated.']);
    }

    public function destroy(FinancialObligation $financialObligation)
    {
        $financialObligation->delete();
        return response()->json(['message' => 'Tanggungan berhasil dihapus.']);
    }
}
