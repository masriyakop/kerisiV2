# GitHub Branch Protection Standard (Main)

Apply these settings to `main` in every company repo.

## Required Settings

1. Protect branch: `main`
2. Require a pull request before merging
3. Require approvals: minimum 1
4. Dismiss stale approvals when new commits are pushed
5. Require status checks to pass before merging
6. Required checks:
   - `AI Guidelines Sync / check-ai-guidelines`
   - `PR Governance / validate-pr-body`
   - Existing project CI checks (test/lint/build) as applicable
7. Require branches to be up to date before merging
8. Restrict direct pushes to `main` (admins excepted by policy)

## Optional Recommended Settings

1. Require conversation resolution before merging
2. Require signed commits (if your org policy uses signing)
3. Lock branch after release (for maintenance-only repositories)

## Setup Steps (GitHub UI)

1. Open repository -> `Settings` -> `Branches`
2. Click `Add branch protection rule`
3. Branch name pattern: `main`
4. Enable the required settings above
5. Under status checks, select:
   - `AI Guidelines Sync / check-ai-guidelines`
   - `PR Governance / validate-pr-body`
6. Save changes

## Verification

1. Open a test PR with intentionally unsynced guideline files.
2. Confirm `AI Guidelines Sync / check-ai-guidelines` fails.
3. Run `composer ai:sync`, push again, and confirm check passes.
4. Confirm direct push to `main` is blocked for non-exempt users.
