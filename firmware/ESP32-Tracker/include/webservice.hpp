#pragma once

#include <Arduino.h>

class Webservice {
public:
  Webservice(const String &url, const String &fieldName = "gpx_file");

  bool uploadFile(const String &filePath);

private:
  String uploadURL;
  String formField;

  String getContentType(const String &filename);
};
