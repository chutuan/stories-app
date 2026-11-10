#!/usr/bin/env python3
"""Chuyển file xuất từ API admin Blogbio thành đúng bộ file mà sync_public.py tạo ra.

Dùng khi phía công khai bị chặn (từ 31/10/2026 cả Blogbio bật Cloudflare challenge với mọi
client không phải trình duyệt, nên sync_public.py nhận 403). File xuất được tạo trong Chrome
của chủ site (đang đăng nhập admin) rồi tải về — xem README, mục "Khi phía công khai bị chặn".

  python3 tools/blogbio-sync/from_admin_export.py ~/Downloads/<site>-published-<ngày>.json \
      --dest imports/<site>

File xuất: {"site", "exported_at", "posts": [chi tiết từng bài đã đăng từ /admin/api/v1/posts/{id}]}.
Ghi ra (trong --dest) giống sync_public.py: posts/<slug>.json, series.json, export.json,
sync_state.json (slug -> updated_at của admin, để lần sau báo được bài nào mới/sửa/biến mất).
"""
import argparse
import json
import os
import re
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from sync_public import build_outputs, clean_content, now  # noqa: E402

PART = re.compile(r'\b(?:part|chapter)\s*(\d+)\b', re.I)


def part_no(title):
    m = PART.search(title or '')
    return int(m.group(1)) if m else 1


def story_key(title):
    """Bài không thuộc series: gom chương theo tên truyện (bỏ PART N / đuôi đánh dấu)."""
    t = PART.sub(' ', title.lower())
    t = re.sub(r'\b(bbb|xxx|00\d)\b', ' ', t)
    return re.sub(r'\s+', ' ', re.sub(r'[^a-z0-9 ]', '', t)).strip()[:60]


def order_chapters(posts):
    """Thứ tự chương: theo chuỗi prev/next của admin nếu liền mạch, không thì theo số PART."""
    by_slug = {p['slug']: p for p in posts}
    heads = [p for p in posts if not p.get('prev_chapter') or p['prev_chapter'] not in by_slug]
    if len(heads) == 1:
        chain, cur, seen = [], heads[0], set()
        while cur and cur['slug'] not in seen:
            seen.add(cur['slug'])
            chain.append(cur)
            cur = by_slug.get(cur.get('next_chapter'))
        if len(chain) == len(posts):
            return chain
    return sorted(posts, key=lambda p: (part_no(p['title']), p['published_at'] or '', p['id']))


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('export')
    ap.add_argument('--dest', required=True)
    a = ap.parse_args()
    data = json.load(open(a.export, encoding='utf-8'))
    posts = [p for p in data['posts'] if p.get('status') == 'published' and p.get('description')]
    dest = a.dest
    os.makedirs(f'{dest}/posts', exist_ok=True)
    state_path = f'{dest}/sync_state.json'
    state = json.load(open(state_path, encoding='utf-8')) if os.path.exists(state_path) else {'posts': {}}
    state['site'] = data.get('site')
    state['source'] = 'admin-export'
    state['last_sync_started_at'] = now()

    groups = {}
    for p in posts:
        key = f"series-{p['series_id']}" if p.get('series_id') else 'title-' + story_key(p['title'])
        groups.setdefault(key, []).append(p)

    new = updated = 0
    current = set()
    for key, items in groups.items():
        chapters = order_chapters(items)
        series_title = chapters[0]['series']['title'] if chapters[0].get('series') else chapters[0]['title']
        toc = [{'n': i + 1, 'slug': c['slug'], 'title': c['title']} for i, c in enumerate(chapters)]
        for c in chapters:
            out = {
                # Bản xuất cũ tự thêm `url`; chi tiết bài lấy thẳng từ API admin thì chỉ có public_url.
                'slug': c['slug'], 'url': c.get('url') or c.get('public_url') or c.get('permalink'),
                'title': c['title'], 'image': c['image'],
                'published_at': c['published_at'], 'modified_at': c['updated_at'],
                'category': (c.get('categories') or [None])[0],
                'series_title': series_title, 'series_toc': toc,
                'content_html': clean_content(c['description']),
                'source_post_id': c['id'], 'source_series_id': c.get('series_id'),
            }
            with open(f"{dest}/posts/{c['slug']}.json", 'w', encoding='utf-8') as f:
                json.dump(out, f, ensure_ascii=False, indent=1)
            known = state['posts'].get(c['slug'])
            if not known:
                new += 1
            elif known.get('lastmod') != c['updated_at']:
                updated += 1
            state['posts'][c['slug']] = {'lastmod': c['updated_at'], 'fetched_at': now(), 'removed_at': None}
            current.add(c['slug'])

    removed = [s for s, v in state['posts'].items() if s not in current and not v.get('removed_at')]
    for s in removed:
        state['posts'][s]['removed_at'] = now()

    n_series, n_posts = build_outputs(dest)
    state['last_sync_finished_at'] = now()
    json.dump(state, open(state_path, 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
    print(f'file xuất: {len(posts)} bài đã đăng | mới {new}, sửa {updated}, biến mất {len(removed)}')
    print(f'XONG: {n_posts} bài / {n_series} truyện trên máy')


if __name__ == '__main__':
    main()
