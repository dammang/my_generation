import 'package:firebase_crashlytics/firebase_crashlytics.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/errors/api_exception.dart';
import '../../../core/ulid.dart';
import '../../../providers/person_provider.dart';
import '../../../providers/sync_provider.dart';
import '../../../repositories/person_repository.dart';
import '../../../widgets/form_banner.dart';

/// A union the contributor must choose between.
class _UnionChoice {
  const _UnionChoice({required this.ulid, required this.label});

  final String ulid;
  final String label;
}

/// Adding somebody to the family.
///
/// The relation is chosen first because it determines everything else: what the
/// server will write, which fields matter, and whether a marriage has to be
/// picked. Asking for a name first and the relationship last is how you end up
/// with a person floating unattached in the graph.
class AddRelativeScreen extends ConsumerStatefulWidget {
  const AddRelativeScreen({
    super.key,
    required this.anchorUlid,
    required this.anchorName,
    this.initialRelation = 'child',
  });

  final String anchorUlid;
  final String anchorName;
  final String initialRelation;

  @override
  ConsumerState<AddRelativeScreen> createState() => _AddRelativeScreenState();
}

class _AddRelativeScreenState extends ConsumerState<AddRelativeScreen> {
  static const _relations = <String, String>{
    'father': 'Father',
    'mother': 'Mother',
    'parent': 'Parent',
    'spouse': 'Spouse',
    'son': 'Son',
    'daughter': 'Daughter',
    'child': 'Child',
    'brother': 'Brother',
    'sister': 'Sister',
    'sibling': 'Sibling',
    'guardian': 'Guardian',
  };

  /// The link as recorded, which is not always biological. Offering this at the
  /// point of writing is the only way adoption gets captured — asked later, it
  /// never is.
  static const _subtypes = <String, String>{
    'biological': 'Biological',
    'adoptive': 'Adopted',
    'step': 'Step',
    'foster': 'Foster',
  };

  final _formKey = GlobalKey<FormState>();
  final _firstName = TextEditingController();
  final _lastName = TextEditingController();
  final _nativeName = TextEditingController();
  final _birth = TextEditingController();
  final _death = TextEditingController();

  /// They have died and nobody knows when — the commonest thing anybody can
  /// say about a person being added from memory.
  bool _deceased = false;

  late String _relation = widget.initialRelation;
  String _gender = 'unknown';
  String _subtype = 'biological';

  bool _saving = false;
  String? _error;

  /// Set when the server refused because the anchor has more than one marriage.
  List<_UnionChoice> _unionChoices = const [];
  String? _unionUlid;

  @override
  void dispose() {
    _firstName.dispose();
    _lastName.dispose();
    _nativeName.dispose();
    _birth.dispose();
    _death.dispose();
    super.dispose();
  }

  bool get _isParentOrChild => const {
    'father',
    'mother',
    'parent',
    'son',
    'daughter',
    'child',
  }.contains(_relation);

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;

    setState(() {
      _saving = true;
      _error = null;
    });

