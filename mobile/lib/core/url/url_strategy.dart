/// Clean URLs on the web, nothing at all anywhere else.
///
/// `package:flutter_web_plugins` exists only in a web build, so it cannot be
/// imported directly from code that also compiles for a phone. The conditional
/// export picks the real implementation for web and a no-op for everything
/// else.
library;

export 'url_strategy_stub.dart'
    if (dart.library.js_interop) 'url_strategy_web.dart';
