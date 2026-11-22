/*
 * =================================================================
 * SMART FASHION INVENTORY
 * FULL INTEGRATED FIRMWARE - ESP32 DEVKIT V1
 *
 * FITUR:
 * 1. WiFi ESP32 ke Laravel
 * 2. Diagnostik koneksi WiFi
 * 3. Sensor IR mendeteksi pakaian
 * 4. PID motor target 80 RPM
 * 5. Encoder RPM
 * 6. LED indikator rak
 * 7. Antrean permanen Preferences
 * 8. HTTP POST ke Laravel
 * 9. Event ID unik untuk mencegah duplikasi
 * 10. Reconnect WiFi otomatis
 * 11. Reset antrean simulasi lama satu kali
 *
 * API:
 * POST http://192.168.88.117:8000/api/inventory/update
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
  "http://192.168.64.117:8000/api/inventory/update";

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

#define LED_RAK_A 17
#define LED_RAK_B 18
#define LED_RAK_C 19

// =================================================================
// 3. VARIABEL PID
// =================================================================

volatile unsigned long encoderPulseCount = 0;

unsigned long lastRPMCalcTime = 0;

const double deltaT = 0.2;

double rawRPM = 0.0;
double currentRPM = 0.0;
double targetRPM = 80.0;

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

int clothesCount = 0;

const int MAX_CAPACITY = 8;
const int TOTAL_RACKS = 3;
const int MAX_TOTAL_CAPACITY = MAX_CAPACITY * TOTAL_RACKS;

bool irLastState = HIGH;

unsigned long lastIRTriggerTime = 0;

const unsigned long IR_DEBOUNCE = 600;

// =================================================================
// 5. PREFERENCES DAN ANTREAN EVENT
// =================================================================

Preferences preferences;

const int MAX_PENDING_EVENTS = 20;

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

SemaphoreHandle_t queueMutex = NULL;

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
// 8. LED INDIKATOR RAK
// =================================================================

void updateLEDStatus(int count) {

  if (count == 0) {

    digitalWrite(LED_RAK_A, HIGH);
    digitalWrite(LED_RAK_B, HIGH);
    digitalWrite(LED_RAK_C, HIGH);

    Serial.println("[LED] Semua rak kosong.");

    return;
  }

  int slot = ((count - 1) % MAX_CAPACITY) + 1;

  String rakAktif = getKodeRak(count);

  if (slot >= 1 && slot <= 3) {

    digitalWrite(LED_RAK_A, LOW);
    digitalWrite(LED_RAK_B, HIGH);
    digitalWrite(LED_RAK_C, HIGH);

  }
  else if (slot >= 4 && slot <= 7) {

    digitalWrite(LED_RAK_A, LOW);
    digitalWrite(LED_RAK_B, LOW);
    digitalWrite(LED_RAK_C, HIGH);

  }
  else if (slot == 8) {

    digitalWrite(LED_RAK_A, LOW);
    digitalWrite(LED_RAK_B, LOW);
    digitalWrite(LED_RAK_C, LOW);

  }

  Serial.printf(
    "[LED] %s | Slot %d/8\n",
    rakAktif.c_str(),
    slot
  );
}

// =================================================================
// 9. EVENT ID UNIK
// =================================================================

String buatEventId() {

  uint64_t randomValue =
    ((uint64_t)esp_random() << 32) | esp_random();

  String macAddress = WiFi.macAddress();

  macAddress.replace(":", "");

  char randomText[17];

  snprintf(
    randomText,
    sizeof(randomText),
    "%016llX",
    (unsigned long long)randomValue
  );

  return "ESP32-" + macAddress + "-" + String(randomText);
}

// =================================================================
// 10. KEY PREFERENCES
// =================================================================

String queueKey(const char* prefix, int slot) {
  return String(prefix) + String(slot);
}

// =================================================================
// 11. SIMPAN EVENT KE MEMORI ESP32
// =================================================================

void simpanEventKeNVS(int slot, const PendingEvent& event) {

  preferences.putString(
    queueKey("id", slot).c_str(),
    event.eventId
  );

  preferences.putFloat(
    queueKey("rpm", slot).c_str(),
    event.rpm
  );

  preferences.putInt(
    queueKey("cnt", slot).c_str(),
    event.totalItem
  );

  preferences.putInt(
    queueKey("slot", slot).c_str(),
    event.slotRak
  );

  preferences.putString(
    queueKey("rak", slot).c_str(),
    event.rakCode
  );
}

// =================================================================
// 12. HAPUS EVENT DARI MEMORI
// =================================================================

void hapusEventDariNVS(int slot) {

  preferences.remove(queueKey("id", slot).c_str());
  preferences.remove(queueKey("rpm", slot).c_str());
  preferences.remove(queueKey("cnt", slot).c_str());
  preferences.remove(queueKey("slot", slot).c_str());
  preferences.remove(queueKey("rak", slot).c_str());
}

// =================================================================
// 13. BACA EVENT DARI MEMORI
// =================================================================

PendingEvent bacaEventDariNVS(int slot) {

  PendingEvent event;

  event.eventId =
    preferences.getString(queueKey("id", slot).c_str(), "");

  event.rpm =
    preferences.getFloat(queueKey("rpm", slot).c_str(), 0.0);

  event.totalItem =
    preferences.getInt(queueKey("cnt", slot).c_str(), 0);

  event.slotRak =
    preferences.getInt(queueKey("slot", slot).c_str(), 0);

  event.rakCode =
    preferences.getString(queueKey("rak", slot).c_str(), "");

  return event;
}

// =================================================================
// 14. RESET DATA SIMULASI LAMA SATU KALI
// =================================================================

void resetSimulasiLamaSatuKali() {

  bool sudahReset = preferences.getBool(
    "sim_reset_v1",
    false
  );

  if (sudahReset) {

    Serial.println(
      "[RESET] Reset simulasi sebelumnya sudah dilakukan."
    );

    Serial.println(
      "[RESET] Antrean baru akan dipertahankan."
    );

    return;
  }

  Serial.println();
  Serial.println("========================================");
  Serial.println("[RESET] MEMBERSIHKAN DATA SIMULASI LAMA");
  Serial.println("========================================");

  // Hanya membersihkan namespace sf-inventory.
  preferences.clear();

  // Simpan penanda agar reset tidak berulang.
  preferences.putBool("sim_reset_v1", true);

  // Inisialisasi metadata antrean.
  preferences.putInt("head", 0);
  preferences.putInt("tail", 0);
  preferences.putInt("count", 0);

  // Reset hitungan pakaian lokal.
  preferences.putInt("clothes", 0);

  queueHead = 0;
  queueTail = 0;
  queueCount = 0;
  clothesCount = 0;

  for (int i = 0; i < MAX_PENDING_EVENTS; i++) {
    pendingEvents[i] = PendingEvent();
  }

  Serial.println("[RESET] Antrean lama berhasil dibersihkan.");
  Serial.println("[RESET] Total pakaian lokal kembali ke 0.");
  Serial.println("[RESET] Kapasitas pengujian: 24 item.");
  Serial.println("========================================");
  Serial.println();
}

// =================================================================
// 15. MEMULIHKAN ANTREAN SAAT ESP32 MENYALA
// =================================================================

void muatAntreanDariNVS() {

  queueHead = preferences.getInt("head", 0);
  queueTail = preferences.getInt("tail", 0);
  queueCount = preferences.getInt("count", 0);

  if (
    queueHead < 0 ||
    queueHead >= MAX_PENDING_EVENTS ||
    queueTail < 0 ||
    queueTail >= MAX_PENDING_EVENTS ||
    queueCount < 0 ||
    queueCount > MAX_PENDING_EVENTS
  ) {

    Serial.println("[QUEUE] Metadata tidak valid.");

    queueHead = 0;
    queueTail = 0;
    queueCount = 0;

    preferences.putInt("head", queueHead);
    preferences.putInt("tail", queueTail);
    preferences.putInt("count", queueCount);
  }

  for (int i = 0; i < MAX_PENDING_EVENTS; i++) {
    pendingEvents[i] = bacaEventDariNVS(i);
  }

  clothesCount = preferences.getInt("clothes", 0);

  if (clothesCount < 0) {
    clothesCount = 0;
  }

  Serial.printf(
    "[QUEUE] Antrean dipulihkan: %d/%d event\n",
    queueCount,
    MAX_PENDING_EVENTS
  );

  Serial.printf(
    "[MEMORY] Total pakaian lokal: %d\n",
    clothesCount
  );
}

// =================================================================
// 16. TAMBAHKAN EVENT KE ANTREAN
// =================================================================

bool tambahAntrean(
  double rpm,
  int totalItem,
  int slotRak,
  String rakCode
) {

  if (queueMutex == NULL) {
    return false;
  }

  if (
    xSemaphoreTake(queueMutex, portMAX_DELAY) != pdTRUE
  ) {
    return false;
  }

  if (queueCount >= MAX_PENDING_EVENTS) {

    Serial.println("[QUEUE PENUH] Event tidak dapat disimpan.");

    xSemaphoreGive(queueMutex);

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

  queueTail =
    (queueTail + 1) % MAX_PENDING_EVENTS;

  queueCount++;

  preferences.putInt("tail", queueTail);
  preferences.putInt("count", queueCount);

  Serial.printf(
    "[QUEUE] Event tersimpan: %s | %d/%d\n",
    event.eventId.c_str(),
    queueCount,
    MAX_PENDING_EVENTS
  );

  xSemaphoreGive(queueMutex);

  return true;
}

// =================================================================
// 17. HAPUS EVENT SETELAH BERHASIL DIKIRIM
// =================================================================

void hapusAntreanPertama(const String& eventId) {

  if (
    xSemaphoreTake(queueMutex, portMAX_DELAY) != pdTRUE
  ) {
    return;
  }

  if (
    queueCount > 0 &&
    pendingEvents[queueHead].eventId == eventId
  ) {

    int slotLama = queueHead;

    queueHead =
      (queueHead + 1) % MAX_PENDING_EVENTS;

    queueCount--;

    preferences.putInt("head", queueHead);
    preferences.putInt("count", queueCount);

    pendingEvents[slotLama] = PendingEvent();

    hapusEventDariNVS(slotLama);

    Serial.printf(
      "[QUEUE] Event terkirim. Sisa antrean: %d/%d\n",
      queueCount,
      MAX_PENDING_EVENTS
    );
  }

  xSemaphoreGive(queueMutex);
}

// =================================================================
// 18. DIAGNOSTIK STATUS WIFI
// =================================================================

const char* namaStatusWiFi(wl_status_t status) {

  switch (status) {

    case WL_IDLE_STATUS:
      return "IDLE";

    case WL_NO_SSID_AVAIL:
      return "SSID TIDAK DITEMUKAN";

    case WL_SCAN_COMPLETED:
      return "SCAN SELESAI";

    case WL_CONNECTED:
      return "TERHUBUNG";

    case WL_CONNECT_FAILED:
      return "KONEKSI GAGAL / PERIKSA PASSWORD";

    case WL_CONNECTION_LOST:
      return "KONEKSI TERPUTUS";

    case WL_DISCONNECTED:
      return "BELUM TERHUBUNG";

    default:
      return "STATUS TIDAK DIKENAL";
  }
}

void cetakDebugWiFi() {

  wl_status_t status = WiFi.status();

  Serial.println();
  Serial.println("========== DEBUG WIFI ==========");

  Serial.print("[WIFI] SSID tujuan: ");
  Serial.println(ssid);

  Serial.print("[WIFI] Status kode: ");
  Serial.println((int)status);

  Serial.print("[WIFI] Keterangan: ");
  Serial.println(namaStatusWiFi(status));

  Serial.print("[WIFI] SSID terdeteksi: ");
  Serial.println(WiFi.SSID());

  Serial.print("[WIFI] IP ESP32: ");
  Serial.println(WiFi.localIP());

  Serial.print("[WIFI] RSSI: ");

  if (status == WL_CONNECTED) {

    Serial.print(WiFi.RSSI());
    Serial.println(" dBm");

    Serial.print("[WIFI] Channel: ");
    Serial.println(WiFi.channel());

    Serial.print("[WIFI] Gateway: ");
    Serial.println(WiFi.gatewayIP());

    Serial.print("[WIFI] Subnet: ");
    Serial.println(WiFi.subnetMask());

  } else {

    Serial.println("Belum tersedia.");
  }

  Serial.print("[WIFI] MAC ESP32: ");
  Serial.println(WiFi.macAddress());

  Serial.println("================================");
  Serial.println();
}

// =================================================================
// 19. WIFI RECONNECT
// =================================================================

void periksaKoneksiWiFi() {

  if (WiFi.status() == WL_CONNECTED) {
    return;
  }

  unsigned long now = millis();

  if (now - lastWifiRetry < WIFI_RETRY_INTERVAL) {
    return;
  }

  lastWifiRetry = now;

  Serial.println();
  Serial.println("[WIFI] Koneksi terputus.");
  Serial.println("[WIFI] Memulai koneksi ulang...");

  WiFi.disconnect(false, false);

  vTaskDelay(pdMS_TO_TICKS(500));

  WiFi.begin(ssid, password);
}

// =================================================================
// 20. TASK PENGIRIMAN DATA KE LARAVEL
// =================================================================

void taskKirimLaravel(void* parameter) {

  Serial.println("[TASK] Pengiriman Laravel aktif.");

  for (;;) {

    periksaKoneksiWiFi();

    PendingEvent event;

    bool adaEvent = false;

    if (
      xSemaphoreTake(queueMutex, portMAX_DELAY) == pdTRUE
    ) {

      if (queueCount > 0) {
        event = pendingEvents[queueHead];
        adaEvent = true;
      }

      xSemaphoreGive(queueMutex);
    }

    if (!adaEvent) {

      vTaskDelay(pdMS_TO_TICKS(500));

      continue;
    }

    if (event.eventId.length() == 0) {

      Serial.println(
        "[QUEUE WARNING] Event kosong ditemukan."
      );

      if (
        xSemaphoreTake(queueMutex, portMAX_DELAY) == pdTRUE
      ) {

        if (queueCount > 0) {

          int slotLama = queueHead;

          queueHead =
            (queueHead + 1) % MAX_PENDING_EVENTS;

          queueCount--;

          preferences.putInt("head", queueHead);
          preferences.putInt("count", queueCount);

          pendingEvents[slotLama] = PendingEvent();

          hapusEventDariNVS(slotLama);
        }

        xSemaphoreGive(queueMutex);
      }

      vTaskDelay(pdMS_TO_TICKS(500));

      continue;
    }

    if (WiFi.status() != WL_CONNECTED) {

      Serial.println(
        "[QUEUE] Menunggu koneksi WiFi untuk mengirim data."
      );

      vTaskDelay(pdMS_TO_TICKS(1000));

      continue;
    }

    Serial.print("[WIFI] Terhubung. IP ESP32: ");
    Serial.println(WiFi.localIP());

    HTTPClient http;

    http.begin(serverUrl);

    http.setTimeout(5000);

    http.addHeader(
      "Content-Type",
      "application/json"
    );

    http.addHeader(
      "Accept",
      "application/json"
    );

    String payload = "{";

    payload += "\"event_id\":\"" + event.eventId + "\",";
    payload += "\"kecepatan_rpm\":" + String(event.rpm, 1) + ",";
    payload += "\"total_count\":" + String(event.totalItem) + ",";
    payload += "\"slot\":" + String(event.slotRak) + ",";
    payload += "\"rak_code\":\"" + event.rakCode + "\"";

    payload += "}";

    Serial.printf(
      "[LARAVEL] Mengirim event: %s\n",
      event.eventId.c_str()
    );

    Serial.println("[LARAVEL] Payload:");
    Serial.println(payload);

    int httpResponseCode = http.POST(payload);

    if (
      httpResponseCode >= 200 &&
      httpResponseCode < 300
    ) {

      Serial.printf(
        "[LARAVEL OK] HTTP %d\n",
        httpResponseCode
      );

      String response = http.getString();

      Serial.println("[LARAVEL RESPONSE]");
      Serial.println(response);

      hapusAntreanPertama(event.eventId);

    } else {

      Serial.printf(
        "[LARAVEL ERROR] HTTP %d\n",
        httpResponseCode
      );

      if (httpResponseCode > 0) {

        Serial.println(http.getString());

      } else {

        Serial.println(
          http.errorToString(httpResponseCode)
        );
      }

      Serial.println(
        "[QUEUE] Event tetap disimpan untuk dicoba kembali."
      );
    }

    http.end();

    vTaskDelay(pdMS_TO_TICKS(1500));
  }
}

// =================================================================
// 21. SETUP
// =================================================================

void setup() {

  Serial.begin(115200);

  delay(500);

  Serial.println();
  Serial.println("=================================================");
  Serial.println(" SMART FASHION INVENTORY - ESP32");
  Serial.println("=================================================");

  // ---------------------------------------------------------------
  // PREFERENCES
  // ---------------------------------------------------------------

  if (!preferences.begin("sf-inventory", false)) {

    Serial.println("[FATAL] Preferences gagal dibuka!");

    while (true) {
      delay(1000);
    }
  }

  // Mutex dibuat sebelum task dan antrean digunakan.
  queueMutex = xSemaphoreCreateMutex();

  if (queueMutex == NULL) {

    Serial.println("[FATAL] Gagal membuat mutex!");

    while (true) {
      delay(1000);
    }
  }

  // RESET SIMULASI LAMA HANYA SATU KALI.
  resetSimulasiLamaSatuKali();

  // Pulihkan antrean setelah reset satu kali diperiksa.
  muatAntreanDariNVS();

  // ---------------------------------------------------------------
  // LED
  // ---------------------------------------------------------------

  pinMode(LED_RAK_A, OUTPUT);
  pinMode(LED_RAK_B, OUTPUT);
  pinMode(LED_RAK_C, OUTPUT);

  updateLEDStatus(clothesCount);

  // ---------------------------------------------------------------
  // MOTOR DAN SENSOR
  // ---------------------------------------------------------------

  pinMode(PIN_ENA, OUTPUT);
  pinMode(PIN_IN1, OUTPUT);
  pinMode(PIN_IN2, OUTPUT);

  // GPIO34 tidak memiliki internal pull-up.
  pinMode(PIN_IR, INPUT);

  pinMode(PIN_ENCODER, INPUT);

  irLastState = digitalRead(PIN_IR);

  attachInterrupt(
    digitalPinToInterrupt(PIN_ENCODER),
    encoderISR,
    RISING
  );

  // ---------------------------------------------------------------
  // PWM MOTOR
  // ---------------------------------------------------------------

  ledcAttach(PIN_ENA, 5000, 8);

  ledcWrite(PIN_ENA, motorPWM);

  digitalWrite(PIN_IN1, HIGH);
  digitalWrite(PIN_IN2, LOW);

  delay(300);

  // ---------------------------------------------------------------
  // WIFI
  // ---------------------------------------------------------------

  Serial.println();
  Serial.print("[WIFI] Menghubungkan ke: ");
  Serial.println(ssid);

  WiFi.mode(WIFI_STA);

  WiFi.setAutoReconnect(true);

  WiFi.setSleep(false);

  WiFi.begin(ssid, password);

  unsigned long wifiStart = millis();

  while (
    WiFi.status() != WL_CONNECTED &&
    millis() - wifiStart < 20000
  ) {

    delay(500);

    Serial.print(".");
  }

  if (WiFi.status() == WL_CONNECTED) {

    Serial.println();
    Serial.println("[WIFI] TERHUBUNG!");

  } else {

    Serial.println();
    Serial.println("[WIFI] Belum berhasil terhubung.");
    Serial.println("[WIFI] Program akan mencoba koneksi ulang.");
  }

  cetakDebugWiFi();

  // ---------------------------------------------------------------
  // TASK LARAVEL
  // ---------------------------------------------------------------

  BaseType_t taskStatus = xTaskCreatePinnedToCore(
    taskKirimLaravel,
    "TaskKirimLaravel",
    8192,
    NULL,
    1,
    NULL,
    0
  );

  if (taskStatus != pdPASS) {

    Serial.println(
      "[FATAL] Task pengiriman Laravel gagal dibuat!"
    );
  }

  lastRPMCalcTime = millis();

  Serial.println();

  Serial.println(
    "[SYSTEM] Konveyor otomatis berjalan."
  );

  Serial.println(
    "[SYSTEM] Target kecepatan: 80 RPM."
  );

  Serial.println(
    "[SYSTEM] Kapasitas maksimal: 24 item."
  );

  Serial.println(
    "[SYSTEM] Antrean Laravel aktif."
  );

  Serial.println("=================================================");
}

// =================================================================
// 22. LOOP UTAMA
// =================================================================

void loop() {

  unsigned long currentTime = millis();

  // ---------------------------------------------------------------
  // A. DEBUG SENSOR IR
  // ---------------------------------------------------------------

  static unsigned long lastDebugIR = 0;

  if (currentTime - lastDebugIR >= 1000) {

    lastDebugIR = currentTime;

    Serial.print("[DEBUG IR] Status Sensor: ");

    Serial.println(
      digitalRead(PIN_IR) == LOW
        ? "LOW (Ada Halangan)"
        : "HIGH (Kosong)"
    );
  }

  // ---------------------------------------------------------------
  // B. DEBUG WIFI BERKALA
  // ---------------------------------------------------------------

  static unsigned long lastDebugWiFi = 0;

  if (currentTime - lastDebugWiFi >= 10000) {

    lastDebugWiFi = currentTime;

    if (WiFi.status() != WL_CONNECTED) {
      cetakDebugWiFi();
    }
  }

  // ---------------------------------------------------------------
  // C. DETEKSI PAKAIAN
  // ---------------------------------------------------------------

  bool irCurrentState = digitalRead(PIN_IR);

  if (
    irCurrentState == LOW &&
    irLastState == HIGH &&
    currentTime - lastIRTriggerTime >= IR_DEBOUNCE
  ) {

    lastIRTriggerTime = currentTime;

    // Batasi pengujian sampai 24 item.
    if (clothesCount >= MAX_TOTAL_CAPACITY) {

      Serial.println();
      Serial.println(
        "[KAPASITAS PENUH] Semua 3 rak sudah mencapai 24 item."
      );

      irLastState = irCurrentState;

    } else {

      int calonCount = clothesCount + 1;

      int slotSekarang =
        ((calonCount - 1) % MAX_CAPACITY) + 1;

      String rakSekarang = getKodeRak(calonCount);

      double rpmKirim =
        currentRPM > 0 ? currentRPM : targetRPM;

      bool berhasilAntre = tambahAntrean(
        rpmKirim,
        calonCount,
        slotSekarang,
        rakSekarang
      );

      if (berhasilAntre) {

        clothesCount = calonCount;

        if (
          xSemaphoreTake(queueMutex, portMAX_DELAY) == pdTRUE
        ) {

          preferences.putInt("clothes", clothesCount);

          xSemaphoreGive(queueMutex);
        }

        Serial.println();

        Serial.println(
          "[!] PAKAIAN TERDETEKSI!"
        );

        Serial.printf(
          "[SENSOR] Total Item: %d\n",
          clothesCount
        );

        Serial.printf(
          "[SENSOR] Rak: %s\n",
          rakSekarang.c_str()
        );

        Serial.printf(
          "[SENSOR] Slot: %d/8\n",
          slotSekarang
        );

        updateLEDStatus(clothesCount);

        Serial.println(
          "[SENSOR] Event masuk antrean Laravel."
        );

      } else {

        Serial.println(
          "[PERINGATAN] Event gagal masuk antrean."
        );
      }
    }
  }

  irLastState = irCurrentState;

  // ---------------------------------------------------------------
  // D. PERHITUNGAN RPM DAN PID SETIAP 200 MS
  // ---------------------------------------------------------------

  if (currentTime - lastRPMCalcTime >= 200) {

    noInterrupts();

    unsigned long pulses = encoderPulseCount;

    encoderPulseCount = 0;

    interrupts();

    rawRPM = (pulses / 20.0) * 300.0;

    currentRPM =
      (0.7 * rawRPM) + (0.3 * currentRPM);

    errorNow = targetRPM - currentRPM;

    integralSum = constrain(
      integralSum + (errorNow * deltaT),
      -255.0,
      255.0
    );

    derivativeErr =
      (errorNow - errorPrev) / deltaT;

    errorPrev = errorNow;

    double u_k =
      (Kp * errorNow) +
      (Ki * integralSum) +
      (Kd * derivativeErr);

    motorPWM = constrain(
      (int)u_k,
      80,
      255
    );

    ledcWrite(PIN_ENA, motorPWM);

    lastRPMCalcTime = currentTime;

    static unsigned long lastPIDPrint = 0;

    if (currentTime - lastPIDPrint >= 1000) {

      lastPIDPrint = currentTime;

      Serial.printf(
        "[PID] RPM: %.1f | Target: %.1f | PWM: %d\n",
        currentRPM,
        targetRPM,
        motorPWM
      );
    }
  }
}
