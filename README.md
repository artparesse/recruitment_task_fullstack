# 💱 Kantor Walutowy - System Kursów NBP

**Profesjonalna aplikacja kantorowa** dla pracowników kantoru do obsługi klientów w zakresie wymiany walut. System dostarcza aktualne kursy walut z NBP API z automatycznym wyliczeniem marż kupna i sprzedaży.

![Aplikacja Kantorowa](assets/img/working_app_preview.png)

## 📋 Spis treści
- [Cel aplikacji](#-cel-aplikacji)
- [Funkcjonalności](#-funkcjonalności)
- [Obsługiwane waluty](#-obsługiwane-waluty)
- [Quick Start](#-quick-start)
- [Szczegółowa instalacja](#-szczegółowa-instalacja)
- [API Documentation](#-api-documentation)
- [Testowanie](#-testowanie)
- [Architektura](#-architektura)
- [Konfiguracja](#-konfiguracja)
- [Performance](#-performance--monitoring)
- [Development](#-development)

## 🎯 Cel aplikacji

Aplikacja służy **pracownikowi kantoru** do codziennej pracy związanej z obsługą klientów w zakresie wymiany walut:

- ✅ **Bieżące kursy walut** z automatycznym wyliczeniem kursów kupna i sprzedaży
- ✅ **Historia kursów** z ostatnich 14 dni przed wybraną datą
- ✅ **Szybki dostęp** do aktualnych informacji niezbędnych do obsługi klientów  
- ✅ **Narzędzie wspomagające** podejmowanie decyzji biznesowych w oparciu o trendy kursowe

### Problem biznesowy
Pracownik kantoru potrzebuje szybkiego dostępu do:
- Aktualnych kursów NBP z marżami kantoru
- Historii kursów do analizy trendów
- Niezawodnej aplikacji działającej podczas wysokiego ruchu

## 🚀 Funkcjonalności

### 🏪 Dla pracownika kantoru
- **Dashboard kursów** - wszystkie obsługiwane waluty w jednym miejscu
- **Kursy kupna/sprzedaży** - automatyczne wyliczenie marż kantorowych
- **Historia kursów** - analiza trendów z ostatnich 14 dni
- **Wybór daty referencyjnej** - sprawdzenie kursów z dowolnego dnia
- **Responsive design** - działanie na desktop i tablet

### 🔧 Techniczne
- **Real-time data** z Narodowego Banku Polskiego (NBP API)
- **Inteligentne cache'owanie** - szybkie odpowiedzi (< 100ms)
- **Graceful error handling** - aplikacja działa nawet gdy NBP API nie odpowiada
- **Automatyczne odświeżanie** - kursy aktualizowane co 5 minut
- **Health monitoring** - status systemu i API

## 💰 Obsługiwane waluty

| Waluta | Kod | Kupno | Sprzedaż | Marża |
|--------|-----|-------|----------|-------|
| **Euro** | EUR | ✅ | ✅ | -0.15 PLN / +0.11 PLN |
| **Dolar amerykański** | USD | ✅ | ✅ | -0.15 PLN / +0.11 PLN |
| **Korona czeska** | CZK | ❌ | ✅ | +0.20 PLN |
| **Rupia indonezyjska** | IDR | ❌ | ✅ | +0.20 PLN |
| **Real brazylijski** | BRL | ❌ | ✅ | +0.20 PLN |

### Logika marż
```
EUR/USD (Tier 1 - pełna obsługa):
- Kurs kupna = kurs NBP - 0.15 PLN
- Kurs sprzedaży = kurs NBP + 0.11 PLN

CZK/IDR/BRL (Tier 2 - tylko sprzedaż):
- Kurs kupna = nie dostępny
- Kurs sprzedaży = kurs NBP + 0.20 PLN
```

## ⚡ Quick Start

```bash
# 1. Klonowanie i instalacja
git clone <repository-url>
cd recruitment_task_fullstack
composer install
npm install

# 2. Uruchomienie
php -S localhost:8000 -t public/    # Backend API
npm run watch                       # Frontend (opcjonalnie)

# 3. Otwarcie aplikacji
open http://localhost:8000
```

**🎉 Gotowe!** Aplikacja działa na `http://localhost:8000`

## 📦 Szczegółowa instalacja

### Wymagania systemowe
- **PHP 8.2+** (z rozszerzeniami: json, curl, mbstring)
- **Composer** (dependency manager dla PHP)
- **Node.js 16+** i **npm** (dla frontend assets)
- **Dostęp do internetu** (dla NBP API)

### Krok po kroku

#### 1. Backend (PHP/Symfony)
```bash
# Instalacja zależności PHP
composer install

# Sprawdzenie konfiguracji
php bin/console about

# Opcjonalne: warmup cache
php bin/console currency:warmup
```

#### 2. Frontend (React)
```bash
# Instalacja zależności Node.js
npm install

# Development build z watch mode
npm run watch

# LUB Production build
npm run build
```

#### 3. Uruchomienie serwera
```bash
# Symfony built-in server (development)
php -S localhost:8000 -t public/

# LUB z Docker (jeśli dostępny)
docker-compose up -d
```

#### 4. Weryfikacja instalacji
```bash
# Test API endpoint
curl http://localhost:8000/api/currencies/current

# Test health check
curl http://localhost:8000/api/currencies/health
```

## 📡 API Documentation

### 🔄 Aktualne kursy walut
```http
GET /api/currencies/current
```

**Response:**
```json
{
  "success": true,
  "data": {
    "EUR": {
      "currency": "EUR",
      "name": "Euro",
      "baseRate": 4.3421,
      "buyRate": 4.1921,      // baseRate - 0.15
      "sellRate": 4.4521,     // baseRate + 0.11
      "supportsBuying": true
    },
    "CZK": {
      "currency": "CZK", 
      "name": "Czech Koruna",
      "baseRate": 0.1876,
      "buyRate": null,         // Nie skupujemy
      "sellRate": 0.3876,     // baseRate + 0.20
      "supportsBuying": false
    }
  }
}
```

### 📊 Historia kursów (14 dni)
```http
GET /api/currencies/{currency}/history
GET /api/currencies/{currency}/history?days=30
```

**Example:**
```bash
curl "http://localhost:8000/api/currencies/EUR/history?days=7"
```

**Response:**
```json
{
  "success": true,
  "data": {
    "currency": "EUR",
    "fromDate": "2024-01-08", 
    "toDate": "2024-01-15",
    "count": 5,               // Business days only
    "rates": [
      {
        "date": "2024-01-08",
        "baseRate": 4.3421,
        "buyRate": 4.1921,
        "sellRate": 4.4521
      }
    ]
  }
}
```

### 📅 Historia od wybranej daty
```http
GET /api/currencies/{currency}/history/{date}
GET /api/currencies/{currency}/history/{date}?days=20
```

**Example:**
```bash
curl "http://localhost:8000/api/currencies/USD/history/2024-01-10?days=14"
```

### 🏥 Health check
```http
GET /api/currencies/health
```

**Response:**
```json
{
  "success": true,
  "data": {
    "status": "ok",
    "timestamp": "2024-01-15T10:30:00+00:00",
    "checks": {
      "nbp_api": {
        "status": "healthy",
        "available_currencies": 5
      },
      "cache": {
        "status": "healthy",
        "architecture": "simplified_single_level"
      },
      "configuration": {
        "status": "healthy",
        "supported_currencies": 5
      }
    },
    "performance": {
      "response_time_ms": 45.67,
      "memory_usage_mb": 12.34,
      "peak_memory_mb": 15.67
    }
  }
}
```

## 🧪 Testowanie

### Uruchomienie testów
```bash
# Wszystkie testy (89 testów, 486 asercji)
php bin/phpunit

# Tylko testy jednostkowe
php bin/phpunit tests/Unit/

# Tylko testy integracyjne  
php bin/phpunit tests/Integration/

# Testy z verbose output
php bin/phpunit --verbose

# Testy konkretnej klasy
php bin/phpunit tests/Unit/Service/CurrencyRateServiceTest.php
```

### Pokrycie testami

| Komponent | Testy | Asercje | Pokrycie |
|-----------|-------|---------|----------|
| **CurrencyRateService** | 14 | 105 | Margin calculations, validation |
| **NBPCurrencyRepository** | 14 | 98 | NBP API integration, fallbacks |
| **DateHelperService** | 22 | 87 | Business days, edge cases |
| **ConfigurationService** | 19 | 76 | YAML loading, error handling |
| **CurrencyController** | 16 | 95 | API endpoints, HTTP responses |
| **Integration Tests** | 17 | 78 | Full request cycle, performance |

**Total: 89 testów, 486 asercji, 2075 linii kodu testowego**

### Typy testów

#### 🔬 Unit Tests
```bash
# Testowanie logiki biznesowej
php bin/phpunit tests/Unit/Service/CurrencyRateServiceTest.php

# Testowanie integracji z NBP API  
php bin/phpunit tests/Unit/Repository/NBPCurrencyRepositoryTest.php

# Testowanie edge cases (daty, weekendy)
php bin/phpunit tests/Unit/Service/DateHelperServiceTest.php
```

#### 🔗 Integration Tests  
```bash
# Testowanie API endpoints
php bin/phpunit tests/Integration/CurrencyControllerTest.php

# Testowanie performance
php bin/phpunit tests/Integration/CurrencyControllerTest.php::testApiResponseTime
```

#### 🎯 Test Features
- **Mock strategies** dla NBP API (success/failure scenarios)
- **Realistic fixtures** z prawdziwymi danymi NBP
- **Edge case testing** (weekendy, nieprawidłowe daty, timeouts)
- **Performance testing** (response time < 2000ms)
- **Error handling** (graceful degradation)

## 🏗️ Architektura

### Diagram architektury
```
Frontend (React)     Backend (PHP/Symfony)           External
     │                        │                        │
┌────▼────┐              ┌────▼────┐              ┌─────▼─────┐
│  React  │              │Controller│              │  NBP API  │
│   SPA   │◄────────────►│          │              │           │
└─────────┘              └────┬────┘              └─────▲─────┘
                              │                         │
                         ┌────▼────┐              ┌─────┴─────┐
                         │ Service │              │Repository │
                         │  Layer  │◄────────────►│  Pattern  │
                         └─────────┘              └───────────┘
                                                        │
                                                  ┌─────▼─────┐
                                                  │  Symfony  │
                                                  │   Cache   │
                                                  └───────────┘
```

### Warstwy aplikacji

#### 🎨 Frontend (React 17)
- **Single Page Application** z React Router DOM 5.3.4
- **Axios HTTP Client** dla komunikacji z API
- **Custom Hooks** (`useCurrencyRates`, `useHistoricalRates`)
- **Error Boundary** dla graceful error handling
- **Responsive Components** (Desktop + Tablet)

#### 🔧 Backend (Symfony 4.4)
```php
Controller Layer    // HTTP handling, validation, responses
    ↓
Service Layer      // Business logic, margin calculations  
    ↓
Repository Layer   // NBP API integration, caching
    ↓
External API       // NBP API, error handling
```

### Wzorce projektowe
- **Repository Pattern** - abstrakcja dostępu do NBP API
- **Service Layer** - logika biznesowa oddzielona od kontrolerów
- **DTO Pattern** - strukturalne dane (HistoricalRateDTO, HistoricalRatesCollectionDTO)
- **YAML Configuration** - externalized configuration
- **Dependency Injection** - loose coupling
- **SOLID Principles** - clean, maintainable code

## ⚙️ Konfiguracja

### Główny plik konfiguracyjny
**`config/currency.yaml`**
```yaml
# Obsługiwane waluty z marżami
currencies:
  EUR: 
    name: "Euro"
    buy_margin: -0.15  # Kupujemy taniej o 15 groszy
    sell_margin: 0.11  # Sprzedajemy drożej o 11 groszy
  USD: 
    name: "US Dollar"  
    buy_margin: -0.15
    sell_margin: 0.11
  CZK: 
    name: "Czech Koruna"
    buy_margin: null   # Nie skupujemy
    sell_margin: 0.2   # Tylko sprzedaż +20 groszy
  IDR: 
    name: "Indonesian Rupiah"
    buy_margin: null
    sell_margin: 0.2
  BRL: 
    name: "Brazilian Real"
    buy_margin: null
    sell_margin: 0.2

# NBP API endpoints
nbp_api:
  tables_today_url: "https://api.nbp.pl/api/exchangerates/tables/A/today/?format=json"
  tables_url: "https://api.nbp.pl/api/exchangerates/tables/A/?format=json"  
  rates_url: "https://api.nbp.pl/api/exchangerates/rates/A/{currency}?format=json"
  historical_last_url: "https://api.nbp.pl/api/exchangerates/tables/A/last/{count}?format=json"

# Cache settings
cache:
  ttl:
    nbp_api: 3600        # 1 godzina cache dla NBP API
    currency_rates: 3600 # 1 godzina cache dla kursów
```

### Dodawanie nowej waluty
1. **Dodaj w `config/currency.yaml`:**
```yaml
currencies:
  GBP:  # Nowa waluta
    name: "British Pound"
    buy_margin: -0.15    # lub null jeśli nie skupujemy
    sell_margin: 0.15    # marża sprzedaży
```

2. **Restart aplikacji** - konfiguracja zostanie automatycznie załadowana

### Zmiana marż
Marże można zmieniać w pliku konfiguracyjnym bez zmiany kodu:
```yaml
currencies:
  EUR:
    buy_margin: -0.20  # Zwiększona marża kupna
    sell_margin: 0.15  # Zwiększona marża sprzedaży
```

## 📈 Performance & Monitoring

### Cache Strategy
- **NBP API responses** - cache 1h (dane się rzadko zmieniają)
- **HTTP Cache Headers** - 5min dla current rates, 1h dla historical
- **Configuration cache** - 2h (statyczne dane)
- **Graceful fallback** - stare dane cache w przypadku awarii NBP

### Performance Metrics
- **API Response Time** < 100ms (z cache)  
- **NBP API Response Time** < 2000ms (bez cache)
- **Memory Usage** < 20MB per request
- **Concurrent Users** - testowane do 50 równoczesnych

### Monitoring Tools
```bash
# Health check
curl http://localhost:8000/api/currencies/health

# Cache warmup (preload)
php bin/console currency:warmup

# Cache warmup z force refresh
php bin/console currency:warmup --force
```

### Error Handling
- **NBP API down** - używane cached data z informacją o wieku
- **Invalid currency** - clear error message z listą obsługiwanych
- **Network timeout** - retry mechanism z exponential backoff
- **Invalid dates** - validation z helpful error messages

## 🛠️ Development

### Code Standards
- **PSR-12** - PHP coding standards
- **SOLID Principles** - clean architecture  
- **DRY** - business logic nie duplikowana
- **Comprehensive Testing** - 89 testów z 486 asercjami

### Debugging
```bash
# Symfony debug info
php bin/console about

# Check configuration
php bin/console debug:config

# View routes
php bin/console debug:router

# Test specific currency
curl -v "http://localhost:8000/api/currencies/EUR/history"
```

### Environment Setup
```bash
# Development
export APP_ENV=dev
php -S localhost:8000 -t public/

# Production-like testing  
export APP_ENV=prod
php bin/console cache:clear --env=prod
php -S localhost:8000 -t public/
```
