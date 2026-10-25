#!/usr/bin/env python3
"""Chuyển truyện đã đồng bộ từ Blogbio thành gói JSON cho `php artisan stories:import`.

Dùng: build_bundle.py <thư-mục-nguồn> [--meta meta.json] [--out bundle.json]

- Chỉ lấy truyện có quyết định GIỮ trong review.csv (bỏ BỎ và CẦN QUYẾT).
- Nội dung: HTML Blogbio -> chữ thuần, mỗi <p> một đoạn, cách nhau một dòng trống (đúng định
  dạng chương của Stories). Bỏ ảnh chèn đầu bài, quảng cáo chèn giữa bài (in-article-ad) và
  khung "Report" của theme ở cuối.
- Tiêu đề chương: dòng đầu "PART N: TÊN PHẦN" -> "Tên Phần" (bỏ khỏi nội dung); không có
  dòng đó thì "Part N".
- Tên truyện: bỏ đuôi đánh dấu bbb/xxx/aaa. Slug: theo tên đã làm sạch, tối đa 120 ký tự,
  không trùng truyện đang có trên Stories (stories_existing.json) và không trùng nhau.
- status luôn "completed": routine nạp truyện chỉ viết tiếp truyện "ongoing", để "ongoing" là
  nó sẽ tự viết thêm chương vào truyện của Blogbio.
- generate: không vẽ bìa (đã có bìa từ Blogbio), không đọc audio (tốn phí, làm riêng nếu cần).
- --meta: file [{source_id, categories, description}] (thể loại + tóm tắt) gộp vào story.
- Ghi kèm import_map.json: source_id (id series Blogbio) -> slug trên Stories, ảnh bìa.
"""
import csv
import html
import json
import re
import sys
import unicodedata

SMALL = {'a', 'an', 'the', 'and', 'but', 'or', 'nor', 'for', 'so', 'yet', 'of', 'to', 'in', 'on', 'at',
         'by', 'up', 'as', 'is', 'if', 'it', 'vs', 'via', 'with', 'from', 'into', 'than', 'that'}


def clean_title(t):
    t = re.sub(r'\s*[-–—]?\s*\b(?:([a-zA-Z])\1{2,})\b\s*$', '', t.strip())
    t = re.sub(r'\s*\.\.+\s*$', '…', t)
    return t.strip()


def slugify(t, limit=120):
    t = unicodedata.normalize('NFKD', t).encode('ascii', 'ignore').decode()
    t = re.sub(r'[^a-z0-9]+', '-', t.lower()).strip('-')
    if len(t) > limit:
        t = t[:limit].rsplit('-', 1)[0]
    return t


def title_case(s):
    words = s.lower().split()
    out = []
    for i, w in enumerate(words):
        core = w.strip('"“”‘’\'.,!?:;()')
        if i not in (0, len(words) - 1) and core in SMALL:
            out.append(w)
        else:
            out.append(re.sub(r"[a-z]", lambda m: m.group(0).upper(), w, count=1))
    return ' '.join(out)


def to_text(content_html):
    # Cắt khung Report của theme (luôn nằm sau nội dung bài).
    cut = len(content_html)
    for marker in ('class="report', '<section'):
        i = content_html.find(marker)
        if i != -1:
            cut = min(cut, content_html.rfind('<', 0, i) if marker.startswith('class') else i)
    body = content_html[:cut]
    # Bỏ quảng cáo chèn giữa bài và mọi khối không phải đoạn văn.
    body = re.sub(r'<div[^>]*class="[^"]*in-article-ad[^"]*"[^>]*>.*?</div>\s*</div>', '', body, flags=re.S)
    paras = []
    for p in re.findall(r'<p\b[^>]*>(.*?)</p>', body, flags=re.S):
        if '<img' in p and not re.sub(r'<[^>]+>', '', p).strip():
            continue                                   # đoạn chỉ có ảnh
        t = re.sub(r'<br\s*/?>', '\n', p)
        t = html.unescape(re.sub(r'<[^>]+>', '', t))
        t = re.sub(r'[ \t ]+', ' ', t).strip()
        t = clean_para(t)
        if t:
            paras.append(t)
    return paras


