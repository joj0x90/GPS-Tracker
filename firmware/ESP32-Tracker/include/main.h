#include <Arduino.h>

#define FILENAME "/track.gpx"

String gpsTimeISO8601();

/// logs the actual position, consisting of latitude, longitude and alititude to
/// the file
void logGPX(double lat, double lng, double alt);

/// sets up the WiFi connection and prints the IP-Adress of the ESP32.
void connectWiFi();

/// sets up and starts the webserver for serving the gpx file.
void setupWebServer();