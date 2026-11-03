#!/usr/bin/env python3
"""The committed AMD build belongs to the committed sources (issue #31).

Every module in amd/src needs its amd/build/<name>.min.js and .min.js.map, every build needs its
source, and the source map has to carry exactly the source of today. That last point is what a
forgotten "grunt amd" leaves wrong - and it is independent of the runner: how Babel transpiles
depends on the toolchain, the source embedded in the map does not.

Usage: check_amd_build.py <plugin directory>. Exit 1 on any mismatch.
"""

import json
import os
import sys


def main(root: str) -> int:
    src = os.path.join(root, 'amd', 'src')
    build = os.path.join(root, 'amd', 'build')
    if not os.path.isdir(src):
        print('No amd/src - nothing to check.')
        return 0
    problems = []
    sources = set()
    for dirpath, _, files in os.walk(src):
        for name in files:
            if not name.endswith('.js'):
                continue
            rel = os.path.relpath(os.path.join(dirpath, name), src)[:-3]
            sources.add(rel)
            minified = os.path.join(build, rel + '.min.js')
            sourcemap = minified + '.map'
            if not os.path.isfile(minified) or not os.path.isfile(sourcemap):
                problems.append(f'{rel}: no build')
                continue
            with open(sourcemap, encoding='utf-8') as fh:
                embedded = (json.load(fh).get('sourcesContent') or [None])[0]
            with open(os.path.join(src, rel + '.js'), encoding='utf-8') as fh:
                if embedded != fh.read():
                    problems.append(f'{rel}: the build was made from another version of the source')
    for dirpath, _, files in os.walk(build):
        for name in files:
            if name.endswith('.min.js'):
                rel = os.path.relpath(os.path.join(dirpath, name), build)[:-len('.min.js')]
                if rel not in sources:
                    problems.append(f'{rel}: a build without a source')
    for problem in problems:
        print(f'::error::amd/build is not the build of amd/src - {problem}. Run "grunt amd" and commit amd/build.')
    if not problems:
        print(f'amd/build matches amd/src ({len(sources)} modules).')
    return 1 if problems else 0


if __name__ == '__main__':
    sys.exit(main(sys.argv[1] if len(sys.argv) > 1 else '.'))
