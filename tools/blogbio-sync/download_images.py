#!/usr/bin/env python3
"""Tải ảnh của bản xuất Blogbio về máy, bỏ ảnh trùng.

Dùng: download_images.py <export.json> <thư-mục-đích> [--dry-run]

- covers/<series_id>-<slug>.<ext>  ảnh bìa từng truyện (series.image), tên dễ đọc
- images/<sha1-nội-dung>.<ext>      mọi ảnh khác (ảnh đại diện bài + ảnh chèn trong nội dung),
                                     đặt tên theo băm NỘI DUNG nên ảnh trùng chỉ lưu một lần
- images_manifest.json              url -> file, sha1, kích thước, dùng ở đâu
Chỉ tải từ đúng các URL có trong bản xuất; nhịp chậm, thử lại khi lỗi.
"""
import hashlib
import json
import os
import re
import sys
import time
import urllib.request
from collections import defaultdict
from urllib.parse import urlparse

EXT = {'image/jpeg': 'jpg', 'image/png': 'png', 'image/webp': 'webp', 'image/gif': 'gif', 'image/avif': 'avif'}
UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) StoriesImport/1.0 (owner export)'


def fetch(url, tries=4):
    for i in range(tries):
        try:
            req = urllib.request.Request(url, headers={'User-Agent': UA})
            with urllib.request.urlopen(req, timeout=60) as r:
                return r.read(), r.headers.get('Content-Type', '').split(';')[0].strip()
        except Exception as e:  # noqa: BLE001
            if i == tries - 1:
                raise
            time.sleep(2 * (i + 1))


def ext_for(url, ctype):
    if ctype in EXT:
        return EXT[ctype]
    m = re.search(r'\.(jpe?g|png|webp|gif|avif)(?:$|\?)', urlparse(url).path, re.I)
    return (m.group(1).lower().replace('jpeg', 'jpg')) if m else 'bin'


def main():
    exp = json.load(open(sys.argv[1], encoding='utf-8'))
    dest = sys.argv[2]
    dry = '--dry-run' in sys.argv
    os.makedirs(f'{dest}/covers', exist_ok=True)
    os.makedirs(f'{dest}/images', exist_ok=True)

    uses = defaultdict(list)            # url -> ["series:12:cover", "post:345:image", "post:345:inline"]
    covers = {}                         # series_id -> url
    for s in exp['series']:
        if s.get('image'):
            uses[s['image']].append(f"series:{s['id']}:cover")
            covers[s['id']] = s['image']
    posts = exp['posts'].values() if isinstance(exp['posts'], dict) else exp['posts']
    for p in posts:
        if p.get('image'):
            uses[p['image']].append(f"post:{p['id']}:image")
        for src in re.findall(r'<img[^>]+src="([^"]+)"', p.get('content_html') or ''):
            uses[src].append(f"post:{p['id']}:inline")

    hosts = defaultdict(int)
    for u in uses:
        hosts[urlparse(u).netloc] += 1
    print(f'{len(uses)} URL ảnh khác nhau, {len(covers)} ảnh bìa truyện. Theo host: {dict(hosts)}', file=sys.stderr)
    if dry:
        return

    # Tăng dần: đọc manifest lần trước, URL nào đã có file trên đĩa thì không tải lại.
    mpath = f'{dest}/images_manifest.json'
    manifest = json.load(open(mpath, encoding='utf-8')) if os.path.exists(mpath) else {}
    by_hash = {m['sha1']: m['file'] for m in manifest.values() if 'file' in m and os.path.exists(f"{dest}/{m['file']}")}
    total, skipped = 0, 0
    slug = {s['id']: s.get('slug') or str(s['id']) for s in exp['series']}
    for n, (url, used_by) in enumerate(uses.items(), 1):
        old = manifest.get(url)
        if old and 'file' in old and os.path.exists(f"{dest}/{old['file']}"):
            old['used_by'] = used_by
            skipped += 1
            continue
        try:
            data, ctype = fetch(url)
        except Exception as e:  # noqa: BLE001
            manifest[url] = {'error': str(e), 'used_by': used_by}
            print(f'[{n}/{len(uses)}] LỖI {url}: {e}', file=sys.stderr)
            continue
        h = hashlib.sha1(data).hexdigest()
        ext = ext_for(url, ctype)
        path = f'images/{h[:20]}.{ext}'
        if h not in by_hash:
            with open(f'{dest}/{path}', 'wb') as f:
                f.write(data)
            by_hash[h] = path
            total += len(data)
        manifest[url] = {'file': by_hash[h], 'sha1': h, 'bytes': len(data), 'content_type': ctype, 'used_by': used_by}
        if n % 25 == 0:
            # Ghi manifest dần: script có dừng giữa chừng thì lần sau vẫn biết ảnh nào đã có.
            json.dump(manifest, open(mpath, 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
            print(f'[{n}/{len(uses)}] {len(by_hash)} ảnh khác nhau, {total / 1e6:.1f} MB', file=sys.stderr)
        time.sleep(0.25)
    json.dump(manifest, open(mpath, 'w', encoding='utf-8'), ensure_ascii=False, indent=1)

    # Ảnh bìa: bản sao tên dễ đọc theo truyện
    for sid, url in covers.items():
        m = manifest.get(url, {})
        if 'file' in m:
            ext = m['file'].rsplit('.', 1)[-1]
            # Tên file giới hạn 120 ký tự (macOS chặn ở 255 byte); slug truyện đủ để nhận ra.
            target = f"{dest}/covers/{slug[sid][:120]}.{ext}"
            # Hard link: cùng một file trên đĩa, không tốn thêm dung lượng.
            if os.path.exists(target):
                os.remove(target)
            os.link(f"{dest}/{m['file']}", target)

    json.dump(manifest, open(mpath, 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
    errors = sum(1 for m in manifest.values() if 'error' in m)
    dup_urls = sum(1 for m in manifest.values() if 'file' in m) - len(by_hash)
    print(f'Bỏ qua {skipped} URL đã tải từ lần trước.', file=sys.stderr)
    print(f'XONG: {len(manifest)} URL, {len(by_hash)} ảnh khác nhau (tải mới {total / 1e6:.1f} MB), '
          f'{dup_urls} URL trùng nội dung với ảnh khác, {errors} lỗi, {len(covers)} ảnh bìa trong covers/', file=sys.stderr)


if __name__ == '__main__':
    main()
