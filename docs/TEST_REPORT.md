# Test Report

**Date of final run:** 24 October 2026 (after switching AI mode from the Anthropic API to a local Ollama model)
**Environment:** Windows 11 · XAMPP (Apache 2.4.58, PHP 8.2.12, MariaDB 10.4.32) · Node.js 22.14 / npm 10.9 · Microsoft Edge (headless Chromium) for browser checks
**Mode tested:** demo mode on Apache; AI mode against a **local mock of the Ollama API** only
**Real AI model used:** none. Ollama is not installed on the development machine, so no model was downloaded or run.

## Summary

| Suite | Command | Result |
|---|---|---|
| PHP syntax check (all backend files) | `php -l` on each file | **21 / 21 OK** |
| Retrieval | `php backend\tests\retrieval_test.php` | **27 / 27 passed** |
| API end-to-end (Apache + MySQL) | `php backend\tests\api_test.php` | **45 / 45 passed** |
| AI mode vs mock Ollama server | `php backend\tests\ai_mock_test.php` | **33 / 33 passed** |
| Frontend production build | `npm run build` | **Success** (JS 188 KB / 59 KB gzipped) |
| Browser checks, demo mode (headless Edge, development script not in repo) | - | **18 / 18 passed**, no console errors |
| Browser check, AI mode against the Ollama mock | - | AI answer, AI-unavailable fallback and no-AI-call cases all labelled correctly |

**Failures in the final run: none.**

## What each suite covers

**Retrieval (27 cases)**
- 16 clearly described issues, each expecting a specific article with high confidence.
- 4 short or partial descriptions expecting medium or low confidence.
- 7 vague or out-of-scope questions that must not produce a confident answer.

**API end-to-end (45 checks)**
- Health check.
- Grounded answer for a UPI issue: KB-005, high confidence, demo notice, every step cites a source, status `open`, category detected.
- Out-of-scope issue: unsupported, no sources, no steps, escalation, no guessed category.
- Weak match: low confidence, escalation, no steps or causes, closest article listed, no category.
- A category chosen by the user is kept.
- Validation: empty, too short, too long, no words, non-string, unknown category, SQL-like category, invalid JSON (400), wrong content type (415), wrong method (405).
- History: stored answer read back; outcome updates; invalid status (422); unknown and non-numeric IDs (404); status filter; search; invalid filter (422).
- Analytics compared with independent SQL queries, with and without sample data: totals, per-status counts, category sums, 14-day activity.
- CORS: allowed for the Vite origin, refused for an unknown origin.
- The test deletes the queries it creates.

**AI mode vs mock Ollama (33 checks)**
- Health reports `mode: ai`, `ai_provider: ollama` and the model name.
- A valid response gives the AI summary and next action, steps copied **verbatim** from KB-005, escalation conditions from the article, and the "local Ollama model" notice.
- The request sent to `/api/chat`: model; `stream: false`; a system message and a user message; `options` with temperature 0, `num_ctx` 8192 and `num_predict` 1024; a JSON schema in `format`; the issue wrapped in `<issue>` tags; numbered article items; the "treat as data" instruction.
- Stored with `answer_mode = ai` and the model name.
- Partial invalid references: unknown article, non-existent item and duplicate are dropped; the valid step is kept.
- Model judges the articles not applicable: no steps, escalation, low confidence, no category.
- Each of these falls back to the template answer with a reason: invalid references only, `done_reason: length` (cut off), non-JSON output, wrong JSON shape, **model not installed** (404, reason includes the `ollama pull` command), HTTP 500, timeout (bounded by the configured 5 s), and **Ollama not running** (connection refused).
- Unsupported and low-confidence issues make **no** AI call.

**Browser checks**
- Demo mode (18 checks):
  - page load with the "Demo mode" status; empty-submit validation;
  - example chip → high-confidence answer citing KB-005; step checkboxes; recording "Resolved"; "Open article" link;
  - out-of-scope answer with escalation and no steps; 390 px mobile layout without overflow;
  - History: ordering, status filter, empty state, sample toggle, detail page with outcome note, unknown-ID error;
  - Knowledge base: list and search;
  - Dashboard totals equal to the API values, the sample toggle, the chart tooltip; mobile and dark-mode dashboard.
- AI mode against the mock:
  - header "AI mode · Ollama llama3.2:3b";
  - "AI-assisted · llama3.2:3b" badge with 3 selected steps;
  - "Template answer · AI unavailable" with the reason on a server error;
  - "Template answer · no AI call" for an unsupported issue.

These scripts were temporary development tools and are not included in the project; README §13 has the equivalent manual checklist.

**Other checks run during development**
- Database scripts imported twice: no duplicates, and the sample-row replacement works.
- Database unavailable (backend pointed at a wrong port): health reports "degraded", the API returns 503 with a generic message, details appear only in the log.
- `.env`, `.env.example`, log files and `src/` aren't reachable through Apache (403 / 404).
- Submission ZIP: secret scan, exclusions, forward-slash paths, fresh install from the extracted ZIP.

## Issues found during development (all fixed)
1. **False high confidence:** "QR code not scanning" was rated high because of generic words. Fixed: generic words aren't counted on their own, and "high" needs at least 3 terms.
2. **Misleading low-confidence answers:** unrelated steps were shown. Fixed: low confidence shows no steps.
3. **Wrong category on weak matches:** fixed by detecting a category only for medium or high confidence.
4. **UI:** chart scaling, mobile navigation overflow, KB tag wrapping, a wrong "no AI call" label on failed AI calls.
5. **Packaging:** a false positive in the secret scanner; backslash ZIP paths that macOS/Linux would mis-extract.
6. **Ollama switch:** a 10-second minimum timeout overrode the configured 5 s in tests. Lowered to 5 s.
7. **Test-script bugs:** null checks written with `?? 'x'` could never pass. Fixed in the tests (not application bugs).

## Known weakness found in verification (not fixed yet)
Short, natural phrasings can rate lower than they should. Examples:
- "customer wants refund" → unsupported, although KB-014 covers refunds;
- "settlement pending since 3 days" → the wrong articles.

The cause is that the minimum score of 6 is too high for a single specific keyword, stop-word handling runs before stemming, and some common wordings are missing from the article keywords. The behaviour stays safe (escalation, no invented steps), but it is less helpful. A fix has been proposed and is waiting for approval.

## Not tested
- **A real Ollama installation and model.** Answer quality and speed of `llama3.2:3b` (or any other model) are unknown.
- Importing through the **phpMyAdmin web interface** (the same SQL files were imported with the MySQL command line).
- Browsers other than Chromium-based Edge; real mobile devices; screen readers. No automated accessibility audit.
- UI behaviour with MySQL stopped (the API behaviour was tested).
- Load or performance testing.
