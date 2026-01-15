# WhatsApp Web Inbox Monorepo

Production-ready scaffold for a WhatsApp Web Inbox system. No business logic yet.

## Services
- Laravel API (PHP 8.2, auto-bootstraps into `apps/api-laravel/app`)
- Node WA gateway (Node 20 + TypeScript)
- PostgreSQL (chosen DB)
- Redis
- Nginx reverse proxy (recommended)
- Reverb WebSocket server
- Queue worker
- Vite dev server (optional)

## Ports
- Laravel (via Nginx): http://localhost:9000
- Node: http://localhost:9001
- Reverb WebSocket: ws://localhost:8080
- Vite dev server: http://localhost:5173
- PostgreSQL: localhost:5432
- Redis: localhost:6379

For access from another machine, use your host IP (e.g. `http://<host-ip>:9000`) and ensure the firewall allows 9000/9001.
For Reverb, use `ws://<host-ip>:8080` and open port 8080.
For Vite HMR from another machine, use `http://<host-ip>:5173`.

## How to run
1) Copy environment files:
   - `cp .env.example .env`
   - `cp apps/api-laravel/.env.example apps/api-laravel/app/.env`
   - `cp apps/wa-gateway-node/.env.example apps/wa-gateway-node/.env`
2) Start the stack:
   - `docker compose up --build`

On first run, the Laravel container will bootstrap a fresh Laravel skeleton into `apps/api-laravel/app`.

## Laravel 11 Auth & Roles
- Users table includes a `role` enum (`admin`, `agent`) with default `agent`.
- Authorization gates live in `apps/api-laravel/app/app/Providers/AuthServiceProvider.php`.
- JSON auth endpoints (no Blade UI):
  - `POST /login` for session-based login
  - `POST /logout` (requires auth)
- Horizon config is ready in `apps/api-laravel/app/config/horizon.php` (queue only).

## Tests
- Login feature test: `apps/api-laravel/app/tests/Feature/Auth/LoginTest.php`
- Run with `php artisan test`

## WA Gateway (Baileys)
- Environment: `apps/wa-gateway-node/.env.example` (set `WA_AUTH_DIR`, `WEBHOOK_URL`, `WEBHOOK_SECRET`).
- Run locally:
  - `cd apps/wa-gateway-node`
  - `npm install`
  - `npm run dev`
- Health endpoint: `GET http://localhost:9001/health`

## Realtime Updates (Reverb + Echo)
- Broadcasts:
  - `ConversationUpdated` on `conversations`
  - `MessageCreated` on `conversation.{id}`
- Keep payloads small; fetch full data via API.

Frontend JS example (Echo):
```js
import Echo from "laravel-echo";
import Pusher from "pusher-js";

window.Pusher = Pusher;

const echo = new Echo({
  broadcaster: "reverb",
  key: "local",
  wsHost: "localhost",
  wsPort: 8080,
  forceTLS: false,
  disableStats: true,
});

echo.channel("conversations").listen("ConversationUpdated", (event) => {
  // Fetch full conversation detail via API using event.id
  console.log("Conversation updated", event);
});

echo.channel("conversation.1").listen("MessageCreated", (event) => {
  // Fetch new message by event.message_id
  console.log("Message created", event);
});
```

## Structure
- `apps/api-laravel`: Laravel API container and app root
- `apps/wa-gateway-node`: Node gateway container and TypeScript app
- `packages/contracts`: Shared contracts/DTOs
- `docs`: Documentation
- `infra`: Infrastructure configs (Nginx)
