/*
 * =================================================================
 * SMART FASHION INVENTORY
 * FULL INTEGRATED FIRMWARE - ESP32 DEVKIT V1 (FAST REAL-TIME v12)
 *
 * ATURAN LED INDIKATOR RAK (Kapasitas 15 per rak):
 * - Isi < 15               = LED MENYALA DIAM (HIGH)
 * - Isi == 15 (Penuh Pas)  = LED MATI (LOW)
 * - Melewati 15 (Item >15) = LED BERKEDIP (OVERFLOW / KEPENUHAN)
 *
 * SINKRONISASI OTOMATIS DENGAN LARAVEL (Tiap 1.5 Detik):
 * - Membaca "stok_aktif_rak" dari /api/inventory/realtime.
 * - Jika barang overload terjual (misal 16 -> 15), LED rak otomatis
 *   berhenti berkedip dan MATI (penuh pas).
 * - Jika web di-reset ke 0, semua LED kembali menyala diam (normal).
 * =================================================================
 */

#include <WiFi.h>
#include <HTTPClient.h>
#include <Preferences.h>
#include <freertos/FreeRTOS.h>
#include <freertos/semphr.h>
#include "esp_system.h"

// =================================================================
// 1. KONFIGURASI WIFI DAN LARAVEL
// =================================================================

const char* ssid = "Galaxya50s";
const char* password = "orku4142";

const char* serverUrl =
  "http://192.168.65.117:8000/api/inventory/update";

const char* realtimeUrl =
  "http://192.168.65.117:8000/api/inventory/realtime";

const unsigned long WIFI_RETRY_INTERVAL = 10000;
unsigned long lastWifiRetry = 0;

// =================================================================
// 2. KONFIGURASI PIN ESP32
// =================================================================

#define PIN_ENA 25
#define PIN_IN1 26
#define PIN_IN2 27

#define PIN_IR 34
#define PIN_ENCODER 35

#define LED_RAK_A 15  // D15 (GPIO 15)
#define LED_RAK_B 18  // D18 (GPIO 18)
#define LED_RAK_C 19  // D19 (GPIO 19)

// =================================================================
// 3. VARIABEL PID
// =================================================================

volatile unsigned long encoderPulseCount = 0;
unsigned long lastRPMCalcTime = 0;
const double deltaT = 0.2;

double rawRPM = 0.0;
double currentRPM = 0.0;
double targetRPM = 50.0;

double Kp = 1.8;
double Ki = 0.35;
double Kd = 0.08;

double errorNow = 0.0;
double errorPrev = 0.0;
double integralSum = 0.0;
double derivativeErr = 0.0;

int motorPWM = 160;

// =================================================================
// 4. SENSOR IR DAN KAPASITAS RAK
// =================================================================

volatile int clothesCount = 0;
volatile bool rakCOverflow = false;
volatile unsigned long lastSendActivityTime = 0;

const int MAX_CAPACITY = 15;
const int TOTAL_RACKS = 3;
const int MAX_TOTAL_CAPACITY = MAX_CAPACITY * TOTAL_RACKS;

bool irLastState = HIGH;
unsigned long lastIRTriggerTime = 0;
const unsigned long IR_DEBOUNCE = 400;

const unsigned long LED_BLINK_INTERVAL = 300;
unsigned long lastLEDBlinkTime = 0;
bool ledBlinkState = false;

// =================================================================
// 5. PREFERENCES DAN ANTREAN EVENT
// =================================================================

Preferences preferences;

const int MAX_PENDING_EVENTS = 30;

struct PendingEvent {
  String eventId;
  float rpm;
  int totalItem;
  int slotRak;
  String rakCode;
};

PendingEvent pendingEvents[MAX_PENDING_EVENTS];

int queueHead = 0;
int queueTail = 0;
int queueCount = 0;

SemaphoreHandle_t stateMutex = NULL;

// =================================================================
// 6. INTERRUPT ENCODER
// =================================================================

void IRAM_ATTR encoderISR() {
  encoderPulseCount++;
}

