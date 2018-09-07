<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTagsTable extends Migration
{
    public function up()
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->unsignedInteger('source_id');
            $table->timestamp('updated_at')->useCurrent();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('source_id')->references('id')->on('sources')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('tags');
    }
}