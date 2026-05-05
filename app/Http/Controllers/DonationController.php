<?php

namespace App\Http\Controllers;

use App\Models\Donation;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DonationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Fetch User's donation history matched by user_id
        $history = Donation::where('user_id', \Illuminate\Support\Facades\Auth::id())
            ->latest()
            ->get();

        return Inertia::render('Dashboard/Donation/Index', [
            'history' => $history,
            'settings' => \App\Models\DonationSetting::first()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'donor_name' => 'required|string|max:255',
            'transaction_number' => 'required|string|max:255',
            'amount' => 'required|numeric|min:1000',
        ]);

        Donation::create([
            'user_id' => \Illuminate\Support\Facades\Auth::id(),
            'donor_name' => $request->donor_name,
            'transaction_number' => $request->transaction_number,
            'amount' => $request->amount,
            'status' => 'pending',
        ]);

        return redirect()->back()->with('success', 'Donasi berhasil disimpan. Silahkan lanjutkan konfirmasi via WhatsApp.');
    }

    /**
     * Display a listing of donations for Admin.
     */
    public function adminIndex()
    {
        $donations = Donation::latest()->get();
        $totalDonations = Donation::where('status', 'approved')->sum('amount');

        return Inertia::render('Dashboard/Donation/AdminIndex', [
            'donations' => $donations,
            'totalDonations' => $totalDonations
        ]);
    }

    /**
     * Update donation status.
     */
    public function updateStatus(Request $request, Donation $donation)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected,pending'
        ]);

        $donation->update(['status' => $request->status]);

        return redirect()->back()->with('success', 'Status donasi berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Donation $donation)
    {
        $donation->delete();

        return redirect()->back()->with('success', 'Data donasi berhasil dihapus.');
    }

    /**
     * Show the form for editing donation settings.
     */
    public function settings()
    {
        $settings = \App\Models\DonationSetting::first();
        return Inertia::render('Dashboard/Donation/Settings', [
            'settings' => $settings
        ]);
    }

    /**
     * Update the donation settings.
     */
    public function updateSettings(Request $request)
    {
        $request->validate([
            'bank_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:255',
            'account_holder' => 'required|string|max:255',
            'wa_number' => 'nullable|string|max:20',
        ]);

        $settings = \App\Models\DonationSetting::first();
        if (!$settings) {
            \App\Models\DonationSetting::create($request->all());
        } else {
            $settings->update($request->all());
        }

        return redirect()->back()->with('success', 'Pengaturan rekening tujuan berhasil diperbarui.');
    }
}