// =================================================================
// 7. MENENTUKAN RAK
// =================================================================

String getKodeRak(int count) {
  int indexRak = ((count - 1) / MAX_CAPACITY) % TOTAL_RACKS;

  if (indexRak == 0) return "RAK-A";
  if (indexRak == 1) return "RAK-B";

  return "RAK-C";
}

// =================================================================
// 8. LED INDIKATOR STATUS MASING-MASING RAK
// =================================================================

void updateLEDStatus(int count) {
  count = constrain(count, 0, MAX_TOTAL_CAPACITY);

  int isiRakA = min(count, MAX_CAPACITY);
  int isiRakB = constrain(count - MAX_CAPACITY, 0, MAX_CAPACITY);
  int isiRakC = constrain(count - (MAX_CAPACITY * 2), 0, MAX_CAPACITY);

  bool rakAKepenuhan = (count > MAX_CAPACITY);
  bool rakBKepenuhan = (count > MAX_CAPACITY * 2);
  bool rakCKepenuhan = rakCOverflow;

  bool rakAPenuhPas = (isiRakA == MAX_CAPACITY && !rakAKepenuhan);
  bool rakBPenuhPas = (isiRakB == MAX_CAPACITY && !rakBKepenuhan);
  bool rakCPenuhPas = (isiRakC == MAX_CAPACITY && !rakCKepenuhan);

  if (!rakAKepenuhan) digitalWrite(LED_RAK_A, rakAPenuhPas ? LOW : HIGH);
  if (!rakBKepenuhan) digitalWrite(LED_RAK_B, rakBPenuhPas ? LOW : HIGH);
  if (!rakCKepenuhan) digitalWrite(LED_RAK_C, rakCPenuhPas ? LOW : HIGH);

  Serial.printf("[LED] A:%d/15 (%s) | B:%d/15 (%s) | C:%d/15 (%s)\n",
    isiRakA, rakAKepenuhan ? "BLINK" : (rakAPenuhPas ? "MATI" : "ON"),
    isiRakB, rakBKepenuhan ? "BLINK" : (rakBPenuhPas ? "MATI" : "ON"),
    isiRakC, rakCKepenuhan ? "BLINK" : (rakCPenuhPas ? "MATI" : "ON"));
}

void updateLEDBlink(unsigned long currentTime, int count) {
  int isiRakA = min(count, MAX_CAPACITY);
  int isiRakB = constrain(count - MAX_CAPACITY, 0, MAX_CAPACITY);
  int isiRakC = constrain(count - (MAX_CAPACITY * 2), 0, MAX_CAPACITY);

  bool rakAKepenuhan = (count > MAX_CAPACITY);
  bool rakBKepenuhan = (count > MAX_CAPACITY * 2);
  bool rakCKepenuhan = rakCOverflow;

  bool rakAPenuhPas = (isiRakA == MAX_CAPACITY && !rakAKepenuhan);
  bool rakBPenuhPas = (isiRakB == MAX_CAPACITY && !rakBKepenuhan);
  bool rakCPenuhPas = (isiRakC == MAX_CAPACITY && !rakCKepenuhan);

  if (currentTime - lastLEDBlinkTime >= LED_BLINK_INTERVAL) {
    lastLEDBlinkTime = currentTime;
    ledBlinkState = !ledBlinkState;
    uint8_t blinkVal = ledBlinkState ? HIGH : LOW;

    digitalWrite(LED_RAK_A, rakAKepenuhan ? blinkVal : (rakAPenuhPas ? LOW : HIGH));
    digitalWrite(LED_RAK_B, rakBKepenuhan ? blinkVal : (rakBPenuhPas ? LOW : HIGH));
    digitalWrite(LED_RAK_C, rakCKepenuhan ? blinkVal : (rakCPenuhPas ? LOW : HIGH));
  }
}

// =================================================================
// 9. EVENT ID UNIK & HELPER NVS
// =================================================================

