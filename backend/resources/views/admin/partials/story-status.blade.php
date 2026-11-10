{{-- Nhãn trạng thái truyện: Hoàn thành = xanh, Đang ra = cam. --}}
<span @class(['badge badge-dot', 'bg-success' => $story->status === 'completed', 'bg-accent' => $story->status !== 'completed'])>{{ $story->status_label }}</span>
