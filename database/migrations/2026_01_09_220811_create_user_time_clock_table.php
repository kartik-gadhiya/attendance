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
        Schema::create('user_time_clock', function (Blueprint $table) {
            $table->id(); // bigint unsigned auto_increment primary key
            $table->integer('shop_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->date('date_at');
            $table->time('time_at');
            $table->dateTime('date_time')->nullable();
            $table->dateTime('formated_date_time')->nullable()->comment('Shop Timezone Date and Time Format');
            $table->time('shift_start')->nullable();
            $table->time('shift_end')->nullable();
            $table->string('type', 255);
            $table->text('comment')->nullable();
            $table->integer('buffer_time')->nullable();
            $table->char('created_from', 1)->nullable();
            $table->char('updated_from', 1)->nullable();
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade')
                  ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_time_clock');
    }
};
