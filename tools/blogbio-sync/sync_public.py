#!/usr/bin/env python3
"""Đồng bộ truyện đã đăng của một site Blogbio về máy, theo kiểu tăng dần.

Đọc từ phía CÔNG KHAI (sitemap + trang bài), không cần đăng nhập:
  python3 tools/blogbio-sync/sync_public.py --site https://storypulse6.cfx.bz \
      --dest imports/storypulse6 [--limit N] [--delay 0.5]

Ghi ra (trong --dest):
  posts/<slug>.json    mỗi bài một file: tiêu đề, nội dung HTML, ảnh, ngày đăng/sửa,
                       truyện (series) và danh sách chương theo thứ tự
  series.json          gom bài theo truyện, đúng thứ tự chương, ảnh bìa = ảnh chương 1
  export.json          gộp series + bài, đúng dạng dedupe_report.py / download_images.py đọc
  sync_state.json      NHỚ LẦN ĐỒNG BỘ CUỐI: slug -> lastmod của sitemap. Lần sau chỉ tải
                       bài mới hoặc có lastmod khác; bài biến khỏi sitemap thì đánh dấu
                       removed_at (không xoá file đã có).

Chỉ có bài đã đăng (public). Bản nháp/bài chưa đăng không nằm trong sitemap.
"""
import argparse
import html as htmllib
import json
import os
import re
import sys
import time
import urllib.request
from datetime import datetime, timezone

UA = 'StoriesSync/1.0 (owner sync; +https://tunastory.com)'


def now():
    return datetime.now(timezone.utc).isoformat(timespec='seconds')


def fetch(url, tries=4):
    for i in range(tries):
        try:
            req = urllib.request.Request(url, headers={'User-Agent': UA})
            with urllib.request.urlopen(req, timeout=60) as r:
                return r.read().decode('utf-8', 'replace')
        except Exception:  # noqa: BLE001
            if i == tries - 1:
                raise
            time.sleep(3 * (i + 1))


def sitemap_posts(site):
    """[(url, lastmod)] của mọi bài trong sitemap (hỗ trợ sitemap index lồng nhau)."""
    out, todo, seen = [], [f'{site}/sitemap.xml'], set()
    while todo:
        sm = todo.pop()
        if sm in seen:
            continue
        seen.add(sm)
        xml = fetch(sm)
        if '<sitemapindex' in xml:
            for loc in re.findall(r'<loc>\s*([^<\s]+)\s*</loc>', xml):
                if 'post' in loc:          # chỉ sitemap bài, bỏ pages/categories/tags
                    todo.append(htmllib.unescape(loc))
        else:
            for block in re.findall(r'<url>(.*?)</url>', xml, re.S):
                loc = re.search(r'<loc>\s*([^<\s]+)\s*</loc>', block)
                mod = re.search(r'<lastmod>\s*([^<\s]+)\s*</lastmod>', block)
                if loc:
                    out.append((htmllib.unescape(loc.group(1)), mod.group(1) if mod else None))
    return out


def inner_div(page, cls):
    """Nội dung HTML bên trong <div class="... cls ..."> đầu tiên, khớp đúng thẻ đóng."""
    m = re.search(r'<div\b[^>]*class="[^"]*\b' + re.escape(cls) + r'\b[^"]*"[^>]*>', page)
    if not m:
        return None
    depth, pos = 1, m.end()
    for t in re.finditer(r'<(/?)div\b[^>]*>', page[pos:]):
        depth += -1 if t.group(1) else 1
        if depth == 0:
            return page[pos:pos + t.start()].strip()
    return None


def meta(page, prop):
    """Giá trị content của thẻ meta, bất kể content đứng trước hay sau property/name."""
    for tag in re.findall(r'<meta\b[^>]*>', page):
        if re.search(r'(?:property|name)="' + re.escape(prop) + r'"', tag):
            m = re.search(r'content="([^"]*)"', tag)
            if m:
                return htmllib.unescape(m.group(1))
    return None


def clean_content(h):
    """Bỏ script/style/noscript và các khối quảng cáo nhúng của theme khỏi nội dung bài."""
    if not h:
        return h
    h = re.sub(r'<(script|style|noscript|template)\b[^>]*>.*?</\1>', '', h, flags=re.S | re.I)
    h = re.sub(r'<ins\b[^>]*adsbygoogle[^>]*>.*?</ins>', '', h, flags=re.S | re.I)
    return h.strip()


def parse_post(url, page):
    slug = url.rstrip('/').rsplit('/', 1)[-1]
    nav = re.search(r'<nav class="series-nav".*?</nav>', page, re.S)
    series_title, toc = None, []
    if nav:
        n = nav.group(0)
        t = re.search(r'series-nav__dropdown-title">(.*?)</div>', n, re.S)
        series_title = htmllib.unescape(re.sub(r'\s+', ' ', t.group(1))).strip() if t else None
        for a in re.finditer(r'<a href="([^"]+)">\s*<span class="series-nav__chapter-num">(\d+)</span>(.*?)</a>', n, re.S):
            toc.append({'n': int(a.group(2)), 'slug': a.group(1).rstrip('/').rsplit('/', 1)[-1],
                        'title': htmllib.unescape(re.sub(r'\s+', ' ', a.group(3))).strip()})
    title = meta(page, 'og:title')
    if not title:
        h = re.search(r'<h1[^>]*>(.*?)</h1>', page, re.S)
        title = htmllib.unescape(re.sub(r'<[^>]+>|\s+', ' ', h.group(1))).strip() if h else slug
    return {
        'slug': slug, 'url': url, 'title': title,
        'image': meta(page, 'og:image'),
        'published_at': meta(page, 'og:article:published_time') or meta(page, 'article:published_time'),
        'modified_at': meta(page, 'og:article:modified_time') or meta(page, 'article:modified_time'),
        'category': (re.findall(r'"name":\s*"([^"]+)",\s*"item":\s*"[^"]*/category/', page) or [None])[0],
        'series_title': series_title,
        'series_toc': toc,
        'content_html': clean_content(inner_div(page, 'module-article-content__body')),
    }


