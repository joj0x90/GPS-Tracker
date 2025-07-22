#include <HardwareSerial.h>
#include <TinyGPS++.h>

#define SECONDS 1000

TinyGPSPlus gps;
HardwareSerial GPS_Serial(2); // Use UART2 on ESP32

const int RXPin = 23; // GPS TX -> ESP32 RX
const int TXPin = 22; // GPS RX -> ESP32 TX
const uint32_t GPSBaud = 9600;

unsigned long lastPrintTime = 0;
const unsigned long printInterval = 15 * SECONDS; // 15 seconds

void setup() {
  Serial.begin(115200);
  GPS_Serial.begin(GPSBaud, SERIAL_8N1, RXPin, TXPin);
  Serial.println("GPS Reader Initialized");
}

void loop() {
  while (GPS_Serial.available() > 0) {
    gps.encode(GPS_Serial.read());
  }

  if (millis() - lastPrintTime > printInterval) {
    lastPrintTime = millis();

    if (gps.location.isValid()) {
      Serial.print("Latitude: ");
      Serial.println(gps.location.lat(), 6);
      Serial.print("Longitude: ");
      Serial.println(gps.location.lng(), 6);
    } else {
      Serial.println("Waiting for valid GPS data...");
    }
  }
}