String buatEventId() {
  uint64_t randomValue = ((uint64_t)esp_random() << 32) | esp_random();
  String macAddress = WiFi.macAddress();
  macAddress.replace(":", "");

  char randomText[17];
  snprintf(randomText, sizeof(randomText), "%016llX", (unsigned long long)randomValue);

  return "ESP32-" + macAddress + "-" + String(randomText);
}

String queueKey(const char* prefix, int slot) {
  return String(prefix) + String(slot);
}

void simpanEventKeNVS(int slot, const PendingEvent& event) {
  preferences.putString(queueKey("id", slot).c_str(), event.eventId);
  preferences.putFloat(queueKey("rpm", slot).c_str(), event.rpm);
  preferences.putInt(queueKey("cnt", slot).c_str(), event.totalItem);
  preferences.putInt(queueKey("slot", slot).c_str(), event.slotRak);
  preferences.putString(queueKey("rak", slot).c_str(), event.rakCode);
}

void hapusEventDariNVS(int slot) {
  preferences.remove(queueKey("id", slot).c_str());
  preferences.remove(queueKey("rpm", slot).c_str());
  preferences.remove(queueKey("cnt", slot).c_str());
  preferences.remove(queueKey("slot", slot).c_str());
  preferences.remove(queueKey("rak", slot).c_str());
}

PendingEvent bacaEventDariNVS(int slot) {
  PendingEvent event;
  event.eventId = preferences.getString(queueKey("id", slot).c_str(), "");
  event.rpm = preferences.getFloat(queueKey("rpm", slot).c_str(), 0.0);
  event.totalItem = preferences.getInt(queueKey("cnt", slot).c_str(), 0);
  event.slotRak = preferences.getInt(queueKey("slot", slot).c_str(), 0);
  event.rakCode = preferences.getString(queueKey("rak", slot).c_str(), "");
  return event;
}

// =================================================================
// 10. RESET DATA ESP32
// =================================================================

void jalankanResetTotalESP32(bool sudahPegangMutex = false) {
  if (!sudahPegangMutex && stateMutex != NULL) {
    xSemaphoreTake(stateMutex, portMAX_DELAY);
  }

  preferences.clear();
  preferences.putBool("sim_reset_v12", true);
  preferences.putInt("head", 0);
  preferences.putInt("tail", 0);
  preferences.putInt("count", 0);
  preferences.putInt("clothes", 0);
  preferences.putBool("rakc_ovf", false);

  queueHead = 0;
  queueTail = 0;
  queueCount = 0;
  clothesCount = 0;
  rakCOverflow = false;

  for (int i = 0; i < MAX_PENDING_EVENTS; i++) {
    pendingEvents[i] = PendingEvent();
  }

  if (!sudahPegangMutex && stateMutex != NULL) {
    xSemaphoreGive(stateMutex);
  }

  updateLEDStatus(0);

  Serial.println("========================================");
  Serial.println("[RESET] MEMORI ESP32 DIKOSONGKAN -> 0");
  Serial.println("========================================");
}

void resetSimulasiLamaSatuKali() {
  if (preferences.getBool("sim_reset_v12", false)) {
    return;
  }
  jalankanResetTotalESP32(false);
}

void muatAntreanDariNVS() {
  queueHead = preferences.getInt("head", 0);
  queueTail = preferences.getInt("tail", 0);
  queueCount = preferences.getInt("count", 0);

  if (
    queueHead < 0 || queueHead >= MAX_PENDING_EVENTS ||
    queueTail < 0 || queueTail >= MAX_PENDING_EVENTS ||
    queueCount < 0 || queueCount > MAX_PENDING_EVENTS
  ) {
    queueHead = 0;
    queueTail = 0;
    queueCount = 0;
    preferences.putInt("head", 0);
    preferences.putInt("tail", 0);
    preferences.putInt("count", 0);
  }

  for (int i = 0; i < MAX_PENDING_EVENTS; i++) {
    pendingEvents[i] = bacaEventDariNVS(i);
  }

  clothesCount = constrain(preferences.getInt("clothes", 0), 0, MAX_TOTAL_CAPACITY);
  rakCOverflow = preferences.getBool("rakc_ovf", false);
}