def build_outputs(dest):
    posts = {}
    for f in os.listdir(f'{dest}/posts'):
        if f.endswith('.json'):
            p = json.load(open(f'{dest}/posts/{f}', encoding='utf-8'))
            posts[p['slug']] = p
    series = {}
    for p in posts.values():
        toc = p.get('series_toc') or [{'n': 1, 'slug': p['slug'], 'title': p['title']}]
        key = toc[0]['slug']                      # truyện = slug chương đầu
        s = series.setdefault(key, {'id': key, 'title': p.get('series_title') or p['title'], 'toc': toc})
        if len(toc) > len(s['toc']):              # bài mới nhất thường có mục lục đầy đủ nhất
            s['toc'] = toc
    for s in series.values():
        slugs = [c['slug'] for c in sorted(s['toc'], key=lambda c: c['n'])]
        s['chapters'] = slugs
        s['missing_locally'] = [x for x in slugs if x not in posts]
        first = posts.get(slugs[0]) if slugs else None
        s['image'] = first.get('image') if first else None
        s['slug'] = s['id']
        for i, x in enumerate(slugs):
            if x in posts:
                posts[x]['series_id'] = s['id']
                posts[x]['chapter_number'] = i + 1
                posts[x]['prev_chapter'] = slugs[i - 1] if i else None
                posts[x]['next_chapter'] = slugs[i + 1] if i + 1 < len(slugs) else None
    json.dump(sorted(series.values(), key=lambda s: s['title'].lower()),
              open(f'{dest}/series.json', 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
    export = {'series': list(series.values()),
              'posts': {k: {**v, 'id': k, 'status': 'published', 'created_at': v.get('published_at')}
                        for k, v in posts.items()}}
    json.dump(export, open(f'{dest}/export.json', 'w', encoding='utf-8'), ensure_ascii=False)
    return len(series), len(posts)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--site', required=True)
    ap.add_argument('--dest', required=True)
    ap.add_argument('--limit', type=int, default=0, help='chỉ tải tối đa N bài (để thử)')
    ap.add_argument('--delay', type=float, default=0.5, help='nghỉ giữa các request (giây)')
    a = ap.parse_args()
    site, dest = a.site.rstrip('/'), a.dest
    os.makedirs(f'{dest}/posts', exist_ok=True)
    state_path = f'{dest}/sync_state.json'
    state = json.load(open(state_path, encoding='utf-8')) if os.path.exists(state_path) else {'site': site, 'posts': {}}
    state['last_sync_started_at'] = now()

    entries = sitemap_posts(site)
    in_sitemap = set()
    todo = []
    for url, lastmod in entries:
        slug = url.rstrip('/').rsplit('/', 1)[-1]
        in_sitemap.add(slug)
        known = state['posts'].get(slug)
        if not known or known.get('lastmod') != lastmod or not os.path.exists(f'{dest}/posts/{slug}.json'):
            todo.append((url, slug, lastmod, 'new' if not known else 'updated'))
    removed = [s for s, v in state['posts'].items() if s not in in_sitemap and not v.get('removed_at')]
    for s in removed:
        state['posts'][s]['removed_at'] = now()
    if a.limit:
        todo = todo[:a.limit]
    print(f'sitemap: {len(entries)} bài | cần tải: {len(todo)} '
          f'(mới {sum(1 for t in todo if t[3] == "new")}, sửa {sum(1 for t in todo if t[3] == "updated")}) '
          f'| biến mất: {len(removed)}', flush=True)

    errors = []
    for i, (url, slug, lastmod, kind) in enumerate(todo, 1):
        try:
            p = parse_post(url, fetch(url))
            if not p['content_html']:
                raise ValueError('không tìm thấy nội dung bài')
            with open(f'{dest}/posts/{slug}.json', 'w', encoding='utf-8') as f:
                json.dump(p, f, ensure_ascii=False, indent=1)
            state['posts'][slug] = {'lastmod': lastmod, 'fetched_at': now(), 'removed_at': None}
        except Exception as e:  # noqa: BLE001
            errors.append({'slug': slug, 'error': str(e)})
        if i % 25 == 0 or i == len(todo):
            json.dump(state, open(state_path, 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
            print(f'[{i}/{len(todo)}] lỗi: {len(errors)}', flush=True)
        time.sleep(a.delay)

    n_series, n_posts = build_outputs(dest)
    if not a.limit and not errors:
        state['last_sync_finished_at'] = now()
    json.dump(state, open(state_path, 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
    if errors:
        json.dump(errors, open(f'{dest}/sync_errors.json', 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
    print(f'XONG: {n_posts} bài / {n_series} truyện trên máy | lỗi lần này: {len(errors)}', flush=True)


if __name__ == '__main__':
    main()
