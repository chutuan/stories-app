<?php echo '<?xml version="1.0" encoding="UTF-8"?>'."\n"; ?>
{{-- RSS 2.0. media:content mang ảnh bìa của từng truyện: Pinterest bỏ qua mục không có
     ảnh, còn Feedly/Inoreader lấy đúng ảnh này làm hình xem trước. --}}
<rss version="2.0"
     xmlns:atom="http://www.w3.org/2005/Atom"
     xmlns:media="http://search.yahoo.com/mrss/">
  <channel>
    <title>{{ config('app.site_name') }} — new free short fiction</title>
    <link>{{ route('public.home') }}</link>
    <atom:link href="{{ route('public.feed') }}" rel="self" type="application/rss+xml"/>
    <description>Free short serialised fiction you can finish in one sitting. New stories every day.</description>
    <language>en</language>
@if ($stories->isNotEmpty())
    <lastBuildDate>{{ $stories->first()->created_at?->toRssString() }}</lastBuildDate>
@endif
    <image>
      <url>{{ asset('icons/icon-180.png') }}</url>
      <title>{{ config('app.site_name') }} — new free short fiction</title>
      <link>{{ route('public.home') }}</link>
    </image>
@foreach ($stories as $story)
    <item>
      <title>{{ $story->title }}</title>
      <link>{{ route('public.story', $story) }}</link>
      <guid isPermaLink="true">{{ route('public.story', $story) }}</guid>
@if ($story->created_at)
      <pubDate>{{ $story->created_at->toRssString() }}</pubDate>
@endif
      <description>{{ trim(implode("\n\n", array_filter([$story->hook, $story->description]))) }}</description>
@foreach ($story->categories as $category)
      <category>{{ $category->name }}</category>
@endforeach
@if ($story->thumbnail_url)
      <media:content url="{{ $story->thumbnail_url }}" medium="image"/>
@endif
    </item>
@endforeach
  </channel>
</rss>
