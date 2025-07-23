#include "main.h"

#include <HardwareSerial.h>
#include <TinyGPS++.h>
#include <WebServer.h>
#include <WiFi.h>
#include <stdint.h>

#include "FS.h"
#include "SPIFFS.h"

#define WIFI_SCAN_WINDOW 3            // Scan for 3 minutes
#define WIFI_CHECK_INTERVAL 15 * 1000 // Check every 15 seconds
#define SSID "AndroidAP_9626"
#define WIFI_PASS "12345678"

WebServer server(80);

TinyGPSPlus gps;
HardwareSerial GPS_Serial(2); // Use UART2 on ESP32

const int RXPin = 23; // GPS TX -> ESP32 RX
const int TXPin = 22; // GPS RX -> ESP32 TX
const uint32_t GPSBaud = 9600;

unsigned long lastPrintTime = 0;
const unsigned long printInterval = 15 * 1000; // 15 seconds

unsigned long lastWiFiCheck = 0;
bool wifiAttemptedThisHour = false;

void setup() {
  Serial.begin(115200);
  GPS_Serial.begin(GPSBaud, SERIAL_8N1, RXPin, TXPin);

  if (!SPIFFS.begin(true)) {
    Serial.println("SPIFFS Mount Failed");
    return;
  }
  connectWiFi();
  setupWebServer();

  Serial.println("GPS Reader Initialized");

  server.onNotFound([]() { server.send(404, "text/plain", "Not found"); });
  server.on("/favicon.ico", HTTP_GET, []() {
    server.send(204); // No Content
  });

  // Write header only if file does not exist
  if (!SPIFFS.exists(FILENAME)) {
    File file = SPIFFS.open(FILENAME, FILE_WRITE);
    if (file) {
      file.println("<?xml version=\"1.0\" encoding=\"UTF-8\"?>");
      file.println("<gpx version=\"1.1\" creator=\"ESP32 GPS Logger\"");
      file.println(" xmlns=\"http://www.topografix.com/GPX/1/1\"");
      file.println(" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\"");
      file.println(" xsi:schemaLocation=\"http://www.topografix.com/GPX/1/1");
      file.println(" http://www.topografix.com/GPX/1/1/gpx.xsd\">");
      file.println("<trk><name>ESP32 GPS Track</name><trkseg>");
      file.close();
      Serial.println("GPX header written");
    } else {
      Serial.println("Failed to create GPX file");
    }
  } else {
    Serial.println("GPX file exists, resuming logging...");
  }
}

void loop() {
  while (GPS_Serial.available() > 0) {
    gps.encode(GPS_Serial.read());
  }

  if (millis() - lastPrintTime > printInterval) {
    lastPrintTime = millis();

    if (gps.location.isValid()) {
      double lat = gps.location.lat();
      double lng = gps.location.lng();
      double alt = gps.altitude.meters();

      Serial.printf("Lat: %.6f, Lng: %.6f, Alt: %.2f\n", lat, lng, alt);

      logGPX(lat, lng, alt);
    } else {
      Serial.println("Waiting for valid GPS data...");
    }
  }

  server.handleClient();
}

/// write gpx waypoint to file
void logGPX(double lat, double lng, double alt) {
  File file;

  // Append waypoint
  file = SPIFFS.open(FILENAME, FILE_APPEND);
  if (file) {
    file.printf("  <trkpt lat=\"%.6f\" lon=\"%.6f\">\n", lat, lng);
    file.printf("    <ele>%.2f</ele>\n", alt);
    file.printf("    <time>%s</time>\n", gpsTimeISO8601().c_str());
    file.println("  </trkpt>");
    file.close();
  }
}

/// convert time to ISO8601 format
String gpsTimeISO8601() {
  if (gps.date.isValid() && gps.time.isValid()) {
    char buf[25];
    snprintf(buf, sizeof(buf), "%04d-%02d-%02dT%02d:%02d:%02dZ",
             gps.date.year(), gps.date.month(), gps.date.day(), gps.time.hour(),
             gps.time.minute(), gps.time.second());
    return String(buf);
  }
  return "1970-01-01T00:00:00Z";
}

void connectWiFi() {
  WiFi.begin(SSID, WIFI_PASS);
  Serial.print("Connecting to WiFi");
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.print("\nConnected! IP Address: ");
  Serial.println(WiFi.localIP());
}

void setupWebServer() {
  server.on("/", HTTP_GET, []() {
    String html = "<h1>ESP32 GPX Logger</h1>";
    html += "<a href='/download' download>Download GPX File</a><br><br>";
    html += "<form action='/delete' method='POST'>";
    html += "<input type='submit' value='Delete GPX File'>";
    html += "</form>";
    server.send(200, "text/html", html);
  });

  server.on("/download", HTTP_GET, []() {
    const char *sourceFile = FILENAME;
    const char *downloadFile = "/track_download.gpx"; // temporary file

    // Check if main file exists
    if (!SPIFFS.exists(sourceFile)) {
      server.send(404, "text/plain", "GPX file not found.");
      return;
    }

    // Copy original file
    File src = SPIFFS.open(sourceFile, FILE_READ);
    File dst = SPIFFS.open(downloadFile, FILE_WRITE);
    if (!src || !dst) {
      server.send(500, "text/plain", "Failed to prepare download.");
      return;
    }

    uint8_t buffer[128];
    while (src.available()) {
      size_t len = src.read(buffer, sizeof(buffer));
      dst.write(buffer, len);
    }
    src.close();

    // Append footer
    dst.println("</trkseg></trk></gpx>");
    dst.close();

    // Serve the copied file
    File file = SPIFFS.open(downloadFile, FILE_READ);
    if (!file || file.size() == 0) {
      server.send(500, "text/plain", "Failed to read prepared file.");
      return;
    }

    server.setContentLength(file.size());
    server.sendHeader("Content-Type", "application/gpx+xml");
    server.sendHeader("Content-Disposition",
                      "attachment; filename=\"track.gpx\"");
    server.sendHeader("Cache-Control", "no-cache");
    server.send(200);

    // Stream file to client
    WiFiClient client = server.client();
    uint8_t fileBuffer[128];
    while (file.available()) {
      size_t len = file.read(fileBuffer, sizeof(fileBuffer));
      if (client.connected()) {
        client.write(fileBuffer, len);
      } else {
        Serial.println("Client disconnected before file fully sent.");
        break;
      }
    }
    file.close();

    // remove the temp file
    SPIFFS.remove(downloadFile);
  });

  server.on("/delete", HTTP_POST, []() {
    if (SPIFFS.exists(FILENAME)) {
      if (SPIFFS.remove(FILENAME)) {
        server.sendHeader("Location", "/");
        server.send(303); // 303 See Other (used for POST redirect)
        Serial.println("Deleted /track.gpx");
      } else {
        server.send(500, "text/plain", "Failed to delete GPX file.");
      }
    } else {
      server.send(404, "text/plain", "File not found.");
    }
  });

  server.begin();
}