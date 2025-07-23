#include "location.hpp"

#include <HardwareSerial.h> // for ESP32 hardware serial
#include <TinyGPSPlus.h>

static HardwareSerial gpsSerial(1); // use Serial1 (custom serial port)
static TinyGPSPlus gps;

Location::Location() : hasFix(false) {}

void Location::begin() { gpsSerial.begin(BAUD, SERIAL_8N1, RX_PIN, TX_PIN); }

bool Location::update() {
  currentLocation = {0, 0, 0, "0"};
  while (gpsSerial.available()) {
    gps.encode(gpsSerial.read());
  }

  if (gps.location.isValid() && gps.location.age() < 2000) {
    acquireGPS();
    hasFix = true;
    return true;
  }

  hasFix = false;
  return false;
}

void Location::acquireGPS() {
  currentLocation.latitude = gps.location.lat();
  currentLocation.longitude = gps.location.lng();
  currentLocation.altitude = gps.altitude.meters();

  char buffer[32];
  snprintf(buffer, sizeof(buffer), "%04d-%02d-%02dT%02d:%02d:%02dZ",
           gps.date.year(), gps.date.month(), gps.date.day(), gps.time.hour(),
           gps.time.minute(), gps.time.second());

  currentLocation.timestamp = std::string(buffer);
}

GPX Location::getData() const { return currentLocation; }

std::string Location::toGPXPoint() const {
  char buffer[256];
  snprintf(buffer, sizeof(buffer),
           "<trkpt lat=\"%.6f\" lon=\"%.6f\">\n  <ele>%.2f</ele>\n  "
           "<time>%s</time>\n</trkpt>",
           currentLocation.latitude, currentLocation.longitude,
           currentLocation.altitude, currentLocation.timestamp.c_str());

  return std::string(buffer);
}

uint32_t Location::getUnixTime() const {
  if (!gps.time.isValid() || !gps.date.isValid())
    return 0;

  struct tm t;
  t.tm_year = gps.date.year() - 1900;
  t.tm_mon = gps.date.month() - 1;
  t.tm_mday = gps.date.day();
  t.tm_hour = gps.time.hour();
  t.tm_min = gps.time.minute();
  t.tm_sec = gps.time.second();
  t.tm_isdst = 0;

  return mktime(&t); // returns time_t (uint32_t)
}
