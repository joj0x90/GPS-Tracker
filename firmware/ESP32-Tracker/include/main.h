#include <Arduino.h>

#include "location.hpp"

#define FILENAME "/track.gpx"

/// logs the actual position, consisting of latitude, longitude and alititude to
/// the file
void logGPX(GPX gpx);

// connects to the hardcoded WiFi network
void connectWiFi();

/// sets up and starts the webserver for serving the gpx file.
void setupWebServer();

constexpr int NUM_WINDOWS = 4;
constexpr uint8_t connection_windows[NUM_WINDOWS] = {0, 15, 30, 45};

/// check, if we are inisde the connection window
bool isConnectionWindow(uint32_t epochSeconds);