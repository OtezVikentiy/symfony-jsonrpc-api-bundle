<!--
One pull request, one logical change. Refactoring mixed with a change in
behaviour is harder to review and harder to revert.
-->

## What changed and why

<!-- The reason, not only the fact. Which invariant was wrong, what it cost. -->

## How it was verified

<!-- Which test notices the regression if someone undoes this. If you changed a
     guard, say whether you confirmed the test reddens with the guard removed. -->

## Checklist

- [ ] Tests pass locally (`composer ci`)
- [ ] A test covers the change — for a behaviour change, one that fails without it
- [ ] Documentation in `docs/` updated, or not applicable
- [ ] `CHANGELOG.md` and `CHANGELOG.ru.md` entries added, or not applicable
- [ ] This changes observable behaviour (a BC break) — if so, `docs/upgrade-X.0.md` says what breaks and what to do
