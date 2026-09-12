{{--
  Thang hài lòng ở cuối chương: giận dữ -> cười tươi, một lần bấm.

  Vẽ bằng SVG chứ KHÔNG dùng emoji. Emoji hiện khác nhau trên mỗi hệ điều hành —
  cùng một ký tự ra mặt vàng trên Apple, mặt khác trên Android, và trên vài máy
  Windows thì ra ô vuông rỗng. Năm khuôn mặt phải so sánh được với nhau thì thang
  đo mới có nghĩa, nên phải tự vẽ.

  Hoạt động KHÔNG CẦN JavaScript: mỗi mặt là một nút submit của form, bấm xong
  tải lại trang và nhảy về #reaction. Đoạn JS ở dưới chỉ để khỏi phải tải lại.
--}}
@php
    $faces = [
        1 => ['label' => 'Hated it',   'brow' => 'M8 10l5 2M24 12l5-2',  'mouth' => 'M11 26q7.5-7 15 0'],
        2 => ['label' => 'Not for me', 'brow' => null,                    'mouth' => 'M12 25q6.5-4 13 0'],
        3 => ['label' => 'It was ok',  'brow' => null,                    'mouth' => 'M12 24h13'],
        4 => ['label' => 'Liked it',   'brow' => null,                    'mouth' => 'M12 22q6.5 5 13 0'],
        5 => ['label' => 'Loved it',   'brow' => 'M8 12l5-2M24 10l5 2',  'mouth' => 'M10 21q8.5 8 17 0'],
    ];
@endphp

<section class="reaction" id="reaction" aria-labelledby="reaction-title">
  <h2 id="reaction-title">How was this chapter?</h2>

  <form method="POST" action="{{ route('public.react', [$story, $chapter->number]) }}" class="faces">
    @csrf
    @foreach ($faces as $score => $face)
      <button type="submit" name="score" value="{{ $score }}"
              class="face{{ ($reaction['mine'] ?? null) === $score ? ' chosen' : '' }}"
              aria-label="{{ $face['label'] }}"
              @if (($reaction['mine'] ?? null) === $score) aria-pressed="true" @endif>
        <svg viewBox="0 0 37 37" aria-hidden="true" focusable="false">
          <circle cx="18.5" cy="18.5" r="16.5" fill="none" stroke="currentColor" stroke-width="2"/>
          <circle cx="13" cy="15.5" r="1.9" fill="currentColor"/>
          <circle cx="24" cy="15.5" r="1.9" fill="currentColor"/>
          @if ($face['brow'])
            <path d="{{ $face['brow'] }}" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          @endif
          <path d="{{ $face['mouth'] }}" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <span class="face-label">{{ $face['label'] }}</span>
      </button>
    @endforeach
  </form>

  <p class="reaction-note">
    @if ($reaction['mine'])
      Thanks — your rating is saved. Tap another face to change it.
    @elseif ($reaction['count'] === 0)
      {{-- KHÔNG bịa số liệu khi chưa có phiếu nào. Dự án này từng phải gỡ điểm
           đánh giá dựng sẵn khỏi ứng dụng di động vì đó đúng là thứ Apple đánh
           trượt; đừng dựng lại cùng một vấn đề trên web. --}}
      Be the first to rate this chapter.
    @else
      One tap, no account needed.
    @endif

    @if ($reaction['average'])
      <span class="reaction-stat">{{ $reaction['average'] }}/5 from {{ $reaction['count'] }} readers</span>
    @endif
  </p>
</section>

{{-- Lớp nâng cấp: gửi phiếu bằng fetch để khỏi tải lại trang.
     Form ở trên vẫn hoạt động đầy đủ khi không có JavaScript — đoạn này chỉ chặn
     sự kiện submit, nên tắt JS thì quay về đúng luồng cũ chứ không hỏng. --}}
<script>
(function () {
  var form = document.querySelector('#reaction form');
  if (!form || !window.fetch) return;
  var note = document.querySelector('.reaction-note');

  form.addEventListener('submit', function (e) {
    var btn = e.submitter;
    if (!btn || !btn.value) return;          // không rõ nút nào -> để trình duyệt tự xử lý
    e.preventDefault();

    var body = new FormData(form);
    body.set('score', btn.value);

    fetch(form.action, {
      method: 'POST',
      body: body,
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    }).then(function (r) {
      if (!r.ok) throw new Error(r.status);
      form.querySelectorAll('.face').forEach(function (f) {
        f.classList.remove('chosen');
        f.removeAttribute('aria-pressed');
      });
      btn.classList.add('chosen');
      btn.setAttribute('aria-pressed', 'true');
      if (note) note.textContent = 'Thanks — your rating is saved. Tap another face to change it.';
    }).catch(function () {
      // Hỏng thì nộp form theo cách thường, người đọc vẫn bỏ phiếu được.
      form.submit();
    });
  });
})();
</script>
