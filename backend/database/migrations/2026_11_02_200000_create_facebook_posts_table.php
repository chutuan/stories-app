<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Truyện đã đăng lên Page Facebook TunaStory — mỗi truyện một lần. Xem App\Services\FacebookPublisher.
 *
 * Dòng có post_id mà chưa có comment_id: bài đã lên nhưng bình luận chứa link chưa được — lệnh
 * facebook:post-next gửi bù bình luận trước khi đăng bài mới.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facebook_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('post_id')->nullable();
            $table->string('comment_id')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facebook_posts');
    }
};
