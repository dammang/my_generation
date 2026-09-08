/// The typographic characters this app writes, in characters a PDF's built-in
/// font can actually draw.
///
/// The font has no em dash and no arrow. It does not fail on one — it drops the
/// glyph and leaves a hole, so a line prints short and the document looks
/// correct to the code that wrote it. Everything an export composes goes
/// through here.
///
/// A name in a script the font does not cover is a different problem, and the
/// picture exports are the answer to it.
String plainForPdf(String text) => text
    .replaceAll('—', '-')
    .replaceAll('–', '-')
    .replaceAll('←', '<-')
    .replaceAll('·', '.')
    .replaceAll('‘', "'")
    .replaceAll('’', "'")
    .replaceAll('“', '"')
    .replaceAll('”', '"')
    .replaceAll('…', '...');
