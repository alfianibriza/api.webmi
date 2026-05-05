<?php

namespace App\Http\Controllers;

use App\Models\StudentObligation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StudentFinancialController extends Controller
{
    public function index()
    {
        // Assuming the logged in user is a student or parent linked to a student
        // Currently Auth::user() might be the student if they have login.
        // Or if parent, need logic to find students.
        // Assuming Auth::user() is linked to Student via user_id

        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Logic to get student(s) for this user.
        // If Model Student has user_id, it equates to the student user.
        $student = \App\Models\Student::where('user_id', $user->id)->first();

        // Handling for Parent role if needed (future proof), but usually student login direct.
        if (!$student && $user->role === 'student') {
            // Maybe user is a student but table not linked? or Parent?
            // Fallback or empty
            $obligations = [];
        } else {
            $rawObligations = StudentObligation::where('student_id', $student->id)
                ->with('financialObligation')
                ->latest()
                ->get();

            $obligations = $rawObligations->map(function ($item) {
                return [
                    'id' => $item->id, // ID of the StudentObligation (for payment reference)
                    'financial_obligation_id' => $item->financial_obligation_id,
                    'title' => $item->financialObligation->title ?? 'Tagihan Tanpa Judul',
                    'amount' => $item->financialObligation->amount ?? 0,
                    'description' => $item->financialObligation->description,
                    'due_date' => $item->financialObligation->due_date,
                    'status' => $item->status,
                    'paid_at' => $item->paid_at,
                    'created_at' => $item->created_at,
                ];
            });
        }

        return response()->json($obligations);
    }

    public function pay(Request $request, StudentObligation $studentObligation)
    {
        $request->validate([
            'proof_image' => 'nullable|image|max:2048', // Optional now
        ]);

        // Ensure this obligation belongs to the auth user's student
        $student = \App\Models\Student::where('user_id', auth()->id())->first();
        if ($studentObligation->student_id !== $student->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $updateData = [
            'status' => 'paid_verification',
            'paid_at' => now(), // User claims they paid now
        ];

        if ($request->hasFile('proof_image')) {
            $path = $request->file('proof_image')->store('payment_proofs', 'public');
            $updateData['proof_image'] = $path;
        }

        $studentObligation->update($updateData);

        return response()->json(['message' => 'Bukti pembayaran berhasil diupload. Menunggu verifikasi admin.']);
    }
}
