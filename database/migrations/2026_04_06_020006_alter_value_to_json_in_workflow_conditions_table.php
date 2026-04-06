<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up()
    {
        // ⚠️ MySQL nécessite parfois de passer par TEXT avant JSON
        Schema::table('workflow_conditions', function (Blueprint $table) {
            $table->text('value')->nullable()->change();
        });

        // 🔹 Conversion des anciennes valeurs vers JSON
        DB::table('workflow_conditions')->get()->each(function ($condition) {

            $value = $condition->value;

            if (is_null($value)) {
                return;
            }

            // Vérifier si déjà JSON
            json_decode($value);
            if (json_last_error() === JSON_ERROR_NONE) {
                return; // déjà bon
            }

            // Sinon convertir en tableau JSON
            DB::table('workflow_conditions')
                ->where('id', $condition->id)
                ->update([
                    'value' => json_encode([$value])
                ]);
        });

        // 🔹 Si ton MySQL supporte JSON (5.7+), tu peux activer ça 👇
        Schema::table('workflow_conditions', function (Blueprint $table) {
            $table->json('value')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('workflow_conditions', function (Blueprint $table) {
            $table->string('value', 255)->nullable()->change();
        });
    }
};