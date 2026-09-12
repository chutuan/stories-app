{{--
  Thang hài lòng ở cuối chương: giận dữ -> cười tươi, một lần bấm.

  Vẽ bằng SVG chứ KHÔNG dùng emoji. Emoji hiện khác nhau trên mỗi hệ điều hành —
  cùng một ký tự ra mặt vàng trên Apple, mặt khác trên Android, và trên vài máy
  Windows thì ra ô vuông rỗng. Năm khuôn mặt phải so sánh được với nhau thì thang
  đo mới có nghĩa, nên phải tự vẽ.

  Hoạt động KHÔNG CẦN JavaScript: mỗi mặt là một nút submit của form, bấm xong
  tải lại trang và nhảy về #reaction. Đoạn JS ở dưới chỉ để khỏi phải tải lại.

  BẤM XONG THÌ THANG BIẾN MẤT, chỉ còn lời cảm ơn. Để nguyên năm khuôn mặt sau khi
  đã bỏ phiếu là mời người đọc bấm tiếp một việc họ vừa làm xong — vừa chiếm chỗ
  vừa gây phân vân. Đánh đổi: không đổi phiếu được nữa; nếu sau này cần thì thêm
  một liên kết "Change" nhỏ ở đây.
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
  @if ($reaction['mine'])
    {{-- Đã bỏ phiếu: chỉ còn lời cảm ơn. --}}
    <h2 id="reaction-title" class="reaction-done">Thanks for rating this chapter.</h2>
    @if ($reaction['average'])
      <p class="reaction-note"><span class="reaction-stat">{{ $reaction['average'] }}/5 from {{ $reaction['count'] }} readers</span></p>
    @endif
  @else
    <h2 id="reaction-title">How was this chapter?</h2>

    <form method="POST" action="{{ route('public.react', [$story, $chapter->number]) }}" class="faces">
      @csrf
      @foreach ($faces as $score => $face)
        <button type="submit" name="score" value="{{ $score }}" class="face" aria-label="{{ $face['label'] }}">
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
      @if ($reaction['count'] === 0)
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
  @endif
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
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
      credentials: 'same-origin',
    }).then(function (r) {
      if (!r.ok) throw new Error(r.status);
      return r.json();
    }).then(function (data) {
      // Thay nguyên khối bằng lời cảm ơn, giống hệt thứ server dựng khi tải lại
      // trang — hai luồng phải cho ra cùng một kết quả, nếu không người tắt JS và
      // người bật JS sẽ thấy hai giao diện khác nhau.
      var box = document.getElementById('reaction');
      var title = box.querySelector('#reaction-title');
      title.textContent = 'Thanks for rating this chapter.';
      title.classList.add('reaction-done');
      form.remove();
      if (note) note.remove();
      if (data && data.average) {
        var p = document.createElement('p');
        p.className = 'reaction-note';
        p.innerHTML = '<span class="reaction-stat"></span>';
        p.firstChild.textContent = data.average + '/5 from ' + data.count + ' readers';
        box.appendChild(p);
      }
    }).catch(function () {
      // Hỏng thì nộp form theo cách thường, người đọc vẫn bỏ phiếu được.
      form.submit();
    });
  });
})();
</script>
