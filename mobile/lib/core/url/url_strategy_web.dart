import 'package:flutter_web_plugins/url_strategy.dart';

/// Drops the `#` from web URLs.
///
/// Without this the address bar reads khanggui.com/#/person/01ABC. The ulid is
/// the public identifier precisely so a link to somebody can be shared, and a
/// fragment is the half of a URL that servers never see — which means the
/// server cannot answer for it, and neither can anything that unfurls a link.
///
/// It needs the server to answer /tree and /person/01ABC with the app shell,
/// because those paths have no file behind them. routes/web.php does.
void useCleanUrls() => usePathUrlStrategy();
