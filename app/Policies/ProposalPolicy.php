<?php

namespace App\Policies;

use App\Models\Program;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProposalPolicy
{
    private function jabatanKey(User $user): string
    {
        $jabatan = strtolower(trim($user->jabatan ?? ''));
        return str_replace([' ', '-'], '_', $jabatan);
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Proposal $proposal): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Program $program): bool
    {
        return $user->hasRole('tim_bidang')
        && $user->bidang_id !== null
        && $program->status !== 'selesai'
        && $program->bidang_id === $user->bidang_id;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Proposal $proposal): bool
    {
        if (! $user->hasRole('tim_bidang')) return false;

        // hanya pemilik proposal
        $isOwner = ($proposal->user_id === $user->id);

        // masih dalam bidang yang sama
        $sameBidang = ($proposal->program?->bidang_id === $user->bidang_id);

        // program belum selesai (status program kamu lowercase: "selesai")
        $programNotClosed = ($proposal->program?->status !== 'selesai');

        // Opsi B: boleh edit saat "review" dan "ditolak"
        $statusOk = in_array($proposal->status, ['review', 'ditolak'], true);

        return $isOwner && $sameBidang && $programNotClosed && $statusOk;
    }


    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Proposal $proposal): bool
    {
        // Opsi B: delete sama rule-nya dengan update
        return $this->update($user, $proposal);
    }

    /**
     * Approve
     */
    public function approve(User $user, Proposal $proposal): bool
    {
        if ($user->hasRole('super_admin')) return true;
        if (! $user->hasRole('tim_inti')) return false;

        // hanya bisa approve kalau sedang direview
        if ($proposal->status !== 'review') return false;

        $jabatan = $this->jabatanKey($user);

        return match ($proposal->stage) {
            'ketua' => $jabatan === 'ketua',
            'bendahara_1' => $jabatan === 'bendahara_1',
            'bendahara_2' => $jabatan === 'bendahara_2',
            default => false,
        };
    }

    /**
     * Reject
     */
    public function reject(User $user, Proposal $proposal): bool
    {
        // rule reject sama dengan approve (sesuai stage)
        return $this->approve($user, $proposal);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Proposal $proposal): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Proposal $proposal): bool
    {
        return false;
    }
}
