import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/errors/api_exception.dart';
import '../../../models/person_summary.dart';
import '../../../providers/person_provider.dart';
import '../../../routing/app_router.dart';
import '../../../widgets/form_banner.dart';
import '../../../widgets/person_tile.dart';

/// Finding somebody in the archive.
///
/// Every screen that writes anything — edit, add a relative, add an event, a
/// photograph, a story, a dispute — hangs off a person's page, and until this
/// existed the only way to reach a first person was to have claimed a profile.
/// An account without one saw an empty tree and could contribute nothing, which
/// read as an app with no features rather than an app with no starting point.
class PersonSearchScreen extends ConsumerStatefulWidget {
  const PersonSearchScreen({super.key});

  @override
  ConsumerState<PersonSearchScreen> createState() => _PersonSearchScreenState();
}

class _PersonSearchScreenState extends ConsumerState<PersonSearchScreen> {
  final _controller = TextEditingController();
  final _focus = FocusNode();

  Timer? _debounce;
  List<PersonSummary> _results = const [];
  bool _searching = false;
  bool _searched = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    // Opening the keyboard is the whole point of arriving here.
    WidgetsBinding.instance.addPostFrameCallback((_) => _focus.requestFocus());
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _controller.dispose();
    _focus.dispose();
    super.dispose();
  }

  /// 400ms, matching the claim-profile search: long enough that typing a name
  /// is one request rather than eight, short enough not to feel stuck.
  void _onChanged(String value) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 400), () => _run(value));
  }

  Future<void> _run(String query) async {
    if (query.trim().length < 2) {
      setState(() {
        _results = const [];
        _searched = false;
        _error = null;
      });

      return;
    }

    setState(() {
      _searching = true;
      _error = null;
    });

    try {
      final people = await ref.read(personRepositoryProvider).search(query);

      if (mounted) {
        setState(() {
          _results = people;
          _searched = true;
        });
      }
    } on ApiException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _searching = false);
    }
  }

  void _open(PersonSummary person) {
    // A placeholder is a record this viewer may not see the identity of. It
    // occupies its position in a tree so the lineage around it stays honest,
    // but there is nothing to open.
    if (person.placeholder) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('That record is not one you can open.')),
      );

      return;
    }

    context.push(Routes.personPath(person.ulid));
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(title: const Text('Find someone')),
      body: SafeArea(
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 8, 20, 12),
              child: TextField(
                controller: _controller,
                focusNode: _focus,
                onChanged: _onChanged,
                textInputAction: TextInputAction.search,
                onSubmitted: _run,
                autocorrect: false,
                decoration: InputDecoration(
                  labelText: 'Name',
                  hintText: 'A given name or a family name',
                  prefixIcon: const Icon(Icons.search),
                  border: const OutlineInputBorder(),
                  suffixIcon: _controller.text.isEmpty
                      ? null
                      : IconButton(
                          tooltip: 'Clear',
                          icon: const Icon(Icons.clear),
                          onPressed: () {
                            _controller.clear();
                            _onChanged('');
                            setState(() {});
                          },
                        ),
                ),
              ),
            ),
            if (_error != null)
              Padding(
                padding: const EdgeInsets.fromLTRB(20, 0, 20, 12),
                child: FormBanner(
                  message: _error!,
                  tone: theme.colorScheme.error,
                  icon: Icons.error_outline,
                ),
              ),
            if (_searching) const LinearProgressIndicator(),
            Expanded(child: _body(theme)),
          ],
        ),
      ),
    );
  }

  Widget _body(ThemeData theme) {
    if (_results.isNotEmpty) {
      return ListView.builder(
        padding: const EdgeInsets.fromLTRB(20, 0, 20, 24),
        itemCount: _results.length,
        itemBuilder: (_, i) {
          final person = _results[i];

          return PersonTile(person: person, onTap: () => _open(person));
        },
      );
    }

    if (_searched && !_searching) {
      // Says why nothing matched, because the prefix rule is invisible and
      // surprising: somebody searching the middle of a name concludes the
      // record is not there rather than that they searched the wrong end of it.
      return _Message(
        icon: Icons.person_search_outlined,
        title: 'Nobody by that name',
        detail:
            'Names are matched from the beginning, so try the start of a '
            'given name or a family name. Only people you are allowed to see '
            'appear here.',
        theme: theme,
      );
    }

    return _Message(
      icon: Icons.search,
      title: 'Search the archive',
      detail: 'Type at least two letters of a given name or a family name.',
      theme: theme,
    );
  }
}

class _Message extends StatelessWidget {
  const _Message({
    required this.icon,
    required this.title,
    required this.detail,
    required this.theme,
  });

  final IconData icon;
  final String title;
  final String detail;
  final ThemeData theme;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 40),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 40, color: theme.colorScheme.onSurfaceVariant),
            const SizedBox(height: 16),
            Text(
              title,
              style: theme.textTheme.titleMedium,
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 8),
            Text(
              detail,
              textAlign: TextAlign.center,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