bool tambahAntrean(double rpm, int totalItem, int slotRak, String rakCode) {
  if (stateMutex == NULL) return false;
  if (xSemaphoreTake(stateMutex, portMAX_DELAY) != pdTRUE) return false;

  if (queueCount >= MAX_PENDING_EVENTS) {
    xSemaphoreGive(stateMutex);
    return false;
  }

  PendingEvent event;
  event.eventId = buatEventId();
  event.rpm = (float)rpm;
  event.totalItem = totalItem;
  event.slotRak = slotRak;
  event.rakCode = rakCode;

  int slot = queueTail;
  simpanEventKeNVS(slot, event);
  pendingEvents[slot] = event;

  queueTail = (queueTail + 1) % MAX_PENDING_EVENTS;
  queueCount++;
  lastSendActivityTime = millis();

  preferences.putInt("tail", queueTail);
  preferences.putInt("count", queueCount);

  xSemaphoreGive(stateMutex);
  return true;
}

void hapusAntreanPertama(const String& eventId) {
  if (xSemaphoreTake(stateMutex, portMAX_DELAY) != pdTRUE) return;

  if (queueCount > 0 && pendingEvents[queueHead].eventId == eventId) {
    int slotLama = queueHead;
    queueHead = (queueHead + 1) % MAX_PENDING_EVENTS;
    queueCount--;

    preferences.putInt("head", queueHead);
    preferences.putInt("count", queueCount);

    pendingEvents[slotLama] = PendingEvent();
    hapusEventDariNVS(slotLama);
  }

  xSemaphoreGive(stateMutex);
}

// =================================================================
// 11. WIFI RECONNECT
// =================================================================

void periksaKoneksiWiFi() {
  if (WiFi.status() == WL_CONNECTED) return;

  unsigned long now = millis();
  if (now - lastWifiRetry < WIFI_RETRY_INTERVAL) return;
  lastWifiRetry = now;

  WiFi.disconnect(false, false);
  vTaskDelay(pdMS_TO_TICKS(300));
  WiFi.begin(ssid, password);
}

// =================================================================
// 12. TASK PENGIRIMAN DATA INSTAN KE LARAVEL
// =================================================================

void taskKirimLaravel(void* parameter) {
  for (;;) {
    periksaKoneksiWiFi();

    PendingEvent event;
    bool adaEvent = false;

    if (xSemaphoreTake(stateMutex, portMAX_DELAY) == pdTRUE) {
      if (queueCount > 0) {
        event = pendingEvents[queueHead];
        adaEvent = true;
      }
      xSemaphoreGive(stateMutex);
    }

    if (!adaEvent) {
      vTaskDelay(pdMS_TO_TICKS(30));
      continue;
    }

    if (event.eventId.length() == 0) {
      if (xSemaphoreTake(stateMutex, portMAX_DELAY) == pdTRUE) {
        if (queueCount > 0) {
          int slotLama = queueHead;
          queueHead = (queueHead + 1) % MAX_PENDING_EVENTS;
          queueCount--;
          preferences.putInt("head", queueHead);
          preferences.putInt("count", queueCount);
          pendingEvents[slotLama] = PendingEvent();
          hapusEventDariNVS(slotLama);
        }
        xSemaphoreGive(stateMutex);
      }
      vTaskDelay(pdMS_TO_TICKS(20));
      continue;
    }

    if (WiFi.status() != WL_CONNECTED) {
      vTaskDelay(pdMS_TO_TICKS(500));
      continue;
    }

    HTTPClient http;
    http.useHTTP10(true);
    http.begin(serverUrl);
    http.setTimeout(3000);
    http.addHeader("Content-Type", "application/json");
    http.addHeader("Accept", "application/json");

    String payload = "{";
    payload += "\"event_id\":\"" + event.eventId + "\",";
    payload += "\"kecepatan_rpm\":" + String(event.rpm, 1) + ",";
    payload += "\"total_count\":" + String(event.totalItem) + ",";
    payload += "\"slot\":" + String(event.slotRak) + ",";
    payload += "\"rak_code\":\"" + event.rakCode + "\"";
    payload += "}";

    unsigned long tStart = millis();
    int httpResponseCode = http.POST(payload);
    http.end();

    if (httpResponseCode >= 200 && httpResponseCode < 300) {
      Serial.printf("[LARAVEL OK] %lums | Item #%d -> %s\n",
        millis() - tStart, event.totalItem, event.rakCode.c_str());
      hapusAntreanPertama(event.eventId);
      vTaskDelay(pdMS_TO_TICKS(20));
    } else {
      Serial.printf("[LARAVEL ERROR] HTTP %d\n", httpResponseCode);
      vTaskDelay(pdMS_TO_TICKS(400));
    }
  }
}

