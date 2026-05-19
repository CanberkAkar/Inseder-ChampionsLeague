# ⚽ Champions League Simulator

A full-stack Champions League group stage simulation built with **Laravel** (backend) and **Vue.js 3** (frontend), running as separate Docker containers.

---

## 🏗️ Architecture

```
┌─────────────────────────────────────────────────┐
│              Nginx (Reverse Proxy :80)           │
│  /api/* → backend:9000  |  /* → frontend:5173   │
└──────────────┬──────────────────┬───────────────┘
               │                  │
    ┌──────────▼──────┐  ┌───────▼────────┐
    │  Laravel Backend │  │  Vue.js 3 +    │
    │  PHP 8.2 + FPM  │  │  Vite Frontend │
    │  OOP / SOLID    │  │  Pinia + TS    │
    └──────────┬──────┘  └────────────────┘
               │
    ┌──────────▼──────┐
    │   MySQL 8.0     │
    └─────────────────┘
```

---

## 🚀 Quick Start

```bash
# 1. Clone the repo
git clone <repo-url>
cd champions-league

# 2. Copy environment file
cp .env.example .env

# 3. Build and start all containers
docker-compose up --build

# 4. Open in browser
open http://localhost
```

> On first run, migrations and seeds run automatically.

---

## 🎮 Features

| Feature | Description |
|---------|-------------|
| **League Table** | Real-time standings (Points, W/D/L, GD) |
| **Play Week** | Simulate the next week's matches |
| **Play All** | Simulate all remaining matches at once |
| **Edit Results** | Modify played match scores (standings recalculate) |
| **Predictions** | Monte Carlo championship probability (last 3 weeks) |
| **Reset League** | Start the season over |

---

## 🏛️ Backend Architecture (Laravel OOP/SOLID)

```
app/
├── Interfaces/          # Contracts (Dependency Inversion)
│   ├── FixtureServiceInterface.php
│   ├── SimulationServiceInterface.php
│   ├── StandingServiceInterface.php
│   └── PredictionServiceInterface.php
├── Services/            # Business Logic (Single Responsibility)
│   ├── FixtureService.php      → Round-robin schedule generation
│   ├── SimulationService.php   → Power-based match simulation
│   ├── StandingService.php     → League table management
│   └── PredictionService.php   → Monte Carlo predictions
├── DTOs/
│   └── MatchResultDTO.php      → Typed result transfer object
├── Models/              → Eloquent ORM
└── Http/Controllers/    → Thin controllers (delegating to services)
```

### API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/league` | League state + standings |
| GET | `/api/teams` | All teams |
| GET | `/api/matches` | All matches grouped by week |
| GET | `/api/matches/week/{week}` | Specific week matches |
| POST | `/api/matches/play-week` | Simulate next week |
| POST | `/api/matches/play-all` | Simulate all remaining |
| PUT | `/api/matches/{id}` | Edit a match result |
| GET | `/api/predictions` | Championship probabilities |
| POST | `/api/league/reset` | Reset the league |

---

## 🎨 Frontend Architecture (Vue.js 3)

```
src/
├── types/          # TypeScript interfaces
├── services/       # api.ts — Axios instance + typed endpoints
├── stores/         # Pinia stores (state management)
│   ├── useLeagueStore.ts
│   ├── useMatchStore.ts
│   └── usePredictionStore.ts
├── components/
│   ├── ui/         # Atom components (BaseButton, BaseCard, MatchEditModal)
│   └── league/     # Domain components (LeagueTable, MatchList, PredictionPanel)
└── views/          # Page-level components
    └── LeagueView.vue
```

---

## 🧪 Running Unit Tests

```bash
# Run tests inside backend container
docker-compose exec backend php artisan test

# Or run specific test class
docker-compose exec backend php artisan test --filter=FixtureServiceTest
```

### Test Coverage

| Test Class | What it tests |
|------------|---------------|
| `FixtureServiceTest` | Round-robin generation, match counts, idempotency |
| `StandingServiceTest` | Points calculation, GD ordering, reset |
| `SimulationServiceTest` | Result DTO, goal ranges, stronger team wins more |
| `PredictionServiceTest` | Probability sum = 100%, show/hide logic |

---

## ⚽ Simulation Algorithm

**Team Power Ratings:**
| Team | Power |
|------|-------|
| Manchester City | 92 |
| Real Madrid | 90 |
| Bayern Munich | 88 |
| PSG | 85 |

**Match outcome formula:**
- Home advantage: `+10%` power boost
- Random variance: `±10%` (form/goalkeeper factor)
- Goals: probability-based for each goal attempt (max 5)

**Prediction algorithm:** Monte Carlo simulation (1000 iterations) over remaining matches.

---

## 🐳 Docker Commands

```bash
# Start all services
docker-compose up -d

# View logs
docker-compose logs -f backend
docker-compose logs -f frontend

# Run artisan commands
docker-compose exec backend php artisan migrate:fresh --seed

# Stop all services
docker-compose down

# Remove volumes (reset DB)
docker-compose down -v
```
