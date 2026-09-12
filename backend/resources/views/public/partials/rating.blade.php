{{--
  Điểm hài lòng của một truyện, gộp từ phiếu của mọi chương.

  CHỈ hiện khi đã đủ MIN_VOTES phiếu. Truyện mới đăng chưa ai bấm mà hiện "0/5"
  hoặc một khuôn mặt cau có thì vừa sai vừa dập tắt ý muốn đọc — và một phiếu duy
  nhất cũng không nói lên điều gì.

  Dự án này từng phải gỡ điểm đánh giá DỰNG SẴN khỏi ứng dụng di động (8.9 sao,
  "1.2K ratings" đều là bịa) vì đó đúng là thứ Apple đánh trượt. Số ở đây là phiếu
  thật của người đọc thật; đừng bao giờ thay bằng số mồi.
--}}
@php
    $votes = (int) ($story->reactions_count ?? 0);
    $avg = $votes >= 3 ? round((float) $story->reactions_avg_score, 1) : null;
@endphp

@if ($avg)
  <span class="rating{{ ($compact ?? false) ? ' compact' : '' }}"
        title="{{ $avg }} out of 5 from {{ $votes }} readers">
    <svg viewBox="0 0 37 37" aria-hidden="true" focusable="false">
      <circle cx="18.5" cy="18.5" r="16.5" fill="none" stroke="currentColor" stroke-width="2.4"/>
      <circle cx="13" cy="15.5" r="2" fill="currentColor"/>
      <circle cx="24" cy="15.5" r="2" fill="currentColor"/>
      {{-- Miệng cong theo điểm: 3/5 là đường thẳng, càng cao càng cười. --}}
      <path d="M11.5 {{ 24 - ($avg - 3) * 1.6 }}q7 {{ ($avg - 3) * 6 }} 14 0"
            fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>
    </svg>
    <span>{{ number_format($avg, 1) }}</span>
    @unless ($compact ?? false)
      <span class="rating-count">from {{ $votes }} {{ Str::plural('reader', $votes) }}</span>
    @endunless
  </span>
@endif
