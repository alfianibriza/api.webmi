<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Http\Request;

class AlumniController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Student::where('status', 'alumni');

        if ($request->has('year') && $request->year != '') {
            $query->where('graduation_year', $request->year);
        }

        $alumni = $query->orderBy('graduation_year', 'desc')
            ->orderBy('name', 'asc')
            ->get();

        return response()->json($alumni);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Usually alumni are promoted from students, but manual add supported
        $rules = [
            'name' => 'required|string|max:255',
            'nisn' => 'nullable|string|max:20',
            'graduation_year' => 'required|numeric',
            'gender' => 'required|in:L,P',
            'status' => 'required|in:alumni',
        ];

        if ($request->hasFile('image')) {
            $rules['image'] = 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048';
        } else {
            $rules['image'] = 'nullable|string';
        }

        $validated = $request->validate($rules);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('students', 'public');
        }

        $student = Student::create($validated);

        return response()->json($student, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $student = Student::findOrFail($id);
        return response()->json($student);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $student = Student::findOrFail($id);

        $rules = [
            'name' => 'required|string|max:255',
            'graduation_year' => 'nullable|numeric',
            // Add other fields as needed
        ];

        if ($request->hasFile('image')) {
            $rules['image'] = 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048';
        } else {
            $rules['image'] = 'nullable|string';
        }

        $validated = $request->validate($rules);

        if ($request->hasFile('image')) {
            // Delete old image if exists and not using media library path recycling (which we usually don't delete if re-selecting same)
            // But if specific file upload, we usually delete old. 
            // If string path, we just update path.
            if ($student->image && $student->image !== $request->image) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($student->image);
            }
            $validated['image'] = $request->file('image')->store('students', 'public');
        }

        $student->update($validated);

        return response()->json($student);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $student = Student::findOrFail($id);
        $student->delete();

        return response()->json(['message' => 'Alumni deleted successfully']);
    }
}
