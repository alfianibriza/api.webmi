<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskAssignee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TaskController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $tasks = Task::with('creator')
            ->withCount([
                'assignees as total_assigned',
                'assignees as pending_count' => function ($query) {
                    $query->where('status', 'pending');
                },
                'assignees as submitted_count' => function ($query) {
                    $query->where('status', 'submitted');
                },
                'assignees as approved_count' => function ($query) {
                    $query->where('status', 'approved');
                },
                'assignees as rejected_count' => function ($query) {
                    $query->where('status', 'rejected');
                },
            ])
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $tasks
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|in:simple,upload,text',
            'target_type' => 'required|in:all,selected',
            'selected_teachers' => 'required_if:target_type,selected|array',
            'selected_teachers.*' => 'exists:users,id',
        ]);

        DB::beginTransaction();
        try {
            $task = Task::create([
                'title' => $request->title,
                'description' => $request->description,
                'type' => $request->type,
                'deadline' => $request->deadline,
                'created_by' => $request->user()->id,
            ]);

            $teacherIds = [];
            if ($request->target_type === 'all') {
                // Get all users with role 'guru'
                // Assuming simple role column or Laratrust logic. 
                // Based on User model 'role' attribute (from previous view_file of User.php it has 'role')
                // Check if role is comma separated or single string. It seems single string based on 'rule:admin,guru' middleware usage in api.php usually implies 'hasRole' but User model has 'role' fillable.
                // Let's assume 'role' column or a scope.
                // Looking at routes, user has `role:guru` middleware.
                // User::where('role', 'guru')->get() might be unsafe if using a package.
                // But I'll stick to a safe query assuming 'role' column exists as seen in User.php.
                $teacherIds = User::where('role', 'guru')->pluck('id')->toArray();
            } else {
                $teacherIds = $request->selected_teachers;
            }

            foreach ($teacherIds as $userId) {
                TaskAssignee::create([
                    'task_id' => $task->id,
                    'user_id' => $userId,
                    'status' => 'pending',
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Task created successfully',
                'data' => $task
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create task: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Task $task)
    {
        $task->load(['assignees.user']);

        // Group assignees by status for easier frontend consumption if needed, 
        // or just return the flat list.
        return response()->json([
            'status' => 'success',
            'data' => $task
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Task $task)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|in:simple,upload,text',
        ]);

        $task->update([
            'title' => $request->title,
            'description' => $request->description,
            'type' => $request->type,
            'deadline' => $request->deadline,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Task updated successfully',
            'data' => $task
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Task $task)
    {
        $task->delete(); // Cascades delete assignees due to DB constraint
        return response()->json([
            'status' => 'success',
            'message' => 'Task deleted successfully'
        ]);
    }

    /**
     * Verify a submission (Approve/Reject)
     */
    public function verifySubmission(Request $request, TaskAssignee $assignee)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected',
            'admin_feedback' => 'required_if:status,rejected|string|nullable'
        ]);

        $assignee->status = $request->status;
        $assignee->admin_feedback = $request->admin_feedback;

        if ($request->status === 'approved') {
            $assignee->completed_at = now();
        } else {
            // If rejected, it might go back to pending or stay rejected but allow resubmission.
            // User requirement: "Task yang ditolak admin harus bisa diperbaiki dan dikirim ulang."
            // So status 'rejected' is fine, logic in Guru controller should allow upgrade from rejected to submitted.
            $assignee->completed_at = null;
        }

        $assignee->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Submission ' . $request->status,
            'data' => $assignee
        ]);
    }
}
