---
paths:
  - 'mobile/**'
---

# Mobile

## Format only the files you touched
`dart format lib test` reformats ~60 unrelated files, buries the real diff, and reflows single-line `if`s into two lines, which then trips `curly_braces_in_flow_control_structures`. Pass the specific paths you edited. If you do run it wide, `git checkout --` everything outside your change before committing.
