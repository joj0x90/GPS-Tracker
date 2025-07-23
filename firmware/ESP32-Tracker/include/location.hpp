#pragma once

#include <string>

#define RX_PIN 23
#define TX_PIN 22
#define BAUD 9600

struct GPX {
  double latitude;
  double longitude;
  double altitude;
  std::string timestamp;
};

#include <string>

class Location {
public:
  Location();    // constructor
  void begin();  // initialize GPS hardware
  bool update(); // acquire GPS data; returns true if valid data was acquired

  GPX getData() const; // return last known position
  std::string
  toGPXPoint() const; // return GPX-formatted string for file writing
  uint32_t getUnixTime() const;

private:
  GPX currentLocation;
  bool hasFix;

  void acquireGPS(); // internal method to read and parse GPS data
};
