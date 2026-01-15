import "dotenv/config";
import { startClient, stopClient } from "./client";
import { validateEnv } from "./config/validateEnv";
import { logger } from "./logger";
import { startServer } from "./server";

const port = Number(process.env.PORT || 3001);

validateEnv();
startServer(port);

startClient().catch((error) => {
  logger.error({ err: error }, "Failed to start WhatsApp client");
});

const shutdown = (signal: string) => {
  logger.info({ signal }, "Shutting down WA gateway");
  stopClient();
  process.exit(0);
};

process.on("SIGINT", () => shutdown("SIGINT"));
process.on("SIGTERM", () => shutdown("SIGTERM"));
