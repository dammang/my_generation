---
paths:
  - 'mobile/lib/features/tree/layout/**'
---

# Layout

## The chart packs families, and the card height is measured
Placement is per-family, not per-row: each couple with everything descended from them is measured and given its own stretch of canvas. Rows cannot be packed independently — a wide branch pushes the next one right, the parent above is already placed and cannot follow, and a grandfather ends up six cards from his grandchildren. Couples are the unit that gets ordered, found through who is married to whom rather than who happens to be adjacent.

TreeMetrics measures the card's two text blocks with a TextPainter in the same styles at the same scale. Both line heights are pinned as constants the card and the metrics share: a `Text` merges its style onto the ambient DefaultTextStyle, so leaving one unset draws at one height and reserves at another. The name is Flexible, so a short reservation is absorbed silently by clipping the second line rather than throwing.
