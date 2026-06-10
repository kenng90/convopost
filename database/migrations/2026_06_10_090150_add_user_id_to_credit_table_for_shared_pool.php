<?php

use App\Models\Company;
use App\Models\Credit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('company_id');
            $table->foreign('user_id')->references('id')->on('users');
            $table->index(['user_id', 'expiration_date']);
        });

        Credit::query()
            ->whereNotNull('company_id')
            ->whereNull('user_id')
            ->orderBy('id')
            ->each(function (Credit $credit): void {
                $company = Company::query()->find($credit->company_id);

                if ($company?->user_id) {
                    $credit->user_id = $company->user_id;
                    $credit->save();
                }
            });

        $duplicateSources = DB::table('credit')
            ->whereNotNull('user_id')
            ->where('source', 'like', 'plan:%')
            ->whereNull('deleted_at')
            ->select('user_id', 'source')
            ->groupBy('user_id', 'source')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicateSources as $duplicate) {
            $credits = Credit::query()
                ->where('user_id', $duplicate->user_id)
                ->where('source', $duplicate->source)
                ->orderBy('id')
                ->get();

            $primary = $credits->shift();

            if ($primary === null) {
                continue;
            }

            foreach ($credits as $extra) {
                $primary->credit_amount += $extra->credit_amount;
                $primary->remaining_credit_amount += $extra->remaining_credit_amount;
                $primary->used_credit_amount += $extra->used_credit_amount;
                $primary->save();
                $extra->delete();
            }
        }
    }

    public function down(): void
    {
        Schema::table('credit', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex(['user_id', 'expiration_date']);
            $table->dropColumn('user_id');
        });
    }
};
