{{-- Điểm phản hồi trung bình (thang 5 mặt cười) + số phiếu. Cần $count và $avg (thô). --}}
@if ((int) $count === 0)
    <span class="text-muted">—</span>
@else
    @php $a = round((float) $avg, 1); @endphp
    <span class="badge bg-{{ $a >= 4 ? 'success' : ($a >= 3 ? 'secondary' : 'danger') }} tabular">{{ number_format($a, 1) }}/5</span>
    <div class="cell-sub">{{ $count }} phiếu</div>
@endif
