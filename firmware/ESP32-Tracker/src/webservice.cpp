#include "webservice.hpp"

#include <Arduino.h>
#include <HTTPClient.h>
#include <SPIFFS.h>
#include <WiFiClient.h>
#include <WiFiClientSecure.h>

const String gpxFooter = "</trkseg></trk></gpx>";

Webservice::Webservice(const String &url, const String &fieldName)
    : uploadURL(url), formField(fieldName) {}

struct ParsedURL {
  String scheme;
  String host;
  int port;
  String path;
};

ParsedURL parseURL(const String &url) {
  ParsedURL result;
  int schemeEnd = url.indexOf("://");
  if (schemeEnd < 0)
    return result;

  result.scheme = url.substring(0, schemeEnd);
  int hostStart = schemeEnd + 3;
  int pathStart = url.indexOf('/', hostStart);
  int portStart = url.indexOf(':', hostStart);

  // Path
  result.path = (pathStart > 0) ? url.substring(pathStart) : "/";

  // Host and optional port
  if (portStart > 0 && portStart < pathStart) {
    result.host = url.substring(hostStart, portStart);
    result.port = url.substring(portStart + 1, pathStart).toInt();
  } else {
    result.host = url.substring(hostStart, pathStart);
    result.port = 0; // to be filled later
  }

  return result;
}

bool Webservice::uploadFile(const String &filePath) {
  if (!SPIFFS.exists(filePath)) {
    Serial.println("[Webservice] File not found: " + filePath);
    return false;
  }

  File file = SPIFFS.open(filePath, "r");
  if (!file || file.isDirectory()) {
    Serial.println("[Webservice] Failed to open file: " + filePath);
    return false;
  }

  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("[Webservice] Not connected to WiFi");
    file.close();
    return false;
  }

  // Manually parse URL.
  ParsedURL parsed = parseURL(uploadURL);

  String scheme = parsed.scheme;
  String host = parsed.host;
  String path = parsed.path;
  int port = parsed.port;
  bool secure = (scheme == "https");

  if (port == 0) {
    port = secure ? 443 : 80;
  }

  // Boundary and multipart parts
  String boundary = "----WebKitFormBoundary7MA4YWxkTrZu0gW";
  String contentType = "multipart/form-data; boundary=" + boundary;

  String head = "--" + boundary + "\r\n";
  head += "Content-Disposition: form-data; name=\"" + formField +
          "\"; filename=\"" + file.name() + "\"\r\n";
  head += "Content-Type: " + getContentType(filePath) + "\r\n\r\n";

  String tail = "\r\n--" + boundary + "--\r\n";

  int contentLength =
      head.length() + file.size() + gpxFooter.length() + tail.length();

  // Connect to server
  WiFiClient *rawClient;
  WiFiClient client;
  WiFiClientSecure clientSecure;

  if (secure) {
    clientSecure.setInsecure(); // no certificate validation
    rawClient = &clientSecure;
  } else {
    rawClient = &client;
  }

  if (!rawClient->connect(host.c_str(), port)) {
    Serial.println("[Webservice] Failed to connect to host");
    file.close();
    return false;
  }

  // Send HTTP POST headers
  rawClient->printf("POST %s HTTP/1.1\r\n", path.c_str());
  rawClient->printf("Host: %s\r\n", host.c_str());
  rawClient->println("Connection: close");
  rawClient->println("User-Agent: ESP32");
  rawClient->printf("Content-Type: %s\r\n", contentType.c_str());
  rawClient->printf("Content-Length: %d\r\n", contentLength);
  rawClient->println(); // End of headers

  // Send body
  rawClient->print(head);
  uint8_t buf[512];
  while (file.available()) {
    size_t len = file.read(buf, sizeof(buf));
    rawClient->write(buf, len);
  }
  rawClient->print(gpxFooter);
  rawClient->print(tail);
  file.close();

  // Wait and read response
  while (rawClient->connected()) {
    String line = rawClient->readStringUntil('\n');
    if (line == "\r")
      break; // End of headers
  }

  String response = rawClient->readString();
  Serial.println("[Webservice] Server response: ");
  Serial.println(response);

  return response.indexOf("200 OK") >= 0 ||
         response.indexOf("201 Created") >= 0 ||
         response.indexOf("File uploaded and parsed successfully") != -1;
}

String Webservice::getContentType(const String &filename) {
  if (filename.endsWith(".gpx"))
    return "application/gpx+xml";
  if (filename.endsWith(".txt"))
    return "text/plain";
  return "application/octet-stream";
}
