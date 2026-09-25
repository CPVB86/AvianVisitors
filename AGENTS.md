# AvianVisitors — Codex project instructions

## Project mission

This repository is being adapted so that AvianVisitors can first run locally on a normal computer as a proof-of-concept, while preserving compatibility with the original Raspberry Pi + BirdNET + optional e-ink deployment.

The immediate priority is a reproducible local browser-based demo.

The long-term target remains:

BirdNET → AvianVisitors → browser/e-ink display

Do not redesign the project into a fundamentally different architecture merely to make local development easier.

---

# Working principles

When working on this repository:

1. Inspect and understand the existing implementation before modifying it.
2. Prefer small, targeted changes over rewrites.
3. Preserve existing Raspberry Pi, BirdNET and e-ink functionality.
4. Do not remove hardware-specific code merely because it cannot run locally.
5. Instead, isolate hardware dependencies behind development/demo modes where necessary.
6. Do not require Gemini for normal local development.
7. Do not hardcode API keys, passwords, tokens or other secrets.
8. Secrets belong in environment variables.
9. Add or update `.env.example` when environment variables become necessary.
10. Do not commit `.env`.
11. Prefer existing dependencies and architecture before adding new frameworks or packages.
12. Keep the local setup reproducible from a clean checkout.
13. Update documentation when setup or behaviour changes.
14. Run the relevant application/tests yourself after making changes whenever the environment allows it.
15. Do not stop after producing only an implementation plan if the implementation can reasonably be completed.
16. Fix errors encountered during implementation when they are caused by or block the requested work.
17. Do not silently change existing production behaviour.
18. Separate production/frame behaviour from demo/development behaviour where appropriate.
19. Keep generated files, caches, virtual environments, build output and secrets out of Git.
20. Before finishing a meaningful unit of work, inspect `git diff` and make sure unrelated changes have not been introduced.

---

# Git / multi-computer workflow

This project is worked on from multiple computers.

The Git repository is the source of truth for code and project documentation.

Before starting work:

```bash
git status
git pull --rebase
```

Before finishing work:

```bash
git status
git diff
```

Commit logical units of work and push them to the remote repository.

Do not commit:

- `.env`
- API keys
- passwords
- access tokens
- local virtual environments
- `node_modules`
- caches
- temporary output
- generated local databases
- machine-specific configuration unless intentionally part of the project

Do not force-push or rewrite shared history unless explicitly requested.

If the working tree contains changes that were not created during the current task, preserve them and avoid overwriting them.

---

# Current primary task

The following is the original project brief and remains the guiding specification until it has been completed or explicitly superseded.

## Goal

Get this repository working locally as a proof-of-concept:

https://github.com/Twarner491/AvianVisitors

AvianVisitors must first be testable on a normal computer WITHOUT requiring:

- Raspberry Pi
- e-ink display/frame
- GPIO/SPI hardware
- a physical BirdNET installation when not necessary for the first test
- Gemini API

Eventually the project will probably run on a Raspberry Pi with BirdNET and an e-ink frame, so preserve the existing architecture and compatibility as much as possible.

---

# Step 1 — Analyse the repository first

Investigate the complete repository before making changes.

Map out:

- how AvianVisitors obtains BirdNET detections;
- how the frontend/collage is constructed;
- where and how bird illustrations are loaded;
- which components are specifically Raspberry Pi/e-ink related;
- which components use Gemini;
- which dependencies are required;
- how the different components communicate with each other.

Use the existing implementation as much as possible.

Do not perform a large rewrite unless it is genuinely necessary.

---

# Step 2 — Create a local test mode

AvianVisitors must be viewable on a normal computer in a web browser.

Create a simple development/demo mode if necessary.

Use simulated Dutch bird detections, for example:

- Merel — 14
- Koolmees — 9
- Pimpelmees — 7
- Roodborst — 5
- Houtduif — 4
- Ekster — 2
- Grote bonte specht — 1

If the existing illustrations do not contain these species, use existing available bird illustrations or clear placeholders for the first technical test.

The purpose of this stage is to get the complete data flow and layout working first.

The eventual local startup should be simple, for example:

```bash
npm run dev
```

or:

```bash
python ...
```

Choose the mechanism that best matches the existing repository.

Do not introduce Node.js merely to provide an `npm run dev` command if the existing application is better served by its current Python/PHP/static architecture.

---

# Step 3 — Isolate hardware dependencies

