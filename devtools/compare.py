import json, sys, os, collections
# Compare old/new dumps : order of keys, and leaf value differences
def flat(o, p=''):
    if isinstance(o, dict):
        for k, v in o.items(): yield from flat(v, p + '/' + str(k))
    elif isinstance(o, list):
        for i, v in enumerate(o): yield from flat(v, p + '[' + str(i) + ']')
    else:
        yield p, o
d = sys.argv[1]
for f in sorted(os.listdir(d + '/new')):
    old = json.load(open(d + '/old/' + f)); new = json.load(open(d + '/new/' + f))
    print('==', f)
    for sec in new:
        o = old.get(sec, {}); n = new[sec]
        if isinstance(n, dict) and 'data' in n: o, n = o.get('data', {}), n['data']
        ko, kn = list(o), list(n)
        if set(ko) != set(kn): print(f'  {sec}: keys -{len(set(ko)-set(kn))} +{len(set(kn)-set(ko))}', list(set(ko) ^ set(kn))[:4])
        elif ko != kn: print(f'  {sec}: same keys, order changed')
    fo, fn = dict(flat(old)), dict(flat(new))
    diffs = [(k, fo.get(k), fn.get(k)) for k in sorted(set(fo) | set(fn)) if fo.get(k) != fn.get(k)]
    kinds = collections.Counter(k.split('/')[1] + ':' + k.rsplit('/', 1)[-1].split('[')[0] for k, a, b in diffs)
    print('  value diffs:', dict(kinds))
    for k, a, b in diffs[:int(sys.argv[2]) if len(sys.argv) > 2 else 3]:
        print('   ', k, '\n      old:', repr(a)[:220], '\n      new:', repr(b)[:220])
