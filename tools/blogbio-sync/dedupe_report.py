#!/usr/bin/env python3
"""Kiểm trùng bản xuất Blogbio trước khi nạp vào Stories.

Dùng: dedupe_report.py <export.json> [stories_existing.json] > report.json

Lớp kiểm:
  1. Trùng truyện: tên đã chuẩn hoá (bỏ đuôi bbb/xxx, PART n, Chapter n, dấu câu),
     và nội dung chương mở đầu (vân tay + độ giống theo cụm 8 từ).
  2. Trong mỗi truyện: thứ tự theo chuỗi prev/next, chương trùng, đứt chuỗi,
     chương lạc truyện.
  3. Cùng một đoạn văn ở hai truyện khác nhau (độ giống theo cụm từ).
  4. Trùng với truyện đã có trên Stories (nếu đưa file thứ hai).
"""
import hashlib
import html
import json
import re
import sys
from collections import defaultdict

SHINGLE = 8          # số từ mỗi cụm
MAX_WORDS = 4000     # chỉ so phần đầu mỗi chương, đủ để bắt trùng
NEAR_DUP = 0.80      # Jaccard >= ngưỡng này = gần như trùng
SIMILAR = 0.40       # >= ngưỡng này = đáng xem lại

MARKER = re.compile(r'[\s\-–—:|.]*\b([a-z])\1{2,}\b\s*$', re.I)   # đuôi kiểu "bbb", "xxx"
PART = re.compile(r'^\s*(part|chapter|chap|ch\.?|phần|chương)\s*\d+\s*[-–—:.]*\s*', re.I)


def norm_title(t: str) -> str:
    t = (t or '').strip()
    t = PART.sub('', t)
    for _ in range(2):
        t = MARKER.sub('', t)
    t = t.lower()
    t = re.sub(r'\.{2,}|…', ' ', t)
    t = re.sub(r'[^a-z0-9\s]', ' ', t)
    return re.sub(r'\s+', ' ', t).strip()


def text_of(html_s: str) -> str:
    s = re.sub(r'<(script|style)[^>]*>.*?</\1>', ' ', html_s or '', flags=re.S | re.I)
    s = re.sub(r'<[^>]+>', ' ', s)
    s = html.unescape(s)
    return re.sub(r'\s+', ' ', s).strip()


def words_of(text: str):
    return re.findall(r"[a-z0-9']+", text.lower())[:MAX_WORDS]


def shingles(words):
    return {hash(' '.join(words[i:i + SHINGLE])) for i in range(max(0, len(words) - SHINGLE + 1))}


def fingerprint(words) -> str:
    return hashlib.sha1(' '.join(words).encode()).hexdigest()[:16]


def jaccard(a, b):
    if not a or not b:
        return 0.0
    return len(a & b) / len(a | b)


def order_chain(posts):
    """Xếp chương theo prev/next (slug). Trả về (thứ tự, các vấn đề)."""
    by_slug = {p['slug']: p for p in posts}
    issues = []
    heads = [p for p in posts if not p.get('prev_chapter') or p['prev_chapter'] not in by_slug]
    if len(heads) != 1:
        issues.append(f'{len(heads)} chương không có chương trước trong cùng truyện (chuỗi đứt hoặc có nhiều đầu)')
    ordered, seen = [], set()
    for h in sorted(heads, key=lambda p: p['created_at'] or ''):
        cur = h
        while cur and cur['slug'] not in seen:
            seen.add(cur['slug'])
            ordered.append(cur)
            nxt = cur.get('next_chapter')
            cur = by_slug.get(nxt) if nxt else None
            if nxt and nxt not in by_slug:
                issues.append(f'chương "{ordered[-1]["title"]}" trỏ tới chương sau không có trong truyện: {nxt}')
    leftover = [p for p in posts if p['slug'] not in seen]
    if leftover:
        issues.append(f'{len(leftover)} chương nằm ngoài chuỗi')
        ordered += sorted(leftover, key=lambda p: p['created_at'] or '')
    return ordered, issues


