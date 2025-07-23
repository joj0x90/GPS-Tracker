#pragma once

#include <WiFi.h>
#include <string>

class Connection {
public:
  Connection(const char *ssid, const char *password);

  void begin();
  void tryConnect(uint32_t currentEpoch); // current time in seconds since epoch
  bool connected() const;
  void disconnect();

private:
  const char *ssid;
  const char *password;

  bool trying;
  uint32_t attemptStartTime;  // epoch seconds when the attempt started
  uint32_t lastAttemptMinute; // last minute we attempted (to avoid retrying too
                              // often)
};
