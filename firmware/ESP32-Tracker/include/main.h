#include <Arduino.h>

#define FILENAME "/track.gpx"

String gpsTimeISO8601();

/// logs the actual position, consisting of latitude, longitude and alititude to
/// the file
void logGPX(double lat, double lng, double alt);

// connects to the hardcoded WiFi network
void connectWiFi();

/// sets up and starts the webserver for serving the gpx file.
void setupWebServer();