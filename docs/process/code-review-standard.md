# Code Review Standard

Use this standard for every pull request across company projects.

## Review Objectives

1. Prevent defects and regressions.
2. Enforce architecture/security standards.
3. Verify tests and operational safety.
4. Keep changes understandable and maintainable.

## Severity Model

- `Blocker`: must fix before merge (security/data loss/broken behavior)
- `High`: likely production issue; fix before merge
- `Medium`: non-critical but meaningful quality risk; fix or explicitly accept
- `Low`: polish/readability/maintainability improvement

## Reviewer Checklist

1. Correctness
- Behavior matches requirement
- Edge cases considered
- No hidden breaking changes

2. Security
- AuthN/AuthZ enforced
- Input validation present
- Sensitive data handling is safe

3. Data and API Safety
- Migration impact is safe/reversible where possible
- API contract changes are documented
- Backward compatibility considered

4. Testing and Validation
- Relevant tests added/updated
- Commands in PR were actually run
- Failure paths covered

5. Maintainability
- No obvious duplication
- Clear naming and boundaries
- Complexity justified

## Required PR Evidence

PR description must include:
- Summary of change
- Changed files and impact
- Validation commands + pass/fail results
- Assumptions and known risks

## Review Process

1. Author opens PR with required evidence.
2. Reviewer leaves findings grouped by severity.
3. Author resolves findings or documents accepted risk.
4. Reviewer re-checks and approves.
5. Merge only after required checks and approvals pass.

## Non-Negotiable Rules

- No approval if blocker/high findings remain unresolved.
- No "looks good" approval without reading changed files.
- No merge with failing required checks.
- No fabricated test output in PR comments/descriptions.
