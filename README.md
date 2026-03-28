# Resolvr API

A support ticket portal built with Laravel 13 where customer organisations submit and manage support tickets, and a support team handles them.

## Tech Stack

- **Backend:** Laravel 13, PHP 8.4
- **Authentication:** Laravel Sanctum v4 (token-based)
- **Database:** MySQL/MariaDB
- **Frontend:** SvelteKit (separate project — `resolvr-frontend`)
- **Testing:** Pest v4
- **Code Quality:** Larastan (PHPStan), Laravel Pint

## Getting Started

```bash
# Install dependencies
composer install

# Copy environment file and configure database
cp .env.example .env
php artisan key:generate

# Run migrations and seed demo data
php artisan migrate:fresh --seed

# The seeder creates:
#   - 3 organisations (Envolutions, xDx, Data Engine)
#   - 5 agents (agent1@gmail.com ... agent5@gmail.com)
#   - ~30-60 clients across organisations (client1@gmail.com, client2@gmail.com, ...)
#   - 14 tickets with messages across all statuses and SLA states
#
# Default password for all seeded users: "password"
```

## Architecture Overview

### Dual-Panel API Design

The API is split into two separate route groups, each with its own controllers, policies, and middleware:

```
/api/v1/agent/*   — auth:sanctum + abilities:role:agent
/api/v1/client/*  — auth:sanctum + abilities:role:client
```

This keeps agent and client logic cleanly separated. Each panel has its own `AuthController`, `TicketController`, `TicketMessageController`, and `ProfileController`. Shared logic lives in service classes.

### Data Model

```
Organisation
 └── has many Clients

User (role: agent | client)
 ├── has one Agent profile   (name, email, password)
 └── has one Client profile  (name, email, password, organisation_id)

Ticket
 ├── belongs to Organisation
 ├── belongs to User (issuer — the client who created it)
 ├── belongs to User (assignee — nullable, an agent)
 └── has many TicketMessages

TicketMessage
 ├── belongs to Ticket
 ├── belongs to User (author)
 └── is_internal (boolean — internal notes only visible to agents)
```

**Why separate Agent/Client models from User?**
The `User` model handles authentication and role. `Agent` and `Client` are profile models with role-specific fields. Passwords are stored on the profile models (not User), so each role authenticates against its own credentials. This allows a clean separation — the User table stays minimal, and role-specific data doesn't leak across concerns.

### Service Layer

Business logic lives in service classes, not controllers:

- **`TicketService`** — Ticket CRUD, priority/status updates, assignee changes, SLA recalculation, auto-assignment, advanced filtering
- **`TicketMessageService`** — Message pagination (with internal note filtering for clients), message creation
- **`AgentAuthService` / `ClientAuthService`** — Login and token generation
- **`AgentProfileService` / `ClientProfileService`** — Profile retrieval

Controllers are thin — they authorize, delegate to services, and return resources.

### Authorization

Authorization uses custom policy classes invoked via an `authorizePolicy()` helper on the base Controller.

**Agent Ticket Policy:**
| Action | Rule |
|--------|------|
| `viewAny` | Must have an agent profile |
| `view` | Any agent can view any ticket |
| `update` | Only the assigned agent can update (status, priority, messages) |

**Client Ticket Policy:**
| Action | Rule |
|--------|------|
| `viewAny` | Must have a client profile |
| `view` | Can only view tickets from own organisation |
| `create` | Must have a client profile |

Clients are read-only on ticket fields — they can create tickets and send messages, but cannot change priority, status, or assignee. Only agents can modify ticket state.

**Internal notes enforcement:** The `TicketMessageService::paginateForClient()` method filters `is_internal = false` at the query level, ensuring internal notes never reach client API responses regardless of what the frontend does.

**Cross-role isolation:** Sanctum token abilities (`role:agent` / `role:client`) enforce that agent tokens cannot access client endpoints and vice versa. A client token hitting `/api/v1/agent/*` gets a 403.

### Response Envelope

All API responses use a consistent wrapper via `app/Utils/Response.php`:

```json
// Success
{
  "success": true,
  "code": "success",
  "message": "Success",
  "data": { ... }
}

// Paginated (items at top level, meta alongside)
{
  "success": true,
  "code": "success",
  "message": "Success",
  "data": [ ... ],
  "meta": { "current_page": 1, "last_page": 5, ... },
  "links": { ... }
}

// Error
{
  "success": false,
  "code": "validation_failed",
  "message": "The title field is required.",
  "errors": { "title": ["The title field is required."] }
}
```

