<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBlog extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        Schema::create('blogs', function (Blueprint $table) {
            $table->bigIncrements('id');
            
            // Basic blog fields
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('content');
            $table->text('excerpt')->nullable();
            $table->string('featured_image')->nullable();
            $table->string('external_link')->nullable();
            
            //Blog post type
            $table->string('post_type')->default("post");

            //Read time
            $table->integer('read_time')->default(1);
            
            // SEO fields
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->string('meta_keywords')->nullable();
            
            // Status and visibility
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->boolean('is_featured')->default(false);
            
            
            $table->timestamps();
            $table->softDeletes(); // Allows for trash/restore functionality
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {

        Schema::dropIfExists('blogs');
    }
}
