#include "main.h"

#include "connection.hpp"
#include "webservice.hpp"

#include "FS.h"
#include "SPIFFS.h"
#include <stdint.h>

#define SSID "AndroidAP_9626"
#define WIFI_PASS "12345678"

unsigned long last_log_time = 0;
const unsigned long log_interval =
    30 * 1000; // get GPS position every 30 seconds

Location location;
Connection wifi(SSID, WIFI_PASS);
Webservice uploader("https://tracking.dev.jirweb.de/upload.php");
String device_uuid;

void setup() {
  Serial.begin(115200);
  location.begin();
  wifi.begin();

  device_uuid = wifi.get_MAC_address();

  if (!SPIFFS.begin(true)) {
    Serial.println("SPIFFS Mount Failed");
    return;
  }

  Serial.println("GPS Reader Initialized");
}

void loop() {
  if (location.update() && millis() - last_log_time > log_interval) {
    last_log_time = millis();

    GPX gpx = location.getData();
    if (gpx.longitude != 0 && gpx.latitude != 0 && gpx.altitude != 0 &&
        gpx.timestamp != "0") {
      logGPX(gpx);

      auto gps_time = location.getUnixTime();
      if (isConnectionWindow(gps_time)) {
        wifi.tryConnect(gps_time);
        if (wifi.connected()) {
          Serial.println("Wi-Fi connected");

          bool success = uploader.uploadFile(FILENAME);
          if (success) {
            if (SPIFFS.exists(FILENAME)) {
              if (!SPIFFS.remove(FILENAME)) {
                Serial.println("File uploaded. But could not be deleted from "
                               "local storage.");
              } else {
                Serial.println(
                    "File was uploaded and deleted from local storage.");
              }
            }
          } else {
            Serial.println("Upload failed.");
          }
        } else {
          Serial.println("Wi-Fi not connected");
        }
      } else {
        wifi.disconnect();
        Serial.println("no connection window");
      }
    }
  }
}

/// write gpx waypoint to file
void logGPX(GPX gpx) {
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
  }

  // Append waypoint
  File file = SPIFFS.open(FILENAME, FILE_APPEND);
  if (file) {
    file.printf("  <trkpt lat=\"%.6f\" lon=\"%.6f\">\n", gpx.latitude,
                gpx.longitude);
    file.printf("    <ele>%.2f</ele>\n", gpx.altitude);
    file.printf("    <time>%s</time>\n", gpx.timestamp.c_str());
    file.printf("    <sensor_nr>%s</sensor_nr>\n", device_uuid.c_str());
    file.println("  </trkpt>");
    file.close();
  }
}

bool isConnectionWindow(uint32_t epochSeconds) {
  uint8_t currentMinute = (epochSeconds / 60) % 60;

  for (int i = 0; i < NUM_WINDOWS; ++i) {
    uint8_t start = connection_windows[i];
    if (currentMinute >= start && currentMinute < start + 3) {
      return true;
    }
  }

  return false;
}