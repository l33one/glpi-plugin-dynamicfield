# -*- coding: utf-8 -*-
"""Compile .po files to .mo files (gettext binary format, little-endian).

This reproduces msgfmt's output layout so the file is a valid .mo:
  header(28) | orig string table (n*8) | trans string table (n*8)
  | hash table (4*hsize) | orig strings | trans strings

GLPI 10 loads these from plugin locales/ named <language>.mo.

Usage: python compile_po_to_mo.py <file.po> [<file.po> ...]
Writes a <stem>.mo next to each .po.
"""

import re
import struct
import sys

MAGIC_LE = 0x950412DE  # packed '<I' -> bytes: de 12 04 95
EMPTY_HASH_SLOT = 0xFFFFFFFF


def unquote(token):
    """Unescape a single quoted token like "a\\"b" -> a"b."""
    body = token[1:-1]
    out = []
    i = 0
    n = len(body)
    esc = {'n': '\n', 't': '\t', 'r': '\r', '"': '"', '\\': '\\', '0': '\x00'}
    while i < n:
        ch = body[i]
        if ch == '\\' and i + 1 < n:
            nxt = body[i + 1]
            out.append(esc.get(nxt, nxt))
            i += 2
        else:
            out.append(ch)
            i += 1
    return ''.join(out)


def parse_po(text):
    """Parse PO text into a list of ordered dict entries."""
    entries = []
    cur = None

    def flush():
        nonlocal cur
        if cur is not None:
            entries.append(cur)
        cur = None

    field = None
    parts = []  # accumulated quoted tokens for current field

    def set_value(key, tokens):
        nonlocal field, parts
        cur[key] = ''.join(unquote(t) for t in tokens)
        field = key
        parts = []

    for raw in text.splitlines():
        line = raw.strip()
        if not line or line.startswith('#'):
            continue

        if line.startswith('"'):
            # Continuation of the previous field's value.
            if field is None or cur is None:
                raise ValueError('continuation without field: %r' % raw)
            cur[field] += unquote(line)
            continue

        m = re.match(r'(msgctxt|msgid_plural|msgstr(?:\[\d+\])?|msgid|msgstr)\s*(.*)$', line)
        if not m:
            raise ValueError('cannot parse line: %r' % raw)
        keyword, rest = m.group(1), m.group(2).strip()

        if keyword == 'msgid':
            if cur is not None and ('msgid' in cur or 'msgid_plural' in cur):
                flush()
            if cur is None:
                cur = {}
            set_value('msgid', [rest]) if rest else cur.setdefault('msgid', '')
            field = 'msgid'
        elif keyword == 'msgid_plural':
            set_value('msgid_plural', [rest])
        elif keyword == 'msgstr':
            set_value('msgstr', [rest])
        elif keyword.startswith('msgstr['):
            set_value(keyword, [rest])
        elif keyword == 'msgctxt':
            set_value('msgctxt', [rest])
    flush()
    return entries


def next_prime(x):
    x += 1
    while True:
        for d in range(2, int(x ** 0.5) + 1):
            if x % d == 0:
                break
        else:
            return x
        x += 1


def msgfmt_hash(s):
    h = 0
    for ch in s.encode('utf-8'):
        h = ((h << 5) + (h >> 27) + ch) & 0xFFFFFFFF
    return h


def compile_mo(entries, out_path):
    n = len(entries)

    hsize = next_prime(max(1, (n * 4) // 3 + 1))
    hash_tab = [EMPTY_HASH_SLOT] * hsize

    def hash_insert(idx, key):
        h = msgfmt_hash(key) % hsize
        incr = 1 + (msgfmt_hash(key) % (hsize - 2))
        while hash_tab[h] != EMPTY_HASH_SLOT:
            h = (h + incr) % hsize
        hash_tab[h] = idx

    for i, e in enumerate(entries):
        key = e.get('msgid', '')
        hash_insert(i, key if key else '\x04')

    orig_off = 28
    trans_off = orig_off + n * 8
    hash_off = trans_off + n * 8
    data_off = hash_off + hsize * 4

    orig_blob = b''
    orig_table = b''
    cur = data_off
    for e in entries:
        s = e.get('msgid', '').encode('utf-8')
        if 'msgid_plural' in e:
            s += b'\x00' + e['msgid_plural'].encode('utf-8')
        orig_table += struct.pack('<II', len(s), cur)
        orig_blob += s + b'\x00'
        cur += len(s) + 1

    trans_blob = b''
    trans_table = b''
    for e in entries:
        if 'msgid_plural' in e:
            forms = [e.get('msgstr[%d]' % i, '').encode('utf-8')
                     for i in range(_plural_count(e))]
            ss = b'\x00'.join(forms)
        else:
            ss = e.get('msgstr', '').encode('utf-8')
        trans_table += struct.pack('<II', len(ss), cur)
        trans_blob += ss + b'\x00'
        cur += len(ss) + 1

    hash_bytes = b''.join(struct.pack('<I', v) for v in hash_tab)
    header = struct.pack('<7I', MAGIC_LE, 0, n, orig_off, trans_off, hsize, hash_off)

    with open(out_path, 'wb') as f:
        f.write(header)
        f.write(orig_table)
        f.write(trans_table)
        f.write(hash_bytes)
        f.write(orig_blob)
        f.write(trans_blob)


def _plural_count(entry):
    i = 0
    while 'msgstr[%d]' % i in entry:
        i += 1
    return max(i, 1)


def main(argv):
    if not argv:
        print('usage: python compile_po_to_mo.py <file.po> [<file.po> ...]')
        return 1
    import os
    for po_path in argv:
        with open(po_path, 'rb') as f:
            text = f.read().decode('utf-8')
        entries = parse_po(text)
        out = os.path.splitext(po_path)[0] + '.mo'
        compile_mo(entries, out)
        print('%s -> %s (%d entries)' % (po_path, out, len(entries)))
    return 0


if __name__ == '__main__':
    sys.exit(main(sys.argv[1:]))