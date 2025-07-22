#include <Arduino.h>
#include <stdio.h>

// put function declarations here:
int myFunction(int, int);

void setup() {
  // put your setup code here, to run once:
  int result = myFunction(2, 3);
  printf("%d\n", result);
}

void loop() {
  // put your main code here, to run repeatedly:
  printf("test");
}

// put function definitions here:
int myFunction(int x, int y) { return x + y; }