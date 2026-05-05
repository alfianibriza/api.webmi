<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ChangePasswordController extends Controller
{
    public function show()
    {
        return \Inertia\Inertia::render('Auth/ChangePassword');
    }

    public function update(Request $request)
    {
        $request->validate([
            'current_password' => 'required', // Optional: Verify current password if default is known
            'password' => 'required|string|min:6|confirmed',
        ]);

        // Optional: Verify current password matches?
        if (!\Illuminate\Support\Facades\Hash::check($request->current_password, $request->user()->password)) {
            return back()->withErrors(['current_password' => 'Password saat ini salah.']);
        }

        $user = $request->user();
        $user->update([
            'password' => \Illuminate\Support\Facades\Hash::make($request->password),
            'must_change_password' => false
        ]);

        // Sync "plain_password" for Teachers (Admin Reference)
        $teacher = \App\Models\Teacher::where('user_id', $user->id)->first();
        if ($teacher) {
            $teacher->update(['plain_password' => $request->password]);
        }

        // Sync "plain_password" for Students
        $student = \App\Models\Student::where('user_id', $user->id)->first();
        if ($student) {
            $student->update(['plain_password' => $request->password]);
        }

        return redirect()->route('dashboard')->with('success', 'Password berhasil diubah. Selamat datang!');
    }
}
