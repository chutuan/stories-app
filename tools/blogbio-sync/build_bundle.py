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
    t = re.sub(r'^(?:chapter|part)\s*1\b\s*[:.\-–—]?\s*', '', t.strip(), flags=re.I)   # "Chapter 1 - ..."
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
    return drop_site_junk(paras)


def is_caps(p):
    return len(p) >= 25 and re.search(r'[A-Z]', p) and not re.search(r'[a-z]', p)


def drop_site_junk(paras):
    """Bỏ rác của theme lọt vào thân bài (mundodehistorias, 31/10/2026):
    - dòng "Advertisement" / "Promoted Content" đứng riêng;
    - khối "May you like" + tên các bài liên quan (VIẾT HOA) ngay sau nó;
    - câu trích nổi bật: đoạn VIẾT HOA lặp lại nguyên một câu đã có trong chương.
    Đoạn VIẾT HOA không lặp (tít báo, tin nhắn, biển hiệu trong truyện) thì giữ."""
    out, skip_caps = [], 0
    for p in paras:
        if re.fullmatch(r'(?i)advertisement|promoted content', p):
            continue
        if re.fullmatch(r'(?i)may you like\W*', p):
            skip_caps = 3
            continue
        if skip_caps and is_caps(p):
            skip_caps -= 1
            continue
        skip_caps = 0
        out.append(p)
    norm = lambda s: re.sub(r'\s+', ' ', re.sub(r'[^a-z0-9\s]', '', s.lower())).strip()
    result = []
    for i, p in enumerate(out):
        if is_caps(p):
            key = norm(p)
            rest = norm(' '.join(q for j, q in enumerate(out) if j != i and not is_caps(q)))
            if key and key in rest:
                continue
        result.append(p)
    return result


def clean_para(t):
    """Dọn dấu vết của AI/markdown còn sót trong bài Blogbio."""
    if re.fullmatch(r'`{3,}\w*(?:`{3,}\w*)*', t):           # ``` hoặc ``````text đứng riêng
        return ''
    if re.fullmatch(r'[—–\-*_=•·]{1,5}', t):               # dòng phân cách cảnh
        return '* * *'
    t = re.sub(r'^#{1,6}\s*', '', t)                       # "# PART 3 — ..." -> "PART 3 — ..."
    t = re.sub(r'\*\*(.+?)\*\*', r'\1', t)                 # **đậm** -> đậm
    t = re.sub(r'^\*\*|\*\*$', '', t)                      # ** mở ở đoạn này, đóng ở đoạn khác
    t = re.sub(r'\s*`{3,}\w*\s*$', '', t)                  # ``` dính cuối đoạn
    return t.strip()


NUM = (r'\d+|one|two|three|four|five|six|seven|eight|nine|ten|eleven|twelve|thirteen|fourteen|fifteen'
       r'|sixteen|seventeen|eighteen|nineteen|twenty|[ivx]{1,5}')
# "PART 3: …", "Chapter 2 — …", "Part One: …", "Part 2:" — có khi kèm một câu dài làm tiêu đề.
HEADING = re.compile(r'^(?:part|chapter)\s+(' + NUM + r')\s*(?:[:.\-–—]\s*(.*))?$', re.I | re.S)
HEADING_MAX = 220


def split_headings(paras):
    """Một bài có thể chứa nhiều chương: truyện dồn cả vào một bài với các dòng "Chapter 2: …",
    hay "PART 6: …" lọt vào cuối bài PART 5. Cắt tại mỗi dòng tiêu đề như vậy (kể cả khi nó
    dính đầu một đoạn qua <br>). Phần trước tiêu đề đầu tiên mà quá ngắn (thường chỉ là tên
    truyện viết hoa lặp lại) thì bỏ."""
    flat = []
    for p in paras:
        first, _, rest = p.partition('\n')
        if rest and HEADING.match(first.strip()):
            flat += [first.strip(), rest.strip()]
        else:
            flat.append(p)
    parts, cur = [], []
    for p in flat:
        if len(p) <= HEADING_MAX and HEADING.match(p) and (cur or parts):
            parts.append(cur)
            cur = [p]
        else:
            cur.append(p)
    parts.append(cur)
    if len(parts) > 1 and len('\n'.join(parts[0])) < 300 and not HEADING.match(parts[0][0]):
        parts = parts[1:]
    return [x for x in parts if x]


def chapter_from(paras, n):
    head = paras[0] if paras else ''
    m = HEADING.match(head) if len(head) <= HEADING_MAX else None
    if m and (m.group(2) or '').strip():
        name = m.group(2).strip()
        title = title_case(name) if name.isupper() else name
        return title[:250], paras[1:]
    if m:
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
        if title.isupper():                                   # "THE KISS THAT EVERYONE SAW"
            title = title_case(title)
        slug = base = slugify(title)
        k = 2
        while slug in used:
            slug = f'{base[:115]}-{k}'
            k += 1
        used.add(slug)
        chapters = []
        for ps in s['chapters']:
            parts = split_headings(to_text(posts[ps]['content_html']))
            for i, part in enumerate(parts):
                n = len(chapters) + 1
                ch_title, paras = chapter_from(part, n)
                if n == 1 and len(parts) > 1 and paras is part:   # đoạn mở đầu trước "Chapter 1:"
                    ch_title = 'Prologue'
                paras = list(paras)
                while paras and paras[-1] == '* * *':             # ngắt cảnh thừa ở mép chương
                    paras.pop()
                while paras and paras[0] == '* * *':
                    paras.pop(0)
                content = '\n\n'.join(paras)
                if len(content) < 500:
                    problems.append(f'{slug} chương {n}: chỉ {len(content)} ký tự')
                chapters.append({'number': n, 'title': ch_title, 'content': content})
        story = {'title': title[:255], 'slug': slug, 'author': 'Tuan', 'status': 'completed'}
        if sid in meta:
            story['categories'] = meta[sid]['categories']
            story['description'] = meta[sid]['description']
        # source_key: để `stories:queue-add` xếp hàng lại cùng file mà không nhân đôi truyện.
        bundles.append({'source_key': sid, 'story': story, 'chapters': chapters,
                        'generate': {'cover': False, 'audio': False}})
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
