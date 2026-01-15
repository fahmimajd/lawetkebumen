const requiredEnv = ["WA_GATEWAY_TOKEN", "WEBHOOK_URL", "WEBHOOK_SECRET"] as const;

export const validateEnv = (): void => {
  const missing = requiredEnv.filter((name) => {
    const value = process.env[name];
    return value == null || value === "";
  });

  if (missing.length > 0) {
    throw new Error(`Missing required env: ${missing.join(", ")}`);
  }
};
