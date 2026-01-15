import { createHmac } from "node:crypto";

export const signPayload = (payload: string, secret: string): string => {
  return createHmac("sha256", secret).update(payload).digest("hex");
};
