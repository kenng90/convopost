<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEmbeddingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('flowdocuments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flow_id');
            $table->string('title')->nullable(); //The title of the document (if any)
            $table->string('source_type')->nullable();    // e.g. ( pdf, .txt, .docx, .doc, .xls, .xlsx, ), faq, website
            $table->string('source_url')->nullable();     // file path or URL, 
            $table->longText('content')->nullable();      // the full content of the document
            $table->timestamps();
        });
        
        Schema::create('embeddedchunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('flowdocuments')->onDelete('cascade');
            $table->longText('content');     // the chunk of text
            $table->json('embedding');       // the embedding vector
            $table->timestamps();
        });
        
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('embeddedchunks');
        Schema::dropIfExists('flowdocuments');
    }
}
