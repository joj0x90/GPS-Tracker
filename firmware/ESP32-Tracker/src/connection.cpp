#include "connection.hpp"

// should only try to connect for up to 3 minutes
#define CONNECTION_WINDOW_SIZE (3 * 60)

Connection::Connection(const char *ssid, const char *password)
    : ssid(ssid), password(password), trying(false), attemptStartTime(0),
      lastAttemptMinute(255) {}

void Connection::begin() {
  WiFi.mode(WIFI_STA);
  WiFi.disconnect(true); // ensure clean state
}

bool Connection::connected() const { return WiFi.status() == WL_CONNECTED; }

void Connection::tryConnect(uint32_t epochSeconds) {
  uint8_t currentMinute = (epochSeconds / 60) % 60;

  // Only try once per minute
  if (currentMinute == lastAttemptMinute)
    return;

  lastAttemptMinute = currentMinute;

  // Start connection attempt
  if (!connected()) {
    WiFi.begin(ssid, password);
    attemptStartTime = epochSeconds;
    trying = true;
  }

  // Already trying, check timeout
  if (trying) {
    if (connected()) {
      trying = false; // successful
    } else if (epochSeconds - attemptStartTime >= CONNECTION_WINDOW_SIZE) {
      // Stop trying after 3 minutes
      WiFi.disconnect(true);
      trying = false;
    }
  }
}
