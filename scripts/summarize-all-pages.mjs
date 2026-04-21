import { readFileSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const cacheDir = path.join(__dirname, '.migration-cache');

const pageIds = [2911, 1715, 2253, 2664];

for (const pid of pageIds) {
  const src = path.join(cacheDir, `page-${pid}.json`);
  const out = path.join(cacheDir, `page-${pid}-summary.json`);
  const records = JSON.parse(readFileSync(src, 'utf8'));
  const components = new Map();
  let blDetails = null;
  for (const r of records) {
    if (!components.has(r.COMPONENTID)) {
      components.set(r.COMPONENTID, {
        COMPONENTID: r.COMPONENTID,
        COMPONENTTITLE: r.COMPONENTTITLE,
        COMPONENTTYPE: r.COMPONENTTYPE,
        DatatableColumnDetails: r['Datatable column details'],
        API_URL: r.API_URL,
        API_BL_NAME: r.API_BL_NAME,
        formItems: [],
      });
      if (r['Business Logic (BL) Details']) {
        blDetails = r['Business Logic (BL) Details'];
      }
    }
    const c = components.get(r.COMPONENTID);
    if (r.Form_Item_Title || r.Form_Item_Type) {
      c.formItems.push({
        title: r.Form_Item_Title,
        type: r.Form_Item_Type,
        cssClass: r.Form_Item_css_class,
        additionalAttr: r.Form_Item_additional_attribute,
        defaultVal: r.Form_Item_default,
        lookupQuery: r.Form_Item_lookup_query,
        isDisable: r.Form_Item_isDisable,
      });
    }
  }
  const summary = {
    page: {
      MENUID: records[0].MENUID,
      PAGEID: records[0].PAGEID,
      PAGETITLE: records[0].PAGETITLE,
      PAGEBREADCRUMBS: records[0].PAGEBREADCRUMBS,
      BL_preprocess_total: records[0].BL_preprocess_total,
      BL_onload_total: records[0].BL_onload_total,
    },
    components: Array.from(components.values()),
    blDetails,
  };
  writeFileSync(out, JSON.stringify(summary, null, 2), 'utf8');
  console.log(`Wrote ${out} — ${components.size} components`);
}
