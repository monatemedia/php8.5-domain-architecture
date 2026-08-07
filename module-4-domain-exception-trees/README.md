# Module 4 — Domain Exception Trees
> Not built yet.

| Lesson | Subject |
|--------|---------|
| 4.1 | Infrastructure failures vs domain failures, and why APIs return 500 for user error |
| 4.2 | Hierarchical exception design — catch broadly at the boundary, throw narrowly in the core |
| 4.3 | Exceptions vs result types — when a failure is not exceptional |
| 4.4 | Mapping to the API boundary — RFC 9457 Problem Details |

The `\InvalidArgumentException` / `\DomainException` split you have been using since Lesson 1.1 is
the seed of the whole tree.
