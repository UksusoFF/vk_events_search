<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWysiwygMediaTable extends Migration
{
    public function up()
    {
        Schema::create('wysiwyg_media', function (Blueprint $table) {
            $table->increments('id');
            $table->string('file_path');
            $table->unsignedInteger('wysiwygable_id')->nullable()->index();
            $table->string('wysiwygable_type')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('wysiwyg_media');
    }
}