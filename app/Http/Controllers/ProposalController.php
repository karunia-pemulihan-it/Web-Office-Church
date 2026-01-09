<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\Proposal;
use App\Models\ProposalFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ProposalController extends Controller
{
    /* Form Upload Proposal */
    public function create(Program $program)
    {
        return view('proposals.create', compact('program'));
    }

    public function store(Request $request, Program $program)
    {
        $validated = $request->validate([
            'judul'      => 'required|string|max:255',
            'tujuan'     => 'required|string',
            'files'      => 'required|array|min:1',
            'files.*'    => 'file|mimes:pdf|max:51200',
        ]);

        $proposal = Proposal::create([
            'program_id'    => $program->id,
            'created_by'    => Auth::id(),
            'judul'         => $validated['judul'],
            'tujuan'        => $validated['tujuan'],
            'status'        => 'review',
            'stage'         => 'ketua'
        ]);

        foreach ($request->file('files') as $file) {
            $path = $file->store('proposal_files', 'public');

            ProposalFile::create([
                'proposal_id'   => $proposal->id,
                'original_name' => $file->getClientOriginalName(),
                'file_path'     => $path,
                'mime_type'     => $file->getMimeType(),
                'file_size'     => $file->getSize(),
            ]);
        }

        return redirect()
            ->route('programs.show', $program)
            ->with('status', 'Proposal berhasil diajukan dan menunggu persetujuan');
    }

    public function approve(Program $program, Proposal $proposal)
    {
        $this->authorize('approve', $proposal);

        // Hanya bisa approve ketika status masih "Review"
        if ($proposal->status !== 'review') {
            return back()->with('error', 'Proposal sudah tidak dalam status REVIEW');
        }

        $userId = Auth::id();
        $now = Carbon::now();

        if($proposal->stage === 'ketua') {
            $proposal->update([
                'ketua_approved_by'     => $userId,
                'ketua_approved_at'     => $now,
                'stage'                 => 'bendahara_1',
                'reject_reason'         => null,
                'rejected_by'           => null,
                'rejected_at'           => null,
                'rejected_stage'        => null,
            ]);
        } elseif ($proposal->stage === 'bendahara_1') {
            $proposal->update([
                'bendahara1_approved_by'    => $userId,
                'bendahara1_approved_at'    => $now,
                'stage'                     => 'bendahara_2',
                'reject_reason'             => null,
                'rejected_by'               => null,
                'rejected_at'               => null,
                'rejected_stage'            => null,
            ]);
        } elseif ($proposal->stage === 'bendahara_2') {
            $proposal->update([
                'bendahara2_approved_by' => $userId,
                'bendahara2_approved_at' => $now,
                'status' => 'diterima',
                'reject_reason' => null,
                'rejected_by' => null,
                'rejected_at' => null,
                'rejected_stage' => null,
            ]);
        }

        return back()->with('success', 'Proposal berhasil di Approve');
    }

    public function reject(Request $request, Program $program, Proposal $proposal)
    {
        $this->authorize('reject', $proposal);

        $request->validate([
            'reject_reason' => 'required|string|min:5',
        ]);

        if ($proposal->status !== 'review') {
            return back()->with('error', 'Proposal sudah tidak dalam status REVIEW.');
        }

        $currentStage = $proposal->stage;

        // Jika B2 reject -> balik ke B1
        $nextStage = match ($currentStage) {
            'ketua' => 'ketua',
            'bendahara_1' => 'bendahara_1',
            'bendahara_2' => 'bendahara_1',
            default => 'ketua',
        };

        // Jika ditolak, set status ditolak, dan stage kembali sesuai role
        $proposal->update([
            'status' => 'ditolak',
            'stage' => $nextStage,
            'reject_reason' => $request->reject_reason,
            'rejected_by' => Auth::id(),
            'rejected_at' => now(),
            'rejected_stage' => $currentStage,
        ]);

        return back()->with('success', 'Proposal berhasil ditolak');
    }
}