// =================================================================
// 13. TASK AUTO-SINKRONISASI STOK AKTIF RAK & LED DARI LARAVEL
// =================================================================

void taskSinkronLaravel(void* parameter) {
  for (;;) {
    vTaskDelay(pdMS_TO_TICKS(1500));

    if (WiFi.status() != WL_CONNECTED || (millis() - lastSendActivityTime < 1500)) {
      continue;
    }

    HTTPClient http;
    http.useHTTP10(true);
    http.begin(realtimeUrl);
    http.setTimeout(3000);
    http.addHeader("Accept", "application/json");

    int code = http.GET();
    if (code == 200) {
      String body = http.getString();
      int idxTotals = body.indexOf("\"totals\":");
      int idxAktif = body.indexOf("\"stok_aktif_rak\":", idxTotals != -1 ? idxTotals : 0);

      if (idxAktif != -1) {
        int start = idxAktif + 17;
        int end = body.indexOf(",", start);
        if (end == -1) end = body.indexOf("}", start);
        int serverAktif = body.substring(start, end).toInt();

        // Jika web di-reset ke 0 total
        if (serverAktif == 0 && (clothesCount > 0 || queueCount > 0 || rakCOverflow)) {
          Serial.println("[AUTO-SYNC] Web di-reset ke 0! Mengembalikan semua LED...");
          jalankanResetTotalESP32(false);
        }
        // Jika barang overload terjual sehingga sisa isi rak turun (misal 16 -> 15 LED jadi mati)
        else if (queueCount == 0 && serverAktif != clothesCount) {
          if (xSemaphoreTake(stateMutex, portMAX_DELAY) == pdTRUE) {
            clothesCount = constrain(serverAktif, 0, MAX_TOTAL_CAPACITY);
            if (clothesCount < MAX_TOTAL_CAPACITY) {
              rakCOverflow = false;
            }
            preferences.putInt("clothes", clothesCount);
            preferences.putBool("rakc_ovf", rakCOverflow);
            xSemaphoreGive(stateMutex);
          }

          updateLEDStatus(clothesCount);
          Serial.printf("[AUTO-SYNC] Barang terjual / sinkron -> Sisa di rak: %d | LED diperbarui!\n", clothesCount);
        }
      }
    }
    http.end();
  }
}

// =================================================================
// 14. SETUP
// =================================================================

