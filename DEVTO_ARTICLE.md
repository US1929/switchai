# Building an Italian energy tariff comparison site for LLMs, not humans

I run a small Italian energy tariff comparison site called [SwitchAI](https://www.switchai.it). For a while the plan was completely ordinary: user uploads a PDF bill, server parses it, site shows cheaper offers. Then I tested that plan against ten real bills from five different Italian providers, and it fell apart in a way that ended up reshaping the whole product.

Here's what happened, and what I think it says about building for a world where the "user" filling in your form is increasingly an LLM holding a PDF, not a person typing numbers.

## The parsing problem that wasn't really a parsing problem

Italian energy bills are not a standard format. Enel, Octopus, A2A, NeN, and Eni Plenitude each lay out consumption, POD/PDR codes, and cost breakdowns differently enough that a regex-based parser trained on one provider silently breaks on another.

My hosting made this worse: shared OVH hosting, no `pdftotext`, no Python runtime, no OCR. I could get a PHP-native parser working reasonably well on Enel bills. Octopus bills, structured completely differently, just didn't cooperate.

I ran the same ten PDFs through Claude, GPT, and Gemini as a sanity check before writing more parsing code. All three extracted consumption, POD, spend, and zone correctly, 10/10, across every provider format, with zero custom logic on my end.

That was the moment the project's architecture changed. I stopped trying to out-parse the LLM at something it was already better at than my code, and started asking a different question: what does a product look like if the LLM is the *input layer*, and my job is just to be an excellent thing for it to call afterward?

## Three front doors, one calculation engine

SwitchAI now has three ways in, all hitting the same tariff-comparison core:

```plaintext
User → uploads bill to Claude/ChatGPT/Gemini
         ↓
LLM → extracts consumption, cost, zone from bill text
         ↓
LLM → calls SwitchAI:
        POST /api/analyze   (plain REST)
        POST /mcp           (MCP server, JSON-RPC 2.0)
        WebMCP              (in-browser agent tool)
         ↓
SwitchAI → compares against 5,600+ ARERA-regulated offers → top 3 + risk assessment
         ↓
LLM → presents the result to the user in natural language
```

The REST endpoint, the MCP server, and the WebMCP integration all call the same PHP calculation engine underneath — I didn't want three implementations of ARERA's tariff math to drift out of sync with each other. `POST /api/analyze` is the one I'd point any agent at: it collapses what used to be 2–3 round trips into a single call and returns a compact, agent-shaped payload — top offers, a plain-language `agent_summary`, a savings breakdown, and an `affiliate_url` ready to be handed back to the user.

## Keeping personal data out of my own system

This is the part I think is actually generalizable to anyone building tools for agents, not just energy comparison sites.

The API never receives a name, address, or fiscal code. It only ever receives numbers — consumption, spend, zone. The LLM extracts the PII from the bill and holds onto it in its own context.

Activation doesn't go through SwitchAI at all. There's no subscription form, no `/sottoscrizione` page, no `submit_subscription` endpoint. The tool returns a direct affiliate link to the provider's site — the user clicks it and completes their activation there, on the provider's own checkout flow. SwitchAI never touches their personal data.

The closest I came to getting this wrong was an early design where the tool would build a prefilled URL with the user's name, fiscal code, and POD as query parameters. I wrote the helper function for it, I defined the parameters in the tool schema — and then I never wired it up. It was dead code from an older design, sitting in the codebase waiting to be mistaken for a real feature. A reviewer caught it during a security pass, and I removed it.

That pattern — code that *looks* like it should work, that you'd *swear* you'd implemented, but that never actually runs — has been the most surprising risk in building agent-facing tools. Not a model hallucinating a fact about the world, but hallucinating a fact about your own API surface based on a plausible reading of your own code.

## The endpoint that didn't exist

During that same security pass, I came across a confident, detailed description of a `submit_subscription` endpoint — the kind of description that reads like documentation, not speculation. It didn't exist anywhere in my routes, my codebase, or anything I'd actually shipped. It had been inferred, plausibly and with total conviction, because the rest of the flow made it *look* like it should exist.

Nothing exploitable came of it, but it's a preview of a genuinely new category of risk: not a model getting a fact wrong in an obviously-wrong way, but inventing a plausible piece of your own API surface with total confidence. If you're building anything agent-facing, verifying "does this actually exist in my code" against every confident claim about your own system — including claims that sound like careful analysis — is now part of the job.

## Discovery, but for agents instead of search engines

Classic technical SEO still matters — canonical URLs, a real sitemap, `noindex` on thin auto-generated pages (SwitchAI has 373 indexed provider pages and deliberately zero indexed offer-detail pages, to avoid doorway-page penalties). But I've been treating a second, parallel discovery layer as equally important:

- `llms.txt` — a plain-language description of the site for models that support it
- `webmcp.json` + registered WebMCP tools, so Chrome's in-browser agent tooling can find and call the site directly
- `openapi.json`, so ChatGPT's Actions (or anything else that consumes OpenAPI) can import the API in one step
- `robots.txt` explicitly allowing ClaudeBot, GPTBot, Google-Extended, PerplexityBot, and anthropic-ai — rather than the default-deny a lot of boilerplate configs still ship with

None of this is complicated to add. Almost none of it is being done by comparable sites in this category yet, which is a strange kind of first-mover advantage that costs nothing but attention.

## What I'd tell someone starting this today

- **If an LLM is already excellent at part of your pipeline** (unstructured extraction, in my case), stop building around that assumption being temporary. Build the seams instead.
- **Keep personal data out of your API surface** wherever you can. Let the agent carry it. If you don't need PII to run your core service, don't accept it — not because you'll mishandle it, but because the code you write around it might do something you didn't intend.
- **Dead code is a liability in an agent-facing system.** A reader parsing your codebase (human or LLM) can't distinguish between "this function exists" and "this function is called." If it's not wired up, delete it.
- **Assume an agent will occasionally invent an endpoint** that sounds like it should exist based on the rest of your API. Have a clear, boring source of truth for what actually does.
- **Treat `llms.txt` / `webmcp.json` / `openapi.json`** the way you'd treat `sitemap.xml` a decade ago: not required, cheap to add, and increasingly where a real slice of your traffic will originate.

If you want to see the whole thing end to end: the site is at [switchai.it](https://www.switchai.it), the MCP server is on [npm](https://www.npmjs.com/package/@us1929/switchai-mcp) and [GitHub](https://github.com/US1929/switchai), and you can add it as a connector directly in Claude (Settings → Connectors → `https://www.switchai.it/mcp`).

Happy to go deeper on any piece of this — the ARERA cost-calculation logic in particular has its own rabbit hole of regulatory edge cases I could write a whole separate post about.
