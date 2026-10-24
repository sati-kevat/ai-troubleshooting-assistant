# Use of AI Assistance in Development

> ✏️ **Candidate: review and complete this file before submitting.**
> It was drafted from the development session log. It describes what the AI coding assistant did and which decisions were confirmed by the candidate *during that session*. It can't know about work you did outside the session. Edit it so it accurately reflects your own contribution.

## Tool used
**Claude Code** (Anthropic's AI coding assistant, running in the terminal on the project folder) was used throughout development. It read and wrote files, ran commands (npm, PHP, MySQL CLI) and ran tests on the local machine. The candidate directed the work, approved each phase before it started, and made the decisions listed below.

## Decisions made by the candidate
| Decision | Choice |
|---|---|
| Assignment | Practical AI Assignment 3 - AI Troubleshooting Assistant for L1/L2 Support |
| Technology stack | React (JavaScript/JSX, not TypeScript) + Vite, PHP REST API, MySQL on XAMPP; no Python |
| Working method | Inspect first, present a plan, then build in phases with testing after each |
| AI approach | Demo mode first; no paid API calls; test the AI integration with a mock server |
| AI provider | Initially the Anthropic Claude API (raw HTTP with PHP cURL); later **replaced by a local Ollama model** (`llama3.2:3b`) at the candidate's request, so AI mode needs no API key and no cloud service |
| Deployment | Backend linked into XAMPP `htdocs` with a directory junction |
| Knowledge base | Fictional payments/technology-operations articles, clearly labelled as fictional |
| Documentation | Requirements checklist marked provisional because the original assignment document was unavailable |

## Tasks where AI assistance was used
| Task | AI assistance |
|---|---|
| Project inspection and plan | Inspected the empty folder and local tools (Node, XAMPP, PHP, MariaDB); drafted the requirements checklist and phased plan |
| Database | Wrote the schema, the 15 fictional articles and the optional sample history |
| Backend | Wrote the PHP API: router, validation, repositories, retrieval algorithm, demo answer generator, AI integration (first Anthropic, then rewritten for Ollama), error handling, CORS |
| Frontend | Wrote the React components, pages, hooks, CSS and charts |
| Testing | Wrote the three PHP test suites and the mock Ollama server; ran automated browser checks with headless Edge; tuned retrieval thresholds from test results; found and fixed bugs |
| Documentation | Drafted the README and every document in `docs/` |
| Packaging | Wrote the PowerShell ZIP script with secret checks |

## What was not done with AI
- No calls to any real AI service or model were made. Ollama was not installed; AI mode was tested only with a local mock.
- **(Candidate: add your own contributions here.)** Examples: manual testing you did, code you changed or rewrote, screenshots you captured, content you edited, what you verified by reading the code.

## How the AI-assisted output was checked
- Every phase was tested before moving on: automated suites (105 checks after the switch to Ollama), production build, and screenshot reviews.
- The AI's own mistakes were caught by tests and corrected. For example, two bugs in its test scripts (null checks that could never pass), misleading low-confidence answers, and incorrect category detection.
- **(Candidate: describe your own review, e.g. "I read through `KnowledgeRetriever.php` and can explain the scoring", or "I re-ran all tests on my machine".)**

## Statement
*(Candidate: write a one- or two-sentence honest summary in your own words. For example: "The code was produced with substantial help from an AI coding assistant under my direction. I made the design decisions above, reviewed the output, ran the tests, and can explain how each part works.")*