def main():
    exp = json.load(open(sys.argv[1], encoding='utf-8'))
    existing = json.load(open(sys.argv[2], encoding='utf-8')) if len(sys.argv) > 2 else []
    series = {s['id']: s for s in exp['series']}
    posts = list(exp['posts'].values()) if isinstance(exp['posts'], dict) else exp['posts']

    for p in posts:
        p['_text'] = text_of(p.get('content_html'))
        w = words_of(p['_text'])
        p['_words'] = len(re.findall(r'\S+', p['_text']))
        p['_fp'] = fingerprint(w)
        p['_sh'] = shingles(w)

    by_series = defaultdict(list)
    orphans = []
    for p in posts:
        (by_series[p['series_id']] if p.get('series_id') else orphans).append(p)

    report = {'totals': {'series': len(series), 'posts': len(posts), 'posts_without_series': len(orphans)},
              'series': [], 'cross_series_overlap': [], 'title_groups': [], 'vs_stories': []}

    # --- Lớp 2: trong mỗi truyện ---
    first_chapter = {}
    for sid, ps in by_series.items():
        ordered, issues = order_chain(ps)
        fps = defaultdict(list)
        for p in ordered:
            fps[p['_fp']].append(p['title'])
        dup_exact = [t for t in fps.values() if len(t) > 1]
        near = []
        for i in range(len(ordered)):
            for j in range(i + 1, len(ordered)):
                s = jaccard(ordered[i]['_sh'], ordered[j]['_sh'])
                if s >= NEAR_DUP and ordered[i]['_fp'] != ordered[j]['_fp']:
                    near.append([ordered[i]['title'], ordered[j]['title'], round(s, 2)])
        empty = [p['title'] for p in ordered if p['_words'] < 200]
        s = series.get(sid, {'title': ordered[0].get('series_title'), 'slug': None})
        first_chapter[sid] = ordered[0]
        report['series'].append({
            'series_id': sid, 'title': s.get('title'), 'norm_title': norm_title(s.get('title')),
            'chapters': len(ordered), 'words': sum(p['_words'] for p in ordered),
            'order': [p['title'] for p in ordered],
            'chain_issues': issues, 'duplicate_chapters_exact': dup_exact,
            'duplicate_chapters_near': near, 'short_chapters_under_200_words': empty,
            'unpublished': [p['title'] for p in ordered if p.get('status') != 'published'],
        })

    # --- Lớp 1: tên trùng sau chuẩn hoá ---
    groups = defaultdict(list)
    for r in report['series']:
        groups[r['norm_title']].append(r['series_id'])
    report['title_groups'] = [{'norm_title': k, 'series_ids': v} for k, v in groups.items() if len(v) > 1]

    # --- Lớp 1+3: nội dung giống nhau giữa các truyện (chỉ mục ngược theo cụm từ) ---
    index = defaultdict(set)
    for p in posts:
        # 600 giá trị băm NHỎ NHẤT (bottom-k), không phải 600 phần tử đầu của set: thứ tự của set
        # là tuỳ ý, hai bài giống hệt nhau có thể bị lấy hai tập khác nhau và lọt lưới.
        for h in sorted(p['_sh'])[:600]:
            index[h].add(p['id'])
    pid = {p['id']: p for p in posts}
    pairs = defaultdict(int)
    for ids in index.values():
        if 1 < len(ids) <= 50:
            ids = sorted(ids)
            for a in range(len(ids)):
                for b in range(a + 1, len(ids)):
                    pairs[(ids[a], ids[b])] += 1
    seen_pairs = set()
    for (a, b), shared in pairs.items():
        pa, pb = pid[a], pid[b]
        if pa.get('series_id') == pb.get('series_id') or shared < 20:
            continue
        s = jaccard(pa['_sh'], pb['_sh'])
        if s >= SIMILAR:
            key = tuple(sorted([pa.get('series_id') or 0, pb.get('series_id') or 0]))
            report['cross_series_overlap'].append({
                'similarity': round(s, 2), 'a': {'series_id': pa.get('series_id'), 'title': pa['title']},
                'b': {'series_id': pb.get('series_id'), 'title': pb['title']}, 'series_pair': key})
            seen_pairs.add(key)
    report['cross_series_overlap'].sort(key=lambda x: -x['similarity'])
    report['totals']['series_pairs_with_overlap'] = len(seen_pairs)

    # --- Lớp 4: so với truyện đã có trên Stories ---
    for st in existing:
        n = norm_title(st['title'])
        sh = shingles(words_of(st.get('first_chapter_text', '')))
        for r in report['series']:
            fc = first_chapter[r['series_id']]
            sim = jaccard(sh, fc['_sh'])
            if r['norm_title'] == n or sim >= SIMILAR:
                report['vs_stories'].append({'stories_id': st['id'], 'stories_title': st['title'],
                                             'series_id': r['series_id'], 'series_title': r['title'],
                                             'same_title': r['norm_title'] == n, 'similarity': round(sim, 2)})

    report['totals']['series_with_chain_issues'] = sum(1 for r in report['series'] if r['chain_issues'])
    report['totals']['series_with_duplicate_chapters'] = sum(1 for r in report['series'] if r['duplicate_chapters_exact'] or r['duplicate_chapters_near'])
    report['totals']['title_groups_with_duplicates'] = len(report['title_groups'])
    json.dump(report, sys.stdout, ensure_ascii=False, indent=1)


if __name__ == '__main__':
    main()
