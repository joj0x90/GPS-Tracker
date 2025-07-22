# GPS-Tracker

This project contains the firmware for the ESP32-Tracker.
A lightweight, portable, battery efficient GPS-Tracker based on the ESP32.

## Hardware:
* ESP-32 Dev Kit C V4 (any other ESP32-Board should work too)
* GY-NEO6MV2 GPS Modul
* Battery (3s LiPo, in my case)

## Connection:
The GPS module is connected to the ESP32 as follows:
| ESP32 (Pin)   | GPS Module |
| -------- | ------- |
| 22 | Rx |
| 23 | Tx |
| GND | GND |
| 3V3 | VCC |

The GPS module supports a baud rate of 9600.

## Develop on Linux
When developing on Linux, I recommend using VsCode with the platformio extension.
Make sure, your user has the appropriate permissions to access the device:
``` bash
ls -l /dev/ttyUSB0
``` 
Now type ```groups```, to see, if your current user is part of the same group which has access to the USB (either dialout or uucp) \
If your user is not part of this group, add the user to the group:
``` bash
sudo usermod -aG <group> $USER
```
Replace <group> with the name of the group from the ls command.

Now restart your machien or log out and back in for the changes to take effect.