## SLA System

### Priority Levels & Resolution Times

| Priority | Resolution Time | Use Case |
|----------|----------------|----------|
| Low | 8 hours | Feature requests, general inquiries |
| Medium | 6 hours | Non-critical bugs, configuration changes |
| High | 4 hours | Service degradation, broken features |
| Urgent | 2 hours | Service outages, security issues |

### SLA Status Thresholds

SLA status is derived from how much of the resolution time has been consumed:

| SLA Status | Condition | Meaning |
|------------|-----------|---------|
| On Track | < 80% consumed | Plenty of time remaining |
| Due Soon | 80% – 99% consumed | Approaching deadline |
| Overdue | >= 100% consumed | Past SLA deadline |

### SLA Clock Behavior

- **On ticket creation:** `due_at` is calculated as `now + resolution_time` based on priority.
- **On priority change (agent only):** Resolution time is recalculated, `due_at` and `sla_status` are updated.
- **On hold:** The SLA clock pauses. `last_sla_paused_at` records when the pause started.
- **Off hold (back to open):** The paused duration is added to `sla_paused_time`, and the clock resumes. `due_at` extends accordingly.
- **Multiple holds:** Paused durations accumulate in `sla_paused_time`.

The `SlaTimeCalculator` handles all of this:

```
consumed_time = (now - created_at) - total_paused_time - current_hold_duration
sla_percentage = consumed_time / sla_resolution_time * 100
due_at = created_at + sla_resolution_time + total_paused_time + current_hold_duration
```

### Scheduled SLA Updates

A scheduled command recalculates SLA status for all open tickets:

```bash
php artisan app:update-ticket-sla-status
```

- Runs every 2 minutes via the scheduler (`withoutOverlapping`)
- Only processes `Open` tickets (on-hold tickets have a paused clock)
- Uses `chunkById(1000)` for memory efficiency with large datasets
- Batch updates by new SLA status (groups changed ticket IDs, single `UPDATE ... WHERE IN`)
- Sets `overdue_at` timestamp when a ticket first becomes overdue
- Designed for 1M+ ticket scale

### Auto-Assignment

When a client creates a ticket, the system automatically assigns it to the agent with the fewest active (open + on-hold) tickets. This is a simple load-balanced assignment:

```sql
SELECT agents.user_id
FROM agents
LEFT JOIN (active ticket counts) ON agents.user_id = assignee_id
ORDER BY active_count ASC, agents.user_id ASC
LIMIT 1
```

## API Endpoints

### Agent Panel

| Method | Endpoint | Purpose |
|--------|----------|---------|
| `POST` | `/api/v1/agent/login` | Login with email + password |
| `GET` | `/api/v1/agent/profile` | Get authenticated agent's profile |
| `GET` | `/api/v1/agent/agents` | List all agents (for assignee dropdown) |
| `GET` | `/api/v1/agent/organizations` | List all organisations (for filter dropdown) |
| `GET` | `/api/v1/agent/tickets` | List tickets (filterable, eager loads assignee/issuer/org) |
| `GET` | `/api/v1/agent/tickets/{id}` | View ticket with assignee, issuer, and organisation details |
| `PATCH` | `/api/v1/agent/tickets/{id}/priority` | Update priority (recalculates SLA) |
| `PATCH` | `/api/v1/agent/tickets/{id}/status` | Update status (manages SLA pause/resume) |
| `PATCH` | `/api/v1/agent/tickets/{id}/assignee` | Reassign ticket to another agent |
| `GET` | `/api/v1/agent/tickets/{id}/messages` | List messages (includes internal notes) |
| `POST` | `/api/v1/agent/tickets/{id}/messages` | Send message (with optional `is_internal` flag) |

### Client Panel

| Method | Endpoint | Purpose |
|--------|----------|---------|
| `POST` | `/api/v1/client/login` | Login with email + password |
| `GET` | `/api/v1/client/profile` | Get authenticated client's profile |
| `GET` | `/api/v1/client/agents` | List agents (for displaying assignee names) |
| `GET` | `/api/v1/client/tickets` | List own/organisation tickets (filterable, eager loads assignee) |
| `POST` | `/api/v1/client/tickets` | Create a new ticket |
| `GET` | `/api/v1/client/tickets/{id}` | View ticket with assignee details |
| `GET` | `/api/v1/client/tickets/{id}/messages` | List messages (internal notes excluded) |
| `POST` | `/api/v1/client/tickets/{id}/messages` | Send message (always public) |

