#!/usr/bin/env python3
"""Attempt load test for mod_adaptivequiz #17 against an installed site seeded by seed_attempt_load.php.

Every participant logs in, starts an attempt and answers every question over HTTP until the result page, as a
browser does: GET the attempt page, POST the response form. Optionally:

  --parallel N        N participants at the same time (default 1: one after the other)
  --double-final      the last answer is sent twice at once (a double click on the last "Submit answer")
  --abort-at K        the answer to question K is sent and the connection dropped after 50 ms - a POST the
                      browser gave up on; the attempt page is then requested again and the attempt goes on
  --relogin-at K      before question K the participant logs in again in a new session: the session cache is
                      empty and the progress comes from the database (issue #149, cache miss)
  --feedback          on the result page every lazy feedback region is loaded as the browser does
                      (local_catquiz_render_feedback_tab); per region the status and whether it failed

The result page is checked in any case for an exception (an error box, a TypeError, a stack trace) and for the
message that the progress is missing; this goes to the summary of each attempt.

The client side is written to --out as JSON: per request the person, the question number, the kind (start,
answer, final, abort, recover), the HTTP status, the client time and the request id the host sent
(X-ALiSe-Request-Id). The server side is in local_catquiz_trace under the same request ids
(analyse_attempt_load.php). No answer text is recorded.

Environment: LOAD_BASE_URL, LOAD_CMID, LOAD_USERS (comma separated), LOAD_PASSWORD.
"""
import argparse
import html
import json
import os
import random
import re
import sys
import threading
import time
from concurrent.futures import ThreadPoolExecutor

import requests

BASE = os.environ['LOAD_BASE_URL'].rstrip('/')
CMID = os.environ['LOAD_CMID']
USERS = os.environ['LOAD_USERS'].split(',')
PASSWORD = os.environ['LOAD_PASSWORD']

INPUT = re.compile(r'<input\b[^>]*>', re.I)
ATTR = re.compile(r'(\w[\w:-]*)\s*=\s*"([^"]*)"')
FORM = re.compile(r'<form\b[^>]*id="responseform"[^>]*>(.*?)</form>', re.S | re.I)
LAZY = re.compile(r'<div data-region="local_catquiz-lazyfeedback"\s+data-attemptid="(\d+)"\s+'
                  r'data-generatorname="([\w-]+)"\s+data-audience="(\w+)"', re.S)
SESSKEY = re.compile(r'"sesskey":"([^"]+)"')
# What a page shows of an exception - not the words in the strings of the page's JavaScript.
EXCEPTION = re.compile(r'class="[^"]*\berrorbox\b|class="errormessage"|data-rel="fatalerror"|Fatal error</b>'
                       r'|Uncaught (?:TypeError|Error|Exception)|"exception":\{|"errorcode":"', re.I)
MISSING = 'The progress of this attempt was not found.'


def attrs(tag):
    return {k.lower(): html.unescape(v) for k, v in ATTR.findall(tag)}


def response_form(page):
    """The fields of the response form - hidden ones, one choice per radio group - or None at the end."""
    m = FORM.search(page)
    if not m:
        return None
    fields, radios = {}, {}
    for tag in INPUT.findall(m.group(1)):
        a = attrs(tag)
        name, kind = a.get('name'), a.get('type', 'text').lower()
        if not name:
            continue
        if kind == 'radio':
            radios.setdefault(name, []).append(a.get('value', ''))
        elif kind in ('hidden', 'submit'):
            fields[name] = a.get('value', '')
        elif kind == 'text':
            fields[name] = '1'
    for name, values in radios.items():
        # Any answer: right or wrong, as the CAT model's selection follows it.
        fields[name] = random.choice([v for v in values if v not in ('', '-1')] or values)
    return fields


def login(session, username):
    page = session.get(BASE + '/login/index.php').text
    token = re.search(r'name="logintoken" value="([^"]+)"', page)
    data = {'username': username, 'password': PASSWORD}
    if token:
        data['logintoken'] = token.group(1)
    r = session.post(BASE + '/login/index.php', data=data)
    if 'loginerrors' in r.text or '/login/index.php' in r.url:
        raise RuntimeError('login failed for ' + username)


def check_result(page):
    """The result page: exception or missing progress; with --feedback every lazy region loaded."""
    result = {'exception': bool(EXCEPTION.search(page)), 'progressmissing': MISSING in page,
              'lazyregions': 0, 'lazyfailed': 0}
    return result


def load_feedback(s, page, record, result):
    regions = LAZY.findall(page)
    key = SESSKEY.search(page)
    result['lazyregions'] = len(regions)
    if not key:
        result['lazyfailed'] = len(regions)
        return
    for attemptid, generator, audience in regions:
        t0 = time.perf_counter()
        r = s.post(f'{BASE}/lib/ajax/service.php', params={'sesskey': key.group(1)}, json=[{
            'index': 0, 'methodname': 'local_catquiz_render_feedback_tab',
            'args': {'attemptid': int(attemptid), 'generatorname': generator, 'audience': audience}}])
        record('feedback', 0, r, t0)
        try:
            body = r.json()
            failed = r.status_code != 200 or body[0].get('error') or EXCEPTION.search(json.dumps(body))
        except (ValueError, IndexError, AttributeError):
            failed = True
        if failed:
            result['lazyfailed'] += 1