    try {
      final result = await ref
          .read(personRepositoryProvider)
          .addRelative(
            anchorUlid: widget.anchorUlid,
            relation: _relation,
            unionUlid: _unionUlid,
            subtype: _isParentOrChild ? _subtype : null,
            person: _personPayload(),
          );

      // The profile, the family lists and the tree are all now stale.
      invalidatePerson(ref, widget.anchorUlid, alsoUlid: result.person?.ulid);

      if (mounted) Navigator.of(context).pop(result);
    } on ApiException catch (error) {
      // No connection is not a failure — it is a later. The write is kept and
      // sent when the server can be reached, which is the whole point of
      // filling this in on a bus.
      if (error.isOffline) {
        await ref
            .read(syncControllerProvider.notifier)
            .enqueue(
              kind: 'add_relative',
              subjectUlid: widget.anchorUlid,
              subjectLabel: widget.anchorName,
              payload: {
                'anchor_ulid': widget.anchorUlid,
                'relation': _relation,
                'subtype': _isParentOrChild ? _subtype : null,
                'person': _personPayload(),
              },
            );

        if (mounted) {
          Navigator.of(context).pop(const AddRelativeResult.queued());
        }

        return;
      }

      // Not a failure to report and forget: the server is telling the
      // contributor which marriage it needs, and it sent the options as data.
      if (error.code == 'UNION_AMBIGUOUS') {
        setState(() {
          _unionChoices = _choicesFrom(error);
          _error = error.message;
        });
        return;
      }

      setState(() => _error = error.message);
    } catch (error, stack) {
      // Anything that is not an ApiException — a response shaped differently
      // from what the client expects, a null where one was not anticipated.
      //
      // This clause is why the form used to freeze: without it the exception
      // escaped _submit entirely, _saving stayed true, and every control on
      // the screen stayed disabled behind a spinner that would never stop. The
      // write had usually SUCCEEDED, so the contributor was told nothing had
      // happened while the server had already recorded it, and adding it again
      // was the natural next thing to try.
      // Reported, not swallowed: this branch existing at all means the client
      // and the API disagree about something, and the only way anybody finds
      // out is a report from the phone it happened on.
      //
      // Wrapped because FirebaseCrashlytics.instance throws outright without
      // Firebase.initializeApp(), and failing to report an error must never
      // become a second error on top of the first.
      try {
        FirebaseCrashlytics.instance.recordError(
          error,
          stack,
          reason: 'add_relative failed outside ApiException',
        );
      } catch (_) {
        debugPrint('add_relative failed: $error');
      }

      if (mounted) {
        setState(
          () => _error =
              'Something went wrong saving that. It may have been recorded '
              'anyway — check the family list before adding them again.',
        );
      }
    } finally {
      // The one place _saving is cleared, so no future branch can forget to.
      // Guarded because the success path pops this screen first.
      if (mounted) setState(() => _saving = false);
    }
  }

  List<_UnionChoice> _choicesFrom(ApiException error) {
    final raw = (error.meta['choices'] as List?) ?? const [];

    return raw
        .whereType<Map>()
        .map(
          (choice) => _UnionChoice(
            ulid: choice['ulid'] as String,
            label: choice['label'] as String? ?? 'Marriage',
          ),
        )
        .toList(growable: false);
  }

  /// Built in one place so the request sent now and the one queued for later
  /// can never disagree about what was typed.
  Map<String, dynamic> _personPayload() => {
    // Minted here so a person created offline is referable before the
    // server has ever seen them — an event about a grandfather added on a
    // plane needs to be able to name him.
    'ulid': _newUlid(),
    'first_name': _firstName.text.trim(),
    'last_name': ?_nullIfBlank(_lastName.text),
    'native_name': ?_nullIfBlank(_nativeName.text),
    'gender': _gender,
    'birth': ?_nullIfBlank(_birth.text),
    'death': ?_nullIfBlank(_death.text),
    // Only when it is the only thing being said about the death. A year in
    // the field above already records it, and sending both would restate a
    // claim the date makes better.
    if (_deceased && _nullIfBlank(_death.text) == null)
      'deceased_declared': true,
  };

  String? _pendingUlid;

  /// Stable across a retry: submitting twice must not mint two identities for
  /// the same person.
  String _newUlid() => _pendingUlid ??= Ulid.generate();

  static String? _nullIfBlank(String value) =>
      value.trim().isEmpty ? null : value.trim();

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(title: Text('Add to ${widget.anchorName}')),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 40),
          children: [
            if (_error != null)
              Padding(
                padding: const EdgeInsets.only(bottom: 16),
                child: FormBanner(
                  message: _error!,
                  tone: theme.colorScheme.error,
                  icon: Icons.error_outline,
                ),
              ),
            Text('Relationship', style: theme.textTheme.labelLarge),
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                for (final entry in _relations.entries)
                  ChoiceChip(
                    label: Text(entry.value),
                    selected: _relation == entry.key,
                    onSelected: _saving
                        ? null
                        : (_) => setState(() {
                            _relation = entry.key;
                            // A different relation may not need the marriage
                            // the last one did.
                            _unionChoices = const [];
                            _unionUlid = null;
                          }),
                  ),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              _relationHint,
              style: theme.textTheme.bodySmall?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
            if (_unionChoices.isNotEmpty) ...[
              const SizedBox(height: 20),
              Text('Which marriage?', style: theme.textTheme.labelLarge),
              const SizedBox(height: 4),
              Text(
                '${widget.anchorName} has more than one. Choosing wrongly gives '
                'the child the wrong mother, so the app will not guess.',
                style: theme.textTheme.bodySmall?.copyWith(
                  color: theme.colorScheme.onSurfaceVariant,
                ),
              ),
              const SizedBox(height: 8),
              RadioGroup<String>(
                groupValue: _unionUlid,
                onChanged: (value) {
                  if (_saving) return;
                  setState(() => _unionUlid = value);
                },
                child: Column(
                  children: [
                    for (final choice in _unionChoices)
                      RadioListTile<String>(
                        value: choice.ulid,
                        title: Text(choice.label),
                        contentPadding: EdgeInsets.zero,
                      ),
                  ],
                ),
              ),
            ],
            const SizedBox(height: 20),
            TextFormField(
              controller: _firstName,
              textCapitalization: TextCapitalization.words,
              decoration: const InputDecoration(labelText: 'First name'),
              validator: (value) => (value?.trim().isEmpty ?? true)
                  ? 'A first name is needed'
                  : null,
            ),
            const SizedBox(height: 14),
            TextFormField(
              controller: _lastName,
              textCapitalization: TextCapitalization.words,
              decoration: const InputDecoration(
                labelText: 'Last name (optional)',
              ),
            ),
            const SizedBox(height: 14),
            TextFormField(
              controller: _nativeName,
              decoration: const InputDecoration(
                labelText: 'Name in your own script (optional)',
                helperText: 'Recorded exactly as written, never transliterated',
              ),
            ),
            const SizedBox(height: 20),
            Text('Gender', style: theme.textTheme.labelLarge),
            const SizedBox(height: 8),
            SegmentedButton<String>(
              segments: const [
                ButtonSegment(value: 'male', label: Text('Male')),
                ButtonSegment(value: 'female', label: Text('Female')),
                ButtonSegment(value: 'unknown', label: Text('Unknown')),
              ],
              selected: {_gender},
              onSelectionChanged: _saving
                  ? null
                  : (values) => setState(() => _gender = values.first),
            ),
            const SizedBox(height: 20),
            TextFormField(
              controller: _birth,
              decoration: const InputDecoration(
                labelText: 'Born (optional)',
                // Free text on purpose. A date picker cannot express "about
                // 1902", and forcing a precise date invents one.
                helperText: 'e.g. 1902, abt. 1902, before 1910, spring 1948',
              ),
            ),
            const SizedBox(height: 14),
            TextFormField(
              controller: _death,
              onChanged: (_) => setState(() {}),
              decoration: const InputDecoration(
                labelText: 'Died (optional)',
                helperText: 'Leave blank if you know the year',
              ),
            ),
            // Most people added from memory have died and nobody remembers
            // when, so the field above asks for a year that does not exist.
            // Hidden once a year is given, because then it has been said
            // better and a switch that changes nothing teaches people that
            // switches do nothing.
            if (_nullIfBlank(_death.text) == null)
              SwitchListTile(
                value: _deceased,
                onChanged: _saving
                    ? null
                    : (value) => setState(() => _deceased = value),
                contentPadding: EdgeInsets.zero,
                title: const Text('This person has died'),
                subtitle: const Text(
                  'For when the family knows, and nobody remembers the year.',
                ),
              ),
            if (_isParentOrChild) ...[
              const SizedBox(height: 20),
              Text('How are they related?', style: theme.textTheme.labelLarge),
              const SizedBox(height: 8),
              Wrap(
                spacing: 8,
                children: [
                  for (final entry in _subtypes.entries)
                    ChoiceChip(
                      label: Text(entry.value),
                      selected: _subtype == entry.key,
                      onSelected: _saving
                          ? null
                          : (_) => setState(() => _subtype = entry.key),
                    ),
                ],
              ),
            ],
            const SizedBox(height: 28),
            FilledButton(
              onPressed: _saving ? null : _submit,
              child: _saving
                  ? const SizedBox(
                      height: 18,
                      width: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Text('Add to the family'),
            ),
          ],
        ),
      ),
    );
  }

  String get _relationHint => switch (_relation) {
    'father' ||
    'mother' ||
    'parent' => 'They will be recorded as a parent of ${widget.anchorName}.',
    'spouse' => 'A marriage will be recorded between them.',
    'son' ||
    'daughter' ||
    'child' => 'They will be recorded as a child of ${widget.anchorName}.',
    'brother' || 'sister' || 'sibling' =>
      'Siblings normally come from shared parents. Recorded directly here '
          'when the parents are unknown.',
    'guardian' => 'A guardian, without claiming a blood relationship.',
    _ => '',
  };
}