Clients cannot update priority, status, or assignee — those endpoints do not exist on the client panel.

### Ticket Filters (query params on list endpoints)

| Filter | Agent | Client | Description |
|--------|-------|--------|-------------|
| `type` | `mine` / `all` | `mine` / `all` | My assigned/issued vs all |
| `search` | Yes | Yes | Keyword search in title + message content |
| `organization_id` | Yes | No | Filter by organisation |
| `priority` | Yes | Yes | `low`, `medium`, `high`, `urgent` |
| `status` | Yes | Yes | `open`, `on-hold`, `resolved`, `archived` |
| `sla_status` | Yes | Yes | `on-track`, `due-soon`, `overdue` |
| `started_from` / `started_to` | Yes | Yes | Date range on started_at |
| `due_from` / `due_to` | Yes | Yes | Date range on due_at |

## Testing

```bash
# Run all tests
php artisan test --compact

# Run specific test files
php artisan test --filter=AgentTicketAuthorizationTest
php artisan test --filter=ClientTicketAuthorizationTest
php artisan test --filter=CrossRoleAccessTest
php artisan test --filter=UpdateTicketSlaStatusCommandTest
php artisan test --filter=TicketSlaTest
php artisan test --filter=SlaTimeCalculatorTest
php artisan test --filter=SlaTimeGeneratorTest
```

### Test Coverage

| Test File | Tests | What It Covers |
|-----------|-------|---------------|
| `AgentTicketAuthorizationTest` | 4 | Agent can view any ticket with full relation data (assignee/issuer/org), can only update assigned tickets, agent sees internal notes in messages |
| `ClientTicketAuthorizationTest` | 4 | Org-scoped visibility, priority and status endpoints return 404 for clients, internal notes are never exposed to clients |
| `CrossRoleAccessTest` | 3 | Client token cannot access agent endpoints, agent token cannot access client endpoints, unauthenticated requests return 401 |
| `UpdateTicketSlaStatusCommandTest` | 5 | SLA transitions (on-track → due-soon → overdue), `overdue_at` timestamping, on-hold/resolved/archived tickets skipped, no-op when unchanged |
| `TicketSlaTest` | 22 | Ticket creation with SLA, auto-assignment load balancing, priority updates recalculate SLA, status transitions with pause/resume tracking |
| `SlaTimeCalculatorTest` | 13 | Consumed time calculation, percentage with edge cases, status thresholds, due date with paused time |
| `SlaTimeGeneratorTest` | 5 | Resolution time per priority level |

**Total: 56 tests, 123 assertions**

Tests focus on the areas that matter most: SLA correctness, authorization boundaries, internal note visibility, and cross-role isolation. Response body assertions verify data shape and content, not just HTTP status codes.

## Code Quality

```bash
# Static analysis
vendor/bin/phpstan analyse

# Code formatting
vendor/bin/pint --dirty
```

## Project Structure

```
app/
├── Console/Commands/          # Artisan commands (SLA update)
├── Enums/                     # TicketStatus, TicketSlaPriority, TicketSlaStatus, UserRole
├── Helpers/                   # Global helper functions (successResponse, errorResponse)
├── Http/
│   ├── Controllers/Api/V1/
│   │   ├── Agent/             # Agent controllers (Ticket, Message, Profile, Auth, Agent, Organization)
│   │   └── Client/            # Client controllers (Ticket, Message, Profile, Auth, Agent)
│   ├── Requests/Api/V1/       # Form request validation (shared + role-specific)
│   └── Resources/Api/V1/      # API resource transformers (Ticket, TicketMessage, User, Profile, Organization)
├── Models/                    # Eloquent models (User, Agent, Client, Ticket, TicketMessage, Organization)
├── Policies/
│   ├── Agent/                 # Agent authorization policies
│   └── Client/                # Client authorization policies
├── Services/                  # Business logic layer
└── Utils/                     # SLA calculation utilities, response wrapper

routes/
├── api-agent.php              # Agent API routes
├── api-client.php             # Client API routes
└── console.php                # Scheduled commands

database/
├── factories/                 # Model factories for testing
├── migrations/                # Database schema
└── seeders/                   # Demo data seeders (organisations, users, tickets with messages)

tests/
├── Feature/                   # Integration tests (auth, SLA, cross-role, command)
└── Unit/                      # Unit tests (SLA generator)
```
