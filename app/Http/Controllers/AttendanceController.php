<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\ProfileSection;
use App\Services\ImageService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    /**
     * Get Attendance List (Admin & Teacher History)
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Attendance::with('user');

        // If Teacher, only see own data
        if ($user->role === 'guru') {
            $query->where('user_id', $user->id);
        }

        // Filter by Date
        if ($request->filled('date')) {
            $query->whereDate('date', $request->date);
        }

        // Filter by Month/Year
        if ($request->filled('month') && $request->filled('year')) {
            $query->whereMonth('date', $request->month)
                ->whereYear('date', $request->year);
        }

        // Filter by Status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by Type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Admin Search by Name
        if ($user->role === 'admin' && $request->filled('search')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%');
            });
        }

        $attendances = $query->latest()->paginate(20)->withQueryString();

        return response()->json($attendances);
    }

    /**
     * Check In (Absen Masuk)
     */
    public function checkIn(Request $request)
    {
        return $this->processAttendance($request, 'masuk');
    }

    /**
     * Check Out (Absen Keluar)
     */
    public function checkOut(Request $request)
    {
        return $this->processAttendance($request, 'keluar');
    }

    /**
     * Centralized processing
     */
    private function processAttendance(Request $request, $type)
    {
        $request->validate([
            'photo' => 'required|image|max:5120', // 5MB max
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $user = $request->user();
        $now = Carbon::now();
        $startTime = $type === 'masuk' ? '07:00' : '10:00';
        $endTime = $type === 'masuk' ? '08:00' : '13:00';

        // 1. Time Validation
        $currentTime = $now->format('H:i');
        if ($currentTime < $startTime || $currentTime > $endTime) {
            // Requirement: "Di luar jam tersebut: Tombol absen nonaktif ATAU Status otomatis: Di luar jam absen"
            // API should reject or allow with status? Plan says "Status otomatis: Di luar jam absen".
            // But existing enum is pending/approved/rejected.
            // "Ditolak (Di Luar Jam)" -> rejected + note.
            // Let's proceed but mark as rejected immediately?
            // Or return error? Frontend should handle disabled button.
            // If user forces API call, return error "Di luar jam absensi".
            return response()->json(['message' => 'Saat ini bukan jam absen ' . $type], 422);
        }

        // 2. Double Check Validation
        $exists = Attendance::where('user_id', $user->id)
            ->whereDate('date', $now->toDateString())
            ->where('type', $type)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Anda sudah melakukan absen ' . $type . ' hari ini.'], 422);
        }

        // 3. Location Validation
        $settings = $this->getSettingsData();
        $distance = $this->calculateDistance(
            $request->latitude,
            $request->longitude,
            $settings['latitude'],
            $settings['longitude']
        );

        $locationStatus = 'valid';
        $status = 'pending';
        // Note: For now we don't auto-reject for invalid location, strictly following requirements:
        // "Jika guru berada di luar radius: Data absensi tetap tersimpan, Status otomatis: Menunggu Persetujuan (Lokasi Tidak Valid)"
        // This maps to status=pending, location_status=invalid.

        if ($distance > $settings['radius']) {
            $locationStatus = 'invalid';
        }

        // 4. Save Photo
        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = ImageService::upload($request->file('photo'), 'attendance/teachers');
        }

        // 5. Store
        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => $now->toDateString(),
            'type' => $type,
            'status' => $status, // Pending
            'photo' => $photoPath,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'location_status' => $locationStatus,
            'admin_note' => null,
        ]);

        return response()->json([
            'message' => 'Absensi ' . $type . ' berhasil dikirim!',
            'data' => $attendance
        ], 201);
    }

    /**
     * Admin Approve
     */
    public function approve(Attendance $attendance)
    {
        $attendance->update([
            'status' => 'approved',
            'admin_note' => null // Clear note if approved
        ]);

        return response()->json(['message' => 'Absensi disetujui.', 'data' => $attendance]);
    }

    /**
     * Admin Reject
     */
    public function reject(Request $request, Attendance $attendance)
    {
        $request->validate([
            'admin_note' => 'required|string',
        ]);

        $attendance->update([
            'status' => 'rejected',
            'admin_note' => $request->admin_note
        ]);

        return response()->json(['message' => 'Absensi ditolak.', 'data' => $attendance]);
    }

    /**
     * Get Settings
     */
    public function getSettings()
    {
        return response()->json($this->getSettingsData());
    }

    /**
     * Update Settings
     */
    public function updateSettings(Request $request)
    {
        $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'radius' => 'required|numeric',
        ]);

        ProfileSection::updateOrCreate(
            ['key' => 'attendance_settings'],
            [
                'title' => 'Pengaturan Absensi Guru',
                'content' => json_encode([
                    'latitude' => $request->latitude,
                    'longitude' => $request->longitude,
                    'radius' => $request->radius,
                ]),
            ]
        );

        return response()->json(['message' => 'Pengaturan berhasil disimpan.']);
    }

    // Helper: Get Settings Data
    private function getSettingsData()
    {
        $section = ProfileSection::where('key', 'attendance_settings')->first();
        if ($section && $section->content) {
            $data = json_decode($section->content, true);
            return [
                'latitude' => $data['latitude'] ?? -7.039600, // Default fallback
                'longitude' => $data['longitude'] ?? 113.918000,
                'radius' => $data['radius'] ?? 100, // Default 100 meters
            ];
        }

        // Factory default / Hardcoded fallback
        return [
            'latitude' => -7.039600,
            'longitude' => 113.918000,
            'radius' => 100
        ];
    }

    // Helper: Haversine Formula for distance (meters)
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000; // Meters

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
