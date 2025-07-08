<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	/**
	 * Run the migrations.
	 */
	public function up(): void
	{
		Schema::create('data_scopes', function (Blueprint $table) {
			$table->id();

			$table->morphs('scopeable');
			$table->json('scope')->comment('数据范围');

			$table->timestamps();
			$table->comment('数据范围表');
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('data_scopes');
	}
};
