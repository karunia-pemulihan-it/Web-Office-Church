<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProgramController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Program::class, 'program');
    }

    /* List Program */
    public function index()
    {
        $user = Auth::user();

        if ($user->hasRole('tim_bidang')) {
            $programs = Program::where('bidang_id', $user->bidang_id)->latest()->get();
        } else {
            $programs = Program::latest()->get();
        }

        return view('programs.index', compact('programs'));
    }

    /* Form tambah program */
    public function create()
    {
        return view('programs.create');
    }

    /* Simpan program baru */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_program'      => 'required|string|max:255',
            'deskripsi'         => 'nullable|string',
            'tanggal_mulai'     => 'required|date',
            'tanggal_selesai'   => 'required|date|after_or_equal:tanggal_mulai',
        ]);

        $user = Auth::user();

        $program = Program::create([
            'nama_program'      => $validated['nama_program'],
            'deskripsi'         => $validated['deskripsi'] ?? null,
            'tanggal_mulai'     => $validated['tanggal_mulai'],
            'tanggal_selesai'   => $validated['tanggal_selesai'],
            'status'            => 'draft',
            'bidang_id'         => $user->bidang_id,
            'created_by'        => $user->id,
        ]);

        return redirect()
            ->route('programs.index', $program)
            ->with('status', 'Program berhasil dibuat');
    }

    public function show(Program $program)
    {
        $program->load(['proposals.files']);
        return view('programs.show', compact('program'));
    }

    /* Method: Hapus Program */
    public function destroy(Program $program)
    {
        $program->delete();
        return redirect()
        ->route('programs.index')
        ->with('status', 'Program berhasil dihapus');
    }

    /* Edit Program */
    public function edit(Program $program)
    {
        return view('programs.edit', compact('program'));
    }

    /* Update Program */
    public function update(Request $request, Program $program)
    {
        $validated = $request->validate([
            'nama_program'      => 'required|string|max:255',
            'deskripsi'         => 'nullable|string',
            'tanggal_mulai'     => 'required|date',
            'tanggal_selesai'   => 'required|date|after_or_equal:tanggal_mulai',
        ]);

        $program->update($validated);

        return redirect()->route('programs.show', $program)->with('status', 'Program berhasil diperbarui');
    }

    public function changeStatus(Request $request, Program $program)
    {
        $this->authorize('changeStatus', $program);

        $request->validate([
            'status' => 'required|in:draft,berjalan,selesai',
        ]);

        $current = $program->status;
        $target = $request->status;

        // Aturan transisi tidak boleh lompat
        $allowed = match ($current) {
            'draft'     => ['draft', 'berjalan'],
            'berjalan'  => ['berjalan', 'selesai'],
            'selesai'   => ['selesai'],
            default     => [$current],
        };

        if(! in_array($target, $allowed, true)) {
            return back()->with('error', 'Transisi status tidak di-izinkan.');
        }

        // Draft -> Berjalan, hanya boleh jika ada minimal 1 proposal final diterima
        if ($current === 'draft' && $target === 'berjalan') {
            $hasFinalApprovedProposal = $program->proposals()
            ->where('status', 'diterima')
            ->exists();

            if (! $hasFinalApprovedProposal) {
                return back()->with('error', 'Belum ada proposal final yang disetujui');
            }
        }

        $program->update([
            'status' => $request->status,
        ]);

        return redirect()
            ->route('programs.show', $program)
            ->with('status', 'Status program berhasil diubah');
    }
}