def clean_para(t):
    """Dọn dấu vết của AI/markdown còn sót trong bài Blogbio."""
    if re.fullmatch(r'`{3,}\w*(?:`{3,}\w*)*', t):           # ``` hoặc ``````text đứng riêng
        return ''
    if re.fullmatch(r'[—–\-*_=•·]{1,5}', t):               # dòng phân cách cảnh
        return '* * *'
    t = re.sub(r'^#{1,6}\s*', '', t)                       # "# PART 3 — ..." -> "PART 3 — ..."
    t = re.sub(r'\*\*(.+?)\*\*', r'\1', t)                 # **đậm** -> đậm
    t = re.sub(r'\s*`{3,}\w*\s*$', '', t)                  # ``` dính cuối đoạn
    return t.strip()


def chapter_from(paras, n):
    head = paras[0] if paras else ''
    m = re.match(r'^(?:part|chapter)\s*(\d+)\s*[:.\-–—]\s*(.+)$', head, re.I)
    if m:
        name = m.group(2).strip()
        title = title_case(name) if name.isupper() else name
        return title[:250], paras[1:]
    if re.match(r'^(?:part|chapter)\s*\d+\s*$', head, re.I):
        return f'Part {n}', paras[1:]
    return f'Part {n}', paras


def main():
    src = sys.argv[1].rstrip('/')
    meta_path = sys.argv[sys.argv.index('--meta') + 1] if '--meta' in sys.argv else None
    out = sys.argv[sys.argv.index('--out') + 1] if '--out' in sys.argv else f'{src}/bundle.json'

    exp = json.load(open(f'{src}/export.json', encoding='utf-8'))
    posts = exp['posts']
    series = {s['id']: s for s in exp['series']}
    keep = {r['id_goc'] for r in csv.DictReader(open(f'{src}/review.csv', encoding='utf-8-sig')) if r['quyet_dinh'] == 'GIỮ'}
    existing = {s['slug'] for s in json.load(open(f'{src}/stories_existing.json', encoding='utf-8'))}
    manifest = json.load(open(f'{src}/images_manifest.json', encoding='utf-8'))
    cover_by_series = {}
    for url, m in manifest.items():
        for use in m.get('used_by', []):
            if use.endswith(':cover') and 'file' in m:
                cover_by_series[use.split(':', 1)[1].rsplit(':', 1)[0]] = m['file']
    meta = {m['source_id']: m for m in json.load(open(meta_path, encoding='utf-8'))} if meta_path else {}

    bundles, imap, used, problems = [], {}, set(existing), []
    for sid in sorted(keep, key=lambda x: posts[series[x]['chapters'][0]].get('published_at') or ''):
        s = series[sid]
        title = clean_title(s['title'])
        slug = base = slugify(title)
        k = 2
        while slug in used:
            slug = f'{base[:115]}-{k}'
            k += 1
        used.add(slug)
        chapters = []
        for n, ps in enumerate(s['chapters'], 1):
            ch_title, paras = chapter_from(to_text(posts[ps]['content_html']), n)
            content = '\n\n'.join(paras)
            if len(content) < 500:
                problems.append(f'{slug} chương {n}: chỉ {len(content)} ký tự')
            chapters.append({'number': n, 'title': ch_title, 'content': content})
        story = {'title': title[:255], 'slug': slug, 'author': 'Tuan', 'status': 'completed'}
        if sid in meta:
            story['categories'] = meta[sid]['categories']
            story['description'] = meta[sid]['description']
        bundles.append({'story': story, 'chapters': chapters, 'generate': {'cover': False, 'audio': False}})
        imap[sid] = {'slug': slug, 'title': title, 'cover_file': cover_by_series.get(sid),
                     'chapters': len(chapters), 'blogbio_first_chapter': posts[s['chapters'][0]]['url']}

    json.dump(bundles, open(out, 'w', encoding='utf-8'), ensure_ascii=False)
    json.dump(imap, open(f'{src}/import_map.json', 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
    print(f'{len(bundles)} truyện, {sum(len(b["chapters"]) for b in bundles)} chương -> {out}'
          f' | có thể loại+tóm tắt: {sum(1 for b in bundles if "categories" in b["story"])}'
          f' | cảnh báo: {len(problems)}', file=sys.stderr)
    for p in problems[:20]:
        print('  ! ' + p, file=sys.stderr)


if __name__ == '__main__':
    main()
