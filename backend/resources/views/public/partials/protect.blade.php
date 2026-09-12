{{--
  Chặn sao chép thân truyện.

  PHẢI NÓI RÕ NÓ LÀM ĐƯỢC GÌ, vì rất dễ tưởng đây là bảo vệ thật:
  nó chặn người đọc bình thường bôi đen rồi Ctrl+C, chặn menu chuột phải, chặn
  long-press "Copy" trên iOS, chặn kéo chữ và ảnh ra ngoài, chặn in ra PDF. Nó
  KHÔNG chặn được Ctrl+U xem nguồn, Reader Mode, devtools, "Save Page As", hay
  curl — tức là không chặn được đúng những kẻ thật sự đi lấy truyện, vì họ dùng
  script chứ không dùng chuột. Lớp chống scraper thật nằm ở robots.txt, nginx và
  Cloudflare.

  Phạm vi hẹp có chủ ý: chỉ thân chương và phần mô tả truyện. KHÔNG áp toàn trang,
  vì người đọc vẫn phải bôi đen được ô tìm kiếm, và phải copy được địa chỉ email
  liên hệ ở footer — chặn cả hai thứ đó là chặn người muốn liên lạc với mình.

  Trình đọc màn hình KHÔNG bị ảnh hưởng: user-select chỉ tác động lên con trỏ
  chuột, không lên cây accessibility.
--}}
<script>
(function () {
  var GUARDED = '.chapter-body, .synopsis';

  function inGuarded(node) {
    if (!node) return false;
    var el = node.nodeType === 1 ? node : node.parentElement;
    return !!(el && el.closest && el.closest(GUARDED));
  }

  // Chặn copy/cut. Bắt ở tầng document rồi mới kiểm vùng chọn, nên copy ở nơi
  // khác (email footer, ô tìm kiếm) vẫn hoạt động bình thường.
  ['copy', 'cut'].forEach(function (evt) {
    document.addEventListener(evt, function (e) {
      var sel = document.getSelection();
      if (sel && sel.rangeCount && inGuarded(sel.getRangeAt(0).commonAncestorContainer)) {
        e.preventDefault();
      }
    });
  });

  // Chặn menu chuột phải trên thân truyện và trên mọi ảnh (ảnh bìa là tài sản
  // riêng, "Save image as" cũng chặn luôn).
  document.addEventListener('contextmenu', function (e) {
    if (inGuarded(e.target) || (e.target.tagName === 'IMG')) e.preventDefault();
  });

  // Chặn kéo chữ/ảnh ra khỏi trang — kéo-thả là đường copy mà người ta hay quên.
  document.addEventListener('dragstart', function (e) {
    if (inGuarded(e.target) || e.target.tagName === 'IMG') e.preventDefault();
  });

  // Ctrl/Cmd+A trên trang. Bỏ qua khi con trỏ đang ở trong ô nhập, nếu không
  // người dùng không sửa lại được từ khoá tìm kiếm của mình.
  document.addEventListener('keydown', function (e) {
    if (!(e.ctrlKey || e.metaKey) || e.key.toLowerCase() !== 'a') return;
    var t = e.target.tagName;
    if (t === 'INPUT' || t === 'TEXTAREA' || e.target.isContentEditable) return;
    if (document.querySelector('.chapter-body')) e.preventDefault();
  });
})();
</script>
