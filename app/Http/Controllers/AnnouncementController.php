<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\User;
use App\Notifications\AnnouncementCreated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class AnnouncementController extends Controller
{
    // Admin: List all announcements
    public function index()
    {
        $announcements = Announcement::with('creator')->latest()->get();
        return response()->json($announcements);
    }

    // Admin: Create announcement
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'target_type' => 'required|in:all,guru,wali_murid',
        ]);

        $announcement = Announcement::create([
            'title' => $request->title,
            'content' => $request->content,
            'target_type' => $request->target_type,
            'created_by' => $request->user()->id,
        ]);

        // Send Notifications
        $users = collect();

        if ($request->target_type === 'all') {
            $users = User::whereIn('role', ['guru', 'wali_murid', 'admin'])->get(); // Send to all relevant roles
        } elseif ($request->target_type === 'guru') {
            $users = User::where('role', 'guru')->get();
        } elseif ($request->target_type === 'wali_murid') {
            $users = User::where('role', 'wali_murid')->get();
        }

        \Illuminate\Support\Facades\Log::info('Creating announcement', [
            'target' => $request->target_type,
            'user_count' => $users->count(),
            'users_ids' => $users->pluck('id')
        ]);

        Notification::send($users, new AnnouncementCreated($announcement));

        return response()->json(['message' => 'Pengumuman berhasil dibuat dan dikirim!', 'announcement' => $announcement], 201);
    }

    // Admin: Delete announcement
    public function destroy(Announcement $announcement)
    {
        $announcement->delete();
        return response()->json(['message' => 'Pengumuman berhasil dihapus']);
    }

    // User: Get Notifications
    public function myNotifications(Request $request)
    {
        $notifications = $request->user()->notifications()->latest()->take(20)->get();
        $unreadCount = $request->user()->unreadNotifications->count();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount
        ]);
    }

    // User: Mark notification as read
    public function markAsRead(Request $request, $id)
    {
        $notification = $request->user()->notifications()->where('id', $id)->first();
        if ($notification) {
            $notification->markAsRead();
        }

        return response()->json(['message' => 'Notifikasi ditandai sudah dibaca']);
    }

    // User: Mark all as read
    public function markAllAsRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();
        return response()->json(['message' => 'Semua notifikasi ditandai sudah dibaca']);
    }
}