Ensure that Raspberry Pi and e-ink functionality do not block the local test environment.

DO NOT remove that functionality.

Prefer a clean distinction such as:

```text
production / frame mode
demo / development mode
```

The eventual Raspberry Pi installation must remain possible.

Hardware-specific modules should not be imported or initialized in local demo mode unless actually needed.

---

# Step 4 — Do not use Gemini for now

There is currently no Gemini API access available for this project.

Disable or bypass Gemini dependencies for the local demo wherever necessary.

The repository contains scripts for generating bird illustrations. That functionality will probably later be migrated to the OpenAI API.

Only perform that migration during this initial phase if Gemini is actually required for the local demo to run.

Otherwise:

- leave the existing generator intact;
- document where Gemini is used;
- make sure normal local demo execution does not require a Gemini API key.

---

# Step 5 — Prepare for OpenAI image generation

Investigate how the current Gemini image-generation pipeline works.

The eventual intention is to replace Gemini image generation with OpenAI image generation.

In the final report clearly identify:

- which file or files need modification;
- which Gemini API calls are currently used;
- what input they receive;
- which reference images are passed;
- what output is expected;
- what subsequent steps are necessary to use OpenAI as a drop-in alternative.

Do not fully implement this migration yet unless it is necessary for the local demo.

Keep image-generation functionality separate from normal application runtime where possible.

---

# Step 6 — Actually test the application

Run the local application yourself.

Resolve errors and missing dependencies where reasonably possible.

Verify that:

1. the application starts;
2. dummy detections are read;
3. birds/data actually appear in the frontend;
4. the frontend is reachable through localhost;
5. Raspberry Pi/e-ink hardware is not required to view the demo.

Run existing tests if present.

Only add tests when they provide meaningful coverage for the changes made.

Do not claim that something was tested if it was only inspected statically.

Clearly distinguish:

```text
verified by execution
```

from:

```text
verified by code inspection
```

---

# Important constraints

- Keep changes as small as possible.
- Preserve existing functionality.
- No large rewrite.
- Do not remove Raspberry Pi-specific code.
- Do not remove e-ink functionality.
- Do not hardcode API keys.
- Do not assume a Gemini account or Gemini API key.
- Secrets must use environment variables only.
- Add `.env.example` if useful.
- Make the local setup reproducible.
- Use existing dependencies and architecture where possible.
- Document new development functionality.

---

# Desired end result

After the changes, a fresh checkout should be able to start the local demo using only a few clearly documented commands.

Create or update documentation containing a section named approximately:

```text
Local development / demo mode
```

It must contain exact setup and startup instructions.

The final implementation report must include:

1. What was discovered about the architecture.
2. Which files were modified.
3. Why each modification was necessary.
4. How to start the demo.
5. Which URL to open in the browser.
6. Which components are currently mocked/simulated.
7. What is needed to connect real BirdNET data.
8. What is needed to replace Gemini with OpenAI.
9. Any problems or technical debt encountered in the repository.

Work independently until the local demo actually works.

Do not stop after only an analysis or implementation proposal when the changes can be performed and tested.

---

# Definition of done for the local proof-of-concept

The local-development milestone is complete only when all of the following are true:

- A clean checkout can be configured using documented commands.
- No Raspberry Pi is required.
- No GPIO/SPI hardware is required.
- No e-ink display is required.
- No Gemini API key is required.
- A local server/process can be started.
- A localhost URL can be opened.
- Simulated detections are fed through the application.
- Those detections visibly affect the AvianVisitors frontend/collage.
- Existing production/Raspberry Pi functionality has not intentionally been removed.
- Setup instructions are documented.
- Relevant tests/checks have been run.

If one of these cannot be achieved, document precisely:

- what is blocking it;
- what was attempted;
- what remains necessary.

Do not describe the milestone as complete while any required item remains unverified.

---

# Continuation between Codex sessions

At the beginning of a new Codex session:

1. Read this `AGENTS.md`.
2. Read `README.md`.
3. Inspect `git status`.
4. Inspect the most recent commits.
5. Inspect any project status/handoff documentation if present.
6. Continue from the existing implementation instead of recreating previous work.

When significant work remains unfinished, maintain a concise project status file at:

```text
docs/LOCAL_DEVELOPMENT_STATUS.md
```

That file may contain:

```text
Current status
What works
What has been tested
Known problems
Next logical step
Relevant commands
```

Keep it factual and concise.

Do not use this file as a substitute for proper README documentation once a feature is finished.