def run_attempt(username, args, log, lock):
    s = requests.Session()
    login(s, username)
    records = []

    def record(kind, number, r, t0):
        records.append({
            'user': username, 'number': number, 'kind': kind, 'status': r.status_code if r is not None else None,
            'ms': round((time.perf_counter() - t0) * 1000, 2),
            'requestid': r.headers.get('X-ALiSe-Request-Id') if r is not None else None,
            'url': r.url.replace(BASE, '') if r is not None else None,
        })

    t0 = time.perf_counter()
    r = s.get(f'{BASE}/mod/adaptivequiz/attempt.php', params={'cmid': CMID})
    record('start', 1, r, t0)
    number = 1
    while True:
        fields = response_form(r.text)
        if fields is None:
            break
        last = number == args.items
        if args.relogin_at and number == args.relogin_at:
            # A new session: nothing of this attempt in the session cache any more.
            s = requests.Session()
            login(s, username)
            t0 = time.perf_counter()
            r = s.get(f'{BASE}/mod/adaptivequiz/attempt.php', params={'cmid': CMID})
            record('relogin', number, r, t0)
            fields = response_form(r.text)
            if fields is None:
                break
        if args.abort_at and number == args.abort_at:
            t0 = time.perf_counter()
            try:
                s.post(f'{BASE}/mod/adaptivequiz/attempt.php', data=fields, timeout=0.05)
            except requests.exceptions.RequestException:
                pass
            record('abort', number, None, t0)
            time.sleep(1.0)
            t0 = time.perf_counter()
            r = s.get(f'{BASE}/mod/adaptivequiz/attempt.php', params={'cmid': CMID})
            record('recover', number, r, t0)
            if response_form(r.text) and response_form(r.text).get('slots') == fields.get('slots'):
                # The aborted answer was not stored: the same question is on the screen again.
                continue
            number += 1
            continue
        if last and args.double_final:
            results = [None, None]

            def send(i):
                t = time.perf_counter()
                resp = s.post(f'{BASE}/mod/adaptivequiz/attempt.php', data=fields)
                results[i] = (resp, t)
            threads = [threading.Thread(target=send, args=(i,)) for i in (0, 1)]
            for t in threads:
                t.start()
            for t in threads:
                t.join()
            for resp, t in results:
                record('final-double', number, resp, t)
            r = results[0][0]
            number += 1
            break
        t0 = time.perf_counter()
        r = s.post(f'{BASE}/mod/adaptivequiz/attempt.php', data=fields)
        record('final' if last else 'answer', number, r, t0)
        number += 1
        if number > args.items + 5:
            break
    result = check_result(r.text)
    if args.feedback:
        load_feedback(s, r.text, record, result)
    with lock:
        log.extend(records)
    return username, number - 1, r.url.replace(BASE, ''), result


def main():
    p = argparse.ArgumentParser()
    p.add_argument('--parallel', type=int, default=1)
    p.add_argument('--users', type=int, default=len(USERS))
    p.add_argument('--items', type=int, default=int(os.environ.get('LOAD_ITEMS', '160')))
    p.add_argument('--double-final', action='store_true')
    p.add_argument('--abort-at', type=int, default=0)
    p.add_argument('--relogin-at', type=int, default=0)
    p.add_argument('--feedback', action='store_true')
    p.add_argument('--out', required=True)
    args = p.parse_args()
    random.seed(17)
    log, lock = [], threading.Lock()
    started = time.time()
    users = USERS[:args.users]
    with ThreadPoolExecutor(max_workers=args.parallel) as ex:
        results = list(ex.map(lambda u: run_attempt(u, args, log, lock), users))
    out = {'parallel': args.parallel, 'users': users, 'items': args.items, 'double_final': args.double_final,
           'abort_at': args.abort_at, 'relogin_at': args.relogin_at, 'feedback': args.feedback,
           'started': started, 'seconds': round(time.time() - started, 1),
           'attempts': [{'user': u, 'answered': n, 'end': end, 'result': res} for u, n, end, res in results],
           'requests': log}
    with open(args.out, 'w') as f:
        json.dump(out, f, indent=1)
    for u, n, end, res in results:
        print(f'{u}: {n} answered, end {end}, result {res}')
    # A problem: an exception or a missing progress on the result page, a feedback region that failed, or an
    # attempt that did not reach its number of questions (refused at the start, ended early).
    bad = [u for u, n, end, res in results
           if res['exception'] or res['progressmissing'] or res['lazyfailed'] or n != args.items]
    statuses = {}
    for rec in log:
        statuses[rec['status']] = statuses.get(rec['status'], 0) + 1
    print(f'{len(log)} requests in {out["seconds"]} s; status {statuses}; attempts with a problem: {len(bad)}')
    return 1 if bad else 0


if __name__ == '__main__':
    sys.exit(main())