void setup() {
  Serial.begin(115200);
  delay(400);

  if (!preferences.begin("sf-inventory", false)) {
    while (true) { delay(1000); }
  }

  stateMutex = xSemaphoreCreateMutex();
  if (stateMutex == NULL) {
    while (true) { delay(1000); }
  }

  pinMode(LED_RAK_A, OUTPUT);
  pinMode(LED_RAK_B, OUTPUT);
  pinMode(LED_RAK_C, OUTPUT);

  resetSimulasiLamaSatuKali();
  muatAntreanDariNVS();

  updateLEDStatus(clothesCount);
  updateLEDBlink(millis(), clothesCount);

  pinMode(PIN_ENA, OUTPUT);
  pinMode(PIN_IN1, OUTPUT);
  pinMode(PIN_IN2, OUTPUT);
  pinMode(PIN_IR, INPUT);
  pinMode(PIN_ENCODER, INPUT);

  irLastState = digitalRead(PIN_IR);
  attachInterrupt(digitalPinToInterrupt(PIN_ENCODER), encoderISR, RISING);

  ledcAttach(PIN_ENA, 5000, 8);
  ledcWrite(PIN_ENA, motorPWM);

  digitalWrite(PIN_IN1, HIGH);
  digitalWrite(PIN_IN2, LOW);

  WiFi.mode(WIFI_STA);
  WiFi.setAutoReconnect(true);
  WiFi.setSleep(false);
  WiFi.begin(ssid, password);

  unsigned long wifiStart = millis();
  while (WiFi.status() != WL_CONNECTED && millis() - wifiStart < 20000) {
    delay(400);
    Serial.print(".");
  }
  Serial.println();

  xTaskCreatePinnedToCore(taskKirimLaravel, "TaskKirimLaravel", 8192, NULL, 2, NULL, 0);
  xTaskCreatePinnedToCore(taskSinkronLaravel, "TaskSinkronLaravel", 6144, NULL, 1, NULL, 0);

  lastRPMCalcTime = millis();
  Serial.println("[SYSTEM] Siap! Pengiriman instan & Auto-Sync LED v12 aktif.");
}

// =================================================================
// 15. LOOP UTAMA
// =================================================================

void loop() {
  unsigned long currentTime = millis();

  if (Serial.available() > 0) {
    String perintah = Serial.readStringUntil('\n');
    perintah.trim();
    perintah.toUpperCase();
    if (perintah == "RESET") {
      jalankanResetTotalESP32(false);
    }
  }

  bool irCurrentState = digitalRead(PIN_IR);

  if (
    irCurrentState == LOW &&
    irLastState == HIGH &&
    currentTime - lastIRTriggerTime >= IR_DEBOUNCE
  ) {
    lastIRTriggerTime = currentTime;

    if (clothesCount >= MAX_TOTAL_CAPACITY) {
      if (!rakCOverflow) {
        if (xSemaphoreTake(stateMutex, portMAX_DELAY) == pdTRUE) {
          rakCOverflow = true;
          preferences.putBool("rakc_ovf", true);
          xSemaphoreGive(stateMutex);
        }
        updateLEDStatus(clothesCount);
      }
      Serial.println("[KAPASITAS PENUH] Semua rak melewati 15 item! Semua LED berkedip.");
    } else {
      int calonCount = clothesCount + 1;
      int slotSekarang = ((calonCount - 1) % MAX_CAPACITY) + 1;
      String rakSekarang = getKodeRak(calonCount);
      double rpmKirim = currentRPM > 0 ? currentRPM : targetRPM;

      if (tambahAntrean(rpmKirim, calonCount, slotSekarang, rakSekarang)) {
        if (xSemaphoreTake(stateMutex, portMAX_DELAY) == pdTRUE) {
          clothesCount = calonCount;
          preferences.putInt("clothes", clothesCount);
          xSemaphoreGive(stateMutex);
        }

        Serial.printf("[SENSOR] Item #%d masuk %s (Slot %d/%d)\n",
          clothesCount, rakSekarang.c_str(), slotSekarang, MAX_CAPACITY);
        updateLEDStatus(clothesCount);
      }
    }
  }

  irLastState = irCurrentState;
  updateLEDBlink(currentTime, clothesCount);

  if (currentTime - lastRPMCalcTime >= 200) {
    noInterrupts();
    unsigned long pulses = encoderPulseCount;
    encoderPulseCount = 0;
    interrupts();

    rawRPM = (pulses / 20.0) * 300.0;
    currentRPM = (0.7 * rawRPM) + (0.3 * currentRPM);

    errorNow = targetRPM - currentRPM;
    integralSum = constrain(integralSum + (errorNow * deltaT), -255.0, 255.0);
    derivativeErr = (errorNow - errorPrev) / deltaT;
    errorPrev = errorNow;

    double u_k = (Kp * errorNow) + (Ki * integralSum) + (Kd * derivativeErr);
    motorPWM = constrain((int)u_k, 80, 255);
    ledcWrite(PIN_ENA, motorPWM);

    lastRPMCalcTime = currentTime;
  }
}