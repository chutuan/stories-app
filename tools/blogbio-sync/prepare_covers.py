#!/usr/bin/env python3
"""Đưa ảnh bìa Blogbio về đúng khuôn bìa của Stories: 600x800 JPEG (tỉ lệ 3:4).

Dùng: prepare_covers.py <thư-mục-nguồn>   (đọc import_map.json, ghi stories_covers/<slug>.jpg)

Cắt về 3:4 trước rồi mới thu nhỏ. Ảnh cao hơn 3:4 thì cắt LỆCH LÊN (bỏ 30% phần thừa ở trên,
70% ở dưới) vì mặt người thường ở nửa trên khung; ảnh rộng hơn thì cắt giữa. Dùng `sips` có
sẵn trong macOS, không cần thư viện ngoài. Ảnh đã có thì bỏ qua (chạy lại không tốn công).
"""
import json
import os
import subprocess
import sys

W, H = 600, 800


def dims(path):
    out = subprocess.run(['sips', '-g', 'pixelWidth', '-g', 'pixelHeight', path],
                         capture_output=True, text=True, check=True).stdout.split()
    return int(out[out.index('pixelWidth:') + 1]), int(out[out.index('pixelHeight:') + 1])


def main():
    src = sys.argv[1].rstrip('/')
    os.makedirs(f'{src}/stories_covers', exist_ok=True)
    imap = json.load(open(f'{src}/import_map.json', encoding='utf-8'))
    done = skipped = 0
    for sid, v in imap.items():
        if not v.get('cover_file'):
            print(f'  ! không có bìa: {v["slug"]}', file=sys.stderr)
            continue
        out = f'{src}/stories_covers/{v["slug"]}.jpg'
        if os.path.exists(out):
            skipped += 1
            continue
        w, h = dims(f'{src}/{v["cover_file"]}')
        if w / h < W / H:                      # cao hơn 3:4 -> cắt bớt chiều cao
            ch, cw = round(w * H / W), w
            oy, ox = round((h - ch) * 0.3), 0
        else:                                  # rộng hơn -> cắt bớt chiều ngang, giữa
            ch, cw = h, round(h * W / H)
            oy, ox = 0, round((w - cw) / 2)
        tmp = out + '.tmp.png'
        subprocess.run(['sips', '-c', str(ch), str(cw), '--cropOffset', str(oy), str(ox),
                        f'{src}/{v["cover_file"]}', '--out', tmp], capture_output=True, check=True)
        subprocess.run(['sips', '-z', str(H), str(W), '-s', 'format', 'jpeg', '-s', 'formatOptions', '82',
                        tmp, '--out', out], capture_output=True, check=True)
        os.remove(tmp)
        v['story_cover'] = f'stories_covers/{v["slug"]}.jpg'
        done += 1
    for v in imap.values():
        if os.path.exists(f'{src}/stories_covers/{v["slug"]}.jpg'):
            v['story_cover'] = f'stories_covers/{v["slug"]}.jpg'
    json.dump(imap, open(f'{src}/import_map.json', 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
    print(f'bìa mới: {done}, đã có: {skipped}', file=sys.stderr)


if __name__ == '__main__':
    main()
