import 'dart:math' as math;
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';

/// Renders a widget that is not on screen, at whatever size it needs.
///
/// The chart cannot be captured from the screen: what is on screen is a
/// culled, panned, zoomed window onto it, and an export of that would be an
/// export of somebody's scroll position. So the whole thing is built again in
/// a pipeline of its own — its own view, layout and paint — at the size the
/// layout engine says the family actually occupies.
Future<ui.Image> renderOffscreen({
  required Widget widget,
  required Size size,
  required double pixelRatio,
  required ui.FlutterView view,
}) async {
  final boundary = RenderRepaintBoundary();

  final renderView = RenderView(
    view: view,
    child: RenderPositionedBox(child: boundary),
    configuration: ViewConfiguration(
      logicalConstraints: BoxConstraints.tight(size),
      physicalConstraints: BoxConstraints.tight(size * pixelRatio),
      devicePixelRatio: pixelRatio,
    ),
  );

  final pipeline = PipelineOwner()..rootNode = renderView;
  renderView.prepareInitialFrame();

  final buildOwner = BuildOwner(focusManager: FocusManager());

  final element = RenderObjectToWidgetAdapter<RenderBox>(
    container: boundary,
    child: Directionality(textDirection: TextDirection.ltr, child: widget),
  ).attachToRenderTree(buildOwner);

  buildOwner
    ..buildScope(element)
    ..finalizeTree();

  pipeline
    ..flushLayout()
    ..flushCompositingBits()
    ..flushPaint();

  final image = await boundary.toImage(pixelRatio: pixelRatio);

  // The pipeline is ours alone, so it has to be taken down by hand or its
  // render objects stay alive holding the whole chart's layout.
  RenderObjectToWidgetAdapter<RenderBox>(
    container: boundary,
  ).attachToRenderTree(buildOwner, element);

  buildOwner.finalizeTree();
  pipeline.rootNode = null;

  return image;
}

/// How large to render, and at what magnification.
///
/// "Up to 6K" is about the longest edge, but a family tree is not a photograph:
/// a long thin one can be six thousand wide and twelve thousand tall, which is
/// four hundred megabytes of pixels before anything is encoded. So there are
/// two ceilings and the stricter one wins.
class ExportScale {
  const ExportScale._(this.pixelRatio, this.pixels);

  final double pixelRatio;
  final Size pixels;

  /// The longest edge of a 6K export.
  static const double maxEdge = 6144;

  /// Roughly 24 megapixels. Beyond this a phone runs out of memory partway
  /// through the encode, which looks to somebody holding it like a crash.
  static const double maxPixels = 24000000;

  /// Sharper than the screen where the chart is small enough to allow it.
  /// A tree of six people should not be exported at postage-stamp size just
  /// because it happens to fit.
  static const double maxRatio = 4;

  factory ExportScale.forCanvas(Size canvas) {
    final width = canvas.width.clamp(1.0, double.infinity);
    final height = canvas.height.clamp(1.0, double.infinity);

    final byEdge = maxEdge / (width > height ? width : height);
    final byArea = math.sqrt(maxPixels / (width * height));

    final ratio = [maxRatio, byEdge, byArea].reduce(math.min);

    return ExportScale._(
      ratio,
      Size((width * ratio).floorToDouble(), (height * ratio).floorToDouble()),
    );
  }
}