/// Shows what the server said after a successful write.
///
/// Warnings arrive with a 200 and are easy to drop on the floor. A contributor
/// who has just recorded a child born after the father's death should be told,
/// once, without the write being refused.
void showAddRelativeOutcome(
  BuildContext context, {
  required AddRelativeResult result,
}) {
  final messenger = ScaffoldMessenger.of(context);

  if (result.queued) {
    // Not "added". The server has never seen this, and saying otherwise is how
    // somebody finds out a week later that it never arrived.
    messenger.showSnackBar(
      const SnackBar(
        content: Text(
          'Saved on this device. It will be sent when you are back online.',
        ),
        duration: Duration(seconds: 4),
      ),
    );

    return;
  }

  final person = result.person!;
  final created = result.created;
  final warnings = result.warnings;

  if (warnings.isEmpty) {
    messenger.showSnackBar(
      SnackBar(
        content: Text(
          created
              ? '${person.displayName} was added.'
              : '${person.displayName} was already on record and has been linked.',
        ),
      ),
    );
    return;
  }

  showDialog<void>(
    context: context,
    builder: (context) => AlertDialog(
      title: Text('${person.displayName} was added'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Saved, with something worth checking:'),
          const SizedBox(height: 12),
          for (final warning in warnings)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Icon(Icons.info_outline, size: 18),
                  const SizedBox(width: 8),
                  Expanded(child: Text(warning.message)),
                ],
              ),
            ),
        ],
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(context).pop(),
          child: const Text('Got it'),
        ),
      ],
    ),
  );
}
