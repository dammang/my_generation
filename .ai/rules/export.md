---
paths:
  - 'mobile/lib/features/tree/export/**'
---

# Export

## Every string entering a PDF goes through plainForPdf
The `pdf` package's built-in Helvetica covers Latin-1 only, and it does not fail on a character it lacks — it drops the glyph and leaves a hole, so a name prints short and the export reports success. Route all PDF text through `plainForPdf()` (pdf_text.dart), which maps the typographic characters this app writes (— – ← · ' " …) to ones the font has.

Assert on it in tests by capturing `print` in a `runZoned` and expecting no "Unable to find a font" line; the hole itself is invisible from the bytes. "Helvetica has no Unicode support" is a blanket notice printed even for pure ASCII — ignore that one.

Non-Latin script (e.g. Burmese) still cannot appear in a PDF; the picture exports render it. Fixing that means shipping a Unicode TTF asset.

Caption text for both chart exports lives in ChartCaption — do not write it a second time.
