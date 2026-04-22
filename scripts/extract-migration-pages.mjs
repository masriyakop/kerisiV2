#!/usr/bin/env node
// Small helper used during migration: extract per-PAGEID records into JSON files.
import { readFileSync, writeFileSync, mkdirSync } from "node:fs";
import { dirname, resolve } from "node:path";

const SRC = "C:/KerisiAI/02MigrateFromOldKerisi/JSON_DATA/PAGE_SETUP_MAINTENANCE-level2.json";
const targetPageIds = [2911, 1715, 2253, 2664];
const outDir = resolve("scripts/.migration-cache");
mkdirSync(outDir, { recursive: true });

const raw = readFileSync(SRC, "utf8");
const data = JSON.parse(raw);
console.log(`Total records: ${data.length}`);

for (const pid of targetPageIds) {
  const recs = data.filter((r) => r.PAGEID === pid);
  const f = resolve(outDir, `page-${pid}.json`);
  writeFileSync(f, JSON.stringify(recs, null, 2));
  console.log(`Wrote ${recs.length} records -> ${f}`);
}
