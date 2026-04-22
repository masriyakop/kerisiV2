#!/usr/bin/env node
import { readFileSync, writeFileSync, mkdirSync } from "node:fs";
import { resolve } from "node:path";

const SRC = "C:/KerisiAI/02MigrateFromOldKerisi/JSON_DATA/FLC_TRIGGER_PAGE.json";
const targetPageIds = [2911, 1715, 2253, 2664];
const outDir = resolve("scripts/.migration-cache");
mkdirSync(outDir, { recursive: true });

const raw = readFileSync(SRC, "utf8");
const data = JSON.parse(raw);
console.log(`FLC_TRIGGER_PAGE total: ${data.length}`);

for (const pid of targetPageIds) {
  const recs = data.filter((r) => r.PAGEID === pid);
  const f = resolve(outDir, `trigger-${pid}.json`);
  writeFileSync(f, JSON.stringify(recs, null, 2));
  console.log(`PAGEID ${pid}: ${recs.length} triggers -> ${f}`);
}
