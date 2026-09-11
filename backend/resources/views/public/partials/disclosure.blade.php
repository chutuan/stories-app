{{--
  Dòng khai minh bạch về cách truyện được soạn.

  Vì sao cần: Google Publisher Policies cấm "khai gian về danh tính hoặc liên kết".
  Site hiển thị byline mang tên Vivian Pryce, Nora Calloway, Marin Halloway... —
  bút danh, không có con người nào mang tên đó. Bút danh trong văn học hoàn toàn
  hợp lệ và Google không cấm; cái tạo ra rủi ro là hiển thị chúng như tác giả thật
  mà toàn site không có một dòng nào đính chính. Lượt soát quét 96 trang tìm
  "AI-generated", "pen name", "pseudonym", "text-to-speech" và thấy ĐÚNG 0 trang.

  Khai thẳng vừa gỡ rủi ro đó, vừa trả lời sẵn câu hỏi mà người soát AdSense và
  người soát App Store đều sẽ đặt ra.
--}}
<p class="disclosure{{ ($inline ?? false) ? ' inline' : '' }}">
  Stories on this site are written for us with the help of AI writing tools and
  reviewed before publication. Author names are house pen names, not real people.
  Cover art is AI-generated and narration is synthesised speech.
  <a href="{{ route('public.terms') }}">More about how these are made</a>.
</p>
