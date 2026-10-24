# 10-Minute Presentation Outline

**Goal:** show a working tool, explain why its answers can be trusted, and be open about what is untested.
**Set-up beforehand:** XAMPP running, `npm run dev` started, browser at `http://localhost:5173`, sample records imported, the presentation slides or README open in a second tab.

| Time | Section | What to say / show |
|---|---|---|
| 0:00–1:00 | **1. Problem** | L1/L2 agents handle repeated issues (terminal offline, UPI pending, settlement delay). Knowledge exists but is slow to find, and wrong guesses cause harm: double charges, lost unsettled batches. Goal: faster, grounded answers and correct escalation. |
| 1:00–2:00 | **2. Solution overview** | Four pages: Assistant, History, Knowledge base, Dashboard. Stack: React + Vite, PHP REST API, MySQL on XAMPP, optional local AI through Ollama (no API key, no cost, data stays on the machine). One principle: *the assistant never invents a fix and never marks anything resolved.* |
| 2:00–5:00 | **3. Live demo** | (a) UPI example → high confidence, KB-005, steps with source tags, matched terms. Tick 2 steps → **Resolved**. (b) GST example → low confidence, no steps, "Escalation recommended", **Copy escalation note**. (c) "How do I apply for annual leave?" → Unsupported. Point to the "Demo mode · no AI call" badge. |
| 5:00–6:00 | **4. History and dashboard** | Filter history by Resolved; open the detail view. Dashboard: totals, categories, activity; untick "Include sample records" to show only real data. Every number is live SQL. |
| 6:00–7:30 | **5. How it stays grounded** | Retrieval scoring (weights × IDF), four confidence levels with rules. In AI mode, the local Ollama model selects steps **by reference** and PHP copies the wording, so invented steps aren't possible. Any AI failure falls back with a visible reason. |
| 7:30–8:30 | **6. Quality and security** | Test suites: retrieval 27, API 45, AI mock 33. Prepared statements, validation, CORS allow-list, `.env` blocked, no API keys needed (local AI). Two bugs found by testing and fixed (misleading low-confidence steps; wrong category on weak matches). |
| 8:30–9:30 | **7. Honest limitations** | Original assignment document not reviewed (checklist provisional). AI mode not yet run with a real Ollama model (mock only). Keyword retrieval, no login, no KB admin UI. Next steps: KB admin page, FULLTEXT/semantic search, authentication, evaluate real Ollama models with a test set. |
| 9:30–10:00 | **8. Close + AI-assistance note** | Summarise the value. State briefly how AI assistance was used in development (see `AI_ASSISTANCE.md`). Invite questions. |

## Backup plan
- If the live demo fails, show the screenshots in `docs/screenshots/` and the test output.
- If asked to show AI mode: if you have installed Ollama, switch `AI_PROVIDER=ollama` and demo it (allow up to a minute per answer). Otherwise explain that it is tested with the mock, and optionally run `php backend\tests\ai_mock_test.php` live (about 30 seconds).

## Three points to land
1. **Grounded:** every step traces to a knowledge-base article.
2. **Honest:** uncertain → escalate; demo and fallback answers are labelled; no fake "resolved".
3. **Verified:** 105 automated checks, plus documented gaps.
