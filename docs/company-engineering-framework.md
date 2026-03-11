# CORRAD Company Engineering Framework

## Purpose

Standardize how teams build software across company projects while using AI safely and predictably.

Goals:
- Faster project setup and delivery
- Consistent architecture and quality
- Lower AI hallucination risk
- Easier onboarding and code reviews

---

## Framework Layers

### 1) Policy Layer (AI + Engineering Rules)

Required in every project:
- `CLAUDE.md` (canonical AI guideline)
- `AGENTS.md` (agent compatibility)
- `.cursorrules` (Cursor compatibility)

Policy covers:
- Architecture rules
- Security rules
- Testing requirements
- Anti-hallucination workflow
- Final response/reporting contract

### 2) Starter Layer (Boilerplate / Template Repos)

Create maintained template repositories:
- `corrad-template-laravel-api`
- `corrad-template-vue-admin`
- `corrad-template-fullstack-laravel-vue`

Each template should include:
- Auth + RBAC baseline
- Shared API envelope/error pattern
- Base modules (users/roles/settings/media)
- CI workflow and test setup
- AI guideline files pre-wired

### 3) Enforcement Layer (Automation + Governance)

Every project must enforce:
- CI gates (lint, test, build)
- Security checks
- Forbidden-pattern checks (custom scripts)
- PR template with evidence (tests run, assumptions, risks)
- Branch protection on `main` with required status checks
- Code review standard with severity-based findings and required approvals

No merge to protected branch if required checks fail.

---

## Standard Project Contract

Every repo must implement:

1. Architecture contract
- Defined folder structure
- Shared naming conventions
- Shared API response contract

2. Security contract
- Authentication/authorization pattern
- Validation and audit requirements
- Upload and secret handling policy

3. Quality contract
- Minimum test coverage for new endpoints/features
- Lint/type-check requirements
- Release checklist

4. AI usage contract
- Discover-first before code changes
- No guessed APIs/files/routes
- Must report commands actually run

---

## Bootstrap Workflow (New Project)

1. Create new repo from company template.
2. Set project metadata (`APP_NAME`, domain, environments).
3. Run setup scripts and baseline tests.
4. Enable protected branches and CI required checks.
5. Confirm AI guideline auto-detection at repo root:
   - `CLAUDE.md`
   - `AGENTS.md`
   - `.cursorrules`
6. Start feature delivery only after baseline passes.

---

## Rollout Plan

### Phase 1: Foundation
- Freeze and approve policy (`CLAUDE.md` as canonical)
- Build/clean template repos
- Add CI + PR templates to templates

### Phase 2: Pilot
- Apply framework to 1-2 active projects
- Track lead time, defect rate, review rework
- Fix friction in templates/rules

### Phase 3: Scale
- Make framework mandatory for all new projects
- Migrate existing projects during planned maintenance windows
- Publish versioned release notes for framework updates

---

## Governance Model

Roles:
- Framework Owner: approves policy/template changes
- Tech Leads: enforce adoption per project
- Project Maintainers: keep repo aligned with framework version

Change control:
- Framework changes via RFC + approval
- Version framework (e.g., `v1.0`, `v1.1`)
- Projects declare current framework version in repo docs

Review cadence:
- Monthly quality review (incidents, rule violations, CI failures)
- Quarterly framework update

---

## Minimum Metrics to Track

- PR cycle time
- Failed CI rate
- Escaped defects (post-release bugs)
- AI-generated change rollback rate
- % of repos on latest framework version

---

## Immediate Next Actions

1. Create a dedicated repo: `corrad-engineering-framework`.
2. Move company-wide policy files there as source of truth.
3. Publish first template repo (`fullstack-laravel-vue`) with CI gates.
4. Add a script/check to verify root AI files exist and are synced.
5. Pilot on one upcoming project before company-wide mandate.
6. Apply branch protection standard from `docs/process/github-branch-protection.md`.
7. Apply code review standard from `docs/process/code-review-standard.md`.
