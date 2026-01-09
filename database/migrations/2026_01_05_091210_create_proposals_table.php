<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('proposals', function (Blueprint $table) {
            $table->id();

            // Relasi Utama
            $table->foreignId('program_id')
            ->constrained('programs')
            ->cascadeOnDelete();

            $table->foreignId('created_by')
            ->nullable()
            ->constrained('users')
            ->nullOnDelete();

            // Metadata Proposal
            $table->string('judul');
            $table->text('tujuan');

            // Status Approval
            $table->enum('status', ['review', 'diterima', 'ditolak'])->default('review');

            // Tahap review (no bypass)
            $table->enum('stage', ['ketua', 'bendahara_1', 'bendahara_2'])
            ->default('ketua');

            // Jejak approval per tahap (LENGKAP)
            $table->foreignId('ketua_approved_by')
            ->nullable()
            ->constrained('users')
            ->nullOnDelete();

            $table->timestamp('ketua_approved_at')->nullable();

            $table->foreignId('bendahara1_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('bendahara1_approved_at')->nullable();

            $table->foreignId('bendahara2_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('bendahara2_approved_at')->nullable();

            // Info penolakan terakhir (cukup untuk audit + re-submit)
            $table->text('reject_reason')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->enum('rejected_stage', ['ketua', 'bendahara_1', 'bendahara_2'])->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proposals');
    }
};
