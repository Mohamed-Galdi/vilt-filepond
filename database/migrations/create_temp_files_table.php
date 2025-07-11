<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('temp_files', function (Blueprint $table) {
            $table->id();
            $table->string('original_name');
            $table->string('filename');
            $table->string('path');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->string('folder');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('temp_files');
    }
};
