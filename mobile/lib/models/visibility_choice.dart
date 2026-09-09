/// Who may see a person's record.
///
/// Four of the archive's five levels. "Anyone in the tribe" exists on the
/// server but is not offered here: it is a rung most people cannot tell apart
/// from the clan, and every option somebody can get wrong is a privacy setting
/// set wrong.
enum VisibilityChoice {
  public('public', 'Anyone', 'Visible to everybody, signed in or not'),
  clan('clan', 'My clan', 'Members of the clan can see me'),
  family(
    'family',
    'Close family',
    'Cousins and nearer — everybody else sees a blank card',
  ),
  onlyMe('private', 'Only me', 'Nobody else sees my name or dates');

  const VisibilityChoice(this.wire, this.label, this.describe);

  /// What the server calls it.
  final String wire;
  final String label;
  final String describe;

  static VisibilityChoice? fromWire(String? value) {
    for (final choice in VisibilityChoice.values) {
      if (choice.wire == value) return choice;
    }

    // 'tribe', or a level added later. Shown as nothing selected rather than
    // guessed at: quietly displaying the wrong answer to "who can see me" is
    // worse than displaying none.
    return null;
  }
}
