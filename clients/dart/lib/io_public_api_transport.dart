import 'dart:convert';
import 'dart:io';

import 'public_api.dart';

/// A dependency-free transport for Flutter mobile and desktop targets.
/// Flutter web should provide a browser-safe [PublicApiTransport] instead.
final class IoPublicApiTransport implements PublicApiTransport {
  IoPublicApiTransport({HttpClient? client}) : _client = client ?? HttpClient();

  final HttpClient _client;

  @override
  Future<PublicApiTransportResponse> get(
    Uri uri, {
    Map<String, String> headers = const <String, String>{},
  }) async {
    final request = await _client.getUrl(uri);
    headers.forEach(request.headers.set);

    return _send(request);
  }

  @override
  Future<PublicApiTransportResponse> post(
    Uri uri, {
    Map<String, String> headers = const <String, String>{},
    JsonMap body = const <String, Object?>{},
  }) async {
    final request = await _client.postUrl(uri);
    headers.forEach(request.headers.set);
    request.headers.contentType = ContentType.json;
    request.write(jsonEncode(body));

    return _send(request);
  }

  Future<PublicApiTransportResponse> _send(HttpClientRequest request) async {
    final response = await request.close();
    final responseText = await response.transform(utf8.decoder).join();
    final decoded = jsonDecode(responseText);

    if (decoded is! Map<String, Object?>) {
      throw const FormatException('The public API response must be a JSON object.');
    }

    return PublicApiTransportResponse(
      statusCode: response.statusCode,
      body: decoded,
    );
  }

  void close({bool force = false}) => _client.close(force: force);
}
