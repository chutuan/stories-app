{{--
  Phân trang tối giản, thay bộ mặc định của Laravel (bộ đó kéo theo Tailwind mà
  trang này không dùng).

  rel="prev"/"next" là có chủ đích: nó cho Google biết đây là một chuỗi trang nối
  tiếp chứ không phải nhiều trang trùng nội dung.
--}}
@if ($paginator->hasPages())
  <nav aria-label="Pagination">
    <ul class="pagination">
      @if ($paginator->onFirstPage())
        <li><span aria-hidden="true">Previous</span></li>
      @else
        <li><a href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a></li>
      @endif

      @foreach ($elements as $element)
        @if (is_string($element))
          <li><span aria-hidden="true">{{ $element }}</span></li>
        @endif

        @if (is_array($element))
          @foreach ($element as $page => $url)
            @if ($page == $paginator->currentPage())
              <li><span class="cur" aria-current="page">{{ $page }}</span></li>
            @else
              <li><a href="{{ $url }}">{{ $page }}</a></li>
            @endif
          @endforeach
        @endif
      @endforeach

      @if ($paginator->hasMorePages())
        <li><a href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a></li>
      @else
        <li><span aria-hidden="true">Next</span></li>
      @endif
    </ul>
  </nav>
@endif
