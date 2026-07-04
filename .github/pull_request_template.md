# Pull Request

## Problem

What problem does this PR solve?

## Summary of Changes

- 

## Affected Public Behavior

Describe any user-visible behavior change. If none, write `None`.

## Changed Areas

Check every area changed by this PR:

- [ ] Source parsing
- [ ] Scanner confidence
- [ ] Policy decisions
- [ ] CLI output
- [ ] JSON schema
- [ ] Cache behavior
- [ ] Documentation/context only
- [ ] Other:

## False-Positive / False-Negative Considerations

Explain how the change avoids false confidence, silent false negatives, and noisy false positives.

## Performance / Cache Impact

Describe any indexing, scanning, reporting, or cache impact. If none, write `None`.

## JSON Contract Impact

Does this alter JSON keys, enum strings, diagnostics, or finding shape?

## Breaking Changes

- [ ] No breaking changes
- [ ] Breaking change included and explained below

Explanation:

## Tests Run

Paste the exact commands run:

```bash
git diff --check
vendor/bin/phpunit
```

Add targeted PHPUnit commands relevant to the changed area.

## Documentation / Context Updates

List README, context, release, or contributor-doc updates. If none were needed, explain why.
